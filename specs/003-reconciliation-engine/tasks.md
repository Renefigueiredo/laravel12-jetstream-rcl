---

description: "Task list for Módulo 2 - Motor de Conciliação e Tratamento de Divergências"
---

# Tasks: Motor de Conciliação e Tratamento de Divergências (Módulo 2)

**Input**: Design documents from `/specs/003-reconciliation-engine/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Incluídos. A constituição (Princípio III, inegociável) exige teste escrito antes da
implementação, com casos que casam, que não casam e de fronteira para as regras de conciliação.
Em cada história, as tarefas de teste vêm primeiro e DEVEM falhar antes do código.

**Organization**: Tarefas agrupadas por história de usuário, para que cada uma seja implementada e
testada de forma independente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependência de tarefa incompleta)
- **[Story]**: história da spec a que a tarefa pertence (US1 a US8)
- Cada descrição traz o caminho exato do arquivo

## Convenções para todas as tarefas

- Criar migrações, modelos, factories, componentes e testes com `php artisan make:*
  --no-interaction` (`make:livewire --class`, `make:test --phpunit`). Enums são escritos direto em
  `app/Enums`.
- Ativar as skills `livewire-development` e `tailwindcss-development` ao mexer em componente ou
  Blade. Usar as APIs do Filament como em `app/Livewire/ExcludedCodes/Index.php`.
- Textos de tela em `lang/pt_BR/conciliation.php`; nenhum texto fixo em Blade ou PHP.
- Valores em centavos inteiros; percentuais em pontos-base; datas e horas em UTC; situações e
  classificações como Enum.
- O núcleo em `app/Services/Reconciliation/Matching` não usa banco, relógio, `config()` nem `__()`;
  recebe tudo por parâmetro.
- Toda criação ou remoção de vínculo passa por `ReconciliationLinker`, dentro de transação.
- Regras de negócio em Services e Actions; componente só delega. Recusas usam
  `ActionRefusedException`. Cada ação grava auditoria com `AuditRecorder` na mesma transação.
- Rodar o teste afetado após cada tarefa e `vendor/bin/pint --dirty --format agent` ao fim de
  cada fase.
- Dados de teste vêm de factories e de planilhas geradas no teste. Os arquivos reais do ERP e do
  ELO NÃO entram no repositório.

## Decisões já tomadas

- **Sem pacote novo**: nenhuma tarefa altera `composer.json`.
- **Direção visual**: padrão do Jetstream, definido pelo responsável em 2026-10-08.
- **Mudança no Módulo 1**: `ReopenSession` passa a descartar o resultado (research R2).
- **Valores iniciais assumidos**: janela de 3 meses e teto de acréscimo de 10%.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: configuração e textos usados por todas as histórias.

- [ ] T001 Acrescentar a `config/conciliation.php` o bloco `engine` com `automatic_threshold` (90), `suggestion_threshold` (60), `supplier_threshold` (90), `lookback_months` (3), `suggestions_per_authorization` (5), `card_species_marker` (`FATURA CARTAO`) e `card_method_marker` (`CARTAO`), todos lidos de variáveis `CONCILIATION_ENGINE_*`; mudar o padrão de `engine_enabled` para `true`; atualizar `.env.example`; não alterar `phpunit.xml`: cada teste do Módulo 1 que precisa do motor desligado define `config(['conciliation.engine_enabled' => false])` no próprio teste
- [ ] T002 [P] Acrescentar a `lang/pt_BR/conciliation.php` as chaves `reconciliation.*` (títulos, abas, colunas, filtros, classificações, tratamentos da diferença, avisos "Pago antes da autorização" e "Cartão diferente", mensagens de sucesso e de recusa de `contracts/routes.md`), `permissions.configure_tolerance` e `audit.actions.*` dos oito casos novos de research R19

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Enums, tabelas, modelos, parâmetros, cartão do pagamento e os dois serviços por onde
passa todo vínculo.

**⚠️ CRITICAL**: nenhuma história começa antes desta fase.

- [ ] T003 [P] Criar os Enums `ReconciliationRunStatus` (`Running`, `Completed`, `Failed`, `Discarded`), `SkipReason` (`ExcludedCode`, `DuplicateOfOtherPeriod`) e `PairBlockReason` (`Rejected`, `Unlinked`) em `app/Enums/`, com valores em minúsculas com sublinhado e `label()`
- [ ] T004 [P] Criar os Enums `MatchClassification` (`Automatic`, `Installment`, `Doubtful`, `Partial`, `Excess`), `SuggestionStatus` (`Pending`, `Confirmed`, `Rejected`, `Superseded`) e `PendingItemKind` (`Suggestion`, `UnmatchedAuthorization`, `OpenBalance`, `UnmatchedPayment`) em `app/Enums/`, com `label()`
- [ ] T005 [P] Criar os Enums `LinkOrigin` (`Automatic`, `Manual`), `DifferenceType` (`Exact`, `Partial`, `Excess`), `DifferenceTreatment` (`StillOwed`, `Discount`, `AcceptedSurcharge`, `Overpayment`), `JustificationCategory` (`CommercialDiscount`, `InterestOrFine`, `Freight`, `PriceAdjustment`, `Rounding`, `Other`) e `AuthorizationStatus` (`Open`, `Partial`, `Reconciled`) em `app/Enums/`, com `label()`
- [ ] T006 Acrescentar `ConfigureTolerance = 'configure_tolerance'` a `app/Enums/UserPermission.php` e os casos `ReconciliationCompleted`, `SuggestionConfirmed`, `SuggestionRejected`, `ManualLinkCreated`, `LinkRemoved`, `AuthorizationClosedWithDiscount`, `AuthorizationCreatedInReconciliation` e `ReconciliationSettingsChanged` a `app/Enums/AuditAction.php`
- [ ] T007 Criar a migração `create_reconciliation_settings_table`: `tolerance_cents` unsignedInteger padrão 50, `tolerance_basis_points` unsignedInteger opcional, `surcharge_cap_basis_points` unsignedInteger padrão 1000, `updated_by` FK `users` opcional com `restrictOnDelete`, `timestamps`; a migração insere a linha inicial
- [ ] T008 Criar a migração `create_reconciliation_runs_table`, com as colunas de `data-model.md`: sessão e solicitante com `restrictOnDelete`, `status` indexado, cópia de todos os parâmetros, `excluded_codes` json, `totals` json opcional, `started_at` obrigatório, `finished_at` opcional, `timestamps`
- [ ] T009 Criar a migração `create_reconciliation_skips_table`: execução com `cascadeOnDelete`; `payment_entry_id` e `authorization_entry_id` opcionais com `cascadeOnDelete`; `reason`; `operation_code` string(20) opcional; `original_session_id` opcional; únicos em (execução, pagamento) e (execução, autorização); índice em (execução, `operation_code`)
- [ ] T010 Criar a migração `create_reconciliation_links_table`, com as colunas de `data-model.md`: autorização e execução com `restrictOnDelete`; `payment_entry_id` **único** com `restrictOnDelete`; `excess_cents`, `discount_cents` e `tolerance_writeoff_cents` unsignedBigInteger padrão 0; `justification_category` opcional; `justification` string(500) opcional; `paid_before_authorization` e `card_mismatch` booleanos padrão falso; `decided_by` e `decided_at` opcionais
- [ ] T011 Criar a migração `create_authorization_states_table`: `authorization_entry_id` como chave primária e FK com `cascadeOnDelete`; `links_count`, `paid_cents`, `discount_cents`, `writeoff_cents`, `balance_cents`; `status` indexado; `updated_at`
- [ ] T012 Criar a migração `create_reconciliation_suggestions_table`, com as colunas de `data-model.md`: FKs com `cascadeOnDelete`; `difference_cents` bigInteger com sinal; `position` unsignedSmallInteger; `is_tie`, `paid_before_authorization` e `card_mismatch` booleanos; único em (execução, autorização, pagamento); índice em (autorização, `status`, `position`)
- [ ] T013 Criar a migração `create_reconciliation_pair_blocks_table`: `authorization_identity_key` char(64), `payment_unit`, `payment_identity_key` string(800), `reason`, `created_by` com `restrictOnDelete`, `created_at`; único nas três chaves
- [ ] T014 Criar a migração `alter_authorization_entries_for_reconciliation`: `import_file_id` passa a aceitar nulo (repetindo `constrained()->cascadeOnDelete()`), mais `created_by` FK `users` opcional e `source_payment_entry_id` FK opcional com `nullOnDelete`
- [ ] T015 [P] Escrever `tests/Unit/Reconciliation/CardNumberExtractorTest.php` com a tabela "Espécie do pagamento → cartão" de `contracts/matching-rules.md`
- [ ] T016 Criar `app/Services/Reconciliation/Matching/CardNumberExtractor.php` (recebe a espécie e o marcador; devolve os quatro dígitos ou nulo), até T015 passar
- [ ] T017 Criar a migração `add_card_to_payment_entries_table`: coluna `card` string(4) opcional e indexada, preenchida para os pagamentos existentes em lotes com `CardNumberExtractor`; preencher `card` em `app/Services/Import/Layouts/PaymentsLayout.php` e acrescentar o caso a `tests/Unit/Conciliation/PaymentsLayoutTest.php`
- [ ] T018 [P] Criar os modelos `ReconciliationSettings` (com `current()`), `ReconciliationRun` (relações `session()`, `requester()`, `links()`, `suggestions()`, `skips()`) e `ReconciliationSkip` em `app/Models/`, com casts de Enum e json, e suas factories em `database/factories/`
- [ ] T019 [P] Criar os modelos `ReconciliationLink`, `ReconciliationSuggestion`, `ReconciliationPairBlock` e `AuthorizationState` em `app/Models/`, com relações, casts e factories com estados (automático, manual, parcela, cada tratamento; cada situação de sugestão)
- [ ] T020 Acrescentar a `app/Models/AuthorizationEntry.php` as relações `state()`, `links()`, `suggestions()`, `creator()`, `sourcePayment()` e os métodos `isCreatedInReconciliation()`, `balanceCents()` e `status()`; a `app/Models/PaymentEntry.php` as relações `link()`, `suggestions()` e `skips()`; e a `app/Models/ReconciliationSession.php` as relações `runs()` e `currentRun()`
- [ ] T021 [P] Escrever `tests/Unit/Reconciliation/EngineParametersTest.php`: tolerância efetiva só com valor fixo, só com percentual, com os dois (vale o maior) e com zero; teto de acréscimo em pontos-base no limite exato, um centavo abaixo e um acima; `fromRun()` devolve os parâmetros gravados na execução, mesmo depois de a configuração mudar
- [ ] T022 Criar `app/Services/Reconciliation/Matching/EngineParameters.php` (`final readonly`, com `toleranceFor(int $balanceCents)`, `allowsSurcharge(int $excessCents, int $authorizedCents)` e a fábrica `app/Services/Reconciliation/EngineParametersFactory.php`, com `fromSettings()`, que lê `ReconciliationSettings` e `config`, e `fromRun(ReconciliationRun)`, que lê os parâmetros gravados na execução), até T021 passar
- [ ] T023 Escrever `tests/Feature/Reconciliation/AuthorizationStateTest.php`: sem vínculo não há linha e a situação é `Open`; vínculo com resto vira `Partial` com o saldo certo; vínculo que zera vira `Reconciled`; resto dentro da tolerância é gravado em `tolerance_writeoff_cents` com a categoria `Rounding` e a autorização fica `Reconciled`; a tolerância usada é a da execução vigente da sessão do pagamento, mesmo depois de a configuração mudar; pagamento maior que o saldo grava `excess_cents` e o saldo fica zero; desvincular recalcula e, sem vínculos, remove a linha; desvincular o vínculo que quitava zera o valor absorvido dos restantes; segundo vínculo para o mesmo pagamento é recusado; duas alterações seguidas na mesma autorização usam o saldo já atualizado (SC-006, SC-007)
- [ ] T024 Criar `app/Services/Reconciliation/AuthorizationStateCalculator.php`, que recalcula `authorization_states` com agregações no banco e a autorização travada com `lockForUpdate`
- [ ] T025 Criar `app/Services/Reconciliation/ReconciliationLinker.php` com `link()` e `unlink()` (exigem transação aberta; calculam tipo de diferença, valor excedido e resto absorvido com a tolerância de `EngineParameters::fromRun()`; recusam pagamento já vinculado; chamam o calculador; `unlink` devolve a `Pending` as sugestões `Superseded` das duas pontas quando elas ficam livres e o par não está bloqueado) e o objeto `app/Services/Reconciliation/LinkAttributes.php`, até T023 passar
- [ ] T026 Em `app/Providers/AppServiceProvider.php`, acrescentar ao mapa polimórfico `reconciliation_run`, `reconciliation_link`, `reconciliation_suggestion`, `authorization_entry` e `reconciliation_settings`, e definir o gate `configure-tolerance`

**Checkpoint**: tabelas, modelos e o caminho único de vínculo prontos.

---

## Phase 3: User Story 1 - Executar a conciliação e ver o que foi resolvido sozinho (Priority: P1) 🎯 MVP

**Goal**: executar uma sessão e ter os pares sem dúvida vinculados, o resto classificado e os
totais gravados.

**Independent Test**: carregar uma sessão com pares idênticos, com centavos de diferença, com
nomes parecidos e sem par; executar; conferir a classificação de cada item e os totais.

### Tests for User Story 1 ⚠️

- [ ] T027 [P] [US1] Escrever `tests/Unit/Reconciliation/SupplierNameNormalizerTest.php` com a tabela de normalização de `contracts/matching-rules.md`
- [ ] T028 [P] [US1] Escrever `tests/Unit/Reconciliation/SupplierSimilarityTest.php` com a tabela de compatibilidade do fornecedor, a simetria (trocar os argumentos) e nome vazio dando 0
- [ ] T029 [P] [US1] Escrever `tests/Unit/Reconciliation/PairScorerTest.php` com a tabela "Compatibilidade do valor e nota": cada limite (90, 89, 60, 59), tolerância no centavo exato e um acima, tolerância percentual, Parcial, Excedente e fornecedor abaixo do limite; os mesmos casos com limites diferentes do padrão (FR-017); a nota calculada contra um valor de referência, para parcelas (FR-044a)
- [ ] T030 [P] [US1] Escrever `tests/Unit/Reconciliation/MatcherTest.php` com a tabela "Quando o motor vincula sozinho": par único; empate entre pagamentos; empate entre autorizações; desempate pelo cartão; desempate pela forma de pagamento; cartão diferente; só um lado com cartão; pagamento anterior à autorização; mesma data; par bloqueado; rodadas (o segundo melhor é vinculado depois que o primeiro é tomado); sugestões com posição e limite de candidatos; nenhuma sugestão abaixo do limite
- [ ] T031 [P] [US1] Escrever `tests/Unit/Reconciliation/MatcherDeterminismTest.php`: as mesmas entradas em ordens embaralhadas produzem vínculos, sugestões, notas e posições idênticos (SC-004)
- [ ] T032 [P] [US1] Escrever `tests/Feature/Reconciliation/RunReconciliationTest.php`: os cenários 1 a 8 da história com planilhas geradas no teste; a execução fica `Completed` com parâmetros e totais; o andamento é informado; uma exceção no meio deixa a execução `Failed`, nenhum vínculo, sugestão ou pulado, e a sessão volta a aberta; o registro `ReconciliationCompleted` leva parâmetros e totais; nenhum vínculo automático passa da tolerância (SC-005)
- [ ] T033 [P] [US1] Escrever `tests/Feature/Reconciliation/ExcludedCodesInRunTest.php`: pagamento com código da lista não recebe nota nem sugestão e ganha linha em `reconciliation_skips` com o código; código com espaços nas pontas também é excluído; a execução guarda a lista vigente e os totais trazem a quantidade por código; mudar a lista depois não altera o resultado; a nova execução usa a lista nova (FR-004a a FR-004c)
- [ ] T034 [P] [US1] Escrever `tests/Feature/Reconciliation/PriorSessionsTest.php` com a tabela "Sessões anteriores": autorização em aberto de sessão processada dentro da janela é vinculada por pagamento da sessão seguinte e o vínculo guarda as duas sessões; fora da janela não é lida; de sessão não processada não é lida; de período posterior não é lida; empate entre autorização da sessão e antiga fica Dúbio; lançamento com chave de identidade já existente em sessão processada de outro período é pulado como duplicado; saldo alterado por outro usuário durante o cálculo vira sugestão; a mesma compra repetida em mês diferente (data diferente) não é duplicada; sessão de agosto executada antes da de julho não lê as autorizações de julho, e passa a lê-las depois de reaberta e executada de novo
- [ ] T035 [P] [US1] Escrever `tests/Feature/Reconciliation/DiscardAndReopenTest.php`: `discardResult` apaga vínculos automáticos, sugestões e pulados, recalcula autorizações de sessões anteriores, deixa a execução `Discarded` com parâmetros e totais e é idempotente; a reabertura descarta na hora; a reabertura é recusada com decisão manual e com autorização da sessão vinculada a pagamento de sessão posterior, informando a quantidade; executar de novo com as mesmas planilhas dá o mesmo resultado; depois de substituir a planilha de autorizações na sessão reaberta, a nova execução usa só os lançamentos do arquivo ativo; uma segunda execução simultânea espera a trava e, sem consegui-la no prazo, falha com mensagem clara; excluir uma sessão nunca processada cuja execução falhou apaga a execução e mantém a auditoria da exclusão

### Implementation for User Story 1

- [ ] T036 [P] [US1] Criar `app/Services/Reconciliation/Matching/SupplierNameNormalizer.php` (research R6), até T027 passar
- [ ] T037 [US1] Criar `app/Services/Reconciliation/Matching/SupplierSimilarity.php`: o maior entre Levenshtein normalizado e contenção de palavras (esta só quando o nome mais curto tem duas palavras ou mais), até T028 passar
- [ ] T038 [P] [US1] Criar os objetos `AuthorizationCandidate`, `PaymentCandidate`, `PairScore` e `MatchResult` em `app/Services/Reconciliation/Matching/`, todos `final readonly`
- [ ] T039 [US1] Criar `app/Services/Reconciliation/Matching/PairScorer.php`: compatibilidade do valor contra o saldo ou contra um valor de referência informado, nota como o menor dos eixos e classificação, até T029 passar
- [ ] T040 [P] [US1] Criar `app/Services/Reconciliation/Matching/PaymentMethodMatcher.php`: cartão diferente e os dois passos do desempate (FR-012a, FR-012c)
- [ ] T041 [US1] Criar `app/Services/Reconciliation/Matching/Matcher.php` com a ordem de research R8, sem a etapa de parcelas: índices por palavra e por valor, pares bloqueados, rodadas de vínculo exato, desempates, data, cartão e sugestões, até T030 e T031 passarem
- [ ] T042 [US1] Criar `app/Services/Reconciliation/CandidateLoader.php`: lê pagamentos ativos da sessão, autorizações ativas da sessão (de arquivo ativo ou criadas na conciliação), autorizações em aberto de sessões processadas anteriores dentro da janela, pulados por código (com `ExcludedOperationCodes::snapshot()`) e duplicados de outro período, e os pares bloqueados; usa `toBase()` e só as colunas necessárias
- [ ] T043 [US1] Criar `app/Services/Reconciliation/RunTotals.php`: totais por classificação, percentual de autorizações da sessão conciliadas automaticamente, autorizações de sessões anteriores conciliadas, pagos antes da autorização, quantidade e soma por tratamento e quantidade excluída por código, todos por agregação no banco
- [ ] T044 [US1] Criar `app/Services/Reconciliation/MatchResultWriter.php`: em uma transação, confere o saldo das autorizações antigas travadas, cria os vínculos pelo `ReconciliationLinker`, insere sugestões e pulados em lotes, grava os totais, muda a execução para `Completed` e grava a auditoria `ReconciliationCompleted`
- [ ] T045 [US1] Criar `app/Services/Reconciliation/DatabaseReconciliationEngine.php` implementando `App\Contracts\ReconciliationEngine`: `run` sob `Cache::lock('conciliation:engine')` (espera de até 10 minutos), com a execução `Running`, leitura, `Matcher`, andamento e gravação, marcando `Failed` em exceção; `discardResult` conforme `contracts/application-interfaces.md`, até T032, T033 e T034 passarem
- [ ] T046 [US1] Criar `app/Services/Reconciliation/ReconciliationDecisionInspector.php` implementando `ReconciliationResultInspector`, com as duas contagens no banco
- [ ] T047 [US1] Em `app/Providers/AppServiceProvider.php`, registrar `DatabaseReconciliationEngine` e `ReconciliationDecisionInspector`; em `app/Actions/Conciliation/ReopenSession.php`, chamar `discardResult()` na transação da reabertura; em `app/Actions/Conciliation/DeleteSession.php`, apagar as execuções da sessão nunca processada antes de apagá-la; ajustar os testes do Módulo 1 que usam o motor falso e o inspetor nulo, até T035 passar
- [ ] T048 [US1] Em `app/Livewire/Sessions/Show.php` e `resources/views/livewire/sessions/show.blade.php`, mostrar na sessão processada o resumo da execução (quem, quando, tolerância, totais e percentual automático)

**Checkpoint**: a conciliação roda de ponta a ponta. Primeira metade do MVP.

---

## Phase 4: User Story 2 - Analisar as pendências lado a lado (Priority: P1)

**Goal**: ver só o que precisa de decisão, com a autorização de um lado e o pagamento do outro,
filtrar, buscar e consultar o que foi conciliado.

**Independent Test**: com uma sessão processada com itens de todas as classificações, abrir a
tela, conferir que os conciliados automáticos não estão na lista padrão, aplicar cada filtro e
buscar um fornecedor.

### Tests for User Story 2 ⚠️

- [ ] T049 [P] [US2] Escrever `tests/Feature/Reconciliation/PendingScreenTest.php`: os cenários 1 a 9 da história; a lista padrão não traz conciliados nem pagamentos sem autorização; autorização `Partial` sem sugestão aparece como "Saldo em aberto"; cada filtro rápido, inclusive "Saldo em aberto" e "Sem autorização"; busca por fornecedor da autorização e do pagamento; aba, filtro e busca na URL; totais do cabeçalho; sugestão com autorização de outra sessão mostra o período de origem; autorização antiga sem sugestão não aparece; pagamento de obrigação com outras linhas indica a quantidade (FR-024a); sessão não processada mostra o aviso em vez das listas; visitante e sessão inexistente
- [ ] T050 [P] [US2] Escrever `tests/Feature/Reconciliation/EarlyPaymentsTest.php`: a aba lista sugestões e vínculos com `paid_before_authorization`, com as duas datas e a situação; o par confirmado continua na aba com o aviso (cenários 10 e 11)
- [ ] T051 [P] [US2] Escrever `tests/Feature/Reconciliation/CardReconciliationTest.php`: `payment_entries.card` é preenchido na importação e pela migração; o resumo por cartão traz autorizações, linhas de fatura, conciliadas, autorizações sem fatura, linhas sem autorização e excluídas por código; cartão só com fatura aparece com zero autorizações; a conferência de um cartão mostra os três blocos; o filtro por cartão vale em Pendências e Conciliados e fica na URL; o aviso "Cartão diferente" aparece no par (FR-023b, FR-023c)
- [ ] T052 [P] [US2] Escrever `tests/Feature/Reconciliation/ExcludedTabTest.php`: a aba mostra os códigos vigentes na execução com a quantidade de cada um e os pagamentos excluídos, filtráveis por código (FR-004d)

### Implementation for User Story 2

- [ ] T053 [US2] Criar a migração `create_reconciliation_pending_items_view`, com a visão de `data-model.md` (sugestões de melhor posição, autorizações sem pagamento, autorizações com saldo em aberto e pagamentos sem autorização) em SQL padrão (`UNION ALL`, `NOT EXISTS`, subconsulta correlacionada), válida em SQLite e PostgreSQL, e o modelo somente leitura `app/Models/PendingItem.php` (chave em texto, relações `suggestion()`, `authorization()` e `payment()`)
- [ ] T054 [US2] Criar `app/Livewire/Reconciliation/Show.php` e `resources/views/livewire/reconciliation/show.blade.php`: autoriza `view` da sessão, mostra o cabeçalho com os totais de `RunTotals`, as abas com `#[Url(as: 'aba')]` e o aviso para sessão não processada; registrar a rota `reconciliation.show` em `routes/web.php` com `whereNumber('session')`
- [ ] T055 [US2] Criar `app/Livewire/Reconciliation/PendingTable.php` e sua view: tabela do Filament sobre `PendingItem`, com as colunas lado a lado de `contracts/routes.md`, filtro rápido por classificação (o padrão "Todos" exclui os pagamentos sem autorização), filtro por cartão, busca por fornecedor dos dois lados, 25 por página e tudo na URL; ainda sem ações
- [ ] T056 [P] [US2] Criar `app/Livewire/Reconciliation/LinksTable.php` e sua view: vínculos da sessão, mais os de autorizações da sessão pagas em sessões posteriores, com origem, nota, tipo de diferença, tratamento, saldo, avisos, filtros (origem, tratamento, só parcelas, cartão) e busca; ainda sem ações
- [ ] T057 [P] [US2] Criar `app/Livewire/Reconciliation/EarlyPaymentsTable.php` e sua view, até T050 passar
- [ ] T058 [P] [US2] Criar `app/Livewire/Reconciliation/CardsTable.php` e sua view: resumo por cartão por agregação no banco e, com `#[Url(as: 'cartao')]`, a conferência do cartão em três blocos paginados, até T051 passar
- [ ] T059 [P] [US2] Criar `app/Livewire/Reconciliation/ExcludedTable.php` e sua view, até T052 passar
- [ ] T060 [US2] Acrescentar o botão "Abrir conciliação" em `resources/views/livewire/sessions/show.blade.php` e a coluna de atalho em `app/Livewire/Sessions/Index.php` para sessões processadas, até T049 passar

