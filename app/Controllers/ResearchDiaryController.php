<?php

namespace App\Controllers;

use App\Models\ResearchDiaryModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class ResearchDiaryController extends BaseController
{
    public function index(): string
    {
        $model = new ResearchDiaryModel();
        $text = $this->request->getGet('q');
        $date = $this->request->getGet('data');
        $text = is_string($text) ? mb_substr(trim($text), 0, 255) : '';
        $date = is_string($date) && $this->validDate($date) ? $date : '';
        if ($text !== '') {
            $model->like('account', $text, 'both', null, true);
        }
        if ($date !== '') {
            $model->where('activity_date', $date);
        }
        $entries = $model->orderBy('activity_date', 'DESC')->orderBy('id', 'DESC')->paginate(20);

        return view('diary/index', compact('entries', 'text', 'date') + ['pager' => $model->pager]);
    }

    public function form(?int $id = null): string
    {
        $entry = $id === null ? [] : $this->entry(new ResearchDiaryModel(), $id);

        return view('diary/form', compact('entry'));
    }

    public function save(?int $id = null)
    {
        $model = new ResearchDiaryModel();
        if ($id !== null) {
            $this->entry($model, $id);
        }
        $date = $this->request->getPost('activity_date');
        $account = $this->request->getPost('account');
        $account = is_string($account) ? trim($account) : '';
        $errors = [];
        if (!is_string($date) || !$this->validDate($date)) {
            $errors[] = 'Informe uma data válida.';
        }
        if ($account === '' || mb_strlen($account) > 20000) {
            $errors[] = 'Escreva um relato de até 20 mil caracteres.';
        }
        $form = $id === null ? 'caderno/diario/novo' : "caderno/diario/$id/editar";
        if ($errors !== []) {
            return redirect()->to(base_url($form))->withInput()->with('errors', $errors);
        }
        $data = ['activity_date' => $date, 'account' => $account];
        $saved = $id === null ? $model->insert($data) !== false : $model->update($id, $data);
        if (!$saved) {
            return redirect()->to(base_url($form))->withInput()->with('errors', ['Não foi possível salvar o registro.']);
        }

        return redirect()->to(base_url('caderno/diario'))->with('success', 'Registro salvo.');
    }

    private function entry(ResearchDiaryModel $model, int $id): array
    {
        $entry = $model->find($id);
        if ($entry === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $entry;
    }

    private function validDate(string $date): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
