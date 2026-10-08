# Feature Specification: Motor de Conciliação e Tratamento de Divergências (Módulo 2)

**Feature Branch**: `003-reconciliation-engine`
**Created**: 2026-10-08
**Status**: Draft
**Input**: User description: "Motor de Conciliação e Tratamento de Divergências. Um motor que cruza Autorizações de Despesas com Pagamentos Realizados, concilia automaticamente os cruzamentos com Score acima de 90 usando uma margem de tolerância global, registra auditoria rigorosa das ações manuais e mantém uma fila para tratamento de divergências (dúbios, parciais, excedentes e órfãos)."

## Clarifications

### Session 2026-10-08

- Q: Ao conciliar uma sessão, o motor compara só os lançamentos dela ou também as autorizações
  ainda abertas de sessões anteriores? → A: A sessão mais as autorizações abertas de sessões
  anteriores.
- Q: Sobre que base é medida a meta de 80% de conciliação automática? → A: Sem meta fixa agora;
  medir nos primeiros meses e fixar depois.
- Q: Como tratar o pagamento um pouco diferente do autorizado por desconto obtido ou pequeno
  acréscimo? → A: Ao confirmar, o Operador pode encerrar a autorização com desconto ou registrar
  o excedente como acréscimo aceito, sempre com justificativa.
- Q: Há limite para o acréscimo aceito? → A: Sim, um teto percentual configurável sobre o valor
  autorizado; acima dele só vale "Pagamento a maior".
- Q: Quem pode encerrar com desconto ou aceitar acréscimo? → A: Operador e Administrador.
- Q: Como tratar a autorização no valor total paga em parcelas (R$ 1.000,00 em 10 × R$ 100,00)?
  → A: A autorização acumula os pagamentos; a condição de pagamento informada na planilha
  (`MAP_CONDICAO_DE_PAGAMENTO`) é usada como indício do parcelamento, sem rigidez, porque o que
  foi informado na autorização nem sempre é o que acontece na compra e no pagamento.
- Q: Quando o ERP divide o mesmo pagamento em várias linhas (mesma obrigação, códigos de operação
  diferentes), o motor compara a soma ou cada linha? → A: Cada linha é um pagamento próprio; a
  compra dividida aparece como Parcial e o Operador vincula as linhas uma a uma.
- Q: O que o Operador pode fazer com um pagamento "Sem autorização" que não é compra e cujo
  código não pode ser excluído? → A: Nenhuma ação nova neste módulo; o pagamento permanece na
  fila e a limpeza é feita pela lista de códigos excluídos (Módulo 5). O tratamento individual
  pode entrar no Módulo 4.
- Q: A forma de pagamento informada na autorização pode ser usada para desempatar candidatos? →
  A: Sim, só como desempate: entre candidatos empatados vence o que combina com a forma
  informada; se o empate continuar, fica Dúbio; sem empate, não muda nada.
- Q: Um pagamento com data anterior à data da autorização pode ser conciliado automaticamente? →
  A: Não. O par vai para o Operador como Dúbio, com o aviso "Pago antes da autorização", e pode
  ser confirmado normalmente. A tela tem uma aba específica para os pagamentos realizados antes
  da autorização.
- Q: O motor deve entrar em uso antes de existir o cadastro de códigos excluídos (Módulo 5)? → A:
  Não. O Módulo 5 é implementado antes do motor, que já nasce usando a lista.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Executar a conciliação e ver o que foi resolvido sozinho (Priority: P1)

Com as três planilhas de uma sessão carregadas (Módulo 1), o Operador aciona "Executar
Conciliação". O sistema compara os pagamentos das duas unidades com as autorizações da sessão e
com as autorizações ainda em aberto de sessões anteriores, atribui uma nota de compatibilidade de
0 a 100 a cada par possível e vincula sozinho os pares em que não há dúvida. Ao terminar, o
Operador vê um resumo: quantas autorizações foram conciliadas automaticamente e quantas ficaram
para análise, por tipo.

**Why this priority**: É o motivo de o produto existir: tirar do Operador a conferência linha a
linha. Sem o cruzamento automático, as planilhas carregadas não servem para nada.

**Independent Test**: Carregar uma sessão com autorizações e pagamentos preparados (pares
idênticos, pares com centavos de diferença, nomes com pequena variação e itens sem par), executar
e conferir que cada item caiu na classificação esperada e que o resumo bate com a contagem.

**Acceptance Scenarios**:

1. **Given** uma autorização de R$ 1.250,40 para "PADARIA PERNAMBUCANA LTDA" e um único pagamento
   de R$ 1.250,40 para "PADARIA PERNAMBUCANA", **When** a conciliação é executada, **Then** os
   dois são vinculados automaticamente, a autorização fica "Conciliada" e o par não aparece na
   tela de pendências.
2. **Given** tolerância de R$ 0,50, uma autorização de R$ 430,00 e um pagamento de R$ 430,30 para
   o mesmo fornecedor, **When** a conciliação é executada, **Then** o par é conciliado
   automaticamente e a diferença de R$ 0,30 fica registrada no vínculo.
3. **Given** uma autorização e um pagamento de mesmo valor cujos nomes de fornecedor são
   parecidos, mas não o bastante para a nota chegar a 90, **When** a conciliação é executada,
   **Then** o par é classificado como "Dúbio", com a nota exibida, e nada é vinculado.
4. **Given** uma autorização de R$ 3.000,00 e um pagamento de R$ 1.000,00 para o mesmo fornecedor,
   **When** a conciliação é executada, **Then** o par é classificado como "Parcial" e fica
   aguardando decisão do Operador.
5. **Given** uma autorização de R$ 500,00 e um pagamento de R$ 650,00 para o mesmo fornecedor,
   **When** a conciliação é executada, **Then** o par é classificado como "Excedente" e fica
   aguardando decisão do Operador.
6. **Given** uma autorização sem nenhum pagamento compatível, **When** a conciliação é executada,
   **Then** ela é classificada como "Sem pagamento".
7. **Given** um pagamento sem nenhuma autorização compatível, **When** a conciliação é executada,
   **Then** ele é classificado como "Sem autorização" e entra na fila de investigação.
8. **Given** duas autorizações idênticas e um único pagamento compatível com ambas, **When** a
   conciliação é executada, **Then** nenhum vínculo automático é feito e os pares são
   classificados como "Dúbios", para que o Operador escolha.
9. **Given** a execução em andamento, **When** o Operador sai para outra tela e volta, **Then** o
   andamento continua sendo exibido e o processamento não foi interrompido.
10. **Given** as mesmas planilhas e os mesmos parâmetros, **When** a conciliação é executada duas
    vezes (com reabertura entre elas), **Then** as notas e as classificações são idênticas.
11. **Given** um pagamento cujo código de operação está na lista de códigos excluídos (Módulo 5),
    **When** a conciliação é executada, **Then** ele não é comparado com nenhuma autorização e
    não aparece como "Sem autorização".
12. **Given** uma autorização de cartão de R$ 320,00 que ficou "Sem pagamento" na sessão de julho,
    já processada, e um pagamento de R$ 320,00 ao mesmo fornecedor na sessão de agosto, **When** a
    sessão de agosto é executada, **Then** os dois são vinculados automaticamente, a autorização
    de julho fica "Conciliada" e o vínculo mostra em que sessão o pagamento ocorreu.
