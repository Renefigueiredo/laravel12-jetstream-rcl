---

description: "Task list for Módulo 1 - Gestão de Sessões e Importação de Arquivos"
---

# Tasks: Gestão de Sessões e Importação de Arquivos (Módulo 1)

**Input**: Design documents from `/specs/001-session-file-import/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Incluídos. A constituição (Princípio III, inegociável) exige teste escrito antes da
implementação. Em cada história, as tarefas de teste vêm primeiro e DEVEM falhar antes do código.

**Organization**: Tarefas agrupadas por história de usuário, para que cada uma seja implementada e
testada de forma independente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependência de tarefa incompleta)
- **[Story]**: história da spec a que a tarefa pertence (US1 a US6)
- Cada descrição traz o caminho exato do arquivo

## Convenções para todas as tarefas

- Criar arquivos com `php artisan make:* --no-interaction` (model, migration, factory, enum, job,
  livewire, policy, command, test `--phpunit`, class, interface).
- Antes de codificar com Livewire 4, Filament 5 ou OpenSpout, consultar `search-docs` do Laravel
  Boost e ativar as skills `livewire-development` e `tailwindcss-development`.
- Textos de tela vêm de `lang/pt_BR/conciliation.php`; nenhum texto fixo em Blade ou PHP.
- Valores em centavos inteiros; datas e horas em UTC; valores fixos como Enum.
- Rodar o teste afetado após cada tarefa (`php artisan test --compact <arquivo>`) e
  `vendor/bin/pint --dirty --format agent` antes de encerrar cada fase.
- Planilhas de teste são geradas no próprio teste; os relatórios reais do ERP e do ELO NÃO entram
  no repositório.

## Bloqueios que dependem do responsável

- **T001** altera `composer.json`: aprovado pelo responsável em 2026-10-08.
- **Jetstream Teams** continua ligado, sem uso para unidades ou papéis; removê-lo é limpeza fora
  desta lista.
- **Direção visual das telas** (T038, T045 e T073): definida pelo responsável em 2026-10-08 como
  o padrão do Jetstream, com seus componentes Blade e o layout existente (constituição,
  fluxo, item 8).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: dependência, configuração e idioma usados por todas as histórias

- [X] T001 Declarar `openspout/openspout` (`^4.32`, já presente no `composer.lock` como dependência de `filament/actions`) em `require` de `composer.json` com `composer require openspout/openspout:^4.32` (aprovado pelo responsável em 2026-10-08)
- [X] T002 [P] Criar `config/conciliation.php` com as chaves e padrões de data-model.md: `upload.max_size_mb` 50, `upload.disk` `local`, `upload.insert_chunk` 500, `attempts.confirmation_ttl_minutes` 30, `attempts.retention_hours` 24, `stale.attempt_minutes` 30, `stale.processing_minutes` 120, `engine_enabled` false (env `CONCILIATION_ENGINE_ENABLED`), `display_timezone` `America/Sao_Paulo`; acrescentar as variáveis a `.env.example`
- [X] T003 [P] Definir `APP_LOCALE=pt_BR` e `APP_FALLBACK_LOCALE=en` em `.env.example` e criar `lang/pt_BR/conciliation.php` (vazio, com as seções `sessions`, `slots`, `import`, `errors`, `audit`) e `lang/pt_BR/validation.php` com as mensagens usadas pela feature
- [X] T004 [P] Publicar `config/livewire.php` e fazer a regra de tamanho do arquivo temporário ler `conciliation.upload.max_size_mb`
- [X] T005 [P] Criar `public/.user.ini` com `upload_max_filesize=55M` e `post_max_size=60M`, e acrescentar a `specs/001-session-file-import/quickstart.md` a conferência do limite efetivo (`php -i`) e do limite do servidor web, para que o envio de 50 MB de FR-009 funcione fora dos testes
- [X] T006 Conferir no Laravel Boost (`search-docs`) os pontos marcados "[verificar no Boost]" em `specs/001-session-file-import/research.md` (opções dos leitores CSV e XLSX do OpenSpout; formato de componente do Livewire 4; Filament Tables e Actions em componente Livewire fora de painel) e registrar o resultado no próprio `research.md`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: papéis, auditoria, sessão e interpretação de planilhas, de que todas as histórias dependem

**⚠️ CRITICAL**: nenhuma história começa antes de esta fase terminar

### Enums, papéis e autorização

- [X] T007 [P] Criar os Enums de data-model.md em `app/Enums/`: `UserRole` (Administrador, Operador), `SessionStatus` (Open, Processing, Processed), `ImportSlot` (Authorizations, PaymentsSocial, PaymentsSaude, com métodos `layout()` e `unit()`), `SpreadsheetLayoutType` (Authorizations, Payments), `OperatingUnit` (Social, Saude), `ImportFileStatus` (Active, Replaced), `ImportAttemptStatus` (Queued, Validating, Rejected, AwaitingConfirmation, Persisting, Accepted, Cancelled, Failed), `AuditAction` (SessionCreated, PeriodDivergenceConfirmed, FileReplaced, ReconciliationRequested, SessionReopened, SessionDeleted); cada um com método `label()` que lê `lang/pt_BR/conciliation.php`
- [X] T008 Escrever `tests/Feature/Conciliation/SessionAuthorizationTest.php` (falhando): usuário novo tem papel Operador; Operador e Administrador passam em todas as habilidades de `ReconciliationSessionPolicy`; visitante é redirecionado ao login; o gate `view-session-history` aceita só Administrador
- [X] T009 Criar a migração que adiciona `users.role` (string, padrão `operador`, não nulo), o cast para `UserRole` em `app/Models/User.php` e o estado `administrador` em `database/factories/UserFactory.php`
- [X] T010 Criar `app/Policies/ReconciliationSessionPolicy.php` (viewAny, view, create, upload, execute, reopen, delete: verdadeiro para os dois papéis) e registrar o gate `view-session-history` em `app/Providers/AppServiceProvider.php`; T008 passa
- [X] T011 Desligar `Features::registration()` em `config/fortify.php` (aprovado pelo responsável em 2026-10-08, Princípio VIII) e conferir que `tests/Feature/RegistrationTest.php` passa com o recurso desligado: a tela de registro não é exibida e o envio não cria usuário
- [X] T012 Desligar `Features::accountDeletion()` em `config/jetstream.php` (aprovado pelo responsável em 2026-10-08, Princípio VII: usuários não são apagados) e conferir que `tests/Feature/DeleteAccountTest.php` passa com o recurso desligado
- [X] T013 Escrever `tests/Feature/Conciliation/CreateAdministratorTest.php` e criar `app/Console/Commands/CreateAdministrator.php` (`conciliation:create-administrator {name} {email}`, senha pedida no terminal; cria usuário com papel Administrador ou promove o usuário existente com esse e-mail) e `database/seeders/DevelopmentUserSeeder.php` (um Administrador e um Operador, chamado por `database/seeders/DatabaseSeeder.php` somente no ambiente `local`)

### Auditoria

- [X] T014 Escrever `tests/Feature/Conciliation/AuditLogTest.php` (falhando): `AuditRecorder::record` grava usuário, ação, tipo e id da entidade, rótulo, `before`, `after` e `created_at`; lança exceção fora de transação; `AuditLog` lança exceção em `update` e em `delete`; o registro permanece quando a entidade é apagada
- [X] T015 Criar a migração `audit_logs` conforme data-model.md (`user_id` FK restrita, `action` string, `auditable_type` string, `auditable_id` bigint sem FK, `label` string, `before` e `after` json nulos, `created_at`, sem `updated_at`; índices em (`auditable_type`, `auditable_id`), `action`, `created_at`, `user_id`; gatilho que recusa UPDATE e DELETE criado somente quando o driver é `pgsql`), `app/Models/AuditLog.php` e `database/factories/AuditLogFactory.php`
- [X] T016 Criar `app/Services/Audit/AuditRecorder.php` conforme `contracts/application-interfaces.md`; T014 passa

### Sessão

- [X] T017 Criar a migração `reconciliation_sessions` conforme data-model.md (`period` date, `status` string padrão `open`, `created_by` FK restrita, `first_processed_at`, `processing_started_at`, `processed_at` timestamps nulos, `progress` smallint nulo, `result_stale` boolean padrão false, `last_failure` text nulo, timestamps; índices em `period` e `status`), `app/Models/ReconciliationSession.php` (casts, relação `creator`) e `database/factories/ReconciliationSessionFactory.php` com os estados `processing`, `processed` e `reopened`

### Interpretação de planilhas

- [X] T018 [P] Escrever `tests/Unit/Conciliation/MoneyParserTest.php` (falhando) com um caso por linha da tabela "Valores" de `contracts/spreadsheet-layouts.md`, incluindo `.8`, `R$ 3.895,73`, célula numérica, `1.234`, `1,2345`, texto e vazio
- [X] T019 [P] Escrever `tests/Unit/Conciliation/DateParserTest.php` (falhando) com um caso por linha da tabela "Datas" de `contracts/spreadsheet-layouts.md`, incluindo `31-JUL-26`, `01-dec-25`, `31/05/2026`, célula de data, `2026-05-31`, `05/31/2026`, `31/02/2026` e `31-AGO-26`
- [X] T020 [P] Criar `app/Services/Import/MoneyParser.php` (texto, inteiro ou decimal para centavos inteiros ou motivo de erro, regras de research.md R4); T018 passa
- [X] T021 [P] Criar `app/Services/Import/DateParser.php` (regras de research.md R5; ano de dois dígitos vira 2000 + aa; resultado sem hora); T019 passa
- [X] T022 [P] Criar `app/Services/Import/HeaderNormalizer.php` com `tests/Unit/Conciliation/HeaderNormalizerTest.php`: ignora maiúsculas, acentos e espaços nas pontas; qualquer outra diferença é nome diferente
- [X] T023 Criar as interfaces `app/Contracts/SpreadsheetReader.php` e `app/Contracts/SpreadsheetLayout.php` e os objetos de valor `app/Services/Import/ParsedRow.php` e `app/Services/Import/RowError.php`, conforme `contracts/application-interfaces.md`
- [X] T024 [P] Criar `tests/Concerns/BuildsSpreadsheets.php`: trait que gera, com OpenSpout, arquivos `.xlsx` e `.csv` (delimitador e codificação à escolha) a partir de um array de linhas, com fábricas de linha válida para os dois layouts
- [X] T025 [P] Escrever `tests/Unit/Conciliation/PaymentsLayoutTest.php` e criar `app/Services/Import/Layouts/PaymentsLayout.php`: 24 cabeçalhos no layout (com `PREV_LIQUIIDACAO`); cabeçalhos exigidos e campos obrigatórios por linha COD_OPERACAO, DT_LIQUIDACAO, CEDENTE, OBRIGACAO, VL_RECEBIDO, VL_OBRIGACAO; valor decisivo VL_RECEBIDO; data do período DT_LIQUIDACAO; chave de identidade OBRIGACAO + COD_OPERACAO + CD_MOVIMENTO_CONTA; colunas opcionais ausentes resultam em campo vazio; `raw` com todas as colunas do arquivo
- [X] T026 [P] Escrever `tests/Unit/Conciliation/AuthorizationsLayoutTest.php` e criar `app/Services/Import/Layouts/AuthorizationsLayout.php`: 11 cabeçalhos no layout; cabeçalhos exigidos e campos obrigatórios por linha MAP_SOLICITACAO, MAP_FORNECEDOR, VALOR, MAP_DATA_AUTORIZACAO; valor decisivo VALOR; data do período MAP_DATA_AUTORIZACAO; chave de identidade SHA-256 de solicitação, fornecedor, valor em centavos e data, normalizados
- [X] T027 Escrever `tests/Feature/Conciliation/SpreadsheetReaderTest.php` e criar `app/Services/Import/OpenSpoutSpreadsheetReader.php`, ligado a `SpreadsheetReader` em `app/Providers/AppServiceProvider.php`: lê `.xlsx` (primeira aba, células de data como data, `sheetCount`) e `.csv` com `;` ou `,` detectado no cabeçalho, em UTF-8 com ou sem BOM ou Windows-1252, devolvendo sempre UTF-8

**Checkpoint**: fundação pronta; as histórias podem começar

---

## Phase 3: User Story 1 - Criar sessão e carregar as três planilhas (Priority: P1) 🎯 MVP

**Goal**: o Operador cria uma sessão por mês/ano, envia as três planilhas e vê cada cartão passar a "Carregado" com os números do arquivo

**Independent Test**: criar a sessão "05/2026", enviar três planilhas válidas, ver os três cartões "Carregado", baixar os originais idênticos ao enviado

### Tests for User Story 1 ⚠️

> Escrever primeiro e confirmar que falham

- [X] T028 [P] [US1] Escrever `tests/Feature/Conciliation/CreateSessionTest.php`: cria sessão "Aberta" com criador e data; grava auditoria `SessionCreated`; recusa mês fora de 01 a 12 e período posterior ao mês corrente em `conciliation.display_timezone`; período já existente exige confirmação de sessão complementar; a lista mostra período, número, situação, criador, data e o status dos três cartões; busca por período e filtro por situação ficam na URL
- [X] T029 [P] [US1] Escrever `tests/Feature/Conciliation/ImportSpreadsheetTest.php`: arquivo válido deixa o cartão "Carregado" com nome, lançamentos, quem e quando; 100 linhas com 3 de valor zero ou negativo importam 97 e contam 3 ignoradas; "1.234,56", "1,234.56" e "1234.56" viram 123456 centavos; `.csv` do ERP com `;`, Windows-1252 e `31-MAY-26` é aceito com acentos corretos; unidade vem do cartão; linhas idênticas viram lançamentos separados; cópia exata fica no disco privado e o download devolve os mesmos bytes; novo envio válido substitui o anterior (anterior `replaced`, auditoria `FileReplaced`); reenviar o mesmo arquivo no mesmo cartão não cria nada; o mesmo arquivo no outro cartão de pagamentos é recusado; `.xlsx` com várias abas lê a primeira e marca `sheet_count`; arquivo sem uma coluna opcional do layout (por exemplo, DS_AUDIT) é aceito, guarda o nome em `missing_columns` e o cartão avisa; a execução fica indisponível com cartão faltando
- [X] T030 [P] [US1] Escrever `tests/Feature/Conciliation/ComplementarySessionTest.php`: com 200 pagamentos Social ativos em outra sessão do período, um arquivo com os mesmos 200 e 15 novos importa 15 e conta 200 já existentes; se as outras sessões têm duas linhas iguais e o arquivo traz três, uma é importada; arquivo cujas linhas todas já existem é aceito com zero lançamentos; lançamentos de arquivo `replaced` e de sessão de outro período não contam; pagamento igual em unidade diferente não conta
- [X] T031 [P] [US1] Escrever `tests/Feature/Conciliation/SpreadsheetTemplateTest.php`: `templates.download` devolve `autorizacoes` em `.xlsx` e `pagamentos` em `.csv` com `;`, cada um com o cabeçalho completo e uma linha de exemplo; outro valor responde 404; visitante é redirecionado

### Implementation for User Story 1

- [X] T032 [US1] Criar a migração `import_files` conforme data-model.md (FK da sessão com cascata, `slot`, `status`, `original_name`, `disk`, `path`, `size_bytes`, `sha256` char(64), `sheet_count`, `missing_columns` json nulo, `rows_imported`, `rows_skipped_value`, `rows_skipped_existing`, `rows_out_of_period`, `min_date`, `max_date` nulos, `period_divergence` boolean, `divergence_confirmed_by` FK nula, `divergence_confirmed_at`, `uploaded_by` FK, `replaced_at`, timestamps; índice único parcial em (`reconciliation_session_id`, `slot`) onde `status = 'active'`; índice em (`reconciliation_session_id`, `sha256`)), `app/Models/ImportFile.php` e `database/factories/ImportFileFactory.php` com os estados `replaced` e `withPeriodDivergence`
- [X] T033 [P] [US1] Criar a migração `authorization_entries` conforme data-model.md (FKs com cascata, `row_number`, `request`, `supplier_name`, `amount_cents` bigint, `authorized_on` date, `payment_method`, `card`, `payment_condition` nulos, `identity_key` char(64), `raw` json, `created_at`; único em (`import_file_id`, `row_number`); índices em `identity_key` e `reconciliation_session_id`), `app/Models/AuthorizationEntry.php` e `database/factories/AuthorizationEntryFactory.php`
- [X] T034 [P] [US1] Criar a migração `payment_entries` conforme data-model.md (FKs com cascata, `row_number`, `unit`, `supplier_name`, `amount_cents` e `obligation_amount_cents` bigint, `paid_on` date, `operation_code`, `obligation_number`, e nulos `operation_name`, `species`, `transaction_type`, `source_document`, `settlement_status`, `account_movement`, `identity_key`, `raw` json, `created_at`; único em (`import_file_id`, `row_number`); índices em (`unit`, `identity_key`), `operation_code` e `reconciliation_session_id`), `app/Models/PaymentEntry.php` e `database/factories/PaymentEntryFactory.php`
- [X] T035 [US1] Criar a migração `import_attempts` conforme data-model.md (FK da sessão com cascata, `slot`, `status`, `user_id`, `original_name`, `disk`, `path`, `size_bytes`, `sha256`, `progress`, `rows_total`, `rows_valid`, `rows_skipped_value`, `rows_out_of_period` nulos, `min_date`, `max_date`, `error_count` padrão 0, `first_errors` json nulo, `error_report_path` nulo, `message` nulo, `import_file_id` FK nula, timestamps), `app/Models/ImportAttempt.php` e `database/factories/ImportAttemptFactory.php`; acrescentar as relações `importFiles`, `activeFiles`, `importAttempts` em `app/Models/ReconciliationSession.php` e o estado `withActiveFiles` em `database/factories/ReconciliationSessionFactory.php`
- [X] T036 [US1] Criar `app/Actions/Conciliation/CreateSession.php`: valida o período, grava a sessão e a auditoria `SessionCreated` em uma transação; informa se já existe sessão no período
- [X] T037 [US1] Registrar em `routes/web.php`, dentro do grupo autenticado, as rotas de `contracts/routes.md` usadas nesta história (`sessions.index`, `sessions.show` com `{session}` numérico, `sessions.files.download`, `templates.download`) e acrescentar o link "Sessões" em `resources/views/navigation-menu.blade.php`
- [X] T038 [US1] Criar `app/Livewire/Sessions/Index.php` e `resources/views/livewire/sessions/index.blade.php`: Filament Table com período, número, situação, criador, data e status dos três cartões (carregando os arquivos ativos sem N+1); busca por período e filtro por situação gravados na URL; ação "Nova Sessão" em modal, com confirmação de sessão complementar; T028 passa
- [X] T039 [US1] Criar `app/Services/Import/SpreadsheetValidator.php` (primeira passada): confere os cabeçalhos exigidos (`requiredHeaders`) e anota as colunas opcionais ausentes, pula linhas em branco, aplica a ordem de regras de research.md R6, conta linhas válidas e ignoradas por valor, guarda menor e maior data e a quantidade de erros; sem lançamentos válidos resulta em recusa "sem lançamentos"
- [X] T040 [US1] Criar `app/Services/Import/SpreadsheetImporter.php` (segunda passada): em uma transação com `lockForUpdate` na sessão, confere que ela está `Open`, conta por chave de identidade os lançamentos ativos das outras sessões do mesmo período (consulta agregada no banco) e grava só o excedente, em lotes de `conciliation.upload.insert_chunk`; move o arquivo para o caminho definitivo no disco privado; cria `ImportFile` ativo; marca o anterior como `replaced` e grava auditoria `FileReplaced`
- [X] T041 [US1] Criar `app/Actions/Conciliation/SubmitSpreadsheet.php`: autoriza, confere sessão `Open`, tipo (`.xlsx`, `.csv`) e tamanho, calcula o SHA-256, devolve "já carregado" se for igual ao arquivo ativo do cartão, recusa se for igual ao arquivo ativo do outro cartão de pagamentos, guarda o arquivo recebido, cria `ImportAttempt` `Queued` e despacha o job
- [X] T042 [US1] Criar `app/Jobs/ProcessImportAttempt.php`: marca `Validating`, executa o validador atualizando `progress`, e então marca `Rejected` (com `message` ou `error_count`) ou `Persisting`, executa o importador e marca `Accepted` com `import_file_id`; qualquer exceção marca `Failed` e não deixa arquivo nem lançamento; nesta história, arquivo com datas fora do período é gravado sem confirmação (a confirmação entra na US3)
- [X] T043 [P] [US1] Criar `app/Services/Import/TemplateWriter.php` e `app/Http/Controllers/SpreadsheetTemplateController.php`; T031 passa
- [X] T044 [P] [US1] Criar `app/Http/Controllers/ImportFileDownloadController.php`: autoriza `view`, responde 404 se o arquivo não pertence à sessão e devolve o original com o nome enviado
- [X] T045 [US1] Criar `app/Livewire/Sessions/Show.php`, `resources/views/livewire/sessions/show.blade.php` e `resources/views/livewire/sessions/partials/slot-card.blade.php`: três cartões com status, nome do arquivo, lançamentos, ignoradas por valor, já existentes, quem, quando, aviso de primeira aba e aviso de colunas do layout não encontradas; envio com `WithFileUploads`; progresso por `wire:poll` só enquanto houver tentativa em andamento; links da planilha modelo e do original; "Executar Conciliação" desabilitada com a lista de cartões faltantes; sessão inexistente leva à lista com o aviso "Sessão não encontrada"; T029 e T030 passam
- [X] T046 [US1] Preencher em `lang/pt_BR/conciliation.php` todos os textos das telas e mensagens desta história

**Checkpoint**: US1 funcional e testável sozinha (MVP)

---

## Phase 4: User Story 2 - Recusar planilha inválida com relatório de erros (Priority: P2)

**Goal**: o arquivo com problema é recusado por inteiro, com resumo na tela e relatório completo para baixar

**Independent Test**: enviar planilha com erros conhecidos, ver o cartão inalterado, o total e os 10 primeiros erros, e baixar o relatório com linha, coluna, valor e motivo

### Tests for User Story 2 ⚠️

- [X] T047 [P] [US2] Escrever `tests/Feature/Conciliation/RejectSpreadsheetTest.php`: letras em valor na linha 45 recusam o arquivo, o cartão fica "Pendente" e nenhum lançamento nem arquivo definitivo é gravado; o resumo traz o total e os 10 primeiros erros com linha, coluna, valor e motivo; o relatório `.xlsx` baixado tem todos os erros na ordem do arquivo; envio inválido sobre cartão "Carregado" mantém o arquivo anterior; só cabeçalho, arquivo vazio e todas as linhas ignoradas por valor são recusados como "sem lançamentos"; tipo não aceito e tamanho acima do limite informam tipos e limite; planilha de pagamentos no cartão de Autorizações lista as colunas ausentes; data em formato não aceito e campo obrigatório vazio geram erro na linha e coluna certas; o relatório só é baixado por quem pode ver a sessão

### Implementation for User Story 2

- [X] T048 [P] [US2] Criar `app/Services/Import/ErrorReportWriter.php`: escreve em fluxo um `.xlsx` no disco privado com as colunas Linha, Coluna, Valor encontrado, Motivo
- [X] T049 [US2] Estender `app/Services/Import/SpreadsheetValidator.php` para enviar cada `RowError` ao `ErrorReportWriter`, guardar os 10 primeiros e o total, e produzir a mensagem de colunas ausentes; estender `app/Jobs/ProcessImportAttempt.php` para gravar `first_errors`, `error_count`, `error_report_path` e `message` na tentativa
- [X] T050 [US2] Criar `app/Http/Controllers/ImportErrorReportController.php` e a rota `sessions.attempts.errors` em `routes/web.php`
- [X] T051 [US2] Mostrar em `resources/views/livewire/sessions/partials/slot-card.blade.php` o resumo da recusa (motivo geral ou total e 10 primeiros erros) e o botão de download do relatório; acrescentar os motivos de erro a `lang/pt_BR/conciliation.php`; T047 passa

**Checkpoint**: US1 e US2 funcionam de forma independente

---

## Phase 5: User Story 3 - Aceitar planilha com datas fora do período mediante confirmação (Priority: P2)

**Goal**: arquivo válido com datas fora do mês/ano só entra depois de o Operador confirmar

**Independent Test**: enviar planilha de junho em sessão de maio, ver o alerta, cancelar (cartão não muda), reenviar e confirmar (cartão "Carregado" com indicador)

### Tests for User Story 3 ⚠️

- [X] T052 [P] [US3] Escrever `tests/Feature/Conciliation/PeriodDivergenceTest.php`: arquivo com datas fora do período para em `AwaitingConfirmation` com quantidade e intervalo de datas, sem lançamentos gravados e sem mudar o cartão; confirmar grava os lançamentos, marca `period_divergence`, quem e quando confirmou, e a auditoria `PeriodDivergenceConfirmed`; cancelar descarta o arquivo; quem enviou, ao abrir o painel de novo, cancela a própria tentativa pendente, e a de outro operador permanece; enviar outro arquivo ao mesmo cartão cancela a pendente; em pagamentos só DT_LIQUIDACAO conta (emissão e vencimento em outro mês não geram alerta); confirmar com a sessão já travada é recusado; `conciliation:prune-import-attempts` cancela confirmações com mais de `confirmation_ttl_minutes` marca como `Failed` as tentativas paradas em `Queued`, `Validating` ou `Persisting` há mais de `stale.attempt_minutes`, e apaga tentativas encerradas, arquivos recebidos e relatórios de erro com mais de `retention_hours`

### Implementation for User Story 3

- [X] T053 [US3] Estender `app/Services/Import/SpreadsheetValidator.php` para contar as linhas cuja data do período cai fora do mês/ano da sessão, e `app/Jobs/ProcessImportAttempt.php` para parar em `AwaitingConfirmation` quando a contagem for maior que zero
- [X] T054 [P] [US3] Criar `app/Jobs/PersistImportAttempt.php`: executa o importador para uma tentativa confirmada e marca `Accepted` ou `Failed`
- [X] T055 [US3] Criar `app/Actions/Conciliation/ConfirmPeriodDivergence.php` (confere tentativa `AwaitingConfirmation` e sessão `Open`, grava auditoria `PeriodDivergenceConfirmed`, marca `Persisting` e despacha `PersistImportAttempt`) e fazer `app/Services/Import/SpreadsheetImporter.php` gravar `period_divergence`, `divergence_confirmed_by` e `divergence_confirmed_at`
- [X] T056 [P] [US3] Criar `app/Actions/Conciliation/CancelImportAttempt.php`: marca `Cancelled` e apaga o arquivo recebido
- [X] T057 [US3] Em `app/Livewire/Sessions/Show.php` e `app/Actions/Conciliation/SubmitSpreadsheet.php`, cancelar, ao montar o painel, as tentativas `AwaitingConfirmation` do cartão que pertencem ao próprio usuário e, ao receber novo envio, todas as do cartão; mostrar em `resources/views/livewire/sessions/partials/slot-card.blade.php` o alerta com quantidade e intervalo, as ações "Confirmar mesmo assim" e "Cancelar", e o indicador de divergência no cartão carregado
- [X] T058 [US3] Criar `app/Console/Commands/PruneImportAttempts.php` (`conciliation:prune-import-attempts`) e agendá-lo a cada 10 minutos em `routes/console.php`; T052 passa

**Checkpoint**: US1 a US3 funcionam de forma independente

---

## Phase 6: User Story 4 - Executar a conciliação e travar a sessão (Priority: P2)

**Goal**: com os três cartões carregados, a execução trava a sessão, roda em fila com progresso e termina "Processada" ou volta a "Aberta" em caso de falha

**Independent Test**: com um motor falso, executar e ver "Em processamento" e depois "Processada"; envio, substituição e exclusão são recusados

### Tests for User Story 4 ⚠️

- [X] T059 [P] [US4] Criar `tests/Fakes/FakeReconciliationEngine.php` (registra chamadas, informa progresso, pode falhar sob comando) e escrever `tests/Feature/Conciliation/ExecuteReconciliationTest.php`: executar com três arquivos ativos muda para `Processing`, grava auditoria `ReconciliationRequested` com os ids dos três arquivos e despacha `RunReconciliation` após o commit; com cartão faltando ou sessão não `Open` é recusado; sucesso muda para `Processed`, grava `first_processed_at` e `processed_at` e zera `result_stale`; falha chama `discardResult`, volta a `Open`, preenche `last_failure` e mantém os três arquivos ativos; segunda execução com a sessão em `Processing` não despacha outro job; envio e confirmação de divergência são recusados em `Processing` e `Processed`; o progresso informado pelo motor é gravado em `progress`; com `conciliation.engine_enabled` false a ação é recusada; `conciliation:recover-stuck-sessions` devolve a `Open` a sessão em `Processing` há mais de `stale.processing_minutes`, chamando `discardResult` e preenchendo `last_failure`, e não toca nas demais

### Implementation for User Story 4

- [X] T060 [P] [US4] Criar a interface `app/Contracts/ReconciliationEngine.php` conforme `contracts/application-interfaces.md`
- [X] T061 [US4] Criar `app/Actions/Conciliation/ExecuteReconciliation.php`: transação com `lockForUpdate`, confere motor habilitado, sessão `Open` e três arquivos ativos, muda para `Processing`, grava `processing_started_at` e a auditoria, e despacha o job após o commit
- [X] T062 [US4] Criar `app/Jobs/RunReconciliation.php` (`ShouldQueue`, `ShouldBeUnique` por sessão): chama `discardResult` e `run` com o retorno de progresso gravando `progress`; no sucesso marca `Processed`; no método `failed` chama `discardResult`, volta a `Open` e preenche `last_failure`
- [X] T063 [US4] Criar `app/Console/Commands/RecoverStuckSessions.php` (`conciliation:recover-stuck-sessions`) e agendá-lo a cada 10 minutos em `routes/console.php`
- [X] T064 [US4] Em `app/Livewire/Sessions/Show.php` e `resources/views/livewire/sessions/show.blade.php`: ação "Executar Conciliação" com confirmação; explicação quando o motor está desligado; cartões travados e progresso por `wire:poll` em `Processing`; aviso da última falha; recusa de envio com a mensagem de sessão travada, conferida no servidor a cada ação; T059 passa

**Checkpoint**: US1 a US4 funcionam; a execução real só é oferecida quando o Módulo 2 ligar `conciliation.engine_enabled`

---

## Phase 7: User Story 5 - Reabrir sessão processada para substituir arquivo (Priority: P3)

**Goal**: uma sessão processada volta a "Aberta" para trocar uma planilha, desde que não haja decisões manuais

**Independent Test**: reabrir uma sessão "Processada", substituir uma planilha, executar de novo; a auditoria registra reabertura e substituição e o arquivo anterior continua disponível

### Tests for User Story 5 ⚠️

- [X] T065 [P] [US5] Escrever `tests/Feature/Conciliation/ReopenSessionTest.php`: reabrir sessão `Processed` volta a `Open`, marca `result_stale` e grava auditoria `SessionReopened` com situação anterior e nova; o painel avisa que o resultado anterior não vale; sessão `Open` ou `Processing` não reabre; com `blockingDecisionCount` maior que zero a reabertura é recusada, a sessão continua `Processed` e a mensagem informa a quantidade; com zero a reabertura ocorre; substituir planilha em sessão reaberta valida o arquivo novo, mantém o anterior como `replaced` com seus lançamentos e download, e grava `FileReplaced`; nova execução chama `discardResult` antes de `run`

### Implementation for User Story 5

- [X] T066 [P] [US5] Criar a interface `app/Contracts/ReconciliationResultInspector.php` e `app/Services/Import/NullReconciliationResultInspector.php` (devolve zero), ligada em `app/Providers/AppServiceProvider.php`
- [X] T067 [US5] Criar `app/Actions/Conciliation/ReopenSession.php`: transação com `lockForUpdate`, confere sessão `Processed` e `blockingDecisionCount` igual a zero, muda para `Open`, marca `result_stale` e grava a auditoria
- [X] T068 [US5] Em `app/Livewire/Sessions/Show.php` e `resources/views/livewire/sessions/show.blade.php`: ação "Reabrir Sessão" com confirmação, mensagem de recusa com a quantidade de decisões manuais, e aviso de resultado inválido enquanto `result_stale`; T065 passa

**Checkpoint**: US1 a US5 funcionam de forma independente

---

## Phase 8: User Story 6 - Excluir sessão criada por engano (Priority: P3)

**Goal**: sessão nunca processada é apagada com arquivos e lançamentos; o Administrador vê quem excluiu e quando

**Independent Test**: criar uma sessão, enviar uma planilha, excluir; sessão e arquivo deixam de existir, a auditoria permanece e sessão já processada não oferece exclusão

### Tests for User Story 6 ⚠️

- [X] T069 [P] [US6] Escrever `tests/Feature/Conciliation/DeleteSessionTest.php`: excluir sessão nunca processada apaga sessão, arquivos, lançamentos e tentativas, remove os arquivos do disco e grava auditoria `SessionDeleted` com período, número, situação e nomes dos arquivos; o registro permanece após a exclusão; sessão com `first_processed_at` preenchido (processada ou reaberta) e sessão `Processing` são recusadas com o motivo; depois da exclusão, os lançamentos não contam mais na comparação de outra sessão do período; ação sobre sessão já excluída leva à lista com "Sessão não encontrada"
- [X] T070 [P] [US6] Escrever `tests/Feature/Conciliation/SessionHistoryTest.php`: `sessions.history` lista exclusões e reaberturas com usuário, data e hora, rótulo da sessão e arquivos removidos; Operador recebe acesso negado; visitante é redirecionado

### Implementation for User Story 6

- [X] T071 [US6] Criar `app/Actions/Conciliation/DeleteSession.php`: transação com `lockForUpdate`, confere sessão `Open` e `first_processed_at` nulo, grava a auditoria com o retrato da sessão, apaga a sessão (cascata) e remove os arquivos do disco após o commit
- [X] T072 [US6] Acrescentar a ação "Excluir" com confirmação em `app/Livewire/Sessions/Show.php` e na tabela de `app/Livewire/Sessions/Index.php`, oculta e recusada no servidor para sessão já processada; T069 passa
- [X] T073 [US6] Criar `app/Livewire/Sessions/History.php`, `resources/views/livewire/sessions/history.blade.php` e a rota `sessions.history` em `routes/web.php` (declarada antes de `sessions.show`), com Filament Table sobre `audit_logs` filtrada por `SessionDeleted` e `SessionReopened`, e o link no menu visível só ao Administrador; T070 passa

**Checkpoint**: todas as histórias funcionam de forma independente

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: qualidade que atravessa as histórias

- [X] T074 [P] Escrever `tests/Feature/Conciliation/ImportPerformanceTest.php`: uma planilha de pagamentos de 10.000 linhas é validada e importada em menos de 60 segundos (SC-004) sem carregar o arquivo inteiro na memória
- [X] T075 [P] Escrever `tests/Feature/Conciliation/ConcurrentUploadTest.php`: gravar um segundo `ImportFile` ativo no mesmo cartão viola o índice único parcial e o importador devolve recusa em vez de erro não tratado
- [X] T076 [P] Rever `lang/pt_BR/conciliation.php` e as três telas em `resources/views/livewire/sessions/`: nenhum texto fixo, contraste, navegação por teclado e rótulos para leitor de tela (Princípio II)
- [X] T077 [P] Atualizar `TECH_STACK.md`: trocar a citação de `TeamPolicy` pelo modelo de papéis do Princípio VIII e registrar OpenSpout e as versões reais de Livewire e Tailwind
- [X] T078 Rodar `vendor/bin/pint --dirty --format agent` e `php artisan test --compact tests/Unit/Conciliation tests/Feature/Conciliation`; perguntar ao responsável se deseja rodar a suíte completa
- [ ] T079 Executar o roteiro de `specs/001-session-file-import/quickstart.md` com planilhas fictícias e registrar no próprio arquivo qualquer passo que divergir

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências
- **Foundational (Phase 2)**: depende do Setup; bloqueia todas as histórias
- **US1 (Phase 3)**: depende da fundação
- **US2 (Phase 4)** e **US3 (Phase 5)**: dependem da US1 (estendem o validador, o job e o cartão)
- **US4 (Phase 6)**: depende da US1; não depende de US2 nem de US3
- **US5 (Phase 7)**: depende da US4 (precisa de sessão processada)
- **US6 (Phase 8)**: depende da US1; o caso "sessão já processada" usa os estados da factory, não a US4
- **Polish (Phase 9)**: depende das histórias desejadas

### Ordem dentro da fundação

- T007 antes de T009, T015 e T017
- T008 antes de T009 e T010; T014 antes de T015 e T016
- T009 antes de T013
- T018 antes de T020; T019 antes de T021
- T023 antes de T025, T026 e T027; T020, T021 e T022 antes de T025 e T026
- T001 antes de T024 e T027

### Ordem dentro de cada história

- Testes escritos e falhando antes da implementação
- Migrações e modelos antes de serviços; serviços antes de actions e jobs; actions antes das telas
- US1: T032 antes de T033, T034 e T035; T039 e T040 antes de T042; T041 e T042 antes de T045

### Arquivos compartilhados (não paralelizar)

- `app/Services/Import/SpreadsheetValidator.php`: T039, T049, T053
- `app/Jobs/ProcessImportAttempt.php`: T042, T049, T053
- `app/Livewire/Sessions/Show.php` e suas views: T045, T051, T057, T064, T068, T072
- `routes/web.php`: T037, T050, T073
- `routes/console.php`: T058, T063
- `app/Providers/AppServiceProvider.php`: T010, T027, T066
- `lang/pt_BR/conciliation.php`: T046, T051 e demais que acrescentam textos

### Parallel Opportunities

- Setup: T002, T003, T004 e T005
- Fundação: T007; depois T018 e T019; depois T020, T021 e T022; depois T024, T025 e T026
- US1: os quatro testes (T028 a T031); depois T033 e T034; depois T043 e T044
- US2, US3 e US4 podem ser feitas por pessoas diferentes depois da US1, desde que combinem a ordem nos arquivos compartilhados
- Polish: T074 a T077

---

## Parallel Example: User Story 1

```bash
# Testes da US1, escritos juntos:
Task: "Escrever tests/Feature/Conciliation/CreateSessionTest.php"
Task: "Escrever tests/Feature/Conciliation/ImportSpreadsheetTest.php"
Task: "Escrever tests/Feature/Conciliation/ComplementarySessionTest.php"
Task: "Escrever tests/Feature/Conciliation/SpreadsheetTemplateTest.php"

