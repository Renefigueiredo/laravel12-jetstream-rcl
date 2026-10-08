# Data Model: Motor de Conciliação e Tratamento de Divergências (Módulo 2)

Sete tabelas novas, uma visão e alterações em `authorization_entries` e `payment_entries`. Valores em centavos
inteiros, percentuais em pontos-base (1% = 100), datas e horas em UTC. Decisões em
[research.md](./research.md).

## Enums (`app/Enums`)

| Enum | Casos | Uso |
|------|-------|-----|
| `ReconciliationRunStatus` | `Running`, `Completed`, `Failed`, `Discarded` | Situação de uma execução |
| `MatchClassification` | `Automatic`, `Installment`, `Doubtful`, `Partial`, `Excess` | Como o motor classificou o par |
| `SuggestionStatus` | `Pending`, `Confirmed`, `Rejected`, `Superseded` | Situação de uma sugestão |
| `LinkOrigin` | `Automatic`, `Manual` | Quem criou o vínculo |
| `DifferenceType` | `Exact`, `Partial`, `Excess` | Diferença de valor no momento do vínculo |
| `DifferenceTreatment` | `StillOwed`, `Discount`, `AcceptedSurcharge`, `Overpayment` | Decisão sobre a diferença |
| `JustificationCategory` | `CommercialDiscount`, `InterestOrFine`, `Freight`, `PriceAdjustment`, `Rounding`, `Other` | Categoria da justificativa (Princípio VII) |
| `AuthorizationStatus` | `Open`, `Partial`, `Reconciled` | Situação derivada da autorização |
| `SkipReason` | `ExcludedCode`, `DuplicateOfOtherPeriod` | Por que o lançamento ficou fora |
| `PairBlockReason` | `Rejected`, `Unlinked` | Por que o par não volta a ser sugerido |
| `PendingItemKind` | `Suggestion`, `UnmatchedAuthorization`, `OpenBalance`, `UnmatchedPayment` | Linha da lista de pendências |
| `UserPermission` (existente) | novo: `ConfigureTolerance` | Configurar tolerância e teto |
| `AuditAction` (existente) | novos: ver research R19 | Trilha de auditoria |

## reconciliation_settings

Linha única com os parâmetros que o Administrador altera pela tela.

| Coluna | Tipo | Regras |
|--------|------|--------|
| `id` | bigint | PK |
| `tolerance_cents` | unsignedInteger | padrão 50; mínimo 0 |
| `tolerance_basis_points` | unsignedInteger | opcional; de 0 a 10000 |
| `surcharge_cap_basis_points` | unsignedInteger | padrão 1000; de 0 a 10000 |
| `updated_by` | FK `users` | opcional; `restrictOnDelete` |
| `created_at`, `updated_at` | timestamp | |

A migração cria a linha com os valores iniciais.

## reconciliation_runs

Uma linha por execução. Nunca é apagada.

| Coluna | Tipo | Regras |
|--------|------|--------|
| `id` | bigint | PK |
| `reconciliation_session_id` | FK | `restrictOnDelete`; indexado |
| `status` | string | `ReconciliationRunStatus`; indexado |
| `requested_by` | FK `users` | `restrictOnDelete` |
| `tolerance_cents`, `tolerance_basis_points` | inteiros | cópia dos parâmetros usados |
| `surcharge_cap_basis_points` | inteiro | cópia |
| `automatic_threshold`, `suggestion_threshold`, `supplier_threshold` | unsignedSmallInteger | de `config` |
| `lookback_months` | unsignedSmallInteger | de `config` |
| `excluded_codes` | json | lista de códigos vigentes no início |
| `totals` | json | opcional; totais por classificação, percentual automático e quantidade excluída por código |
| `started_at` | timestamp | obrigatório |
| `finished_at` | timestamp | opcional |
| `created_at`, `updated_at` | timestamp | |

- A execução vigente de uma sessão é a mais recente com situação `Completed`.
- A FK da sessão é `restrictOnDelete`; `DeleteSession` apaga as execuções de uma sessão nunca
  processada antes de apagá-la.
- Descartar apaga os filhos e muda a situação para `Discarded`; parâmetros e totais permanecem.

## reconciliation_skips

Lançamentos que ficaram fora da comparação em uma execução.

| Coluna | Tipo | Regras |
|--------|------|--------|
| `id` | bigint | PK |
| `reconciliation_run_id` | FK | `cascadeOnDelete` |
| `payment_entry_id` | FK | opcional; `cascadeOnDelete` |
| `authorization_entry_id` | FK | opcional; `cascadeOnDelete` |
| `reason` | string | `SkipReason` |
| `operation_code` | string(20) | preenchido em `ExcludedCode` |
| `original_session_id` | FK `reconciliation_sessions` | preenchido em `DuplicateOfOtherPeriod` |

- Exatamente um entre `payment_entry_id` e `authorization_entry_id` é preenchido.
- Único: (`reconciliation_run_id`, `payment_entry_id`) e (`reconciliation_run_id`,
  `authorization_entry_id`). Índice em (`reconciliation_run_id`, `operation_code`).