13. **Given** uma autorização em aberto de uma sessão mais antiga que a janela de meses
    configurada, **When** uma sessão posterior é executada, **Then** ela não é comparada.
14. **Given** uma autorização em aberto de uma sessão anterior que ainda não foi processada,
    **When** uma sessão posterior é executada, **Then** ela não é comparada.
15. **Given** um pagamento compatível, com a mesma nota, com uma autorização da sessão e com uma
    autorização em aberto de sessão anterior, **When** a conciliação é executada, **Then** nenhum
    vínculo automático é feito e os dois pares ficam "Dúbios".
16. **Given** uma autorização com forma de pagamento "Cartão de crédito" e dois pagamentos
    empatados na maior nota, um vindo de fatura de cartão e outro não, **When** a conciliação é
    executada, **Then** o pagamento da fatura de cartão é o escolhido e o par é conciliado
    automaticamente.
17. **Given** uma autorização com forma de pagamento "Boleto" e um único pagamento compatível,
    vindo de fatura de cartão, **When** a conciliação é executada, **Then** o par é conciliado
    normalmente, porque não há empate.
18. **Given** uma autorização datada de 15/07 e um pagamento de 10/07 de mesmo valor e mesmo
    fornecedor, **When** a conciliação é executada, **Then** nenhum vínculo automático é feito e
    o par fica "Dúbio" com o aviso "Pago antes da autorização".
19. **Given** uma autorização e um pagamento de mesma data, **When** a conciliação é executada,
    **Then** a regra de data não se aplica e o par é conciliado normalmente.

---

### User Story 2 - Analisar as pendências lado a lado (Priority: P1)

Depois da execução, o Operador abre a tela de conciliação da sessão e vê apenas o que precisa de
decisão: de um lado a autorização, do outro o pagamento sugerido, com a nota, a diferença de valor
e o motivo da classificação. Ele filtra por tipo de pendência e procura pelo nome do fornecedor.

**Why this priority**: É onde o Operador passa o tempo. O ganho do produto depende de ele
conseguir entender cada pendência sem abrir as planilhas.

**Independent Test**: Com uma sessão processada que tenha itens de todas as classificações, abrir
a tela, conferir que os conciliados automáticos não aparecem na lista padrão, aplicar cada filtro
e buscar um fornecedor.

**Acceptance Scenarios**:

1. **Given** uma sessão processada, **When** o Operador abre a tela de conciliação, **Then** vê
   apenas os itens Dúbios, Parciais, Excedentes, Sem pagamento e Sem autorização, cada um com a
   autorização à esquerda e o pagamento à direita.
2. **Given** a lista de pendências, **When** o Operador escolhe o filtro "Dúbios", "Parciais",
   "Excedentes", "Sem pagamento" ou "Sem autorização", **Then** a lista mostra só aquele tipo, e
   o filtro "Todos" volta a mostrar tudo.
3. **Given** a lista de pendências, **When** o Operador digita parte do nome de um fornecedor,
   **Then** a lista mostra os itens em que a autorização ou o pagamento contém o texto.
4. **Given** um item Dúbio, Parcial ou Excedente, **When** ele é exibido, **Then** mostra a nota,
   a compatibilidade do fornecedor e a do valor em separado, e a diferença entre os dois valores.
5. **Given** um filtro e uma busca aplicados, **When** o Operador copia o endereço da tela e o
   abre em outra aba, **Then** vê a mesma lista filtrada.
6. **Given** uma sessão processada, **When** o Operador aplica o filtro "Conciliados", **Then** vê
   os pares vinculados, com a indicação de automático ou manual.
7. **Given** a tela de conciliação, **When** ela é exibida, **Then** mostra o total de itens por
   classificação e quantos ainda aguardam decisão.
8. **Given** uma sugestão que envolve uma autorização de sessão anterior, **When** ela é exibida,
   **Then** mostra o período da sessão de origem da autorização.
9. **Given** uma autorização de sessão anterior que não recebeu nenhuma sugestão nesta execução,
   **When** o Operador abre as pendências da sessão, **Then** ela não aparece como "Sem pagamento"
   nesta sessão; continua pendente na sessão de origem.
10. **Given** uma sessão processada, **When** o Operador abre a aba "Pagos antes da autorização",
    **Then** vê todos os pares em que a data do pagamento é anterior à da autorização, com as
    duas datas e a situação de cada um (aguardando decisão, confirmado ou rejeitado).
11. **Given** um par com o aviso "Pago antes da autorização" confirmado pelo Operador, **When**
    ele é consultado depois, **Then** o aviso continua visível e o par continua na aba.

---

### User Story 3 - Confirmar ou rejeitar uma sugestão do motor (Priority: P2)

Para cada par Dúbio, o Operador decide: confirma o vínculo, se os dois lançamentos são a mesma
compra, ou rejeita a sugestão, se não são. Um par rejeitado não volta a ser sugerido.

**Why this priority**: Resolve a maior parte das pendências que o motor não quis decidir sozinho.

**Independent Test**: Confirmar um Dúbio e verificar que a autorização fica "Conciliada", que o
par sai das pendências e que há registro de auditoria; rejeitar outro e verificar que autorização
e pagamento voltam a ficar sem par.

**Acceptance Scenarios**:

1. **Given** um par Dúbio, **When** o Operador confirma, **Then** os dois são vinculados como
   conciliação manual, o par sai da lista de pendências e a ação é registrada na auditoria com
   usuário, data, hora e a nota do motor.
2. **Given** um par Dúbio, **When** o Operador rejeita, **Then** nenhum vínculo é criado, a
   autorização passa a "Sem pagamento" e o pagamento a "Sem autorização" (ou ao próximo candidato,
   se houver), e a rejeição é registrada na auditoria.
3. **Given** um par rejeitado, **When** qualquer nova sugestão é calculada para a sessão,
   **Then** aquele par não é sugerido de novo.
4. **Given** dois Operadores com o mesmo par aberto, **When** os dois tentam decidir, **Then** só
   a primeira decisão vale e o segundo vê a situação atual do par.
5. **Given** vários pares Dúbios selecionados, **When** o Operador confirma em conjunto, **Then**
   cada par é vinculado e auditado individualmente.

---

### User Story 4 - Decidir pagamentos parciais e excedentes (Priority: P2)

Quando o pagamento é menor que o valor autorizado, o Operador confirma o vínculo e diz o que a
diferença significa: ainda falta pagar, e a autorização continua aberta mostrando o saldo, ou
houve desconto, e a autorização é encerrada. Quando o pagamento é maior, o Operador confirma e diz
se foi um acréscimo aceito (juros, multa, frete, reajuste), que quita a autorização sem alerta, ou
um pagamento a maior, que quita a autorização e deixa o par marcado em vermelho para auditoria.

**Why this priority**: São os casos em que há dinheiro em jogo: saldo ainda devido ou valor pago
além do autorizado.

