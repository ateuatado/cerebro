<?php

namespace App\Controllers;

use App\Models\ResearchProjectModel;
use App\Models\EntityModel;

class ResearchProjectController extends BaseController
{
    private ResearchProjectModel $researchModel;
    private EntityModel          $entityModel;

    public function __construct()
    {
        $this->researchModel = new ResearchProjectModel();
        $this->entityModel   = new EntityModel();
    }

    /**
     * GET /pesquisas — Lista todas as pesquisas/assuntos
     */
    public function index(): string
    {
        $researches = $this->researchModel->findAllWithDocumentCount();

        return view('researches/index', [
            'researches' => $researches,
        ]);
    }

    /**
     * GET /pesquisas/nova — Formulário de criação
     */
    public function create(): string
    {
        return view('researches/form', [
            'research' => null,
            'isEdit'   => false,
        ]);
    }

    /**
     * POST /pesquisas/nova — Salvar nova pesquisa
     */
    public function store()
    {
        $rules = [
            'title' => 'required|min_length[3]|max_length[255]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userId = session()->get('user_id');

        $data = [
            'title'       => trim($this->request->getPost('title')),
            'description' => trim((string)$this->request->getPost('description')),
            'created_by'  => $userId ?: null,
        ];

        $this->researchModel->insert($data);

        return redirect()->to(base_url('pesquisas'))->with('success', 'Pesquisa criada com sucesso!');
    }

    /**
     * GET /pesquisas/(:num)/editar — Formulário de edição
     */
    public function edit(int $id)
    {
        $research = $this->researchModel->find($id);
        if (!$research) {
            return redirect()->to(base_url('pesquisas'))->with('error', 'Pesquisa não encontrada.');
        }

        return view('researches/form', [
            'research' => $research,
            'isEdit'   => true,
        ]);
    }

    /**
     * POST /pesquisas/(:num)/editar — Atualizar pesquisa
     */
    public function update(int $id)
    {
        $research = $this->researchModel->find($id);
        if (!$research) {
            return redirect()->to(base_url('pesquisas'))->with('error', 'Pesquisa não encontrada.');
        }

        $rules = [
            'title' => 'required|min_length[3]|max_length[255]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'title'       => trim($this->request->getPost('title')),
            'description' => trim((string)$this->request->getPost('description')),
        ];

        $this->researchModel->update($id, $data);

        return redirect()->to(base_url('pesquisas'))->with('success', 'Pesquisa atualizada com sucesso!');
    }

    /**
     * POST /pesquisas/(:num)/deletar — Deletar pesquisa
     */
    public function delete(int $id)
    {
        $research = $this->researchModel->find($id);
        if (!$research) {
            return redirect()->to(base_url('pesquisas'))->with('error', 'Pesquisa não encontrada.');
        }

        $this->researchModel->delete($id);

        return redirect()->to(base_url('pesquisas'))->with('success', 'Pesquisa removida com sucesso!');
    }

    /**
     * GET /pesquisas/(:num)/documentos — Gerenciar documentos da pesquisa
     */
    public function manageDocuments(int $id)
    {
        $research = $this->researchModel->find($id);
        if (!$research) {
            return redirect()->to(base_url('pesquisas'))->with('error', 'Pesquisa não encontrada.');
        }

        $allDocuments = $this->entityModel->findByType('document');
        $attachedDocumentIds = $this->researchModel->getDocumentIds($id);

        return view('researches/documents', [
            'research'            => $research,
            'allDocuments'        => $allDocuments,
            'attachedDocumentIds' => $attachedDocumentIds,
        ]);
    }

    /**
     * POST /pesquisas/(:num)/documentos — Salvar lista de documentos vinculados
     */
    public function saveDocuments(int $id)
    {
        $research = $this->researchModel->find($id);
        if (!$research) {
            return redirect()->to(base_url('pesquisas'))->with('error', 'Pesquisa não encontrada.');
        }

        $selectedDocuments = (array)$this->request->getPost('documents');
        $this->researchModel->syncDocuments($id, $selectedDocuments);

        return redirect()->to(base_url('pesquisas'))->with('success', 'Documentos vinculados à pesquisa com sucesso!');
    }
}
