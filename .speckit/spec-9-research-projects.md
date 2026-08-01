# Especificação 9 — Gestão de Pesquisas/Assuntos e Filtragem do Grafo Por Tema

## Contexto & Objetivos

No escopo historiográfico do projeto Cerebro, os documentos, fotos e transcrições são coletados em função de temas de investigação específicos (ex: *"Baby de Andrade"*, *"Revolução de 1932"*, *"Levante Constitucionalista"*).

A **Spec 9** introduz o conceito de **Pesquisas / Temas de Investigação** (Research Projects / Topics), permitindo:
1. Cadastrar e gerenciar assuntos/pesquisas no acervo.
2. Vincular documentos primários a uma ou mais pesquisas durante o upload, revisão ou lote.
3. Filtrar o Grafo do Conhecimento por Pesquisa/Assunto, permitindo isolar visualmente apenas a rede de pessoas, locais, eventos e documentos pertencentes àquela linha de investigação.

---

## Princípios não negociáveis (Constituição)

1. **Rastreabilidade à Fonte (Princípio I)**: O vínculo de uma entidade/relação com uma Pesquisa deriva da associação do **documento fonte** (`source_document_id`) com aquela Pesquisa. Não há desconexão entre o fato e sua fonte.
2. **Revisão Humana (Princípio II)**: As pesquisas servem como lentes de visualização e organização, sem alterar a regra de que hipóteses extraídas por IA exigem validação humana.
3. **Preservação do Grafo Global**: A filtragem por pesquisa é dinâmica e não-destrutiva. O grafo unificado continua existindo, permitindo análises cruzadas entre pesquisas quando desejado.

---

## Requisitos Funcionais

### RF-1: Cadastro e Gestão de Pesquisas (CRUD)
- Interface em `/researches` para listar, criar, editar e excluir pesquisas/assuntos.
- Campos da pesquisa:
  - `title` (obrigatório, ex: "Baby de Andrade")
  - `description` (opcional, detalhamento do escopo historiográfico)
  - `created_at`, `updated_at`, `created_by`.

### RF-2: Vinculação de Documentos a Pesquisas
- Ao cadastrar/revisar um documento ou em ingesta em lote, o usuário pode selecionar a qual(is) pesquisa(s) o documento pertence.
- Tabela relacional de vínculo N:N entre pesquisas e documentos (`research_documents`).

### RF-3: Filtragem Dinâmica do Grafo de Conhecimento
- Na página `/graph`, inclusão de um seletor dropdown de Pesquisas:
  - *"Todas as Pesquisas (Grafo Global)"*
  - *"Pesquisa: [Nome da Pesquisa]"*
- Quando uma pesquisa for selecionada:
  - O gráfico interativo (Vis-Network) e a API `/graph/data` filtram as conexões onde `source_document_id` pertence a um documento da pesquisa selecionada.
  - Exibe apenas as entidades (pessoas, locais, eventos, documentos) envolvidas nessas conexões ou vinculadas à pesquisa.

---

## Estrutura de Banco de Dados

### Tabela: `research_projects`
```sql
CREATE TABLE research_projects (
    id          SERIAL PRIMARY KEY,
    title       TEXT NOT NULL,
    description TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by  INTEGER REFERENCES users(id) ON DELETE SET NULL
);
```

### Tabela: `research_documents`
```sql
CREATE TABLE research_documents (
    research_id INTEGER NOT NULL REFERENCES research_projects(id) ON DELETE CASCADE,
    document_id INTEGER NOT NULL REFERENCES entities(id) ON DELETE CASCADE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (research_id, document_id)
);
```
