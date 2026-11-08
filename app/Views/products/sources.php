<?php ob_start(); ?>
<a href="<?= base_url('produtos') ?>">Voltar à Produção intelectual</a>
<h1><?= esc($product['title']) ?></h1>
<?= view('products/_nav', ['productId' => $product['id'], 'tab' => 'sources']) ?>
<h2>Fontes do produto</h2>
<p>Documentos e passagens do acervo vinculados às partes deste texto.</p>
<?php if ($sources === []): ?>
    <p>Nenhum vínculo ainda. Abra uma parte em <strong>Estrutura e texto</strong> para adicionar uma fonte.</p>
<?php else: ?>
    <?= view('products/_source_links', ['sources' => $sources, 'overview' => true]) ?>
<?php endif ?>
<?= view('layout/base', ['title' => 'Fontes do produto', 'content' => ob_get_clean()]) ?>
