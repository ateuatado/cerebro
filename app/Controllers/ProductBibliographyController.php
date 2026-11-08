<?php

namespace App\Controllers;

use App\Models\BibliographicWorkModel;
use App\Models\IntellectualProductModel;
use App\Models\ProductBibliographyModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class ProductBibliographyController extends BaseController
{
    public function index(int $id): string
    {
        $product = $this->product($id);
        $input = $this->request->getGet('q');
        $query = is_string($input) ? mb_substr(trim($input), 0, 255) : '';
        $library = new BibliographicWorkModel();
        $results = $query !== '' ? $library->search($query) : [];
        $works = (new ProductBibliographyModel())->works($id);
        $linked = array_fill_keys(array_column($works, 'id'), true);

        return view('products/bibliography', compact('product', 'works', 'query', 'results', 'linked')
            + ['pager' => $library->pager]);
    }

    public function attach(int $id, int $workId)
    {
        $this->product($id);
        if ((new BibliographicWorkModel())->find($workId) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $saved = (new ProductBibliographyModel())->attach($id, $workId);

        return redirect()->to(base_url("produtos/$id/bibliografia"))
            ->with($saved ? 'success' : 'error', $saved ? 'Obra na bibliografia.' : 'Não foi possível incluir a obra.');
    }

    public function detach(int $id, int $workId)
    {
        $this->product($id);
        $saved = (new ProductBibliographyModel())->detach($id, $workId);

        return redirect()->to(base_url("produtos/$id/bibliografia"))
            ->with($saved ? 'success' : 'error', $saved ? 'Obra retirada da bibliografia deste produto.' : 'Não foi possível retirar a obra.');
    }

    private function product(int $id): array
    {
        return (new IntellectualProductModel())->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }
}
