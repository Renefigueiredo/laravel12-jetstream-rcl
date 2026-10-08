# Implementation Plan: Gestão de Sessões e Importação de Arquivos (Módulo 1)

**Branch**: `001-session-file-import` | **Date**: 2026-10-08 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-session-file-import/spec.md`

**Note**: O plano foi gerado com `SPECIFY_FEATURE=001-session-file-import` a partir de `master`. A
spec sugere a branch `feat/mod1-gestao-sessoes`; nenhuma branch foi criada.

## Summary

O Operador cria sessões de conciliação por mês/ano e carrega três planilhas: autorizações (ELO),
pagamentos da Unidade Social e pagamentos da Unidade de Saúde (ERP). Cada envio é validado por
inteiro em um job de fila e aceito ou recusado como um todo, com relatório de erros por linha e
coluna. A sessão trava ao executar a conciliação, pode ser reaberta sob condições e só é excluída
se nunca foi processada. Toda mudança relevante grava um registro de auditoria imutável.

Abordagem: componentes Livewire 4 com Filament Tables e Actions; leitura em fluxo com OpenSpout
atrás de uma interface própria; classes puras para interpretar valores e datas; ações de domínio
em `app/Actions/Conciliation` com transação e trava de linha; tabela única `audit_logs`; o motor
de conciliação entra por uma interface que o Módulo 2 implementa.

## Technical Context

**Language/Version**: PHP 8.3+
**Primary Dependencies**: Laravel 12.53, Livewire 4.2, Filament 5.3, Jetstream 5.4 + Fortify, Tailwind CSS 4.2, OpenSpout 4.32 (hoje transitivo; a declarar)
**Storage**: PostgreSQL no Supabase (produção), SQLite (desenvolvimento e testes), disco privado `local` para arquivos
**Testing**: PHPUnit 11 (`php artisan test --compact`), testes de Feature e de Unit; Pint
**Target Platform**: aplicação web em servidor Linux; hospedagem de produção ainda não definida
**Project Type**: web, monólito Laravel renderizado no servidor
**Performance Goals**: planilha de 10.000 linhas validada em menos de 60 s (SC-004); criar sessão
e carregar três planilhas em menos de 5 min (SC-003)
**Constraints**: arquivo de até 50 MB; leitura em fluxo com memória constante; envio tudo ou nada;
valores em centavos inteiros; datas e horas em UTC; textos em pt-BR
**Scale/Scope**: uso interno, poucos usuários simultâneos; alguns milhares de linhas por arquivo
por mês; 3 telas, 6 tabelas novas, 1 coluna nova em `users`

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Situação | Como o plano atende |
|-----------|----------|---------------------|
| I. Laravel 12 First | Atende | Regras em Actions e Services; Enums para valores fixos; policies; rotas nomeadas; Form Objects do Livewire para validação de entrada |
| II. Reactive UI | Atende | Livewire 4, Filament Tables e Actions, Tailwind 4, `lang/pt_BR`, filtros na URL. Direção visual definida pelo responsável: padrão do Jetstream |
| III. Test-First | Atende | Cada história tem testes de Feature escritos antes; valores e datas têm testes de Unit de fronteira (ver [quickstart.md](./quickstart.md)) |
| IV. PostgreSQL Data Integrity | Atende, com risco | Centavos inteiros, UTC, FKs, índice único parcial, contagens no banco, transações. Risco: testes em SQLite não provam a trava de linha (R16) |
| V. Boost-Guided | Atende, com pendência | Sem pacote novo baixado; `composer.json` muda só para declarar OpenSpout, mediante aprovação. O Boost não respondeu nesta sessão; pontos marcados em [research.md](./research.md) |
| VI. Production-Ready Integrations | Atende | Jobs de fila independentes de driver, com progresso; fila em banco de dados; validação completa antes de gravar; importação atômica e idempotente; disco privado; limites em config |
| VII. Auditability | Atende | `audit_logs` somente acréscimo, gravado na mesma transação; arquivos substituídos sempre retidos; exclusão só de sessão nunca processada, com registro que sobrevive; exclusão de conta de usuário desligada |
| VIII. Role & Permission Access | Atende | `UserRole` como Enum, policy e gate no servidor; auto-registro desligado; usuários criados por comando. Jetstream Teams segue ligado, sem uso para unidades ou papéis |
| IX. Deterministic Engine | Não se aplica diretamente | Este módulo não calcula pontuação. Garante o que lhe cabe: resultado anterior descartado antes de nova execução e nenhuma execução simultânea |

**Resultado do gate (antes da Fase 0)**: passa.
A constituição v2.1.0 tornou o Horizon opcional, o que eliminou o desvio registrado na primeira
versão deste plano.

**Reavaliação após a Fase 1**: o desenho não criou novo desvio. O modelo de dados cumpre IV e VII
(FKs, índice parcial, gatilho de auditoria no PostgreSQL); os contratos cumprem VI (fonte externa
atrás de interface) e IX (interface do motor com descarte de resultado). O gate continua
passando nas mesmas condições.

## Project Structure

### Documentation (this feature)

```text
specs/001-session-file-import/
├── plan.md              # este arquivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   ├── routes.md
│   ├── spreadsheet-layouts.md
│   └── application-interfaces.md
├── checklists/
│   └── requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Actions/Conciliation/
│   ├── CreateSession.php
│   ├── SubmitSpreadsheet.php            # cria a tentativa e despacha o job
│   ├── ConfirmPeriodDivergence.php
│   ├── CancelImportAttempt.php
│   ├── ExecuteReconciliation.php
│   ├── ReopenSession.php
│   └── DeleteSession.php
├── Console/Commands/{PruneImportAttempts,RecoverStuckSessions,CreateAdministrator}.php
├── Contracts/
│   ├── SpreadsheetReader.php
│   ├── SpreadsheetLayout.php
│   ├── ReconciliationEngine.php
│   └── ReconciliationResultInspector.php
├── Enums/                               # UserRole, SessionStatus, ImportSlot, ...
├── Http/Controllers/
│   ├── ImportFileDownloadController.php
│   ├── ImportErrorReportController.php
│   └── SpreadsheetTemplateController.php
├── Jobs/
│   ├── ProcessImportAttempt.php         # valida; grava se não houver divergência
│   ├── PersistImportAttempt.php         # grava após a confirmação
│   └── RunReconciliation.php
├── Livewire/Sessions/
│   ├── Index.php                        # lista + nova sessão
│   ├── Show.php                         # painel com os três cartões
│   └── History.php                      # exclusões e reaberturas (Administrador)
├── Models/
│   ├── ReconciliationSession.php
│   ├── ImportFile.php
│   ├── ImportAttempt.php
│   ├── AuthorizationEntry.php
│   ├── PaymentEntry.php
│   └── AuditLog.php
├── Policies/ReconciliationSessionPolicy.php
└── Services/
    ├── Audit/AuditRecorder.php
    └── Import/
        ├── OpenSpoutSpreadsheetReader.php
        ├── MoneyParser.php
        ├── DateParser.php
        ├── HeaderNormalizer.php
        ├── SpreadsheetValidator.php     # primeira passada: erros e contagens
        ├── SpreadsheetImporter.php      # segunda passada: grava em lotes
        ├── ErrorReportWriter.php
        ├── TemplateWriter.php
        ├── NullReconciliationResultInspector.php
        └── Layouts/{AuthorizationsLayout,PaymentsLayout}.php

