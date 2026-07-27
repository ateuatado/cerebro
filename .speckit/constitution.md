# Constituição do Projeto Cerebro

Este projeto (Cerebro) é uma ferramenta de pesquisa histórica para organizar
documentos, fotos e textos de pesquisa acadêmica, extraindo entidades
(pessoas, locais, eventos) e suas conexões em um grafo rastreável. O
propósito final é servir de arcabouço formal e verificável para uma pesquisa
de doutorado sobre violência de Estado em períodos de fragilização
democrática (décadas de 1920-1930).

Os princípios abaixo são inegociáveis e devem guiar toda decisão de
especificação, plano e implementação daqui em diante.

## Princípio I — Rastreabilidade total

Toda entidade e relação armazenada no grafo deve remeter obrigatoriamente a
uma fonte primária identificável (documento, página, trecho ou foto
específicos). Nenhuma feature pode ser especificada, planejada ou
implementada de forma que permita gravar um dado no grafo sem essa
referência de origem.

## Princípio II — Separação entre fato e hipótese

O sistema deve distinguir, de forma estrutural (não apenas visual), entre:
(a) fatos extraídos e confirmados por revisão humana, e (b) hipóteses ou
conexões inferidas pelo sistema ou sugeridas pela pesquisadora, ainda não
verificadas. Hipóteses nunca podem ser apresentadas, armazenadas ou
consultadas com o mesmo peso ou aparência de um fato confirmado.

## Princípio III — Revisão humana obrigatória

Nenhum dado extraído automaticamente (via IA/OCR) entra no estado de "fato
confirmado" sem passar por uma etapa explícita de validação por um usuário
humano. Toda feature de ingestão ou extração deve incluir uma etapa de
revisão como parte do fluxo, não como acréscimo posterior.

## Princípio IV — Simplicidade e granularidade (TDD estrito)

Toda funcionalidade deve ser desenvolvida em unidades pequenas e testáveis.
Testes precedem ou acompanham a implementação (test-first sempre que
viável). Tarefas grandes ou ambíguas devem ser quebradas antes da
implementação começar. Evitar abstrações prematuras ou generalizações não
solicitadas pela especificação atual.

## Princípio V — Stack e restrições técnicas fixas

- Backend: PHP + CodeIgniter 4, usando o sistema de views nativo do
  framework (nenhum outro motor de template)
- Frontend: Bootstrap, com todos os assets hospedados localmente —
  nenhuma dependência de CDN externo
- Banco de dados: PostgreSQL, com o grafo modelado de forma relacional
  (tabelas de entidades + tabela de relações + JSONB para atributos
  variáveis + CTEs recursivas para travessia)
- Toda a stack e suas restrições estão detalhadas em AGENTS.md, que deve
  ser consultado e respeitado em toda especificação e implementação

## Princípio VI — Governança do processo

Specs, planos e tarefas seguem sempre o fluxo completo
(constitution → specify → plan → tasks) para qualquer feature nova ou
mudança estrutural. Cada tarefa concluída gera exatamente um commit,
referenciando a tarefa correspondente. Nenhuma credencial, chave de API ou
segredo é gravado em código ou histórico de commit.

Qualquer especificação ou plano que viole um destes princípios deve ser
rejeitado ou revisado antes de prosseguir para implementação.
