# Research: Gestão de Parcelamentos e Pagamentos Parciais (Módulo 3)

Não havia pontos "NEEDS CLARIFICATION" no contexto técnico. As decisões abaixo resolvem as
escolhas de desenho.

## R1. Stack e reaproveitamento

- **Decision**: mesma stack dos módulos anteriores, sem dependência nova. Reaproveitar
  `ReconciliationLinker`, `AuthorizationStateCalculator`, `LinkManually`, `ConfirmSuggestion`,
  `RemoveLink`, `CreateMatchingAuthorization`, os traits `DecidesPendingItems`,
  `ShowsEntryDetails` e `ShowsRefusal`, e a visão `reconciliation_pending_items`.
- **Rationale**: o saldo, a tolerância, o vínculo e o desvínculo já existem e estão testados.
  Refazê-los criaria uma segunda fonte para o saldo, o que a constituição proíbe (IX).
- **Alternatives considered**: um conjunto de ações próprio do painel. Rejeitado: duplicaria as
  recusas e a auditoria.

## R2. Onde o painel fica

- **Decision**: a rota `/dashboard` (nome `dashboard`, destino do login em `config/fortify.php`)
  passa a apontar para o componente `App\Livewire\Dashboard\Show`. A view `dashboard.blade.php`
  e o componente `<x-welcome>` deixam de ser usados pela rota. A aba fica na URL como
  `?aba=abertas|conciliadas|divergencias`; padrão `abertas`.
- **Rationale**: resposta da clarificação (pergunta 3). Manter o nome da rota evita mexer no
  menu, no redirecionamento do login e nos testes do Jetstream.
- **Alternatives considered**: item novo de menu. Rejeitado na clarificação.

## R3. Quais autorizações entram no painel

- **Decision**: um escopo único, em `AuthorizationPanel`, usado pela lista e pelos totais:
  autorização de arquivo ativo ou criada na conciliação; sessão com situação Processada; sem
  registro em `reconciliation_skips` (duplicada de outro período). A situação vem de
  `authorization_states`; sem linha de estado, a autorização está Aberta e o saldo é o valor
  autorizado.
- **Rationale**: FR-006. É o mesmo critério que o motor usa para ler autorizações de sessões
  anteriores, então o painel e o motor nunca discordam sobre o que existe.
- **Totais**: uma consulta de agregação (`SUM`, `COUNT` com `CASE`), nunca soma em PHP. Com o
  filtro da aba aplicado, a mesma consulta recebe os mesmos filtros.
- **Alternatives considered**: tabela de totais mantida a cada vínculo. Rejeitado: 50.000 linhas
  agregam em milissegundos com os índices certos, e uma tabela de totais é mais um lugar para
  ficar errado.

## R4. A linha que se abre (acordeão)

- **Decision**: tabela do Filament com as colunas em `Split` e um `Panel` recolhível
  (`->collapsible()`) contendo uma `View` com o extrato. O extrato das linhas da página (25) é
  carregado com as relações já lidas (`links.payment.session`, `links.decider`, `plan.items`,
  `forecast`), sem consulta por linha.
- **Rationale**: é o recurso do Filament para conteúdo que se abre na linha, já vem com o botão
  de abrir e fechar e com a paginação, a busca e os filtros da tabela.
- **Ressalva (Princípio II)**: com colunas em `Split` a tabela não mostra o cabeçalho de colunas.
  Cada valor leva o seu rótulo na própria linha ("Autorizado", "Pago", "Saldo").
- **Verificação na implementação**: conferir, com `search-docs`, que o botão do painel
  recolhível expõe o estado aberto/fechado ao leitor de tela e funciona pelo teclado (FR-008).
- **Alternativa de reserva**: se o painel recolhível não atender à acessibilidade ou esconder
  demais, uma lista própria em Livewire (tabela HTML com `aria-expanded`, paginação do Livewire,
  filtros na URL), como a aba "Por cartão" foi na primeira versão. Custa mais código de busca e
  filtro. A troca fica restrita a `AuthorizationsTable` e à sua view.
- **Alternatives considered**: abrir o extrato em modal. Rejeitado: o pedido fala em expandir a
  linha, e o modal já existe (detalhe da autorização).

