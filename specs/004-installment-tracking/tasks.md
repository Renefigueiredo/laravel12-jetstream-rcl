# Tasks: Gestão de Parcelamentos e Pagamentos Parciais (Módulo 3)

**Input**: Design documents from `/specs/004-installment-tracking/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: obrigatórios. A constituição (Princípio III) exige o teste antes da implementação.
Cada teste é escrito primeiro e precisa falhar antes de o código existir.

**Organization**: tarefas agrupadas por história de usuário, na ordem de prioridade da spec.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode ser feita em paralelo (arquivos diferentes, sem depender de tarefa em aberto)
- **[Story]**: a história da spec a que a tarefa pertence (US1 a US6)
- Criar arquivos com `php artisan make:* --no-interaction`; testes com
  `php artisan make:test --phpunit`; rodar `vendor/bin/pint --dirty --format agent` ao terminar
  cada fase.

---

## Phase 1: Setup

**Purpose**: o que todas as histórias usam e não tem regra.

- [X] T001 [P] Criar `app/Enums/InstallmentStatus.php` (`Paid`, `Open`, `Overdue`) e `app/Enums/StatementLineKind.php` (`Payment`, `Discount`, `ToleranceWriteoff`, `AcceptedSurcharge`, `Overpayment`), cada um com `label()` lendo `lang/pt_BR/conciliation.php`, como os enums existentes
- [X] T002 [P] Acrescentar a `lang/pt_BR/conciliation.php` o grupo `dashboard` (título, resumo, alertas, abas, colunas, extrato, plano, previsão, mensagens e erros) e os rótulos dos dois enums de T001; acrescentar chaves à medida que as fases pedirem
- [X] T003 [P] Acrescentar a `tests/Concerns/BuildsReconciliations.php` os auxiliares que os testes deste módulo repetem: `processedSession(string $period)`, `linkedPayment(AuthorizationEntry, int $cents, array $attributes = [])` (cria o pagamento e vincula por `linkPair`) e `administrator()`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: a consulta que define quais autorizações o painel enxerga. Lista, totais, extrato,
plano e previsão partem dela.

**⚠️ CRITICAL**: nenhuma história começa antes desta fase.

- [X] T004 Escrever `tests/Feature/Dashboard/AuthorizationPanelTest.php`, parte do escopo (research R3): entram autorizações de arquivo ativo e as criadas na conciliação, de sessões Processadas; não entram as de sessão aberta, em processamento ou reaberta, as de arquivo substituído e as puladas como duplicadas de outro período; autorização sem vínculo conta como Aberta com saldo igual ao autorizado
- [X] T005 Criar `app/Services/Reconciliation/AuthorizationPanel.php` com `query(): Builder` (junção com `import_files`, `reconciliation_sessions` e `authorization_states`; sem linha em `reconciliation_skips`), até T004 passar
- [X] T006 Criar a migration `add_panel_indexes_to_reconciliation_tables` com índices em `status` e em `balance_cents`, se ainda não existirem (conferir com a ferramenta `database-schema`)

**Checkpoint**: o painel sabe o que mostrar.

---

## Phase 3: User Story 1 - Ver a situação de todas as autorizações em um só lugar (Priority: P1) 🎯 MVP

**Goal**: a página "Dashboard" vira o painel, com resumo e as abas "Em aberto / Parcial" e
"Conciliadas", somando todas as sessões processadas.

**Independent Test**: com duas sessões processadas e uma autorização de R$ 900,00 de julho que
recebeu R$ 300,00 em julho e R$ 300,00 em agosto, abrir o painel; ela aparece uma vez em "Em
aberto / Parcial", com pago de R$ 600,00 e saldo de R$ 300,00, e os totais somam as duas sessões.

### Tests for User Story 1 ⚠️

- [X] T007 [P] [US1] Completar `tests/Feature/Dashboard/AuthorizationPanelTest.php` com os totais: autorizado, pago, saldo, descontos, acréscimos aceitos, pagamentos a maior e quantidades por situação, somando sessões; os totais acompanham um filtro aplicado à consulta; banco sem sessão processada dá zeros
- [X] T008 [P] [US1] Escrever `tests/Feature/Dashboard/DashboardPageTest.php`: os cenários 1 a 7 da história; `/dashboard` exige login e é o destino depois do login; Operador e Administrador acessam; aba inválida cai em `abertas`; aba, busca e filtros ficam na URL; autorização paga em duas sessões aparece em uma linha; sem sessão processada aparece a orientação com link para "Sessões"; filtros por situação, sessão de origem e cartão; busca por fornecedor; a faixa de totais da aba (quantidade, autorizado, pago, saldo) acompanha a busca e os filtros, e o resumo do alto não muda (FR-005a)

### Implementation for User Story 1

- [X] T009 [US1] Acrescentar a `app/Services/Reconciliation/AuthorizationPanel.php` o método `totals(?Builder $filtered = null): array`, por agregação no banco (`SUM` e `COUNT` com `CASE`), até T007 passar
- [X] T010 [US1] Criar `app/Livewire/Dashboard/Show.php` e `resources/views/livewire/dashboard/show.blade.php`: resumo, área de alertas alimentada por `AuthorizationPanel::alerts()` (criado aqui devolvendo zeros; T027 e T045 preenchem), abas com `#[Url(as: 'aba')]`, estado sem sessão processada, e atualização no evento `reconciliation-changed`; seguir o padrão de `app/Livewire/Reconciliation/Show.php`
- [X] T011 [US1] Em `routes/web.php`, apontar `/dashboard` (nome `dashboard`) para `App\Livewire\Dashboard\Show` com `Route::livewire`; remover `resources/views/dashboard.blade.php`; ajustar os testes existentes que esperavam a tela de boas-vindas
- [X] T012 [US1] Criar `app/Livewire/Dashboard/AuthorizationsTable.php` (tabela do Filament, propriedade travada `scope` = `open` ou `reconciled`): linha com fornecedor, pedido, situação, autorizado, pago, saldo, quantidade de pagamentos, condição com parcelas previstas, cartão, sessão de origem e marca "Criada na conciliação"; busca por fornecedor; filtros de `contracts/routes.md` menos os de parcela atrasada; ordenação padrão; 25 por página; ações de detalhe do trait `ShowsEntryDetails`; faixa de totais da aba calculada por `AuthorizationPanel::totals()` com a consulta já filtrada da tabela, em view própria `resources/views/livewire/dashboard/authorizations-table.blade.php`, até T008 passar
- [X] T013 [US1] Em `resources/views/livewire/dashboard/show.blade.php`, renderizar `AuthorizationsTable` nas abas `abertas` e `conciliadas`, com chave distinta por aba

