# Feature Specification: Gestão de Parcelamentos e Pagamentos Parciais (Módulo 3)

**Feature Branch**: `004-installment-tracking`
**Created**: 2026-10-09
**Status**: Draft
**Input**: User description: "Módulo 3 - Gestão de Parcelamentos e Conciliação de Tolerância. Cruza autorizações e pagamentos importados pelo Módulo 1, abate o saldo devedor por pagamentos parciais, gerencia a passagem de Parcial para Conciliado, aplica a margem de tolerância, mantém uma fila de divergências com vínculo manual e estorno, e mostra o histórico das parcelas pagas em um painel com abas e listagem em acordeão, tudo com auditoria."

## Clarifications

### Session 2026-10-09

- Q: Quando uma compra é paga em parcelas de valores diferentes, como o sistema deve reconhecer
  cada parcela? → A: O Operador informa o plano de parcelas na autorização (os valores, uma vez),
  e o sistema vincula sozinho o pagamento que bater com uma parcela ainda em aberto.
- Q: A aba "Divergências" traz só os pagamentos sem autorização e os Excedentes, ou também os
  Dúbios e Parciais de todas as sessões? → A: Só pagamentos sem autorização e Excedentes; Dúbios
  e Parciais continuam sendo decididos na tela da sessão.
- Q: Onde o painel de autorizações deve ficar? → A: Substitui o conteúdo da página "Dashboard"
  atual e passa a ser a tela inicial depois do login.

## Contexto: o que já existe e o que este módulo acrescenta

O pedido original descreve dez requisitos. Sete deles já foram entregues pelo Módulo 2 (motor de
conciliação) e continuam valendo sem mudança. Este módulo não os refaz: ele se apoia neles.

| Pedido original | Situação |
|-----------------|----------|
| FR1 – consumir em segundo plano o que o Módulo 1 importou | Entregue no Módulo 2 |
| FR2 – abater o saldo a cada pagamento vinculado | Entregue no Módulo 2 |
| FR3 – manter "Parcial" enquanto houver saldo | Entregue no Módulo 2 |
| FR4 – margem de tolerância em valor, percentual ou ambos | Entregue no Módulo 2 (tela "Tolerância") |
| FR5 – passar a "Conciliada" quando os pagamentos atingem o autorizado, dentro da tolerância | Entregue no Módulo 2 |
| FR6 – fila de divergências | Entregue por sessão no Módulo 2; **este módulo junta as sessões** |
| FR7 – vínculo manual por qualquer operador | Entregue no Módulo 2; **reaproveitado no painel** |
| FR8 – auditoria de todo ajuste manual | Entregue no Módulo 2 |
| FR9 – desfazer o vínculo a partir do extrato | Regra entregue no Módulo 2; **o extrato é novo** |
| FR10 – painel, abas e listagem em acordeão | **Novo, é o centro deste módulo** |

O que este módulo entrega de novo:

1. Um **painel de autorizações que atravessa as sessões**, com resumo financeiro, alertas e as
   abas "Em aberto / Parcial", "Conciliadas" e "Divergências".
2. O **extrato de cada autorização**, aberto na própria lista, com cada pagamento abatido, o
   saldo depois de cada um e as parcelas que ainda faltam.
3. **Desfazer** um pagamento a partir do extrato.
4. **Parcelas de valores diferentes** (entrada mais parcelas, última parcela maior), que o
   Módulo 2 deixou de fora.
5. **Previsão das parcelas que faltam**, com aviso das que já deveriam ter sido pagas.

Vocabulário: o pedido fala em "Pendente", "Divergente" e "Estornar". O sistema já usa "Aberta"
para a autorização sem pagamento, "Sem autorização" e "Excedente" para os dois tipos de
divergência, e "Desvincular" para desfazer. Este documento usa os termos do sistema e cita os do
pedido onde ajuda.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver a situação de todas as autorizações em um só lugar (Priority: P1)

O Operador abre o painel e vê, sem escolher sessão, quanto foi autorizado, quanto já foi pago e
quanto falta, e a lista das autorizações separada em "Em aberto / Parcial" e "Conciliadas". Uma
compra autorizada em julho e paga em parcelas até outubro aparece em uma linha só, com o saldo de
hoje.

**Why this priority**: Hoje cada sessão mostra só o seu mês. Para saber quanto ainda se deve de
uma compra parcelada é preciso abrir sessão por sessão. Sem esta visão o resto do módulo não tem
onde aparecer.

