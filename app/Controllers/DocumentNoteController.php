<?php

namespace App\Controllers;

use App\Models\DocumentNoteModel;
use App\Models\EntityModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class DocumentNoteController extends BaseController
{
    public function index(): string
    {
        $model = new DocumentNoteModel();
        $query = $this->request->getGet('q');
        $query = is_string($query) ? mb_substr(trim($query), 0, 255) : '';
        $kind = $this->request->getGet('tipo');
        $kind = is_string($kind) && isset(DocumentNoteModel::KINDS[$kind]) ? $kind : '';
        if ($query !== '') {
            $model->groupStart()->like('document_label', $query, 'both', null, true)
                ->orLike('locator', $query, 'both', null, true)
                ->orLike('excerpt', $query, 'both', null, true)
                ->orLike('observations', $query, 'both', null, true)->groupEnd();
        }
        if ($kind !== '') {
            $model->where('kind', $kind);
        }
        $notes = $model->orderBy('id', 'DESC')->paginate(20);

        return view('document_notes/index', compact('notes', 'query', 'kind') + ['pager' => $model->pager]);
    }

    public function form(?int $id = null): string
    {
        $model = new DocumentNoteModel();
        $note = $this->findNote($model, $id);
        if ($id === null) {
            $documentId = $this->request->getGet('documento');
            $locator = $this->request->getGet('localizador');
            if (is_string($documentId) && ctype_digit($documentId)
                && is_string($locator) && preg_match('/^p\. [1-9][0-9]{0,8}$/D', $locator)
                && (new EntityModel())->where('type', 'document')->find($documentId) !== null) {
                $note = ['document_id' => $documentId, 'locator' => $locator];
            }
        }

        return view('document_notes/form', ['note' => $note, 'documents' => $model->documents()]);
    }

    public function save(?int $id = null)
    {
        $model = new DocumentNoteModel();
        $note = $this->findNote($model, $id);
        $data = [];
        foreach (['document_id', 'kind', 'locator', 'excerpt', 'observations'] as $key) {
            $value = $this->request->getPost($key);
            $data[$key] = is_string($value) ? trim($value) : '';
        }
        $document = ctype_digit($data['document_id'])
            ? (new EntityModel())->where('type', 'document')->find($data['document_id']) : null;
        $retained = $id !== null && $note['document_id'] === null && $data['document_id'] === '';
        $errors = [];
        if ($document === null && !$retained) {
            $errors[] = 'Selecione um documento do acervo.';
        }
        if (!isset(DocumentNoteModel::KINDS[$data['kind']])) {
            $errors[] = 'Escolha o tipo de ficha.';
        }
        if (mb_strlen($data['locator']) > 255 || mb_strlen($data['excerpt']) > 20000 || mb_strlen($data['observations']) > 20000) {
            $errors[] = 'Use até 255 caracteres no localizador e até 20 mil em cada campo de texto.';
        }
        if ($data['kind'] === 'excerto' && $data['excerpt'] === '') {
            $errors[] = 'Preencha o excerto.';
        }
        if ($data['excerpt'] !== '' && $data['locator'] === '') {
            $errors[] = 'Informe o localizador do excerto.';
        }
        if ($data['excerpt'] === '' && $data['observations'] === '') {
            $errors[] = 'Escreva um excerto ou uma observação.';
        }
        $path = $id === null ? 'novo' : "$id/editar";
        if ($errors !== []) {
            return redirect()->to(base_url("caderno/fichamentos/$path"))->withInput()->with('errors', $errors);
        }

        $data['document_id'] = $document['id'] ?? null;
        $data['document_label'] = $document['name'] ?? $note['document_label'];
        $saved = $id === null ? $model->insert($data) !== false : $model->update($id, $data);
        if (!$saved) {
            return redirect()->to(base_url("caderno/fichamentos/$path"))->withInput()->with('errors', ['Não foi possível salvar a ficha.']);
        }

        return redirect()->to(base_url('caderno/fichamentos'))->with('success', 'Ficha salva.');
    }

    private function findNote(DocumentNoteModel $model, ?int $id): array
    {
        if ($id === null) {
            return [];
        }

        return $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }
}
