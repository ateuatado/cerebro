# Plano 19 — Caderno de pesquisa

A Constituição permanece aplicável. Reusar autenticação, permissões e proteção CSRF. Usar views nativas, Bootstrap e assets locais e PostgreSQL.

1. Criar o menu Caderno de pesquisa e a linha do tempo do Diário. Persistir entradas e vínculos opcionais, sem depender de produtos.
2. Criar Fichamentos com busca e formulário. Referenciar o ID do documento existente e guardar o localizador na ficha. Validar vínculos no servidor e preservar fichas quando houver tentativa de excluir um registro vinculado.
3. Integrar o atalho no visualizador, verificando documento e página e oferecendo retorno ao original.
4. Depois da biblioteca da T20.1, permitir fichar obras pela mesma interface, mantendo referências distintas para obras e documentos. Essa integração não exige que a professora crie um produto.

Verificar permissões, entradas sem fonte, múltiplas fichas por documento, retorno à página e ausência de mudanças no grafo. Validar no navegador e no PostgreSQL; rodar pi-lens antes de fechar cada tarefa.

## Integração com a Biblioteca

Usar vínculo de uma obra para muitas notas e exibir a coleção tanto no detalhe da obra quanto na busca de Fichamentos. Testar duas notas na mesma página, edição independente e exibição dos metadados sem copiá-los para as notas.
