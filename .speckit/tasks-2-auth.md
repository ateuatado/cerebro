# Tasks 2 — Autenticação e usuários

**Status**: pronto para execução  
**Data**: 2026-07-27  
**Spec**: `.speckit/spec-2-auth.md`  
**Plan**: `.speckit/plan-2-auth.md`

---

## Ordem de execução

```
T1 → T3 → T4 → T5 → T6
               ↓
T1 → T7 ───────┤
               ↓
T1 → T2 ───────┴──→ T8
```

Cada tarefa = 1 commit. T2, T6 e T7 podem ser paralelizados após T1.

---

## T1 — Migration: CreateUsersTable

**Arquivo**: `app/Database/Migrations/2026-07-27-000003_CreateUsersTable.php`

**Ação**: criar migration com:

- Tabela `users` (seção 3.1 da Spec): `id`, `name`, `email` UNIQUE,
  `password_hash`, `role` CHECK, `active` BOOLEAN DEFAULT `true`,
  `created_at`, `updated_at`
- Índices: `idx_users_email`, `idx_users_role`, `idx_users_active`
- Trigger `trg_users_updated_at` (usa `CREATE OR REPLACE FUNCTION
  update_timestamp()` da Spec 1)

**Rollback (`down`)**: DROP trigger → DROP índices → DROP tabela.

**Validação**:

```bash
php spark migrate                    # sobe sem erro
php spark migrate:rollback           # desce
php spark migrate                    # sobe novamente
```

**Mensagem de commit**:

```
T1: migration CreateUsersTable — tabela users, índices, trigger
```

**Dependências**: Spec 1 migrations (função `update_timestamp()` já existe).

---

## T2 — Migration: AlterCreatedByValidatedByToFk

**Arquivo**: `app/Database/Migrations/2026-07-27-000004_AlterCreatedByValidatedByToFk.php`

**Passo 0 (manual, antes de rodar a migration) — limpeza obrigatória**:

```sql
DELETE FROM relationships;
DELETE FROM entities;
```

**Ação** (na migration): para `entities` e `relationships`:

1. `DROP CONSTRAINT IF EXISTS confirmed_requires_validation`
2. `ALTER COLUMN created_by TYPE INTEGER USING (created_by::INTEGER)`
3. `ALTER COLUMN validated_by TYPE INTEGER USING (validated_by::INTEGER)`
4. `ADD CONSTRAINT fk_*_created_by FOREIGN KEY (created_by) REFERENCES users(id)`
5. `ADD CONSTRAINT fk_*_validated_by FOREIGN KEY (validated_by) REFERENCES users(id)`
6. `ADD CONSTRAINT confirmed_requires_validation CHECK (...)`

**Rollback (`down`)**: reverter ALTER COLUMN para TEXT, remover FKs,
recriar constraint original. Best-effort (a conversão INTEGER→TEXT é
segura, mas dados originais não numéricos são irrecuperáveis).

**Validação**:

```bash
php spark migrate                    # sobe sem erro
php spark migrate:rollback           # reverte
php spark migrate                    # sobe novamente
```

**Mensagem de commit**:

```
T2: migration AlterCreatedByValidatedByToFk — TEXT → INTEGER FK para users
```

**Dependências**: T1 (tabela `users` deve existir para as FKs).

---

## T3 — Model: UserModel

**Arquivo**: `app/Models/UserModel.php`

**Ação**: model para usuários:

- Extende `CodeIgniter\Model`
- `$table = 'users'`
- `$allowedFields`: `name`, `email`, `password_hash`, `role`, `active`
- `$useTimestamps = true`
- `$returnType = 'array'`

**Métodos**:

| Método | Comportamento |
| -------- | -------------- |
| `findByEmail(string $email)` | `WHERE email = ?`, retorna primeiro registro ou null |
| `verifyPassword(string $password, string $hash)` | `password_verify($password, $hash)` |
| `isActive(int $id)` | Busca por id, retorna `(bool) $row['active']` |
| `isCoordenador(int $id)` | Busca por id, retorna `$row['role'] === 'coordenador'` |

**Validação**: instanciar, verificar `$table` retorna `'users'`.

**Mensagem de commit**:

```
T3: UserModel — findByEmail, verifyPassword, isActive, isCoordenador
```

