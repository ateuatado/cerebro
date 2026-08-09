# Especificação 11 — Vocabulário Controlado de Atributos & Validação Defensiva de Tipos

## Contexto & Objetivos

Durante a implementação da extração de entidades por região (Spec 8), as IAs (DeepSeek e Gemini Vision) extraíam tipos de entidade de vocabulário livre (`"organization"`, `"institution"`, `"publication"`), causando exceções no PostgreSQL ao tentar gravar no ENUM `entity_type`.

A **Spec 11** resolve este problema em 3 frentes:
1. **Vocabulário controlado de atributos em tabela de banco**, substituindo a convenção textual do AGENTS.md por uma fonte de verdade consultável pelo sistema.
2. **Injeção dinâmica do vocabulário nos prompts** do Gemini e DeepSeek, restringindo estritamente os tipos válidos (`person`, `location`, `event`, `document`).
3. **Validação defensiva no controller**: entidades com tipo inválido são ignoradas individualmente com log de aviso, sem crashear a transação inteira.

---

## Princípios não negociáveis (Constituição)

1. **Rastreabilidade à Fonte (Princípio I)**: O vocabulário controlado não altera a rastreabilidade — toda entidade continua vinculada ao `source_document_id` e ao trecho de comprovação.
2. **Revisão Humana Obrigatória (Princípio II)**: Entidades continuam entrando como `hypothesis`. A modal de curadoria permite ao pesquisador corrigir tipo e atributos antes de gravar.
3. **Vocabulário Fechado por Design**: Novas chaves só devem ser adicionadas com atualização coordenada na tabela e no AGENTS.md, evitando dispersão semântica.

---

## Requisitos Funcionais

### RF-1: Tabela `entity_attribute_vocabulary`
- Tabela PostgreSQL com colunas: `id`, `entity_type`, `key_name`, `label`, `description`, `is_active`, `created_at`.
- Constraint UNIQUE em `(entity_type, key_name)`.
- Seed inicial com ~26 chaves distribuídas pelos 4 tipos de entidade.

### RF-2: Model de domínio `EntityAttributeVocabularyModel`
- `getByType(entityType)`: vocabulário ativo filtrado por tipo.
- `getAllGroupedByType()`: vocabulário completo agrupado para API/frontend.
- `getAsPromptList(entityType)`: formatação textual para injeção em prompt.
- `addKey(entityType, keyName, label, desc)`: inserção segura com verificação de duplicata.
- `isValidType(type)`: validação estática contra os 4 tipos do ENUM.

### RF-3: Injeção dinâmica nos prompts de IA
- Método `buildVocabularyPromptSection()` em `DeepSeekService` e `GeminiVisionService`.
- Todos os métodos de extração (`extractKnowledge`, `extractFromCropText`, `extractFromCropImage`, `extractKnowledgeChunked`, `extractKnowledgeFromText`) recebem e utilizam o vocabulário.
- Prompt contém bloco explícito de tipos válidos e proibição de tipos alternativos.

### RF-4: Validação defensiva no controller
- `DocumentReviewController::confirmRegionEntities()` usa `isValidType()` antes de qualquer INSERT.
- Entidade com tipo inválido é ignorada com log de warning e incluída em `skippedEntities` na resposta JSON.

### RF-5: API e frontend de curadoria
- Endpoint `GET api/vocabulario-atributos` expõe vocabulário em JSON.
- Modal de curadoria renderiza dropdowns editáveis (tipo de entidade + chaves de atributos).
- Opção `+ nova chave...` para atributos não previstos no vocabulário.

### RF-6: Pipeline de extração em 2 estágios com vocabulário
- `DocumentExtractionService` carrega vocabulário do banco e o passa para Gemini e DeepSeek.
- Garante consistência tanto no fluxo de extração por região (Spec 8) quanto no fluxo de ingestão em lote (Spec 6).
