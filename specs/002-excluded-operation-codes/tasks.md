---

description: "Task list for Módulo 5 - Códigos de Operação Excluídos da Conciliação"
---

# Tasks: Códigos de Operação Excluídos da Conciliação (Módulo 5)

**Input**: Design documents from `/specs/002-excluded-operation-codes/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Incluídos. A constituição (Princípio III, inegociável) exige teste escrito antes da
implementação. Em cada história, as tarefas de teste vêm primeiro e DEVEM falhar antes do código.

**Organization**: Tarefas agrupadas por história de usuário, para que cada uma seja implementada e
testada de forma independente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependência de tarefa incompleta)
- **[Story]**: história da spec a que a tarefa pertence (US1 a US5)
- Cada descrição traz o caminho exato do arquivo

## Convenções para todas as tarefas

- Criar arquivos com `php artisan make:* --no-interaction` (model, migration, factory, job,
  livewire `--class`, command, test `--phpunit`, class). Enums são escritos direto em
  `app/Enums`, porque `make:enum` os coloca em `app/`.
- Ativar as skills `livewire-development` e `tailwindcss-development` ao mexer em componente ou
  em Blade. Seguir as APIs do Filament já usadas em `app/Livewire/Sessions/Index.php` e
  `History.php`.
- Textos de tela vêm de `lang/pt_BR/conciliation.php`; nenhum texto fixo em Blade ou PHP.
- Datas e horas em UTC, exibidas em `config('conciliation.display_timezone')`; valores fixos
  como Enum.
- Regras de negócio em `app/Actions/Conciliation` e `app/Services/ExcludedCodes`; componente e
  comando só delegam. Recusas usam `ActionRefusedException`.
- Rodar o teste afetado após cada tarefa (`php artisan test --compact <arquivo>`) e
  `vendor/bin/pint --dirty --format agent` antes de encerrar cada fase.
- Planilhas de teste são geradas no próprio teste, com `tests/Concerns/BuildsSpreadsheets.php`.
  Os relatórios reais do ERP NÃO entram no repositório.

## Decisões já tomadas

- **Sem pacote novo**: nenhuma tarefa altera `composer.json`.
- **Direção visual** (T019, T024, T038, T046): padrão do Jetstream, definido pelo responsável em
  2026-10-08.
- **História 2 parcial**: este módulo entrega a fotografia da lista; marcar pagamentos e
  registrar códigos por execução fica no Módulo 2 (research R8).
- **Permissão por comando**: não há tela de usuários (research R2).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: configuração e textos usados por todas as histórias.

- [X] T001 Acrescentar o bloco `excluded_codes` em `config/conciliation.php`, com `max_size_mb` (`CONCILIATION_EXCLUDED_CODES_MAX_SIZE_MB`, padrão 5) e `max_rows` (`CONCILIATION_EXCLUDED_CODES_MAX_ROWS`, padrão 10000), e as duas variáveis comentadas em `.env.example`
- [X] T002 [P] Acrescentar em `lang/pt_BR/conciliation.php` as chaves `excluded_codes.*` (menu, título, colunas, botões, mensagens de sucesso, de recusa e de importação de `contracts/routes.md`, motivos de erro por linha de `contracts/import-file.md`; título da tela "Códigos de Operação Excluídos" e item de menu "Códigos excluídos"), `permissions.manage_excluded_codes` e `audit.actions.*` para `excluded_code_added` ("Inclusão manual"), `excluded_codes_imported` ("Inclusão por arquivo"), `excluded_code_removed` ("Exclusão"), `permission_granted` e `permission_revoked`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Enums, tabelas, modelos, permissão e a tela protegida, que todas as histórias usam.

**⚠️ CRITICAL**: nenhuma história começa antes desta fase.

- [X] T003 [P] Criar o Enum `UserPermission` em `app/Enums/UserPermission.php`, com o caso `ManageExcludedCodes = 'manage_excluded_codes'` e `label()` lendo `conciliation.permissions.*`
- [X] T004 [P] Criar o Enum `ExcludedCodeSource` em `app/Enums/ExcludedCodeSource.php`, com `Manual = 'manual'` e `File = 'file'` e `label()`
- [X] T005 [P] Criar o Enum `ExcludedCodeImportStatus` em `app/Enums/ExcludedCodeImportStatus.php`, com `Queued`, `Processing`, `Completed`, `Rejected`, `Failed` (valores em minúsculas) e `isInProgress()` verdadeiro para `Queued` e `Processing`
- [X] T006 Acrescentar a `app/Enums/AuditAction.php` os casos `ExcludedCodeAdded`, `ExcludedCodesImported`, `ExcludedCodeRemoved`, `PermissionGranted` e `PermissionRevoked`, com os valores de T002
- [X] T007 [P] Escrever `tests/Unit/Conciliation/OperationCodeTest.php`: `normalize()` remove espaços e espaço não separável nas pontas, devolve null para vazio, preserva maiúsculas e zeros à esquerda; `isValid()` aceita 1 e 20 caracteres de letras e dígitos e recusa vazio, 21 caracteres, hífen, espaço interno e acento
- [X] T008 Criar `app/Services/ExcludedCodes/OperationCode.php` com `normalize(?string): ?string` e `isValid(string): bool` (padrão `^[A-Za-z0-9]{1,20}$`), até T007 passar
- [X] T009 Criar a migração `create_user_permissions_table` em `database/migrations`: `user_id` FK `users` com `restrictOnDelete`, `permission` string, `granted_by` FK `users` com `restrictOnDelete`, `created_at` obrigatório; único em (`user_id`, `permission`)
- [X] T010 Criar a migração `create_excluded_code_imports_table` em `database/migrations`: `user_id` FK `users` com `restrictOnDelete`, `status` string indexado, `original_name`, `disk`, `path`, `size_bytes` unsignedBigInteger, `added_count` e `ignored_count` unsignedInteger com padrão 0, `errors` json opcional, `failure_message` opcional, `started_at` e `finished_at` opcionais, `timestamps`
- [X] T011 Criar a migração `create_excluded_operation_codes_table` em `database/migrations` (depois de T010): `code` string(20) único, `description` string(255) opcional, `source` string, `excluded_code_import_id` FK opcional com `restrictOnDelete`, `created_by` FK `users` com `restrictOnDelete`, `created_at` obrigatório e indexado; sem `updated_at`
- [X] T012 [P] Criar o modelo `app/Models/UserPermissionGrant.php` (tabela `user_permissions`, sem `updated_at`, cast de `permission` para `UserPermission`, relações `user()` e `grantor()`) e `database/factories/UserPermissionGrantFactory.php`
- [X] T013 [P] Criar o modelo `app/Models/ExcludedCodeImport.php` (casts de `status` para o Enum, `errors` para array e datas; relações `user()` e `codes()`) e `database/factories/ExcludedCodeImportFactory.php` com estados para cada situação
- [X] T014 [P] Criar o modelo `app/Models/ExcludedOperationCode.php` (sem `updated_at`, cast de `source` para o Enum, relações `creator()` e `import()`) e `database/factories/ExcludedOperationCodeFactory.php` gerando códigos numéricos únicos de 8 dígitos
- [X] T015 Escrever `tests/Feature/Conciliation/UserPermissionTest.php`: Administrador tem a permissão sem linha na tabela; Operador não tem; Operador com concessão tem; a mesma concessão duas vezes viola a unicidade
- [X] T016 Acrescentar a `app/Models/User.php` a relação `permissionGrants()` e `hasPermission(UserPermission $permission): bool`, e a `database/factories/UserFactory.php` o estado `withPermission(UserPermission $permission)`, até T015 passar
- [X] T017 Em `app/Providers/AppServiceProvider.php`, definir o gate `manage-excluded-codes` com `hasPermission(UserPermission::ManageExcludedCodes)` e acrescentar ao mapa polimórfico `excluded_operation_code`, `excluded_code_import` e `user`
- [X] T018 Escrever `tests/Feature/Conciliation/ExcludedCodeAuthorizationTest.php`: visitante é redirecionado ao login; Operador recebe 403 em `/codigos-excluidos`; Administrador recebe 200; Operador com a concessão recebe 200
- [X] T019 Criar o componente `app/Livewire/ExcludedCodes/Index.php` (classe, com `HasTable`, `HasActions`, `HasSchemas` como em `Sessions\History`), autorizando `manage-excluded-codes` em `mount()`, com tabela de `ExcludedOperationCode` ainda sem ações, e a view `resources/views/livewire/excluded-codes/index.blade.php` no layout do Jetstream
- [X] T020 Registrar em `routes/web.php`, no grupo autenticado, `Route::livewire('/codigos-excluidos', Index::class)->can('manage-excluded-codes')->name('excluded-codes.index')`, até T018 passar

**Checkpoint**: a tela abre vazia para quem tem a permissão e é negada aos demais.

---

## Phase 3: User Story 1 - Cadastrar manualmente um código a desconsiderar (Priority: P1) 🎯 MVP

**Goal**: informar um código e uma descrição opcional e vê-lo na lista, com data e responsável.

**Independent Test**: cadastrar um código com descrição, conferir que aparece na lista com data e
responsável, e tentar cadastrá-lo de novo para confirmar a recusa por duplicidade.

### Tests for User Story 1 ⚠️

- [X] T021 [US1] Escrever `tests/Feature/Conciliation/AddExcludedCodeTest.php`: cadastro com descrição grava `source` Manual, `created_by` e `created_at`; cadastro sem descrição grava descrição nula; " 20150652 " é gravado como "20150652"; código já cadastrado é recusado com a mensagem de duplicidade, também quando informado com espaços; código em branco, com hífen ou com 21 caracteres não grava e mostra erro no campo; descrição com 256 caracteres é recusada; o cadastro grava um `AuditLog` `ExcludedCodeAdded` com usuário, `label` igual ao código e `after` com código e descrição; Operador sem permissão não consegue chamar a ação

### Implementation for User Story 1

- [X] T022 [US1] Criar `app/Actions/Conciliation/AddExcludedCode.php`: normaliza com `OperationCode`, valida, grava em transação com `AuditRecorder` e converte a violação de unicidade em `ActionRefusedException` com a mensagem "código já cadastrado"
- [X] T023 [P] [US1] Criar o Form Object `app/Livewire/Forms/ExcludedCodeForm.php` com `code` (obrigatório, 1 a 20 letras ou dígitos depois de remover espaços) e `description` (opcional, até 255), com mensagens de `lang/pt_BR`
- [X] T024 [US1] Em `app/Livewire/ExcludedCodes/Index.php`, acrescentar o modal "Adicionar código" (propriedade de abertura, `save()` que autoriza o gate, valida o form, chama `AddExcludedCode`, notifica o sucesso e limpa o form) e as colunas da tabela: código, descrição, data de cadastro (`d/m/Y H:i`, no fuso de exibição) e responsável (`creator.name`), com `creator` carregado junto
- [X] T025 [US1] Em `resources/views/livewire/excluded-codes/index.blade.php`, acrescentar o botão e o modal de cadastro com os componentes Blade do Jetstream (`x-dialog-modal`, `x-input`, `x-input-error`), como em `resources/views/livewire/sessions/index.blade.php`, até T021 passar

**Checkpoint**: a lista já pode ser montada à mão. MVP entregue.

---

## Phase 4: User Story 2 - Desconsiderar na conciliação os pagamentos com código excluído (Priority: P1)

**Goal**: oferecer ao motor (Módulo 2) uma fotografia imutável da lista, com comparação exata.

**Independent Test**: tirar a fotografia, alterar a lista e conferir que a fotografia não mudou;
conferir que `contains()` ignora espaços nas pontas e diferencia zeros à esquerda.

**Escopo**: marcar pagamentos como excluídos e registrar os códigos por execução (cenários 1 a 3
da história) é do Módulo 2. Ver `contracts/application-interfaces.md`.

### Tests for User Story 2 ⚠️

- [X] T026 [US2] Escrever `tests/Feature/Conciliation/ExcludedCodeSnapshotTest.php`: `snapshot()->codes()` devolve os códigos em ordem crescente; `contains()` é verdadeiro para o código exato e para o código com espaços nas pontas, e falso para nulo, vazio, "123" quando a lista tem "00123" e maiúsculas diferentes; adicionar ou remover um código depois da fotografia não altera a fotografia já tirada; lista vazia devolve fotografia vazia; a importação de uma planilha de pagamentos com código presente na lista continua gravando esses pagamentos (FR-026, usando `tests/Concerns/SubmitsSpreadsheets.php`)

### Implementation for User Story 2

- [X] T027 [P] [US2] Criar `app/Services/ExcludedCodes/ExcludedCodeSnapshot.php`, `final readonly`, construído com a lista de códigos, com `contains(?string $operationCode): bool` (normaliza com `OperationCode` e compara de forma exata) e `codes(): array` em ordem crescente
- [X] T028 [US2] Criar `app/Services/ExcludedCodes/ExcludedOperationCodes.php` com `snapshot(): ExcludedCodeSnapshot`, lendo só a coluna `code` em uma consulta, até T026 passar

**Checkpoint**: o contrato que o Módulo 2 vai consumir existe e está testado.

---

## Phase 5: User Story 3 - Importar códigos de uma planilha (Priority: P2)

**Goal**: enviar uma planilha, conferi-la inteira e gravar os códigos novos, ou recusar tudo
indicando cada linha com problema.

**Independent Test**: importar um arquivo com 10 códigos novos e 5 já cadastrados e ver "10
adicionados, 5 ignorados"; importar um arquivo com uma linha inválida e ver que nada foi gravado
e que a linha foi indicada.

### Tests for User Story 3 ⚠️

- [X] T029 [P] [US3] Escrever `tests/Unit/Conciliation/ExcludedCodeFileParserTest.php` com as tabelas de `contracts/import-file.md`: cabeçalho `COD_OPERACAO` em qualquer caixa é pulado e outra primeira linha é lida como código; linha com as duas colunas vazias é pulada; código numérico (float 20150652.0) vira "20150652"; "00123" é preservado; código vazio com descrição gera erro "código em branco" na coluna `COD_OPERACAO`; código com caractere inválido, com letra acentuada ou com 21 caracteres gera erro na coluna `COD_OPERACAO`; descrição com 256 caracteres gera erro na coluna `DESCRICAO`; linha com os dois problemas gera dois erros; arquivo com as colunas invertidas (descrição na primeira) é recusado; todos os erros são listados com linha no arquivo, coluna e motivo; código repetido três vezes conta uma vez e duas ignoradas; terceira coluna em diante é desconsiderada; arquivo vazio ou só com cabeçalho é recusado como "sem códigos"; arquivo com `max_rows + 1` linhas de dados é recusado pelo limite, sem erros por linha
- [X] T030 [P] [US3] Escrever `tests/Feature/Conciliation/ImportExcludedCodesTest.php` (fila síncrona): `.csv` com `;`, `.csv` com `,` e `.xlsx` válidos gravam os códigos com `source` File e o vínculo com a importação; 10 novos e 5 já cadastrados resultam em `Completed`, `added_count` 10, `ignored_count` 5 e na mensagem do resumo; `.xlsx` com duas abas lê só a primeira; arquivo de 100 linhas com a linha 45 inválida resulta em `Rejected`, nenhum código gravado e erro com linha 45, coluna e motivo; descrição de código já cadastrado não muda; todos já cadastrados conclui com zero adicionados; `.csv` em Windows-1252 grava a descrição acentuada correta; extensão `.txt` e arquivo acima de `max_size_mb` são recusados na validação do envio; segundo envio do mesmo usuário com importação em andamento é recusado; importação concluída grava um `AuditLog` `ExcludedCodesImported` com o nome do arquivo e os códigos adicionados; um código cadastrado por outro usuário entre a leitura e a gravação conta como ignorado e não entra na lista da auditoria; importação recusada não grava auditoria; o arquivo enviado fica no disco privado sem alteração; Operador sem permissão não consegue enviar
- [X] T031 [P] [US3] Escrever `tests/Feature/Conciliation/ExcludedCodeTemplateTest.php`: `/codigos-excluidos/modelo` devolve `modelo-codigos-excluidos.xlsx` com os cabeçalhos `COD_OPERACAO` e `DESCRICAO` e uma linha de exemplo; o próprio modelo é importável e grava um código; sem a permissão responde 403
- [X] T032 [P] [US3] Escrever `tests/Feature/Conciliation/ExcludedCodeImportMaintenanceTest.php`: `conciliation:prune-import-attempts` apaga importações `Rejected` e `Failed` mais antigas que `conciliation.attempts.retention_hours`, com o arquivo, e mantém as `Completed` e as recentes; `conciliation:recover-stuck-sessions` marca como `Failed` a importação `Queued` ou `Processing` parada além de `conciliation.stale.attempt_minutes` e não toca nas demais

### Implementation for User Story 3

- [X] T033 [P] [US3] Criar `app/Services/ExcludedCodes/ParsedExcludedCodeFile.php`, objeto de resultado com os códigos válidos (código e descrição), os erros (`row`, `column`, `reason`), a quantidade de repetidos no arquivo, o motivo de recusa geral e `isAccepted()`
- [X] T034 [US3] Criar `app/Services/ExcludedCodes/ExcludedCodeFileParser.php`, que recebe as linhas de `SpreadsheetReader::rows()`, aplica as regras de research R5 na ordem, usa `CellText::from()` e `OperationCode`, para de ler ao passar de `conciliation.excluded_codes.max_rows` ou de `conciliation.upload.blank_rows_limit` linhas em branco seguidas, até T029 passar
- [X] T035 [US3] Criar `app/Services/ExcludedCodes/ExcludedCodeImporter.php`: em uma transação, carrega os códigos existentes, insere os novos em lotes de `conciliation.upload.insert_chunk` com `insertOrIgnore`, calcula adicionados pela quantidade de fato inserida e ignorados pelo restante, atualiza a importação para `Completed` e grava a auditoria `ExcludedCodesImported` com a lista de códigos lida do banco pelo `excluded_code_import_id` da importação
- [X] T036 [US3] Criar o job `app/Jobs/ImportExcludedCodes.php` (`ShouldQueue`, `tries` 1, `timeout` 300): marca `Processing`, lê com `ExcludedCodeFileParser`, grava com `ExcludedCodeImporter` ou marca `Rejected` com `errors` ou `failure_message`; `failed()` marca `Failed`
- [X] T037 [US3] Criar `app/Actions/Conciliation/SubmitExcludedCodeImport.php`: recusa quando o usuário tem importação em andamento, guarda o arquivo no disco privado em `excluded-codes/{id}/`, cria a importação `Queued` e despacha o job
- [X] T038 [US3] Em `app/Livewire/ExcludedCodes/Index.php`, acrescentar `WithFileUploads`, a regra de envio (extensões `csv` e `xlsx`, tamanho de `conciliation.excluded_codes.max_size_mb`), o método que autoriza o gate e chama `SubmitExcludedCodeImport`, e a propriedade computada com a última importação do usuário
- [X] T039 [US3] Em `resources/views/livewire/excluded-codes/index.blade.php`, acrescentar o bloco de importação com os cinco estados de `contracts/routes.md` (na recusa, uma tabela com linha, coluna e motivo de cada erro), `wire:poll.2s` só enquanto houver importação em andamento, envio desabilitado nesse período e o link "Baixar Planilha Modelo", até T030 passar
- [X] T040 [P] [US3] Criar `app/Services/ExcludedCodes/ExcludedCodeTemplateWriter.php`, que gera o `.xlsx` com OpenSpout, cabeçalhos `COD_OPERACAO` e `DESCRICAO` e a linha de exemplo com o código como texto
- [X] T041 [US3] Criar `app/Http/Controllers/ExcludedCodeTemplateController.php` (invocável, resposta em stream) e a rota `excluded-codes.template` em `routes/web.php` com `->can('manage-excluded-codes')`, até T031 passar
- [X] T042 [US3] Estender `app/Console/Commands/PruneImportAttempts.php` para apagar importações de códigos `Rejected` e `Failed` vencidas, com o arquivo
- [X] T043 [US3] Marcar como `Failed` a importação de códigos parada, até T032 passar. Feito em `app/Console/Commands/PruneImportAttempts.php`, e não em `RecoverStuckSessions.php`, porque é lá que o Módulo 1 já trata os envios parados (research R12)

**Checkpoint**: a lista pode ser montada de uma vez a partir de uma planilha.

---

## Phase 6: User Story 4 - Consultar e remover códigos da lista (Priority: P2)

**Goal**: consultar a lista em páginas, buscar, ordenar e remover um código com confirmação.

**Independent Test**: com mais de 50 códigos, conferir a paginação, buscar por parte de uma
descrição, ordenar por data e remover um código confirmando a ação.

### Tests for User Story 4 ⚠️

- [X] T044 [US4] Escrever `tests/Feature/Conciliation/ExcludedCodeListTest.php`: com 60 códigos, a primeira página não mostra todos; a busca encontra por trecho do código e por trecho da descrição; a ordenação por código, por descrição e por data vale para a lista inteira (o primeiro da ordem aparece na primeira página); busca e ordenação ficam na URL e uma nova montagem com a mesma URL mostra a mesma lista; remover com confirmação apaga o código e grava `AuditLog` `ExcludedCodeRemoved` com código e descrição em `before`; o código removido deixa de constar em `snapshot()`; cadastrar de novo o código removido cria um registro novo; remover um código já removido por outro usuário mostra o aviso sem erro; Operador sem permissão não consegue remover

### Implementation for User Story 4

- [X] T045 [US4] Criar `app/Actions/Conciliation/RemoveExcludedCode.php`: em transação, trava a linha, grava a auditoria `ExcludedCodeRemoved` com o retrato anterior e apaga; se a linha não existe mais, lança `ActionRefusedException`
- [X] T046 [US4] Em `app/Livewire/ExcludedCodes/Index.php`, tornar código e descrição pesquisáveis, tornar código, descrição e data ordenáveis, definir a ordem padrão por data decrescente e 25 linhas por página, ligar busca e ordenação à URL (`busca` e `ordem`, esta no formato `coluna:direção` do Filament, com `$tableSearch` sem tipo como em `Sessions\Index`), e acrescentar a ação de linha "Remover" do Filament com `requiresConfirmation()`, que autoriza o gate e chama `RemoveExcludedCode`, até T044 passar

**Checkpoint**: a lista pode ser mantida no dia a dia.

---

## Phase 7: User Story 5 - Restringir o acesso e rastrear alterações (Priority: P2)

**Goal**: só quem tem a permissão vê e altera a lista, e a permissão pode ser concedida a outros
usuários com registro de auditoria.

**Independent Test**: entrar sem a permissão e conferir que o menu não mostra a tela e que o
acesso direto é negado; conceder a permissão por comando e conferir o acesso e a auditoria.

A proteção da rota já foi feita na fundação (T017 a T020), e a auditoria de cada ação é conferida
nos testes das histórias 1, 3 e 4.

### Tests for User Story 5 ⚠️

- [X] T047 [US5] Ampliar `tests/Feature/Conciliation/ExcludedCodeAuthorizationTest.php`: o painel (`/dashboard`) mostra o item "Códigos excluídos" ao Administrador e ao Operador com a concessão, e não o mostra ao Operador sem ela; o componente recusa `save`, envio e remoção quando a permissão é revogada depois de a tela estar aberta
- [X] T048 [P] [US5] Escrever `tests/Feature/Conciliation/UserPermissionCommandTest.php`: `conciliation:grant-permission` concede, grava `AuditLog` `PermissionGranted` em nome do Administrador de `--by` e, repetido, não duplica nem grava novo registro; falha com mensagem clara quando `--by` falta, não existe ou não é Administrador, quando o usuário não existe e quando a permissão não existe; `conciliation:revoke-permission` remove, grava `PermissionRevoked` e, sem concessão, termina sem erro e sem auditoria

### Implementation for User Story 5

- [X] T049 [P] [US5] Criar `app/Actions/Conciliation/GrantUserPermission.php` e `app/Actions/Conciliation/RevokeUserPermission.php`, em transação com `AuditRecorder`, recusando ator que não seja Administrador
- [X] T050 [US5] Criar `app/Console/Commands/GrantPermission.php` com a assinatura `conciliation:grant-permission {email} {permission} {--by=}`, delegando a `GrantUserPermission`
- [X] T051 [US5] Criar `app/Console/Commands/RevokePermission.php` com a assinatura `conciliation:revoke-permission {email} {permission} {--by=}`, delegando a `RevokeUserPermission`, até T048 passar
- [X] T052 [US5] Acrescentar em `resources/views/navigation-menu.blade.php` o item "Códigos excluídos" dentro de `@can('manage-excluded-codes')`, no menu de mesa e no responsivo, como o item do histórico
- [X] T053 [US5] Conferir em `app/Livewire/ExcludedCodes/Index.php` que cada ação pública (salvar, enviar, remover) chama `$this->authorize('manage-excluded-codes')` antes de delegar, até T047 passar

**Checkpoint**: todas as histórias funcionam de forma independente.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: desempenho, conferência final e registro das decisões.

- [X] T054 [P] Escrever `tests/Feature/Conciliation/ExcludedCodePerformanceTest.php`: importar uma planilha com 10.000 códigos em menos de 60 s (SC-004) e, com 10.000 códigos cadastrados, montar a tela com busca e ordenação em menos de 2 s (SC-006), no mesmo padrão de `ImportPerformanceTest.php`
- [X] T055 Rodar a suíte inteira (`php artisan test --compact`) e `vendor/bin/pint --dirty --format agent`, e corrigir o que falhar
- [ ] T056 Executar o roteiro manual de `specs/002-excluded-operation-codes/quickstart.md` no navegador, com `queue:work` e `npm run build`, incluindo o passo de acessibilidade (teclado, rótulos, foco visível e mensagens lidas por leitor de tela, Princípio II)
- [X] T058 [US1] Acrescentar a `tests/Feature/Conciliation/AddExcludedCodeTest.php` os casos do alerta de código sem uso (FR-005a): alerta exibido e cadastro permitido; sem alerta quando o código é usado por algum pagamento, quando não há pagamento importado e quando o código é vazio, inválido ou já cadastrado
- [X] T059 [US1] Implementar o alerta: `isUnusedByImportedPayments()` em `app/Services/ExcludedCodes/ExcludedOperationCodes.php`, a propriedade `codeUsageWarning` em `app/Livewire/ExcludedCodes/Index.php`, o aviso no modal de `resources/views/livewire/excluded-codes/index.blade.php` e a mensagem de confirmação com estilo de alerta
- [X] T057 Registrar em `specs/002-excluded-operation-codes/research.md` as decisões tomadas durante a implementação e o resultado do roteiro manual

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependência.
- **Foundational (Phase 2)**: depende do Setup; bloqueia todas as histórias.
- **US1 (Phase 3)**: depende da fundação.
- **US2 (Phase 4)**: depende da fundação; não depende da tela nem das outras histórias.
- **US3 (Phase 5)**: depende da fundação; usa a tela criada em T019.
- **US4 (Phase 6)**: depende da fundação; o teste de recadastro usa `AddExcludedCode` (US1) e o de fotografia usa US2.
- **US5 (Phase 7)**: depende das ações de tela das histórias 1, 3 e 4 para T047 e T053; T048 a T051 dependem só da fundação.
- **Polish (Phase 8)**: depois de todas as histórias.

### Ordem dentro da fundação

1. T003 a T005 e T007 em paralelo; T006 e T008 em seguida.
2. T009, T010 e T011 nessa ordem (T011 referencia a tabela de T010).
3. T012 a T014 em paralelo.
4. T015 → T016 → T017 → T018 → T019 → T020.

### Ordem dentro de cada história

- Teste antes do código, e o teste DEVE falhar primeiro.
- Serviço antes da ação; ação antes do componente; componente antes da view.

### Arquivos compartilhados (não paralelizar)

- `app/Livewire/ExcludedCodes/Index.php`: T019, T024, T038, T046, T053.
- `resources/views/livewire/excluded-codes/index.blade.php`: T019, T025, T039.
- `routes/web.php`: T020, T041.
- `lang/pt_BR/conciliation.php`: T002, e ajustes durante as histórias.
- `tests/Feature/Conciliation/ExcludedCodeAuthorizationTest.php`: T018, T047.

### Parallel Opportunities

- Fundação: T003, T004, T005 e T007; depois T012, T013 e T014.
- US2 inteira pode andar em paralelo com US1.
- US3: os quatro testes (T029 a T032); T033 e T040.
- US5: T048 e T049 em paralelo com as histórias 3 e 4.

---

## Parallel Example: User Story 3

```bash
# Testes da história, em paralelo:
Task: "ExcludedCodeFileParserTest em tests/Unit/Conciliation/ExcludedCodeFileParserTest.php"
Task: "ImportExcludedCodesTest em tests/Feature/Conciliation/ImportExcludedCodesTest.php"
Task: "ExcludedCodeTemplateTest em tests/Feature/Conciliation/ExcludedCodeTemplateTest.php"
Task: "ExcludedCodeImportMaintenanceTest em tests/Feature/Conciliation/ExcludedCodeImportMaintenanceTest.php"

