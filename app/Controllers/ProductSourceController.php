<?php

namespace App\Controllers;

use App\Models\IntellectualProductModel;
use App\Models\ProductPartSourceModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class ProductSourceController extends BaseController
{
    public function index(int $id): string
    {
        $product = (new IntellectualProductModel())->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $sources = (new ProductPartSourceModel())->sources($id);

        return view('products/sources', compact('product', 'sources'));
    }

    public function attach(int $id, int $partId)
    {
        $input = $this->request->getPost('source');
        $parsed = is_string($input) && preg_match('/^(document|note):([1-9][0-9]{0,9})$/D', $input, $match);
        $saved = $parsed && (int) $match[2] <= 2147483647
            && (new ProductPartSourceModel())->attach($id, $partId, $match[1], (int) $match[2]);

        return redirect()->to(base_url("produtos/$id/partes/$partId/editar#fontes-desta-parte"))
            ->with($saved ? 'success' : 'error', $saved ? 'Fonte vinculada à parte.' : 'Selecione uma fonte disponível.');
    }

    public function detach(int $id, int $partId, int $linkId)
    {
        $saved = (new ProductPartSourceModel())->detach($id, $partId, $linkId);

        return redirect()->to(base_url("produtos/$id/partes/$partId/editar#fontes-desta-parte"))
            ->with($saved ? 'success' : 'error', $saved ? 'Vínculo retirado.' : 'Vínculo não encontrado.');
    }
}
