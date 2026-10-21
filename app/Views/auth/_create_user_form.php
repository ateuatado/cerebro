<div class="container-fluid py-3"><div class="row justify-content-center"><div class="col-lg-7"><div class="card"><div class="card-body"><h1 class="h4 mb-3">Cadastrar usuário</h1>
<form method="post" action="<?= base_url('usuarios/novo') ?>"><?= csrf_field() ?>
<label class="form-label" for="name">Nome</label><input class="form-control mb-3" id="name" name="name" required autocomplete="name">
<label class="form-label" for="email">E-mail</label><input class="form-control mb-3" type="email" id="email" name="email" required autocomplete="email">
<label class="form-label" for="role">Perfil</label><select class="form-select mb-3" id="role" name="role"><option value="colaborador">Colaborador</option><option value="coordenador">Coordenador</option></select>
<label class="form-label" for="password">Senha temporária</label><input class="form-control mb-1" type="password" id="password" name="password" required autocomplete="new-password"><div class="form-text mb-4">O usuário será obrigado a trocar esta senha no primeiro acesso.</div>
<button class="btn btn-primary" type="submit">Criar usuário</button>
</form></div></div></div></div>
