# Plan 2 — Autenticação e usuários

**Status**: planejado  
**Data**: 2026-07-27  
**Spec**: `.speckit/spec-2-auth.md`  
**Dependências**: Spec 1 (modelo de dados), Constituição, AGENTS.md

---

## 1. Visão geral do plano

Este plano decompõe a Spec 2 em tarefas implementáveis, ordenadas por
dependência estrita. O escopo é migrations + model + service + filter +
controller + view + seeds — autenticação completa por email/senha com
sessão nativa do CodeIgniter 4.

Cada tarefa gera **exatamente um commit** (Princípio VI).

---

## 2. Pré-requisito

Nenhum pré-requisito adicional além das migrations da Spec 1 já instaladas.

---

## 3. Tarefas

### Tarefa 1 — Migration: CreateUsersTable

**Arquivo**: `app/Database/Migrations/2026-07-27-000003_CreateUsersTable.php`

**O que criar**:

1. Tabela `users` (seção 3.1 da Spec)
2. Índices: `idx_users_email`, `idx_users_role`, `idx_users_active`
3. Trigger `trg_users_updated_at` (reusa função `update_timestamp()` da Spec 1)

**Atenção**:

- `email` UNIQUE NOT NULL
- `role` CHECK (`'coordenador'` ou `'colaborador'`), default `'colaborador'`
- `active` BOOLEAN DEFAULT `true`
- `password_hash` TEXT NOT NULL (hash bcrypt, não a senha em si)

**Rollback (`down`)**: DROP trigger → DROP índices → DROP tabela.

**Validação**: `php spark migrate` sobe sem erro.

**Dependências**: Spec 1 (função `update_timestamp()` já existe).

---

### Tarefa 2 — Migration: AlterCreatedByValidatedByToFk

**Arquivo**: `app/Database/Migrations/2026-07-27-000004_AlterCreatedByValidatedByToFk.php`

**Passo 0 — limpeza de dados de teste (executar antes de rodar a migration)**:

```sql
DELETE FROM relationships;
DELETE FROM entities;
```

As colunas `created_by`/`validated_by` contêm valores TEXT ('seed') de
testes anteriores, não convertíveis para INTEGER. Esta limpeza é
obrigatória e faz parte da execução da tarefa — não é uma nota externa.
Após os DELETEs, as colunas estarão NULL em todos os registros restantes
(nenhum), e a conversão `USING (created_by::INTEGER)` é segura porque
`NULL::INTEGER = NULL`.

**O que fazer** (na migration) para `entities` e `relationships`:

1. `DROP CONSTRAINT IF EXISTS confirmed_requires_validation`
2. `ALTER COLUMN created_by TYPE INTEGER USING (created_by::INTEGER)`
3. `ALTER COLUMN validated_by TYPE INTEGER USING (validated_by::INTEGER)`
4. `ADD CONSTRAINT fk_*_created_by FOREIGN KEY (created_by) REFERENCES users(id)`
5. `ADD CONSTRAINT fk_*_validated_by FOREIGN KEY (validated_by) REFERENCES users(id)`
6. `ADD CONSTRAINT confirmed_requires_validation CHECK (...)`

**Rollback (`down`)**: reverter cada `ALTER COLUMN ... TYPE TEXT`, remover
FKs, recriar constraint original (se necessário). Como a conversão
TEXT→INTEGER→TEXT pode perder dados não numéricos, o down é best-effort
(documentar no código).

**Validação**:

```bash
php spark migrate                    # sobe sem erro
php spark migrate:rollback           # reverte (best-effort)
php spark migrate                    # sobe novamente
```

**Dependências**: T1 (tabela `users` deve existir para as FKs).

---

### Tarefa 3 — Model: UserModel

**Arquivo**: `app/Models/UserModel.php`

**O que implementar**:

- Extende `CodeIgniter\Model`
- `$table = 'users'`
- `$allowedFields`: `name`, `email`, `password_hash`, `role`, `active`
- `$useTimestamps = true`
- `$returnType = 'array'`

**Métodos**:

| Método | Comportamento |
| -------- | -------------- |
| `findByEmail(string $email)` | `WHERE email = ?`, retorna array ou null |
| `verifyPassword(string $password, string $hash)` | `password_verify($password, $hash)` |
| `isActive(int $id)` | Busca por id, retorna `(bool) $user['active']` |
| `isCoordenador(int $id)` | Busca por id, retorna `$user['role'] === 'coordenador'` |

