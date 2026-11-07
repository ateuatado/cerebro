<?php

namespace App\Controllers;

use App\Models\IntellectualProductModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class IntellectualProductController extends BaseController
{
    public function index(): string
    {
        $model = new IntellectualProductModel();
        $products = $model->orderBy('id', 'DESC')->paginate(30);

        return view('products/index', ['products' => $products, 'pager' => $model->pager]);
    }

    public function form(): string
    {
        return view('products/form');
    }

    public function save()
    {
        $title = $this->request->getPost('title');
        $title = is_string($title) ? trim($title) : '';
        $kind = $this->request->getPost('kind');
        $errors = [];
        if ($title === '' || mb_strlen($title) > 255) {
            $errors[] = 'Informe um título de até 255 caracteres.';
        }
        if (!is_string($kind) || !isset(IntellectualProductModel::KINDS[$kind])) {
            $errors[] = 'Escolha um tipo de produto.';
        }
        if ($errors !== []) {
            return redirect()->to(base_url('produtos/novo'))->withInput()->with('errors', $errors);
        }

        $id = (new IntellectualProductModel())->createWithOutline($title, $kind);
        if ($id === null) {
            return redirect()->to(base_url('produtos/novo'))->withInput()->with('errors', ['Não foi possível criar o produto.']);
        }

        return redirect()->to(base_url("produtos/$id"))->with('success', 'Produto criado.');
    }

    public function show(int $id): string
    {
        $model = new IntellectualProductModel();
        $product = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $outline = [];
        foreach ($model->parts($id) as $part) {
            $outline[$part['parent_id'] ?? 0][] = $part;
        }

        return view('products/show', compact('product', 'outline'));
    }
}
