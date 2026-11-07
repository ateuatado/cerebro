<?php use App\Models\IntellectualProductModel; ?>
<?php ob_start(); ?>
<a href="<?= base_url('produtos') ?>">Voltar à Produção intelectual</a>
<h1><?= esc($product['title']) ?></h1>
<p><?= esc(IntellectualProductModel::KINDS[$product['kind']]) ?></p>
<section class="cbr-card p-3" aria-labelledby="product-outline">
    <h2 id="product-outline">Estrutura</h2>
    <p>Clique no título de uma parte para escrever ou editar seu texto.</p>
    <a class="btn btn-primary mb-3" href="<?= base_url('produtos/' . $product['id'] . '/partes/nova') ?>">Adicionar parte</a>
    <?php if (empty($outline[0])): ?>
        <p>A estrutura ainda não tem partes.</p>
    <?php else: ?>
        <?= view('products/_parts', ['parts' => $outline[0], 'outline' => $outline, 'productId' => $product['id']]) ?>
    <?php endif ?>
</section>
<?= view('layout/base', ['title' => $product['title'], 'content' => ob_get_clean()]) ?>
