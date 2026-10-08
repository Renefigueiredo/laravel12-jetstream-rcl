# Data Model: Gestão de Sessões e Importação de Arquivos (Módulo 1)

**Date**: 2026-10-08 | **Spec**: [spec.md](./spec.md) | **Research**: [research.md](./research.md)

Convenções: valores em centavos inteiros (`bigint`); datas e horas em UTC; datas de lançamento sem
hora (`date`); valores fixos como Enum PHP gravado em `string`. Nomes de tabelas e colunas em
inglês.

## Enums (`app/Enums`)

| Enum | Casos | Uso |
|------|-------|-----|
| `UserRole` | `Administrador`, `Operador` | `users.role` |
| `SessionStatus` | `Open`, `Processing`, `Processed` | `reconciliation_sessions.status` |
| `ImportSlot` | `Authorizations`, `PaymentsSocial`, `PaymentsSaude` | cartão do painel; informa o layout e a unidade |
| `SpreadsheetLayoutType` | `Authorizations`, `Payments` | layout de cabeçalhos |
| `OperatingUnit` | `Social`, `Saude` | `payment_entries.unit` |
| `ImportFileStatus` | `Active`, `Replaced` | `import_files.status` |
| `ImportAttemptStatus` | `Queued`, `Validating`, `Rejected`, `AwaitingConfirmation`, `Persisting`, `Accepted`, `Cancelled`, `Failed` | `import_attempts.status` |
| `AuditAction` | `SessionCreated`, `PeriodDivergenceConfirmed`, `FileReplaced`, `ReconciliationRequested`, `SessionReopened`, `SessionDeleted` | `audit_logs.action` (outros módulos acrescentam casos) |

## users (alteração)

| Coluna | Tipo | Regra |
|--------|------|-------|
| `role` | string | `UserRole`; padrão `operador`; não nulo |

## reconciliation_sessions

O nome evita conflito com a tabela `sessions` do Laravel. O número da sessão exibido nas telas é o
`id`.

| Coluna | Tipo | Regra |
|--------|------|-------|
| `id` | bigint PK | número sequencial da sessão (FR-002) |
| `period` | date | primeiro dia do mês de referência; não posterior ao mês corrente (FR-003) |
| `status` | string | `SessionStatus`; padrão `open` |
| `created_by` | FK `users` | restrito (usuário não é apagado) |
| `first_processed_at` | timestamp nulo | preenchido na primeira conclusão; não nulo = já processada alguma vez (FR-032) |
| `processing_started_at` | timestamp nulo | |
| `processed_at` | timestamp nulo | última conclusão |
| `progress` | smallint nulo | 0 a 100, enquanto `processing` |
| `result_stale` | boolean | verdadeiro após reabertura, até nova execução (FR-029) |
| `last_failure` | text nulo | mensagem da última falha (FR-027) |
| `created_at`, `updated_at` | timestamps | |

Índices: `period`; `status`. Não há unicidade em `period` (FR-003a).

**Transições de situação**

| De | Para | Gatilho | Condições |
|----|------|---------|-----------|
| (nova) | `Open` | criar sessão | período válido |
| `Open` | `Processing` | executar | três arquivos ativos; motor habilitado |
| `Processing` | `Processed` | job concluído | grava `first_processed_at` se nulo; `result_stale = false` |
| `Processing` | `Open` | job falhou, ou passou de `stale.processing_minutes` | resultado parcial descartado; `last_failure` preenchido |
| `Processed` | `Open` | reabrir | zero decisões manuais e vínculos externos; `result_stale = true` |
| `Open` | (apagada) | excluir | `first_processed_at` nulo |

Toda transição lê a linha com `lockForUpdate` dentro de uma transação e confere a situação atual
(FR-025, FR-031).

## import_files

Um arquivo aceito em um cartão.

| Coluna | Tipo | Regra |
|--------|------|-------|
| `id` | bigint PK | |
| `reconciliation_session_id` | FK | apaga em cascata com a sessão |
| `slot` | string | `ImportSlot` |
| `status` | string | `ImportFileStatus` |
| `original_name` | string | nome enviado |
| `disk`, `path` | string | disco privado; cópia exata do original (FR-014) |
| `size_bytes` | bigint | |
| `sha256` | char(64) | idempotência (FR-016) |
| `sheet_count` | smallint | maior que 1 gera o aviso "apenas a primeira aba foi lida" |
| `missing_columns` | json nulo | colunas opcionais do layout não encontradas no arquivo (FR-011k) |
| `rows_imported` | integer | lançamentos gravados |
| `rows_skipped_value` | integer | valor zero ou negativo (FR-010b) |
| `rows_skipped_existing` | integer | já existentes no período (FR-003c) |
| `rows_out_of_period` | integer | |
| `min_date`, `max_date` | date nulo | intervalo encontrado (FR-020) |
| `period_divergence` | boolean | aceito com divergência (FR-022) |
| `divergence_confirmed_by` | FK `users` nulo | |
| `divergence_confirmed_at` | timestamp nulo | |
| `uploaded_by` | FK `users` | |
| `replaced_at` | timestamp nulo | |
| `created_at`, `updated_at` | timestamps | `created_at` é o momento do envio |