## R5. Extrato calculado, nunca gravado

- **Decision**: `AuthorizationStatement` (pura) recebe o valor autorizado e os vínculos em ordem
  de data do pagamento e devolve as linhas com o saldo depois de cada uma. Um vínculo gera a
  linha do pagamento e, quando houver, linhas de abatimento: desconto, baixa por tolerância,
  acréscimo aceito, pagamento a maior.
- **Conferência**: o saldo da última linha tem de ser igual ao `balance_cents` de
  `authorization_states`. Um teste de Feature compara os dois para todas as autorizações de um
  cenário variado (SC-002).
- **Rationale**: FR-011 e Princípio IX. O extrato é uma leitura dos vínculos; não há o que
  dessincronizar.
- **Alternatives considered**: tabela de movimentos. Rejeitado: duplicaria `reconciliation_links`.

## R6. Plano de parcelas

- **Decision**: tabelas `installment_plans` e `installment_plan_items`. O plano é identificado
  pela **chave de identidade** da autorização (`authorization_identity_key`, única), não pelo id.
- **Rationale**: quando a planilha de autorizações é substituída, a autorização ganha outro id e
  mantém a chave de identidade. Guardar pela chave faz o plano valer para a autorização que
  voltou, sem o Módulo 1 saber que o plano existe. É o mesmo recurso dos bloqueios de par.
- **Validação**: de 1 a 120 parcelas; cada valor maior que zero; a soma igual ao valor
  autorizado dentro da tolerância em vigor (FR-021); o plano novo não pode ter menos parcelas do
  que os pagamentos já vinculados que ocupam parcela (FR-025).
- **Histórico**: há uma linha por autorização; alterar substitui as parcelas; remover apaga. O
  histórico fica na auditoria, com os valores anterior e novo (FR-031).
- **Alternatives considered**: versões do plano em tabela. Rejeitado: a auditoria já guarda o
  antes e o depois, e nenhuma tela consulta versões.

## R7. Ocupação das parcelas

- **Decision**: `InstallmentSchedule` (pura) monta as parcelas previstas e diz qual pagamento
  ocupa cada uma. Regra: os pagamentos vinculados entram em ordem de data e, no empate, de
  identificador; cada um ocupa a primeira parcela livre cujo valor esteja dentro da tolerância do
  valor da parcela. O que não ocupar nenhuma fica marcado como "fora do plano" e continua
  abatendo o saldo (FR-023a).
- **Tolerância**: a ocupação e a validação da soma do plano usam a tolerância em vigor no
  momento da leitura (`EngineParametersFactory::fromSettings()`), não a de cada execução. A
  ocupação é só uma leitura: mudar a tolerância pode mudar que parcela aparece como paga, nunca o
  saldo nem os abatimentos, que ficam gravados no vínculo.
- **Origem das parcelas previstas**, nesta ordem: o plano informado; a condição de pagamento com
  mais de uma parcela (valor autorizado dividido, o resto do centavo na última); nenhuma.
- **Rationale**: a ocupação é derivada, não gravada. Desfazer um pagamento ou mudar o plano não
  exige corrigir nada: a próxima leitura já mostra a ocupação nova. A regra fixa dá o mesmo
  resultado em toda leitura (IX).
- **Alternatives considered**: gravar em cada vínculo a parcela que ele ocupa. Rejeitado: cria um
  dado que precisa ser refeito a cada desvínculo e a cada mudança de plano.

## R8. O motor e o plano

- **Decision**: `AuthorizationCandidate` ganha `planAmounts` (lista de valores das parcelas
  **em aberto** do plano, com repetição; nulo quando não há plano). No `Matcher`, havendo plano:
  - as referências de parcela são só as do plano (FR-024); a condição de pagamento e os valores
    já vinculados como "Ainda falta pagar" não entram;
  - cada vínculo consome uma parcela daquele valor; sem parcela livre, o pagamento não vincula
    sozinho (FR-023);
  - valem todas as proteções das parcelas iguais: nota no limite automático contra o valor da
    parcela, cartão não diferente, caber no saldo, pagamento que não serve a outra autorização.
