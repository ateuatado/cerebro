# Projeto Cerebro — Convenções

Ferramenta de pesquisa histórica para organizar documentos, fotos e textos
coletados pela professora Eliane Furoni, extraindo entidades (pessoas, locais,
eventos) e suas conexões em um grafo rastreável até a fonte primária.

Foco de pesquisa: violência de Estado em períodos de fragilização democrática
nas décadas de 1920-1930.

## Princípio fundador (não negociável)

Toda entidade e relação no grafo deve ser rastreável a uma fonte primária
específica (documento, página, trecho, foto). Conexões inferidas pelo sistema
(não explicitamente afirmadas na fonte) devem ser marcadas como hipótese, com
nível de confiança, e nunca apresentadas com o mesmo peso visual de um fato
documentado.

Nenhum dado entra no grafo como fato confirmado sem revisão humana explícita.

## Stack técnico

- **Framework**: CodeIgniter 4.7.4
- **Frontend**: Bootstrap 5.3.8 + Bootstrap Icons 1.13.1 — assets locais,
  **NUNCA usar CDN externo** para CSS/JS/fontes
- **Templates**: sistema de views/template nativo do CodeIgniter
  (não usar Blade, Twig, Smarty ou qualquer outro motor de template)
- **Banco de dados**: PostgreSQL

## Estrutura do projeto

```
cerebro/
├── app/                 # Controllers, Models, Views, Config
├── public/              # Web root
│   ├── index.php        # Entry point do CodeIgniter
│   ├── favicon.ico
│   └── assets/           # Assets locais
│       ├── css/
│       ├── js/
│       ├── img/
│       └── vendor/bootstrap/   # Bootstrap 5.3.8 + Icons 1.13.1, local
├── vendor/               # Dependências Composer
├── writable/             # Cache, logs, uploads
├── spark                 # CLI do CodeIgniter
├── setup-cerebro.ps1     # Script de scaffolding
└── AGENTS.md             # Este arquivo
```

## Regras de assets (obrigatórias)

- Views nunca contêm CSS ou JS inline — sempre em arquivos separados
- Referenciar assets via helper de URL do CodeIgniter (`base_url()`), nunca
  caminho absoluto hardcoded
- Nenhuma tag `<script src="https://...">` ou `<link href="https://...">`
  apontando para CDN em qualquer view

## Banco de dados — modelo de grafo em PostgreSQL

O grafo é implementado de forma relacional, não com um banco de grafo dedicado:

- **Entidades** (pessoa, local, evento, documento) em tabelas próprias
- **Atributos variáveis** (que mudam conforme o tipo de entidade) em colunas
  `JSONB`, não em colunas fixas para cada possível campo
- **Relações** (arestas do grafo) em uma tabela `connections`/`relationships`,
  com no mínimo: tipo de relação, direção, nível de confiança, referência à
  fonte documental, timestamp de criação, quem validou
- Consultas de travessia do grafo via `WITH RECURSIVE` (CTE recursiva)
- Extensões de grafo dedicadas (ex: Apache AGE) só devem ser consideradas se
  CTEs recursivas se mostrarem insuficientes — não instalar por padrão

## Direções futuras conhecidas

Decisões de design adiadas deliberadamente, registradas para evitar
rediscussão:

- **Multi-tenancy / múltiplos projetos de pesquisa**: considerado e adiado
  — decisão documentada na Spec 2 (`.speckit/spec-2-auth.md`). O escopo
  atual é um único projeto de pesquisa.
- **Recuperação de senha por e-mail**: fora de escopo até haver configuração
  de envio de e-mail (SMTP) no projeto.

## Convenção de atributos bibliográficos

Para entidades do tipo `document`, o campo `attributes` (JSONB) deve seguir um
vocabulário controlado de chaves. Esta é uma **convenção documental**, não uma
constraint de banco — o objetivo é garantir consistência nos dados desde o
primeiro documento cadastrado, viabilizando uma futura feature de geração de
citação bibliográfica (ABNT e normas customizáveis, possivelmente via
abordagem CSL) sem retrabalho de padronização retroativa.

