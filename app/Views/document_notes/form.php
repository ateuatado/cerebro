<?php use App\Models\DocumentNoteModel; ?>
<?php ob_start(); ?>
<a href="<?= base_url('caderno/fichamentos') ?>">Voltar aos Fichamentos</a>
<h1><?= isset($note['id']) ? 'Editar ficha documental' : 'Criar ficha documental' ?></h1>
<?php foreach ((session()->getFlashdata('errors') ?? []) as $error): ?>
    <p class="text-danger"><?= esc($error) ?></p>
<?php endforeach ?>
<?php
$unlinked = isset($note['id']) && $note['document_id'] === null;
$selected = old('document_id', (string) ($note['document_id'] ?? ''), false);
?>
<form method="post" action="<?= base_url('caderno/fichamentos/' . (isset($note['id']) ? $note['id'] . '/editar' : 'novo')) ?>">
    <?= csrf_field() ?>
    <label class="form-label" for="document-id">Documento do acervo</label>
    <select class="form-select mb-3" name="document_id" id="document-id" <?= $unlinked ? '' : 'required' ?>>
        <option value=""><?= $unlinked ? esc($note['document_label']) . ' (removido do acervo)' : 'Selecione um documento' ?></option>
        <?php foreach ($documents as $document): ?>
            <option value="<?= esc($document['id']) ?>" <?= $selected === (string) $document['id'] ? 'selected' : '' ?>><?= esc($document['name']) ?> (#<?= esc($document['id']) ?>)</option>
        <?php endforeach ?>
    </select>
    <label class="form-label" for="kind">Tipo de ficha</label>
    <select class="form-select mb-3" name="kind" id="kind" required>
        <?php foreach (DocumentNoteModel::KINDS as $key => $label): ?>
            <option value="<?= esc($key) ?>" <?= old('kind', $note['kind'] ?? 'excerto', false) === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach ?>
    </select>
    <label class="form-label" for="locator">Página, folha, foto ou trecho</label>
    <input class="form-control" id="locator" name="locator" maxlength="255" aria-describedby="locator-help" value="<?= esc(old('locator', $note['locator'] ?? '', false)) ?>">
    <p class="form-text" id="locator-help">Obrigatório quando houver excerto.</p>
    <label class="form-label" for="excerpt">Excerto</label>
    <textarea class="form-control mb-3" name="excerpt" id="excerpt" rows="5" maxlength="20000"><?= esc(old('excerpt', $note['excerpt'] ?? '', false)) ?></textarea>
    <label class="form-label" for="observations">Observações ou paráfrase</label>
    <textarea class="form-control mb-3" name="observations" id="observations" rows="5" maxlength="20000"><?= esc(old('observations', $note['observations'] ?? '', false)) ?></textarea>
    <button class="btn btn-primary">Salvar ficha</button>
</form>
<?= view('layout/base', ['title' => 'Ficha documental', 'content' => ob_get_clean()]) ?>