## reconciliation_links

Ligação entre uma autorização e um pagamento.

| Coluna | Tipo | Regras |
|--------|------|--------|
| `id` | bigint | PK |
| `authorization_entry_id` | FK | `restrictOnDelete`; indexado |
| `payment_entry_id` | FK | `restrictOnDelete`; **único** (FR-018) |
| `reconciliation_run_id` | FK | execução vigente da sessão do pagamento; `restrictOnDelete` |
| `origin` | string | `LinkOrigin` |
| `is_installment` | boolean | padrão falso |
| `engine_classification` | string | opcional; `MatchClassification` |
| `score`, `supplier_score`, `amount_score` | unsignedSmallInteger | opcionais; 0 a 100 |
| `difference_type` | string | `DifferenceType` |
| `excess_cents` | unsignedBigInteger | padrão 0; quanto o pagamento passou do saldo |
| `treatment` | string | opcional; `DifferenceTreatment` |
| `discount_cents` | unsignedBigInteger | padrão 0 |
| `tolerance_writeoff_cents` | unsignedBigInteger | padrão 0; resto absorvido pela tolerância |
| `surcharge_cap_basis_points` | unsignedInteger | opcional; teto vigente na decisão de acréscimo |
| `justification_category` | string | opcional; `JustificationCategory`; obrigatória em `Discount` e `AcceptedSurcharge`; `Rounding` quando há resto absorvido pela tolerância |
| `justification` | string(500) | obrigatória em `Discount` e `AcceptedSurcharge` |
| `paid_before_authorization` | boolean | padrão falso; indexado |
| `card_mismatch` | boolean | padrão falso; cartões identificados e diferentes |
| `decided_by` | FK `users` | opcional; quem confirmou, vinculou ou tratou a diferença |
| `decided_at` | timestamp | opcional |
| `created_at`, `updated_at` | timestamp | |

Regras:

- `origin = Automatic` e `decided_by` nulo: vínculo sem decisão humana, apagado no descarte.
- `decided_by` preenchido: decisão manual; bloqueia a reabertura da sessão do pagamento.
- `Overpayment` é o alerta permanente de pagamento a maior, com `excess_cents`.
- `Discount`: `discount_cents` é o saldo restante no momento; nunca um valor informado.
- `AcceptedSurcharge`: só quando `excess_cents ≤ teto × valor autorizado`.

## authorization_states

Situação derivada da autorização. Recalculada a cada mudança de vínculo; nunca editada por tela.

| Coluna | Tipo | Regras |
|--------|------|--------|
| `authorization_entry_id` | FK | PK; `cascadeOnDelete` |
| `links_count` | unsignedInteger | |
| `paid_cents` | unsignedBigInteger | Σ dos pagamentos vinculados |
| `discount_cents` | unsignedBigInteger | Σ dos descontos |
| `writeoff_cents` | unsignedBigInteger | Σ absorvido pela tolerância |
| `balance_cents` | unsignedBigInteger | `max(0, autorizado − pago − desconto − absorvido)` |
| `status` | string | `AuthorizationStatus`; indexado |
| `updated_at` | timestamp | |

- Sem linha: a autorização está `Open` com saldo igual ao valor autorizado.
- `links_count = 0` remove a linha.

Transições:

```text
Open ──vínculo com saldo restante──▶ Partial ──vínculo que zera, ou desconto──▶ Reconciled
Open ──vínculo exato, excedente ou com desconto──▶ Reconciled
Reconciled / Partial ──desvínculo──▶ Partial ou Open (recalculado)
```

## reconciliation_suggestions

Candidatos que o motor não vinculou sozinho.

| Coluna | Tipo | Regras |
|--------|------|--------|
| `id` | bigint | PK |
| `reconciliation_run_id` | FK | `cascadeOnDelete`; indexado |
| `authorization_entry_id` | FK | `cascadeOnDelete` |
| `payment_entry_id` | FK | `cascadeOnDelete`; indexado |
| `classification` | string | `Doubtful`, `Partial` ou `Excess` |
| `score`, `supplier_score`, `amount_score` | unsignedSmallInteger | 0 a 100 |
| `difference_cents` | bigInteger | pagamento menos saldo, com sinal |
| `position` | unsignedSmallInteger | 1 é o melhor candidato da autorização |
| `is_tie` | boolean | empate que impediu o vínculo automático |
| `paid_before_authorization` | boolean | padrão falso |
| `card_mismatch` | boolean | padrão falso |
| `status` | string | `SuggestionStatus`; indexado |
| `decided_by` | FK `users` | opcional |
| `decided_at` | timestamp | opcional |
| `created_at`, `updated_at` | timestamp | |

- Único: (`reconciliation_run_id`, `authorization_entry_id`, `payment_entry_id`).
- Índice: (`authorization_entry_id`, `status`, `position`).

Transições: `Pending → Confirmed` (vira vínculo), `Pending → Rejected` (vira bloqueio de par),
`Pending → Superseded` (o pagamento foi vinculado em outro par, ou a autorização foi quitada).

