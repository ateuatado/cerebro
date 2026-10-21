<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar senha — Cerebro</title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/login.css') ?>">
</head>
<body><div class="login-page"><div class="login-card">
    <h1 class="login-title">Recuperar senha</h1>
    <p class="login-subtitle">Informe seu e-mail para receber um link de redefinição.</p>
    <?php if (session('success') || session('error')): ?><div class="alert <?= session('success') ? 'alert-success' : 'alert-danger' ?>"><?= esc(session('success') ?: session('error')) ?></div><?php endif; ?>
    <form method="post" action="<?= base_url('senha/esqueci') ?>">
        <?= csrf_field() ?>
        <label for="email" class="form-label">E-mail</label>
        <input type="email" id="email" name="email" class="form-control mb-4" required autocomplete="email">
        <button class="btn btn-primary w-100" type="submit">Enviar instruções</button>
    </form>
    <div class="text-center mt-3"><a href="<?= base_url('login') ?>">Voltar ao login</a></div>
</div></div></body></html>
