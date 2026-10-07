# Spec 12 — Feedback de Correções Humanas para Calibração da IA (Human-in-the-Loop)

**Status**: especificado
**Data**: 2026-08-10
**Dependências**: Spec 1 (data model), Spec 6 (two-stage extraction), Spec 7 (workspace), Spec 8 (region extraction), Spec 11 (vocabulary)
**Princípios aplicáveis**: I (rastreabilidade), II (fato vs hipótese), III (revisão humana), V (stack fixa)

---

## 1. Visão geral

Documentos históricos de 110 anos apresentam caligrafia cursiva, fontes
tipográficas irregulares e desgaste físico que frequentemente induzem a IA
a erros de leitura. Exemplo concreto: a IA leu "Benedicto de Andrade"
(diretor d'O Parafuso) como "Bei".

Esta spec introduz um ciclo de **feedback humano → IA**: toda correção feita
por um pesquisador humano é registrada e retroalimentada nos prompts de
extração futura, permitindo que a IA "aprenda" com os erros passados —
análogo a como um paleógrafo humano calibra sua leitura após ver exemplos
de caligrafia de uma mesma fonte.

O mecanismo opera em dois níveis:

1. **Correção por documento-fonte**: correções feitas em entidades extraídas
   de um documento específico são reapresentadas à IA quando ela processa
   NOVAS páginas/regiões do MESMO documento
2. **Correção global**: correções acumuladas de todos os documentos formam
   um "dicionário de erros comuns" que é injetado em todo prompt de extração

---

## 2. Infraestrutura já existente

A tabela `correction_log` (criada na migration `2026-08-10-000001`) registra:

```sql
correction_log (
    id, entity_id, field, old_value, new_value, corrected_by, created_at
)
```

O controller `EntityController::update()` já popula esta tabela automaticamente
quando nome, tipo ou atributos de uma entidade são alterados via modal de edição.

---

## 3. Requisitos Funcionais

### RF-1: Coleta de correções por documento-fonte

Dado um `source_document_id`, o sistema consulta todas as correções feitas
em entidades que foram extraídas daquele documento, agrupando por `field`:

```sql
SELECT c.field, c.old_value, c.new_value, COUNT(*) as vezes_corrigido,
       MAX(c.created_at) as ultima_correcao
FROM correction_log c
JOIN relationships r ON r.target_entity_id = c.entity_id
                      OR r.source_entity_id = c.entity_id
WHERE r.source_document_id = ?
GROUP BY c.field, c.old_value, c.new_value
ORDER BY vezes_corrigido DESC
LIMIT 20
```

### RF-2: Injeção no prompt do DeepSeek (extração de texto)

O `DeepSeekService` deve incluir no prompt do sistema, quando houver
correções para o documento-fonte sendo processado:

```
## Correções de leituras anteriores neste documento (calibração)
As seguintes correções foram feitas por pesquisadores humanos em extrações
anteriores DESTE MESMO documento. Use-as para calibrar sua leitura:

- Nome próprio: "Bei" → "Benedicto de Andrade" (corrigido 1x)
- Local: "S. Paulo" → "São Paulo" (corrigido 2x)
```

### RF-3: Injeção no prompt do Gemini Vision (leitura de imagem)

O `GeminiVisionService` deve receber correções visuais quando disponíveis,
no formato:

```
## Correções de leitura paleográfica (aprendizado)
Ao ler este documento, note que leituras anteriores foram corrigidas:
- O trecho manuscrito "Bei" deve ser lido como "Benedicto de Andrade"
- Abreviatura "S." expande para "São"
```

### RF-4: Dicionário global de erros comuns

Além das correções por documento, um resumo global das 30 correções mais
frequentes (agregadas de todos os documentos) é injetado em todo prompt
de extração, funcionando como "conhecimento prévio" da IA sobre os vícios
de leitura comuns neste acervo.

### RF-5: Endpoint de consulta de correções

`GET /api/correcoes/documento/(:num)` — retorna JSON com correções
associadas a um documento-fonte específico, para uso em tempo real
durante a extração.

---

## 4. Alterações em serviços existentes

| Serviço | Alteração |
|---|---|
| `DeepSeekService` | Novo método `buildCorrectionPrompt(?int $sourceDocumentId): string` |
| `GeminiVisionService` | Novo método `buildCorrectionPrompt(?int $sourceDocumentId): string` |
| `DocumentExtractionService` | Passar `source_document_id` ao construir prompts |

---

## 5. Critérios de aceite

| # | Critério | Validação |
|---|---|---|
| 1 | Após editar "Bei" → "Benedicto de Andrade", `correction_log` contém o registro | SELECT na tabela |
| 2 | `GET /api/correcoes/documento/1` retorna a correção name: Bei → Benedicto de Andrade | Verificar JSON |
| 3 | Prompt do DeepSeek para re-extração do documento #1 contém a correção | Log do prompt |
| 4 | Prompt do Gemini Vision para nova página do documento #1 contém a correção | Log do prompt |
| 5 | Correção NÃO aparece em extrações de documento diferente (#2, se houver) | Isolamento por source_document_id |
| 6 | Dicionário global contém a correção após N acumularem | Prompt de qualquer extração |

---

## 6. Fora de escopo

- Fine-tuning real de modelos (requer API de fine-tuning do DeepSeek/Gemini,
  que pode não estar disponível ou ter custo proibitivo)
- Correção automática sem revisão humana (violaria Princípio III)
- Aprendizado entre documentos de acervos diferentes (escopo atual é um
  único projeto de pesquisa)

---

## 7. Verificação de conformidade constitucional

| Princípio | Conformidade |
|---|---|
| I — Rastreabilidade | Correções são vinculadas a `entity_id` → `source_document_id`; a origem do aprendizado é rastreável |
| II — Fato vs Hipótese | Correções são apenas SUGESTÕES no prompt; a IA ainda gera hipóteses, não fatos |
| III — Revisão humana | O ciclo começa COM a correção humana; a IA nunca auto-corrige sem input humano |
| V — Stack fixa | Prompt engineering no PHP, sem dependências externas novas |
