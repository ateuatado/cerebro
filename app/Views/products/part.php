<?php ob_start(); ?>
<?php
$path = 'produtos/' . $product['id'];
$editing = isset($part['id']);
$action = $path . '/partes/' . ($editing ? $part['id'] . '/editar' : 'nova');
$hasChildren = $editing && in_array((string) $part['id'], array_map('strval', array_column($parts, 'parent_id')), true);
?>
<a href="<?= base_url($path) ?>">Voltar à estrutura</a>
<h1><?= $editing ? 'Editar parte' : 'Nova parte' ?></h1>
<p><?= esc($product['title']) ?></p>
<form method="post" action="<?= base_url($action) ?>">
    <?= csrf_field() ?>
    <?php if (!$editing): ?>
        <label class="form-label" for="part-parent">Onde incluir</label>
        <select class="form-select mb-3" id="part-parent" name="parent_id">
            <option value="">Na estrutura principal</option>
            <?php foreach ($parts as $candidate): ?>
                <?php if ($candidate['parent_id'] === null): ?>
                    <option value="<?= esc($candidate['id']) ?>" <?= old('parent_id', '', false) === (string) $candidate['id'] ? 'selected' : '' ?>><?= esc($candidate['title']) ?> — como subseção</option>
                <?php endif ?>
            <?php endforeach ?>
        </select>
    <?php endif ?>
    <label class="form-label" for="part-title">Título</label>
    <input class="form-control mb-3" id="part-title" name="title" maxlength="255" required value="<?= esc(old('title', $part['title'] ?? '', false)) ?>">
    <label class="form-label" for="part-content">Texto</label>
    <textarea class="form-control mb-3" id="part-content" name="content" rows="16" maxlength="1000000"><?= esc(old('content', $part['content'] ?? '', false)) ?></textarea>
    <button class="btn btn-primary">Salvar parte</button>
    <a class="btn btn-secondary" href="<?= base_url($path) ?>">Voltar à estrutura</a>
</form>
<?php if ($editing && !$hasChildren): ?>
    <button type="button" class="btn btn-outline-danger mt-3" data-bs-toggle="modal" data-bs-target="#delete-part">Excluir parte</button>
    <div class="modal fade" id="delete-part" tabindex="-1" aria-labelledby="delete-part-title" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= base_url($path . '/partes/' . $part['id'] . '/excluir') ?>">
                <?= csrf_field() ?>
                <div class="modal-header"><h2 class="modal-title fs-5" id="delete-part-title">Excluir parte?</h2></div>
                <div class="modal-body">A parte <strong><?= esc($part['title']) ?></strong> e seu texto serão excluídos permanentemente.</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-danger">Excluir parte</button>
                </div>
            </form>
        </div>
    </div>
<?php elseif ($hasChildren): ?>
    <p class="mt-3">Para excluir esta parte, primeiro remova suas subseções.</p>
<?php endif ?>
<?= view('layout/base', ['title' => 'Escrita', 'content' => ob_get_clean()]) ?>
