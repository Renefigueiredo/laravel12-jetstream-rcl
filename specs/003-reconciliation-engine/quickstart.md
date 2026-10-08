# Quickstart: Motor de Conciliação e Tratamento de Divergências (Módulo 2)

Como conferir a feature de ponta a ponta. Regras do motor em
[contracts/matching-rules.md](./contracts/matching-rules.md); telas e ações em
[contracts/routes.md](./contracts/routes.md); modelo em [data-model.md](./data-model.md).

## Pré-requisitos

Os mesmos dos módulos anteriores, com os Módulos 1 e 5 já entregues na `master`. Não há pacote
novo nem ajuste de PHP.

## Preparar

```bash
php artisan migrate
php artisan queue:work          # em outro terminal; reinicie após mudar código
npm run build                   # ou npm run dev
```

O motor fica ligado por padrão. Para desligar: `CONCILIATION_ENGINE_ENABLED=false`.

## Testes automatizados

```bash
php artisan test --compact tests/Unit/Reconciliation
php artisan test --compact tests/Feature/Reconciliation
php artisan test --compact
```

Arquivos de teste previstos:

| Pasta e arquivo | Cobre |
|-----------------|-------|
| `Unit/Reconciliation/SupplierNameNormalizerTest` | tabela de normalização |
| `Unit/Reconciliation/SupplierSimilarityTest` | tabela de compatibilidade do fornecedor e simetria |
| `Unit/Reconciliation/PairScorerTest` | valor, nota e classificação em cada limite; cada modo de tolerância |
| `Unit/Reconciliation/PaymentConditionParserTest` | tabela de parcelas previstas |
| `Unit/Reconciliation/CardNumberExtractorTest` | tabela de espécie para cartão |
| `Unit/Reconciliation/EngineParametersTest` | tolerância efetiva e teto de acréscimo |
| `Unit/Reconciliation/MatcherTest` | vínculo exato, empates, desempate, data, bloqueio, sugestões |
| `Unit/Reconciliation/MatcherInstallmentsTest` | tabela de parcelas |
| `Unit/Reconciliation/MatcherDeterminismTest` | mesma entrada em ordens diferentes, mesma saída |
| `Feature/Reconciliation/RunReconciliationTest` | US1: execução completa, andamento, falha, totais |
| `Feature/Reconciliation/ExcludedCodesInRunTest` | US1 e Módulo 5: pulados, fotografia, contagem por código |
| `Feature/Reconciliation/PriorSessionsTest` | janela, sessão não processada, duplicado de outro período |
| `Feature/Reconciliation/DiscardAndReopenTest` | descarte, reabertura bloqueada, vínculos entre sessões |
| `Feature/Reconciliation/AuthorizationStateTest` | saldo e situação; duas ações na mesma autorização |
| `Feature/Reconciliation/PendingScreenTest` | US2: lista, filtros, busca, URL, totais, abas |
| `Feature/Reconciliation/ConfirmRejectSuggestionTest` | US3: confirmar, rejeitar, próximo candidato, lote |
| `Feature/Reconciliation/DifferenceTreatmentTest` | US4: falta pagar, desconto, a maior, acréscimo e teto |
| `Feature/Reconciliation/ManualLinkTest` | US5: vincular e desvincular, bloqueio de par, auditoria |
| `Feature/Reconciliation/InvestigationQueueTest` | US6: fila, filtros, criar autorização, desfazer |
| `Feature/Reconciliation/ReconciliationSettingsTest` | US7: permissão, validação, efeito só nas próximas execuções |
| `Feature/Reconciliation/InstallmentsAcrossSessionsTest` | US8: parcelas em três sessões |
| `Feature/Reconciliation/EarlyPaymentsTest` | pago antes da autorização: aba e avisos |
| `Feature/Reconciliation/ExcludedTabTest` | códigos vigentes e pagamentos excluídos da execução |
| `Feature/Reconciliation/CardReconciliationTest` | cartão na importação e na migração; cartão diferente; aba Por cartão e filtro |
| `Feature/Reconciliation/ReconciliationPerformanceTest` | SC-002 |

## Roteiro manual

Com a sessão de julho/2026 e os arquivos reais já carregados, e a lista de códigos excluídos
montada (67 códigos).

1. **Executar**: no painel da sessão, "Executar Conciliação". O andamento aparece; sair e voltar
   não interrompe. Ao terminar, a sessão fica Processada e o resumo aparece.
2. **Conferir os totais**: 136 autorizações; cerca de 266 pagamentos comparados e 3.367 excluídos
   por código. Anotar o percentual de conciliação automática (SC-001).
3. **Aba Excluídos por código**: os 67 códigos, com a quantidade de cada um; a soma bate com os
   excluídos.
4. **Pendências**: abrir, filtrar por Dúbios, Parciais, Excedentes e Sem pagamento; buscar um
   fornecedor; copiar o endereço para outra aba e ver a mesma lista.
5. **Confirmar um Dúbio**: o par sai da lista e aparece em Conciliados como manual.
6. **Rejeitar um Dúbio**: o próximo candidato aparece, ou a autorização vai para Sem pagamento.
7. **Parcial**: confirmar um como "Ainda falta pagar" e conferir o saldo; confirmar outro como
   "Encerrar com desconto", com categoria e justificativa, e conferir que ficou Conciliada.
   Conferir que a autorização do primeiro aparece no filtro "Saldo em aberto".
8. **Excedente**: confirmar um como "Acréscimo aceito" (dentro de 10%) e outro como "Pagamento a
   maior"; conferir o alerta vermelho só no segundo.
9. **Vincular manualmente**: em uma autorização Sem pagamento, escolher um pagamento da fila.
10. **Desvincular**: em Conciliados, desvincular um par automático; os dois voltam às pendências e
    o par não é mais sugerido.
11. **Fila de investigação**: filtrar por unidade e por código; "Criar autorização
    correspondente" em um pagamento; desvincular e ver a autorização criada sumir.
12. **Pagos antes da autorização**: abrir a aba e conferir as duas datas.
    Na aba **Por cartão**, conferir os cinco cartões de julho (0798, 4931, 5352, 7607 e 7222) e
    abrir a conferência do 0798: linhas da fatura, autorizações sem fatura e linhas sem
    autorização. Filtrar as Pendências por cartão.
13. **Tolerância**: como Administrador, mudar para R$ 1,00; a sessão já processada continua
    mostrando R$ 0,50.
14. **Reabrir**: tentar reabrir com decisões manuais (recusado, com a quantidade); desfazer as
    decisões, reabrir e executar de novo; conferir que o par rejeitado não voltou.
15. **Sessão seguinte**: criar a sessão de agosto, carregar os arquivos e executar; conferir que
    autorizações de cartão de julho foram conciliadas por pagamentos de agosto e que o vínculo
    mostra as duas sessões. Tentar reabrir julho: recusado, citando agosto.
16. **Acessibilidade**: percorrer a lista e os modais de decisão só com o teclado; conferir
    rótulos, foco visível e mensagens lidas por leitor de tela.

## Resultado esperado

- Suíte verde, incluindo as dos Módulos 1 e 5.
- `vendor/bin/pint --dirty --format agent` sem alterações pendentes.
- Duas execuções seguidas da mesma sessão, com reabertura entre elas, dão os mesmos totais.
