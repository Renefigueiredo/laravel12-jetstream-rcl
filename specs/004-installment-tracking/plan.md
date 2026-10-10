# Implementation Plan: Gestão de Parcelamentos e Pagamentos Parciais (Módulo 3)

**Branch**: `004-installment-tracking` | **Date**: 2026-10-09 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/004-installment-tracking/spec.md`

**Note**: Os Módulos 1 (sessões e importação), 2 (motor de conciliação) e 5 (códigos excluídos)
estão na `master`. Este módulo não refaz o que o Módulo 2 entregou; apoia-se nele.

## Summary

A página "Dashboard" deixa de ser a tela de boas-vindas e passa a ser o painel de autorizações de
todas as sessões processadas: resumo financeiro, alertas e três abas ("Em aberto / Parcial",
"Conciliadas", "Divergências"). Cada autorização se abre na própria lista e mostra o extrato:
pagamentos abatidos, abatimentos, saldo depois de cada um e parcelas que faltam. Do extrato o
Operador desfaz um pagamento; da aba "Divergências" vincula um pagamento sem autorização ou
decide um Excedente, de qualquer sessão. O Operador pode informar um plano de parcelas de valores
diferentes, que o motor usa para vincular sozinho cada parcela, no máximo um pagamento por
parcela. A previsão das parcelas que faltam marca as atrasadas e alimenta um alerta.

Abordagem: nenhuma regra de saldo nova. O extrato e a previsão são calculados por duas classes
puras a partir dos vínculos existentes; as decisões reutilizam as ações do Módulo 2
(`LinkManually`, `ConfirmSuggestion`, `RemoveLink`, `CreateMatchingAuthorization`), que deixam de
depender de uma sessão escolhida na tela. O banco ganha três tabelas: o plano, suas parcelas e
uma previsão materializada por autorização, que torna o alerta e o filtro de atrasadas uma
consulta simples. O `Matcher` ganha uma referência de parcela com quantidade (as parcelas em
aberto do plano). Nenhuma dependência nova.

## Technical Context

**Language/Version**: PHP 8.5 (mínimo 8.3)
**Primary Dependencies**: Laravel 12.69, Livewire 4.4, Filament 5.10 (tables, actions, forms, schemas), Jetstream 5.5 + Fortify, Tailwind CSS 4.2
**Storage**: PostgreSQL no Supabase (produção), SQLite (desenvolvimento e testes)
**Testing**: PHPUnit 11 (`php artisan test --compact`), testes de Unit para as classes puras e de Feature para ações, motor e telas; Pint
**Target Platform**: aplicação web em servidor Linux
**Project Type**: web, monólito Laravel renderizado no servidor
**Performance Goals**: painel em menos de 3 s com 50.000 autorizações acumuladas e extrato em
menos de 1 s (SC-008)
**Constraints**: saldo sempre derivado dos vínculos; valores em centavos inteiros; cada parcela
do plano recebe no máximo um pagamento; previsão por mês, nunca bloqueia vínculo; toda mudança
auditada na mesma transação; textos em pt-BR
**Scale/Scope**: uso interno; cerca de 140 autorizações por mês; 1 página refeita, 3 abas, 2
tabelas de tela, 3 tabelas de banco novas, 2 ações novas, 2 classes puras novas, ajuste em 1
trait e em 3 classes do Módulo 2

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Situação | Como o plano atende |
|-----------|----------|---------------------|
| I. Laravel 12 First | Atende | Ações para plano; Services para extrato, previsão e consultas do painel; Enums para situação de parcela e tipo de linha; rota nomeada `dashboard` mantida |
| II. Reactive UI | Atende, com ressalva | Livewire 4 e Filament Tables/Actions; aba, busca e filtros na URL; textos em `lang/pt_BR`. Ressalva: a linha que se abre usa o painel recolhível do Filament, o que troca o cabeçalho de colunas por rótulos na linha (R4) |
| III. Test-First | Atende | Classes puras (`AuthorizationStatement`, `InstallmentSchedule`) com testes de fronteira; `Matcher` com casos de plano; testes de Feature por história e teste de duas ações na mesma autorização |
| IV. PostgreSQL Data Integrity | Atende, com risco | Centavos inteiros; chave única do plano; totais e contagens por agregação no banco; alterações em transação com trava na autorização. Risco: agregações e travas só se provam em PostgreSQL (pendência já registrada no Módulo 2) |
| V. Boost-Guided | Atende | Sem dependência nova; componentes e ações existentes reaproveitados; mudança mínima no motor |
| VI. Production-Ready Integrations | Atende | Previsão recalculada dentro das transações já existentes e no fim da execução em fila; sem serviço externo |
| VII. Auditability | Atende | Salvar e remover plano auditados com valores anterior e novo; vincular e desfazer usam as ações já auditadas; nenhuma tela edita saldo |
| VIII. Role & Permission Access | Atende | Operador e Administrador veem o painel, vinculam, desfazem e informam plano; criar autorização segue restrito ao Administrador; autorização no servidor em cada ação |
| IX. Deterministic Engine | Atende | Vínculo de parcela do plano exige a nota no limite automático, contra o valor da parcela; ocupação das parcelas por regra fixa (data, depois identificador); o plano é indício e não reduz nota |

**Resultado do gate (antes da Fase 0)**: passa.

**Reavaliação após a Fase 1**: o desenho não criou desvio. O plano de parcelas é guardado pela
chave de identidade da autorização (sobrevive à substituição da planilha); a ocupação das
parcelas é derivada, não gravada, o que mantém o saldo com uma única fonte (IV, IX). A ressalva
do Princípio II continua registrada em R4, com a alternativa descrita. O gate continua passando.

## Project Structure

### Documentation (this feature)

```text
specs/004-installment-tracking/
├── plan.md              # este arquivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   ├── statement-rules.md
│   ├── routes.md
│   └── application-interfaces.md
├── checklists/
│   └── requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Actions/Conciliation/
│   ├── SaveInstallmentPlan.php              # novo
│   ├── RemoveInstallmentPlan.php            # novo
│   ├── LinkManually.php                     # usa AuthorizationAvailability
│   └── ReopenSession.php                    # mensagem de recusa (FR-034)
├── Console/Commands/
│   └── RefreshForecasts.php                 # novo: conciliation:refresh-forecasts
├── Enums/
│   ├── InstallmentStatus.php                # novo: Paid, Open, Overdue
│   ├── StatementLineKind.php                # novo
│   └── AuditAction.php                      # dois casos novos
├── Livewire/
│   ├── Dashboard/
│   │   ├── Show.php                         # página: resumo, alertas e abas
│   │   ├── AuthorizationsTable.php          # abas "Em aberto / Parcial" e "Conciliadas"
│   │   └── DivergencesTable.php             # aba "Divergências"
│   └── Reconciliation/Concerns/
│       └── DecidesPendingItems.php          # sessão vem do registro, não da tela
├── Models/
│   ├── InstallmentPlan.php                  # novo
│   ├── InstallmentPlanItem.php              # novo
│   ├── AuthorizationForecast.php            # novo
│   └── AuthorizationEntry.php               # relações plan e forecast; escopo do painel
└── Services/Reconciliation/
    ├── Matching/
    │   ├── Matcher.php                      # parcelas do plano, uma por pagamento
    │   ├── AuthorizationCandidate.php       # planAmounts
    │   ├── PaymentConditionParser.php       # prazos em dias
    │   ├── InstallmentSchedule.php          # novo, puro: parcelas previstas e ocupação
    │   └── AuthorizationStatement.php       # novo, puro: linhas do extrato e saldo corrente
    ├── AuthorizationAvailability.php        # novo: autorizações que podem receber um pagamento
    ├── AuthorizationPanel.php               # novo: consultas e totais do painel
    ├── InstallmentForecaster.php            # novo: recalcula a previsão materializada
    ├── CandidateLoader.php                  # lê as parcelas em aberto do plano
    ├── ReconciliationLinker.php             # recalcula a previsão ao vincular e desvincular
    └── MatchResultWriter.php                # recalcula a previsão ao fim da execução