**Independent Test**: Confirmar um Parcial como "ainda falta pagar" e conferir o saldo restante;
confirmar outro como "encerrar com desconto" e conferir que a autorização fica Conciliada com o
desconto registrado; confirmar um Excedente como "pagamento a maior" e conferir o alerta;
confirmar outro como "acréscimo aceito" e conferir que não há alerta.

**Acceptance Scenarios**:

1. **Given** uma autorização de R$ 3.000,00 e um pagamento de R$ 1.000,00 classificados como
   Parcial, **When** o Operador confirma escolhendo "Ainda falta pagar", **Then** o vínculo é
   criado, a autorização fica "Parcial" com saldo restante de R$ 2.000,00 e continua disponível
   para receber outros pagamentos.
2. **Given** uma autorização "Parcial" com saldo de R$ 2.000,00, **When** o Operador vincula a
   ela outro pagamento de R$ 2.000,00, **Then** o saldo zera e a autorização fica "Conciliada".
3. **Given** uma autorização de R$ 500,00 e um pagamento de R$ 650,00 classificados como
   Excedente, **When** o Operador confirma escolhendo "Pagamento a maior", **Then** a autorização
   é quitada e o par exibe um alerta vermelho "Pagamento a maior" com o valor excedido de
   R$ 150,00.
4. **Given** um par com alerta de pagamento a maior, **When** o Operador o consulta depois,
   **Then** o alerta continua visível e aparece no filtro de Excedentes confirmados.
5. **Given** um par Parcial ou Excedente, **When** o Operador rejeita, **Then** nenhum vínculo é
   criado e os dois lançamentos voltam a ficar sem par.
6. **Given** uma autorização com saldo restante dentro da tolerância, **When** o último pagamento
   é vinculado, **Then** a autorização fica "Conciliada".
7. **Given** uma autorização de R$ 1.000,00 e um pagamento de R$ 950,00 classificados como
   Parcial, **When** o Operador confirma escolhendo "Encerrar com desconto" e informa a
   justificativa, **Then** a autorização fica "Conciliada" com saldo zero, o vínculo registra
   desconto de R$ 50,00 e a ação é auditada com a justificativa.
8. **Given** uma autorização de R$ 1.000,00, teto de acréscimo de 10% e um pagamento de
   R$ 1.030,00 classificados como Excedente, **When** o Operador confirma escolhendo "Acréscimo
   aceito" e informa a justificativa, **Then** a autorização é quitada, o vínculo registra
   acréscimo de R$ 30,00 e nenhum alerta de pagamento a maior é exibido.
9. **Given** uma autorização de R$ 1.000,00, teto de acréscimo de 10% e um pagamento de
   R$ 1.200,00, **When** o Operador confirma o Excedente, **Then** a opção "Acréscimo aceito" não
   está disponível, com a indicação do teto, e só é possível "Pagamento a maior".
10. **Given** a opção "Encerrar com desconto" ou "Acréscimo aceito", **When** o Operador tenta
    confirmar sem justificativa, **Then** a ação é recusada.
11. **Given** uma autorização "Parcial" com saldo de R$ 40,00 depois de um ou mais pagamentos,
    **When** o Operador aciona "Encerrar com desconto" sobre o saldo e justifica, **Then** a
    autorização fica "Conciliada" e o desconto de R$ 40,00 fica registrado.
12. **Given** uma autorização encerrada com desconto, **When** o Operador desvincula o pagamento,
    **Then** o desconto é desfeito junto e a autorização volta a ficar em aberto pelo valor
    correspondente.
13. **Given** uma sessão processada, **When** o Operador vê os totais, **Then** aparecem a
    quantidade e a soma dos descontos, dos acréscimos aceitos e dos pagamentos a maior.

---

### User Story 5 - Vincular manualmente e desvincular (Priority: P2)

O Operador sabe que uma autorização corresponde a um pagamento que o motor não sugeriu. Ele
escolhe o pagamento na lista e cria o vínculo. Se um vínculo, automático ou manual, estiver
errado, ele desvincula e os dois lançamentos voltam a ficar pendentes.

**Why this priority**: É a saída para tudo o que o motor não acerta, e a correção para o que ele
erra.

**Independent Test**: Vincular manualmente uma autorização "Sem pagamento" a um pagamento "Sem
autorização"; desvincular um par conciliado automaticamente e conferir que os dois voltam às
pendências e que a auditoria guarda a nota original.

**Acceptance Scenarios**:

1. **Given** uma autorização "Sem pagamento", **When** o Operador aciona "Vincular" e escolhe um
   pagamento ainda sem vínculo da mesma sessão, **Then** o vínculo manual é criado, o tipo de
   diferença (exata, parcial ou excedente) é calculado e a ação é auditada.
2. **Given** a busca de pagamentos para vínculo manual, **When** o Operador procura, **Then**
   pode filtrar por fornecedor e por valor, e os candidatos vêm ordenados pela nota.
3. **Given** um par conciliado automaticamente, **When** o Operador aciona "Desvincular" e
   confirma, **Then** o vínculo é desfeito, os dois lançamentos voltam às pendências e a auditoria
   registra usuário, data, hora, a nota e a classificação originais do motor.
4. **Given** um par conciliado manualmente, **When** o Operador desvincula, **Then** o mesmo
   acontece, e a auditoria registra também quem havia feito o vínculo.
5. **Given** uma autorização com dois pagamentos vinculados, **When** o Operador desvincula um
   deles, **Then** o saldo e a situação da autorização são recalculados a partir do pagamento que
   restou.
6. **Given** um pagamento já vinculado, **When** qualquer usuário tenta vinculá-lo a outra
   autorização, **Then** a ação é recusada com a informação de onde ele está vinculado.
7. **Given** um par desvinculado, **When** novas sugestões são calculadas, **Then** aquele par
   não é vinculado automaticamente de novo.
8. **Given** um pagamento "Sem autorização" da sessão, **When** o Operador aciona "Vincular",
   **Then** pode escolher uma autorização da sessão ou uma autorização em aberto de sessão
   anterior processada, dentro da janela de meses.

---

### User Story 6 - Investigar pagamentos sem autorização (Priority: P3)

Os pagamentos sem autorização formam uma fila de investigação. Para um pagamento que de fato foi
uma compra feita sem autorização registrada, o Operador aciona "Criar autorização correspondente":
o sistema cria uma autorização com os dados do pagamento, marcada como criada na conciliação, e já
a vincula.

**Why this priority**: Regulariza o que foi pago sem autorização, mas é um trabalho de exceção,
feito depois de resolvidas as outras pendências.

**Independent Test**: Na fila de investigação, criar a autorização correspondente a um pagamento
e verificar que o pagamento sai da fila, que a autorização aparece marcada como criada na
conciliação e que a ação foi auditada.

**Acceptance Scenarios**:

1. **Given** uma sessão processada, **When** o Operador abre a fila de investigação, **Then** vê
   os pagamentos "Sem autorização" com unidade, fornecedor, valor, data, código e descrição da
   operação e forma de pagamento.
2. **Given** um pagamento na fila, **When** o Operador aciona "Criar autorização correspondente" e
   confirma, **Then** o sistema cria uma autorização com fornecedor, valor e data do pagamento,
   marcada como "Criada na conciliação", vincula os dois e registra na auditoria quem criou.