**Checkpoint**: MVP. O painel entre sessões funciona sozinho.

---

## Phase 4: User Story 2 - Abrir o extrato de uma autorização na própria lista (Priority: P1)

**Goal**: clicar na autorização abre, na própria lista, os pagamentos abatidos com o saldo depois
de cada um.

**Independent Test**: para a autorização do teste anterior, abrir a linha e ver duas linhas de
extrato em ordem de data, com saldos de R$ 600,00 e R$ 300,00; fechar.

### Tests for User Story 2 ⚠️

- [X] T014 [P] [US2] Escrever `tests/Unit/Reconciliation/AuthorizationStatementTest.php` com as tabelas "Linhas do extrato" de `contracts/statement-rules.md`: cada tipo de vínculo e as linhas que gera, ordem por data e identificador, saldo nunca negativo, autorização sem vínculo, e o saldo das linhas seguintes quando um pagamento do meio some
- [X] T015 [P] [US2] Escrever `tests/Feature/Dashboard/StatementTest.php`: os cenários 1 a 6 da história; o extrato mostra data, fornecedor do pagamento, valor, unidade, sessão, forma do vínculo, quem decidiu e saldo depois; desconto, baixa por tolerância, acréscimo aceito e pagamento a maior aparecem com valor e justificativa; autorização sem pagamento mostra a mensagem; para um cenário com todos os tipos de vínculo, o saldo da última linha é igual ao `balance_cents` guardado de cada autorização (SC-002); a lista de 25 linhas não faz consulta por linha

### Implementation for User Story 2

