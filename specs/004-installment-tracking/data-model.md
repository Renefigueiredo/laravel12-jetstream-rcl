# Data Model: Gestão de Parcelamentos e Pagamentos Parciais (Módulo 3)

Valores em centavos inteiros. Meses guardados como a data do primeiro dia do mês.

## Tabelas novas

### `installment_plans`

O plano de parcelas que o Operador informou para uma autorização.

| Coluna | Tipo | Regra |
|--------|------|-------|
| `id` | bigint | chave |
| `authorization_identity_key` | char(64) | única; a chave de identidade da autorização (R6) |
| `created_by` | FK `users` | quem informou; `restrictOnDelete` |
| `updated_by` | FK `users`, nulo | quem alterou por último |
| `created_at`, `updated_at` | timestamp | |

- Uma autorização tem no máximo um plano em vigor.
- Sem chave estrangeira para `authorization_entries`: o plano sobrevive à substituição da
  planilha e volta a valer para a autorização de mesma chave.

### `installment_plan_items`

| Coluna | Tipo | Regra |
|--------|------|-------|
| `id` | bigint | chave |
| `installment_plan_id` | FK `installment_plans` | `cascadeOnDelete` |
| `position` | smallint | de 1 em diante; única com `installment_plan_id` |
| `amount_cents` | bigint | maior que zero |
| `expected_month` | date, nulo | primeiro dia do mês esperado |

- De 1 a 120 itens por plano.
- A soma de `amount_cents` é igual ao valor autorizado, dentro da tolerância em vigor ao salvar.

### `authorization_forecasts`

Previsão materializada, uma linha por autorização que tem parcelas previstas (R9). É um cache
derivado: pode ser apagada e refeita a qualquer momento por `InstallmentForecaster`.

| Coluna | Tipo | Regra |
|--------|------|-------|
| `authorization_entry_id` | FK `authorization_entries` | chave primária; `cascadeOnDelete` |
| `expected_count` | smallint | parcelas previstas |
| `paid_count` | smallint | parcelas ocupadas por um pagamento |
| `overdue_count` | smallint | parcelas em aberto com mês esperado já processado; índice |
| `next_expected_month` | date, nulo | mês da próxima parcela em aberto |
| `from_plan` | boolean | se as parcelas vêm do plano informado ou da condição de pagamento |
| `updated_at` | timestamp | |

- Autorização sem plano e sem condição com mais de uma parcela não tem linha.
- Autorização Conciliada tem `overdue_count` zero.

## Tabelas alteradas

Nenhuma coluna nova em tabelas existentes. Índices criados (uma chave estrangeira não traz
índice): `authorization_states (balance_cents)`,
`reconciliation_links (authorization_entry_id, treatment)`,
`reconciliation_links (reconciliation_run_id)`, `reconciliation_skips (authorization_entry_id)`
e `reconciliation_skips (payment_entry_id)`.

## Modelos

| Modelo | Relações e métodos novos |
|--------|--------------------------|
| `InstallmentPlan` | `items()` ordenados por `position`; `creator()`, `updater()`; `authorization()` pela chave de identidade |
| `InstallmentPlanItem` | `plan()` |
| `AuthorizationForecast` | `authorization()` |
| `AuthorizationEntry` | `plan()` (pela chave de identidade), `forecast()` |

## Enums

| Enum | Casos |
|------|-------|
| `InstallmentStatus` | `Paid`, `Open`, `Overdue` |
| `StatementLineKind` | `Payment`, `Discount`, `ToleranceWriteoff`, `AcceptedSurcharge`, `Overpayment` |
| `AuditAction` (novos) | `InstallmentPlanSaved`, `InstallmentPlanRemoved` |

## Objetos de valor (classes puras)

### `StatementLine`

`kind`, `linkId`, `paymentId`, `paidOn`, `amountCents` (o que a linha abate; zero no pagamento a
maior, que só informa o excedente), `informedCents` (valor mostrado), `balanceAfterCents`,
`installmentPosition` (nulo quando fora do plano ou sem previsão).

### `ScheduledInstallment`

`position`, `amountCents`, `expectedMonth` (nulo sem previsão de data), `status`, `paymentId`
(nulo quando em aberto).

## Derivados, não guardados

| Dado | De onde vem |
|------|-------------|
| Saldo e situação da autorização | `authorization_states`, mantida pelo Módulo 2 a partir dos vínculos |
| Linhas do extrato | `AuthorizationStatement`, a partir de `reconciliation_links` |
| Qual pagamento ocupa qual parcela | `InstallmentSchedule`, a partir do plano e dos vínculos |
| Divergências | visão `reconciliation_pending_items`, tipos `unmatched_payment` e sugestão `excess` |
| Totais do painel | agregação sobre `authorization_entries` e `authorization_states` |

## Regras de integridade

- Salvar e remover plano acontecem em transação, com trava na autorização, e gravam auditoria na
  mesma transação.
- A previsão é recalculada na mesma transação de todo vínculo e desvínculo.
- Nenhuma tabela nova guarda saldo.
- Apagar uma autorização (sessão nunca processada excluída) apaga a previsão; o plano fica, sem
  efeito, até a autorização de mesma chave voltar.