3. **Given** uma autorização criada na conciliação, **When** ela aparece em qualquer lista,
   **Then** é distinguível das autorizações que vieram da planilha.
4. **Given** uma autorização criada na conciliação, **When** o Operador desvincula o par,
   **Then** a autorização criada é desfeita e o pagamento volta à fila.
5. **Given** a fila de investigação, **When** o Operador filtra por unidade, por código de
   operação ou por forma de pagamento, **Then** a fila mostra só os pagamentos correspondentes.

---

### User Story 7 - Definir a margem de tolerância (Priority: P3)

O Administrador define a tolerância usada para considerar dois valores iguais: um valor fixo em
reais, um percentual, ou os dois. A tolerância vale para as próximas execuções; o que já foi
processado não muda.

**Why this priority**: O sistema funciona com um valor inicial. Ajustar a tolerância é ocasional.

**Independent Test**: Alterar a tolerância, executar uma sessão nova e conferir que ela usa o
valor novo, enquanto uma sessão já processada continua mostrando o valor com que foi executada.

**Acceptance Scenarios**:

1. **Given** um usuário com permissão de configurar a tolerância, **When** ele informa R$ 1,00 e
   salva, **Then** as próximas execuções tratam como iguais valores com até R$ 1,00 de diferença.
2. **Given** tolerância de R$ 0,50 e 1%, **When** uma autorização de R$ 1.000,00 é comparada com
   um pagamento de R$ 1.008,00, **Then** os valores são considerados compatíveis, porque a
   diferença cabe no percentual.
3. **Given** uma sessão processada com tolerância de R$ 0,50, **When** a tolerância passa a
   R$ 1,00, **Then** o resultado daquela sessão não muda, e os dados da execução continuam
   mostrando R$ 0,50.
4. **Given** uma alteração de tolerância, **When** ela é salva, **Then** a auditoria registra o
   usuário, a data e os valores anterior e novo.
5. **Given** um usuário sem a permissão, **When** ele tenta abrir a configuração, **Then** o
   acesso é negado.
6. **Given** a tela de configuração, **When** o usuário informa valor negativo ou percentual
   acima de 100, **Then** o valor é recusado com o motivo.

---

### User Story 8 - Acompanhar compras pagas em parcelas (Priority: P2)

Uma compra autorizada pelo valor total é paga em várias parcelas, em meses diferentes. O sistema
usa a condição de pagamento informada na autorização ("3x", "30/60/90 dias") como indício: quando
chega um pagamento do fornecedor no valor de uma parcela, ele é vinculado sozinho e a autorização
segue aberta com o saldo. Se a autorização dizia "à vista" e a compra acabou parcelada, o
Operador confirma a primeira parcela e as seguintes passam a ser vinculadas sozinhas. Se dizia
"3x" e foi paga de uma vez, concilia normalmente. A condição informada ajuda, mas nunca impede.

**Why this priority**: Sem isso, cada parcela exige uma confirmação manual, todo mês, e as
parcelas de compras antigas deixam de ser sugeridas.

**Independent Test**: Processar três sessões seguidas com uma autorização "3x" de R$ 900,00 e um
pagamento de R$ 300,00 em cada uma, e conferir que as três parcelas são vinculadas sozinhas e a
autorização termina Conciliada. Repetir com uma autorização "A vista" paga em três parcelas e
conferir que só a primeira exige confirmação.

**Acceptance Scenarios**:

1. **Given** uma autorização de R$ 900,00 com condição "3x" e um pagamento de R$ 300,00 ao mesmo
   fornecedor, **When** a conciliação é executada, **Then** o pagamento é vinculado
   automaticamente como parcela, a autorização fica "Parcial" com saldo de R$ 600,00 e o par não
   aparece nas pendências.
2. **Given** a mesma autorização com saldo de R$ 600,00, **When** as sessões dos dois meses
   seguintes são executadas, cada uma com um pagamento de R$ 300,00, **Then** cada pagamento é
   vinculado automaticamente e, no último, a autorização fica "Conciliada".
3. **Given** uma autorização de R$ 1.000,00 com condição "A vista" e um pagamento de R$ 100,00,
   **When** a conciliação é executada, **Then** o par aparece como "Parcial", para decisão do
   Operador.
4. **Given** o par do cenário 3 confirmado como "Ainda falta pagar", **When** uma sessão seguinte
   traz outro pagamento de R$ 100,00 ao mesmo fornecedor, **Then** ele é vinculado
   automaticamente como parcela.
5. **Given** uma autorização de R$ 900,00 com condição "3x" e um único pagamento de R$ 900,00,
   **When** a conciliação é executada, **Then** o par é conciliado automaticamente pelo valor
   total, sem aviso de erro.
6. **Given** uma autorização de R$ 900,00 com condição "3x" e um pagamento de R$ 450,00, **When**
   a conciliação é executada, **Then** o par aparece como "Parcial", mostrando que o valor não
   corresponde à parcela prevista.
7. **Given** uma autorização parcelada com pagamentos vinculados e saldo em aberto, de uma sessão
   mais antiga que a janela de meses, **When** uma sessão posterior é executada, **Then** ela
   continua sendo comparada.
8. **Given** duas autorizações em aberto do mesmo fornecedor para as quais um mesmo pagamento
   serve como parcela, **When** a conciliação é executada, **Then** nenhum vínculo automático é
   feito e o Operador escolhe.
9. **Given** uma autorização com condição "3x" de R$ 1.000,00, **When** chegam parcelas de
   R$ 333,33, R$ 333,33 e R$ 333,34, **Then** as três são vinculadas e a autorização fica
   "Conciliada".
10. **Given** uma autorização parcelada, **When** ela é exibida, **Then** mostra a condição como
    foi informada, as parcelas previstas, quantos pagamentos já foram vinculados, o valor pago e
    o saldo.
11. **Given** uma condição de pagamento que o sistema não reconhece (por exemplo "488,02"),
    **When** a conciliação é executada, **Then** a autorização é tratada como se não houvesse
    indício, sem erro.
12. **Given** uma parcela vinculada automaticamente, **When** o Operador a desvincula, **Then** o
    saldo é recalculado e aquele par não volta a ser vinculado sozinho.

---

### Edge Cases

- **Vários pagamentos compatíveis com a mesma autorização**: se um único candidato tem a maior
  nota, é ele o sugerido; se dois ou mais empatam na maior nota, nenhum vínculo automático é feito
  e o item fica Dúbio com todos os candidatos empatados.
- **Empate desfeito pela forma de pagamento**: o desempate vale nos dois sentidos, entre
  pagamentos para uma autorização e entre autorizações para um pagamento (FR-012a).
- **Pagamento anterior à autorização**: nunca é vinculado sozinho; vai para o Operador com o aviso
  e aparece na aba própria. Vale também para o vínculo automático de parcela.
- **Um pagamento compatível com várias autorizações**: vale a mesma regra; um pagamento nunca é
  vinculado automaticamente a mais de uma autorização.
- **Nota exatamente no limite**: nota 90 concilia automaticamente; nota 60 é Dúbio; abaixo de 60
  não gera sugestão.
- **Valor compatível, fornecedor totalmente diferente**: a nota não chega a 60; não há sugestão.
- **Fornecedor igual, valor muito diferente**: é Parcial ou Excedente somente quando a
  compatibilidade do fornecedor é alta; caso contrário, não há sugestão.
