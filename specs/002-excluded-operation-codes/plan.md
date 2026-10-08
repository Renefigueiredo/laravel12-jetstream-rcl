# Implementation Plan: Códigos de Operação Excluídos da Conciliação (Módulo 5)

**Branch**: `002-excluded-operation-codes` | **Date**: 2026-10-08 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/002-excluded-operation-codes/spec.md`

**Note**: A spec sugere a branch `feature/config-codigos-ignorados`. O plano usa
`002-excluded-operation-codes`, criada a partir de `master`, porque os scripts do Spec Kit
localizam a feature pelo nome da branch. Este módulo é implementado antes do Módulo 2, por
decisão do responsável em 2026-10-08.

## Summary

Quem tem a permissão de gerenciar códigos excluídos mantém uma lista única de códigos de operação
do ERP que não entram na conciliação: cadastra um a um, importa de uma planilha, consulta, busca
e remove. A importação confere o arquivo inteiro antes de gravar e é tudo ou nada. Toda mudança
grava auditoria. O motor de conciliação (Módulo 2) consome a lista por uma fotografia tirada no
início de cada execução.

Abordagem: uma tela Livewire 4 com Filament Tables; ações de domínio em
`app/Actions/Conciliation`; importação em job de fila, reaproveitando o leitor de planilhas do
Módulo 1; permissões por usuário em tabela própria, com Enum e gate; nenhuma dependência nova.

## Technical Context

**Language/Version**: PHP 8.5 (mínimo 8.3)
**Primary Dependencies**: Laravel 12.69, Livewire 4.4, Filament 5.10 (tables, actions), Jetstream 5.5 + Fortify, Tailwind CSS 4.2, OpenSpout 4.32
**Storage**: PostgreSQL no Supabase (produção), SQLite (desenvolvimento e testes), disco privado `local` para os arquivos enviados
**Testing**: PHPUnit 11 (`php artisan test --compact`), testes de Feature e de Unit; Pint
**Target Platform**: aplicação web em servidor Linux
**Project Type**: web, monólito Laravel renderizado no servidor
**Performance Goals**: planilha com 10.000 códigos importada em menos de 1 min (SC-004); lista com
10.000 códigos responde a busca, ordenação e paginação em menos de 2 s (SC-006)
**Constraints**: arquivo de até 5 MB e 10.000 linhas; importação tudo ou nada; comparação exata
de código; datas e horas em UTC; textos em pt-BR
**Scale/Scope**: uso interno; lista de dezenas a poucas centenas de códigos; 1 tela, 3 tabelas
novas, 2 comandos novos e 1 comando estendido

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Situação | Como o plano atende |
|-----------|----------|---------------------|
| I. Laravel 12 First | Atende | Regras em Actions e Services; Enums para permissão, origem e situação; gate; rotas nomeadas; Form Object do Livewire para o cadastro |
| II. Reactive UI | Atende | Livewire 4, Filament Tables e Actions, Tailwind 4, `lang/pt_BR`, busca e ordenação na URL; direção visual já definida (padrão do Jetstream) |
| III. Test-First | Atende | Cada história começa pelos testes de Feature; normalização e leitura do arquivo têm testes de Unit de fronteira (ver [quickstart.md](./quickstart.md)) |
| IV. PostgreSQL Data Integrity | Atende, com risco | Unicidade e chaves estrangeiras no banco; contagens pela quantidade de linhas inseridas; gravação em transação. Risco: concorrência real só se prova em PostgreSQL (R11) |
| V. Boost-Guided | Atende, com ressalva | Sem dependência nova. Boost consultado; não trouxe documentação do Filament, então as APIs seguem o que já está em uso no Módulo 1 (R9) |
| VI. Production-Ready Integrations | Atende | Importação em job de fila com situação visível; validação completa antes de gravar; tudo ou nada; arquivo sem alteração em disco privado; limites em configuração |
| VII. Auditability | Atende | Inclusão manual, inclusão por arquivo, exclusão e mudança de permissão gravam auditoria na transação; importações concluídas e seus arquivos são retidos |
| VIII. Role & Permission Access | Atende | Primeira permissão granular: Enum, tabela, `hasPermission()` e gate; autorização conferida em rota e em cada ação |
| IX. Deterministic Engine | Atende no que cabe | Entrega a fotografia imutável da lista; pular os pagamentos, mantê-los guardados e registrar os parâmetros por execução é do Módulo 2 (R8) |

**Resultado do gate (antes da Fase 0)**: passa.

**Reavaliação após a Fase 1**: o desenho não criou desvio. O modelo de dados cumpre IV e VII
(unicidade, chaves estrangeiras, retenção); os contratos cumprem VI (leitura atrás de
`SpreadsheetReader`) e deixam explícitas as obrigações do motor para IX. O gate continua
passando.

## Project Structure

### Documentation (this feature)

```text
specs/002-excluded-operation-codes/
├── plan.md              # este arquivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   ├── routes.md
│   ├── import-file.md
│   └── application-interfaces.md
├── checklists/
│   └── requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Actions/Conciliation/
│   ├── AddExcludedCode.php
│   ├── RemoveExcludedCode.php
│   ├── SubmitExcludedCodeImport.php      # cria a importação e despacha o job
│   ├── GrantUserPermission.php
│   └── RevokeUserPermission.php
├── Console/Commands/
│   ├── GrantPermission.php               # conciliation:grant-permission
│   ├── RevokePermission.php              # conciliation:revoke-permission
│   ├── Concerns/ResolvesPermissionArguments.php
│   └── PruneImportAttempts.php           # estendido
├── Enums/
│   ├── UserPermission.php
│   ├── ExcludedCodeSource.php
│   ├── ExcludedCodeImportStatus.php
│   └── AuditAction.php                   # novos casos
├── Http/Controllers/ExcludedCodeTemplateController.php
├── Jobs/ImportExcludedCodes.php
├── Livewire/
│   ├── ExcludedCodes/Index.php
│   └── Forms/ExcludedCodeForm.php
├── Models/
│   ├── ExcludedOperationCode.php
│   ├── ExcludedCodeImport.php
│   ├── UserPermissionGrant.php
│   └── User.php                          # hasPermission(), permissionGrants()
├── Providers/AppServiceProvider.php      # gate e mapa polimórfico
└── Services/ExcludedCodes/
    ├── OperationCode.php                 # normalizar e validar
    ├── ExcludedCodeFileParser.php        # leitura e validação do arquivo
    ├── ParsedExcludedCodeFile.php
    ├── ExcludedCodeImporter.php          # gravação em lotes e auditoria
    ├── ExcludedCodeTemplateWriter.php
    ├── ExcludedOperationCodes.php        # snapshot()
    └── ExcludedCodeSnapshot.php