# Tabelas de lançamentos, depois de import_files (T032):
Task: "Criar migração, modelo e factory de authorization_entries"
Task: "Criar migração, modelo e factory de payment_entries"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Fase 1: Setup
2. Fase 2: Fundação
3. Fase 3: US1
4. **Parar e validar**: passos 1 a 6 e 11 do quickstart.md
5. Demonstrar: sessões criadas e planilhas reais carregadas, ainda sem execução
6. Não liberar a usuários antes da US3: até lá, planilha com datas fora do período entra sem
   confirmação, o que descumpre FR-021

### Incremental Delivery

1. Setup + Fundação
2. US1: criar sessão e carregar planilhas (MVP)
3. US2: relatório de erros, que torna a recusa útil ao Operador
4. US3: confirmação de divergência de período
5. US4: execução e travamento, oferecida ao usuário quando o Módulo 2 existir
6. US5: reabertura
7. US6: exclusão e histórico
8. Polish

### Parallel Team Strategy

Com mais de uma pessoa, depois da US1: uma segue US2 e US3 (mesmos arquivos de validação), outra
segue US4 e US5 (estado da sessão), outra faz US6.

---

## Notes

- 79 tarefas: Setup 6, Fundação 21, US1 19, US2 5, US3 7, US4 6, US5 4, US6 5, Polish 6
- [P] indica arquivos diferentes e nenhuma dependência pendente
- Confirmar que cada teste falha antes de implementar
- Fazer commit ao fim de cada tarefa ou grupo lógico, no formato Conventional Commits
- Pendências de dados que não bloqueiam: unicidade de OBRIGACAO + COD_OPERACAO + CD_MOVIMENTO_CONTA e grafia de quatro cabeçalhos do ELO; a grafia não bloqueia mais (colunas opcionais ausentes geram aviso) e, se mudar, afeta só T026