- [X] T016 [P] [US2] Criar em `app/Services/Reconciliation/Matching/` os objetos `StatementEntry` (entrada: dados de um vínculo) e `StatementLine` (saída, conforme `data-model.md`) e a classe pura `AuthorizationStatement` com `lines(int $authorizedCents, array $links): array`, até T014 passar
- [X] T017 [US2] Criar `resources/views/livewire/dashboard/partials/statement.blade.php`: tabela do extrato com cabeçalhos, blocos de pagamentos e abatimentos, mensagem de vazio; valores com `App\Support\Money`
- [X] T018 [US2] Em `app/Livewire/Dashboard/AuthorizationsTable.php`, passar as colunas para `Split` e acrescentar o `Panel` recolhível com a view do extrato (research R4); carregar `links.payment.session`, `links.decider` e `state` junto com a página; montar as linhas com `AuthorizationStatement`; acrescentar ao extrato as ações de ver os dados de cada pagamento, até T015 passar
- [X] T019 [US2] Conferir com `search-docs` e no navegador que o botão de abrir o painel recolhível funciona pelo teclado e expõe o estado aberto/fechado (FR-008); se não atender, trocar `AuthorizationsTable` pela lista própria descrita em research R4 e registrar a decisão em `specs/004-installment-tracking/research.md`

**Checkpoint**: o histórico de qualquer autorização está a dois cliques.

---

## Phase 5: User Story 3 - Desfazer um pagamento a partir do extrato (Priority: P2)

**Goal**: cada pagamento do extrato tem "Desfazer", com as regras do desvínculo já existentes.

**Independent Test**: no extrato de uma autorização Conciliada por três parcelas, desfazer a
segunda; ela volta a Parcial, o pagamento vai para as divergências e a auditoria registra quem
desfez.

### Tests for User Story 3 ⚠️

- [X] T020 [US3] Escrever `tests/Feature/Dashboard/UndoFromStatementTest.php`: os cenários 1 a 6 da história; a autorização muda de aba quando o saldo deixa de ser zero; o registro de auditoria `LinkRemoved` traz o autor e os dados do vínculo; vínculo já desfeito por outra pessoa é recusado no diálogo de recusa e o extrato se atualiza; sessão do pagamento não processada é recusada; autorização criada na conciliação some ao perder o único pagamento; desfazer e vincular na mesma autorização, em sequência imediata, deixam o saldo igual ao que os vínculos produzem (SC-009; a trava em si só se prova em PostgreSQL, pendência já registrada)

### Implementation for User Story 3

- [X] T021 [US3] Em `app/Livewire/Dashboard/AuthorizationsTable.php`, criar a ação de componente `undoLink` (confirmação, argumento com o id do vínculo) que chama `App\Actions\Conciliation\RemoveLink`, usa `ShowsRefusal` para as recusas e dispara `reconciliation-changed`; acrescentar o botão "Desfazer" a cada pagamento em `resources/views/livewire/dashboard/partials/statement.blade.php`, até T020 passar

**Checkpoint**: corrigir um vínculo não exige achar a sessão.

---

## Phase 6: User Story 4 - Tratar as divergências de todas as sessões (Priority: P2)

**Goal**: a aba "Divergências" reúne pagamentos sem autorização e Excedentes de todas as sessões,
com as ações de vincular, confirmar, rejeitar e criar autorização.

**Independent Test**: com um pagamento "Sem autorização" em julho e um "Excedente" em agosto, ver
os dois na aba; vincular o primeiro a uma autorização em aberto; ele sai da aba, o saldo muda e a
auditoria registra o operador.

### Tests for User Story 4 ⚠️

- [X] T022 [P] [US4] Escrever `tests/Feature/Reconciliation/AuthorizationAvailabilityTest.php`: para um pagamento de agosto, estão disponíveis as autorizações com saldo de agosto, as de sessões processadas dentro da janela e as mais antigas que já têm pagamento; não estão as quitadas, as de sessão aberta, as de período posterior, as de arquivo substituído e as antigas sem pagamento; `assertCanReceive` recusa com as mesmas mensagens de hoje
- [X] T023 [P] [US4] Escrever `tests/Feature/Dashboard/DivergencesTest.php`: os cenários 1 a 7 da história; Dúbios e Parciais não aparecem (FR-016a); filtros por tipo, unidade, sessão, código de operação e cartão; vincular a autorização de sessão anterior; confirmar Excedente como "Acréscimo aceito" e como "Pagamento a maior"; rejeitar Excedente faz o pagamento passar a "Sem autorização"; "Criar autorização" só para o Administrador; cada ação grava auditoria com o autor