- **Nomes com razão social abreviada, sufixos (LTDA, ME, EIRELI, S/A), acentos, pontuação ou
  CNPJ no início** ("60.499.628 LILIAN KARLA GUILHERME SANTOS"): essas diferenças não reduzem a
  compatibilidade do fornecedor.
- **Pagamento de valor zero ou negativo**: não chega ao motor, porque não é importado (Módulo 1).
- **Sessão com muitos pagamentos e poucas autorizações** (caso real: cerca de 3.600 pagamentos
  para 136 autorizações): a fila de investigação é paginada e filtrável, e os pagamentos de
  códigos excluídos não entram nela.
- **Falha durante a execução**: nenhum vínculo da execução permanece; a sessão volta a "Aberta"
  (Módulo 1) e o Operador é informado.
- **Nova execução após reabertura**: o resultado automático anterior é descartado antes de o novo
  ser produzido. A reabertura só é possível depois de desfeitas as decisões manuais (Módulo 1).
- **Duas ações simultâneas sobre a mesma autorização** (por exemplo, dois Operadores vinculando
  pagamentos diferentes): as ações são aplicadas uma de cada vez, e a segunda é avaliada contra o
  saldo já atualizado.
- **Pagamento um pouco menor por desconto** (autorizado R$ 1.000,00, pago R$ 950,00): fora da
  tolerância, nunca é conciliado sozinho; aparece como Parcial e o Operador o encerra com
  desconto.
- **Pagamento um pouco maior por juros, multa ou reajuste**: aparece como Excedente e o Operador
  o registra como acréscimo aceito, se couber no teto.
- **Acréscimo exatamente no teto**: é aceito; acima do teto, não.
- **Teto de acréscimo alterado depois da decisão**: as decisões já tomadas não mudam.
- **Desconto em autorização com vários pagamentos**: o desconto é sempre o saldo restante no
  momento do encerramento, nunca um valor digitado.
- **Condição informada diferente do que aconteceu** (autorizado "à vista", pago em parcelas, ou o
  contrário): não é erro e não reduz a nota; o caso segue as regras gerais de exato, Parcial e
  Excedente.
- **Duas parcelas da mesma autorização na mesma sessão**: as duas são vinculadas, desde que
  caibam no saldo; pagamentos iguais para a mesma autorização não contam como empate.
- **Parcela maior que o saldo restante**: não é vinculada sozinha; aparece como Excedente.
- **Mais pagamentos do que parcelas previstas**: o que conta é o saldo, não a quantidade
  prevista.
- **Parcelamento interrompido** (sobram parcelas que nunca chegam): a autorização segue "Parcial";
  o Operador pode encerrá-la com desconto ou tratá-la no painel de pendências (Módulo 4).
- **Pagamento "Sem autorização" que não é compra**: permanece na fila de investigação. Não há
  ação para dispensá-lo individualmente neste módulo; a fila é reduzida pela lista de códigos
  excluídos (Módulo 5), que vale a partir da execução seguinte.
- **Desvincular um pagamento de uma autorização já quitada por excedente**: a autorização volta a
  ficar sem pagamento e o alerta de pagamento a maior deixa de existir.
- **Lista de códigos excluídos alterada depois da execução**: o resultado da sessão não muda
  (Módulo 5).
- **Arquivo de autorizações substituído em sessão reaberta**: a nova execução usa apenas os
  lançamentos do arquivo ativo.
- **Mesmo lançamento presente em sessão de outro período**: a cópia da sessão posterior não é
  comparada e fica marcada como duplicada, com a indicação da sessão em que o lançamento já
  existe (FR-031c).
- **Sessões executadas fora de ordem** (agosto antes de julho): agosto só enxerga as sessões
  anteriores já processadas. As autorizações de julho não são comparadas com os pagamentos de
  agosto enquanto agosto não for reaberta e executada de novo; o vínculo manual continua possível.
- **Reabrir uma sessão cujas autorizações receberam pagamentos de sessões posteriores**: a
  reabertura é recusada, com a lista das sessões posteriores envolvidas; é preciso reabrir ou
  desvincular nelas primeiro (FR-031e).
- **Reabrir a sessão posterior**: os vínculos automáticos que ela fez com autorizações de sessões
  anteriores são descartados, e essas autorizações voltam a ficar em aberto na sessão de origem.
- **Autorização de sessão anterior com saldo parcial**: é comparada pelo saldo restante, não pelo
  valor autorizado original.

## Requirements *(mandatory)*

### Functional Requirements

**Execução**

- **FR-001**: O sistema DEVE executar a conciliação de uma sessão a partir dos lançamentos ativos
  das três planilhas carregadas, quando solicitado pelo Operador.
- **FR-002**: O sistema DEVE executar a conciliação em segundo plano, exibindo o andamento e
  permitindo que o Operador navegue por outras telas.
- **FR-003**: O sistema DEVE comparar os pagamentos das duas unidades (Social e Saúde) da sessão
  com as autorizações da sessão e com as autorizações em aberto de sessões anteriores (FR-031).
- **FR-004**: O sistema DEVE deixar fora da comparação os pagamentos cujo código de operação
  esteja na lista de códigos excluídos no momento da execução, mantendo-os guardados e marcados.
- **FR-005**: O sistema DEVE produzir o mesmo resultado sempre que os lançamentos e os parâmetros
  forem os mesmos.
- **FR-006**: O sistema DEVE guardar, em cada execução, os parâmetros usados: tolerância, limites
  de nota, janela de meses anteriores, quem solicitou e quando.
- **FR-007**: Em caso de falha, o sistema NÃO DEVE manter nenhum vínculo produzido pela execução.
- **FR-008**: Ao executar de novo uma sessão reaberta, o sistema DEVE descartar o resultado
  anterior antes de produzir o novo.

**Nota de compatibilidade e classificação**

- **FR-009**: O sistema DEVE atribuir a cada par autorização-pagamento candidato uma nota de 0 a
  100, calculada a partir de dois eixos: compatibilidade do fornecedor e compatibilidade do
  valor.
- **FR-010**: A compatibilidade do fornecedor DEVE ser medida pela semelhança entre os nomes,
  desconsiderando maiúsculas, acentos, pontuação, espaços repetidos, sufixos societários e
  números de documento no nome.
- **FR-011**: A compatibilidade do valor DEVE ser máxima quando a diferença entre o valor
  autorizado em aberto e o valor pago couber na tolerância, e diminuir à medida que a diferença
  aumenta.
- **FR-012**: O sistema DEVE conciliar automaticamente um par somente quando a nota for igual ou
  superior a 90, a diferença de valor couber na tolerância e não houver outro candidato com a
  mesma nota para a autorização ou para o pagamento. A única outra forma de vínculo automático é
  a de parcela (FR-044 e FR-045).
- **FR-012a**: Havendo empate na maior nota, o sistema DEVE desempatar pela forma de pagamento
  informada na autorização: autorização de cartão de crédito combina com pagamento vindo de
  fatura de cartão; as demais formas combinam com os outros pagamentos. Se exatamente um dos
  empatados combinar, ele é o escolhido; caso contrário, o empate permanece. A forma de pagamento
  NÃO DEVE alterar a nota nem excluir candidatos quando não há empate.