**Independent Test**: Com duas sessões processadas, uma autorização de R$ 900,00 em julho que
recebeu R$ 300,00 em julho e R$ 300,00 em agosto, abrir o painel e conferir que ela aparece uma
vez em "Em aberto / Parcial", com pago de R$ 600,00 e saldo de R$ 300,00, e que os totais do
resumo somam todas as autorizações das duas sessões.

**Acceptance Scenarios**:

1. **Given** autorizações em várias sessões processadas, **When** o Operador entra no sistema ou
   aciona "Dashboard" no menu,
   **Then** vê o total autorizado, o total pago, o saldo em aberto, e a quantidade de
   autorizações Abertas, Parciais e Conciliadas, somando todas as sessões.
2. **Given** o painel aberto, **When** o Operador escolhe a aba "Em aberto / Parcial", **Then**
   vê as autorizações com saldo maior que zero, com fornecedor, valor autorizado, valor pago,
   saldo, quantidade de pagamentos, condição de pagamento e a sessão de origem.
3. **Given** o painel aberto, **When** o Operador escolhe a aba "Conciliadas", **Then** vê as
   autorizações com saldo zero, com a data do último pagamento.
4. **Given** uma autorização de julho com pagamentos vinculados em julho e em agosto, **When**
   ela aparece no painel, **Then** aparece em uma única linha, com a soma dos dois pagamentos.
5. **Given** a lista das abas de autorizações, **When** o Operador busca por fornecedor ou filtra
   por situação, sessão de origem ou cartão, **Then** a lista e a faixa de totais da aba
   (autorizado, pago e saldo) passam a refletir o filtro, e o endereço da página guarda o filtro.
6. **Given** uma sessão aberta ou em processamento, **When** o painel é exibido, **Then** os
   lançamentos dela não entram nos totais nem nas listas.
7. **Given** uma autorização de planilha substituída ou de sessão excluída, **When** o painel é
   exibido, **Then** ela não aparece.

---

### User Story 2 - Abrir o extrato de uma autorização na própria lista (Priority: P1)

O Operador clica em uma autorização e a linha se abre, mostrando cada pagamento que foi abatido:
data, valor, de que sessão veio, como foi vinculado (sozinho, como parcela, por alguém) e o saldo
que ficou depois dele. Não precisa sair da lista nem abrir outra tela.

**Why this priority**: É a "listagem em acordeão" do pedido e a resposta para a pergunta mais
comum sobre uma compra parcelada: quais parcelas já foram pagas, e quando.

**Independent Test**: Para a autorização do teste anterior, clicar na linha e conferir duas
linhas no extrato, em ordem de data, com saldo de R$ 600,00 depois da primeira e de R$ 300,00
depois da segunda; clicar de novo e conferir que o extrato se fecha.

**Acceptance Scenarios**:

1. **Given** uma autorização com pagamentos vinculados, **When** o Operador clica na linha,
   **Then** a linha se expande e mostra cada pagamento com data, fornecedor do pagamento, valor,
   sessão, forma do vínculo (automático, parcela, manual), quem decidiu quando houve decisão, e o
   saldo depois daquele pagamento.
2. **Given** o extrato aberto, **When** há desconto, baixa por tolerância, acréscimo aceito ou
   pagamento a maior, **Then** cada um aparece como uma linha própria do extrato, com o valor e a
   justificativa, de modo que o saldo final bate com a soma das linhas.
3. **Given** uma autorização sem nenhum pagamento, **When** o Operador a expande, **Then** vê a
   mensagem de que ainda não há pagamento vinculado e, se houver, as parcelas previstas.
4. **Given** o extrato aberto, **When** o Operador clica de novo na linha ou usa o teclado para
   fechá-la, **Then** o extrato se recolhe.
5. **Given** o painel recém-aberto, **When** o Operador quer ver o extrato de uma autorização,
   **Then** chega a ele em no máximo dois cliques: escolher a aba e abrir a linha.
6. **Given** uma autorização criada na conciliação, **When** aparece na lista ou no extrato,
   **Then** está marcada como tal.

---

### User Story 3 - Desfazer um pagamento a partir do extrato (Priority: P2)

No extrato, cada pagamento tem "Desfazer". O Operador confirma, o pagamento deixa de abater a
autorização, o saldo e a situação são recalculados e o pagamento volta para as divergências.

