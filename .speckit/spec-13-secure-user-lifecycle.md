# Spec 13 — Ciclo de Vida Seguro de Usuários e Senhas

**Status**: implementado localmente; validação operacional pendente  
**Data**: 2026-10-21  
**Dependências**: Spec 2 (autenticação), AGENTS.md  
**Princípios aplicáveis**: III (revisão humana), IV (simplicidade), V (stack fixa), VI (governança)

## Objetivo

Substituir a dependência de uma senha fixa criada por migration por um fluxo
seguro de criação de usuários, troca de senha e recuperação de acesso.

## Requisitos

- Coordenador autenticado pode criar usuários com papel coordenador ou colaborador.
- Usuário autenticado pode trocar a própria senha informando a senha atual.
- Usuário recém-criado pode ser obrigado a trocar a senha temporária no primeiro acesso.
- Recuperação usa token aleatório de uso único, armazenado somente como hash e com expiração.
- O pedido de recuperação sempre retorna mensagem genérica, sem revelar se o e-mail existe.
- Senhas nunca são gravadas em texto puro, código, logs ou respostas HTTP.
- A migration inicial não pode redefinir senhas em cada instalação/deploy.
- Existe comando CLI para criar usuário coordenador e redefinir senha em emergência,
  sem exibir a senha em logs persistentes.

## Fora de escopo

- Recuperação sem SMTP configurado.
- Convites multi-tenant ou permissões além dos papéis existentes.

## Critérios de aceite

1. A migration não contém senha fixa nem atualiza senha de usuário existente.
2. Coordenador consegue criar usuário e o novo usuário é obrigado a trocar a senha.
3. Usuário consegue trocar a própria senha com proteção CSRF e confirmação.
4. Token de recuperação é aleatório, expira, é armazenado como hash e não pode ser reutilizado.
5. Pedido para e-mail inexistente e existente possui a mesma resposta externa.
6. O CLI permite criar/redefinir usuário sem registrar senha em arquivo.
7. Credenciais SMTP vêm somente de variáveis de ambiente.