### Implementation for User Story 4

- [X] T024 [US4] Criar `app/Services/Reconciliation/AuthorizationAvailability.php` com `assertCanReceive()` e `forPayment()`, movendo a regra de `LinkManually::assertAuthorizationCanReceive`; fazer `app/Actions/Conciliation/LinkManually.php` usá-la, até T022 passar e `tests/Feature/Reconciliation/ManualLinkTest.php` continuar passando
- [X] T025 [US4] Em `app/Livewire/Reconciliation/Concerns/DecidesPendingItems.php`, obter a sessão do próprio registro (a do pagamento) em `decide()`, `paymentOptions()`, `selectionType()` e `pairType()`, e montar `authorizationOptions()` com `AuthorizationAvailability::forPayment()`; os testes de `tests/Feature/Reconciliation` continuam passando, e um caso novo em `CardReconciliationTest` confere que a lista de escolha traz a autorização em aberto de sessão anterior
- [X] T026 [US4] Criar `app/Livewire/Dashboard/DivergencesTable.php`: `PendingItem` de todas as sessões com `kind` `unmatched_payment` ou classificação `excess`; colunas, busca e filtros de `contracts/routes.md`; ações `linkToAuthorizationAction`, `confirmAction`, `rejectAction`, `createAuthorizationAction` e as de detalhe; renderizar na aba `divergencias` de `resources/views/livewire/dashboard/show.blade.php`, até T023 passar
- [X] T027 [US4] Preencher em `AuthorizationPanel::alerts()` (`app/Services/Reconciliation/AuthorizationPanel.php`) a quantidade de divergências e de autorizações com pagamento a maior, e mostrá-las em `resources/views/livewire/dashboard/show.blade.php` como atalhos para a aba já filtrada; acrescentar o filtro "com pagamento a maior" a `AuthorizationsTable`; cobrir em `DashboardPageTest`

**Checkpoint**: nenhuma pendência de pagamento depende de lembrar o mês.

---

## Phase 7: User Story 5 - Reconhecer parcelas de valores diferentes (Priority: P2)

**Goal**: o Operador informa o plano de parcelas e o motor vincula sozinho cada pagamento que
bate com uma parcela em aberto, no máximo um por parcela.

**Independent Test**: informar em uma autorização de R$ 1.000,00 o plano 400,00 + 300,00 +
300,00; processar três sessões com esses pagamentos; os três entram sozinhos e a autorização fica
Conciliada.

### Tests for User Story 5 ⚠️

- [X] T028 [P] [US5] Escrever `tests/Unit/Reconciliation/InstallmentScheduleTest.php`, partes "Parcelas previstas" e "Ocupação das parcelas" de `contracts/statement-rules.md`: origem pelo plano, pela condição ou nenhuma; divisão com centavo de resto na última; ocupação em ordem de data e identificador; pagamento fora do plano; tolerância na fronteira (300,40 ocupa, 300,51 não); `openAmounts()` com repetição
- [X] T029 [P] [US5] Escrever `tests/Unit/Reconciliation/MatcherInstallmentPlanTest.php` com a tabela "O motor com plano informado", usando `tests/Concerns/BuildsMatcherCandidates.php`: uma parcela por pagamento, plano prevalece sobre a condição e sobre o valor já vinculado, vínculo exato pelo total, cartão diferente, disputa entre duas autorizações, e nenhuma soma acima do autorizado
- [X] T030 [P] [US5] Escrever `tests/Feature/Dashboard/InstallmentPlanTest.php`: os cenários 1, 2, 7 e 8 da história; salvar, alterar e remover gravam `InstallmentPlanSaved` e `InstallmentPlanRemoved` com as parcelas anteriores e novas; recusas de `contracts/application-interfaces.md` (soma diferente com a diferença na mensagem, mais de 120, valor não positivo, menos parcelas do que a quantidade de pagamentos já vinculados, autorização sem saldo); o plano é reencontrado pela chave de identidade depois de a planilha ser substituída; Operador e Administrador podem; as ações na tela (formulário, erros de validação, remoção)
- [X] T031 [P] [US5] Escrever `tests/Feature/Dashboard/InstallmentPlanEngineTest.php`: os cenários 3 a 6 e 9 da história em três sessões; segunda cobrança de parcela já paga não vincula; valor fora do plano vira Parcial com o aviso; alterar o plano não mexe nos vínculos e vale na execução seguinte; parcela desvinculada não volta sozinha (SC-006, SC-007)