**Checkpoint**: MVP completo. O Operador executa e enxerga o resultado.

---

## Phase 5: User Story 3 - Confirmar ou rejeitar uma sugestão do motor (Priority: P2)

**Goal**: decidir cada par Dúbio; o rejeitado não volta a ser sugerido.

**Independent Test**: confirmar um Dúbio e ver a autorização Conciliada, o par fora das
pendências e o registro de auditoria; rejeitar outro e ver os dois sem par.

### Tests for User Story 3 ⚠️

- [ ] T061 [US3] Escrever `tests/Feature/Reconciliation/ConfirmRejectSuggestionTest.php`: confirmar cria vínculo manual com nota e classificação do motor, tira o par da lista e audita com usuário, data e nota; as outras sugestões do mesmo pagamento ficam `Superseded`; rejeitar não cria vínculo, grava o bloqueio do par, audita e promove o próximo candidato; sem próximo candidato, a autorização vai para Sem pagamento e o pagamento para Sem autorização; par rejeitado não volta depois de reabrir e executar de novo; dois usuários decidindo o mesmo par: só o primeiro vale e o segundo vê a situação atual; confirmação em lote vincula e audita cada par e informa os recusados; a ação é recusada em sessão não processada; Operador e Administrador conseguem confirmar e rejeitar (FR-041)