database/migrations/
├── xxxx_create_installment_plans_table.php
├── xxxx_create_installment_plan_items_table.php
└── xxxx_create_authorization_forecasts_table.php

resources/views/
├── dashboard.blade.php                      # removida; a rota passa ao componente
└── livewire/dashboard/
    ├── show.blade.php
    └── partials/
        ├── statement.blade.php              # extrato dentro da linha
        └── installment-plan-fields.blade.php

lang/pt_BR/conciliation.php                  # chaves `dashboard.*`

tests/
├── Unit/Reconciliation/
│   ├── AuthorizationStatementTest.php
│   ├── InstallmentScheduleTest.php
│   ├── MatcherInstallmentPlanTest.php
│   └── PaymentConditionParserTest.php       # prazos
└── Feature/Dashboard/
    ├── AuthorizationPanelTest.php
    ├── StatementTest.php
    ├── UndoFromStatementTest.php
    ├── DivergencesTest.php
    ├── InstallmentPlanTest.php
    ├── InstallmentPlanEngineTest.php
    ├── ForecastTest.php
    └── DashboardPerformanceTest.php
```

**Structure Decision**: monólito Laravel, na estrutura já usada. A pasta nova
`app/Livewire/Dashboard` e a de testes `tests/Feature/Dashboard` seguem o padrão das existentes
(`Sessions`, `Reconciliation`, `ExcludedCodes`). As classes puras ficam em
`Services/Reconciliation/Matching`, junto das que o motor já usa, porque o motor e a tela leem as
mesmas regras de parcela.

## Complexity Tracking

| Ponto | Por que é necessário | Alternativa mais simples rejeitada |
|-------|----------------------|------------------------------------|
| Previsão materializada (`authorization_forecasts`) | O alerta e o filtro "com parcela atrasada" precisam ser uma consulta, com 50.000 autorizações | Calcular em PHP a cada abertura do painel: não permite filtrar nem paginar no banco |
| Plano guardado pela chave de identidade, sem chave estrangeira | A autorização ganha outro identificador quando a planilha é substituída; o plano precisa sobreviver | Chave estrangeira com cópia do plano na reimportação: acopla o Módulo 1 a este |
