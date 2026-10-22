# Spec 17 — Proveniência visível e relações não resolvidas

Data: 2026-10-22. Constituição I–VI consultada.

O grafo projeta as ocorrências da Spec 16 como vínculos documentais entre uma
identidade e sua fonte, agrupados por par. Estes não são afirmações históricas,
não recebem confiança inventada e nunca aparecem no filtro de confirmados.
Linha cinza pontilhada, legenda e tooltip identificam sua natureza de hipótese
de ocorrência. Os detalhes da entidade permitem acessar as fontes e atributos.
O vínculo não promove a entidade, ocorrência ou relação a fato confirmado.

Relações sugeridas pela IA cujas pontas não puderem ser resolvidas, ou que se
tornem autorrelações, devem ser preservadas separadamente como pendências,
com payload original, motivo e documento-fonte. Elas não são arestas históricas.
O detalhe do documento permite inspecioná-las e encaminhar correção pela
revisão existente. Nunca aproximar nomes silenciosamente nem inventar relações.

Consultas de pesquisa incluem entidades e fontes das ocorrências somente dos
documentos vinculados à pesquisa. Não criar vínculos retroativos sem ocorrência.
Não limpar dados. Migration aditiva apenas para pendências; grafos consultam
ocorrências já armazenadas e não dependem de nova chamada de IA.

Aceite: entidade sem relação histórica mas com ocorrência conecta-se à fonte;
repetições do mesmo par produzem uma linha; confirmado não mostra proveniência;
relação inválida mantém fonte e payload, sem entrar na tabela relationships.