**Validação**: instanciar o model, verificar `$table` e `$allowedFields`.

**Dependências**: T1 (tabela existe).

---

### Tarefa 4 — Service: AuthService

**Arquivo**: `app/Services/AuthService.php`

**O que implementar**:

Classe concreta (não singleton, instanciada via `new` ou `service()` helper)
que centraliza a lógica de autenticação.

| Método | Comportamento |
| -------- | -------------- |
| `login(string $email, string $password): bool` | Busca por email, verifica senha com `password_verify`, verifica `active`, popula `session()` com `user_id`, `email`, `role`. Retorna false em qualquer falha — mesma mensagem genérica para email inexistente, senha errada e conta inativa (anti-enumeração). |
| `logout(): void` | `session()->destroy()` |
| `currentUser(): ?array` | Retorna `['user_id' => ..., 'email' => ..., 'role' => ...]` da sessão, ou null |
| `isLoggedIn(): bool` | `session('user_id') !== null` |
| `isCoordenador(): bool` | `session('role') === 'coordenador'` |
| `canConfirm(): bool` | Alias para `isCoordenador()` — extensível |

**Validação**: instanciar, chamar `login()` com credenciais de seed,
verificar `currentUser()`.

**Dependências**: T3 (UserModel).

---

### Tarefa 5 — Filter: AuthFilter + registro em Filters.php

**Arquivos**:

- `app/Filters/AuthFilter.php`
- editar `app/Config/Filters.php`

**O que implementar**:

- `AuthFilter` implementa `FilterInterface`
- `before()`: verifica `session('user_id')`; se ausente, redireciona para
  `/login` com flash message "Faça login para acessar esta página."
- `after()`: no-op

**Registro em Filters.php**:

```php
public array $aliases = [
    // ... existentes ...
    'auth' => \App\Filters\AuthFilter::class,
];
```

**Validação**: acessar rota protegida sem sessão → redireciona para `/login`.

**Dependências**: T4 (AuthService para `isLoggedIn()`, ou acesso direto à sessão).

---

### Tarefa 6 — Controller: AuthController + view de login + rotas

**Arquivos**:

- `app/Controllers/AuthController.php`
- `app/Views/auth/login.php`
- editar `app/Config/Routes.php`

**O que implementar**:

**AuthController**:

| Método | Rota | Comportamento |
| -------- | ------ | -------------- |
| `loginForm()` | `GET /login` | Renderiza `auth/login.php`. Se já logado, redireciona para `/` |
| `loginAction()` | `POST /login` | Valida `$email`/`$password`, chama `AuthService::login()`. Sucesso → redirect `/`. Falha → redirect `/login` com flash error "Email ou senha inválidos." |
| `logout()` | `GET /logout` | `AuthService::logout()`, redirect `/login` |

**View `auth/login.php`**:

- Formulário Bootstrap com campos email e senha
- Exibe flash messages (erro de login)
- Assets locais, sem CDN, sem CSS/JS inline (regras do AGENTS.md)
- Referência: `base_url('assets/css/...')`, `base_url('assets/js/...')`

**Rotas em Routes.php**:

```php
$routes->get('login', 'AuthController::loginForm');
$routes->post('login', 'AuthController::loginAction');
$routes->get('logout', 'AuthController::logout');
```

**Validação**: acessar `GET /login` → formulário renderizado; `POST /login`
com credenciais válidas → redireciona para `/`.

**Dependências**: T4 (AuthService), T5 (AuthFilter para referência de
consistência, embora /login em si não use o filtro).

---

### Tarefa 7 — Seed: UserSeeder

**Arquivo**: `app/Database/Seeds/UserSeeder.php`

**O que implementar**:

- Gera senhas aleatórias via `bin2hex(random_bytes(8))`
- Exibe credenciais UMA vez no console durante `php spark db:seed UserSeeder`
- Insere 1 coordenador + 1 colaborador
- Senhas **nunca** são fixadas como string literal no código

**Validação**:

```bash
php spark db:seed UserSeeder
# Deve exibir:
# === Credenciais geradas (anote antes de fechar o terminal) ===
# Coordenador: eliane@exemplo.com / <random>
# Colaborador: aluno@exemplo.com / <random>
```

**Dependências**: T1 (tabela `users`).

---

### Tarefa 8 — Seed de validação dos critérios de aceite

**Arquivo**: `app/Database/Seeds/AuthAcceptanceSeeder.php`

**O que implementar**:

