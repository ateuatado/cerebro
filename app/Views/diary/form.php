<?php ob_start(); ?>
<a href="<?= base_url('caderno/diario') ?>">Voltar ao Diário</a>
<h1><?= isset($entry['id']) ? 'Editar registro' : 'Registrar atividade' ?></h1>
<?php foreach ((session()->getFlashdata('errors') ?? []) as $error): ?>
    <p class="text-danger"><?= esc($error) ?></p>
<?php endforeach ?>
<form method="post" action="<?= base_url(isset($entry['id']) ? 'caderno/diario/' . $entry['id'] . '/editar' : 'caderno/diario/novo') ?>">
    <?= csrf_field() ?>
    <label class="form-label" for="activity_date">Data da atividade</label>
    <input type="date" class="form-control mb-3" id="activity_date" name="activity_date" required value="<?= esc(old('activity_date', $entry['activity_date'] ?? date('Y-m-d'), false)) ?>">
    <label class="form-label" for="account">Relato</label>
    <textarea class="form-control mb-3" id="account" name="account" rows="8" required maxlength="20000"><?= esc(old('account', $entry['account'] ?? '', false)) ?></textarea>
    <button class="btn btn-primary">Salvar registro</button>
    <a class="btn btn-secondary" href="<?= base_url('caderno/diario') ?>">Cancelar</a>
</form>
<?php if (isset($entry['id'])): ?>
    <button type="button" class="btn btn-outline-danger mt-3" data-bs-toggle="modal" data-bs-target="#delete-diary-entry">Excluir registro</button>
    <div class="modal fade" id="delete-diary-entry" tabindex="-1" aria-labelledby="delete-diary-title" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= base_url('caderno/diario/' . $entry['id'] . '/excluir') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="delete-diary-title">Excluir registro do Diário?</h2>
                </div>
                <div class="modal-body">
                    <p>O registro de <?= esc(implode('/', array_reverse(explode('-', $entry['activity_date'])))) ?> será excluído permanentemente.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Excluir registro</button>
                </div>
            </form>
        </div>
    </div>
<?php endif ?>
<?= view('layout/base', ['title' => 'Diário de pesquisa', 'content' => ob_get_clean()]) ?>