### Implementation for User Story 5

- [X] T032 [P] [US5] Criar as migrations `create_installment_plans_table` e `create_installment_plan_items_table` conforme `data-model.md`; criar `app/Models/InstallmentPlan.php` e `app/Models/InstallmentPlanItem.php` com relações, `casts()` e factories; acrescentar `plan()` a `app/Models/AuthorizationEntry.php` (pela chave de identidade) e `installment_plan` ao mapa polimórfico em `app/Providers/AppServiceProvider.php`
- [X] T033 [P] [US5] Criar em `app/Services/Reconciliation/Matching/` o objeto `ScheduledInstallment` e a classe pura `InstallmentSchedule` com `for()` (sem mês esperado nesta fase: `expectedMonth` nulo e situação `Paid` ou `Open`) e `openAmounts()`, até T028 passar
- [X] T034 [US5] Acrescentar `planAmounts` a `app/Services/Reconciliation/Matching/AuthorizationCandidate.php` e, em `app/Services/Reconciliation/Matching/Matcher.php`, fazer a etapa de parcelas usar só as parcelas do plano quando ele existe, consumindo uma por vínculo (research R8), até T029 passar e `MatcherInstallmentsTest` continuar passando
- [X] T035 [US5] Acrescentar `InstallmentPlanSaved` e `InstallmentPlanRemoved` a `app/Enums/AuditAction.php`; criar `app/Actions/Conciliation/SaveInstallmentPlan.php` e `app/Actions/Conciliation/RemoveInstallmentPlan.php` (trava na autorização, validações, auditoria na mesma transação), até a parte de ações de T030 passar
- [X] T036 [US5] Em `app/Services/Reconciliation/CandidateLoader.php`, ler os planos das autorizações carregadas e preencher `planAmounts` com `InstallmentSchedule::openAmounts()`, até T031 passar
- [X] T037 [US5] Em `app/Livewire/Dashboard/AuthorizationsTable.php`, criar as ações "Informar plano de parcelas" (modal com `Repeater` de valor e mês opcional, total e diferença para o autorizado visíveis; view `resources/views/livewire/dashboard/partials/installment-plan-fields.blade.php` se o `Repeater` não bastar) e "Remover plano"; mostrar no extrato a parcela que cada pagamento ocupa e o aviso "fora do plano"; em `app/Livewire/Reconciliation/PendingTable.php`, fazer o aviso "Fora da parcela prevista" considerar o plano; até a parte de tela de T030 passar

**Checkpoint**: parcelas desiguais deixam de exigir um clique por mês.

---

## Phase 8: User Story 6 - Saber quais parcelas faltam e quais estão atrasadas (Priority: P3)

**Goal**: o extrato mostra as parcelas que faltam com o mês esperado, e o painel avisa das
atrasadas.

**Independent Test**: autorização "30/60/90 dias" de R$ 900,00 de julho, uma parcela paga em
agosto; processar setembro sem pagamento; o extrato mostra duas parcelas faltando, a de setembro
atrasada, e o painel conta a autorização no alerta.

### Tests for User Story 6 ⚠️