### Chaves padrão

| Chave                        | Tipo   | Descrição                                          |
|------------------------------|--------|----------------------------------------------------|
| `autor_responsavel`          | string | Autor ou entidade responsável pelo documento       |
| `titulo`                     | string | Título formal do documento                         |
| `tipo_documento`             | string | Natureza do documento (ex: processo_judicial, oficio, correspondencia, foto, periodico) |
| `instituicao_custodiadora`   | string | Instituição que detém a guarda do original          |
| `localizacao_arquivistica`   | object | Referência de localização física, com subcampos:   |
|                              |        | `fundo` — nome do fundo arquivístico               |
|                              |        | `caixa` — identificador da caixa                   |
|                              |        | `maco` — identificador do maço                     |
| `data`                       | string | Data associada ao documento (YYYY-MM-DD ou YYYY-MM ou YYYY) |
| `data_acesso`                | string | Data em que o documento foi consultado/acessado    |

### Exemplo

```json
{
  "autor_responsavel": "Tribunal de Justiça do Estado de São Paulo",
  "titulo": "Processo Judicial n. 487/1929",
  "tipo_documento": "processo_judicial",
  "instituicao_custodiadora": "Arquivo Público do Estado de São Paulo",
  "localizacao_arquivistica": {
    "fundo": "Tribunal de Justiça",
    "caixa": "C-1929-03",
    "maco": "M-487"
  },
  "data": "1929-07-15",
  "data_acesso": "2025-11-10"
}
```

### Regras de uso

- Todas as chaves são opcionais — um documento pode ter apenas os campos
  conhecidos no momento do cadastro
- Chaves não previstas nesta lista **não devem ser inventadas sem antes
  atualizar esta convenção** — o vocabulário é fechado por design para
  evitar dispersão semântica
- Novas chaves devem ser propostas como alteração pontual nesta seção do
  AGENTS.md

## Ambiente de desenvolvimento

- **Servidor**: XAMPP (`C:\xampp\htdocs\cerebro`)
- **URL local**: <http://localhost/cerebro/public>
- **Comando alternativo**: `php spark serve` (servidor embutido do CodeIgniter)
- **DeepSeek API**: configurada via variável de ambiente `DEEPSEEK_API_KEY`
  (nunca hardcoded em nenhum arquivo)
- **CLI**: `php spark` (lista todos os comandos disponíveis)
- **Scaffolding de assets**: `.\setup-cerebro.ps1` (já executado)

## Regras de trabalho para o agente

- Sempre seguir o fluxo `/speckit.*` (constitution → specify → plan → tasks)
  para qualquer feature nova ou mudança estrutural relevante
- **Um commit git por tarefa concluída** do `/speckit.tasks` — nunca agrupar
  várias tarefas em um único commit
- Mensagens de commit devem referenciar a tarefa/spec correspondente
- Rodar lint/type-check (via pi-lens) antes de considerar qualquer tarefa
  concluída
- Nunca commitar credenciais, chaves de API, ou strings de conexão com
  senha real — usar variáveis de ambiente (`.env`, já ignorado pelo git)
- Nunca escrever em arquivos fora da pasta do projeto sem confirmação
  explícita
- Ao extrair dados de documentos históricos: sempre popular o campo de
  referência à fonte antes de considerar a extração válida; nunca inferir
  uma conexão entre entidades sem marcar explicitamente como hipótese
- **Este arquivo não deve ser reescrito ou resumido automaticamente.**
  Alterações devem ser feitas por edição pontual, preservando todas as
  seções acima. Se alguma seção parecer desatualizada, sinalizar ao usuário
  em vez de remover.

## O que evitar

- Frameworks de template alternativos ao nativo do CodeIgniter
- Qualquer dependência de frontend via CDN
- Escrita direta em colunas de "fato confirmado" a partir de inferência da IA
  sem passar por revisão humana
- Commits grandes e não granulares