**Why this priority**: O desvínculo já existe na tela de cada sessão. Tê-lo no extrato evita
procurar a sessão em que o pagamento foi vinculado, o que em compra parcelada é trabalhoso.

**Independent Test**: No extrato de uma autorização Conciliada por três parcelas, desfazer a
segunda; conferir que a autorização volta a Parcial com o saldo da parcela desfeita, que o
pagamento aparece na aba "Divergências" e que a auditoria registrou quem desfez.

**Acceptance Scenarios**:

1. **Given** o extrato de uma autorização Parcial ou Conciliada, **When** o Operador aciona
   "Desfazer" em um pagamento e confirma, **Then** o vínculo é desfeito, o saldo e a situação são
   recalculados e o extrato mostra o novo saldo.
2. **Given** um pagamento desfeito, **When** o Operador abre a aba "Divergências", **Then** o
   pagamento está lá, como "Sem autorização".
3. **Given** um pagamento desfeito, **When** a sessão dele é executada de novo, **Then** aquele
   par não volta a ser vinculado sozinho.
4. **Given** qualquer desvínculo, **When** ele é concluído, **Then** a auditoria registra quem
   desfez, quando, e os dados do vínculo desfeito (origem, quem tinha decidido, valores e saldo
   antes e depois).
5. **Given** uma autorização criada na conciliação com um único pagamento, **When** esse
   pagamento é desfeito, **Then** a autorização criada deixa de existir e o pagamento volta às
   divergências.
6. **Given** um pagamento cujo vínculo foi desfeito por outra pessoa enquanto o extrato estava
   aberto, **When** o Operador aciona "Desfazer", **Then** a ação é recusada com o motivo e o
   extrato é atualizado.

---

### User Story 4 - Tratar as divergências de todas as sessões (Priority: P2)

A aba "Divergências" junta, de todas as sessões processadas, os pagamentos que não encontraram
autorização e os que passam do saldo da autorização mais provável. O Operador vincula o pagamento
a uma autorização em aberto, de qualquer sessão que possa recebê-lo, dizendo o que a diferença
significa quando o valor não bate.

**Why this priority**: As ações já existem por sessão. Reuni-las tira do Operador o trabalho de
lembrar em que mês ficou cada pendência, mas o trabalho já pode ser feito hoje.

**Independent Test**: Com um pagamento "Sem autorização" em julho e outro "Excedente" em agosto,
abrir a aba e ver os dois; vincular o primeiro a uma autorização em aberto e conferir que ele sai
da aba, que o saldo da autorização mudou e que a auditoria registrou o operador.

**Acceptance Scenarios**:

1. **Given** sessões processadas com pagamentos sem autorização e sugestões Excedentes
   pendentes, **When** o Operador abre "Divergências", **Then** vê os dois tipos, cada um
   identificado, com unidade, fornecedor, valor, data, sessão e, no Excedente, a autorização
   sugerida e quanto passa do saldo.
2. **Given** um pagamento sem autorização, **When** o Operador aciona "Vincular" e escolhe uma
   autorização em aberto, **Then** o saldo e a situação da autorização são atualizados, o
   pagamento sai da aba e a auditoria registra o operador, a data e a ação.
3. **Given** um pagamento que passa do saldo da autorização escolhida, **When** o Operador
   confirma o vínculo, **Then** precisa dizer se é "Pagamento a maior" ou "Acréscimo aceito", com
   justificativa neste segundo caso, como já acontece na tela da sessão.
4. **Given** um pagamento menor que o saldo, **When** o Operador confirma o vínculo, **Then**
   precisa dizer se "Ainda falta pagar" ou se encerra com desconto.
5. **Given** a aba "Divergências", **When** o Operador filtra por tipo, unidade, sessão, código
   de operação ou cartão, ou busca por fornecedor, **Then** a lista reflete o filtro.
6. **Given** pagamentos excluídos por código de operação ou duplicados de outro período,
   **When** a aba é exibida, **Then** eles não aparecem.
7. **Given** um pagamento sem autorização, **When** um Administrador o vê, **Then** tem também a
   ação de criar a autorização correspondente, que o Operador não vê.

---

### User Story 5 - Reconhecer parcelas de valores diferentes (Priority: P2)

Nem todo parcelamento tem parcelas iguais: há entrada maior, última parcela com o resto, ou
valores combinados caso a caso. O Operador informa, na autorização, o plano de parcelas (os
valores, e opcionalmente as datas previstas). A partir daí, cada pagamento do fornecedor com o
valor de uma parcela ainda não paga do plano é vinculado sozinho, como já acontece com as
parcelas iguais.

