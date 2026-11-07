<?php

namespace App\Controllers;

use App\Models\IntellectualProductModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class ProductPartController extends BaseController
{
    public function form(int $productId, ?int $partId = null): string
    {
        [$product, $part, $parts] = $this->context($productId, $partId);

        return view('products/part', compact('product', 'part', 'parts'));
    }

    public function save(int $productId, ?int $partId = null)
    {
        $this->context($productId, $partId);
        $title = $this->request->getPost('title');
        $content = $this->request->getPost('content');
        $parent = $partId === null ? $this->request->getPost('parent_id') : '';
        $title = is_string($title) ? trim($title) : '';
        $form = "produtos/$productId/partes/" . ($partId === null ? 'nova' : "$partId/editar");
        if ($title === '' || mb_strlen($title) > 255 || !is_string($content) || mb_strlen($content) > 1000000
            || !is_string($parent) || ($parent !== '' && !ctype_digit($parent))) {
            return redirect()->to(base_url($form))->withInput()->with('error', 'Informe um título de até 255 caracteres e um texto de até um milhão de caracteres.');
        }

        $saved = (new IntellectualProductModel())->savePart($productId, $partId, $parent === '' ? null : (int) $parent, $title, $content);
        if ($saved === null) {
            return redirect()->to(base_url($form))->withInput()->with('error', 'Não foi possível salvar a parte. Confira se a seção principal ainda existe.');
        }

        return redirect()->to(base_url("produtos/$productId/partes/$saved/editar"))->with('success', 'Parte salva.');
    }

    public function move(int $productId, int $partId)
    {
        $this->context($productId, $partId);
        $direction = $this->request->getPost('direction');
        if ($direction !== 'up' && $direction !== 'down') {
            return redirect()->to(base_url("produtos/$productId"))->with('error', 'Escolha uma direção válida.');
        }
        $moved = (new IntellectualProductModel())->movePart($productId, $partId, $direction);

        return redirect()->to(base_url("produtos/$productId#parte-$partId"))
            ->with($moved ? 'success' : 'error', $moved ? 'Ordem atualizada.' : 'Não foi possível mover a parte.');
    }

    public function delete(int $productId, int $partId)
    {
        $this->context($productId, $partId);
        $deleted = (new IntellectualProductModel())->deletePart($productId, $partId);

        return $deleted
            ? redirect()->to(base_url("produtos/$productId"))->with('success', 'Parte excluída.')
            : redirect()->to(base_url("produtos/$productId/partes/$partId/editar"))
                ->with('error', 'Remova as subseções antes de excluir esta parte.');
    }

    private function context(int $productId, ?int $partId): array
    {
        $model = new IntellectualProductModel();
        $product = $model->find($productId) ?? throw PageNotFoundException::forPageNotFound();
        $parts = $model->parts($productId);
        $part = $partId === null ? [] : (array_column($parts, null, 'id')[$partId] ?? throw PageNotFoundException::forPageNotFound());

        return [$product, $part, $parts];
    }
}