# Classes sem dependência entre si:
Task: "ParsedExcludedCodeFile em app/Services/ExcludedCodes/ParsedExcludedCodeFile.php"
Task: "ExcludedCodeTemplateWriter em app/Services/ExcludedCodes/ExcludedCodeTemplateWriter.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1 e Phase 2.
2. Phase 3 (US1).
3. **Parar e validar**: cadastrar códigos à mão na tela, como Administrador.

### Incremental Delivery

1. Fundação → tela protegida e vazia.
2. US1 → lista montada à mão (MVP).
3. US2 → contrato pronto para o Módulo 2.
4. US3 → lista montada por planilha.
5. US4 → busca, ordenação e remoção.
6. US5 → menu e permissão para outros usuários.
7. Polish → desempenho, suíte completa e roteiro manual.

### Parallel Team Strategy

Com mais de uma pessoa, depois da fundação: uma segue US1 → US4 (tela e lista), outra segue US2
e os comandos de permissão de US5, e uma terceira segue US3 (importação). O componente
`ExcludedCodes\Index` é o ponto de encontro e não deve ser editado em paralelo.

---

## Notes

- A marca de "excluído por código" no pagamento e o registro por execução NÃO fazem parte desta
  lista; entram nas tarefas do Módulo 2.
- A tela de usuários, para conceder permissões sem comando, NÃO faz parte desta lista.
- Fazer commit ao fim de cada fase, com mensagem no formato Conventional Commits.