### Implementation for User Story 3

- [ ] T062 [US3] Criar `app/Actions/Conciliation/ConfirmSuggestion.php`: autoriza, trava a sugestão, recusa se não está `Pending`, vincula pelo `ReconciliationLinker` como manual com `decided_by`, copia os avisos, resolve as outras sugestões (research R11) e audita `SuggestionConfirmed`
- [ ] T063 [US3] Criar `app/Actions/Conciliation/RejectSuggestion.php`: trava a sugestão, marca `Rejected`, grava o bloqueio em `reconciliation_pair_blocks` pelas chaves de identidade e audita `SuggestionRejected`
- [ ] T064 [US3] Em `app/Livewire/Reconciliation/PendingTable.php`, acrescentar as ações de linha "Confirmar" (Dúbio), "Rejeitar" e "Ver outros candidatos", e a ação em lote "Confirmar selecionados", cada uma conferindo a autorização e atualizando os totais, até T061 passar

**Checkpoint**: os Dúbios podem ser resolvidos.

---

## Phase 6: User Story 4 - Decidir pagamentos parciais e excedentes (Priority: P2)

**Goal**: confirmar um Parcial como "ainda falta pagar" ou "encerrar com desconto", e um Excedente
como "pagamento a maior" ou "acréscimo aceito".

