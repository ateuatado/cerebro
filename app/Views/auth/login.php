<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cerebro — Ferramenta de pesquisa histórica. Faça login para acessar o grafo de conhecimento.">
    <title>Login — Cerebro</title>

    <!-- Bootstrap local -->
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <!-- Bootstrap Icons local -->
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/icons/bootstrap-icons.min.css') ?>">
    <!-- Estilos -->
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/login.css') ?>">

    <link rel="icon" type="image/svg+xml" href="<?= base_url('favicon.ico') ?>">
</head>
<body>

<!-- Toggle de tema (canto superior direito) -->
<div class="login-theme-toggle">
    <button id="login-theme-toggle" aria-label="Mudar tema">
        <i class="bi bi-sun" aria-hidden="true"></i>
    </button>
</div>

<div class="login-page">
    <div class="login-card">

        <!-- Logo -->
        <div class="login-logo">
            <div class="login-logo-icon" aria-hidden="true">
                <i class="bi bi-diagram-3-fill"></i>
            </div>
            <div class="login-logo-text">
                <div class="name">Cerebro</div>
                <div class="sub">Pesquisa histórica</div>
            </div>
        </div>

        <h1 class="login-title">Bem-vindo(a) ao Cerebro</h1>
        <p class="login-subtitle">Acesse o grafo de conhecimento histórico</p>

        <!-- Alertas -->
        <?php if (session('error')): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0" aria-hidden="true"></i>
            <?= esc(session('error')) ?>
        </div>
        <?php endif; ?>

        <?php if (session('success')): ?>
        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill flex-shrink-0" aria-hidden="true"></i>
            <?= esc(session('success')) ?>
        </div>
        <?php endif; ?>

        <!-- Formulário -->
        <form action="<?= base_url('login') ?>" method="post" id="login-form" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    class="form-control"
                    required
                    autofocus
                    autocomplete="email"
                    inputmode="email"
                    placeholder="seu@email.com"
                    aria-required="true"
                >
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Senha</label>
                <div class="login-pass-wrap">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        aria-required="true"
                        style="padding-right:3rem"
                    >
                    <button type="button"
                            class="login-pass-toggle"
                            id="pass-toggle"
                            aria-label="Mostrar/ocultar senha"
                            aria-controls="password">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="login-submit">
                <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>
                Entrar
            </button>
        </form>

        <div class="login-footer">
            Acesso restrito — ferramenta de pesquisa histórica
        </div>

    </div>
</div>

<!-- Scripts -->
<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<script>
// Toggle mostrar/ocultar senha
document.getElementById('pass-toggle').addEventListener('click', function() {
    var pwd  = document.getElementById('password');
    var icon = this.querySelector('i');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'bi bi-eye-slash';
        this.setAttribute('aria-label', 'Ocultar senha');
    } else {
        pwd.type = 'password';
        icon.className = 'bi bi-eye';
        this.setAttribute('aria-label', 'Mostrar senha');
    }
});
</script>
</body>
</html>
