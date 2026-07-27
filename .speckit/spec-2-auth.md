# Spec 2 — Autenticação e usuários

**Status**: especificado  
**Data**: 2026-07-27  
**Dependências**: Spec 1 (modelo de dados), Constituição, AGENTS.md  
**Princípios aplicáveis**: III (revisão humana), IV (simplicidade), V (stack
fixa), VI (governança)

---

## 1. Visão geral

Esta spec define a camada de autenticação e usuários do projeto Cerebro,
substituindo as colunas `created_by`/`validated_by` (atualmente TEXT) por
FKs reais para uma tabela `users`. É pré-requisito para qualquer cadastro
de dados de produção — sem usuários reais, a autoria e validação ficariam
como texto livre inconsistente, comprometendo a rastreabilidade de quem
inseriu ou validou cada dado.

O escopo é autenticação por email/senha via sessão nativa do CodeIgniter 4,
com dois papéis (`coordenador` e `colaborador`) e um filtro de autorização
que distingue quem pode confirmar hipóteses.

---

## 2. Decisões de design

### 2.1 Password hashing

Usar `password_hash()` nativo do PHP com algoritmo `PASSWORD_BCRYPT` (ou
`PASSWORD_DEFAULT`). Nunca hash customizado, nunca reversível, nunca MD5/SHA1.

### 2.2 Sessão nativa do CodeIgniter 4

O CodeIgniter 4 traz um sistema de sessão completo (configurável para file,
database ou cache). Esta spec usa o driver padrão (`CodeIgniter\Session\Handlers\FileHandler`)
já pré-configurado. A sessão armazena `user_id`, `email` e `role` após login
bem-sucedido.

### 2.3 Dois papéis, regra clara

| Papel | Pode criar entidades | Pode criar relações | Pode confirmar hipótese → fato |
| ------- | :---: | :---: | :---: |
| `colaborador` | Sim | Sim | **Não** |
| `coordenador` | Sim | Sim | **Sim** |

A regra de confirmação é implementada na camada de aplicação
(controller/service), não no banco. O banco já garante (Spec 1) que
`confirmed` exige `validated_by` preenchido — quem tem permissão para
preencher é regra de negócio.

### 2.4 Desativação, não exclusão

A coluna `active` (BOOLEAN, default `true`) permite desativar um usuário
sem apagar seu histórico de autoria. Usuários inativos não podem fazer
login, mas seus IDs permanecem nas FKs `created_by`/`validated_by`.

### 2.5 Sem autocadastro público

Contas são criadas manualmente pelo coordenador ou via seed. Não existe
formulário público de registro. Justificativa: o projeto é uma ferramenta
de pesquisa restrita a um grupo conhecido (coordenador + alunos), não uma
plataforma aberta.

---

## 3. Schema PostgreSQL

### 3.1 Tabela: `users`

```sql
CREATE TABLE users (
    id              SERIAL PRIMARY KEY,
    name            TEXT NOT NULL,
    email           TEXT NOT NULL UNIQUE,
    password_hash   TEXT NOT NULL,
    role            TEXT NOT NULL DEFAULT 'colaborador'
                    CHECK (role IN ('coordenador', 'colaborador')),
    active          BOOLEAN NOT NULL DEFAULT true,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_users_email  ON users (email);
CREATE INDEX idx_users_role   ON users (role);
CREATE INDEX idx_users_active ON users (active);
```

### 3.2 Alteração: `entities.created_by` e `entities.validated_by`

```sql
-- Passo 1: remover a constraint confirmada_requires_validation
-- (ela referencia validated_by; precisamos recriá-la após a conversão)
ALTER TABLE entities DROP CONSTRAINT IF EXISTS confirmed_requires_validation;

-- Passo 2: converter colunas de TEXT para INTEGER
-- Como não há dados de produção, usamos USING com safe cast
ALTER TABLE entities
    ALTER COLUMN created_by   TYPE INTEGER USING (created_by::INTEGER),
    ALTER COLUMN validated_by TYPE INTEGER USING (validated_by::INTEGER);

-- Passo 3: adicionar FKs
ALTER TABLE entities
    ADD CONSTRAINT fk_entities_created_by
        FOREIGN KEY (created_by) REFERENCES users(id),
    ADD CONSTRAINT fk_entities_validated_by
        FOREIGN KEY (validated_by) REFERENCES users(id);

-- Passo 4: recriar a constraint do Princípio III
ALTER TABLE entities
    ADD CONSTRAINT confirmed_requires_validation
        CHECK (status <> 'confirmed' OR validated_by IS NOT NULL);
```