Índices: único parcial em (`reconciliation_session_id`, `slot`) onde `status = 'active'`;
(`reconciliation_session_id`, `sha256`).

Regras: no máximo um arquivo ativo por sessão e cartão; o mesmo `sha256` não pode estar ativo nos
dois cartões de pagamento da mesma sessão; substituição marca o anterior como `replaced` na mesma
transação em que o novo se torna `active`.

## authorization_entries

Uma linha da planilha do ELO.

| Coluna | Tipo | Origem |
|--------|------|--------|
| `id` | bigint PK | |
| `import_file_id` | FK | cascata |
| `reconciliation_session_id` | FK | cascata; repetido para consulta |
| `row_number` | integer | linha na planilha |
| `request` | string | MAP_SOLICITACAO (obrigatório) |
| `supplier_name` | string | MAP_FORNECEDOR (obrigatório) |
| `amount_cents` | bigint | VALOR (obrigatório, maior que zero) |
| `authorized_on` | date | MAP_DATA_AUTORIZACAO (obrigatório) |
| `payment_method` | string nulo | MAP_FORMA_DE_PAGAMENTO |
| `card` | string nulo | MAP_AFRAFEP_CARTAO |
| `payment_condition` | string nulo | MAP_CONDICAO_DE_PAGAMENTO |
| `identity_key` | char(64) | SHA-256 de solicitação, fornecedor, valor e data normalizados |
| `raw` | json | todas as colunas como vieram no arquivo |
| `created_at` | timestamp | |

Índices: (`import_file_id`, `row_number`) único; `identity_key`; `reconciliation_session_id`.

## payment_entries

Uma linha do relatório de liquidação do ERP.

| Coluna | Tipo | Origem |
|--------|------|--------|
| `id` | bigint PK | |
| `import_file_id` | FK | cascata |
| `reconciliation_session_id` | FK | cascata; repetido para consulta |
| `row_number` | integer | linha na planilha |
| `unit` | string | `OperatingUnit`, dado pelo cartão (FR-011b) |
| `supplier_name` | string | CEDENTE (obrigatório) |
| `amount_cents` | bigint | VL_RECEBIDO (obrigatório, maior que zero) |
| `obligation_amount_cents` | bigint | VL_OBRIGACAO (obrigatório) |
| `paid_on` | date | DT_LIQUIDACAO (obrigatório) |
| `operation_code` | string | COD_OPERACAO (obrigatório) |
| `operation_name` | string nulo | NM_OPERACAO |
| `species` | string nulo | ESPECIE |
| `transaction_type` | string nulo | CD_TIPO_TRANSACAO |
| `obligation_number` | string | OBRIGACAO (obrigatório) |
| `source_document` | string nulo | DOC_GERADOR |
| `settlement_status` | string nulo | SITUACAO |
| `account_movement` | string nulo | CD_MOVIMENTO_CONTA |
| `identity_key` | string | OBRIGACAO, COD_OPERACAO e CD_MOVIMENTO_CONTA concatenados com um separador fixo (FR-011g) |
| `raw` | json | todas as colunas do arquivo como vieram |
| `created_at` | timestamp | |

Índices: (`import_file_id`, `row_number`) único; (`unit`, `identity_key`); `operation_code`
(usado pelo Módulo 5); `reconciliation_session_id`.

**Lançamento ativo**: aquele cujo `import_files.status` é `active`. A comparação entre sessões
(FR-003b) conta lançamentos ativos por `identity_key` (e `unit`, em pagamentos) nas outras sessões
com o mesmo `period`.

## import_attempts

Um envio em andamento ou encerrado. Sustenta o progresso, a confirmação de divergência e o
relatório de erros. Linhas encerradas são apagadas pelo comando agendado.

