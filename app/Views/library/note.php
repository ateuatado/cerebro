<?php ob_start(); ?>
<a href="<?= base_url('biblioteca/' . $work['id']) ?>">Voltar à obra</a>
<h1><?= isset($note['id']) ? 'Editar nota bibliográfica' : 'Nova nota bibliográfica' ?></h1>
<p><?= esc($work['title']) ?><?php if (!empty($work['authors'])): ?> — <?= esc($work['authors']) ?><?php endif ?></p>
<?php foreach ((session()->getFlashdata('errors') ?? []) as $error): ?>
    <p class="text-danger"><?= esc($error) ?></p>
<?php endforeach ?>
<form method="post" action="<?= base_url('biblioteca/' . $work['id'] . '/notas/' . ($note['id'] ?? 'nova')) ?>">
    <?= csrf_field() ?>
    <label class="form-label" for="locator">Página ou outro localizador</label>
    <input class="form-control mb-1" id="locator" name="locator" maxlength="255" aria-describedby="locator-help" value="<?= esc(old('locator', $note['locator'] ?? '', false)) ?>">
    <p class="form-text mb-3" id="locator-help">Obrigatório ao registrar um excerto. Ex.: p. 42, cap. 3.</p>
    <label class="form-label" for="excerpt">Excerto</label>
    <textarea class="form-control mb-3" id="excerpt" name="excerpt" rows="5" maxlength="20000"><?= esc(old('excerpt', $note['excerpt'] ?? '', false)) ?></textarea>
    <label class="form-label" for="observations">Observações da pesquisadora</label>
    <textarea class="form-control mb-3" id="observations" name="observations" rows="5" maxlength="20000"><?= esc(old('observations', $note['observations'] ?? '', false)) ?></textarea>
    <button class="btn btn-primary">Salvar nota</button>
</form>
<?php if (isset($note['id'])): ?>
    <button type="button" class="btn btn-outline-danger mt-3" data-bs-toggle="modal" data-bs-target="#delete-note">Excluir nota</button>
    <div class="modal fade" id="delete-note" tabindex="-1" aria-labelledby="delete-note-title" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= base_url('biblioteca/' . $work['id'] . '/notas/' . $note['id'] . '/excluir') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="delete-note-title">Excluir nota bibliográfica?</h2>
                </div>
                <div class="modal-body">
                    <p>Esta nota de <strong><?= esc($work['title']) ?></strong><?php if ($note['locator'] !== ''): ?> (<?= esc($note['locator']) ?>)<?php endif ?> será excluída permanentemente.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Excluir nota</button>
                </div>
            </form>
        </div>
    </div>
<?php endif ?>
<?= view('layout/base', ['title' => 'Nota bibliográfica', 'content' => ob_get_clean()]) ?>