### 3.3 Alteração: `relationships.created_by` e `relationships.validated_by`

```sql
ALTER TABLE relationships DROP CONSTRAINT IF EXISTS confirmed_requires_validation;

ALTER TABLE relationships
    ALTER COLUMN created_by   TYPE INTEGER USING (created_by::INTEGER),
    ALTER COLUMN validated_by TYPE INTEGER USING (validated_by::INTEGER);

ALTER TABLE relationships
    ADD CONSTRAINT fk_relationships_created_by
        FOREIGN KEY (created_by) REFERENCES users(id),
    ADD CONSTRAINT fk_relationships_validated_by
        FOREIGN KEY (validated_by) REFERENCES users(id);

ALTER TABLE relationships
    ADD CONSTRAINT confirmed_requires_validation
        CHECK (status <> 'confirmed' OR validated_by IS NOT NULL);
```

**Tratamento de dados existentes**: antes de executar esta migration,
limpar registros de teste do banco (dados descartáveis de validação das
Specs 1, não dados de pesquisa real):

```sql
DELETE FROM relationships;
DELETE FROM entities;
```

Após a limpeza, as colunas `created_by` e `validated_by` estarão NULL
em todos os registros restantes (nenhum). A conversão `USING
(created_by::INTEGER)` é segura porque NULL::INTEGER = NULL. Não há
necessidade de criar um usuário "sistema" fictício para preservar
dados que não são de produção.

### 3.4 Trigger `update_timestamp` para `users`

```sql
CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_timestamp();
```

---

## 4. Mapeamento CodeIgniter 4

### 4.1 Model: `UserModel`

| Método | Descrição |
| -------- | ----------- |
| `findByEmail(string $email)` | Busca usuário por email |
| `verifyPassword(string $password, string $hash)` | `password_verify()` |
| `isActive(int $id)` | Verifica se `active = true` |
| `isCoordenador(int $id)` | Verifica se `role = 'coordenador'` |

### 4.2 Controller: `AuthController`

| Rota | Método | Descrição |
| ------ | -------- | ----------- |
| `GET /login` | `loginForm()` | Exibe formulário de login |
| `POST /login` | `loginAction()` | Processa credenciais, inicia sessão |
| `GET /logout` | `logout()` | Destrói sessão, redireciona para login |

### 4.3 Filter: `AuthFilter`

Filtro de autenticação registrado em `app/Config/Filters.php` na categoria
`$aliases` como `'auth'`. Aplicável via `$filters['auth']` nas rotas
protegidas.

Lógica:

- Se `session('user_id')` existe e o usuário está ativo → prossegue
- Caso contrário → redireciona para `/login` com mensagem flash

### 4.4 Serviço: `AuthService` (ou lógica no controller)

Centraliza a lógica de autenticação para evitar duplicação entre
controller e filter:

| Método | Descrição |
| -------- | ----------- |
| `login(string $email, string $password): bool` | Valida credenciais e popula sessão |
| `logout(): void` | Destrói sessão |
| `currentUser(): ?array` | Retorna dados do usuário logado ou null |
| `isCoordenador(): bool` | Atalho para verificação de role |
| `canConfirm(): bool` | `isCoordenador()` — pode ser estendido no futuro |

### 4.5 Middleware de autorização para confirmação

Além do `AuthFilter` (que só verifica se está logado), um método
`requireCoordenador()` (invocado no controller de alteração de status)
verifica `role === 'coordenador'` e retorna 403 se não for.

---

## 5. Migrations

| # | Migration | Conteúdo |
|---|-----------|----------|
| 1 | `CreateUsersTable` | Tabela `users`, índices, trigger `updated_at` |
| 2 | `AlterCreatedByValidatedByToFk` | Converte TEXT → INTEGER FK em `entities` e `relationships`, recria constraints |

---

## 6. Seeds

### UserSeeder

Cria usuários iniciais com senhas aleatórias geradas em tempo de execução
(exibidas uma única vez no console, nunca fixadas como string literal no
código-fonte):

