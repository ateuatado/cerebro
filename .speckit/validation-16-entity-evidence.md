# Validação e atualização — Spec 16

## Resultado local

- Sintaxe PHP dos arquivos alterados: válida.
- PHPUnit `EntityObservationTest`: 3 testes, 11 asserções aprovadas.
- Renderização do painel: conteúdo escapado, fonte acessível e divergência visível.
- `git diff --check`: sem erros.
- pi-lens indisponível neste ambiente.
- Testes PostgreSQL preparados, ainda sem execução bem-sucedida: conexão de testes
  dedicada indisponível. Tentativa de instância descartável falhou por restrições
  de acesso/processo; arquivos temporários removidos. Não tratar como aceite integrado.
- Nenhuma limpeza ou migration aplicada ao banco do acervo.

## Atualização

Esta versão exige a migration `2026-10-22-000001_CreateEntityObservations` antes
de usar extração e detalhe de entidades. Ela adiciona uma tabela; não apaga dados.

Conferir `php spark migrate:status` e aplicar `php spark migrate` somente depois
de revisar as migrations pendentes. No ambiente local existe também a migration
anterior `AddSecureUserLifecycle` pendente, fora do escopo desta mudança; por isso
não foi executada automaticamente.

## Teste integrado em banco dedicado

Configurar `database.tests` para PostgreSQL de testes (nunca o acervo), com
`DBPrefix` vazio. Ativar `CEREBRO_PG_TESTS=1` e executar:

```sh
php vendor/bin/phpunit --no-coverage --filter EntityObservationPostgresTest
```

Os testes criam schema isolado dentro de transação revertida ao final.
Cobrem identidade entre fontes e classificações, atributos conflitantes,
preservação da confirmação, repetição idempotente, relações por fonte,
transferência manual e remoção em cascata.

## Conferência funcional após atualizar

1. Ingerir duas páginas com a mesma entidade e atributos diferentes.
2. Verificar um único nó e relações das duas fontes.
3. Abrir a entidade e conferir as ocorrências e links de origem.
4. Conferir que sugestões divergentes não mudaram os atributos revisados.
5. Reprocessar a mesma página: evidências idênticas não devem se multiplicar.

Identidade usa nome com caixa/espaços normalizados. Não aproxima grafias por IA.
Homônimos precisam de nomes distinguíveis na curadoria; documentos ficam separados
de entidades não documentais. Dois registros previamente ambíguos exigem revisão.
