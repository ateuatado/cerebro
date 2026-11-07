<?php ob_start(); ?>
<h1>Caderno de pesquisa</h1>
<?= view('diary/_nav', ['tab' => 'notes']) ?>
<?= view('diary/_note_nav', ['collection' => 'bibliographic']) ?>
<h2>Notas bibliográficas</h2>
<p>Consulte as notas de leitura registradas nas obras da Biblioteca.</p>
<a class="btn btn-primary mb-3" href="<?= base_url('biblioteca') ?>">Abrir Biblioteca</a>
<form method="get" action="<?= base_url('caderno/fichamentos/bibliograficos') ?>" class="row g-2 align-items-end mb-3">
    <div class="col-sm">
        <label class="form-label" for="notes-query">Buscar por título, autoria, localizador ou texto</label>
        <input class="form-control" id="notes-query" name="q" maxlength="255" value="<?= esc($query) ?>">
    </div>
    <div class="col-sm-auto"><button class="btn btn-secondary">Buscar</button></div>
</form>
<?php if ($notes === []): ?>
    <p><?= $query === '' ? 'Ainda não há notas bibliográficas. Abra uma obra na Biblioteca para criar uma nota.' : 'Nenhuma nota encontrada para esta busca.' ?></p>
<?php endif ?>
<?php foreach ($notes as $note): ?>
    <?php $workPath = 'biblioteca/' . $note['work_id']; ?>
    <article class="cbr-card mb-3 p-3">
        <h3 class="h5"><a href="<?= base_url($workPath) ?>"><?= esc($note['title']) ?></a></h3>
        <p><?= esc($note['authors'] ?? '') ?><?= $note['locator'] !== '' ? ' · ' . esc($note['locator']) : '' ?></p>
        <?php if ($note['excerpt'] !== ''): ?>
            <div class="fw-semibold">Excerto</div>
            <blockquote><?= nl2br(esc($note['excerpt'])) ?></blockquote>
        <?php endif ?>
        <?php if ($note['observations'] !== ''): ?>
            <div class="fw-semibold">Observações</div>
            <p><?= nl2br(esc($note['observations'])) ?></p>
        <?php endif ?>
        <a href="<?= base_url($workPath . '/notas/' . $note['id'] . '/editar') ?>">Editar nota</a>
    </article>
<?php endforeach ?>
<?php if ($pager->getPageCount() > 1): ?><?= $pager->only(['q'])->links() ?><?php endif ?>
<?= view('layout/base', ['title' => 'Notas bibliográficas', 'content' => ob_get_clean()]) ?>