- **FR-012b**: O sistema NÃO DEVE vincular automaticamente, nem como exato nem como parcela, um
  pagamento com data anterior à data da autorização. O par DEVE ser classificado como "Dúbio"
  (ou Parcial ou Excedente, conforme o valor) e marcado com o aviso "Pago antes da autorização".
  O aviso permanece no par depois da confirmação. Mesma data não é anterior; sem data em um dos
  lados, a regra não se aplica.
- **FR-013**: O sistema DEVE classificar como "Dúbio" o par com nota de 60 a 89 e diferença de
  valor dentro da tolerância, e também os pares empatados que impediram a conciliação automática.
- **FR-014**: O sistema DEVE classificar como "Parcial" o par com compatibilidade de fornecedor
  alta em que o valor pago é menor que o saldo da autorização além da tolerância.
- **FR-015**: O sistema DEVE classificar como "Excedente" o par com compatibilidade de fornecedor
  alta em que o valor pago é maior que o saldo da autorização além da tolerância.
- **FR-016**: O sistema DEVE classificar como "Sem pagamento" a autorização sem nenhum candidato
  com nota igual ou superior a 60, e como "Sem autorização" o pagamento na mesma condição.
- **FR-017**: Os limites de nota (90 e 60) e o limite de compatibilidade de fornecedor usado em
  Parcial e Excedente DEVEM ser parâmetros do sistema, não valores fixos.
- **FR-018**: Um pagamento DEVE estar vinculado a no máximo uma autorização. Uma autorização PODE
  ter mais de um pagamento vinculado.
- **FR-019**: O saldo e a situação de uma autorização (Aberta, Parcial, Conciliada) DEVEM ser
  sempre o resultado dos pagamentos vinculados a ela e dos descontos registrados nesses
  vínculos, e nunca editados diretamente.

**Tela de conciliação**

- **FR-020**: O sistema DEVE exibir as pendências de uma sessão em lista comparativa, com a
  autorização de um lado e o pagamento do outro, mostrando nota, compatibilidade de cada eixo e
  diferença de valor.
- **FR-021**: A lista padrão DEVE mostrar apenas Dúbios, Parciais, Excedentes, Sem pagamento e Sem
  autorização. Os conciliados DEVEM poder ser consultados por filtro.
- **FR-022**: O sistema DEVE oferecer filtros rápidos por classificação e busca por nome de
  fornecedor, mantendo filtro e busca no endereço da tela.
- **FR-023**: O sistema DEVE exibir, por sessão, o total de itens em cada classificação, o
  percentual de autorizações da sessão conciliadas automaticamente, quantas autorizações de
  sessões anteriores foram conciliadas por pagamentos desta sessão, a quantidade de pares pagos
  antes da autorização, a quantidade e a soma dos descontos, dos acréscimos aceitos e dos
  pagamentos a maior, e quantos itens aguardam decisão.
- **FR-023a**: A tela DEVE ter uma aba própria "Pagos antes da autorização", com todos os pares
  marcados com esse aviso, pendentes ou já decididos, inclusive os vinculados manualmente,
  mostrando as duas datas e a situação. Os pares pendentes dessa aba também aparecem na lista
  padrão, na sua classificação.
- **FR-024**: A lista DEVE ser paginada.
- **FR-024a**: Ao exibir um pagamento cuja obrigação tem outras linhas na mesma sessão, o sistema
  DEVE indicar quantas são e permitir vê-las, para que o Operador vincule as demais à mesma
  autorização. O motor NÃO soma as linhas.

**Decisões do Operador**

- **FR-025**: O sistema DEVE permitir confirmar um par Dúbio, Parcial ou Excedente, criando o
  vínculo como conciliação manual.
- **FR-026**: O sistema DEVE permitir rejeitar uma sugestão, e NÃO DEVE sugerir nem vincular
  automaticamente de novo um par rejeitado ou desvinculado na mesma sessão.
- **FR-027**: Ao confirmar um Parcial, o Operador DEVE escolher entre "Ainda falta pagar", que
  mantém a autorização "Parcial" com o saldo restante exibido, e "Encerrar com desconto", que
  deixa a autorização "Conciliada" com saldo zero e registra a diferença como desconto.
- **FR-028**: Ao confirmar um Excedente, o Operador DEVE escolher entre "Pagamento a maior", que
  quita a autorização e marca o par com um alerta permanente com o valor excedido, e "Acréscimo
  aceito", que quita a autorização e registra a diferença como acréscimo, sem alerta.
  - **FR-028a**: "Encerrar com desconto" e "Acréscimo aceito" DEVEM exigir justificativa em texto.
  - **FR-028b**: "Acréscimo aceito" só DEVE estar disponível quando o valor excedido não
    ultrapassar um teto percentual sobre o valor autorizado. O teto DEVE ser um parâmetro do
    sistema, com valor inicial de 10%, alterável por quem configura a tolerância.
  - **FR-028c**: O sistema DEVE permitir encerrar com desconto o saldo restante de uma
    autorização "Parcial" que já tenha ao menos um pagamento vinculado. O desconto é sempre o
    saldo restante, e não um valor informado.
  - **FR-028d**: As mesmas escolhas DEVEM ser oferecidas no vínculo manual (FR-029) quando o tipo
    de diferença for parcial ou excedente.
  - **FR-028e**: Desvincular o pagamento DEVE desfazer o desconto ou o acréscimo registrado
    naquele vínculo.
  - **FR-028f**: Diferenças fora da tolerância NÃO DEVEM ser encerradas automaticamente pelo
    motor em nenhum caso.
- **FR-029**: O sistema DEVE permitir vincular manualmente um pagamento sem vínculo da sessão a
  uma autorização da sessão ou a uma autorização em aberto de sessão anterior dentro da janela
  (FR-031), calculando o tipo de diferença.
- **FR-030**: O sistema DEVE permitir desvincular qualquer par conciliado, devolvendo os dois
  lançamentos às pendências e recalculando o saldo da autorização.
- **FR-031**: Ao executar uma sessão, o sistema DEVE incluir na comparação as autorizações com
  saldo em aberto (Aberta ou Parcial) de sessões de períodos anteriores que já estejam
  processadas, dentro de uma janela de meses anteriores.
  - **FR-031a**: A janela DEVE ser um parâmetro do sistema, com valor inicial de 3 meses.
  - **FR-031b**: As autorizações de sessões anteriores DEVEM seguir as mesmas regras de nota,
    classificação e empate das autorizações da sessão, e ser comparadas pelo saldo restante.
  - **FR-031c**: Um lançamento com o mesmo identificador de um lançamento de sessão de outro
    período NÃO DEVE ser comparado de novo; a cópia da sessão posterior fica guardada e marcada
    como duplicada, com a indicação da sessão de origem.
  - **FR-031d**: Uma autorização de sessão anterior só DEVE aparecer nas pendências da sessão em
    execução quando participa de uma sugestão ou de um vínculo com pagamento dessa sessão. O
    vínculo DEVE mostrar a sessão da autorização e a sessão do pagamento.
  - **FR-031e**: O sistema DEVE recusar a reabertura de uma sessão enquanto alguma de suas
    autorizações estiver vinculada a pagamento de sessão posterior, informando quais sessões.
  - **FR-031f**: Ao executar de novo uma sessão reaberta, o descarte do resultado anterior
    (FR-008) DEVE incluir os vínculos automáticos feitos com autorizações de sessões anteriores,
    recalculando o saldo dessas autorizações.
