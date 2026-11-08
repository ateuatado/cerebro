<?php ob_start(); ?>
<?php $path = 'produtos/' . $product['id'] . '/bibliografia'; ?>
<a href="<?= base_url('produtos') ?>">Voltar à Produção intelectual</a>
<h1><?= esc($product['title']) ?></h1>
<?= view('products/_nav', ['productId' => $product['id'], 'tab' => 'bibliography']) ?>
<h2>Bibliografia do produto</h2>
<p>Selecione na Biblioteca as obras de trabalho deste texto. A mesma obra pode ser usada em outros produtos.</p>
<section class="cbr-card p-3 mb-3" aria-labelledby="bibliography-current">
    <h3 id="bibliography-current">Obras selecionadas</h3>
    <?php if ($works === []): ?><p class="mb-0">Nenhuma obra adicionada.</p><?php endif ?>
    <?php foreach ($works as $work): ?>
        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
            <a href="<?= base_url('biblioteca/' . $work['id']) ?>"><?= esc($work['title']) ?></a>
            <span><?= esc($work['authors'] ?? '') ?></span>
            <form method="post" action="<?= base_url($path . '/' . $work['id'] . '/retirar') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-secondary" aria-label="Retirar <?= esc($work['title']) ?> deste produto">Retirar deste produto</button>
            </form>
        </div>
    <?php endforeach ?>
</section>
<section class="cbr-card p-3" aria-labelledby="bibliography-search">
    <h3 id="bibliography-search">Adicionar obras da Biblioteca</h3>
    <form method="get" action="<?= base_url($path) ?>" class="row g-2 align-items-end mb-3">
        <div class="col-sm">
            <label class="form-label" for="work-query">Título, autoria ou identificador</label>
            <input id="work-query" class="form-control" name="q" maxlength="255" required value="<?= esc($query) ?>">
        </div>
        <div class="col-sm-auto"><button class="btn btn-primary">Buscar</button></div>
    </form>
    <?php if ($query !== '' && $results === []): ?><p>Nenhuma obra encontrada.</p><?php endif ?>
    <?php foreach ($results as $work): ?>
        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
            <a href="<?= base_url('biblioteca/' . $work['id']) ?>"><?= esc($work['title']) ?></a>
            <span><?= esc($work['authors'] ?? '') ?></span>
            <?php if (isset($linked[$work['id']])): ?>
                <span>Já adicionada</span>
            <?php else: ?>
                <form method="post" action="<?= base_url($path . '/' . $work['id'] . '/adicionar') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-primary" aria-label="Adicionar <?= esc($work['title']) ?>">Adicionar</button>
                </form>
            <?php endif ?>
        </div>
    <?php endforeach ?>
    <?php if ($pager !== null && $pager->getPageCount() > 1): ?><?= $pager->only(['q'])->links() ?><?php endif ?>
    <a href="<?= base_url('biblioteca/nova') ?>">Cadastrar obra na Biblioteca</a>
</section>
<?= view('layout/base', ['title' => 'Bibliografia do produto', 'content' => ob_get_clean()]) ?>