**Independent Test**: confirmar um Parcial de cada jeito e conferir saldo e desconto; confirmar um
Excedente de cada jeito e conferir que só o pagamento a maior tem alerta.

### Tests for User Story 4 ⚠️

- [ ] T065 [US4] Escrever `tests/Feature/Reconciliation/DifferenceTreatmentTest.php`: os cenários 1 a 13 da história; "Ainda falta pagar" deixa `Partial` com o saldo; segundo pagamento que zera deixa `Reconciled`; "Encerrar com desconto" grava `discount_cents` igual ao saldo e exige categoria e texto da justificativa; "Pagamento a maior" grava `excess_cents` e o alerta; "Acréscimo aceito" é permitido no teto exato e recusado um centavo acima, exige categoria e texto da justificativa e grava o teto vigente; mudar o teto depois não altera a decisão; encerrar com desconto o saldo de uma autorização `Partial` grava no vínculo mais recente; sem vínculo, é recusado; desvincular desfaz o desconto; os totais trazem quantidade e soma de descontos, acréscimos e pagamentos a maior; tratamento ausente em Parcial ou Excedente é recusado (SC-010)

### Implementation for User Story 4

- [ ] T066 [US4] Estender `app/Actions/Conciliation/ConfirmSuggestion.php` para exigir e validar o tratamento em Parcial e Excedente, a categoria e o texto da justificativa em `Discount` e `AcceptedSurcharge` e o teto com `EngineParameters::allowsSurcharge()`
- [ ] T067 [P] [US4] Criar `app/Actions/Conciliation/CloseAuthorizationWithDiscount.php`: trava a autorização, exige vínculo e saldo, exige categoria e texto da justificativa, grava o desconto no vínculo mais recente com `decided_by`, recalcula e audita `AuthorizationClosedWithDiscount`
- [ ] T068 [US4] Em `app/Livewire/Reconciliation/PendingTable.php`, trocar a confirmação de Parcial e Excedente por um modal do Filament com a escolha do tratamento, a categoria e o texto da justificativa (opção de acréscimo desabilitada, com o motivo, acima do teto), e acrescentar a ação "Encerrar com desconto" para os itens "Saldo em aberto"; em `app/Livewire/Reconciliation/LinksTable.php`, mostrar o alerta vermelho "Pagamento a maior" e o filtro por tratamento, até T065 passar

