# Plano 16

1. Migration aditiva `entity_observations`: FK de entidade e documento, JSONB
   para atributos e referência, tipo sugerido, status fixo hypothesis e impressão
   determinística para evitar repetições idênticas. ON DELETE CASCADE acompanha
   a limpeza existente. Serviço transacional com lock PostgreSQL por nome.
2. Centralizar persistência de entidades/relações nos caminhos de extração.
   Preservar atributos canônicos existentes; sugestões novas ficam nas ocorrências.
   Evitar perda de atributos entre blocos da IA.
3. Exibir ocorrências no detalhe com links para fontes e aviso de divergência;
   testar sem chamadas externas, incluindo PostgreSQL em transação revertida.

Validação: PHPUnit, sintaxe PHP/JS aplicável e diff-check. pi-lens será usado
se disponível; ausência deve ser relatada. Sem migração automática no VPS.