| Coluna | Tipo | Regra |
|--------|------|-------|
| `id` | bigint PK | |
| `reconciliation_session_id` | FK | cascata |
| `slot` | string | `ImportSlot` |
| `status` | string | `ImportAttemptStatus` |
| `user_id` | FK `users` | |
| `original_name` | string | |
| `disk`, `path` | string | arquivo recebido, ainda não definitivo |
| `size_bytes`, `sha256` | bigint, char(64) | |
| `progress` | smallint | 0 a 100 |
| `sheet_count` | smallint nulo | quantidade de abas, copiada para o arquivo aceito |
| `missing_columns` | json nulo | colunas opcionais do layout não encontradas |
| `rows_total`, `rows_valid`, `rows_skipped_value`, `rows_out_of_period` | integer nulo | resultado da validação |
| `min_date`, `max_date` | date nulo | |
| `error_count` | integer | |
| `first_errors` | json nulo | até 10 erros: linha, coluna, valor, motivo (FR-018) |
| `error_report_path` | string nulo | relatório completo (FR-019) |
| `message` | string nulo | motivo de recusa que não é por linha (tipo, tamanho, cabeçalho, vazio) |
| `divergence_confirmed_by` | FK `users` nulo | quem confirmou a divergência de período |
| `divergence_confirmed_at` | timestamp nulo | |
| `import_file_id` | FK nulo | preenchido quando aceita |
| `created_at`, `updated_at` | timestamps | |

**Estados da tentativa**

```text
Queued → Validating → Rejected                 (erros; nada gravado)
                    → AwaitingConfirmation → Persisting → Accepted
                                           → Cancelled   (quem enviou cancelou ou reabriu o painel; novo envio; expirou)
                    → Persisting → Accepted    (sem divergência)
qualquer estado → Failed                       (erro inesperado ou parado além do limite; nada gravado)
```

## audit_logs

Tabela única de auditoria, somente acréscimo.

| Coluna | Tipo | Regra |
|--------|------|-------|
| `id` | bigint PK | |
| `user_id` | FK `users` | restrito |
| `action` | string | `AuditAction` |
| `auditable_type` | string | por exemplo, `reconciliation_session` |
| `auditable_id` | bigint | sem chave estrangeira, para sobreviver à exclusão (FR-036) |
| `label` | string | texto de identificação, por exemplo "Sessão 12 - 05/2026" |
| `before` | json nulo | dados antes |
| `after` | json nulo | dados depois |
| `created_at` | timestamp | UTC; não há `updated_at` |

Índices: (`auditable_type`, `auditable_id`); `action`; `created_at`; `user_id`.

Proteção: o modelo lança exceção em `update` e `delete`; no PostgreSQL, um gatilho recusa
`UPDATE` e `DELETE`.

**Conteúdo por ação**

| Ação | `before` | `after` |
|------|----------|---------|
| `SessionCreated` | nulo | período, situação |
| `PeriodDivergenceConfirmed` | nulo | cartão, arquivo, linhas fora do período, intervalo de datas |
| `FileReplaced` | cartão, arquivo anterior, lançamentos | cartão, arquivo novo, lançamentos |
| `ReconciliationRequested` | situação `open` | situação `processing`, ids dos três arquivos |
| `SessionReopened` | situação `processed` | situação `open` |
| `SessionDeleted` | período, número, situação, nomes dos arquivos | nulo |

## Relacionamentos

```text
User 1─N ReconciliationSession (created_by)
ReconciliationSession 1─N ImportFile 1─N AuthorizationEntry | PaymentEntry
ReconciliationSession 1─N ImportAttempt N─1 User
ImportAttempt 0..1─1 ImportFile
User 1─N AuditLog
```

Todo modelo novo tem factory. `ReconciliationSessionFactory` tem os estados `processing`,
`processed`, `reopened` e `withActiveFiles`; `ImportFileFactory` tem `replaced` e
`withPeriodDivergence`.

## Configuração (`config/conciliation.php`)

| Chave | Padrão | Uso |
|-------|--------|-----|
| `upload.max_size_mb` | 50 | FR-009 |
| `upload.disk` | `local` | disco privado |
| `upload.insert_chunk` | 500 | lote de gravação |
| `attempts.confirmation_ttl_minutes` | 30 | expiração da confirmação de divergência |
| `attempts.retention_hours` | 24 | retenção de tentativas e relatórios de erro |
| `stale.attempt_minutes` | 30 | envio parado em fila, validação ou gravação vira falha (FR-027a) |
| `stale.processing_minutes` | 120 | sessão parada em "Em processamento" volta a "Aberta" (FR-027a) |
| `engine_enabled` | false | liga a execução quando o Módulo 2 existir |
| `display_timezone` | `America/Sao_Paulo` | exibição e mês corrente |
