# Especificação 8 — Extração de Entidades e Grafo a partir da Seleção de Região (Vision-AI HTR & Extração em 1-Clique)

## Contexto & Objetivos

Documentos históricos como os boletins de batalhões e processos judiciais das décadas de 1920-1930 possuem seções de texto cursivo denso ("Observações", "Relação de Praças", "Ocorrências da Segunda-Feira") ricas em nomes de pessoas, patentes militares, locais de transferência e eventos.

A **Spec 8** permite que o pesquisador selecione qualquer área retangular do documento com o mouse e dispare em 1 clique a **Extração de Entidades e Grafo pela IA**, permitindo revisar as entidades encontradas (pessoas, locais, eventos) antes de gravá-las no Grafo de Hipóteses.

---

## Princípios não negociáveis (Constituição)

1. **Revisão Humana Obrigatória para Grafo (Princípio II)**: Nenhuma entidade entra no Grafo como fato confirmado sem validação do pesquisador. As entidades descobertas na região entram como hipótese (`status = 'hypothesis'`) e passam pela modal de aprovação do pesquisador.
2. **Rastreabilidade à Fonte (Princípio I)**: Todas as entidades e relações extraídas do recorte mantêm a referência ao `source_document_id` e ao trecho manuscrito correspondente.
3. **Desempenho & Resiliência**: Suporte a recortes de imagem com codificação Base64 e instruções especializadas em paleografia e acrônimos militares históricos em português (décadas de 1920-1930).

---

## Requisitos Funcionais

### RF-1: Ação de Extração de Grafo por Região (Crop Tool)
- Adição do botão **"✨ Extrair Entidades (IA)"** na barra de ferramentas do recorte de região.
- Ao selecionar uma área retangular e clicar no botão, o backend recebe as coordenadas `[x, y, w, h]`, recorta a imagem e envia a região para o pipeline de leitura e extração.

### RF-2: Pipeline de Visão Multimodal & HTR
- Envio do recorte em alta definição codificado em Base64 para a API da IA com prompt especializado em reconhecer caligrafia cursiva em português e extrair entidades.
- Retorno estruturado contendo:
  - Transcrição do texto da região.
  - Lista de Entidades encontradas (`name`, `type`, `attributes`).
  - Lista de Relações encontradas (`source`, `target`, `relationship_type`, `confidence`).

### RF-3: Modal de Pre-visualização & Aprovação de Entidades
- Exibição de modal com as entidades e relações encontradas na área selecionada.
- O pesquisador pode selecionar quais entidades deseja incluir e clicar em **"Confirmar e Adicionar ao Grafo"**.
- O texto transcrito da região é anexado automaticamente ao editor de transcrição do documento.
