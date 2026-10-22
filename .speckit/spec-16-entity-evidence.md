# Spec 16 — Identidade única e dimensões documentais

Data: 2026-10-22. Constituição consultada: princípios I–VI.

Uma identidade nomeada pode aparecer em várias páginas sem multiplicar nós.
Ocorrências guardam atributos JSONB, classificação sugerida e documento-fonte,
sempre como hipótese. Atributos revisados nunca são sobrescritos pela extração.

Identidade automática: nome normalizado por caixa e espaços; documentos ficam
separados de entidades mencionadas. Um único candidato não documental é
reutilizado mesmo com classificação divergente, registrada na ocorrência.
Havendo mais de um candidato, a extração exige desambiguação em vez de escolher
silenciosamente. Nomes iguais são uma convenção de identidade neste acervo;
homônimos devem receber nomes distinguíveis na curadoria. Sem união por
similaridade, remoção de acentos ou inferência semântica.

Toda ocorrência referencia documento real e guarda a classificação e atributos
originais, sem converter divergências em fatos. Cada nova combinação de fonte,
referência, tipo e atributos é preservada; repetição idêntica é idempotente.
Relações conservam suas fontes. A visualização usa a identidade, e o detalhe
mostra ocorrências e divergências para revisão com a edição já existente.

Sem limpeza de dados, consolidação retroativa, novos tipos ou chamadas extras
de IA. Migration aditiva; tabelas e interfaces existentes permanecem.

Aceite: uma entidade em duas fontes resulta em um nó e duas ocorrências;
atributos conflitantes permanecem separados; confirmação prévia não é alterada;
classificações divergentes não criam segundo nó; documentos não são confundidos
com produtos homônimos; caminhos de lote, releitura e região usam a mesma regra.
