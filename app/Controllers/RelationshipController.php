<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Models\RelationshipModel;
use App\Services\AuthService;

/**
 * RelationshipController — CRUD de arestas do grafo
 */
class RelationshipController extends BaseController
{
    private RelationshipModel $relModel;
    private EntityModel       $entityModel;
    private AuthService       $auth;

    public function __construct()
    {
        $this->relModel    = new RelationshipModel();
        $this->entityModel = new EntityModel();
        $this->auth        = new AuthService();
    }

    /**
     * GET /relacoes — Listagem
     */
    public function index(): string
    {
        $relationships = $this->relModel->findAllRaw();
        $relatedEntities = $this->enrichEntityIndex($relationships);

        return view('relationships/index', [
            'relationships'  => $relationships,
            'relatedEntities'=> $relatedEntities,
        ]);
    }

    /**
     * GET /relacoes/nova — Formulário de criação
     */
    public function create(): string
    {
        $prefillEntity = null;
        $originId = (int)$this->request->getGet('origem');
        if ($originId > 0) {
            $prefillEntity = $this->entityModel->find($originId);
        }

        return view('relationships/create', [
            'prefillEntity' => $prefillEntity,
        ]);
    }

    /**
     * POST /relacoes/nova — Persiste nova relação
     */
    public function store()
    {
        $sourceId   = (int)$this->request->getPost('source_entity_id');
        $targetId   = (int)$this->request->getPost('target_entity_id');
        $docId      = (int)$this->request->getPost('source_document_id');
        $relType    = $this->request->getPost('relationship_type');
        $direction  = $this->request->getPost('direction') ?? 'directed';
        $confPct    = (float)($this->request->getPost('confidence_pct') ?? 75);
        $status     = $this->request->getPost('status') ?? 'hypothesis';
        $sourceRef  = $this->request->getPost('source_reference') ?? [];

        // Validações
        if (!$sourceId || !$targetId || !$relType) {
            session()->setFlashdata('error', 'Origem, destino e tipo de relação são obrigatórios.');
            return redirect()->back()->withInput();
        }

        if ($sourceId === $targetId) {
            session()->setFlashdata('error', 'A entidade origem e destino não podem ser a mesma.');
            return redirect()->back()->withInput();
        }

        if (!$docId) {
            session()->setFlashdata('error', 'O documento-fonte é obrigatório (Princípio I de rastreabilidade).');
            return redirect()->back()->withInput();
        }

        // Verificar que source_document_id é de tipo document
        $doc = $this->entityModel->find($docId);
        if (!$doc || $doc['type'] !== 'document') {
            session()->setFlashdata('error', 'A fonte selecionada deve ser uma entidade do tipo "documento".');
            return redirect()->back()->withInput();
        }

        // Apenas coordenador pode confirmar diretamente
        if ($status === 'confirmed' && !$this->auth->canConfirm()) {
            $status = 'hypothesis';
        }

        // Limpar source_reference de valores vazios
        $cleanRef = array_filter($sourceRef, fn($v) => $v !== '' && $v !== null);

        $data = [
            'source_entity_id'  => $sourceId,
            'target_entity_id'  => $targetId,
            'relationship_type' => $relType,
            'direction'         => in_array($direction, ['directed','symmetric']) ? $direction : 'directed',
            'confidence'        => round($confPct / 100, 2),
            'source_document_id'=> $docId,
            'source_reference'  => json_encode($cleanRef ?: (object)[]),
            'status'            => $status,
            'created_by'        => $this->auth->currentUser()['user_id'] ?? null,
        ];

        if ($status === 'confirmed') {
            $data['validated_by'] = $this->auth->currentUser()['user_id'];
        }

        $id = $this->relModel->insert($data);

        if (!$id) {
            session()->setFlashdata('error', 'Erro ao salvar relação. Verifique os dados e tente novamente.');
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', 'Relação "' . $relType . '" criada com sucesso.');
        return redirect()->to('relacoes');
    }

    /**
     * POST /relacoes/{id}/confirmar — Confirma hipótese (coordenador)
     */
    public function confirm(int $id)
    {
        if (!$this->auth->canConfirm()) {
            session()->setFlashdata('error', 'Apenas o coordenador pode confirmar relações.');
            return redirect()->to('relacoes');
        }

        $rel = $this->relModel->find($id);
        if (!$rel) {
            session()->setFlashdata('error', 'Relação não encontrada.');
            return redirect()->to('relacoes');
        }

        if ($rel['status'] === 'confirmed') {
            session()->setFlashdata('info', 'Esta relação já está confirmada.');
            return redirect()->to('relacoes');
        }

        $this->relModel->update($id, [
            'status'       => 'confirmed',
            'validated_by' => $this->auth->currentUser()['user_id'],
        ]);

        session()->setFlashdata('success', 'Relação confirmada como fato documentado.');
        return redirect()->to('relacoes');
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    /**
     * Monta índice id → entidade para todas as entidades referenciadas
     */
    private function enrichEntityIndex(array $relationships): array
    {
        $ids = [];
        foreach ($relationships as $rel) {
            $ids[] = $rel['source_entity_id'];
            $ids[] = $rel['target_entity_id'];
            if (!empty($rel['source_document_id'])) {
                $ids[] = $rel['source_document_id'];
            }
        }
        $ids = array_unique(array_filter($ids));

        $index = [];
        foreach ($ids as $id) {
            $e = $this->entityModel->find((int)$id);
            if ($e) $index[$id] = $e;
        }
        return $index;
    }
}
