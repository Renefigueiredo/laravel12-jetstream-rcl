# Implementation Plan: Motor de Conciliação e Tratamento de Divergências (Módulo 2)

**Branch**: `003-reconciliation-engine` | **Date**: 2026-10-08 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/003-reconciliation-engine/spec.md`

**Note**: Os Módulos 1 (sessões e importação) e 5 (códigos excluídos) já estão na `master` e foram
trazidos para esta branch.

## Summary

Ao executar uma sessão, o motor compara os pagamentos das duas unidades com as autorizações da
sessão e com as autorizações em aberto de sessões anteriores. Dá a cada par uma nota de 0 a 100 a
partir do nome do fornecedor e do valor, vincula sozinho os pares sem dúvida (inclusive parcelas)
e deixa o resto para o Operador: Dúbios, Parciais, Excedentes, autorizações sem pagamento e
pagamentos sem autorização. O Operador confirma, rejeita, vincula, desvincula, encerra com
desconto, aceita acréscimo e cria autorização para pagamento órfão. O Administrador configura a
tolerância. Tudo é auditado, e o saldo de cada autorização é sempre derivado dos vínculos.

Abordagem: um núcleo puro (`Matcher`), sem banco, com as regras de cruzamento e testes de
fronteira; uma camada de gravação que persiste o resultado em uma transação; ações de domínio
para cada decisão; uma página com abas, cada uma com uma tabela do Filament; uma visão de banco
para a lista de pendências; o cartão do pagamento em coluna própria, para resumo e filtro. Nenhuma dependência nova.

## Technical Context

**Language/Version**: PHP 8.5 (mínimo 8.3)
**Primary Dependencies**: Laravel 12.69, Livewire 4.4, Filament 5.10 (tables, actions, forms), Jetstream 5.5 + Fortify, Tailwind CSS 4.2
**Storage**: PostgreSQL no Supabase (produção), SQLite (desenvolvimento e testes)
**Testing**: PHPUnit 11 (`php artisan test --compact`), testes de Unit para o núcleo e de Feature para execução, ações e telas; Pint
**Target Platform**: aplicação web em servidor Linux
**Project Type**: web, monólito Laravel renderizado no servidor
**Performance Goals**: sessão com 10.000 lançamentos conciliada em menos de 2 min (SC-002);
decisão de uma pendência em menos de 30 s pelo Operador (SC-008)
**Constraints**: resultado determinístico; valores em centavos inteiros e percentuais em
pontos-base; execução tudo ou nada; um pagamento em no máximo um vínculo; saldo derivado dos
vínculos; textos em pt-BR
**Scale/Scope**: uso interno; por mês, cerca de 140 autorizações e de 300 a 3.600 pagamentos; 2
páginas, 6 abas, 7 tabelas novas, 1 visão, 2 tabelas alteradas

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Situação | Como o plano atende |
|-----------|----------|---------------------|
| I. Laravel 12 First | Atende | Regras em Services e Actions; Enums para toda situação e classificação; policy e gate; rotas nomeadas |
| II. Reactive UI | Atende | Livewire 4 e Filament Tables/Actions; textos em `lang/pt_BR`; aba, filtros e busca na URL; direção visual já definida (Jetstream); passo de acessibilidade no roteiro |
| III. Test-First | Atende | Núcleo puro com testes de Unit para pares que casam, que não casam e de fronteira: cada limite de nota, cada modo de tolerância, parcelas; teste de duas ações na mesma autorização |
| IV. PostgreSQL Data Integrity | Atende, com risco | Centavos e pontos-base inteiros; índice único no pagamento do vínculo; chaves estrangeiras; saldo e totais por agregação no banco; gravação em transação. Risco: travas e a visão só se provam em PostgreSQL (R21) |
| V. Boost-Guided | Atende | Sem dependência nova; APIs do Filament iguais às já em uso; mudança mínima no Módulo 1 (R2) |
| VI. Production-Ready Integrations | Atende | Execução no job de fila já existente, com andamento; fila em banco; nada de IA; parâmetros em configuração |
| VII. Auditability | Atende | Desconto e acréscimo com categoria de justificativa; confirmação, rejeição, vínculo manual, desvínculo, desconto, autorização criada e mudança de tolerância auditados na mesma transação; desvínculo guarda nota e sugestão originais; vínculos impedem apagar lançamentos (`restrictOnDelete`) |
| VIII. Role & Permission Access | Atende | Operador e Administrador executam e decidem; `ConfigureTolerance` como permissão por usuário; autorização no servidor em cada ação |
| IX. Deterministic Engine | Atende | Nota por dois eixos, só com texto e aritmética; todo vínculo automático, inclusive o de parcela, exige nota no limite automático; limites e tolerância em configuração; parâmetros gravados por execução; saldo derivado e serializado por trava; códigos excluídos pulados e guardados; nova execução descarta a anterior |

**Resultado do gate (antes da Fase 0)**: passa.

**Reavaliação após a Fase 1**: o desenho não criou desvio. O modelo de dados cumpre IV, VII e IX
(índice único, valores congelados no vínculo, execução com parâmetros); o contrato de regras
(`matching-rules.md`) dá a base dos testes de fronteira de III. O gate continua passando.

## Project Structure

### Documentation (this feature)

```text
specs/003-reconciliation-engine/
├── plan.md              # este arquivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   ├── matching-rules.md
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
│   ├── ConfirmSuggestion.php
│   ├── RejectSuggestion.php
│   ├── LinkManually.php
│   ├── RemoveLink.php
│   ├── CloseAuthorizationWithDiscount.php
│   ├── CreateMatchingAuthorization.php
│   ├── UpdateReconciliationSettings.php
│   ├── ReopenSession.php                    # passa a descartar o resultado
│   └── DeleteSession.php                    # apaga execuções de sessão nunca processada
├── Enums/                                   # ver data-model.md
├── Livewire/
│   ├── Reconciliation/
│   │   ├── Show.php                         # página: resumo e abas
│   │   ├── PendingTable.php
│   │   ├── InvestigationTable.php
│   │   ├── LinksTable.php
│   │   ├── EarlyPaymentsTable.php
│   │   ├── CardsTable.php
│   │   ├── ExcludedTable.php
│   │   └── Settings.php
│   └── Sessions/Show.php                    # resumo da execução e atalho
├── Models/
│   ├── ReconciliationRun.php
│   ├── ReconciliationSkip.php
│   ├── ReconciliationLink.php
│   ├── ReconciliationSuggestion.php
│   ├── ReconciliationPairBlock.php
│   ├── ReconciliationSettings.php
│   ├── AuthorizationState.php
│   ├── PendingItem.php                      # modelo da visão, somente leitura
│   ├── AuthorizationEntry.php               # relações e origem
│   └── PaymentEntry.php                     # relações
├── Providers/AppServiceProvider.php         # motor, inspetor, gate, mapa polimórfico
└── Services/Reconciliation/
    ├── Matching/                            # núcleo puro
    │   ├── Matcher.php
    │   ├── SupplierNameNormalizer.php
    │   ├── SupplierSimilarity.php
    │   ├── PairScorer.php
    │   ├── PaymentConditionParser.php
    │   ├── CardNumberExtractor.php
    │   ├── PaymentMethodMatcher.php
    │   ├── EngineParameters.php
    │   ├── AuthorizationCandidate.php
    │   ├── PaymentCandidate.php
    │   ├── PairScore.php
    │   └── MatchResult.php
    ├── DatabaseReconciliationEngine.php
    ├── CandidateLoader.php                  # leitura: sessão, sessões anteriores, pulados
    ├── MatchResultWriter.php                # gravação em uma transação
    ├── ReconciliationLinker.php
    ├── AuthorizationStateCalculator.php
    ├── ReconciliationDecisionInspector.php
    └── RunTotals.php                        # totais por agregação