**Why this priority**: O Módulo 2 só reconhece parcelas iguais. Em julho/2026 só 6 autorizações
indicam parcelamento, então o ganho imediato é pequeno, mas cada caso sem essa regra exige uma
confirmação manual por mês.

**Independent Test**: Informar em uma autorização de R$ 1.000,00 o plano "R$ 400,00 de entrada e
duas de R$ 300,00"; processar três sessões com pagamentos de R$ 400,00, R$ 300,00 e R$ 300,00 do
mesmo fornecedor; conferir que os três são vinculados sozinhos e a autorização fica Conciliada.

**Acceptance Scenarios**:

1. **Given** uma autorização em aberto, **When** o Operador informa um plano de parcelas cuja
   soma é igual ao valor autorizado, **Then** o plano é salvo, aparece no extrato como parcelas
   previstas e a auditoria registra quem informou.
2. **Given** um plano cuja soma difere do valor autorizado além da tolerância, **When** o
   Operador tenta salvar, **Then** o plano é recusado com a diferença apontada.
3. **Given** uma autorização com plano de R$ 400,00, R$ 300,00 e R$ 300,00, **When** uma sessão
   traz um pagamento de R$ 400,00 do mesmo fornecedor, **Then** ele é vinculado sozinho como
   parcela e a parcela de R$ 400,00 aparece como paga no extrato.
4. **Given** o mesmo plano com a parcela de R$ 400,00 já paga, **When** chega outro pagamento de
   R$ 400,00, **Then** ele não é vinculado sozinho, porque não há parcela desse valor em aberto,
   e fica para decisão do Operador.
5. **Given** um plano informado, **When** chega um pagamento cujo valor não é o de nenhuma
   parcela em aberto, **Then** o par aparece como Parcial com o aviso de que o valor não
   corresponde ao plano, como já acontece com a parcela prevista.
6. **Given** uma autorização com plano informado e também condição de pagamento "3x" na
   planilha, **When** a conciliação é executada, **Then** vale o plano informado.
7. **Given** um plano informado, **When** o Operador o altera ou remove, **Then** os pagamentos
   já vinculados não são afetados, a mudança vale para as próximas execuções e a auditoria
   registra os valores anterior e novo.
8. **Given** uma autorização que já tem pagamentos vinculados, **When** o Operador informa o
   plano, **Then** cada pagamento já vinculado ocupa a parcela de mesmo valor do plano, em ordem
   de data, e só as parcelas restantes ficam em aberto.
9. **Given** uma parcela vinculada pelo plano, **When** valem as proteções das parcelas iguais,
   **Then** cartão diferente não vincula sozinho, um pagamento que serve a duas autorizações
   fica para o Operador, e a soma dos vínculos nunca passa do valor autorizado.

---

### User Story 6 - Saber quais parcelas faltam e quais estão atrasadas (Priority: P3)

O extrato mostra, abaixo dos pagamentos, as parcelas que ainda faltam, com o valor e o mês em que
cada uma é esperada. O painel avisa quantas autorizações têm parcela esperada em um mês cuja
sessão já foi processada sem que o pagamento tenha aparecido.

**Why this priority**: É informação de acompanhamento. Ajuda a cobrar um pagamento que não veio,
mas nada deixa de ser conciliado sem ela.

**Independent Test**: Com uma autorização "30/60/90 dias" de R$ 900,00 autorizada em julho e uma
parcela paga em agosto, processar setembro sem pagamento do fornecedor; conferir que o extrato
mostra duas parcelas faltando, a de setembro marcada como atrasada, e que o painel conta essa
autorização no alerta.

**Acceptance Scenarios**:

1. **Given** uma autorização com parcelas previstas pela condição de pagamento ou pelo plano
   informado, **When** o Operador abre o extrato, **Then** vê as parcelas que faltam, com valor e
   mês esperado.
2. **Given** uma condição com prazos em dias ("30/60/90 dias"), **When** os meses esperados são
   calculados, **Then** cada parcela é esperada no mês da data da autorização somada ao prazo.
3. **Given** uma condição "Nx" sem prazos, **When** os meses esperados são calculados, **Then**
   as parcelas são esperadas uma por mês, a partir do mês seguinte ao da autorização.
4. **Given** uma parcela esperada em um mês igual ou anterior ao da sessão processada mais
   recente, **When** não há pagamento vinculado para ela, **Then** ela aparece como atrasada no
   extrato e a autorização entra no alerta do painel, mesmo que o mês esperado nunca tenha tido
   sessão.