- `CandidateLoader` calcula `planAmounts` com `InstallmentSchedule`, a partir do plano e dos
  vínculos já existentes.
- **Rationale**: muda o mínimo no núcleo: a etapa de parcelas já existe; passa a receber uma
  lista com quantidade no lugar de um conjunto.
- **Alternatives considered**: uma etapa separada para planos. Rejeitado: seriam duas regras de
  disputa entre autorizações para manter iguais.

## R9. Previsão e atraso

- **Decision**: `PaymentConditionParser` passa a devolver também os prazos em dias, quando a
  condição os traz. `InstallmentSchedule` dá a cada parcela prevista um mês esperado: o do plano,
  quando informado; a data da autorização mais o prazo; ou, sem prazos, um mês por parcela a
  partir do mês seguinte ao da autorização (FR-028).
- **Atraso**: parcela em aberto cujo mês esperado é igual ou anterior ao mês da sessão
  processada mais recente (FR-029). Usa-se o mês mais recente, e não "existe sessão daquele mês",
  para que um mês pulado não esconda o atraso.
- **Materialização**: `authorization_forecasts` guarda, por autorização com parcelas previstas,
  quantas estão previstas, quantas pagas, quantas atrasadas e o próximo mês esperado.
  `InstallmentForecaster` recalcula: ao vincular e desvincular (dentro da transação do
  `ReconciliationLinker`); ao salvar e remover plano; e, para todas as autorizações com saldo e
  parcelas previstas, ao fim de cada execução e na reabertura, porque o "mês processado mais
  recente" muda.
- **Rationale**: o alerta e o filtro "com parcela atrasada" viram uma consulta com índice
  (SC-008, SC-010). O conjunto recalculado em massa é pequeno: só autorizações com saldo e mais
  de uma parcela prevista.
- **Alternatives considered**: calcular o atraso em SQL a cada consulta. Rejeitado: a ocupação
  das parcelas (R7) é uma regra com tolerância e ordem, difícil de repetir em SQL nos dois bancos.

## R10. Divergências entre sessões

- **Decision**: a aba lê a visão `reconciliation_pending_items`, sem filtro de sessão, só com os
  tipos `unmatched_payment` e sugestão `excess` (FR-016, FR-016a). A visão já exclui pagamentos
  vinculados, pulados por código, duplicados e de arquivo substituído, e só traz sessão com
  execução concluída.
- **Ações**: "Vincular" no pagamento sem autorização (`LinkManually`); "Confirmar" e "Rejeitar"
  no Excedente (`ConfirmSuggestion`, `RejectSuggestion`), com o tratamento da diferença; "Criar
  autorização" para o Administrador. São as ações do trait `DecidesPendingItems`.
- **Ajuste no trait**: hoje ele lê `$this->sessionId` para autorizar, montar as listas de escolha
  e achar a execução. Passa a obter a sessão do próprio registro (a do pagamento), o que serve às
  telas da sessão e ao painel.
- **Rationale**: nenhuma estrutura nova; a aba é a mesma fila, vista sem o recorte da sessão.

## R11. Autorizações que podem receber um pagamento

- **Decision**: extrair de `LinkManually::assertAuthorizationCanReceive` a regra para
  `AuthorizationAvailability`, com duas formas: conferir uma autorização (usada pela ação) e
  listar as disponíveis para um pagamento (usada pela escolha na tela). Disponível é a
  autorização ativa, com saldo, da sessão do pagamento, ou de sessão processada de período
  anterior dentro da janela de meses, ou já com pagamento vinculado.
- **Rationale**: hoje a lista de escolha do "Vincular" a partir do pagamento mostra só as
  autorizações da própria sessão, embora a ação aceite as de sessões anteriores. No painel isso
  apareceria em todo pagamento de parcela. Uma regra só evita a lista oferecer o que a ação
  recusa.
- **Fronteira**: vincular a autorização fora da janela e sem pagamento continua recusado; é do
  Módulo 4.

## R12. Desfazer pelo extrato

- **Decision**: ação de componente com confirmação, que chama `RemoveLink` com o vínculo da
  linha. Nenhuma regra nova: bloqueio do par, volta das sugestões, exclusão da autorização criada
  e auditoria já estão na ação. A recusa (vínculo já desfeito, sessão não processada) aparece no
  diálogo de recusa.
