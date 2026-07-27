# Spec 1 — Modelo de dados fundacional do grafo

**Status**: especificado  
**Data**: 2026-07-27  
**Dependências**: Constituição (`.speckit/constitution.md`), AGENTS.md  
**Princípios aplicáveis**: I (rastreabilidade), II (fato vs hipótese), V (stack
fixa), VI (governança)

---

## 1. Visão geral

Esta spec define o schema PostgreSQL que serve de alicerce para todo o grafo
de conhecimento do projeto Cerebro. O escopo é estritamente **migrations +
models** — sem controllers, rotas, views ou lógica de extração.

O design resolve três exigências centrais da constituição:

1. **Toda relação referencia obrigatoriamente uma fonte documental**
   (Princípio I)
2. **Fato e hipótese são estruturalmente distintos, não apenas uma flag
   opcional** (Princípio II)
3. **O grafo é relacional, usando PostgreSQL nativo com JSONB + futuras
   CTEs recursivas** (Princípio V)

---

## 2. Decisões de design arquitetural

### 2.1 Tabela única de entidades + views por tipo

AGENTS.md prescreve "tabelas próprias" para cada tipo de entidade; esta spec
prescreve "estrutura comum compartilhada". A reconciliação adotada é:

- **Uma tabela base `entities`** com toda a estrutura comum (id, tipo,
  nome, atributos JSONB, status, timestamps)
- **Uma view por tipo de entidade**: `entity_persons`, `entity_locations`,
  `entity_events`, `entity_documents` — cada view expõe apenas registros do
  tipo correspondente, funcionando como a "tabela própria" que AGENTS.md
  requisita
- **Nenhuma FK genérica da tabela `relationships` para "qualquer entidade"
  usando polimorfismo frágil** — as FKs apontam diretamente para
  `entities.id`, e as views de tipo garantem integridade semântica

Isto evita duplicação de schema (4 tabelas idênticas) e mantém a capacidade
de fazer travessia de grafo com CTE recursiva sobre uma única tabela de
entidades.

### 2.2 Separação estrutural fato vs hipótese

Conforme Princípio II, a distinção não pode ser uma coluna booleana
opcional que dependa da disciplina da aplicação para ser respeitada.

Solução adotada:

- Coluna `status` NOT NULL com CHECK constraint: `status IN ('confirmed',
  'hypothesis')`
- **Views `confirmed_entities` e `hypothesis_entities`** como interface
  primária de consulta — quem quiser fatos confirmados consulta uma view;
  quem quiser hipóteses consulta outra
- O mesmo padrão se repete em `relationships` → views
  `confirmed_relationships` e `hypothesis_relationships`
- A tabela base (`entities`, `relationships`) existe para operações de
  escrita e para travessias de grafo que precisem ver ambos os status —
  mas o "caminho fácil" (views) sempre exige escolha consciente entre fato
  e hipótese

Isto satisfaz o requisito constitucional: **um registro em hipótese nunca
é consultado pelo mesmo caminho que um fato confirmado sem que essa
distinção seja explícita na própria consulta**. Usar a view
`confirmed_entities` é uma escolha explícita; fazer `SELECT * FROM entities`
sem filtrar por status também é uma escolha explícita (e legítima para
travessia de grafo).

### 2.3 JSONB para atributos variáveis

Cada tipo de entidade tem campos específicos que não justificam colunas
fixas (profissão de uma pessoa, tipo de instituição de um local, data de um
evento). Esses atributos vão em `attributes JSONB`, com índices GIN para
consultas futuras.

---

## 3. Schema PostgreSQL

### 3.1 Enum auxiliar: `entity_type`

```sql
CREATE TYPE entity_type AS ENUM (
    'person',
    'location',
    'event',
    'document'
);
```

### 3.2 Enum auxiliar: `entity_status`

```sql
CREATE TYPE entity_status AS ENUM (
    'confirmed',
    'hypothesis'
);
```

### 3.3 Tabela: `entities`