**Checkpoint**: saldo devido e valor pago a mais ficam tratados e auditados.

---

## Phase 7: User Story 5 - Vincular manualmente e desvincular (Priority: P2)

**Goal**: vincular um par que o motor não sugeriu e desfazer qualquer vínculo.

**Independent Test**: vincular uma autorização Sem pagamento a um pagamento Sem autorização;
desvincular um par automático e ver os dois de volta às pendências, com a nota original na
auditoria.

### Tests for User Story 5 ⚠️

- [ ] T069 [US5] Escrever `tests/Feature/Reconciliation/ManualLinkTest.php`: os cenários 1 a 9 da história; o vínculo manual calcula o tipo de diferença e pede o tratamento quando é parcial ou excedente; a busca de pagamentos filtra por fornecedor e valor e vem ordenada pela nota; pode escolher autorização em aberto de sessão anterior dentro da janela; pagamento já vinculado, excluído por código ou de outra sessão é recusado; desvincular par automático audita nota, classificação e origem; as sugestões descartadas por aquele vínculo voltam a pendentes quando as duas pontas ficam livres; desvincular par manual audita também quem vinculou; com dois pagamentos, o saldo é recalculado pelo que restou; o par desvinculado não é vinculado sozinho de novo; desvincular pagamento de outra sessão é recusado; 100% das ações têm registro de auditoria (SC-003)

