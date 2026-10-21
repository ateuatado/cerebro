# Spec 15 — Identidade e Deduplicação de Entidades

**Status**: implementado localmente; validação operacional pendente  
**Data**: 2026-10-21  
**Dependências**: Spec 1 (modelo de dados), Spec 6 (extração)  

## Objetivo

Evitar que diferenças de maiúsculas, minúsculas ou espaços criem múltiplos nós
para a mesma entidade, preservando o tipo e o rastro documental.

## Requisitos

- A identidade de ingestão deve normalizar caixa e espaços.
- A comparação deve respeitar o tipo da entidade.
- O cadastro manual não deve criar duplicata silenciosamente.
- Deve existir comando explícito para consolidar duplicatas já existentes.
- Relações e vínculos de pesquisa devem apontar para o registro sobrevivente.
- Nenhuma consolidação deve apagar a fonte documental das relações.
