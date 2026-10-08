# Quickstart: Gestão de Sessões e Importação de Arquivos (Módulo 1)

Como preparar o ambiente e conferir a feature de ponta a ponta. Detalhes de modelo em
[data-model.md](./data-model.md); formatos em
[contracts/spreadsheet-layouts.md](./contracts/spreadsheet-layouts.md).

## Pré-requisitos

1. Dependências instaladas: `composer install` e `npm install`. Hoje `vendor/` e `node_modules/`
   não existem neste checkout, e por isso o servidor Laravel Boost não sobe.
2. `openspout/openspout` declarado em `composer.json` (depende de aprovação; ver
   [research.md](./research.md), R2).
3. PHP com `upload_max_filesize` e `post_max_size` de pelo menos 55M. O padrão local é 2M e 8M.
   O servidor web precisa do mesmo limite (por exemplo, `client_max_body_size` no nginx).
4. `.env`: `APP_LOCALE=pt_BR`, `QUEUE_CONNECTION=database`, `FILESYSTEM_DISK=local`.
5. Auto-registro e exclusão de conta desligados (tarefas da Fundação).

## Preparar

```bash
php artisan migrate
php artisan queue:work          # em outro terminal
npm run dev                     # ou npm run build
```

Criar os usuários de desenvolvimento com `php artisan db:seed` (um Administrador e um Operador,
só no ambiente `local`). Em outros ambientes, criar o primeiro Administrador com
`php artisan conciliation:create-administrator "Nome" email@exemplo.com`.

## Roteiro de conferência

Use planilhas fictícias geradas a partir das planilhas modelo. Os relatórios reais têm folha de
pagamento e nomes de pessoas e não devem entrar no repositório.

| # | Passo | Resultado esperado | História |
|---|-------|--------------------|----------|
| 1 | Entrar como Operador e abrir `/sessoes` | Lista vazia e ação "Nova Sessão" | 1 |
| 2 | Criar sessão "05/2026" | Painel com três cartões "Pendente"; "Executar Conciliação" indisponível | 1 |
| 3 | Baixar a planilha modelo de cada cartão | Arquivos com os cabeçalhos do layout | 1 |
| 4 | Enviar autorizações válidas com 3 linhas de valor zero | "Carregado", com "3 linhas ignoradas por valor negativo ou zero" | 1 |
| 5 | Enviar pagamentos em `.csv` com `;`, acentos em Windows-1252 e datas `31-MAY-26` | "Carregado"; nomes com acento corretos | 1 |
| 6 | Enviar o mesmo arquivo de pagamentos no outro cartão | Recusado | 1 |
| 7 | Enviar planilha com letras em um valor na linha 45 | Recusada; resumo com total e 10 primeiros erros; relatório para baixar | 2 |
| 8 | Enviar pagamentos no cartão de Autorizações | Recusada, com as colunas ausentes | 2 |
| 9 | Enviar planilha com datas de junho | Alerta com quantidade e intervalo; cancelar mantém o cartão | 3 |
| 10 | Reenviar e "Confirmar mesmo assim" | "Carregado" com indicador de divergência; registro na auditoria | 3 |
| 11 | Criar outra sessão "05/2026" e enviar os mesmos arquivos | Aviso de sessão complementar; cartões "Carregado" com zero lançamentos novos | 1 |
| 12 | Excluir a segunda sessão | Some da lista; arquivos apagados do disco; registro na auditoria | 6 |
| 13 | Com `CONCILIATION_ENGINE_ENABLED=false`, abrir a primeira sessão | "Executar Conciliação" indisponível, com a explicação | 4 |
| 14 | Entrar como Administrador e abrir `/sessoes/historico` | Exclusão do passo 12 listada com usuário, data e arquivos | 6 |
| 15 | Entrar como Operador e abrir `/sessoes/historico` | Acesso negado | 6 |

Execução, travamento e reabertura (Histórias 4 e 5) dependem do motor do Módulo 2. Até lá são
conferidos pelos testes automatizados, que usam um motor falso.

## Testes automatizados

```bash
php artisan test --compact tests/Unit/Conciliation
php artisan test --compact tests/Feature/Conciliation
vendor/bin/pint --dirty --format agent
```

| Arquivo | Cobre |
|---------|-------|
| `tests/Unit/Conciliation/MoneyParserTest.php` | toda a tabela de valores do contrato, incluindo fronteiras |
| `tests/Unit/Conciliation/DateParserTest.php` | toda a tabela de datas |
| `tests/Unit/Conciliation/PaymentsLayoutTest.php`, `AuthorizationsLayoutTest.php` | cabeçalhos, obrigatórios, identidade |
| `tests/Feature/Conciliation/CreateSessionTest.php` | período, sessão complementar, auditoria |
| `tests/Feature/Conciliation/ImportSpreadsheetTest.php` | aceitar, ignorar por valor, CSV do ERP, tudo ou nada, substituição, idempotência |
| `tests/Feature/Conciliation/RejectSpreadsheetTest.php` | cada motivo de recusa, resumo e relatório |
| `tests/Feature/Conciliation/PeriodDivergenceTest.php` | alerta, confirmar, cancelar, expirar |
| `tests/Feature/Conciliation/ComplementarySessionTest.php` | comparação por quantidade entre sessões |
| `tests/Feature/Conciliation/ExecuteReconciliationTest.php` | travamento, execução dupla, falha, progresso |
| `tests/Feature/Conciliation/ReopenSessionTest.php` | reabrir, bloqueio por decisões manuais, resultado inválido |
| `tests/Feature/Conciliation/DeleteSessionTest.php` | excluir, recusas, espaço liberado, auditoria que sobrevive |
| `tests/Feature/Conciliation/AuditLogTest.php` | somente acréscimo, conteúdo por ação |
| `tests/Feature/Conciliation/SessionAuthorizationTest.php` | papéis, visitante, histórico só para Administrador |
| `tests/Feature/Conciliation/SessionPanelTest.php` | painel com os três cartões, avisos e sessão excluída em outra aba |
| `tests/Feature/Conciliation/SessionHistoryTest.php` | histórico de exclusões e reaberturas |
| `tests/Feature/Conciliation/SpreadsheetReaderTest.php` | leitura de .xlsx e .csv, delimitador e codificação |
| `tests/Feature/Conciliation/SpreadsheetTemplateTest.php` | planilhas modelo |
| `tests/Feature/Conciliation/CreateAdministratorTest.php` | criação e promoção de Administrador |
| `tests/Feature/Conciliation/ImportPerformanceTest.php` | 10.000 linhas em menos de 60 s |
| `tests/Feature/Conciliation/ConcurrentUploadTest.php` | um único arquivo ativo por cartão |

Os testes de envio usam `Storage::fake` e planilhas geradas no próprio teste.

## Operação

Dois comandos ficam agendados em `routes/console.php`, a cada 10 minutos, e exigem o agendador
do Laravel ativo no servidor (`php artisan schedule:run` a cada minuto):

```bash
php artisan conciliation:prune-import-attempts
php artisan conciliation:recover-stuck-sessions
```

O primeiro cancela confirmações de divergência expiradas, encerra envios parados e apaga
tentativas e relatórios de erro mais antigos que o período de retenção. O segundo devolve a
"Aberta" a sessão parada em "Em processamento" além do limite.

Trabalhos de fila que falham ficam na tabela `failed_jobs` (`php artisan queue:failed`).