```php
// Gerar senhas aleatórias (exibidas apenas durante a execução do seed)
$coordPassword = bin2hex(random_bytes(8));
$collabPassword = bin2hex(random_bytes(8));

echo "\n=== Credenciais geradas (anote antes de fechar o terminal) ===\n";
echo "Coordenador: eliane@exemplo.com / {$coordPassword}\n";
echo "Colaborador: aluno@exemplo.com / {$collabPassword}\n";
echo "============================================================\n\n";

// Coordenador (pode confirmar hipóteses)
$userModel->insert([
    'name'          => 'Eliane Furoni',
    'email'         => 'eliane@exemplo.com',
    'password_hash' => password_hash($coordPassword, PASSWORD_DEFAULT),
    'role'          => 'coordenador',
]);

// Colaborador de exemplo
$userModel->insert([
    'name'          => 'Aluno Pesquisador',
    'email'         => 'aluno@exemplo.com',
    'password_hash' => password_hash($collabPassword, PASSWORD_DEFAULT),
    'role'          => 'colaborador',
]);
```

---

## 7. Critérios de aceite

| # | Critério | Validação |
| --- | ---------- | ----------- |
| 1 | `php spark migrate` sobe sem erro (tabela users + alterações FK) | Rodar migrate em DB limpo |
| 2 | Login com email/senha válidos cria sessão com `user_id`, `email`, `role` | Fazer POST /login com credenciais corretas |
| 3 | Login com senha inválida rejeita com mensagem genérica (sem revelar se email existe) | POST /login com senha errada → "Email ou senha inválidos" |
| 4 | Login com email inexistente rejeita com a mesma mensagem genérica | POST /login com email que não existe → mesma mensagem |
| 5 | Logout destrói a sessão | GET /logout → sessão limpa, redireciona para /login |
| 6 | Rota protegida sem sessão redireciona para /login | Acessar rota com filtro `auth` sem login |
| 7 | Rota protegida com sessão ativa acessa normalmente | Acessar mesma rota após login |
| 8 | Usuário inativo (active=false) não consegue logar | Desativar usuário, tentar login → rejeitado |
| 9 | Colaborador logado NÃO consegue acessar rota de confirmação de hipótese | POST para endpoint de confirmar → 403 |
| 10 | Coordenador logado CONSEGUE acessar rota de confirmação | Mesmo POST como coordenador → 200 |
| 11 | `created_by`/`validated_by` aceitam apenas INTEGER com FK para users | Tentar inserir value não-inteiro ou ID inexistente → rejeitado |
| 12 | Migration de alteração de colunas roda sem erro em `entities` e `relationships` vazias (pós-limpeza de dados de teste) | Rodar migration após `DELETE FROM relationships; DELETE FROM entities;` → não falha |

---

## 8. Fora de escopo (adiado para specs futuras)

- Autocadastro público de usuários (contas criadas manualmente pelo
  coordenador ou via seed)
- Recuperação de senha por e-mail (requer configuração de SMTP)
- Múltiplos projetos de pesquisa / multi-tenancy
- Permissões granulares por tipo de entidade
- Autenticação via OAuth/provedores externos
- Tela de gerenciamento de usuários (CRUD visual) — apenas seeds e
  comandos manuais por enquanto

---

## 9. Verificação de conformidade constitucional

| Princípio | Conformidade |
| ----------- | ------------- |
| I — Rastreabilidade | Não diretamente afetado; `created_by`/`validated_by` agora são FKs reais, reforçando a rastreabilidade de autoria |
| II — Fato vs Hipótese | A constraint `confirmed_requires_validation` é preservada durante a migração; a separação estrutural não é alterada |
| III — Revisão humana | A regra "só coordenador confirma" implementa a revisão humana na prática; `validated_by` FK garante que há um humano identificável por trás de cada confirmação |
| IV — Simplicidade | 2 migrations, 1 model, 1 controller, 1 filter, 1 service — granularidade mantida |
| V — Stack fixa | PostgreSQL, PHP nativo `password_hash()`, sessão CodeIgniter, Bootstrap (tela de login) |
| VI — Governança | Esta spec precede qualquer plano ou implementação |

Nenhum princípio é violado. A spec está pronta para seguir para
`/speckit.plan`.