- **Concorrência**: `RemoveLink` e `ReconciliationLinker` já travam a autorização na transação.
  Um teste de Feature dispara desfazer e vincular na mesma autorização e confere o saldo final
  contra os vínculos (SC-009).

## R13. Auditoria

- **Decision**: dois casos novos em `AuditAction`: `InstallmentPlanSaved` e
  `InstallmentPlanRemoved`, gravados na transação da mudança, com a entidade `InstallmentPlan`, o
  fornecedor como rótulo, e as listas de parcelas anterior e nova. Vincular e desfazer continuam
  com os registros que já têm.
- **Mapa polimórfico**: acrescentar `installment_plan`.

## R14. Mensagem de reabertura (FR-034)

- **Decision**: o texto de recusa de `ReopenSession` passa a dizer onde desfazer: na aba
  Conciliados da sessão ou no extrato da autorização, no Dashboard. Só muda o texto em
  `lang/pt_BR/conciliation.php`.

## R15. Desempenho

- **Decision**: índices em `authorization_states (status)` e `(balance_cents)` se ainda não
  existirem, em `authorization_forecasts (overdue_count)` e em
  `installment_plans (authorization_identity_key)` (único). A lista pagina no banco e carrega as
  relações do extrato só das 25 linhas da página.
- **Teste**: `DashboardPerformanceTest` com 50.000 autorizações e 60.000 vínculos inseridos em
  lote; abrir o painel e uma página da lista em menos de 3 s (SC-008).

## R16. Decisões tomadas durante a implementação

- **Acordeão (R4)**: ficou o painel recolhível do Filament. O botão de abrir a linha é um
  `button` com `aria-expanded` ligado ao estado, então funciona pelo teclado e informa o leitor de
  tela. A conferência no navegador continua pendente (roteiro manual, passo 14).
- **Índices que faltavam**: o teste de desempenho com 50.000 autorizações levou 113 s na primeira
  execução. A causa era a falta de índice em `reconciliation_links.authorization_entry_id`: a
  chave estrangeira não cria índice. Com os índices de `data-model.md` o painel abre em menos de
  3 s. O saldo do Módulo 2 lê os vínculos pela mesma coluna e também ganha com isso.
- **Mês processado**: a previsão conta uma sessão a partir do momento em que a execução dela é
  concluída, e não pela situação "Processada", que só é gravada um instante depois. Sem isso a
  previsão refeita no fim da execução não enxergava a própria sessão.
- **Onde a previsão é refeita na reabertura**: em `DatabaseReconciliationEngine::discardResult()`
  e não em `ReopenSession`, para o Módulo 1 continuar sem conhecer a previsão. Como o descarte
  também acontece antes de cada execução, a previsão é refeita duas vezes por execução; o
  conjunto é pequeno (autorizações com saldo).
- **Autorizações pagas de uma vez**: `InstallmentForecaster::refresh()` sai cedo quando a
  autorização não tem plano nem condição com mais de uma parcela, para não ler vínculos a cada
  vínculo automático de uma execução grande.
- **Total e diferença no formulário do plano**: o formulário não mostra a soma enquanto se
  digita. A soma é conferida ao salvar, e a recusa diz quanto falta ou sobra.
- **Lista por página**: os parâmetros de tolerância e o mês processado mais recente são lidos uma
  vez por página, não por linha; um teste limita a quantidade de consultas da lista.
- **Aviso "Fora da parcela prevista"** na tela da sessão: passou a comparar com todas as parcelas
  em aberto (do plano ou da condição), e não só com a divisão em partes iguais.

## Pendências que dependem do responsável

1. **Acessibilidade do painel recolhível** (R4): conferir no navegador, com teclado e leitor de
   tela, junto com o passo de acessibilidade que ficou pendente do Módulo 2.
2. **PostgreSQL**: as agregações do painel e as travas só se provam lá (pendência já registrada).
3. **Parcelas reais**: julho/2026 tem 6 autorizações com condição parcelada e nenhuma com
   parcelas diferentes conhecidas. A regra de plano foi desenhada sem caso real; revisar quando
   agosto for importado.
