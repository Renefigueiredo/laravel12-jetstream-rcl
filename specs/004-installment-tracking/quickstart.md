# Quickstart: Gestão de Parcelamentos e Pagamentos Parciais (Módulo 3)

Como conferir que o módulo funciona de ponta a ponta. Regras em
[contracts/statement-rules.md](./contracts/statement-rules.md); telas em
[contracts/routes.md](./contracts/routes.md).

## Pré-requisitos

- Branch `004-installment-tracking`, com os Módulos 1, 2 e 5 já na base.
- Banco local com a sessão de julho/2026 processada.

## Preparar

```bash
php artisan migrate
php artisan conciliation:refresh-forecasts   # monta a previsão das autorizações já existentes
npm run build
php artisan queue:work --stop-when-empty   # se for executar uma sessão
```

A previsão é um cache derivado: o comando acima pode ser repetido a qualquer momento, e ela
também é refeita ao fim de cada execução de sessão.

## Testes automatizados

```bash
php artisan test --compact tests/Unit/Reconciliation/AuthorizationStatementTest.php
php artisan test --compact tests/Unit/Reconciliation/InstallmentScheduleTest.php
php artisan test --compact tests/Unit/Reconciliation/MatcherInstallmentPlanTest.php
php artisan test --compact tests/Feature/Dashboard
php artisan test --compact                 # suíte inteira, com os módulos anteriores
vendor/bin/pint --dirty --format agent
```

O que os testes precisam provar:

| Critério | Teste |
|----------|-------|
| SC-002: saldo do extrato igual ao saldo guardado | `StatementTest` |
| SC-004: auditoria de desfazer, vincular e plano | `UndoFromStatementTest`, `DivergencesTest`, `InstallmentPlanTest` |
| SC-006 e SC-007: plano vincula sem clique, uma parcela por pagamento, sem passar do autorizado | `MatcherInstallmentPlanTest`, `InstallmentPlanEngineTest` |
| SC-008: painel em menos de 3 s com 50.000 autorizações | `DashboardPerformanceTest` |
| SC-009: duas ações na mesma autorização | `UndoFromStatementTest` |
| SC-010: parcela atrasada no alerta | `ForecastTest` |

## Roteiro manual

1. **Entrar**: depois do login, a primeira tela é o painel, com o resumo e as três abas.
2. **Resumo**: conferir que o total autorizado e as quantidades batem com a soma das sessões
   processadas.
3. **Em aberto / Parcial**: buscar um fornecedor; filtrar por cartão e por sessão; copiar o
   endereço para outra aba do navegador e ver a mesma lista.
4. **Extrato**: abrir uma autorização com pagamentos; conferir data, valor, sessão, forma do
   vínculo e saldo depois de cada um; fechar. Contar os cliques desde o painel: dois.
5. **Conciliadas**: abrir uma autorização encerrada com desconto e outra com pagamento a maior;
   conferir a linha do abatimento e o alerta.
6. **Desfazer**: no extrato, desfazer um pagamento; a autorização muda de aba se for o caso, e o
   pagamento aparece em "Divergências".
7. **Divergências**: filtrar por tipo; vincular um pagamento sem autorização a uma autorização
   em aberto de outra sessão; confirmar um Excedente como "Acréscimo aceito".
8. **Plano de parcelas**: em uma autorização em aberto, informar um plano de valores diferentes;
   tentar salvar com soma errada (recusado, com a diferença); salvar certo; ver as parcelas no
   extrato.
9. **Motor com plano**: em uma sessão de teste, executar com um pagamento no valor de uma parcela
   do plano; ele entra sozinho e a parcela aparece paga.
10. **Previsão**: em uma autorização "30/60/90 dias", conferir os meses esperados; depois de
    processar um mês sem o pagamento, ver a parcela atrasada e o alerta no painel; clicar no
    alerta e cair na lista filtrada.
11. **Sem sessão processada**: em um banco vazio, o painel mostra totais zerados e o link para
    "Sessões".
12. **Reabrir**: tentar reabrir uma sessão com vínculo manual; a mensagem diz onde desfazer.
13. **Perfis**: como Operador, "Criar autorização" não aparece em "Divergências"; como
    Administrador, aparece.
14. **Acessibilidade**: abrir e fechar o extrato pelo teclado; conferir que o leitor de tela
    anuncia aberto e fechado, os rótulos dos valores e as mensagens de recusa.

## Resultado esperado

- Suíte verde, incluindo as dos Módulos 1, 2 e 5.
- `vendor/bin/pint --dirty --format agent` sem alterações pendentes.
- Para toda autorização do painel, o saldo da lista, o da última linha do extrato e o guardado
  são o mesmo valor.