config/conciliation.php
config/livewire.php                      # publicado, para o limite de envio
database/
├── factories/                           # uma por modelo novo
└── migrations/                          # role em users + 6 tabelas
lang/pt_BR/{conciliation,validation}.php
resources/views/livewire/sessions/{index,show,history}.blade.php
resources/views/livewire/sessions/partials/slot-card.blade.php
routes/web.php                           # rotas de sessões
routes/console.php                       # agendamento do comando de limpeza

tests/
├── Feature/Conciliation/                # ver a lista em quickstart.md
└── Unit/Conciliation/                   # MoneyParser, DateParser, layouts
```

**Structure Decision**: monólito Laravel existente, sem novos projetos. As pastas novas dentro de
`app/` (`Enums`, `Jobs`, `Livewire`, `Contracts`, `Services`, `Console/Commands`) são as que os
comandos `php artisan make:*` criam. O domínio fica agrupado sob o nome `Conciliation` em
`Actions`, `Services` e `tests`, para os Módulos 2 a 6 seguirem o mesmo padrão.

## Fase 2: abordagem para as tarefas

Ordem sugerida para `/speckit-tasks`. Cada bloco começa pelos testes.

1. **Fundação**: declarar OpenSpout; `config/conciliation.php`; locale pt-BR; Enums; migração de
   `role`; `audit_logs` com `AuditRecorder`; policy e gate.
2. **Interpretação**: `MoneyParser`, `DateParser`, `HeaderNormalizer`, os dois layouts e o leitor
   (testes de Unit com as tabelas do contrato).
3. **História 1 (P1)**: sessões, lista, painel, envio, validação, gravação, planilha modelo,
   download do original, sessão complementar.
4. **História 2 (P2)**: recusa, resumo e relatório de erros.
5. **História 3 (P2)**: divergência de período, confirmação, cancelamento, limpeza agendada.
6. **História 4 (P2)**: execução, travamento, job, progresso, falha.
7. **História 5 (P3)**: reabertura e substituição em sessão já processada.
8. **História 6 (P3)**: exclusão e histórico do Administrador.

Histórias 4 e 5 ficam completas e testadas com um motor falso, mas a execução só é oferecida ao
usuário quando o Módulo 2 ligar `conciliation.engine_enabled`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Tabela `import_attempts` além de `import_files` | Sustenta progresso em fila, confirmação de divergência e relatório de erros sem gravar dado parcial | Guardar esse estado no componente Livewire se perde quando o usuário navega, o que a spec exige permitir |

## Decisões pendentes do responsável

1. Rodar a suíte também em PostgreSQL.
2. Confirmar a unicidade do identificador do pagamento.
3. Backup fora do servidor da pasta de arquivos (Princípio VI), quando o servidor de produção for definido.
4. Decidir se as linhas de folha de pagamento podem ficar visíveis a todos os usuários.
