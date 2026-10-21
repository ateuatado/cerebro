<div class="container-fluid py-3"><div class="row justify-content-center"><div class="col-lg-7"><div class="card">
    <div class="card-body"><h1 class="h4 mb-3">Trocar senha</h1><p class="text-muted">A senha deve ter ao menos 12 caracteres, uma letra maiúscula, uma minúscula e um número.</p>
    <form method="post" action="<?= esc($action) ?>"><?= csrf_field() ?>
        <?php if ($current): ?><label class="form-label" for="current_password">Senha atual</label><input class="form-control mb-3" type="password" id="current_password" name="current_password" required autocomplete="current-password"><?php endif; ?>
        <label class="form-label" for="password">Nova senha</label><input class="form-control mb-3" type="password" id="password" name="password" required autocomplete="new-password">
        <label class="form-label" for="password_confirmation">Confirme a nova senha</label><input class="form-control mb-4" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
        <button class="btn btn-primary" type="submit">Salvar senha</button>
    </form></div></div></div></div>