```sql
CREATE TABLE entities (
    id              SERIAL PRIMARY KEY,
    type            entity_type NOT NULL,
    name            TEXT NOT NULL,
    attributes      JSONB NOT NULL DEFAULT '{}',
    status          entity_status NOT NULL DEFAULT 'hypothesis',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by      TEXT,   -- FK futura para users
    validated_by    TEXT,   -- FK futura para users; NULL = não validado
    CONSTRAINT confirmed_requires_validation
        CHECK (status <> 'confirmed' OR validated_by IS NOT NULL)
);

-- Índices
CREATE INDEX idx_entities_type          ON entities (type);
CREATE INDEX idx_entities_status        ON entities (status);
CREATE INDEX idx_entities_type_status   ON entities (type, status);
CREATE INDEX idx_entities_attributes    ON entities USING GIN (attributes);
```

**Regra**: um registro nasce com `status = 'hypothesis'`. A transição para
`confirmed` é feita explicitamente por revisão humana (fora do escopo desta
spec, mas o schema impõe o default seguro).

### 3.4 Views de entidade por tipo

```sql
CREATE VIEW entity_persons AS
    SELECT * FROM entities WHERE type = 'person';

CREATE VIEW entity_locations AS
    SELECT * FROM entities WHERE type = 'location';

CREATE VIEW entity_events AS
    SELECT * FROM entities WHERE type = 'event';

CREATE VIEW entity_documents AS
    SELECT * FROM entities WHERE type = 'document';
```

### 3.5 Views de entidade por status

```sql
CREATE VIEW confirmed_entities AS
    SELECT * FROM entities WHERE status = 'confirmed';

CREATE VIEW hypothesis_entities AS
    SELECT * FROM entities WHERE status = 'hypothesis';
```

### 3.6 Tabela: `relationships`

```sql
CREATE TABLE relationships (
    id                  SERIAL PRIMARY KEY,
    source_entity_id    INTEGER NOT NULL REFERENCES entities(id),
    target_entity_id    INTEGER NOT NULL REFERENCES entities(id),
    relationship_type   TEXT NOT NULL,
    direction           TEXT NOT NULL DEFAULT 'directed'
                        CHECK (direction IN ('directed', 'symmetric')),
    confidence          REAL NOT NULL DEFAULT 0.0
                        CHECK (confidence >= 0.0 AND confidence <= 1.0),
    source_document_id  INTEGER NOT NULL REFERENCES entities(id),
    source_reference    JSONB NOT NULL DEFAULT '{}',  -- {"description": "fl. 47, §3"}; extensível para coordenadas visuais futuras
    status              entity_status NOT NULL DEFAULT 'hypothesis',
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by          TEXT,
    validated_by        TEXT,
    CONSTRAINT different_entities CHECK (source_entity_id <> target_entity_id),
    CONSTRAINT confirmed_requires_validation
        CHECK (status <> 'confirmed' OR validated_by IS NOT NULL)
);

-- Índices
CREATE INDEX idx_rel_source          ON relationships (source_entity_id);
CREATE INDEX idx_rel_target          ON relationships (target_entity_id);
CREATE INDEX idx_rel_type            ON relationships (relationship_type);
CREATE INDEX idx_rel_status          ON relationships (status);
CREATE INDEX idx_rel_document        ON relationships (source_document_id);
CREATE INDEX idx_rel_source_target   ON relationships (source_entity_id, target_entity_id);
```

**Regras**:

- `source_document_id` é NOT NULL — implementa o Princípio I (toda relação
  tem fonte primária). Reforçado pelo trigger `trg_relationships_source_is_document`
  (seção 3.8), que rejeita qualquer valor que não referencie uma entidade do tipo
  `document`
- `confidence` é um REAL entre 0.0 e 1.0; hipóteses tipicamente têm
  confiança ≤ 0.5, fatos confirmados próximos de 1.0, mas o schema não
  impõe esta correlação (é responsabilidade da camada de aplicação)
- A restrição `different_entities` impede laços próprios (auto-relação)

### 3.7 Views de relacionamento por status