5. **Given** o alerta do painel, **When** o Operador clica nele, **Then** a aba "Em aberto /
   Parcial" abre filtrada pelas autorizações com parcela atrasada.
6. **Given** uma autorização sem condição reconhecida e sem plano, **When** o extrato é aberto,
   **Then** não há previsão, só o saldo.
7. **Given** uma parcela paga antes ou depois do mês esperado, **When** o extrato é exibido,
   **Then** ela conta como paga; a previsão nunca impede nem atrasa um vínculo.

---

### Edge Cases

- **Vários pagamentos da mesma autorização na mesma execução**: são aplicados um depois do outro,
  em ordem de data, e cada um vê o saldo deixado pelo anterior. O saldo nunca é calculado em
  paralelo (já garantido pelo Módulo 2; este módulo não pode criar um caminho que escape disso).
- **Duas pessoas agindo na mesma autorização**: se uma desfaz um pagamento enquanto outra vincula
  um novo, as ações acontecem uma por vez e a segunda trabalha com o saldo já atualizado; se o
  que ela pretendia deixou de valer, é recusada com o motivo.
- **Exclusão na origem**: uma sessão processada não pode ser excluída, e só pode ser reaberta
  depois de desfeitas as decisões manuais e os vínculos com outras sessões (já garantido pelos
  Módulos 1 e 2). A mensagem de recusa deve dizer onde desfazer: no extrato da autorização.
- **Planilha de autorizações substituída**: só é possível com a sessão reaberta, o que já exige
  desfazer os vínculos. O plano de parcelas informado em uma autorização da planilha antiga é
  reaproveitado quando a mesma autorização (mesmo identificador) volta na planilha nova.
- **Tolerância em valor e em percentual ao mesmo tempo**: vale a mais permissiva das duas, com o
  percentual limitado ao teto (regra já em vigor).
- **Tolerância alterada no meio de um parcelamento**: cada vínculo guarda a tolerância da
  execução em que foi feito; o extrato não recalcula vínculos antigos.
- **Pagamento a maior**: aparece no extrato com o valor excedente em destaque, e a autorização
  aparece em "Conciliadas" com o alerta.
- **Autorização encerrada com desconto**: aparece em "Conciliadas", com a linha do desconto no
  extrato.
- **Desfazer um pagamento do meio**: o extrato é recalculado inteiro; os saldos depois de cada
  pagamento seguinte mudam.
- **Plano de parcelas menor do que o que já foi pago**: se o plano novo tem menos parcelas do
  que a quantidade de pagamentos já vinculados à autorização, a alteração é recusada.
- **Tolerância usada pelo plano**: validar a soma do plano e decidir que pagamento ocupa que
  parcela usam a tolerância em vigor no momento da leitura. Isso não altera nenhum vínculo: o
  saldo e os abatimentos de cada vínculo continuam os que foram gravados na execução dele.
- **Autorização muito antiga com saldo**: continua no painel enquanto tiver saldo, sem limite de
  meses. Dar baixa nela é assunto do Módulo 4.
- **Lista grande**: o painel precisa continuar utilizável com dezenas de milhares de
  autorizações acumuladas ao longo dos anos; as listas são paginadas e os totais vêm prontos.

## Requirements *(mandatory)*

### Functional Requirements

**Painel**

- **FR-001**: O sistema DEVE oferecer um painel de autorizações que reúne as autorizações ativas
  de todas as sessões processadas, sem que o usuário escolha uma sessão.
- **FR-002**: O painel DEVE mostrar um resumo com o total autorizado, o total pago, o saldo em
  aberto, os totais de desconto, acréscimo aceito e pagamento a maior, e a quantidade de
  autorizações Abertas, Parciais e Conciliadas.
- **FR-003**: O painel DEVE ter as abas "Em aberto / Parcial" (saldo maior que zero),
  "Conciliadas" (saldo zero) e "Divergências", e o endereço da página DEVE guardar a aba, a
  busca, os filtros e a página.
- **FR-004**: Cada autorização DEVE aparecer uma única vez, com fornecedor, pedido, valor
  autorizado, valor pago, saldo, quantidade de pagamentos, condição de pagamento, cartão, sessão
  de origem e situação. A unidade é do pagamento e aparece em cada linha do extrato.
