<?php ob_start(); ?>
<h1>Biblioteca</h1>
<p>Obras de leitura e fundamentação para os seus textos.</p>
<form method="get" action="<?= base_url('biblioteca') ?>" class="d-flex gap-2 mb-3">
    <label for="library-query" class="visually-hidden">Buscar obras</label>
    <input id="library-query" class="form-control" name="q" value="<?= esc($query) ?>" placeholder="Título, autoria ou identificador">
    <button class="btn btn-secondary">Buscar</button>
    <a class="btn btn-primary text-nowrap" href="<?= base_url('biblioteca/nova') ?>">Cadastrar obra</a>
</form>
<?php if (!$works): ?>
    <p><?= $query === '' ? 'Nenhuma obra cadastrada.' : 'Nenhuma obra encontrada para esta busca.' ?></p>
<?php else: ?>
    <ul class="list-group mb-3">
        <?php foreach ($works as $work): ?>
            <li class="list-group-item">
                <a href="<?= base_url('biblioteca/' . $work['id']) ?>"><?= esc($work['title']) ?></a>
                <span class="text-body-secondary"><?= esc($work['authors'] ?? '') ?></span>
            </li>
        <?php endforeach ?>
    </ul>
    <?= $pager->only(['q'])->links() ?>
<?php endif ?>
<?= view('layout/base', ['title' => 'Biblioteca', 'content' => ob_get_clean()]) ?>
