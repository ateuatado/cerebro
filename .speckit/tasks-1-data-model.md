# Tasks 1 — Modelo de dados fundacional do grafo

**Status**: pronto para execução  
**Data**: 2026-07-27  
**Spec**: `.speckit/spec-1-data-model.md`  
**Plan**: `.speckit/plan-1-data-model.md`

---

## Ordem de execução

```
T1 → T2 → T3 → T4 → T5 → T6 → T7
```

Cada tarefa = 1 commit. Executar na ordem listada; T3 e T4 podem ser
paralelizados se houver mais de um executor, mas T6 exige ambos concluídos.

---

## T1 — Ajustar Database.php para PostgreSQL

**Arquivo**: `app/Config/Database.php`

**Ação**: alterar o driver da conexão `default` de `MySQLi` para `Postgre`,
ajustando charset, collation e porta.

**Detalhe**:

```php
'DBDriver' => 'Postgre',
'charset'  => 'utf8',
'DBCollat' => '',
'port'     => 5432,
```

**Validação**: `php spark db:connect` (ou comando equivalente) não reporta
erro de conexão.

**Mensagem de commit**:

```
T1: ajustar Database.php para driver PostgreSQL
```

**Dependências**: nenhuma.

---

## T2 — Migration: CreateEntitiesTable

**Arquivo**: `app/Database/Migrations/2026-07-27-000001_CreateEntitiesTable.php`

**Ação**: criar migration com:

- ENUM `entity_type` e `entity_status`
- Tabela `entities` (colunas, constraints, índices — seções 3.3 da Spec)
- 6 views: `entity_persons`, `entity_locations`, `entity_events`,
  `entity_documents`, `confirmed_entities`, `hypothesis_entities`
- Função `update_timestamp()` + trigger `trg_entities_updated_at`

**Constraints obrigatórias**:

- `confirmed_requires_validation`: `CHECK (status <> 'confirmed' OR validated_by IS NOT NULL)`
- `status` default = `'hypothesis'`
- Índice GIN em `attributes`

**Rollback (`down`)**: desfaz na ordem: triggers → views → tabela → ENUMs.

**Validação**:

```bash
php spark migrate                    # sobe sem erro
php spark migrate:rollback           # desce sem erro
php spark migrate                    # sobe novamente
```

**Mensagem de commit**:

```
T2: migration CreateEntitiesTable — ENUMs, tabela, views, trigger
```

**Dependências**: T1.

---

## T3 — Migration: CreateRelationshipsTable

**Arquivo**: `app/Database/Migrations/2026-07-27-000002_CreateRelationshipsTable.php`

**Ação**: criar migration com:

- Tabela `relationships` (seção 3.6 da Spec)
- 2 views: `confirmed_relationships`, `hypothesis_relationships`
- Trigger `trg_relationships_updated_at`
- Função `check_source_is_document()` + trigger
  `trg_relationships_source_is_document`

**Constraints obrigatórias**:

- `source_document_id` NOT NULL + FK → `entities(id)`
- `source_reference` JSONB NOT NULL DEFAULT `'{}'`
- `different_entities`: `CHECK (source_entity_id <> target_entity_id)`
- `confirmed_requires_validation`
- `direction` CHECK (`'directed'` ou `'symmetric'`)
- `confidence` CHECK (`>= 0.0 AND <= 1.0`)

**Rollback (`down`)**: triggers → views → função → tabela.

**Validação**:

```bash
php spark migrate                    # sobe sem erro
```

**Mensagem de commit**:

```
T3: migration CreateRelationshipsTable — tabela, views, trigger source_is_document
```

**Dependências**: T2.

---

## T4 — Model: EntityModel

**Arquivo**: `app/Models/EntityModel.php`

**Ação**: criar model base para entidades:

- Extende `CodeIgniter\Model`
- `$table = 'entities'`
- `$allowedFields`: `type`, `name`, `attributes`, `status`, `created_by`,
  `validated_by`
- `$useTimestamps = true`
- `$returnType = 'array'`

**Métodos**:

| Método | Comportamento |
| -------- | -------------- |
| `findByType(string $type)` | `WHERE type = ?` |
| `findConfirmed()` | Query na view `confirmed_entities` |
| `findHypothesis()` | Query na view `hypothesis_entities` |
| `findAllRaw()` | SELECT direto em `entities` sem filtro de status |

**Validação**: instanciar o model via `php spark tinker` (se disponível) ou
verificar que `$model->table` retorna `'entities'`.

**Mensagem de commit**:

```
T4: EntityModel — CRUD base com findConfirmed/findHypothesis/findAllRaw
```

**Dependências**: T2.

---

## T5 — Models especializados: Person, Location, Event, Document

**Arquivos**:

- `app/Models/PersonModel.php`
- `app/Models/LocationModel.php`
- `app/Models/EventModel.php`
- `app/Models/DocumentModel.php`

