<?php ob_start(); ?>
<h1>Caderno de pesquisa</h1>
<h2>Diário</h2>
<p>Registre visitas, leituras, buscas e decisões da pesquisa.</p>
<a class="btn btn-primary mb-3" href="<?= base_url('caderno/diario/novo') ?>">Registrar atividade</a>
<form method="get" action="<?= base_url('caderno/diario') ?>" class="row g-2 align-items-end mb-3">
    <div class="col-sm">
        <label for="diary-query" class="form-label">Buscar no relato</label>
        <input id="diary-query" class="form-control" name="q" maxlength="255" value="<?= esc($text) ?>">
    </div>
    <div class="col-sm-auto">
        <label for="diary-date" class="form-label">Data da atividade</label>
        <input id="diary-date" type="date" class="form-control" name="data" value="<?= esc($date) ?>">
    </div>
    <div class="col-sm-auto">
        <button class="btn btn-secondary">Buscar</button>
        <?php if ($text !== '' || $date !== ''): ?>
            <a class="btn btn-outline-secondary" href="<?= base_url('caderno/diario') ?>">Limpar</a>
        <?php endif ?>
    </div>
</form>
<?php if ($entries === []): ?>
    <p><?= $text === '' && $date === '' ? 'O diário ainda não tem registros.' : 'Nenhum registro encontrado para esta busca.' ?></p>
<?php endif ?>
<?php foreach ($entries as $entry): ?>
    <article class="cbr-card mb-3 p-3">
        <h3 class="h5"><time datetime="<?= esc($entry['activity_date']) ?>"><?= esc(implode('/', array_reverse(explode('-', $entry['activity_date'])))) ?></time></h3>
        <p><?= nl2br(esc($entry['account'])) ?></p>
        <a href="<?= base_url('caderno/diario/' . $entry['id'] . '/editar') ?>">Editar registro</a>
    </article>
<?php endforeach ?>
<?php if ($pager->getPageCount() > 1): ?>
    <?= $pager->only(['q', 'data'])->links() ?>
<?php endif ?>
<?= view('layout/base', ['title' => 'Diário de pesquisa', 'content' => ob_get_clean()]) ?>
