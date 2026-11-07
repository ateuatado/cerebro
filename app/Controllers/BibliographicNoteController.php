<?php

namespace App\Controllers;

use App\Models\BibliographicNoteModel;
use App\Models\BibliographicWorkModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class BibliographicNoteController extends BaseController
{
    public function form(int $workId, ?int $noteId = null): string
    {
        [$work, $note] = $this->context($workId, $noteId);

        return view('library/note', ['work' => $work, 'note' => $note]);
    }

    public function save(int $workId, ?int $noteId = null)
    {
        $this->context($workId, $noteId);
        $data = [];
        foreach (['locator', 'excerpt', 'observations'] as $field) {
            $value = $this->request->getPost($field);
            $data[$field] = is_string($value) ? trim($value) : '';
        }
        $this->validateData($data, [
            'locator' => 'permit_empty|max_length[255]',
            'excerpt' => 'permit_empty|max_length[20000]',
            'observations' => 'permit_empty|max_length[20000]',
        ]);
        $errors = $this->validator->getErrors();
        if ($data['excerpt'] !== '' && $data['locator'] === '') {
            $errors['locator'] = 'Informe a página ou outro localizador do excerto.';
        }
        if ($data['excerpt'] === '' && $data['observations'] === '') {
            $errors['observations'] = 'Escreva um excerto ou uma observação.';
        }
        if ($errors !== []) {
            $form = $noteId === null ? 'nova' : $noteId . '/editar';

            return redirect()->to(base_url("biblioteca/$workId/notas/$form"))->withInput()->with('errors', $errors);
        }

        $model = new BibliographicNoteModel();
        $saved = $noteId === null
            ? $model->insert(['work_id' => $workId] + $data) !== false
            : $model->update($noteId, $data);

        return redirect()->to(base_url("biblioteca/$workId#notas"))
            ->with($saved ? 'success' : 'error', $saved ? 'Nota salva.' : 'Não foi possível salvar a nota.');
    }

    private function context(int $workId, ?int $noteId): array
    {
        $work = (new BibliographicWorkModel())->find($workId);
        $note = $noteId === null ? [] : (new BibliographicNoteModel())->where('work_id', $workId)->find($noteId);
        if ($work === null || $note === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return [$work, $note];
    }
}
