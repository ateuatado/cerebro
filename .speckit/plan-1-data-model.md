# Plan 1 — Modelo de dados fundacional do grafo

**Status**: planejado  
**Data**: 2026-07-27  
**Spec**: `.speckit/spec-1-data-model.md`  
**Dependências**: Constituição, AGENTS.md, Spec 1

---

## 1. Visão geral do plano

Este plano decompõe a Spec 1 em tarefas implementáveis, ordenadas por
dependência estrita. O escopo é migrations + models + seed de validação
— exatamente o definido na spec, nada além.

Cada tarefa gera **exatamente um commit** (Princípio VI).

---

## 2. Pré-requisito: ambiente PostgreSQL

Antes de qualquer tarefa de implementação, é necessário garantir que:

- PostgreSQL está instalado e rodando
- O banco `cerebro` existe (via `CREATE DATABASE cerebro`)
- As credenciais estão configuradas em `.env` (já existe)
- `app/Config/Database.php` está apontando para `DBDriver = 'Postgre'`

O `.env` atual contém as configurações corretas; a tarefa 1 formaliza o
ajuste do driver no arquivo de config.

---

## 3. Tarefas

### Tarefa 1 — Ajustar Database.php para PostgreSQL

**Arquivo**: `app/Config/Database.php`

**O que fazer**: alterar a conexão `default` de `MySQLi` para `Postgre`,
compatível com os valores já definidos no `.env`.

**Alteração pontual**:

```php
// De:
'DBDriver'     => 'MySQLi',
'charset'      => 'utf8mb4',
'DBCollat'     => 'utf8mb4_general_ci',
'port'         => 3306,

// Para:
'DBDriver'     => 'Postgre',
'charset'      => 'utf8',
'DBCollat'     => 'utf8_general_ci',
'port'         => 5432,
```

**Validação**: `php spark db:connect` (ou equivalente) não reporta erro.

**Dependências**: nenhuma — é a primeira tarefa.

---

### Tarefa 2 — Migration: CreateEntitiesTable

**Arquivo**: `app/Database/Migrations/2026-07-27-000001_CreateEntitiesTable.php`

**O que criar**:

1. ENUM `entity_type` (`person`, `location`, `event`, `document`)
2. ENUM `entity_status` (`confirmed`, `hypothesis`)
3. Tabela `entities` com todas as colunas, constraints e índices da seção 3.3
4. 4 views por tipo: `entity_persons`, `entity_locations`, `entity_events`,
   `entity_documents` (seção 3.4)
5. 2 views por status: `confirmed_entities`, `hypothesis_entities` (seção 3.5)
6. Função + trigger `update_timestamp` para `entities` (seção 3.8)

**Atenção**:

- `confirmed_requires_validation` CHECK deve estar presente
- Default de `status` = `'hypothesis'`
- Índice GIN em `attributes`

**Rollback (`down`)**: DROP triggers, views, tabela, ENUMs (na ordem correta:
dependências primeiro).

**Validação**: `php spark migrate` sobe; `php spark migrate:rollback` desce.

**Dependências**: Tarefa 1 (Database.php configurado).

---

### Tarefa 3 — Migration: CreateRelationshipsTable

**Arquivo**: `app/Database/Migrations/2026-07-27-000002_CreateRelationshipsTable.php`

**O que criar**:

1. Tabela `relationships` conforme seção 3.6 (inclui `different_entities`
   CHECK e `confirmed_requires_validation` CHECK)
2. 2 views por status: `confirmed_relationships`, `hypothesis_relationships`
   (seção 3.7)
3. Função `update_timestamp` + trigger para `relationships` (seção 3.8)
4. Função `check_source_is_document` + trigger
   `trg_relationships_source_is_document` (seção 3.8)

**Atenção**:

- `source_document_id` é NOT NULL
- `source_reference` é JSONB NOT NULL DEFAULT `'{}'`
- FKs referenciam `entities(id)`
- A função `update_timestamp` pode ser reutilizada da migration 1; neste
  caso usar `CREATE OR REPLACE FUNCTION`

**Rollback (`down`)**: DROP triggers, views, função `check_source_is_document`,
tabela (na ordem).

**Validação**: `php spark migrate` sobe sem erro.

**Dependências**: Tarefa 2 (tabela `entities` deve existir para as FKs).

---

### Tarefa 4 — Model: EntityModel

**Arquivo**: `app/Models/EntityModel.php`

**O que implementar**:

- Extender `CodeIgniter\Model`
- `$table = 'entities'`
- `$allowedFields`: todas as colunas exceto `id`, `created_at`, `updated_at`
- `$returnType = 'array'`
- `$useTimestamps = true` (CodeIgniter gerencia `created_at`, `updated_at`)
- Método `findByType(string $type)`: filtra por `type`
- Método `findConfirmed()`: usa `$this->db->table('confirmed_entities')`
- Método `findHypothesis()`: usa `$this->db->table('hypothesis_entities')`
- Método `findAllRaw()`: SELECT direto na tabela `entities` sem filtro de
  status — nome explícito indicando que pula a distinção fato/hipótese

**Validação**: instanciar o model, verificar que `$table` e `$allowedFields`
estão corretos.

**Dependências**: Tarefa 2 (tabela existe).

---

### Tarefa 5 — Models especializados: Person, Location, Event, Document

**Arquivos**:

- `app/Models/PersonModel.php`
- `app/Models/LocationModel.php`
- `app/Models/EventModel.php`
- `app/Models/DocumentModel.php`

