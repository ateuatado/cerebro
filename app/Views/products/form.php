<?php use App\Models\IntellectualProductModel; ?>
<?php ob_start(); ?>
<a href="<?= base_url('produtos') ?>">Voltar à Produção intelectual</a>
<h1>Novo produto</h1>
<p>Escolha o tipo para receber uma estrutura inicial.</p>
<?php foreach ((session()->getFlashdata('errors') ?? []) as $error): ?>
    <p class="text-danger"><?= esc($error) ?></p>
<?php endforeach ?>
<form method="post" action="<?= base_url('produtos/novo') ?>">
    <?= csrf_field() ?>
    <label class="form-label" for="product-title">Título (pode ser provisório)</label>
    <input class="form-control mb-3" id="product-title" name="title" maxlength="255" required value="<?= esc(old('title', '', false)) ?>">
    <label class="form-label" for="product-kind">Tipo de produto</label>
    <select class="form-select mb-3" id="product-kind" name="kind" required>
        <option value="">Escolha um tipo</option>
        <?php foreach (IntellectualProductModel::KINDS as $value => $label): ?>
            <option value="<?= esc($value) ?>" <?= old('kind', '', false) === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach ?>
    </select>
    <button class="btn btn-primary">Criar produto</button>
    <a class="btn btn-secondary" href="<?= base_url('produtos') ?>">Cancelar</a>
</form>
<?= view('layout/base', ['title' => 'Novo produto', 'content' => ob_get_clean()]) ?>