**Ação**: cada model:

- Estende `EntityModel`
- No construtor, redefine `$table` para a view do tipo:
  - `PersonModel` → `entity_persons`
  - `LocationModel` → `entity_locations`
  - `EventModel` → `entity_events`
  - `DocumentModel` → `entity_documents`
- Sobrescreve `insert($data)`: injeta `$data['type'] = 'person'` (etc.)
  antes de `parent::insert($data)`

**Regra**: zero duplicação de lógica. Cada model tem ~15 linhas.

**Validação**:

```php
$pm = new PersonModel();
$pm->findAll();        // retorna apenas type='person'
$pm->insert(['name' => 'Teste', 'validated_by' => 'seed']);
                       // funciona sem passar 'type' explicitamente
```

**Mensagem de commit**:

```
T5: PersonModel, LocationModel, EventModel, DocumentModel — especializações de EntityModel
```

**Dependências**: T4.

---

## T6 — Model: RelationshipModel

**Arquivo**: `app/Models/RelationshipModel.php`

**Ação**: model de arestas:

- Extende `CodeIgniter\Model`
- `$table = 'relationships'`
- `$allowedFields`: `source_entity_id`, `target_entity_id`,
  `relationship_type`, `direction`, `confidence`, `source_document_id`,
  `source_reference`, `status`, `created_by`, `validated_by`
- `$useTimestamps = true`
- `$returnType = 'array'`

**Métodos**:

| Método | Comportamento |
| -------- | -------------- |
| `findConfirmed()` | View `confirmed_relationships` |
| `findHypothesis()` | View `hypothesis_relationships` |
| `findAllRaw()` | SELECT direto em `relationships` |
| `findBySource(int $id)` | `WHERE source_entity_id = ?` |
| `findByTarget(int $id)` | `WHERE target_entity_id = ?` |
| `findByDocument(int $id)` | `WHERE source_document_id = ?` |

**Validação**: criar 2 entidades via EntityModel, inserir relação com
RelationshipModel, recuperar com `findBySource()`.

**Mensagem de commit**:

```
T6: RelationshipModel — CRUD de arestas com findConfirmed/findHypothesis
```

**Dependências**: T3 + T4.

---

## T7 — Seed de validação dos critérios de aceite

**Arquivo**: `app/Database/Seeds/AcceptanceCriteriaSeeder.php`

**Ação**: seed que exercita todos os critérios de aceite (seção 6 da Spec).
Usa os models de T5 e T6.

**Casos de teste no seed**:

| # | Caso | Critério |
| --- | ------ | ---------- |
| 1 | Criar 4 entidades (pessoa, local, evento, documento) | #2 |
| 2 | Consultar `entity_persons` via PersonModel e confirmar que só retorna registros com `type='person'` — nenhum local, evento ou documento aparece | #5 |
| 3 | Criar relação pessoa→documento com source_reference JSONB | #3, #8 |
| 4 | Tentar relação sem `source_document_id` → capturar exceção | #4 |
| 5 | Tentar entidade `confirmed` sem `validated_by` → capturar exceção | #7 |
| 6 | Criar hipótese e verificar ausência em `findConfirmed()` | #6 |
| 7 | Tentar `source_document_id` apontando para pessoa → trigger rejeita | critério adicional (I) |
| 8 | Inserir atributos heterogêneos por tipo (JSONB) | #3 |
| 9 | **Manual**: `git diff --stat master..HEAD -- 'app/Controllers/' 'app/Views/' 'app/Config/Routes.php'` | #9 |

**Estrutura**: cada caso é um método privado. `run()` chama todos em
sequência. try/catch documenta comportamento esperado em casos de rejeição.

**Execução**:

```bash
php spark db:seed AcceptanceCriteriaSeeder
```

**Validação final (manual)**:

```bash
# Confirmar que nenhum controller/view/rota foi criado:
git diff --stat master..HEAD -- 'app/Controllers/' 'app/Views/' 'app/Config/Routes.php'
# Deve produzir saída vazia
```

**Mensagem de commit**:

```
T7: AcceptanceCriteriaSeeder — valida 9 critérios de aceite da Spec 1
```

**Dependências**: T5 + T6.

---

## Resumo de commits (7)

```
T1: ajustar Database.php para driver PostgreSQL
T2: migration CreateEntitiesTable — ENUMs, tabela, views, trigger
T3: migration CreateRelationshipsTable — tabela, views, trigger source_is_document
T4: EntityModel — CRUD base com findConfirmed/findHypothesis/findAllRaw
T5: PersonModel, LocationModel, EventModel, DocumentModel — especializações de EntityModel
T6: RelationshipModel — CRUD de arestas com findConfirmed/findHypothesis
T7: AcceptanceCriteriaSeeder — valida 9 critérios de aceite da Spec 1
```