Seed que exercita os critérios de aceite da Spec 2. Diferente da Spec 1,
vários critérios exigem requests HTTP (login/logout/sessão). A abordagem:

- Usar `$this->call()` do CodeIgniter 4 (feature de teste integrado)
  para simular requests GET/POST com sessão
- Cada caso é um método privado chamado por `run()`

**Casos**:

| # | Caso | Critério |
| --- | ------ | ---------- |
| 1 | `php spark migrate` sobe sem erro | #1 |
| 2 | Login válido → sessão populada com `user_id`, `email`, `role` | #2 |
| 3 | Login senha inválida → mensagem genérica | #3 |
| 4 | Login email inexistente → mesma mensagem genérica | #4 |
| 5 | Logout → sessão destruída | #5 |
| 6 | Rota protegida sem sessão → redirect `/login` | #6 |
| 7 | Rota protegida com sessão → acesso normal | #7 |
| 8 | Usuário inativo → login rejeitado | #8 |
| 9 | Colaborador tenta confirmar → 403 | #9 |
| 10 | Coordenador confirma → 200 | #10 |
| 11 | FK rejeita ID inexistente em created_by | #11 |
| 12 | Migration alteração roda em tabelas vazias | #12 |

**Pré-requisito do seed**: o banco já deve estar com todas as migrations
da Spec 1 e Spec 2 aplicadas (T1 e T2 concluídas). O seed NÃO executa
`migrate:refresh` — ele valida contra o schema existente. Para os
critérios #1 e #12, o seed verifica via `migrate:status` que todas as
migrations estão aplicadas e que as FKs e constraints da Spec 2 existem
no schema atual.

**Validação**:

```bash
php spark db:seed AuthAcceptanceSeeder
# Deve passar todos os 12 casos
```

**Dependências**: T6 (AuthController, rotas), T7 (usuários seed para login).

---

## 4. Grafo de dependências

```
T1 (CreateUsersTable)
 │
 ├─► T3 (UserModel)
 │    │
 │    └─► T4 (AuthService)
 │         │
 │         ├─► T5 (AuthFilter + Filters.php)
 │         │
 │         └─► T6 (AuthController + view + rotas)
 │              │
 │              └─► T8 (AuthAcceptanceSeeder)
 │
 ├─► T2 (AlterCreatedByValidatedByToFk) ◄── pré: DELETE FROM relationships; DELETE FROM entities;
 │    │
 │    └─► (validado em T8)
 │
 └─► T7 (UserSeeder)
      │
      └─► T8 (precisa de usuários para testar login)
```

## 5. Ordem de execução

```
T1 → T3 → T4 → T5 → T6
                ↓
T1 → T7 ────────┤
                ↓
T1 → T2 ────────┴──→ T8
```

T2, T7 e T6 podem ser paralelizados após T1 estar pronto, mas T8 exige
todos concluídos.

---

## 6. Mapeamento tarefas → critérios de aceite

| Critério | Coberto por |
| ---------- | ------------- |
| 1 — migrate sobe sem erro | T1, T2 |
| 2 — Login válido cria sessão | T4 + T6 + T8 |
| 3 — Senha inválida → mensagem genérica | T4 + T8 |
| 4 — Email inexistente → mesma mensagem | T4 + T8 |
| 5 — Logout destrói sessão | T4 + T6 + T8 |
| 6 — Rota protegida sem sessão → redirect | T5 + T8 |
| 7 — Rota protegida com sessão → acesso | T5 + T6 + T8 |
| 8 — Usuário inativo não loga | T4 + T8 |
| 9 — Colaborador não confirma → 403 | T6 + T8 |
| 10 — Coordenador confirma → 200 | T6 + T8 |
| 11 — FK rejeita ID inexistente | T2 + T8 |
| 12 — Migration em tabelas vazias | T2 + T8 |

---

## 7. Verificação de conformidade

| Princípio | Como o plano garante |
| ----------- | --------------------- |
| I — Rastreabilidade | T2 (FKs reais para users em created_by/validated_by) |
| II — Fato vs Hipótese | T2 (constraint confirmed_requires_validation preservada) |
| III — Revisão humana | T6 (só coordenador confirma) + T2 (validated_by FK) |
| IV — Simplicidade | 8 tarefas granulares, 1 commit por tarefa |
| V — Stack fixa | `password_hash()` nativo, sessão CodeIgniter, PostgreSQL, Bootstrap |
| VI — Governança | Plano segue constitution → specify → plan → tasks |

O plano está pronto para `/speckit.tasks`.
