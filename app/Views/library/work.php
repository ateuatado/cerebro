<?php use App\Models\BibliographicWorkModel; ?>
<?php ob_start(); ?>
<a href="<?= base_url('biblioteca') ?>">Voltar à Biblioteca</a>
<h1><?= esc($editing ? (isset($work['id']) ? 'Editar obra' : 'Cadastrar obra') : $work['title']) ?></h1>
<?php $fields = [
    'authors' => 'Autoria', 'publication_date' => 'Data da publicação (AAAA, AAAA-MM ou AAAA-MM-DD)',
    'publication' => 'Editora, periódico ou instituição', 'identifier' => 'Identificador (DOI, ISBN ou URL)',
]; ?>
<?php if ($editing): ?>
    <form method="post" action="<?= base_url(isset($work['id']) ? 'biblioteca/' . $work['id'] . '/editar' : 'biblioteca/nova') ?>">
        <?= csrf_field() ?>
        <?php foreach ((session()->getFlashdata('errors') ?? []) as $error): ?>
            <p class="text-danger"><?= esc($error) ?></p>
        <?php endforeach ?>
        <label class="form-label" for="title">Título</label>
        <input class="form-control mb-3" id="title" name="title" required maxlength="255" value="<?= esc(old('title', $work['title'] ?? '', false)) ?>">
        <label class="form-label" for="kind">Tipo de obra</label>
        <select class="form-select mb-3" id="kind" name="kind" required>
            <?php foreach (BibliographicWorkModel::KINDS as $key => $label): ?>
                <option value="<?= esc($key) ?>" <?= old('kind', $work['kind'] ?? 'book', false) === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
        <?php foreach ($fields as $field => $label): ?>
            <label class="form-label" for="<?= esc($field) ?>"><?= esc($label) ?></label>
            <input class="form-control mb-3" id="<?= esc($field) ?>" name="<?= esc($field) ?>" maxlength="<?= $field === 'authors' ? 4000 : ($field === 'publication_date' ? 10 : 255) ?>" value="<?= esc(old($field, $work[$field] ?? '', false)) ?>">
        <?php endforeach ?>
        <button class="btn btn-primary">Salvar obra</button>
    </form>
<?php else: ?>
    <dl>
        <dt>Tipo de obra</dt><dd><?= esc(BibliographicWorkModel::KINDS[$work['kind']] ?? $work['kind']) ?></dd>
        <?php foreach ($fields as $field => $label): ?>
            <?php if (!empty($work[$field])): ?>
                <dt><?= esc($label) ?></dt><dd><?= esc($work[$field]) ?></dd>
            <?php endif ?>
        <?php endforeach ?>
    </dl>
    <a class="btn btn-secondary" href="<?= base_url('biblioteca/' . $work['id'] . '/editar') ?>">Editar obra</a>
<?php endif ?>
<?= view('layout/base', ['title' => 'Biblioteca', 'content' => ob_get_clean()]) ?>
