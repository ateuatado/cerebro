<?php use App\Models\IntellectualProductModel; ?>
<?php ob_start(); ?>
<a href="<?= base_url('produtos') ?>">Voltar à Produção intelectual</a>
<h1><?= esc($product['title']) ?></h1>
<p><?= esc(IntellectualProductModel::KINDS[$product['kind']]) ?></p>
<section class="cbr-card p-3" aria-labelledby="product-outline">
    <h2 id="product-outline">Estrutura</h2>
    <ol class="mb-0">
        <?php foreach ($outline[0] ?? [] as $part): ?>
            <li>
                <?= esc($part['title']) ?>
                <?php if (isset($outline[$part['id']])): ?>
                    <ol>
                        <?php foreach ($outline[$part['id']] as $child): ?>
                            <li><?= esc($child['title']) ?></li>
                        <?php endforeach ?>
                    </ol>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ol>
</section>
<?= view('layout/base', ['title' => $product['title'], 'content' => ob_get_clean()]) ?>