- [X] T038 [P] [US6] Acrescentar a `tests/Unit/Reconciliation/PaymentConditionParserTest.php` a tabela "Condição de pagamento → prazos em dias" para `termDays()`
- [X] T039 [P] [US6] Acrescentar a `tests/Unit/Reconciliation/InstallmentScheduleTest.php` as tabelas "Mês esperado" e "Atraso": meses pelo plano, pelos prazos em dias e um por mês; atraso pelo mês processado mais recente, inclusive com mês pulado; parcela paga antes ou depois do esperado conta como paga; sem mês não atrasa
- [X] T040 [P] [US6] Escrever `tests/Feature/Dashboard/ForecastTest.php`: os cenários 1 a 7 da história; a previsão é refeita ao vincular, ao desvincular, ao salvar e remover plano, ao fim da execução e na reabertura; autorização Conciliada tem zero atrasadas; autorização sem parcelas previstas não tem linha de previsão; o alerta conta todas as autorizações com parcela atrasada (SC-010) e leva à lista filtrada; `conciliation:refresh-forecasts` refaz a tabela apagada

### Implementation for User Story 6

- [X] T041 [P] [US6] Acrescentar `termDays(?string $condition): ?array` a `app/Services/Reconciliation/Matching/PaymentConditionParser.php`, até T038 passar
- [X] T042 [US6] Completar `app/Services/Reconciliation/Matching/InstallmentSchedule.php` com o mês esperado de cada parcela e a situação `Overdue` (research R9), até T039 passar
- [X] T043 [P] [US6] Criar a migration `create_authorization_forecasts_table` e `app/Models/AuthorizationForecast.php` conforme `data-model.md`; acrescentar `forecast()` a `app/Models/AuthorizationEntry.php`
- [X] T044 [US6] Criar `app/Services/Reconciliation/InstallmentForecaster.php` com `refresh()` e `refreshAll()`; chamá-lo em `app/Services/Reconciliation/ReconciliationLinker.php` (vincular e desvincular), em `SaveInstallmentPlan` e `RemoveInstallmentPlan`, em `app/Services/Reconciliation/MatchResultWriter.php` (fim da execução) e em `app/Actions/Conciliation/ReopenSession.php`; criar `app/Console/Commands/RefreshForecasts.php` (`conciliation:refresh-forecasts`), até a parte de dados de T040 passar
- [X] T045 [US6] Mostrar no extrato (`resources/views/livewire/dashboard/partials/statement.blade.php`) o bloco "Parcelas que faltam" com posição, valor, mês esperado e situação; em `app/Livewire/Dashboard/AuthorizationsTable.php`, o aviso de parcela atrasada na linha, o filtro "com parcela atrasada" e a ordenação com as atrasadas primeiro; em `AuthorizationPanel::alerts()` e `resources/views/livewire/dashboard/show.blade.php`, o alerta de parcelas atrasadas com o atalho, até T040 passar

**Checkpoint**: todas as histórias funcionam de forma independente.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: desempenho, mensagens, conferência final e documentação.

- [X] T046 [P] Trocar o texto `conciliation.sessions.reopen.blocked` em `lang/pt_BR/conciliation.php` para dizer onde desfazer os vínculos (aba Conciliados da sessão ou extrato da autorização no Dashboard) e ajustar `tests/Feature/Conciliation/ReopenSessionTest.php` (FR-034)
- [X] T047 [P] Escrever `tests/Feature/Dashboard/DashboardPerformanceTest.php`: 50.000 autorizações e 60.000 vínculos inseridos em lote; abrir o painel com a primeira página de "Em aberto / Parcial" em menos de 3 s e montar o extrato de uma autorização em menos de 1 s (SC-008); ajustar consultas e índices se não passar
- [X] T048 [P] Atualizar `specs/003-reconciliation-engine/contracts/matching-rules.md` (parcelas do plano) e `specs/003-reconciliation-engine/contracts/routes.md` (lista de escolha de autorização com sessões anteriores)
- [X] T049 [P] Escrever `tests/Feature/Dashboard/BalanceIsDerivedTest.php`: depois de cada ação do painel (desfazer, vincular, confirmar, rejeitar, criar autorização, salvar e remover plano), o `balance_cents` de toda autorização é igual ao recalculado pelos vínculos, e toda ação que mudou um saldo deixou registro de auditoria (FR-011, FR-032, SC-005)
- [X] T050 Rodar `php artisan migrate` e `php artisan conciliation:refresh-forecasts` no banco local e conferir, na sessão de julho/2026, que os totais do painel batem com o resumo da sessão
- [X] T051 Rodar a suíte inteira (`php artisan test --compact`) e `vendor/bin/pint --dirty --format agent`, e corrigir o que falhar
- [ ] T052 Executar o roteiro manual de `specs/004-installment-tracking/quickstart.md` no navegador, com `npm run build`, incluindo o passo de acessibilidade
- [X] T053 Registrar em `specs/004-installment-tracking/research.md` as decisões tomadas durante a implementação e o resultado do roteiro manual

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências.
- **Foundational (Phase 2)**: depende do Setup; bloqueia todas as histórias.
- **US1 (Phase 3)**: depende da Phase 2.
- **US2 (Phase 4)**: depende da US1 (a lista onde a linha se abre).
- **US3 (Phase 5)**: depende da US2 (o extrato onde fica o "Desfazer").
- **US4 (Phase 6)**: depende da US1 (a página e as abas). Independe de US2 e US3.
- **US5 (Phase 7)**: depende da US2 (o plano aparece no extrato). O motor e as ações (T032 a
  T036) independem das telas e podem começar depois da Phase 2.
