# Lista de Tarefas — Spec 11: Vocabulário Controlado de Atributos & Validação Defensiva

- [x] `T1`: Criar migration `entity_attribute_vocabulary` + seed inicial de vocabulário por tipo de entidade
- [x] `T2`: Criar `EntityAttributeVocabularyModel` com consulta por tipo, agrupamento, formatação para prompt e validação de ENUM
- [x] `T3`: Adicionar endpoint `GET api/vocabulario-atributos` em `EntityController` + rota protegida
- [x] `T4`: Atualizar `DeepSeekService` — prompts com tipos válidos fixos + `buildVocabularyPromptSection()` + injeção em `extractFromCropText` e `extractKnowledge`
- [x] `T5`: Atualizar `GeminiVisionService` — restrição de tipos + vocabulário em `extractKnowledgeFromText` + `buildVocabularyPromptSection()`
- [x] `T6`: Atualizar `DocumentReviewController::confirmRegionEntities()` — validação defensiva de tipo + skippedEntities + injeção de vocabulário em `extractEntitiesFromRegion`
- [x] `T7`: Atualizar modal de curadoria (`review_workspace.php` + `review-workspace.js`) — dropdowns de tipo, atributos editáveis com vocabulário, cards por entidade
- [x] `T8`: Atualizar `AGENTS.md` — nota da Spec 11 na seção de convenção de atributos bibliográficos
- [x] `T9`: Melhorar contraste e legibilidade dos dropdowns no modal de curadoria
- [x] `T10`: Alinhar `extractFromCropImage()` no DeepSeek com restrição de tipos e vocabulário (prompt antigo corrigido)
- [x] `T11`: Propagar `$vocabulary` em `extractKnowledgeChunked()` para `extractKnowledge()` em cada chunk
- [x] `T12`: Atualizar `DocumentExtractionService` para carregar vocabulário do banco e passar para Gemini e DeepSeek no pipeline de 2 estágios