- **FR-032**: O sistema DEVE permitir criar, a partir de um pagamento sem autorização, uma
  autorização correspondente marcada como criada na conciliação, já vinculada ao pagamento.
- **FR-033**: O sistema DEVE aplicar uma de cada vez as ações que alteram a mesma autorização ou o
  mesmo pagamento, e recusar a ação baseada em situação desatualizada, mostrando a situação atual.

**Parcelamento**

- **FR-043**: O sistema DEVE interpretar a condição de pagamento informada na autorização para
  obter o número de parcelas previstas: "Nx" indica N parcelas; uma lista de prazos separados por
  barra ("30/60/90 dias") indica uma parcela por prazo; "à vista" e um prazo único indicam uma
  parcela. Texto não reconhecido significa "sem indício" e NÃO DEVE gerar erro.
- **FR-044**: Quando a autorização tem mais de uma parcela prevista, o sistema DEVE vincular
  automaticamente, como parcela, o pagamento cujo valor seja igual ao valor autorizado dividido
  pelo número de parcelas, dentro da tolerância, desde que a compatibilidade do fornecedor
  atinja o limite da conciliação automática, o valor caiba no saldo e o pagamento não sirva a
  outra autorização.
- **FR-045**: Depois que um pagamento é vinculado a uma autorização como "Ainda falta pagar", o
  sistema DEVE vincular automaticamente os pagamentos seguintes do mesmo fornecedor cujo valor
  seja igual ao de um pagamento já vinculado, dentro da tolerância, nas mesmas condições de
  FR-044, qualquer que seja a condição informada.
- **FR-046**: A condição de pagamento DEVE servir apenas como indício. Ela NÃO DEVE reduzir a
  nota, impedir a conciliação pelo valor total nem impedir a classificação como Parcial ou
  Excedente. A divergência entre o previsto e o ocorrido é exibida, e não é tratada como erro.
- **FR-047**: A autorização com saldo em aberto e ao menos um pagamento vinculado DEVE continuar
  na comparação das sessões seguintes, mesmo fora da janela de meses (FR-031a).
- **FR-048**: O sistema DEVE exibir, para cada autorização, a condição de pagamento como foi
  informada, as parcelas previstas (quando reconhecidas), a quantidade de pagamentos vinculados,
  o valor pago e o saldo.
- **FR-049**: Vários pagamentos de parcela para a mesma autorização NÃO contam como empate entre
  si. Quando mais de um couber, o sistema DEVE vinculá-los em ordem de data de pagamento, até o
  limite do saldo.
- **FR-050**: O vínculo automático de parcela DEVE ficar identificado como tal, seguir as regras
  de desvínculo e de descarte em nova execução, e NÃO DEVE ser refeito sozinho depois de
  desvinculado pelo Operador.

**Tolerância**

- **FR-034**: O sistema DEVE permitir configurar uma tolerância global em valor fixo, em
  percentual, ou em ambos. Com ambos, dois valores são compatíveis quando a diferença cabe em
  qualquer um dos dois.
- **FR-035**: A tolerância inicial DEVE ser de R$ 0,50, sem percentual.
- **FR-036**: A alteração da tolerância DEVE valer apenas para execuções iniciadas depois dela.
- **FR-037**: Somente usuários com a permissão de configurar a tolerância DEVEM poder alterá-la.

**Auditoria e acesso**

- **FR-038**: O sistema DEVE registrar na trilha de auditoria, na mesma operação da mudança: cada
  confirmação (com o tratamento da diferença e a justificativa, quando houver), rejeição, vínculo
  manual, desvínculo, criação de autorização na conciliação e alteração de tolerância ou do teto
  de acréscimo, com usuário, data e hora, ação, item afetado e dados antes e depois.
- **FR-039**: O registro de desvínculo DEVE conter a nota e a classificação originais do motor e,
  quando o vínculo era manual, quem o havia feito.
- **FR-040**: Os vínculos automáticos NÃO precisam de registro individual na auditoria; a execução
  que os produziu fica registrada com seus parâmetros e totais.
- **FR-041**: Operadores e Administradores DEVEM poder executar a conciliação e tomar todas as
  decisões sobre pendências, inclusive encerrar com desconto e aceitar acréscimo, em qualquer
  sessão e para as duas unidades.
- **FR-042**: O sistema DEVE informar ao Módulo 1 quantas decisões manuais existem em uma sessão
  e quantas de suas autorizações estão vinculadas a pagamentos de sessões posteriores (FR-031e),
  para que a reabertura seja bloqueada enquanto houver alguma.

### Key Entities

- **Execução da Conciliação**: uma rodada do motor sobre uma sessão. Tem quem solicitou, início e
  fim, tolerância e limites de nota usados, códigos excluídos vigentes e os totais por
  classificação.
- **Autorização**: lançamento importado da planilha de autorizações (Módulo 1) ou criado na
  conciliação. Para a conciliação, tem valor autorizado, saldo restante e situação (Aberta,
  Parcial, Conciliada), sempre derivados dos vínculos. Traz a condição de pagamento informada e
  as parcelas previstas que o sistema reconheceu nela.
- **Pagamento**: lançamento importado das planilhas de pagamentos (Módulo 1), com unidade,
  fornecedor, valor pago, data e código de operação. Para a conciliação, está sem vínculo,
  vinculado ou excluído por código.
- **Sugestão**: par autorização-pagamento avaliado pelo motor, com nota, compatibilidade do
  fornecedor, compatibilidade do valor, diferença de valor e classificação (Dúbio, Parcial,
  Excedente). Pode ser confirmada ou rejeitada.
- **Vínculo de Conciliação**: ligação entre uma autorização e um pagamento. Tem origem (automática
  ou manual), nota e classificação do motor, tipo de diferença (exata, parcial, excedente), valor
  da diferença, tratamento da diferença (ainda falta pagar, desconto, acréscimo aceito, pagamento
  a maior), justificativa, quem vinculou e quando, o alerta de pagamento a maior e o aviso de
  pago antes da autorização, se houver.
- **Configuração do Teto de Acréscimo**: percentual vigente, com quem alterou e quando.
- **Configuração de Tolerância**: valor fixo e percentual vigentes, com quem alterou e quando.
- **Registro de Auditoria**: descrito no Módulo 1; recebe as ações deste módulo.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Em 100% das sessões processadas, o percentual de autorizações conciliadas
  automaticamente é exibido e fica guardado com a execução. Não há meta fixa neste módulo: a meta
  de automação é definida pelo responsável depois de três sessões mensais processadas com dados
  reais.
- **SC-002**: Uma sessão com 10.000 lançamentos, somadas as autorizações em aberto da janela de
  meses anteriores, é conciliada em menos de 2 minutos.
