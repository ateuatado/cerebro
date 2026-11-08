<?php

namespace App\Controllers;

use App\Models\BibliographicWorkModel;
use App\Models\IntellectualProductModel;
use App\Models\PartBibliographyModel;
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
        $library->whereNotIn('id', static fn (\CodeIgniter\Database\BaseBuilder $builder) =>
            $builder->select('work_id')->from('intellectual_product_works')->where('product_id', $id));
        $results = $library->search($query);
        $works = (new ProductBibliographyModel())->works($id);

        $partsByWork = (new PartBibliographyModel())->partsByWork($id);

        return view('products/bibliography', compact('product', 'works', 'query', 'results', 'partsByWork')
            + ['pager' => $library->pager]);
    }

    public function attach(int $id)
    {
        $this->product($id);
        $input = $this->request->getPost('work_id');
        $workId = is_string($input) ? filter_var($input, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 2147483647],
        ]) : false;
        if ($workId === false) {
            return redirect()->to(base_url("produtos/$id/bibliografia"))->with('error', 'Selecione uma obra.');
        }
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
        if (isset((new PartBibliographyModel())->partsByWork($id)[$workId])) {
            return redirect()->to(base_url("produtos/$id/bibliografia"))
                ->with('error', 'Retire a obra das partes vinculadas antes de removê-la da bibliografia geral.');
        }
        $saved = (new ProductBibliographyModel())->detach($id, $workId);

        return redirect()->to(base_url("produtos/$id/bibliografia"))
            ->with($saved ? 'success' : 'error', $saved ? 'Obra retirada da bibliografia deste produto.' : 'Não foi possível retirar a obra.');
    }

    private function product(int $id): array
    {
        return (new IntellectualProductModel())->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }
}
