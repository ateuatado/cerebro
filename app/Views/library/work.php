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
<?php if ($notes === []): ?>
    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-work">Excluir obra</button>
    <div class="modal fade" id="delete-work" tabindex="-1" aria-labelledby="delete-work-title" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= base_url('biblioteca/' . $work['id'] . '/excluir') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="delete-work-title">Excluir obra?</h2>
                </div>
                <div class="modal-body">
                    <p>Você está prestes a excluir <strong><?= esc($work['title']) ?></strong> da Biblioteca. Essa ação não pode ser desfeita.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Excluir obra</button>
                </div>
            </form>
        </div>
    </div>
<?php endif ?>
<?php endif ?>
<?php if (!$editing): ?>
    <section class="mt-4" id="notas" aria-labelledby="notes-title">
        <h2 id="notes-title">Notas bibliográficas</h2>
        <a class="btn btn-primary mb-3" href="<?= base_url('biblioteca/' . $work['id'] . '/notas/nova') ?>">Nova nota bibliográfica</a>
        <?php if ($notes === []): ?>
            <p>Esta obra ainda não tem notas.</p>
        <?php endif ?>
        <?php foreach ($notes as $note): ?>
            <article class="cbr-card mb-3 p-3">
                <h3 class="h5"><?= esc($note['locator'] !== '' ? $note['locator'] : 'Nota geral') ?></h3>
                <?php if ($note['excerpt'] !== ''): ?>
                    <div class="fw-semibold">Excerto</div>
                    <blockquote><?= nl2br(esc($note['excerpt'])) ?></blockquote>
                <?php endif ?>
                <?php if ($note['observations'] !== ''): ?>
                    <div class="fw-semibold">Observações</div>
                    <p><?= nl2br(esc($note['observations'])) ?></p>
                <?php endif ?>
                <a href="<?= base_url('biblioteca/' . $work['id'] . '/notas/' . $note['id'] . '/editar') ?>">Editar nota</a>
            </article>
        <?php endforeach ?>
    </section>
<?php endif ?>
<?= view('layout/base', ['title' => 'Biblioteca', 'content' => ob_get_clean()]) ?>