- **SC-003**: 100% dos desvínculos, confirmações, rejeições e vínculos manuais têm registro na
  trilha de auditoria com usuário, data e hora.
- **SC-004**: Executar duas vezes a mesma sessão, com os mesmos parâmetros, produz 100% das notas
  e classificações iguais.
- **SC-005**: Nenhum par é conciliado automaticamente com diferença de valor acima da tolerância
  (0 ocorrências).
- **SC-006**: Nenhum pagamento fica vinculado a mais de uma autorização (0 ocorrências).
- **SC-007**: Em 100% das autorizações, o saldo exibido é igual ao valor autorizado menos a soma
  dos pagamentos vinculados e dos descontos registrados, nunca abaixo de zero.
- **SC-008**: O Operador encontra e decide uma pendência (confirmar, rejeitar ou vincular) em
  menos de 30 segundos, sem abrir as planilhas.
- **SC-009**: Para qualquer sessão processada, é possível saber com que tolerância e com quais
  limites de nota ela foi executada.
- **SC-010**: 100% dos descontos e acréscimos aceitos têm justificativa e registro na trilha de
  auditoria, e nenhum acréscimo aceito ultrapassa o teto vigente na data da decisão.
- **SC-011**: Em uma compra paga em N parcelas iguais com condição de pagamento reconhecida, o
  Operador não faz nenhuma confirmação; com condição não reconhecida ou diferente do ocorrido,
  faz no máximo uma.
- **SC-012**: Nenhum vínculo automático de parcela deixa a soma dos pagamentos acima do valor
  autorizado além da tolerância (0 ocorrências).

## Assumptions

- **Importação já entregue**: o envio, a validação e a prevenção de duplicidade dentro do mesmo
  período são do Módulo 1. As histórias "Upload com mapeamento inteligente" e "Tratamento de
  duplicidades no upload" da descrição original não fazem parte deste módulo: o mapeamento de
  colunas pelo Operador foi adiado para uma feature própria, e a duplicidade no mesmo período já
  está resolvida.
- **Sessão no lugar de lote**: a descrição original fala em "lote de importação" com as situações
  Processando, Aguardando Revisão e Finalizado. O sistema trabalha com a sessão do Módulo 1
  (Aberta, Em processamento, Processada). "Aguardando revisão" corresponde a uma sessão
  Processada com pendências; não há uma situação "Finalizada", e a sessão mostra quantas
  pendências restam.
- **Limites de nota**: a descrição original diz "acima de 90" e "60 a 89", deixando sem definição
  a nota 90 e as notas abaixo de 60. Assume-se: 90 ou mais concilia; 60 a 89 é Dúbio; abaixo de 60
  não há sugestão.
- **Conciliação automática exige valor dentro da tolerância**: uma nota alta obtida só pelo nome do
  fornecedor não basta para vincular sozinho valores diferentes.
- **Peso dos eixos**: assume-se que a nota de um par com valor dentro da tolerância é determinada
  pela compatibilidade do fornecedor, de modo que o valor decide se o par é candidato a exato,
  parcial ou excedente, e o fornecedor decide a confiança. A fórmula exata fica para o
  planejamento, respeitando FR-009 a FR-017.
- **Autorizações sem unidade**: a planilha de autorizações não indica a unidade, então cada
  autorização é comparada com os pagamentos das duas unidades.
- **Pagamento dividido em várias linhas**: no relatório do ERP, uma mesma obrigação pode aparecer
  em várias linhas, com códigos de operação diferentes (119 obrigações em julho/2026). Assume-se
  que cada linha é um pagamento próprio para o motor (confirmado pelo responsável). Se o valor
  autorizado corresponder à soma das linhas, o caso aparece como Parcial e é resolvido pelo
  Operador vinculando as linhas. Nessas 119 obrigações, as linhas têm o mesmo fornecedor e a
  mesma data.
- **Critérios do cruzamento**: fornecedor e valor. A data, a forma de pagamento e o cartão não
  entram na nota neste módulo; são exibidos para ajudar a decisão. A data só impede o vínculo
  automático quando o pagamento é anterior à autorização (FR-012b). A forma de pagamento serve
  apenas de desempate (FR-012a). Assume-se que a espécie do documento no relatório do ERP
  ("FATURA CARTAO ...") identifica os pagamentos de fatura de cartão.
- **Dependência do Módulo 5**: o cadastro e a importação de códigos excluídos (Módulo 5) são
  implementados antes deste módulo, e o motor já nasce usando a lista. A fila de investigação só
  é utilizável com ela preenchida: em julho/2026 há cerca de 3.600 pagamentos para 136
  autorizações, e a maior parte é folha, impostos e outras operações que não são compras.
- **Pendências de sessões anteriores**: nos arquivos de julho/2026, só 4 das 56 autorizações de
  cartão de crédito têm pagamento de cartão de mesmo valor no próprio mês; a fatura chega no mês
  seguinte. Por isso o motor olha para trás. A janela inicial de 3 meses é uma suposição, a
  ajustar com o uso. Só entram sessões anteriores já processadas.
- **Meta de automação**: a meta original de 80% foi retirada por falta de base. Em julho/2026,
  cerca de 30% das autorizações têm pagamento compatível no mesmo mês.
- **Duplicidade entre períodos**: assume-se que o mesmo identificador em sessões de períodos
  diferentes é o mesmo lançamento enviado duas vezes, e não um lançamento novo.
- **Desconto e acréscimo**: a tolerância cobre só diferenças de centavos e continua sendo o único
  critério da conciliação automática. Descontos e acréscimos são decisão do Operador. O teto
  inicial de 10% para acréscimo é uma suposição. O desconto não tem teto, porque pagar menos não
  traz o risco que o alerta de pagamento a maior protege. Encerrar com desconto exige ao menos um
  pagamento vinculado; dar baixa em autorização sem nenhum pagamento é do Módulo 4.
- **Condição de pagamento como indício**: a coluna `MAP_CONDICAO_DE_PAGAMENTO` é texto livre. Em
  julho/2026 os valores são "A vista" (77), "1x" (38), "30 dias" (9), "10 DIAS" (8), "2x" (1),
  "30/60 dias" (1), "30/60/90 dias" (1) e um valor digitado por engano ("488,02"). Só 3 das 136
  autorizações indicam parcelamento. Assume-se que as parcelas previstas têm valores iguais; os
  prazos em dias não são usados para prever datas neste módulo.
- **Criar autorização correspondente**: cria uma autorização com os dados do pagamento, marcada
  como criada na conciliação. Ela regulariza o registro no sistema e não substitui o processo de
  autorização de compra da organização.
- **Permissões**: executar e decidir pendências cabe a Operador e Administrador (constituição,
  Princípio VIII). Configurar a tolerância é uma permissão própria, do Administrador por padrão.
- **Fora do escopo**: o histórico consolidado de parcelas, a previsão de vencimentos e os
  parcelamentos com parcelas de valores diferentes (Módulo 3); o painel de pendências acumuladas
  entre sessões, o vínculo com autorizações fora da janela de meses, a baixa com justificativa e a
  dispensa individual de pagamentos sem autorização (Módulo 4); a manutenção da lista de códigos
  excluídos (Módulo 5); relatórios e a tela da trilha de auditoria (Módulo 6).