config/conciliation.php                   # bloco excluded_codes
database/
├── factories/                            # uma por modelo novo
├── migrations/                           # user_permissions, excluded_code_imports, excluded_operation_codes
└── seeders/                              # sem mudança obrigatória
lang/pt_BR/conciliation.php               # textos da tela, erros e ações de auditoria
resources/views/livewire/excluded-codes/index.blade.php
resources/views/navigation-menu.blade.php # item de menu sob o gate
routes/web.php

tests/
├── Feature/Conciliation/                 # lista em quickstart.md
└── Unit/Conciliation/                    # OperationCode, ExcludedCodeFileParser
```

**Structure Decision**: monólito Laravel existente. O módulo segue o agrupamento `Conciliation`
do Módulo 1 em `Actions` e `tests`, e ganha a pasta `Services/ExcludedCodes`. As ações de
permissão ficam junto das demais porque servirão aos Módulos 2 e 6.

## Fase 2: abordagem para as tarefas

Ordem sugerida para `/speckit-tasks`. Cada bloco começa pelos testes.

1. **Fundação**: bloco de configuração; Enums; migrações e modelos com factories; `OperationCode`;
   permissões (`hasPermission`, gate, ações e comandos); novos casos de auditoria e textos.
   A rota protegida e a tela vazia também ficam na fundação, porque todas as histórias dependem
   delas.
2. **História 1 (P1)**: cadastro manual, duplicidade, validação e auditoria.
3. **História 2 (P1, parcial)**: `ExcludedOperationCodes::snapshot()` e seus testes; teste de que
   a importação de pagamentos não é afetada.
4. **História 3 (P2)**: leitor do arquivo, job, gravação, resumo, erros por linha e coluna,
   planilha modelo, limpeza e recuperação.
5. **História 4 (P2)**: lista com busca, ordenação e paginação; remoção e recadastro.
6. **História 5 (P2)**: menu, autorização em cada ação e comandos de permissão.
7. **Acabamento**: testes de desempenho (SC-004, SC-006), roteiro manual, acessibilidade e Pint.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Tabela `excluded_code_imports` com situação, para um arquivo de até 5 MB | O Princípio VI exige processamento de planilhas em fila com situação visível, e FR-015 pede o bloqueio de um segundo envio | Processar na requisição dispensaria a tabela de situação, mas contraria o Princípio VI |
| Tabela `user_permissions` e dois comandos, que a spec deste módulo não descreve | FR-027 depende de uma permissão atribuível a usuários, e o Módulo 1 só entregou papéis | Liberar a tela só ao Administrador cumpriria o padrão, mas não a parte "atribuível a outros usuários" |

## Decisões pendentes do responsável

1. **História 2 fica completa só no Módulo 2.** Este módulo entrega a lista e a fotografia que o
   motor usa. Marcar pagamentos como excluídos e registrar os códigos por execução depende do
   motor (R8).
2. **Concessão de permissão por comando.** Não há tela de usuários. Até existir, a permissão é
   concedida no servidor, por comando, com auditoria (R2). Uma tela de usuários seria uma feature
   própria.
3. **Rodar a suíte também em PostgreSQL**, pendência herdada do Módulo 1.
4. **Ajuste na spec do Módulo 2** (`specs/003-reconciliation-engine`, em outra branch), a fazer
   antes de planejá-lo: dizer que o pagamento excluído por código não pode ser vinculado
   manualmente (FR-021 deste módulo) e que a execução guarda a quantidade de pagamentos excluídos
   por código (FR-023 e SC-007 deste módulo).
