# Plano de Implementação — Spec 13: Ciclo de Vida Seguro de Usuários e Senhas

## Tarefas

1. Ajustar o modelo de dados para `must_change_password` e tokens de recuperação.
2. Remover senha fixa da migration de usuário inicial sem apagar/redefinir contas existentes.
3. Implementar serviço de autenticação para criação, troca e recuperação de senha.
4. Implementar controller, rotas e views para usuários e senhas.
5. Adicionar comandos CLI de criação e redefinição administrativa.
6. Configurar e-mail somente por variáveis de ambiente e adicionar validações.
7. Criar/atualizar testes de aceite e revisar documentação operacional.

## Segurança

Tokens são gerados com `random_bytes`, enviados somente por URL HTTPS, armazenados
como SHA-256 e invalidados após uso ou expiração. A resposta de recuperação não
confirma a existência do endereço. O acesso administrativo permanece restrito ao
papel `coordenador`.