```sql
CREATE VIEW confirmed_relationships AS
    SELECT * FROM relationships WHERE status = 'confirmed';

CREATE VIEW hypothesis_relationships AS
    SELECT * FROM relationships WHERE status = 'hypothesis';
```

### 3.8 Trigger de `updated_at`

```sql
CREATE OR REPLACE FUNCTION update_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_entities_updated_at
    BEFORE UPDATE ON entities
    FOR EACH ROW EXECUTE FUNCTION update_timestamp();

CREATE TRIGGER trg_relationships_updated_at
    BEFORE UPDATE ON relationships
    FOR EACH ROW EXECUTE FUNCTION update_timestamp();

CREATE OR REPLACE FUNCTION check_source_is_document()
RETURNS TRIGGER AS $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM entities
        WHERE id = NEW.source_document_id AND type = 'document'
    ) THEN
        RAISE EXCEPTION
            'source_document_id=% referencia entity id=% que não é do tipo document',
            NEW.id, NEW.source_document_id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_relationships_source_is_document
    BEFORE INSERT OR UPDATE ON relationships
    FOR EACH ROW EXECUTE FUNCTION check_source_is_document();
```

### 3.9 Diagrama resumido

```
┌──────────────────────────────────────────┐
│                 entities                  │
│  id (PK)    type (ENUM)    name          │
│  attributes (JSONB)   status (ENUM)      │
│  created_at   updated_at                 │
│  created_by   validated_by               │
└──────┬──────────────┬────────────────────┘
       │              │
       ▼              ▼
┌──────────┐   ┌──────────────┐
│  Views   │   │relationships │
│  por tipo│   │              │
│  + status│   │ source (FK)  │
└──────────┘   │ target (FK)  │
               │ type  dir    │
               │ confidence   │
               │ source_doc   │───→ entities(id) [NOT NULL]
               │ reference    │
               │ status       │
               └──────────────┘
```

---

## 4. Mapeamento CodeIgniter 4 → PostgreSQL

### 4.1 Models

| Model                | Tabela/View             | Descrição                              |
|----------------------|-------------------------|----------------------------------------|
| `EntityModel`        | `entities`              | CRUD base para qualquer entidade       |
| `PersonModel`        | `entity_persons`        | Opera sobre a view de pessoas          |
| `LocationModel`      | `entity_locations`      | Opera sobre a view de locais           |
| `EventModel`         | `entity_events`         | Opera sobre a view de eventos          |
| `DocumentModel`      | `entity_documents`      | Opera sobre a view de documentos       |
| `RelationshipModel`  | `relationships`         | CRUD de arestas do grafo               |

Os models de tipo (`PersonModel`, etc.) são especializações de `EntityModel`
que automaticamente injetam `type` nas queries. Eles **não duplicam lógica**,
apenas fixam o filtro de tipo.

### 4.2 Estratégia de status nos models

- `EntityModel` por padrão opera sobre a tabela `entities` (acesso bruto)
- Métodos `findConfirmed()` e `findHypothesis()` redirecionam para as views
  `confirmed_entities` e `hypothesis_entities`
- `RelationshipModel` segue o mesmo padrão: `findConfirmed()` →
  `confirmed_relationships`, `findHypothesis()` →
  `hypothesis_relationships`
- O desenvolvedor é forçado a escolher um método — não existe `find()`
  genérico que retorne ambos sem filtro de status. Se quiser ambos, usa
  `findAll()` explicitamente (nome que deixa claro que não há filtro)

### 4.3 Migrations

Duas migrations bastam:

| # | Migration                | Conteúdo                                        |
|---|--------------------------|-------------------------------------------------|
| 1 | `CreateEntitiesTable`    | Cria ENUMs, tabela `entities`, views, triggers  |
| 2 | `CreateRelationshipsTable` | Cria `relationships`, views, triggers         |

---

## 5. Seeds de teste (critérios de aceite)

Para validar a spec, um seed deve demonstrar o cenário mínimo:

```php
// 1. Criar um documento (fonte primária)
$doc = $documentModel->insert([
    'type'       => 'document',
    'name'       => 'Processo Judicial n. 487/1929',
    'attributes' => json_encode([
        'tipo_documento' => 'processo_judicial',
        'arquivo'        => 'processo_487_1929.pdf'
    ]),
    'status'     => 'confirmed'
]);

// 2. Criar uma pessoa
$person = $personModel->insert([
    'type'       => 'person',
    'name'       => 'João da Silva',
    'attributes' => json_encode(['profissao' => 'operário', 'apelido' => 'João Ferreiro']),
    'status'     => 'confirmed'
]);

// 3. Criar relação com referência à fonte
$rel = $relationshipModel->insert([
    'source_entity_id'   => $person,
    'target_entity_id'   => $doc,
    'relationship_type'  => 'mencionado_em',
    'direction'          => 'directed',
    'confidence'         => 1.0,
    'source_document_id' => $doc,
    'source_reference'   => '{"description": "fl. 47, §3"}',
    'status'             => 'confirmed'
]);

// 4. Recuperar a cadeia completa
//    Pessoa → Relação (com ref. de fonte) → Documento
```

---

## 6. Critérios de aceite (verificáveis)

| # | Critério                                              | Validação                                  |
|---|-------------------------------------------------------|--------------------------------------------|
| 1 | `php spark migrate` executa sem erro em PostgreSQL   | Rodar em DB limpo                          |
| 2 | `entities` aceita inserção dos 4 tipos               | Seed com pessoa, local, evento, documento   |
| 3 | `attributes` JSONB aceita campos heterogêneos por tipo| Inserir atributos distintos por tipo       |
| 4 | `relationships` exige `source_document_id` NOT NULL  | Tentar inserir sem: deve falhar            |
| 5 | Views `entity_persons` etc. retornam apenas seu tipo | Query deve ter `type` = 'person'           |
| 6 | Views `confirmed_entities` exclui hipóteses           | `SELECT * FROM confirmed_entities` sem status='hypothesis' |
| 7 | `status` default = 'hypothesis'                       | Insert sem status: nasce hypothesis        |
| 8 | Cadeia completa recuperável                           | Pessoa → Relação → Documento com ref. fonte |
| 9 | Nenhum controller, view ou rota criado                | Verificar diff                             |

---

## 7. Fora de escopo (adiado para specs futuras)

- CRUD controllers e rotas REST
- Interface de usuário (Bootstrap, visualização de grafo)
- Extração de entidades via IA/DeepSeek
- OCR de documentos
- Autenticação e sistema de usuários (colunas `created_by`/`validated_by`
  já existem como preparação, permanecem TEXT NULL)
- Consultas de travessia com CTE recursiva (o schema as viabiliza, mas não
  as implementa)
- Upload e armazenamento de arquivos (campo `arquivo` no JSONB é só
  referência textual)
- Busca e navegação visual de imagens/recortes por galeria — mencionado
  pelo usuário como lição aprendida de uso anterior do Miro; o schema atual
  já viabiliza via `attributes` JSONB indexado (GIN) e relacionamentos
  estruturados, mas a interface de galeria/busca visual em si é
  responsabilidade de uma spec de UI futura

---

## 8. Verificação de conformidade constitucional

| Princípio | Conformidade |
| ----------- | ------------- |
| I — Rastreabilidade | `source_document_id` NOT NULL + trigger que exige `type='document'` + `source_reference` |
| II — Fato vs Hipótese | Coluna `status` NOT NULL + views dedicadas `confirmed_*` / `hypothesis_*` |
| III — Revisão humana | Constraint `confirmed_requires_validation` impede fisicamente `status='confirmed'` com `validated_by IS NULL`; o banco rejeita, não depende de disciplina da aplicação |
| IV — Simplicidade | 2 migrations, 6 models finos, sem acoplamento a UI |
| V — Stack fixa | PostgreSQL puro, JSONB, views; sem extensões de grafo externas |
| VI — Governança | Esta spec precede qualquer plano ou implementação |

Nenhum princípio é violado. A spec está pronta para seguir para
`/speckit.plan`.