**Dependências**: T1.

---

## T4 — Service: AuthService

**Arquivo**: `app/Services/AuthService.php`

**Ação**: classe concreta que centraliza lógica de autenticação. Usa
`session()` helper do CodeIgniter 4.

**Métodos**:

| Método | Comportamento |
| -------- | -------------- |
| `login(string $email, string $password): bool` | 1. Busca usuário por email. 2. Se não encontrado, retorna false. 3. `password_verify()`. 4. Se inválida, retorna false. 5. Verifica `active`. 6. Se inativo, retorna false. 7. Popula `session()` com `user_id`, `email`, `role`. 8. Retorna true. **Todos os caminhos de falha são indistinguíveis externamente.** |
| `logout(): void` | `session()->destroy()` |
| `currentUser(): ?array` | `['user_id' => session('user_id'), ...]` ou null |
| `isLoggedIn(): bool` | `session('user_id') !== null` |
| `isCoordenador(): bool` | `session('role') === 'coordenador'` |
| `canConfirm(): bool` | Alias para `isCoordenador()` |

**Validação**: instanciar, verificar que métodos retornam tipos corretos.

**Mensagem de commit**:

```
T4: AuthService — login/logout/currentUser/canConfirm
```

**Dependências**: T3.

---

## T5 — Filter: AuthFilter + registro em Filters.php

**Arquivos**:

- `app/Filters/AuthFilter.php` (criar)
- `app/Config/Filters.php` (editar)

**Ação**:

- `AuthFilter` implementa `CodeIgniter\Filters\FilterInterface`
- `before()`: se `session('user_id')` é null, redireciona para `/login`
  com flash message "Faça login para acessar esta página."
- `after()`: no-op
- Registrar alias no `$aliases` de `Filters.php`:
  `'auth' => \App\Filters\AuthFilter::class`

**Validação**: acessar qualquer rota com filtro `auth` sem sessão →
redireciona para `/login`.

**Mensagem de commit**:

```
T5: AuthFilter + registro em Filters.php — proteção de rotas
```

**Dependências**: T4 (usa `AuthService::isLoggedIn()` ou acesso direto à
sessão).

---

## T6 — AuthController + view de login + rotas

**Arquivos**:

- `app/Controllers/AuthController.php` (criar)
- `app/Views/auth/login.php` (criar)
- `app/Config/Routes.php` (editar)

**Ação**:

**AuthController**:

| Método | Rota | Comportamento |
| -------- | ------ | -------------- |
| `loginForm()` | `GET /login` | Se já logado, redirect `/`. Senão, renderiza view `auth/login`. |
| `loginAction()` | `POST /login` | Valida `$this->request->getPost(['email','password'])`, chama `AuthService::login()`. Sucesso → redirect `/`. Falha → redirect `/login` com flash error "Email ou senha inválidos." |
| `logout()` | `GET /logout` | `AuthService::logout()`, redirect `/login`. |
| `testeAutorizacao()` | `POST /teste-autorizacao-coordenador` | **Scaffolding de teste.** Verifica `AuthService::canConfirm()`. Se true → 200 `{"autorizado":true}`. Se false → 403 `{"autorizado":false,"motivo":"requer role coordenador"}`. Não altera nenhum dado. Este endpoint existe apenas para validar a lógica de autorização da Spec 2 e **será substituído** pelo endpoint real de confirmação de entidades quando a spec de revisão for implementada. |

**View `auth/login.php`**:

- Formulário Bootstrap 5: email (type="email"), senha (type="password"),
  botão submit
- Exibe `session('error')` e `session('success')` como flash messages
- CSS e JS em arquivos separados em `public/assets/`, referenciados via
  `base_url()` — zero inline, zero CDN (AGENTS.md)
- Título: "Cerebro — Login"

**Rotas**:

```php
$routes->get('login', 'AuthController::loginForm');
$routes->post('login', 'AuthController::loginAction');
$routes->get('logout', 'AuthController::logout');
// Scaffolding de teste — será substituído pelo endpoint real de confirmação
$routes->post('teste-autorizacao-coordenador', 'AuthController::testeAutorizacao', ['filter' => 'auth']);
```

**Validação**:

```bash
# GET /login → HTML com formulário
# POST /login com credenciais do UserSeeder → redirect /
# GET /logout → sessão destruída, redirect /login
```

