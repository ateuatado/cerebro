<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Models\PersonModel;
use App\Models\LocationModel;
use App\Models\EventModel;
use App\Models\DocumentModel;
use App\Models\RelationshipModel;
use App\Models\EntityAttributeVocabularyModel;
use App\Services\AuthService;

/**
 * EntityController — CRUD de entidades do grafo e exclusão em cascata de documentos e rastro
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

        // 1. Carregar dados dos usuários pesquisadores (criador e validador)
        $userModel     = new \App\Models\UserModel();
        $creatorUser   = !empty($entity['created_by']) ? $userModel->find((int)$entity['created_by']) : null;
        $validatorUser = !empty($entity['validated_by']) ? $userModel->find((int)$entity['validated_by']) : null;

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

        // 2. Coletar documentos fontes e trechos de citação vinculados às relações da entidade
        $allRelations = array_merge($relationsAsSource, $relationsAsTarget);
        $sourceDocuments = [];
        $docIds = [];

        foreach ($allRelations as $rel) {
            $docId = (int)($rel['source_document_id'] ?? 0);
            if ($docId > 0) {
                if (!isset($docIds[$docId])) {
                    $docIds[$docId] = [
                        'doc_id'   => $docId,
                        'excerpts' => [],
                    ];
                }

                $ref = is_string($rel['source_reference'] ?? null)
                    ? (json_decode($rel['source_reference'], true) ?? [])
                    : ($rel['source_reference'] ?? []);

                $trecho = $ref['trecho'] ?? null;
                if (!empty($trecho) && !in_array($trecho, $docIds[$docId]['excerpts'])) {
                    $docIds[$docId]['excerpts'][] = $trecho;
                }
            }
        }

        foreach ($docIds as $docId => $info) {
            $docEntity = $this->entityModel->find($docId);
            if ($docEntity) {
                $docAttrs = is_string($docEntity['attributes'])
                    ? (json_decode($docEntity['attributes'], true) ?? [])
                    : ($docEntity['attributes'] ?? []);

                $docUploader = !empty($docEntity['created_by']) ? $userModel->find((int)$docEntity['created_by']) : null;
                $filePath    = $docAttrs['caminho_arquivo'] ?? '';
                $format      = strtolower($docAttrs['formato'] ?? pathinfo($docEntity['name'], PATHINFO_EXTENSION));

                $sourceDocuments[] = [
                    'id'          => $docId,
                    'name'        => $docEntity['name'],
                    'attributes'  => $docAttrs,
                    'uploader'    => $docUploader,
                    'excerpts'    => $info['excerpts'],
                    'has_file'    => !empty($filePath) && file_exists($filePath),
                    'format'      => $format,
                    'created_at'  => $docEntity['created_at'] ?? null,
                ];
            }
        }

        return view('entities/show', [
            'entity'            => $entity,
            'creatorUser'       => $creatorUser,
            'validatorUser'     => $validatorUser,
            'sourceDocuments'   => $sourceDocuments,
            'relationsAsSource' => $relationsAsSource,
            'relationsAsTarget' => $relationsAsTarget,
            'relatedEntities'   => $relatedEntities,
        ]);
    }

    /**
     * POST /entidades/{id}/atributos — Adiciona ou atualiza um atributo JSONB
     */
    public function addAttribute(int $id)
    {
        $entity = $this->entityModel->find($id);
        if (!$entity) {
            session()->setFlashdata('error', 'Entidade não encontrada.');
            return redirect()->to('entidades');
        }

        $key       = trim((string)$this->request->getPost('attr_key'));
        $val       = trim((string)$this->request->getPost('attr_value'));
        $customKey = trim((string)$this->request->getPost('custom_attr_key'));

        if ($key === 'outro' && !empty($customKey)) {
            $key = $customKey;
        } elseif (empty($key) && !empty($customKey)) {
            $key = $customKey;
        }

        // Sanitização da chave (snake_case minúsculo sem caracteres especiais)
        $key = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', str_replace(' ', '_', $key)));
        $key = trim($key, '_');

        if (empty($key) || $val === '') {
            session()->setFlashdata('error', 'O nome do atributo e o valor são obrigatórios.');
            return redirect()->to('entidades/' . $id);
        }

        $attrs = is_string($entity['attributes'])
            ? (json_decode($entity['attributes'], true) ?? [])
            : ($entity['attributes'] ?? []);

        $attrs[$key] = $val;

        $this->entityModel->update($id, [
            'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
        ]);

        session()->setFlashdata('success', 'Atributo "' . $key . '" salvo com sucesso.');
        return redirect()->to('entidades/' . $id);
    }

    /**
     * POST /entidades/{id}/atributos/remover — Remove um atributo do JSONB
     */
    public function removeAttribute(int $id)
    {
        $entity = $this->entityModel->find($id);
        if (!$entity) {
            session()->setFlashdata('error', 'Entidade não encontrada.');
            return redirect()->to('entidades');
        }

        $key = trim((string)$this->request->getPost('attr_key'));

        if (!empty($key)) {
            $attrs = is_string($entity['attributes'])
                ? (json_decode($entity['attributes'], true) ?? [])
                : ($entity['attributes'] ?? []);

            unset($attrs[$key]);

            $this->entityModel->update($id, [
                'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
            ]);

            session()->setFlashdata('success', 'Atributo "' . $key . '" removido com sucesso.');
        }

        return redirect()->to('entidades/' . $id);
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
     * POST /entidades/{id}/deletar — Apaga uma entidade e limpa em cascata suas conexões
     */
    public function delete(int $id)
    {
        $entity = $this->entityModel->find($id);
        if (!$entity) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(404)->setJSON(['error' => 'Entidade não encontrada.']);
            }
            session()->setFlashdata('error', 'Entidade não encontrada.');
            return redirect()->to('entidades');
        }

        $db = \Config\Database::connect();

        // Se for um documento, executa a exclusão completa do rastro do documento
        if ($entity['type'] === 'document') {
            return $this->deleteDocument($id);
        }

        // Remover relações onde a entidade aparece como origem ou destino
        $db->table('relationships')
            ->where('source_entity_id', $id)
            ->orWhere('target_entity_id', $id)
            ->delete();

        // Apagar a entidade
        $this->entityModel->delete($id);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Entidade "' . $entity['name'] . '" e suas conexões foram excluídas com sucesso.'
            ]);
        }

        session()->setFlashdata('success', 'Entidade "' . $entity['name'] . '" e suas conexões foram excluídas.');
        return redirect()->to('entidades');
    }

    /**
     * POST /documentos/{id}/deletar — Apaga o documento, arquivo físico e todo o rastro no grafo
     */
    public function deleteDocument(int $id)
    {
        $doc = $this->entityModel->find($id);
        if (!$doc || $doc['type'] !== 'document') {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(404)->setJSON(['error' => 'Documento não encontrado.']);
            }
            session()->setFlashdata('error', 'Documento não encontrado.');
            return redirect()->to('documentos');
        }

        $db = \Config\Database::connect();

        // 1. Obter arquivo físico para remoção
        $attrs = is_string($doc['attributes'])
            ? (json_decode($doc['attributes'], true) ?? [])
            : ($doc['attributes'] ?? []);

        $filePath = $attrs['caminho_arquivo'] ?? '';
        if (!empty($filePath) && file_exists($filePath)) {
            @unlink($filePath);
        }

        // 2. Apagar todas as relações vinculadas a este documento como fonte primária
        $db->table('relationships')->where('source_document_id', $id)->delete();

        // 3. Apagar o registro do documento na tabela 'entities'
        $this->entityModel->delete($id);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Documento "' . $doc['name'] . '", arquivo físico e rastro no grafo foram excluídos com sucesso!'
            ]);
        }

        session()->setFlashdata('success', 'Documento "' . $doc['name'] . '", arquivo físico e seu rastro no grafo foram apagados.');
        return redirect()->to('documentos');
    }

    /**
     * POST /api/limpar-banco-total — Apaga TODAS as entidades, relações e arquivos de upload (Apenas Coordenador)
     */
    public function clearAllIngestions()
    {
        if (!$this->auth->canConfirm()) { // Apenas coordenador
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Apenas coordenadores podem zerar a base de dados.']);
        }

        $db = \Config\Database::connect();

        // 1. Apagar Relações
        $db->table('relationships')->emptyTable();
        $db->query('ALTER SEQUENCE IF EXISTS relationships_id_seq RESTART WITH 1;');

        // 2. Apagar Entidades e Documentos
        $db->table('entities')->emptyTable();
        $db->query('ALTER SEQUENCE IF EXISTS entities_id_seq RESTART WITH 1;');

        // 3. Excluir arquivos físicos salvos em writable/uploads/documents/
        $uploadDir = WRITEPATH . 'uploads/documents/';
        if (is_dir($uploadDir)) {
            $files = glob($uploadDir . '*');
            foreach ($files as $f) {
                if (is_file($f) && basename($f) !== 'index.html') {
                    @unlink($f);
                }
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Toda a base de entidades, relações e arquivos de ingestão foi completamente zerada!'
        ]);
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

    private function flattenAttributes(array $attrs, string $type): array
    {
        $result = [];

        foreach ($attrs as $key => $value) {
            if (is_array($value)) {
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

    /**
     * GET /api/vocabulario-atributos — Vocabulário controlado de atributos por tipo (Spec 11)
     * Retorna JSON agrupado por entity_type para uso nos dropdowns da modal de aprovação.
     */
    public function getAttributeVocabulary()
    {
        $model = new EntityAttributeVocabularyModel();
        return $this->response->setJSON([
            'success'    => true,
            'vocabulary' => $model->getAllGroupedByType(),
        ]);
    }
}
