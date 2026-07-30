# Especificação 6 — Ingestão em 2 Estágios e Painel de Documentos Pendentes

## Contexto & Objetivos

No projeto **Cerebro**, a ingestão de documentos históricos extensos (como edições completas de jornais e processos judiciais) exige uma arquitetura robusta que não tente forçar o processamento de textos gigantescos em uma única chamada síncrona com risco de erro de codificação JSON ou estouro de limites.

A **Spec 6** introduz a arquitetura de **Ingestão em Dois Estágios Desacoplados** e cria o **Painel Visual de Documentos Pendentes de Extração**, permitindo o controle exaustivo, a edição manual prévia da transcrição e o disparar de extrações por IA bloco a bloco tanto de forma individual quanto em lote.

---

## Princípios não negociáveis (Constituição)

1. **Rastreabilidade à Fonte Primária (Princípio I)**: Cada hipótese extraída no Estágio 2 deve ser gravada na tabela `relationships` referenciando obrigatoriamente o `source_document_id` e o trecho transcrito em `source_reference` (JSONB).
2. **Diferenciação entre Fato e Hipótese (Princípio II)**: Toda extração automatizada via IA entra no banco categorizada estritamente como `status = 'hypothesis'`, aguardando validação por um usuário com perfil `coordenador`.
3. **Padrão Nativo PHP/CodeIgniter (Princípio V)**: Toda a lógica do pipeline é implementada nativamente em PHP 8.2+ no CodeIgniter 4, **sem scripts ad-hoc em Python** ou dependências externas incompatíveis.
4. **Zero CDN & Assets Locais**: As telas e modais utilizam Bootstrap 5.3.8 local e Bootstrap Icons locais.

---

## Requisitos Funcionais

### RF-1: Armazenamento no Repositório de Texto Bruto (Estágio 1)
- O sistema deve aceitar upload de arquivos (.txt, .pdf, .jpg, .png, .jpeg, .webp).
- O `DocumentParserService` extrai 100% da transcrição/OCR sem truncamento arbitrário.
- O documento é registrado/atualizado na tabela `entities` (type = `document`), armazenando a transcrição completa no atributo `attributes->'conteudo_transcrito'`.
- O documento recebe o status de extração `attributes->'extraction_status' = 'pending'`.

### RF-2: Serviço de Extração Tokenizada Sanitizada (Estágio 2)
- Criar o `DocumentExtractionService` nativo em PHP.
- **Sanitização de Caracteres**: Deve sanitizar caracteres de controle ASCII não imprimíveis (`[\x00-\x1F\x7F]`) e tratar quebras de linha para evitar o erro `Control character error, possibly incorrectly encoded`.
- **Chunking Contextual**: Deve dividir o `conteudo_transcrito` em blocos sequenciais de ~2.500–3.000 caracteres respeitando quebras de parágrafo.
- **Extração Exaustiva**: Enviar cada bloco à API DeepSeek solicitando JSON estruturado com entidades (`person`, `location`, `event`) e relacionamentos.
- **Deduplicação & Persistência**: Mesclar entidades duplicadas por nome/tipo e inserir as relações como hipóteses vinculadas ao `source_document_id`.
- **Atualização de Status**: Atualizar `extraction_status` para `'completed'` (ou `'error'` com a mensagem de exceção).

### RF-3: Interface Visual de Gestão de Pendentes (`/documentos/pendentes`)
- Rota acessível pelo menu superior do sistema ("Pendentes de Extração").
- Exibe contador em tempo real dos documentos pendentes e total de caracteres acumulados.
- Tabela com os documentos em estado `'pending'` ou `'error'`.
- **Modal de Transcrição**: Permite visualizar e editar o texto bruto `conteudo_transcrito` antes de enviar para a IA.
- **Botão "Extrair por IA"**: Dispara a extração via AJAX com feedback de progresso e atualiza a página ao concluir.
- **Botão "Processar Todos os Pendentes"**: Dispara o reprocessamento em lote de todos os arquivos pendentes.

---

## Critérios de Aceite

1. Documentos ingeridos salvam 100% da transcrição no campo `conteudo_transcrito` do JSONB `attributes`.
2. A tela `/documentos/pendentes` lista todos os documentos com status `pending` ou `error`.
3. O modal de transcrição permite ler e alterar o texto bruto do documento.
4. O `DocumentExtractionService` sanitiza caracteres de controle e executa a extração em blocos sem falhas no `json_decode()`.
5. Extrações criam entidades e relações no banco de dados categorizadas como `status = 'hypothesis'`.
6. Todas as visualizações seguem a regra de zero CDN (Bootstrap 5 local).