- **FR-005**: As listas DEVEM permitir busca por fornecedor e filtros por situação, sessão de
  origem, cartão, autorizações com parcela atrasada e autorizações com pagamento a maior.
- **FR-005a**: Cada aba de autorizações DEVE mostrar uma faixa de totais (quantidade, valor
  autorizado, valor pago e saldo) do que está listado, que acompanha a busca e os filtros. O
  resumo do alto do painel é sempre o de todas as autorizações.
- **FR-006**: Lançamentos de sessão aberta ou em processamento, de planilha substituída, de
  sessão excluída, pagamentos excluídos por código e duplicados de outro período NÃO DEVEM entrar
  nas listas nem nos totais.
- **FR-007**: O painel DEVE ocupar a página "Dashboard" já existente no menu, no lugar do
  conteúdo de boas-vindas, e DEVE ser a tela exibida depois do login, para Operadores e
  Administradores.
- **FR-007a**: Sem nenhuma sessão processada, o painel DEVE mostrar os totais zerados e uma
  orientação para criar e executar a primeira sessão, com o link para "Sessões".

**Extrato**

- **FR-008**: Ao acionar uma autorização na lista, a linha DEVE se expandir na própria página e
  mostrar o extrato; acionar de novo DEVE recolhê-la. O extrato DEVE ser operável pelo teclado e
  anunciar a quem usa leitor de tela se está aberto ou fechado.
- **FR-009**: O extrato DEVE listar, em ordem de data do pagamento, cada pagamento vinculado, com
  data, fornecedor do pagamento, valor, unidade, sessão, forma do vínculo (automático, parcela,
  manual, autorização criada), quem decidiu e quando, e o saldo depois dele.
- **FR-010**: Desconto, baixa por tolerância, acréscimo aceito e pagamento a maior DEVEM aparecer
  no extrato com valor e justificativa, de modo que valor autorizado, menos pagamentos, menos
  abatimentos, seja igual ao saldo exibido.
- **FR-011**: O extrato DEVE ser sempre derivado dos vínculos existentes. Nenhuma tela deste
  módulo permite digitar ou corrigir um saldo.
- **FR-012**: Do painel recém-aberto, o extrato de qualquer autorização visível DEVE estar a no
  máximo dois cliques.

**Desfazer**

- **FR-013**: Cada pagamento do extrato DEVE ter a ação "Desfazer", com confirmação, que desfaz
  o vínculo pelas mesmas regras do desvínculo do Módulo 2: saldo e situação recalculados, o par
  não volta a ser vinculado sozinho, sugestões afastadas voltam, e a autorização criada na
  conciliação deixa de existir quando perde seu único pagamento.
- **FR-014**: O pagamento desfeito DEVE aparecer na aba "Divergências".
- **FR-015**: Desfazer DEVE ser recusado, com o motivo, quando o vínculo já não existe ou quando
  a sessão do pagamento não está processada.

**Divergências**

- **FR-016**: A aba "Divergências" DEVE reunir, de todas as sessões processadas, os pagamentos
  sem autorização e os pagamentos com sugestão Excedente pendente, identificando o tipo de cada
  um.
- **FR-016a**: Sugestões Dúbias e Parciais NÃO DEVEM aparecer na aba "Divergências"; continuam
  sendo decididas na tela da sessão.
- **FR-017**: A aba DEVE permitir busca por fornecedor e filtros por tipo, unidade, sessão,
  código de operação e cartão.
- **FR-018**: Qualquer Operador ou Administrador DEVE poder vincular um pagamento da aba a uma
  autorização em aberto que possa recebê-lo, informando o tratamento da diferença quando o valor
  não couber na tolerância, pelas mesmas regras e recusas do vínculo manual do Módulo 2.
- **FR-018a**: Para um pagamento Excedente, o Operador DEVE poder confirmar a sugestão,
  informando "Pagamento a maior" ou "Acréscimo aceito", ou rejeitá-la; rejeitado, o pagamento
  passa a "Sem autorização".
- **FR-019**: Criar a autorização correspondente a partir da aba DEVE continuar restrito ao
  Administrador.

**Parcelas de valores diferentes**

- **FR-020**: O sistema DEVE permitir informar, em uma autorização com saldo, um plano de
  parcelas: a lista dos valores e, opcionalmente, o mês esperado de cada uma.
- **FR-021**: A soma do plano DEVE ser igual ao valor autorizado, dentro da tolerância; caso
  contrário o plano é recusado, com a diferença apontada.
