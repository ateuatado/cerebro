# Validação 20 — Produtos intelectuais

O teste integrado rodou no PostgreSQL local em uma transação revertida ao final. As 15 verificações passaram:

- Tese e artigo com estruturas próprias; edição, recuperação de texto com quebra de linha, reordenação e inclusão de subseção.
- Mesma obra nos dois produtos e em duas partes da tese; inclusão repetida sem duplicação e tentativa de vínculo entre produtos rejeitada.
- Retirada de uma parte preserva os outros vínculos. Retirada do artigo preserva a tese e a Biblioteca. Exclusão de uma subseção limpa seus vínculos.
- Impressões digitais dos registros de entidades e relações iguais antes e depois do fluxo. Produtos e obra de teste não permaneceram após a reversão.

No navegador, o usuário confirmou salvar texto e mudar a posição das partes. A verificação do percurso completo pelo navegador segue pendente. pi-lens indisponível no ambiente.