**Mensagem de commit**:

```
T6: AuthController, view login, rotas, endpoint scaffolding teste-autorizacao-coordenador
```

**Dependências**: T4, T5.

---

## T7 — Seed: UserSeeder

**Arquivo**: `app/Database/Seeds/UserSeeder.php`

**Ação**:

- Gera 2 senhas aleatórias via `bin2hex(random_bytes(8))`
- Exibe credenciais UMA vez no console
- Insere 1 coordenador + 1 colaborador
- **Nenhuma senha fixada como string literal no código**

**Execução esperada**:

```bash
php spark db:seed UserSeeder
# === Credenciais geradas (anote antes de fechar o terminal) ===
# Coordenador: eliane@exemplo.com / a1b2c3d4e5f6g7h8
# Colaborador: aluno@exemplo.com / 9i0j1k2l3m4n5o6p
# ============================================================
```

**Mensagem de commit**:

```
T7: UserSeeder — coordenador + colaborador com senhas aleatórias
```

**Dependências**: T1.

---

## T8 — Seed de validação dos critérios de aceite

**Arquivo**: `app/Database/Seeds/AuthAcceptanceSeeder.php`

**Pré-requisito**: banco com todas as migrations aplicadas (T1 + T2) e
usuários criados (T7). O seed **não** executa `migrate:refresh` — valida
contra o schema existente.

**Ação**: seed que exercita os 12 critérios de aceite via
`$this->call()` do CodeIgniter 4 (simula requests HTTP com sessão).

**Casos**:

| # | Caso | Critério |
| --- | ------ | ---------- |
| 1 | Verificar `migrate:status` — todas as migrations aplicadas | #1 |
| 2 | `$this->call('post', 'login', [...])` com credenciais válidas → sessão populada | #2 |
| 3 | `$this->call('post', 'login', [...])` com senha errada → flash error, mesma mensagem | #3 |
| 4 | `$this->call('post', 'login', [...])` com email inexistente → mesma mensagem | #4 |
| 5 | Login → `$this->call('get', 'logout')` → sessão vazia | #5 |
| 6 | `$this->call('get', 'rota-protegida')` sem sessão → header `Location: /login` | #6 |
| 7 | Login → `$this->call('get', 'rota-protegida')` com sessão → 200 | #7 |
| 8 | Desativar usuário → tentar login → rejeitado (mesma mensagem genérica) | #8 |
| 9 | Login como colaborador → `$this->call('post', 'teste-autorizacao-coordenador')` → 403 | #9 |
| 10 | Login como coordenador → `$this->call('post', 'teste-autorizacao-coordenador')` → 200 | #10 |
| 11 | Tentar inserir entidade com `created_by = 99999` (ID inexistente) → FK rejeita | #11 |
| 12 | Verificar que colunas `created_by`/`validated_by` são tipo INTEGER com FK | #12 |

**Nota**: os casos 9 e 10 usam o endpoint `POST /teste-autorizacao-coordenador`
criado na T6 como scaffolding de teste. O endpoint apenas verifica
`AuthService::canConfirm()` e retorna 200 ou 403 — não altera dados.

**Estrutura**: cada caso é um método privado chamado por `run()`, com
try/catch e asserts documentando o comportamento esperado.

**Execução**:

```bash
php spark db:seed AuthAcceptanceSeeder
# Deve exibir 12/12 casos passando
```

**Mensagem de commit**:

```
T8: AuthAcceptanceSeeder — valida 12 critérios de aceite da Spec 2
```

**Dependências**: T6 + T7.

---

## Resumo de commits (8)

```
T1: migration CreateUsersTable — tabela users, índices, trigger
T2: migration AlterCreatedByValidatedByToFk — TEXT → INTEGER FK para users
T3: UserModel — findByEmail, verifyPassword, isActive, isCoordenador
T4: AuthService — login/logout/currentUser/canConfirm
T5: AuthFilter + registro em Filters.php — proteção de rotas
T6: AuthController, view login, rotas, endpoint scaffolding teste-autorizacao-coordenador
T7: UserSeeder — coordenador + colaborador com senhas aleatórias
T8: AuthAcceptanceSeeder — valida 12 critérios de aceite da Spec 2
```