- **FR-022**: Havendo plano, o motor DEVE vincular sozinho, como parcela, o pagamento do mesmo
  fornecedor cujo valor seja o de uma parcela ainda não paga do plano, dentro da tolerância, nas
  mesmas condições das parcelas iguais: compatibilidade do fornecedor no limite da conciliação
  automática, valor que caiba no saldo, cartão que não seja diferente, e pagamento que não sirva
  a outra autorização.
- **FR-023**: Cada parcela do plano DEVE receber no máximo um pagamento. Um segundo pagamento com
  o valor de uma parcela já paga NÃO DEVE ser vinculado sozinho.
- **FR-023a**: Ao informar o plano em uma autorização que já tem pagamentos, cada pagamento já
  vinculado DEVE ocupar uma parcela de mesmo valor (dentro da tolerância), em ordem de data. O
  pagamento que não corresponder a nenhuma parcela continua abatendo o saldo e aparece no extrato
  como fora do plano.
- **FR-024**: O plano informado DEVE prevalecer sobre a condição de pagamento da planilha.
- **FR-025**: Alterar ou remover o plano NÃO DEVE mexer nos vínculos existentes, DEVE valer só
  para as execuções seguintes, e DEVE ser recusado quando o plano novo tiver menos parcelas do
  que a quantidade de pagamentos já vinculados à autorização.
- **FR-026**: O plano é só um indício, como a condição de pagamento: NÃO DEVE reduzir a nota,
  impedir a conciliação pelo valor total nem impedir a classificação como Parcial ou Excedente.

**Previsão**

- **FR-027**: O extrato DEVE mostrar as parcelas que faltam, com valor e mês esperado, quando a
  autorização tem plano informado ou condição de pagamento reconhecida com mais de uma parcela.
- **FR-028**: O mês esperado DEVE vir do plano, quando informado; dos prazos em dias da condição,
  somados à data da autorização; ou, sem prazos, de uma parcela por mês a partir do mês seguinte
  ao da autorização.
- **FR-029**: Uma parcela DEVE ser considerada atrasada quando não foi paga e o mês esperado é
  igual ou anterior ao mês da sessão processada mais recente. O painel DEVE mostrar quantas autorizações têm parcela atrasada
  e levar à lista filtrada.
- **FR-030**: A previsão NÃO DEVE impedir, atrasar nem alterar nenhum vínculo.

**Auditoria e integridade**

- **FR-031**: Informar, alterar e remover um plano de parcelas, vincular a partir da aba
  "Divergências" e desfazer a partir do extrato DEVEM gravar na trilha de auditoria, na mesma
  operação, quem fez, quando, o que foi feito e os valores anterior e novo.
- **FR-032**: Nenhuma ação deste módulo DEVE alterar o saldo de uma autorização sem deixar um
  registro de auditoria que não pode ser editado nem apagado.
- **FR-033**: Ações simultâneas sobre a mesma autorização DEVEM ser aplicadas uma por vez, e o
  saldo exibido DEVE ser sempre o resultado dos vínculos existentes.
- **FR-034**: A recusa de reabrir uma sessão por causa de decisões manuais DEVE indicar que os
  vínculos são desfeitos no extrato da autorização ou na aba Conciliados da sessão.

### Key Entities

- **Autorização**: a compra autorizada, com valor autorizado, fornecedor, data, condição de
  pagamento e cartão. Seu saldo e sua situação (Aberta, Parcial, Conciliada) são derivados dos
  pagamentos vinculados. Já existe; ganha o plano de parcelas.
- **Plano de parcelas**: o que o Operador informa sobre uma autorização: uma lista ordenada de
  parcelas, cada uma com valor e, opcionalmente, mês esperado, mais quem informou e quando. Uma
  autorização tem no máximo um plano em vigor.
- **Parcela prevista**: uma posição do plano (ou da condição de pagamento), que está paga quando
  há um pagamento vinculado para ela, em aberto, ou atrasada.
- **Vínculo**: a ligação entre um pagamento e uma autorização, com origem, tratamento da
  diferença, justificativa e quem decidiu. Já existe; é a fonte do extrato.
- **Linha do extrato**: cada movimento que muda o saldo de uma autorização (pagamento, desconto,
  baixa por tolerância, acréscimo aceito, pagamento a maior), com o saldo resultante. Não é
  guardada: é calculada dos vínculos.
