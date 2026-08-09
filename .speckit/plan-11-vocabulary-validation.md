# Plano de Implementação — Spec 11: Vocabulário Controlado & Validação Defensiva

## Contexto
A IA retornava tipos de entidade fora do ENUM do PostgreSQL (ex: "organization"), crasheando a transação de gravação. A correção envolve vocabulário controlado em banco, injeção nos prompts e validação defensiva.

---

## Componentes Modificados (em ordem de dependência)

### 1. Banco de Dados
#### [NEW] Migration `2026-08-09-000001_CreateEntityAttributeVocabulary.php`
- Cria tabela `entity_attribute_vocabulary` com seed de ~26 chaves controladas.

---

### 2. Model de Domínio
#### [NEW] `EntityAttributeVocabularyModel.php`
- CRUD do vocabulário, validação de tipo, formatação para prompt.

---

### 3. Serviços de IA
#### [MODIFY] `DeepSeekService.php`
- `extractFromCropImage()`: adicionar restrição de tipos + vocabulário.
- `extractFromCropText()`: já atualizado com vocabulário.
- `extractKnowledge()`: já atualizado com vocabulário.
- `extractKnowledgeChunked()`: propagar `$vocabulary` para `extractKnowledge()`.
- `buildVocabularyPromptSection()`: já implementado.

#### [MODIFY] `GeminiVisionService.php`
- `extractFromImage()`: já aceita vocabulário.
- `extractKnowledgeFromText()`: já atualizado com restrição de tipos + vocabulário.
- `buildVocabularyPromptSection()`: já implementado.

#### [MODIFY] `DocumentExtractionService.php`
- Carregar vocabulário do banco e passar para Gemini e DeepSeek no pipeline de 2 estágios.

---

### 4. Controllers
#### [MODIFY] `DocumentReviewController.php`
- `confirmRegionEntities()`: validação defensiva de tipo + log + skippedEntities.
- `extractEntitiesFromRegion()`: injeção de vocabulário na chamada de IA.

#### [MODIFY] `EntityController.php`
- Endpoint `getAttributeVocabulary()` para a API.

---

### 5. Rotas
#### [MODIFY] `Routes.php`
- `GET api/vocabulario-atributos` → `EntityController::getAttributeVocabulary`.

---

### 6. Frontend
#### [MODIFY] `review_workspace.php`
- Modal de curadoria expandida com cards por entidade.

#### [MODIFY] `review-workspace.js`
- Fetch do vocabulário, dropdowns editáveis, envio de dados curados.

---

### 7. Documentação
#### [MODIFY] `AGENTS.md`
- Nota da Spec 11 na seção de convenção de atributos bibliográficos.

## Verificação
- Teste manual de extração de entidades por região com documento real.
- Verificar que tipos inválidos são ignorados com mensagem amigável (não crasheiam).
- Verificar que o vocabulário aparece nos dropdowns da modal de curadoria.
