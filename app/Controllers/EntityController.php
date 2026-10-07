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
     * GET /entidades/{id}/editar — Retorna JSON com dados da entidade para o modal de edição
     */
    public function edit(int $id)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        $entity = $this->entityModel->find($id);
        if (!$entity) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Entidade não encontrada.']);
        }

        $attrs = is_string($entity['attributes'])
            ? (json_decode($entity['attributes'], true) ?? [])
            : ($entity['attributes'] ?? []);

        return $this->response->setJSON([
            'success'    => true,
            'entity'     => [
                'id'         => $entity['id'],
                'type'       => $entity['type'],
                'name'       => $entity['name'],
                'status'     => $entity['status'],
                'attributes' => $attrs,
            ],
        ]);
    }

    /**
     * POST /entidades/{id}/editar — Atualiza a entidade e registra correções para feedback da IA
     */
    public function update(int $id)
    {
        $entity = $this->entityModel->find($id);
        if (!$entity) {
            session()->setFlashdata('error', 'Entidade não encontrada.');
            return redirect()->to('entidades');
        }

        $newName = trim((string) $this->request->getPost('name'));
        $newType = $this->request->getPost('type');

        if (empty($newName)) {
            session()->setFlashdata('error', 'O nome da entidade é obrigatório.');
            return redirect()->to('entidades/' . $id);
        }

        // Entidades confirmadas não podem mudar de tipo
        if ($entity['status'] === 'confirmed') {
            $newType = $entity['type'];
        } else {
            $allowedTypes = ['person', 'location', 'event', 'document'];
            if (!in_array($newType, $allowedTypes)) {
                session()->setFlashdata('error', 'Tipo de entidade inválido.');
                return redirect()->to('entidades/' . $id);
            }
        }

        // Atualizar atributos via o form (chave/valor)
        $attrs = is_string($entity['attributes'])
            ? (json_decode($entity['attributes'], true) ?? [])
            : ($entity['attributes'] ?? []);

        // Processa atributos enviados pelo modal de edição
        $attrKeys   = $this->request->getPost('attr_keys')   ?? [];
        $attrValues = $this->request->getPost('attr_values') ?? [];
        $attrDeletes = $this->request->getPost('attr_deletes') ?? [];

        // Remover atributos marcados para exclusão
        foreach ($attrDeletes as $delKey) {
            unset($attrs[$delKey]);
        }

        // Atualizar/inserir atributos
        if (is_array($attrKeys) && is_array($attrValues)) {
            $count = min(count($attrKeys), count($attrValues));
            for ($i = 0; $i < $count; $i++) {
                $k = trim((string) ($attrKeys[$i] ?? ''));
                $v = trim((string) ($attrValues[$i] ?? ''));
                if ($k !== '' && $v !== '') {
                    $attrs[$k] = $v;
                }
            }
        }

        $updateData = [
            'name'       => $newName,
            'type'       => $newType,
            'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
        ];

        $this->entityModel->update($id, $updateData);

        // Registrar correções para feedback da IA (base para Spec futura)
        $this->logCorrections($entity, $newName, $newType, $attrs);

        session()->setFlashdata('success', 'Entidade "' . $newName . '" atualizada com sucesso.');
        return redirect()->to('entidades/' . $id);
    }

    /**
     * Registra mudanças de nome, tipo ou atributos na tabela correction_log
     * para futuro feedback de aprendizado da IA (human-in-the-loop).
     */
    private function logCorrections(array $oldEntity, string $newName, string $newType, array $newAttrs): void
    {
        $db = \Config\Database::connect();
        $userId = $this->auth->currentUser()['user_id'] ?? null;
        $oldAttrs = is_string($oldEntity['attributes'])
            ? (json_decode($oldEntity['attributes'], true) ?? [])
            : ($oldEntity['attributes'] ?? []);

        // 1. Mudança de nome
        if ($oldEntity['name'] !== $newName) {
            $db->table('correction_log')->insert([
                'entity_id' => $oldEntity['id'],
                'field'     => 'name',
                'old_value' => $oldEntity['name'],
                'new_value' => $newName,
                'corrected_by' => $userId,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        // 2. Mudança de tipo
        if ($oldEntity['type'] !== $newType) {
            $db->table('correction_log')->insert([
                'entity_id' => $oldEntity['id'],
                'field'     => 'type',
                'old_value' => $oldEntity['type'],
                'new_value' => $newType,
                'corrected_by' => $userId,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        // 3. Mudanças de atributos (valores novos vs antigos)
        foreach ($newAttrs as $key => $newVal) {
            $oldVal = $oldAttrs[$key] ?? null;
            $oldValStr = is_array($oldVal) ? json_encode($oldVal, JSON_UNESCAPED_UNICODE) : (string) $oldVal;
            $newValStr = is_array($newVal) ? json_encode($newVal, JSON_UNESCAPED_UNICODE) : (string) $newVal;
            if ($oldValStr !== $newValStr) {
                $db->table('correction_log')->insert([
                    'entity_id' => $oldEntity['id'],
                    'field'     => 'attribute:' . $key,
                    'old_value' => $oldValStr,
                    'new_value' => $newValStr,
                    'corrected_by' => $userId,
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        }
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

        // 1.5. Apagar relações onde este documento aparece como origem ou destino
        $db->table('relationships')
            ->where('source_entity_id', $id)
            ->orWhere('target_entity_id', $id)
            ->delete();

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
     * POST /entidades/merge — Mescla duas entidades do mesmo tipo em uma só.
     * Transfere relações, mescla atributos, apaga a origem.
     */
    public function merge()
    {
        $sourceId = (int) $this->request->getPost('source_id');
        $targetId = (int) $this->request->getPost('target_id');

        $source = $this->entityModel->find($sourceId);
        $target = $this->entityModel->find($targetId);

        if (!$source || !$target) {
            session()->setFlashdata('error', 'Uma das entidades não foi encontrada.');
            return redirect()->to('entidades');
        }

        if ($source['type'] !== $target['type']) {
            session()->setFlashdata('error', 'Só é possível mesclar entidades do mesmo tipo.');
            return redirect()->to('entidades/' . $sourceId);
        }

        if ($sourceId === $targetId) {
            session()->setFlashdata('error', 'Não é possível mesclar uma entidade com ela mesma.');
            return redirect()->to('entidades/' . $sourceId);
        }

        $db = \Config\Database::connect();

        // 1. Transferir relações: source → target
        $db->table('relationships')
            ->where('source_entity_id', $sourceId)
            ->update(['source_entity_id' => $targetId]);

        $db->table('relationships')
            ->where('target_entity_id', $sourceId)
            ->update(['target_entity_id' => $targetId]);

        // Remover auto-relações que podem ter surgido
        $db->table('relationships')
            ->where('source_entity_id', $targetId)
            ->where('target_entity_id', $targetId)
            ->delete();

        // 2. Mesclar atributos (target vence em conflito)
        $sourceAttrs = is_string($source['attributes'])
            ? (json_decode($source['attributes'], true) ?? [])
            : ($source['attributes'] ?? []);
        $targetAttrs = is_string($target['attributes'])
            ? (json_decode($target['attributes'], true) ?? [])
            : ($target['attributes'] ?? []);

        $mergedAttrs = array_merge($sourceAttrs, $targetAttrs);

        $this->entityModel->update($targetId, [
            'attributes' => json_encode($mergedAttrs, JSON_UNESCAPED_UNICODE),
        ]);

        // 3. Se source for confirmed, garantir que target também seja
        if ($source['status'] === 'confirmed' && $target['status'] !== 'confirmed') {
            $this->entityModel->update($targetId, [
                'status'       => 'confirmed',
                'validated_by' => $this->auth->currentUser()['user_id'] ?? null,
            ]);
        }

        // 4. Registrar correção no log
        $db->table('correction_log')->insert([
            'entity_id'   => $targetId,
            'field'       => 'merged_from',
            'old_value'   => '',
            'new_value'   => $source['name'] . ' (#' . $sourceId . ')',
            'corrected_by'=> $this->auth->currentUser()['user_id'] ?? null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        // 5. Apagar entidade origem
        $this->entityModel->delete($sourceId);

        session()->setFlashdata('success',
            '"' . $source['name'] . '" foi mesclada em "' . $target['name'] . '". ' .
            'Relações transferidas, atributos combinados.'
        );
        return redirect()->to('entidades/' . $targetId);
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