- **Divergência**: um pagamento de sessão processada que não tem autorização, ou que só tem uma
  sugestão Excedente pendente. Não é uma entidade nova: é uma visão do que o Módulo 2 já mantém,
  agora reunida entre as sessões.
- **Registro de auditoria**: quem fez, o que fez, quando, e os valores antes e depois. Já existe.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A partir do painel, o Operador vê o histórico completo de pagamentos de qualquer
  autorização em no máximo dois cliques, sem trocar de página.
- **SC-002**: Para 100% das autorizações, o saldo mostrado no painel e no extrato é igual ao
  valor autorizado menos os pagamentos e os abatimentos listados no extrato.
- **SC-003**: Uma compra paga em parcelas ao longo de vários meses aparece em uma única linha do
  painel, e responder "quanto falta pagar e quais parcelas já vieram" leva menos de 30 segundos.
- **SC-004**: 100% dos desvínculos feitos pelo extrato, dos vínculos feitos pela aba
  "Divergências" e das alterações de plano de parcelas têm registro de auditoria com o autor, a
  data e os valores anterior e novo.
- **SC-005**: Não existe, em nenhuma tela, caminho para alterar o saldo de uma autorização que
  não passe por criar ou desfazer um vínculo auditado.
- **SC-006**: Com plano de parcelas informado, 100% dos pagamentos cujo valor corresponde a uma
  parcela em aberto, do mesmo fornecedor e sem disputa com outra autorização, são vinculados sem
  clique; nenhuma parcela do plano recebe mais de um pagamento automático.
- **SC-007**: Nenhum vínculo automático de parcela faz a soma dos pagamentos passar do valor
  autorizado além da tolerância.
- **SC-008**: O painel abre em menos de 3 segundos com 50.000 autorizações acumuladas, e abrir um
  extrato leva menos de 1 segundo.
- **SC-009**: Duas ações simultâneas sobre a mesma autorização nunca deixam saldo diferente do
  que os vínculos existentes produzem.
- **SC-010**: 100% das autorizações com parcela esperada em mês já processado e não paga
  aparecem no alerta do painel.

## Assumptions

- **Escopo**: os requisitos FR1 a FR5 e FR8 do pedido já estão entregues pelo Módulo 2 e não são
  refeitos. Este módulo entrega o painel entre sessões, o extrato, o desfazer pelo extrato, as
  divergências reunidas, as parcelas de valores diferentes e a previsão.
- **Fronteira com o Módulo 4**: este módulo mostra e permite vincular e desfazer. Dar baixa em
  autorização sem pagamento, dispensar um pagamento sem autorização e vincular a autorizações
  fora da janela de meses continuam no Módulo 4, que acrescentará essas ações ao mesmo painel.
- **Situação "Pendente"**: é a "Aberta" do sistema (autorização sem nenhum pagamento).
- **"Divergente"**: o pedido usa uma palavra só para o pagamento sem autorização e para o que
  passa da tolerância. O sistema mantém os dois tipos ("Sem autorização" e "Excedente"), reunidos
  na mesma aba, porque o tratamento é diferente.
- **História 3 do pedido** (R$ 510,00 para saldo de R$ 500,00 com tolerância de R$ 5,00): já é o
  comportamento atual: o par fica como Excedente, não é conciliado sozinho.
- **Margem híbrida**: vale a mais permissiva entre o valor fixo e o percentual limitado ao teto,
  como já está em vigor (hoje R$ 0,50 ou 1% até R$ 200,00).
- **"Vínculo forçado"**: é o vínculo manual já existente, com o tratamento da diferença
  obrigatório quando o valor não cabe na tolerância. Não há vínculo que ignore essas regras.
- **Quem pode**: Operador e Administrador veem o painel, vinculam, desfazem e informam plano de
  parcelas. Criar autorização correspondente continua só com o Administrador.
- **Plano de parcelas**: é informado à mão, autorização por autorização. Não vem da planilha.
  Assume-se até 120 parcelas por plano.
- **Previsão**: trabalha por mês, não por dia, porque as sessões são mensais. É um
  acompanhamento, não uma cobrança: não há notificação por e-mail nem bloqueio.
- **Volume**: 136 autorizações por mês em julho/2026; em cinco anos, menos de 10.000. A meta de
  50.000 dá folga.
- **Fora do escopo**: baixa e dispensa (Módulo 4); relatórios exportáveis e a tela da trilha de
  auditoria (Módulo 6); notificações; importação de planos de parcelas por planilha; juros,
  multa e correção de parcelas atrasadas.