- **US6 (Phase 8)**: depende da US5 (`InstallmentSchedule` e o plano).
- **Polish (Phase 9)**: depois das histórias que forem entregues.

### Within Each User Story

- Testes escritos e falhando antes da implementação.
- Classes puras antes dos serviços; serviços antes das ações; ações antes das telas.

### Arquivos compartilhados (não paralelizar entre si)

- `app/Livewire/Dashboard/AuthorizationsTable.php`: T012, T018, T021, T027, T037, T045.
- `resources/views/livewire/dashboard/partials/statement.blade.php`: T017, T021, T037, T045.
- `resources/views/livewire/dashboard/show.blade.php`: T010, T013, T026, T027, T045.
- `app/Services/Reconciliation/AuthorizationPanel.php`: T005, T009, T010, T027, T045.
- `app/Services/Reconciliation/Matching/InstallmentSchedule.php`: T033, T042.
- `tests/Unit/Reconciliation/InstallmentScheduleTest.php`: T028, T039.
- `app/Models/AuthorizationEntry.php`: T032, T043.
- `lang/pt_BR/conciliation.php`: T002 e todas as fases de tela.

### Parallel Opportunities

- Setup: T001, T002 e T003.
- US1: os dois testes (T007, T008).
- US2: os dois testes (T014, T015); T016 com T017.
- US4: os dois testes (T022, T023).
- US5: os quatro testes (T028 a T031); T032 com T033.
- US6: os três testes (T038 a T040); T041 com T043.
- Polish: T046, T047, T048 e T049.

---

## Parallel Example: User Story 5

```bash
# Testes primeiro, juntos:
Task: "T028 InstallmentScheduleTest (parcelas previstas e ocupação)"
Task: "T029 MatcherInstallmentPlanTest"
Task: "T030 InstallmentPlanTest"
Task: "T031 InstallmentPlanEngineTest"

# Depois, em paralelo, o que não divide arquivo:
Task: "T032 migrations e modelos do plano"
Task: "T033 InstallmentSchedule"
```

---

## Implementation Strategy

### MVP First (User Stories 1 e 2)

1. Phase 1 e Phase 2.
2. US1: o painel entre sessões. **Parar e validar** com os dados de julho/2026.
3. US2: o extrato na linha. Aqui se decide se o painel recolhível do Filament serve (T019).

### Incremental Delivery

1. US1 + US2 → ver tudo (MVP).
2. US3 → desfazer pelo extrato.
3. US4 → divergências de todas as sessões.
4. US5 → plano de parcelas.
5. US6 → previsão e atraso.
6. Polish.

Cada passo pode ser validado e commitado sozinho.

---

## Notes

- Nenhuma tarefa cria regra de saldo. Se uma tarefa parecer precisar disso, ela está errada: o
  saldo vem de `authorization_states`, mantida pelo `ReconciliationLinker`.
- Dados reais não entram em fixtures; os testes usam fornecedores inventados.
- A previsão (`authorization_forecasts`) é um cache: todo teste que a lê precisa também provar
  que ela é refeita a partir dos vínculos e do plano.