config/conciliation.php                      # bloco engine; engine_enabled true
database/
├── factories/                               # uma por modelo novo
└── migrations/                              # 7 tabelas, authorization_entries, payment_entries.card, visão
lang/pt_BR/conciliation.php
resources/views/livewire/reconciliation/     # show, abas e settings
resources/views/livewire/sessions/show.blade.php
resources/views/navigation-menu.blade.php
routes/web.php

tests/
├── Feature/Reconciliation/                  # lista em quickstart.md
└── Unit/Reconciliation/
```

**Structure Decision**: monólito Laravel existente. O motor ganha a pasta
`Services/Reconciliation`, com o núcleo puro isolado em `Matching` para que os testes de Unit não
precisem de banco. As ações seguem em `Actions/Conciliation`, como nos módulos anteriores. Os
testes novos ficam em pastas `Reconciliation`, separadas das de importação.

## Fase 2: abordagem para as tarefas

Ordem sugerida para `/speckit-tasks`. Cada bloco começa pelos testes.

1. **Fundação**: configuração; Enums; migrações, modelos e factories; coluna de cartão no
   pagamento (importação e carga dos já importados); `EngineParameters`;
   `ReconciliationSettings`; `AuthorizationStateCalculator` e `ReconciliationLinker`; novos casos
   de auditoria e textos.
2. **Núcleo de cruzamento** (parte da História 1): normalizador, semelhança, nota, `Matcher` com
   vínculo exato, empates, desempate por cartão e forma de pagamento, cartão diferente, data e
   sugestões. Só testes de Unit.
3. **História 1 (P1)**: leitura, gravação, motor registrado, códigos excluídos, sessões
   anteriores, descarte, inspetor, mudança no `ReopenSession`, resumo no painel da sessão.
4. **História 2 (P1)**: visão de pendências, página com abas, totais, filtros (inclusive por
   cartão), busca e URL; abas Conciliados, Pagos antes da autorização, Por cartão e Excluídos por
   código, sem ações.
5. **História 3 (P2)**: confirmar e rejeitar, próximo candidato, lote.
6. **História 4 (P2)**: tratamento da diferença, desconto, acréscimo e teto.
7. **História 5 (P2)**: vínculo manual e desvínculo, bloqueio de par.
8. **História 8 (P2)**: `PaymentConditionParser`, parcelas no `Matcher`, leitura fora da janela,
   exibição do andamento do parcelamento.
9. **História 6 (P3)**: fila de investigação e autorização criada na conciliação.
10. **História 7 (P3)**: tela de tolerância e permissão.
11. **Acabamento**: desempenho (SC-002), calibração com os arquivos reais, roteiro manual,
    acessibilidade e Pint.

O MVP é a soma das Histórias 1 e 2: executar e ver o resultado. As decisões entram em seguida.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Visão de banco `reconciliation_pending_items` | O filtro "Todos" junta três tipos de item em uma lista com busca e paginação no banco (FR-021, FR-022, FR-024) | Tabela materializada exigiria sincronizar a cada ação; uma lista por tipo não cumpre o filtro "Todos" |
| Tabela `authorization_states` (situação derivada, guardada) | Listas filtram e ordenam por situação e saldo, e o motor lê o saldo de centenas de autorizações antigas | Calcular por subconsulta em cada linha repete a regra em vários lugares e pesa nas listas |
| Trava global do motor | Duas execuções simultâneas poderiam quitar a mesma autorização antiga com pagamentos diferentes | Travar autorização por autorização durante todo o cálculo seguraria linhas por minutos |
| Mudança no `ReopenSession` do Módulo 1 | Com vínculos entre sessões, um resultado vencido continuaria quitando autorizações de outra sessão | Manter o resultado até a nova execução deixaria saldos errados entre a reabertura e a execução |

## Decisões pendentes do responsável

1. **Rodar a suíte em PostgreSQL** antes de produção. Neste módulo as travas de linha e a visão
   tornam essa conferência mais importante (R21).
2. **Calibrar os limites de nota** (90 e 60) com os arquivos reais de julho/2026, durante a
   implementação (R6).
3. **Empate entre compras idênticas**: duas autorizações iguais e dois pagamentos iguais ficam
   como Dúbio. Se gerar muitos cliques nos dados reais, cabe uma regra de desempate por data em
   uma evolução.
4. **Janela de 3 meses e teto de 10%**: valores iniciais assumidos, alteráveis sem mudar código.
