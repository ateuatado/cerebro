<?php use App\Models\IntellectualProductModel; ?>
<?php ob_start(); ?>
<h1>Produção intelectual</h1>
<p>Organize teses, dissertações, artigos, ensaios e apresentações.</p>
<a class="btn btn-primary mb-3" href="<?= base_url('produtos/novo') ?>">Novo produto</a>
<?php if ($products === []): ?>
    <p>Nenhum produto cadastrado.</p>
<?php endif ?>
<?php foreach ($products as $product): ?>
    <article class="cbr-card mb-3 p-3">
        <h2 class="h5"><a href="<?= base_url('produtos/' . $product['id']) ?>"><?= esc($product['title']) ?></a></h2>
        <p class="mb-0"><?= esc(IntellectualProductModel::KINDS[$product['kind']]) ?></p>
    </article>
<?php endforeach ?>
<?php if ($pager->getPageCount() > 1): ?><?= $pager->links() ?><?php endif ?>
<?= view('layout/base', ['title' => 'Produção intelectual', 'content' => ob_get_clean()]) ?>
