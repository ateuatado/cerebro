<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Models\PersonModel;
use App\Models\LocationModel;
use App\Models\EventModel;
use App\Models\DocumentModel;
use App\Models\RelationshipModel;
use App\Services\AuthService;

/**
 * EntityController — CRUD de entidades do grafo
 */
class EntityController extends BaseController
{
    private EntityModel       $entityModel;
    private RelationshipModel $relModel;
    private AuthService       $auth;

    public function __construct()
    {
        $this->entityModel = new EntityModel();
        $this->relModel    = new RelationshipModel();
        $this->auth        = new AuthService();
    }

    /**
     * GET /entidades — Listagem com filtros
     */
    public function index(): string
    {
        $entities = $this->entityModel->findAllRaw();

        return view('entities/index', [
            'entities' => $entities,
        ]);
    }

    /**
     * GET /entidades/nova — Formulário de criação
     */
    public function create(): string
    {
        return view('entities/create');
    }

    /**
     * POST /entidades/nova — Persiste a nova entidade
     */
    public function store()
    {
        $type   = $this->request->getPost('type');
        $name   = $this->request->getPost('name');
        $status = $this->request->getPost('status') ?? 'hypothesis';
        $attrs  = $this->request->getPost('attributes') ?? [];

        // Apenas coordenador pode criar como "confirmed"
        if ($status === 'confirmed' && !$this->auth->canConfirm()) {
            $status = 'hypothesis';
        }

        // Validação mínima
        if (empty($type) || empty($name)) {
            session()->setFlashdata('error', 'Tipo e nome são obrigatórios.');
            return redirect()->to('entidades/nova');
        }

        $allowedTypes = ['person', 'location', 'event', 'document'];
        if (!in_array($type, $allowedTypes)) {
            session()->setFlashdata('error', 'Tipo de entidade inválido.');
            return redirect()->to('entidades/nova');
        }

        // Flatten de atributos aninhados (localizacao_arquivistica)
        $attributes = $this->flattenAttributes($attrs, $type);

        $data = [
            'type'       => $type,
            'name'       => $name,
            'status'     => $status,
            'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE),
            'created_by' => $this->auth->currentUser()['user_id'] ?? null,
        ];

        if ($status === 'confirmed') {
            $data['validated_by'] = $this->auth->currentUser()['user_id'] ?? null;
        }

        $modelMap = [
            'person'   => new PersonModel(),
            'location' => new LocationModel(),
            'event'    => new EventModel(),
            'document' => new DocumentModel(),
        ];

        $id = $modelMap[$type]->insert($data);

        if (!$id) {
            session()->setFlashdata('error', 'Erro ao salvar entidade. Verifique os dados e tente novamente.');
            return redirect()->to('entidades/nova');
        }

        session()->setFlashdata('success', 'Entidade "' . $name . '" criada com sucesso.');
        return redirect()->to('entidades/' . $id);
    }

    /**
     * GET /entidades/{id} — Detalhe de uma entidade
     */
    public function show(int $id): string
    {
        $entity = $this->entityModel->find($id);

        if (!$entity) {
            session()->setFlashdata('error', 'Entidade não encontrada.');
            return redirect()->to('entidades');
        }

        // Decodifica atributos JSONB
        if (is_string($entity['attributes'])) {
            $entity['attributes'] = json_decode($entity['attributes'], true) ?? [];
        }

        // Relações onde esta entidade aparece
        $relationsAsSource = $this->relModel->findBySource($id);
        $relationsAsTarget = $this->relModel->findByTarget($id);

        // Enriquecer relações com nomes das entidades
        $allIds = array_unique(array_merge(
            array_column($relationsAsSource, 'target_entity_id'),
            array_column($relationsAsTarget, 'source_entity_id')
        ));

        $relatedEntities = [];
        foreach ($allIds as $eid) {
            $e = $this->entityModel->find((int)$eid);
            if ($e) $relatedEntities[$eid] = $e;
        }

        return view('entities/show', [
            'entity'            => $entity,
            'relationsAsSource' => $relationsAsSource,
            'relationsAsTarget' => $relationsAsTarget,
            'relatedEntities'   => $relatedEntities,
        ]);
    }

    /**
     * POST /entidades/{id}/confirmar — Promove hipótese a fato (coordenador)
     */
    public function confirm(int $id)
    {
        if (!$this->auth->canConfirm()) {
            session()->setFlashdata('error', 'Apenas o coordenador pode confirmar entidades.');
            return redirect()->to('entidades/' . $id);
        }

        $entity = $this->entityModel->find($id);
        if (!$entity) {
            session()->setFlashdata('error', 'Entidade não encontrada.');
            return redirect()->to('entidades');
        }

        if ($entity['status'] === 'confirmed') {
            session()->setFlashdata('info', 'Esta entidade já está confirmada.');
            return redirect()->to('entidades/' . $id);
        }

        $this->entityModel->update($id, [
            'status'       => 'confirmed',
            'validated_by' => $this->auth->currentUser()['user_id'],
        ]);

        session()->setFlashdata('success', '"' . $entity['name'] . '" confirmada como fato documentado.');
        return redirect()->to('entidades/' . $id);
    }

    /**
     * GET /api/entidades/busca?q=... — Autocomplete JSON
     */
    public function search()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        $q = $this->request->getGet('q');
        if (strlen((string)$q) < 2) {
            return $this->response->setJSON([]);
        }

        $entities = $this->entityModel
            ->like('name', $q)
            ->select('id, name, type, status')
            ->findAll(20);

        return $this->response->setJSON($entities);
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    /**
     * GET /documentos — Lista apenas entidades do tipo document
     */
    public function documents(): string
    {
        $entities = $this->entityModel
            ->where('type', 'document')
            ->orderBy('created_at', 'DESC')
            ->findAll();

        return view('entities/index', [
            'entities'       => $entities,
            'defaultType'    => 'document',
            'pageTitle'      => 'Documentos',
        ]);
    }

    /**
     * GET /documentos/(:num)/arquivo — Transmite o arquivo original (imagem ou PDF)
     */
    public function serveFile(int $id)
    {
        $doc = $this->entityModel->find($id);
        if (!$doc || $doc['type'] !== 'document') {
            return $this->response->setStatusCode(404)->setBody('Documento não encontrado.');
        }

        $attrs = is_string($doc['attributes'])
            ? (json_decode($doc['attributes'], true) ?? [])
            : ($doc['attributes'] ?? []);

        $filePath = $attrs['caminho_arquivo'] ?? '';

        if (empty($filePath) || !file_exists($filePath)) {
            return $this->response->setStatusCode(404)->setBody('Arquivo original não encontrado no servidor.');
        }

        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'inline; filename="' . basename($doc['name']) . '"')
            ->setBody(file_get_contents($filePath));
    }

    /**
     * Normaliza atributos do formulário (aninhados e vazios)
     */
    private function flattenAttributes(array $attrs, string $type): array
    {
        $result = [];

        foreach ($attrs as $key => $value) {
            if (is_array($value)) {
                // Subobjetos (ex: localizacao_arquivistica)
                $sub = array_filter($value, fn($v) => $v !== '' && $v !== null);
                if (!empty($sub)) {
                    $result[$key] = $sub;
                }
            } elseif ($value !== '' && $value !== null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