### Implementation for User Story 5

- [ ] T070 [US5] Criar `app/Actions/Conciliation/LinkManually.php`: autoriza, valida sessão processada, pagamento sem vínculo e sem pulo, autorização da sessão ou em aberto de sessão anterior na janela; calcula avisos de data e de cartão; aplica o tratamento como em `ConfirmSuggestion`; audita `ManualLinkCreated`
- [ ] T071 [US5] Criar `app/Actions/Conciliation/RemoveLink.php`: trava o vínculo, desfaz pelo `ReconciliationLinker`, grava o bloqueio do par e audita `LinkRemoved` com o vínculo inteiro em `before`
- [ ] T072 [US5] Em `app/Livewire/Reconciliation/PendingTable.php`, acrescentar a ação "Vincular" para autorização Sem pagamento ou com Saldo em aberto, com modal de busca de pagamentos da sessão (fornecedor, valor, ordenação por nota calculada com `PairScorer`) e tratamento da diferença; em `app/Livewire/Reconciliation/LinksTable.php`, acrescentar "Desvincular" com confirmação, até T069 passar

**Checkpoint**: tudo o que o motor não acerta tem saída, e tudo o que ele erra tem correção.

---

## Phase 8: User Story 8 - Acompanhar compras pagas em parcelas (Priority: P2)

**Goal**: vincular sozinho as parcelas de uma compra autorizada pelo total, usando a condição de
pagamento como indício.

**Independent Test**: processar três sessões seguidas com uma autorização "3x" de R$ 900,00 e um
pagamento de R$ 300,00 em cada; as três parcelas são vinculadas sozinhas. Repetir com "A vista":
só a primeira exige confirmação.

### Tests for User Story 8 ⚠️

- [ ] T073 [P] [US8] Escrever `tests/Unit/Reconciliation/PaymentConditionParserTest.php` com a tabela "Condição de pagamento → parcelas previstas"
- [ ] T074 [P] [US8] Escrever `tests/Unit/Reconciliation/MatcherInstallmentsTest.php` com a tabela "Vínculo automático de parcela", incluindo a tabela de nota da parcela (valor contra a parcela de referência; fornecedor abaixo do limite não vincula), divisão com centavo de resto, duas parcelas na mesma sessão em ordem de data, parcela que não cabe no saldo, duas autorizações servidas pelo mesmo pagamento, data anterior e cartão diferente
- [ ] T075 [P] [US8] Escrever `tests/Feature/Reconciliation/InstallmentsAcrossSessionsTest.php`: os cenários 1 a 12 da história em três sessões; autorização parcelada com vínculo e saldo é lida fora da janela; primeira parcela de autorização "A vista" exige confirmação e as seguintes entram sozinhas; parcela desvinculada não volta sozinha; nenhum vínculo de parcela passa do valor autorizado (SC-011, SC-012)

