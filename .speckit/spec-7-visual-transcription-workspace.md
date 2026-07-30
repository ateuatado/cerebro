# Especificação 7 — Workspace Interativo de Transcrição Histórica (Rotação, Recorte por Região & HTR com IA)

## Contexto & Objetivos

Documentos históricos (como o acervo de jornais, relatórios do exército, mapas diários de batalhões e boletins policiais das décadas de 1920-1930) frequentemente apresentam:
1. **Páginas Digitalizadas Deitadas ou Invertidas (Rotação de 90°/180°)**: O OCR falha completamente se o texto estiver deitado ou de cabeça para baixo.
2. **Manuscritos Cursivos e Tabelas**: Estruturas complexas onde recortar uma coluna ou trecho específico resulta em uma leitura infinitamente mais precisa da IA.
3. **Necessidade de Rastreabilidade Fiel (Princípio I)**: Manter o vínculo direto entre o arquivo/página original e a transcrição corrigida pelo pesquisador humano.

A **Spec 7** cria o **Workspace Interativo de Transcrição Histórica**, oferecendo ferramentas visuais de navegação por página, rotação de imagens com re-OCR automático, seleção e recorte interativo por região (Crop Tool com IA) e editor paginado com sincronização em tempo real.

---

## Princípios não negociáveis (Constituição)

1. **Rastreabilidade à Fonte Primária (Princípio I)**: Todo texto revisado fica vinculado à página exata e ao documento original no repositório.
2. **Revisão Humana Obrigatória (Princípio II)**: O texto extraído pela IA ou OCR regional entra no editor como proposta de transcrição, cabendo ao pesquisador humano revisar e salvar no repositório.
3. **Desenvolvimento Nativo PHP (Princípio V)**: Toda a lógica de renderização, rotação de imagem e recorte é executada nativamente em PHP com GD/Imagick ou comandos seguros do sistema, **sem dependência de APIs externas de terceiros inacessíveis**.
4. **Zero CDN**: Bootstrap 5.3.8 local e Bootstrap Icons locais.

---

## Requisitos Funcionais

### RF-1: Navegação Paginada & Cache de Imagens
- Para documentos PDF ou conjunto de imagens, o sistema deve renderizar e expor cada página individualmente.
- O usuário navega entre as páginas (`Página 1 de 11`, `Página 2 de 11`, etc.).

### RF-2: Rotação Visual & Re-OCR Orientado
- Botões na barra de ferramentas da imagem: `Girar 90° Horário`, `Girar 90° Anti-horário`, `Girar 180°`.
- Ao girar a página, a imagem da página é fisicamente rotacionada no servidor, salva a nova orientação e dispara um re-OCR automático na página com o texto na orientação correta.

### RF-3: Seleção e Recorte Interativo por Região (Crop Tool)
- No canvas/container da página, o usuário pode clicar e arrastar para desenhar um retângulo de seleção sobre qualquer trecho manuscrito ou coluna de tabela.
- Ao clicar em **"Extrair Região Selecionada (IA)"**, o backend recebe as coordenadas `[x, y, largura, altura]`, gera o recorte da área, envia à IA para leitura HTR (Handwritten Text Recognition) e insere o resultado no editor da direita.

### RF-4: Editor de Transcrição Paginado Lado a Lado
- O workspace exibe em tela dividida (Visualizador de Imagem à esquerda, Editor de Texto à direita).
- O pesquisador lê a imagem em alta resolução (com zoom/pan/rotação) e edita a transcrição exata daquela página.
- Botão "Salvar Transcrição da Página" persiste as alterações no atributo `attributes->'conteudo_transcrito'` do repositório PostgreSQL.

---

## Critérios de Aceite

1. O workspace em `/documentos/{id}/revisar` carrega o visualizador de imagem da página e o editor de transcrição lado a lado.
2. O recurso de rotação altera a orientação física da imagem da página e atualiza a exibição.
3. A ferramenta de recorte permite desenhar um retângulo sobre a imagem, enviar a área selecionada para a IA e receber a transcrição manuscrita/tabelada no editor.
4. Alterações salvas no editor atualizam o repositório do documento no PostgreSQL sem perdas.
5. Todos os scripts e estilos são locais (zero CDN).
