# Validação e atualização — Spec 17

- PHP: 6 testes unitários/renderização, 19 asserções aprovadas.
- JavaScript: 7 testes de navegador simulado aprovados (incluindo limpeza).
- Sintaxe PHP/JS e diff-check válidos. pi-lens indisponível.
- Integração PostgreSQL preparada no teste opt-in da Spec 16, mas pendente de
  conexão de testes dedicada. Sem execução de IA ou modificação do acervo.

Após atualizar o código, conferir `php spark migrate:status` e aplicar as
migrations pendentes revisadas com `php spark migrate`. Esta versão acrescenta
`2026-10-22-000002_CreateUnresolvedRelationships` e pressupõe a tabela de
ocorrências da Spec 16. Não foi aplicada nenhuma migration automaticamente.

No grafo geral, as ocorrências já guardadas passam a aparecer sem reprocessar
documentos. Documentos sem ocorrência registrada continuam isolados. Sugestões
descartadas em versões antigas não podem ser recuperadas sem nova extração.

Vínculos documentais são cinza pontilhado, sem confiança numérica e sem aparecer
no filtro Confirmados. Cada vínculo reúne ocorrências da mesma entidade/fonte.
Hipóteses históricas continuam com o estilo anterior, separado da proveniência.

As futuras sugestões não resolvidas ficam no detalhe do documento-fonte com
motivo e trecho original. Não são promovidas automaticamente a relações;
correção e cadastro continuam pela revisão humana existente. O registro original
é preservado mesmo depois de cadastro manual de uma relação revisada.
