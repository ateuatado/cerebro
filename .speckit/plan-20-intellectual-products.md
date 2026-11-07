# Plano 20 — Produtos intelectuais

A Constituição permanece aplicável. A área usa a conta atual, com autenticação, autorização e CSRF conforme as convenções do projeto. Usar CodeIgniter com views nativas, Bootstrap e assets locais e PostgreSQL. Nenhuma operação escreve no grafo.

1. Criar a Biblioteca com armazenamento próprio e metadados independentes de normas de citação. Oferecer busca antes do cadastro e preservar referências de fichas e produtos quando uma obra estiver vinculada.
2. Criar a lista de produtos e a edição por abas: Estrutura e texto, Bibliografia. Persistir cada produto e suas partes ordenadas, com hierarquia onde aplicável. Copiar uma estrutura inicial pelo tipo ao criar o produto; edições afetam apenas aquele produto. Validar ordem, hierarquia e pertencimento das partes no servidor.
3. Vincular obras e produtos em relação muitos para muitos, com unicidade do par. Desvincular remove apenas a associação. Expor a seleção de obras para o fichamento da Spec 19 sem dependência de um produto.
4. Verificar persistência da escrita, edição da estrutura, compartilhamento e remoção independente de obras. Exercitar permissões e fluxos no navegador e PostgreSQL, e rodar pi-lens antes de fechar cada tarefa.

## Navegação da Biblioteca

Dar à Biblioteca uma entrada própria no menu e uma tela de detalhe por obra, acessível sem produto. Integrar a coleção de notas nessa tela quando T19.4 estiver disponível. A aba Bibliografia de um produto seleciona obras desse cadastro.