### Implementation for User Story 8

- [ ] T076 [P] [US8] Criar `app/Services/Reconciliation/Matching/PaymentConditionParser.php`, até T073 passar
- [ ] T077 [US8] Acrescentar a etapa de parcelas a `app/Services/Reconciliation/Matching/Matcher.php` (research R9), com o valor de referência pela condição ou por pagamento já vinculado como "Ainda falta pagar", a nota calculada contra esse valor e o vínculo só com nota igual ou superior ao limite automático (FR-044a), até T074 passar
- [ ] T078 [US8] Em `app/Services/Reconciliation/CandidateLoader.php`, ler as autorizações com vínculo e saldo fora da janela e os valores de parcelas já vinculadas; em `app/Services/Reconciliation/MatchResultWriter.php`, gravar o vínculo de parcela com `is_installment`, até T075 passar
- [ ] T079 [US8] Mostrar em `app/Livewire/Reconciliation/PendingTable.php` e `app/Livewire/Reconciliation/LinksTable.php` a condição como informada, as parcelas previstas, os pagamentos vinculados, o valor pago e o saldo, e o aviso quando o valor não corresponde à parcela prevista (FR-048)

**Checkpoint**: parcelamento reconhecido sem clique, ou com um só.

---

## Phase 9: User Story 6 - Investigar pagamentos sem autorização (Priority: P3)

**Goal**: tratar a fila de pagamentos sem autorização e regularizar um pagamento criando a
autorização correspondente.

**Independent Test**: na fila, criar a autorização de um pagamento; ele sai da fila, a autorização
aparece marcada como criada na conciliação e a ação é auditada.

### Tests for User Story 6 ⚠️

- [ ] T080 [US6] Escrever `tests/Feature/Reconciliation/InvestigationQueueTest.php`: os cenários 1 a 5 da história; a fila não traz excluídos por código, duplicados, vinculados nem pagamentos com sugestão pendente; filtros por unidade, código, forma e cartão; criar a autorização copia fornecedor, valor e data, marca como criada na conciliação, vincula e audita; a autorização criada é distinguível nas listas; desvincular apaga a autorização criada e devolve o pagamento à fila; pagamento já vinculado ou excluído é recusado; vincular a partir da fila aceita autorização da sessão ou antiga na janela

### Implementation for User Story 6

- [ ] T081 [US6] Criar `app/Actions/Conciliation/CreateMatchingAuthorization.php`: cria a `AuthorizationEntry` sem arquivo, com `created_by` e `source_payment_entry_id`, vincula pelo `ReconciliationLinker` como manual e audita `AuthorizationCreatedInReconciliation`; ajustar `app/Actions/Conciliation/RemoveLink.php` para apagar a autorização criada na conciliação
- [ ] T082 [US6] Criar `app/Livewire/Reconciliation/InvestigationTable.php` e sua view: pagamentos sem autorização da sessão, com as colunas e os filtros de `contracts/routes.md` e as ações "Vincular" e "Criar autorização correspondente"; marcar a autorização criada na conciliação nas demais tabelas, até T080 passar

**Checkpoint**: pagamentos órfãos têm tratamento.

---

## Phase 10: User Story 7 - Definir a margem de tolerância (Priority: P3)

**Goal**: o Administrador altera a tolerância e o teto de acréscimo, valendo para as próximas
execuções.

**Independent Test**: alterar a tolerância, executar uma sessão nova e ver o valor novo; a sessão
já processada continua mostrando o valor antigo.

### Tests for User Story 7 ⚠️

- [ ] T083 [US7] Escrever `tests/Feature/Reconciliation/ReconciliationSettingsTest.php`: os cenários 1 a 6 da história; Operador sem a permissão recebe 403 e não vê o item de menu; Operador com `ConfigureTolerance` acessa; valor negativo, percentual acima de 100 e teto acima de 100 são recusados; a alteração audita os valores anterior e novo; a execução seguinte usa o valor novo e a já processada mantém o seu (SC-009); tolerância só em valor, só em percentual e nos dois

### Implementation for User Story 7

- [ ] T084 [US7] Criar `app/Actions/Conciliation/UpdateReconciliationSettings.php`: autoriza o gate, valida os limites, grava com `updated_by` e audita `ReconciliationSettingsChanged`
- [ ] T085 [US7] Criar `app/Livewire/Reconciliation/Settings.php`, o Form Object `app/Livewire/Forms/ReconciliationSettingsForm.php` (reais com vírgula convertidos para centavos; percentuais com até duas casas convertidos para pontos-base) e a view; registrar a rota `reconciliation.settings` com `->can('configure-tolerance')` em `routes/web.php`; acrescentar o item "Tolerância" a `resources/views/navigation-menu.blade.php` sob o gate, até T083 passar

**Checkpoint**: todas as histórias funcionam de forma independente.

---

## Phase 11: Polish & Cross-Cutting Concerns

**Purpose**: desempenho, calibração com dados reais, conferência final e documentação.