**O que implementar**:

- Cada model estende `EntityModel`
- No construtor, redefine `$table` para a view correspondente:
  - `PersonModel` → `entity_persons`
  - `LocationModel` → `entity_locations`
  - `EventModel` → `entity_events`
  - `DocumentModel` → `entity_documents`
- Método `insert($data)`: automaticamente injeta `'type' => 'person'` (etc.)
  no array antes de chamar `parent::insert()`
- **Nenhuma duplicação de lógica** — apenas injeção do filtro de tipo

**Validação**: `$personModel->findAll()` retorna apenas pessoas;
`$personModel->insert()` sem `type` explícito funciona corretamente.

**Dependências**: Tarefa 4 (EntityModel).

---

### Tarefa 6 — Model: RelationshipModel

**Arquivo**: `app/Models/RelationshipModel.php`

**O que implementar**:

- Extender `CodeIgniter\Model`
- `$table = 'relationships'`
- `$allowedFields`: todas as colunas exceto `id`, `created_at`, `updated_at`
- `$returnType = 'array'`
- `$useTimestamps = true`
- Método `findConfirmed()`: redireciona para view `confirmed_relationships`
- Método `findHypothesis()`: redireciona para view `hypothesis_relationships`
- Método `findAllRaw()`: SELECT direto na tabela `relationships`
- Método `findBySource(int $entityId)`: busca relações onde a entidade é
  origem
- Método `findByTarget(int $entityId)`: busca relações onde a entidade é
  destino
- Método `findByDocument(int $documentId)`: busca relações lastreadas por
  um documento específico

**Validação**: inserir relação entre duas entidades e recuperá-la.

**Dependências**: Tarefa 3 (tabela existe) + Tarefa 4 (entidades para
referenciar).

---

### Tarefa 7 — Seed de validação dos critérios de aceite

**Arquivo**: `app/Database/Seeds/AcceptanceCriteriaSeeder.php`

**O que implementar**:

O seed da seção 5 da Spec 1, mais casos adicionais que exercitam todos os
critérios de aceite:

```php
// 1. Criar 4 entidades (uma de cada tipo)
// 2. Criar uma relação entre pessoa e documento
// 3. Tentar criar relação sem source_document_id → deve falhar
// 4. Tentar criar entidade confirmed sem validated_by → deve falhar
// 5. Criar hipótese (status default) e verificar que não aparece
//    em confirmed_entities
// 6. Tentar relação com source_document_id apontando para pessoa → trigger
//    rejeita
// 7. Criar relação com source_reference JSONB
// 8. Verificar via `git diff --stat` que nenhum controller, view ou rota
//    foi criado fora do escopo desta spec
```

**Estrutura**: cada caso é um método privado chamado por `run()`, com
try/catch documentando o comportamento esperado. O passo 8 é executado
manualmente ao final: `git diff --stat master..HEAD -- 'app/Controllers/' 'app/Views/' 'app/Config/Routes.php'`
e deve produzir saída vazia.

**Validação**: `php spark db:seed AcceptanceCriteriaSeeder` executa sem
erro e produz a saída esperada.

**Dependências**: Tarefas 5 e 6 (todos os models disponíveis).

---

## 4. Grafo de dependências

```
T1 (Database.php)
 │
 └─► T2 (Migration: entities + views + triggers)
      │
      ├─► T4 (EntityModel)
      │    │
      │    └─► T5 (Person, Location, Event, Document models)
      │         │
      │         └──────────────────────────┐
      └─► T3 (Migration: relationships      │
           + views + triggers)              │
           │                               │
           └─► T6 (RelationshipModel)       │
                │                          │
                └─► T7 (Seed de validação) ◄┘
```

## 5. Ordem de execução

```
T1 → T2 → T3 → T4 → T5 → T6 → T7
```

T3 pode ser feito em paralelo com T4 se houver mais de um desenvolvedor,
mas T6 exige ambos.

---

## 6. Mapeamento tarefas → critérios de aceite

| Critério | Coberto por |
| ---------- | ------------- |
| 1 — `php spark migrate` sem erro | T2, T3 |
| 2 — Inserção dos 4 tipos | T5 + T7 |
| 3 — `attributes` JSONB heterogêneo | T7 |
| 4 — `source_document_id` NOT NULL | T3 + T7 |
| 5 — Views por tipo retornam só seu tipo | T5 + T7 |
| 6 — Views por status excluem opostos | T4 + T7 |
| 7 — Default status = hypothesis | T2 + T7 |
| 8 — Cadeia completa recuperável | T7 |
| 9 — Sem controllers/views/rotas | T7 (passo 8: `git diff` sobre Controllers/Views/Routes) |

---

## 7. Verificação de conformidade

| Princípio | Como o plano garante |
| ----------- | --------------------- |
| I — Rastreabilidade | T3 (NOT NULL + trigger) + T7 (valida rejeição) |
| II — Fato vs Hipótese | T2 (views), T4 (métodos findConfirmed/findHypothesis), T7 (valida separação) |
| III — Revisão humana | T2 (CHECK constraint), T7 (valida rejeição) |
| IV — Simplicidade | 7 tarefas granulares, 1 commit por tarefa |
| V — Stack fixa | PostgreSQL puro, JSONB, views nativas |
| VI — Governança | Plano segue constitution → specify → plan → tasks |

O plano está pronto para `/speckit.tasks`.