## reconciliation_pair_blocks

Pares que o motor não volta a sugerir nem a vincular sozinho. Sobrevive a novas execuções.

| Coluna | Tipo | Regras |
|--------|------|--------|
| `id` | bigint | PK |
| `authorization_identity_key` | char(64) | chave de identidade da autorização |
| `payment_unit` | string | unidade do pagamento |
| `payment_identity_key` | string(800) | chave de identidade do pagamento |
| `reason` | string | `PairBlockReason` |
| `created_by` | FK `users` | `restrictOnDelete` |
| `created_at` | timestamp | |

- Único: (`authorization_identity_key`, `payment_unit`, `payment_identity_key`).
- O vínculo manual do mesmo par continua permitido; o bloqueio vale só para o motor.

## payment_entries (alteração)

| Coluna | Mudança |
|--------|---------|
| `card` | nova; string(4) opcional, indexada; os quatro dígitos lidos da espécie |

- Preenchida na importação (`PaymentsLayout`) e, para os pagamentos já existentes, pela migração,
  em lotes, com o mesmo `CardNumberExtractor`.
- Nula quando o pagamento não vem de fatura de cartão.

## authorization_entries (alteração)

| Coluna | Mudança |
|--------|---------|
| `import_file_id` | passa a aceitar nulo |
| `created_by` | nova; FK `users` opcional; preenchida na autorização criada na conciliação |
| `source_payment_entry_id` | nova; FK `payment_entries` opcional, `nullOnDelete` |

- `import_file_id` nulo identifica a autorização "Criada na conciliação".
- A migração repete todos os atributos da coluna alterada.
- Novas colunas calculadas não são criadas: as parcelas previstas são interpretadas de
  `payment_condition` na leitura.

## reconciliation_pending_items (visão)

Une, em SQL padrão, o que a aba Pendências mostra. Somente leitura.

| Coluna | Origem |
|--------|--------|
| `id` | texto: `s-{id}`, `a-{id}` ou `p-{id}` |
| `reconciliation_session_id` | sessão em cuja tela o item aparece |
| `kind` | `PendingItemKind` |
| `classification` | `doubtful`, `partial`, `excess`, `unmatched_authorization`, `open_balance`, `unmatched_payment` |
| `suggestion_id`, `authorization_entry_id`, `payment_entry_id` | conforme o tipo |
| `authorization_supplier`, `payment_supplier` | para busca |
| `score`, `difference_cents` | das sugestões |
| `paid_before_authorization`, `card_mismatch` | das sugestões |
| `authorization_card`, `payment_card` | para o filtro por cartão |

Regras de composição:

- **Sugestão**: `Pending`, de execução `Completed`, cuja posição é a menor entre as pendentes da
  mesma autorização (empates aparecem juntos). A sessão é a da execução.
- **Autorização sem pagamento**: autorização ativa de sessão processada, sem linha em
  `authorization_states`, sem sugestão pendente e sem linha em `reconciliation_skips`.
- **Saldo em aberto**: autorização ativa de sessão processada com situação `Partial` em
  `authorization_states` e sem sugestão pendente.
- **Pagamento sem autorização**: pagamento ativo de sessão processada, sem vínculo, sem sugestão
  pendente e sem linha em `reconciliation_skips`.

## Registros de auditoria

| Ação | Entidade | `before` | `after` |
|------|----------|----------|---------|
| `ReconciliationCompleted` | execução | nulo | parâmetros e totais |
| `SuggestionConfirmed` | vínculo | sugestão (nota, classificação) | vínculo, tratamento, justificativa, saldo |
| `SuggestionRejected` | sugestão | sugestão pendente | situação rejeitada |
| `ManualLinkCreated` | vínculo | saldo anterior | vínculo, tratamento, justificativa, saldo |
| `LinkRemoved` | vínculo | vínculo inteiro: origem, nota e classificação do motor, quem vinculou, tratamento | saldo e situação recalculados |
| `AuthorizationClosedWithDiscount` | vínculo | saldo anterior | desconto, justificativa |
| `AuthorizationCreatedInReconciliation` | autorização | nulo | autorização criada e pagamento de origem |
| `ReconciliationSettingsChanged` | configuração | valores anteriores | valores novos |

O mapa de tipos polimórficos ganha `reconciliation_run`, `reconciliation_link`,
`reconciliation_suggestion`, `authorization_entry` e `reconciliation_settings`.

## Objetos sem tabela

- **`EngineParameters`**: tolerância, limites, janela e teto de uma execução; calcula a
  tolerância efetiva para um saldo.
- **`AuthorizationCandidate`** e **`PaymentCandidate`**: o que o `Matcher` recebe: id, sessão,
  fornecedor normalizado, valor ou saldo, data, forma de pagamento, cartão, parcelas previstas, valores de
  parcelas já vinculadas, chave de identidade.
- **`PairScore`**: nota, eixos, diferença e classificação de um par.
- **`MatchResult`**: vínculos automáticos, sugestões e pulados produzidos pelo `Matcher`.