- [ ] T086 [P] Escrever `tests/Feature/Reconciliation/ReconciliationPerformanceTest.php`: sessão com 10.000 lançamentos (2.000 autorizações e 8.000 pagamentos, com fornecedores variados) conciliada em menos de 120 s (SC-002), e ajustar o `Matcher` se não passar
- [ ] T087 Executar a conciliação da sessão de julho/2026 com os arquivos reais já importados no banco local; conferir uma amostra dos vínculos automáticos e dos Dúbios; ajustar, se preciso, os limites em `config/conciliation.php` e as regras de `SupplierNameNormalizer`; registrar o percentual automático e os ajustes em `specs/003-reconciliation-engine/research.md`, sem copiar dados pessoais
- [ ] T088 Atualizar `specs/001-session-file-import/contracts/application-interfaces.md` com a chamada de `discardResult()` na reabertura
- [ ] T089 Rodar a suíte inteira (`php artisan test --compact`) e `vendor/bin/pint --dirty --format agent`, e corrigir o que falhar
- [ ] T090 Executar o roteiro manual de `specs/003-reconciliation-engine/quickstart.md` no navegador, com `queue:work` e `npm run build`, incluindo o passo de acessibilidade (Princípio II)
- [ ] T091 Registrar em `specs/003-reconciliation-engine/research.md` as decisões tomadas durante a implementação e o resultado do roteiro manual

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependência.
- **Foundational (Phase 2)**: depende do Setup; bloqueia todas as histórias.
- **US1 (Phase 3)**: depende da fundação.
- **US2 (Phase 4)**: depende de US1 (precisa de uma sessão processada pelo motor).
- **US3 (Phase 5)**: depende de US2 (ações na lista de pendências).
- **US4 (Phase 6)**: depende de US3 (estende `ConfirmSuggestion`).
- **US5 (Phase 7)**: depende de US3; usa o tratamento de US4 no vínculo manual.
- **US8 (Phase 8)**: depende de US1; o cenário "A vista" depende de US4 ("Ainda falta pagar").
- **US6 (Phase 9)**: depende de US2 e de US5 (`RemoveLink`).
- **US7 (Phase 10)**: depende só da fundação e de US1; pode andar em paralelo com US2 a US6.
- **Polish (Phase 11)**: depois de todas as histórias.

### Ordem dentro da fundação

1. T003 a T005 em paralelo; T006.
2. T007 a T014, na ordem (chaves estrangeiras).
3. T015 → T016 → T017.
4. T018 e T019 em paralelo; T020.
5. T021 → T022; T023 → T024 → T025; T026.

### Ordem dentro de cada história

- Teste antes do código, e o teste DEVE falhar primeiro.
- Núcleo puro antes da leitura e da gravação; serviço antes da ação; ação antes do componente.

### Arquivos compartilhados (não paralelizar)

- `app/Services/Reconciliation/Matching/Matcher.php`: T041, T077, T086.
- `app/Services/Reconciliation/CandidateLoader.php`: T042, T078.
- `app/Services/Reconciliation/MatchResultWriter.php`: T044, T078.
- `app/Livewire/Reconciliation/PendingTable.php`: T055, T064, T068, T072, T079.
- `app/Livewire/Reconciliation/LinksTable.php`: T056, T068, T072, T079.
- `app/Actions/Conciliation/ConfirmSuggestion.php`: T062, T066.
- `app/Actions/Conciliation/RemoveLink.php`: T071, T081.
- `app/Providers/AppServiceProvider.php`: T026, T047.
- `routes/web.php`: T054, T085.

### Parallel Opportunities

- Fundação: os Enums (T003 a T005); os modelos (T018, T019); T015 e T021.
- US1: os nove arquivos de teste (T027 a T035); T036, T038 e T040.
- US2: os quatro testes (T049 a T052); as abas T056 a T059.
- US8: os três testes (T073 a T075); T076.
- US7 inteira em paralelo com US2 a US6.

---

## Parallel Example: User Story 1

```bash
# Testes do núcleo, em paralelo:
Task: "SupplierNameNormalizerTest em tests/Unit/Reconciliation/SupplierNameNormalizerTest.php"
Task: "SupplierSimilarityTest em tests/Unit/Reconciliation/SupplierSimilarityTest.php"
Task: "PairScorerTest em tests/Unit/Reconciliation/PairScorerTest.php"
Task: "MatcherTest em tests/Unit/Reconciliation/MatcherTest.php"
Task: "MatcherDeterminismTest em tests/Unit/Reconciliation/MatcherDeterminismTest.php"

# Classes sem dependência entre si:
Task: "SupplierNameNormalizer em app/Services/Reconciliation/Matching/SupplierNameNormalizer.php"
Task: "Objetos de valor em app/Services/Reconciliation/Matching/"
Task: "PaymentMethodMatcher em app/Services/Reconciliation/Matching/PaymentMethodMatcher.php"
```

---

## Implementation Strategy

### MVP First (User Stories 1 e 2)

1. Phase 1 e Phase 2.
2. Phase 3 (US1): a conciliação roda e grava o resultado.
3. Phase 4 (US2): o resultado aparece na tela.
4. **Parar e validar**: executar a sessão de julho com os arquivos reais e conferir a
   classificação, antes de construir as decisões.

### Incremental Delivery

1. Fundação → tabelas e caminho único de vínculo.
2. US1 + US2 → executar e ver (MVP). Calibrar com os dados reais (T087 pode ser antecipada aqui).
3. US3 → resolver Dúbios.
4. US4 → Parciais e Excedentes, com desconto e acréscimo.
5. US5 → vínculo manual e desvínculo.
6. US8 → parcelas.
7. US6 → fila de investigação.
8. US7 → tolerância.
9. Polish → desempenho, suíte completa e roteiro manual.

### Parallel Team Strategy

Com mais de uma pessoa, depois da fundação: uma no núcleo de cruzamento (US1 e US8), outra nas
telas (US2 e, depois, as ações de US3 a US6), e uma terceira em US7 e nos testes de desempenho.
`Matcher`, `PendingTable` e `LinksTable` são os pontos de encontro e não devem ser editados em
paralelo.

---

## Notes

- A calibração com os arquivos reais (T087) é feita no banco local; nenhum dado real entra no
  repositório nem nos documentos.
- A suíte roda em SQLite. Antes de produção, rodar em PostgreSQL: travas de linha e a visão de
  pendências só se provam lá.
- O painel de pendências acumuladas, o vínculo fora da janela, a baixa com justificativa e a
  dispensa individual de pagamentos NÃO fazem parte desta lista (Módulo 4).
- Fazer commit ao fim de cada fase, com mensagem no formato Conventional Commits.
