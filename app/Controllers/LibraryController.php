<?php

namespace App\Controllers;

use App\Models\BibliographicNoteModel;
use App\Models\BibliographicWorkModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class LibraryController extends BaseController
{
    public function index(): string
    {
        $query = $this->request->getGet('q');
        $query = is_string($query) ? mb_substr(trim($query), 0, 255) : '';
        $model = new BibliographicWorkModel();

        return view('library/index', ['works' => $model->search($query), 'pager' => $model->pager, 'query' => $query]);
    }

    public function show(int $id): string
    {
        $work = (new BibliographicWorkModel())->find($id);
        if ($work === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $notes = (new BibliographicNoteModel())->where('work_id', $id)->orderBy('id', 'DESC')->findAll();

        return view('library/work', ['work' => $work, 'editing' => false, 'notes' => $notes]);
    }

    public function form(?int $id = null): string
    {
        $work = $id === null ? [] : (new BibliographicWorkModel())->find($id);
        if ($work === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('library/work', ['work' => $work, 'editing' => true]);
    }

    public function save(?int $id = null)
    {
        $model = new BibliographicWorkModel();
        if ($id !== null && $model->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'title' => 'required|max_length[255]',
            'kind' => 'required|in_list[' . implode(',', array_keys(BibliographicWorkModel::KINDS)) . ']',
            'authors' => 'permit_empty|max_length[4000]',
            'publication_date' => ['permit_empty', 'regex_match[/^\d{4}(?:-(?:0[1-9]|1[0-2])(?:-(?:0[1-9]|[12]\d|3[01]))?)?$/]'],
            'publication' => 'permit_empty|max_length[255]',
            'identifier' => 'permit_empty|max_length[255]',
        ];
        $data = [];
        foreach ($rules as $field => $rule) {
            $value = $this->request->getPost($field);
            $data[$field] = is_string($value) ? trim($value) : '';
        }
        if (!$this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($id === null) {
            $id = $model->insert($data);
            $saved = $id !== false;
        } else {
            $saved = $model->update($id, $data);
        }
        if (!$saved) {
            return redirect()->back()->withInput()->with('error', 'Não foi possível salvar a obra.');
        }

        return redirect()->to(base_url('biblioteca/' . $id))->with('success', 'Obra salva.');
    }

    public function delete(int $id)
    {
        $model = new BibliographicWorkModel();
        if ($model->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ((new BibliographicNoteModel())->where('work_id', $id)->countAllResults() > 0) {
            return redirect()->to(base_url('biblioteca/' . $id))->with('error', 'Esta obra tem notas bibliográficas e não pode ser excluída.');
        }

        if (!$model->delete($id)) {
            return redirect()->to(base_url('biblioteca/' . $id))->with('error', 'Não foi possível excluir a obra.');
        }

        return redirect()->to(base_url('biblioteca'))->with('success', 'Obra excluída.');
    }
}
