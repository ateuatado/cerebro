<div class="container-fluid py-3"><div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4">Usuários</h1><a class="btn btn-primary" href="<?= base_url('usuarios/novo') ?>">Novo usuário</a></div>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Senha inicial</th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr><td><?= esc($user['name']) ?></td><td><?= esc($user['email']) ?></td><td><?= esc($user['role']) ?></td><td><?= !empty($user['active']) ? 'Ativo' : 'Inativo' ?></td><td><?= !empty($user['must_change_password']) ? 'Troca obrigatória' : 'Definida' ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
