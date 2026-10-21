<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Nova senha — Cerebro</title>
<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>"><link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>"><link rel="stylesheet" href="<?= base_url('assets/css/login.css') ?>"></head>
<body><div class="login-page"><div class="login-card">
    <h1 class="login-title">Definir nova senha</h1>
    <p class="login-subtitle">Use uma senha com 12 caracteres, maiúscula, minúscula e número.</p>
    <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif; ?>
    <form method="post" action="<?= base_url('senha/redefinir/' . esc($token)) ?>">
        <?= csrf_field() ?>
        <label for="password" class="form-label">Nova senha</label>
        <input type="password" id="password" name="password" class="form-control mb-3" required autocomplete="new-password">
        <label for="password_confirmation" class="form-label">Confirme a nova senha</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control mb-4" required autocomplete="new-password">
        <button class="btn btn-primary w-100" type="submit">Salvar nova senha</button>
    </form>
</div></div></body></html>
