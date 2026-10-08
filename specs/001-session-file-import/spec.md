# Feature Specification: Gestão de Sessões e Importação de Arquivos (Módulo 1)

**Feature Branch**: `feat/mod1-gestao-sessoes`
**Created**: 2026-10-08
**Status**: Draft
**Input**: User description: "Módulo 1 - Gestão de Sessões e Importação de Arquivos. O Operador cria sessões de conciliação vinculadas a um período (mês/ano) e importa as três planilhas obrigatórias (Autorizações, Pagamentos Social, Pagamentos Saúde). O sistema valida profundamente cada upload, controla o estado da sessão (bloqueio/reabertura) e mantém trilha de auditoria."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Criar sessão e carregar as três planilhas (Priority: P1)

O Operador abre uma nova sessão de conciliação para um mês/ano de referência e vê um painel com
três cartões, um para cada planilha obrigatória: Autorizações, Pagamentos da Unidade Social e
Pagamentos da Unidade de Saúde. Em cada cartão ele envia a planilha correspondente. O sistema
confere o arquivo por inteiro e, se estiver correto, guarda uma cópia exata do original e marca o
cartão como "Carregado".

**Why this priority**: Sem sessão e sem planilhas válidas não existe o que conciliar. Todo o
restante do produto depende desta entrada de dados.

**Independent Test**: Criar uma sessão para "05/2026", enviar três planilhas válidas e verificar
que os três cartões ficam "Carregado", que os arquivos originais podem ser baixados idênticos ao
que foi enviado e que a ação "Executar Conciliação" fica disponível.

**Acceptance Scenarios**:

1. **Given** que o Operador está no painel principal, **When** ele aciona "Nova Sessão" e informa
   o período "05/2026", **Then** o sistema cria a sessão com situação "Aberta", registra quem a
   criou e quando, e exibe três cartões com status "Pendente".
2. **Given** uma sessão recém-criada, **When** o painel é exibido, **Then** a ação "Executar
   Conciliação" está desabilitada e o sistema informa quais planilhas ainda faltam.
3. **Given** um cartão "Pendente", **When** o Operador envia uma planilha válida, **Then** o
   sistema valida tipo de arquivo, tamanho, cabeçalhos e todas as linhas, guarda a cópia exata do
   original e muda o cartão para "Carregado", mostrando nome do arquivo, quantidade de
   lançamentos, quem enviou e quando.
4. **Given** uma planilha válida com 100 linhas, das quais 3 têm valor negativo ou zero, **When**
   é enviada, **Then** o arquivo é aceito, 97 lançamentos são importados e o cartão informa "3
   linhas ignoradas por valor negativo ou zero".
5. **Given** uma planilha válida com os valores "1.234,56", "1,234.56" e "1234.56" em linhas
   diferentes, **When** é enviada, **Then** os três lançamentos são importados com o mesmo valor
   de mil duzentos e trinta e quatro reais e cinquenta e seis centavos.
6. **Given** que já existe uma sessão de "05/2026" com 200 pagamentos da Unidade Social, **When**
   o Operador cria outra sessão de "05/2026", confirma o aviso de sessão complementar e envia uma
   planilha de pagamentos da Unidade Social com os mesmos 200 lançamentos e 15 novos, **Then** 15
   lançamentos são importados e o cartão informa "200 linhas já existentes em outra sessão do
   período".
7. **Given** dois cartões "Carregado" e um "Pendente", **When** o Operador tenta executar a
   conciliação, **Then** a ação continua bloqueada e o cartão faltante é apontado.
8. **Given** um cartão "Carregado" em sessão "Aberta", **When** o Operador envia outra planilha
   válida para o mesmo cartão, **Then** a nova planilha substitui a anterior e o cartão exibe os
   dados do novo arquivo.

---

### User Story 2 - Recusar planilha inválida com relatório de erros (Priority: P2)

Quando a planilha enviada tem problema de estrutura ou de conteúdo (coluna obrigatória ausente,
texto em campo de valor, data inválida, arquivo só com cabeçalho), o sistema recusa o arquivo
inteiro, mostra quantos erros encontrou e os primeiros casos, e oferece o relatório completo para
download, indicando linha e coluna de cada erro.

**Why this priority**: É o que impede dado ruim de chegar à conciliação. Sem isso o Operador só
descobre o problema depois, com resultado errado na mão.

**Independent Test**: Enviar uma planilha com erros conhecidos em linhas específicas e conferir
que o cartão não muda de status, que o resumo mostra a contagem correta e que o relatório baixado
lista cada erro com linha, coluna e motivo.

**Acceptance Scenarios**:

1. **Given** um cartão "Pendente", **When** o Operador envia uma planilha com letras em um campo
   de valor na linha 45, **Then** o arquivo é recusado por inteiro, o cartão permanece "Pendente"
   e nenhuma linha da planilha fica registrada.
2. **Given** um upload recusado, **When** o resumo é exibido, **Then** ele mostra o total de erros
   e os 10 primeiros, cada um com linha, coluna, valor encontrado e motivo.
3. **Given** um upload recusado, **When** o Operador aciona o download do relatório, **Then**
   recebe um arquivo de planilha com todos os erros, cada um com linha, coluna, valor encontrado e
   motivo.
4. **Given** um cartão "Carregado", **When** o Operador envia uma planilha inválida para
   substituí-lo, **Then** o arquivo é recusado e o cartão continua "Carregado" com a planilha
   anterior intacta.
5. **Given** uma planilha que contém apenas a linha de cabeçalho, **When** é enviada, **Then** é
   recusada com o aviso de que não há lançamentos.
6. **Given** um arquivo de tipo não aceito ou acima do tamanho máximo, **When** é enviado,
   **Then** é recusado com mensagem que informa os tipos aceitos ou o limite de tamanho.

---

### User Story 3 - Aceitar planilha com datas fora do período mediante confirmação (Priority: P2)

Uma planilha estruturalmente correta pode trazer lançamentos com data fora do mês/ano da sessão
(por exemplo, despesa autorizada no fim do mês e paga no mês seguinte). O sistema avisa o Operador,
mostra quantos lançamentos estão fora do período e só aceita o arquivo se ele confirmar.

**Why this priority**: Lançamentos fora do período são comuns e legítimos, mas também são o
sintoma de quem enviou a planilha do mês errado. A confirmação separa os dois casos.

**Independent Test**: Enviar uma planilha válida de junho em uma sessão de maio, verificar o
alerta, cancelar (cartão não muda), reenviar e confirmar (cartão "Carregado" com marca de
divergência).

**Acceptance Scenarios**:

1. **Given** uma planilha válida com lançamentos datados fora do período da sessão, **When** é
   enviada, **Then** o sistema exibe um alerta com a quantidade de lançamentos fora do período e
   o intervalo de datas encontrado, e o cartão ainda não passa a "Carregado".
2. **Given** o alerta de divergência, **When** o Operador aciona "Confirmar mesmo assim", **Then**
   o arquivo é aceito, o cartão passa a "Carregado" com um indicador visível de divergência de
   período e a confirmação fica registrada na trilha de auditoria com usuário, data e hora.
3. **Given** o alerta de divergência, **When** o Operador cancela ou sai da tela, **Then** o
   arquivo é descartado e o cartão volta ao estado em que estava antes do envio.

---

### User Story 4 - Executar a conciliação e travar a sessão (Priority: P2)

Com os três cartões "Carregado", o Operador solicita a execução da conciliação. A partir da
solicitação, a sessão fica travada: não aceita novos envios, substituições nem exclusão. O
Operador acompanha o andamento e pode navegar para outras telas enquanto o processamento ocorre.

**Why this priority**: O travamento garante que o resultado da conciliação corresponda exatamente
aos arquivos que estão na sessão.

**Independent Test**: Em uma sessão com três cartões "Carregado", solicitar a execução e verificar
que a sessão passa a "Em processamento" e depois "Processada", e que toda tentativa de envio,
substituição ou exclusão é recusada.

**Acceptance Scenarios**:

1. **Given** os três cartões "Carregado", **When** o Operador aciona "Executar Conciliação",
   **Then** a sessão passa a "Em processamento", os cartões ficam travados e o andamento é
   exibido.
2. **Given** uma sessão "Em processamento", **When** o processamento termina com sucesso, **Then**
   a sessão passa a "Processada" e continua travada.
3. **Given** uma sessão "Em processamento" ou "Processada", **When** qualquer usuário tenta enviar
   ou substituir uma planilha, **Then** a ação é recusada com a informação de que a sessão está
   travada.
4. **Given** uma sessão "Em processamento", **When** o processamento falha, **Then** a sessão
   volta a "Aberta" sem resultado parcial, os três arquivos permanecem "Carregado" e o Operador é
   informado da falha.
5. **Given** uma sessão "Em processamento", **When** o Operador aciona "Executar Conciliação"
   novamente (inclusive em outra aba), **Then** nenhuma segunda execução é iniciada.

---

### User Story 5 - Reabrir sessão processada para substituir arquivo (Priority: P3)

Depois de processada, o Operador percebe que uma das planilhas estava errada. Ele reabre a sessão,
substitui o arquivo e executa a conciliação de novo. O resultado anterior deixa de valer.

**Why this priority**: Corrige um erro de entrada sem obrigar a criar outra sessão, mas acontece
com menos frequência que o fluxo principal.

**Independent Test**: Reabrir uma sessão "Processada", substituir uma planilha, executar de novo e
verificar que a trilha de auditoria registra a reabertura e a substituição, e que o arquivo
substituído continua disponível para consulta.

**Acceptance Scenarios**:

1. **Given** uma sessão "Processada", **When** o Operador aciona "Reabrir Sessão" e confirma,
   **Then** a sessão volta a "Aberta", os cartões são destravados e a reabertura é registrada na
   trilha de auditoria com usuário, data, hora e situação anterior e nova.
2. **Given** uma sessão reaberta, **When** o painel é exibido, **Then** o sistema informa que o
   resultado anterior não é mais válido e que a conciliação precisa ser executada novamente.
3. **Given** uma sessão reaberta, **When** o Operador substitui uma planilha, **Then** o novo
   arquivo passa pelas mesmas validações, a substituição é registrada na trilha de auditoria e o
   arquivo anterior permanece guardado como evidência, marcado como substituído.
4. **Given** uma sessão reaberta com os três cartões "Carregado", **When** a conciliação é
   executada novamente, **Then** o resultado anterior da sessão é descartado antes de o novo ser
   produzido.
5. **Given** uma sessão "Processada" cujo resultado tem decisões manuais de operadores
   (confirmações, vínculos manuais ou baixas) ou vínculos com lançamentos de outras sessões,
   **When** o Operador aciona "Reabrir Sessão", **Then** a reabertura é recusada, a sessão
   continua "Processada" e o sistema informa quantas decisões manuais precisam ser desfeitas antes
   de reabrir.
6. **Given** uma sessão "Processada" cujas decisões manuais foram todas desfeitas, **When** o
   Operador aciona "Reabrir Sessão" e confirma, **Then** a sessão é reaberta normalmente.

---

### User Story 6 - Excluir sessão criada por engano (Priority: P3)

O Operador criou uma sessão no período errado ou desistiu dela antes de conciliar. Ele exclui a
sessão, que some da lista junto com os arquivos enviados. O Administrador consegue ver, na trilha
de auditoria, quem excluiu qual sessão e quando.

**Why this priority**: Mantém a lista de sessões limpa. É uma correção ocasional e não bloqueia o
fluxo principal.

**Independent Test**: Criar uma sessão, enviar uma planilha, excluir a sessão e verificar que ela
e o arquivo deixam de existir, que o registro de auditoria permanece e que uma sessão já
processada não oferece exclusão.

**Acceptance Scenarios**:

1. **Given** uma sessão que nunca foi processada, **When** o Operador aciona "Excluir" e confirma,
   **Then** a sessão, seus arquivos e as linhas importadas são apagados definitivamente e a sessão
   deixa de aparecer para todos os usuários.
2. **Given** a exclusão concluída, **When** o Administrador consulta a trilha de auditoria,
   **Then** encontra um registro com o período da sessão, o usuário que excluiu, data e hora e os
   nomes dos arquivos removidos.
3. **Given** uma sessão que já foi processada alguma vez (esteja "Processada" ou reaberta),
   **When** qualquer usuário tenta excluí-la, **Then** a exclusão é recusada com a explicação de
   que sessões já conciliadas são mantidas como evidência de auditoria.
4. **Given** uma sessão "Em processamento", **When** qualquer usuário tenta excluí-la, **Then** a
   exclusão é recusada.

---

### Edge Cases

- **Arquivo acima do limite**: o envio é recusado antes da validação de conteúdo, com mensagem que
  informa o limite (50 MB por padrão), e o cartão não muda.
- **Queda de conexão durante o envio**: nada é guardado, nenhuma linha é registrada e o cartão
  permanece no estado anterior ("Pendente" ou "Carregado" com o arquivo antigo).
- **Planilha só com cabeçalho ou totalmente vazia**: recusada com aviso de ausência de
  lançamentos.
- **Sessão excluída em outra aba**: qualquer ação posterior sobre ela (envio, execução, reabertura)
  retorna "Sessão não encontrada" e leva o usuário de volta à lista de sessões.
- **Sessão travada em outra aba**: se a conciliação foi solicitada na Aba A, um envio na Aba B é
  recusado, porque a situação da sessão é conferida no momento de cada ação.
- **Coluna opcional do layout ausente** (por exemplo, DS_AUDIT ou MAP_APROVADOR): o arquivo é
  aceito e o cartão lista as colunas não encontradas.
- **Confirmação de divergência pendente de outro operador**: abrir o painel não a cancela; ela só
  é cancelada por quem enviou, por um novo envio ao mesmo cartão ou pelo fim do prazo.
- **Processamento interrompido** (o serviço de fila parou no meio): depois do tempo máximo, a
  sessão volta a "Aberta" e o envio em validação é encerrado, sem dado parcial.
- **Dois operadores enviando para o mesmo cartão ao mesmo tempo**: apenas um envio é aceito por
  vez; o segundo é validado contra o estado resultante e nunca gera dois arquivos ativos no mesmo
  cartão.
- **Planilha no cartão errado** (por exemplo, Pagamentos enviada em Autorizações): recusada pela
  validação de cabeçalhos, com indicação das colunas esperadas e ausentes.
- **Planilha de pagamentos da unidade errada** (Social enviada no cartão de Saúde ou o inverso):
  como o layout é o mesmo, o sistema não consegue detectar a troca pelas colunas. O cartão exibe o
  nome do arquivo e a quantidade de lançamentos para conferência, e enviar o mesmo arquivo nos
  dois cartões de pagamento da sessão é recusado.
- **Cabeçalho com diferença de maiúsculas, acentos ou espaços nas pontas**: é reconhecido como o
  cabeçalho esperado; qualquer outra diferença de nome conta como coluna ausente.
- **Reabertura enquanto outro operador registra uma decisão manual na mesma sessão**: a
  verificação é feita no momento da reabertura; se a decisão foi registrada antes, a reabertura é
  recusada.
- **Mesmo arquivo enviado duas vezes no mesmo cartão**: o resultado é um único arquivo ativo e um
  único conjunto de linhas, sem duplicação.
- **Período já existente**: criar sessão para um mês/ano que já possui sessão é permitido após
  confirmação; a nova sessão é complementar e só recebe lançamentos que ainda não existem nas
  outras sessões do período.
- **Mesmas planilhas enviadas em uma sessão complementar**: todas as linhas já existem, então cada
  cartão fica "Carregado" com zero lançamentos novos e nada é contado em dobro.
- **Arquivo substituído em uma sessão quando o período tem outras sessões**: a comparação é
  refeita no momento do novo envio, contra os lançamentos ativos das outras sessões do período.
- **Sessão do mesmo período excluída**: seus lançamentos deixam de existir e não contam mais na
  comparação de envios posteriores.
- **Período inválido ou futuro**: mês fora de 01 a 12 é recusado; período posterior ao mês
  corrente é recusado.
- **Planilha com várias abas**: apenas a primeira aba é lida, e o resumo do cartão informa isso.
- **Valor "1.234" ou "1,234"**: lido como mil duzentos e trinta e quatro reais, porque um único
  separador seguido de três dígitos é separador de milhar. "1.23" e "1,23" são lidos como um real
  e vinte e três centavos.
- **Valores em formatos diferentes na mesma planilha**: aceitos; cada valor é interpretado pela
  mesma regra, linha a linha.
- **Data em formato não aceito** (por exemplo, 2026-05-31 ou 05/31/2026): erro de validação na
  linha e coluna correspondentes; o arquivo é recusado.
- **Pagamento com emissão ou vencimento em outro mês**: é normal no relatório do ERP e não gera
  alerta; só a data do pagamento é comparada com o período.
- **Obrigação quitada só por desconto** (valor pago zero): a linha é ignorada e contada entre as
  linhas ignoradas por valor.
- **Obrigação liquidada em parte**: é importada pelo valor pago naquela liquidação, não pelo valor
  total da obrigação.
- **Mesma obrigação em mais de um código de operação**: cada linha é um lançamento próprio.
- **Valor pago diferente do valor da obrigação** (juros ou desconto): não é erro; vale o valor
  pago.
- **Linhas idênticas na mesma planilha**: cada uma vira um lançamento próprio, identificado pela
  sua posição (linha) no arquivo.
- **Linha com valor negativo ou zero**: é pulada sem erro e somada ao contador de linhas
  ignoradas do cartão. Um valor não numérico continua sendo erro e recusa o arquivo.
- **Planilha em que todas as linhas têm valor negativo ou zero**: recusada com aviso de ausência
  de lançamentos válidos.
- **Linhas totalmente em branco no meio ou no fim da planilha**: são ignoradas e não contam como
  erro nem como lançamento.

## Requirements *(mandatory)*

### Functional Requirements

**Sessões**

- **FR-001**: O sistema DEVE permitir que usuários autenticados criem uma sessão de conciliação
  informando um período de referência no formato mês/ano (MM/AAAA).
- **FR-002**: O sistema DEVE registrar, em cada sessão, quem a criou, quando, e atribuir um número
  sequencial único que a identifica nas demais telas.
- **FR-003**: O sistema DEVE recusar a criação de sessão para períodos inválidos ou posteriores ao
  mês corrente.
- **FR-003a**: O sistema DEVE permitir mais de uma sessão para o mesmo período. Ao criar uma
  sessão em período que já possui outra, o sistema DEVE avisar que ela será uma sessão
  complementar e pedir confirmação.
- **FR-003b**: Ao aceitar uma planilha, o sistema DEVE deixar de importar as linhas que já existem
  como lançamentos ativos do mesmo tipo de planilha em outras sessões do mesmo período. Um
  pagamento já existe quando a unidade e o identificador do lançamento (FR-011g) coincidem. Uma
  autorização já existe quando todos os campos obrigatórios do seu layout coincidem. A comparação
  respeita a quantidade: se as outras sessões têm duas linhas iguais e o arquivo traz três, uma é
  importada.
- **FR-003c**: O sistema DEVE informar, no resumo do cartão e na confirmação do envio, quantas
  linhas deixaram de ser importadas por já existirem em outra sessão do período, separadamente
  das linhas ignoradas por valor.
- **FR-003d**: Uma planilha com linhas válidas em que todas já existem em outras sessões do
  período DEVE ser aceita com zero lançamentos novos, e o cartão passa a "Carregado" exibindo essa
  informação.
- **FR-004**: O sistema DEVE manter cada sessão em exatamente uma situação: "Aberta", "Em
  processamento" ou "Processada".
- **FR-005**: O sistema DEVE listar as sessões com período, número, situação, criador, data de
  criação e o status de cada um dos três cartões, permitindo busca por período e filtro por
  situação.

**Painel e envio de planilhas**

- **FR-006**: O sistema DEVE apresentar cada sessão como um painel com três cartões independentes:
  Autorizações, Pagamentos da Unidade Social e Pagamentos da Unidade de Saúde.
- **FR-007**: Cada cartão DEVE exibir seu status ("Pendente" ou "Carregado") e, quando carregado,
  o nome do arquivo, a quantidade de lançamentos, a quantidade de linhas ignoradas, a quantidade de
  linhas já existentes em outras sessões do período, quem enviou, quando, o indicador de
  divergência de período, se houver, e as colunas do layout não encontradas no arquivo, se
  houver.
- **FR-008**: O sistema DEVE aceitar planilhas nos formatos .xlsx e .csv e recusar qualquer outro
  tipo de arquivo.
- **FR-009**: O sistema DEVE impor um tamanho máximo por arquivo, ajustável por configuração do
  sistema, com valor inicial de 50 MB.
- **FR-010**: O sistema DEVE validar, a cada envio e antes de registrar qualquer lançamento: tipo
  do arquivo, tamanho, presença das colunas obrigatórias do tipo de planilha e o conteúdo de todas
  as linhas (valores monetários numéricos, datas válidas, campos obrigatórios preenchidos).
- **FR-010a**: O sistema DEVE pular, sem tratar como erro, as linhas cujo valor seja negativo ou
  zero, em qualquer planilha. Em pagamentos, o valor considerado é o valor pago (VL_RECEBIDO).
  Essas linhas não viram lançamentos, não entram na conciliação e não aparecem no relatório de
  erros. O valor é avaliado antes dos demais campos: uma linha de valor negativo ou zero é pulada
  mesmo que outro campo dela esteja vazio ou inválido. Um valor ilegível continua sendo erro.
- **FR-010b**: O sistema DEVE informar, no resumo do cartão e na confirmação do envio, quantas
  linhas foram ignoradas por valor negativo ou zero, separadamente da quantidade de lançamentos
  importados.
- **FR-010c**: O sistema DEVE aceitar valores monetários escritos no formato brasileiro (1.234,56)
  ou no internacional (1,234.56 ou 1234.56), com ou sem o símbolo "R$", e interpretá-los assim:
  quando há ponto e vírgula, o separador que aparece por último é o decimal; quando há um único
  separador seguido de um ou dois dígitos, ele é o decimal; quando há um único separador seguido
  de exatamente três dígitos, ele é separador de milhar; sem separador, o valor é em reais
  inteiros. Um valor sem parte inteira (".8") é lido como centavos (oitenta centavos). Valores com
  mais de duas casas decimais ou que não se encaixem nessas regras são erro.
- **FR-010d**: O sistema DEVE aceitar datas em dois formatos: o do relatório do ERP, com dia, mês
  abreviado em três letras em inglês e ano com dois dígitos (31-JUL-26, 15-MAY-26), e o brasileiro
  dia/mês/ano (31/05/2026). O ano com dois dígitos pertence ao século atual. Qualquer outro formato
  de texto, ou uma data inexistente, é erro.
- **FR-010e**: Células que a planilha já guarda como número ou como data DEVEM ser lidas pelo seu
  valor, sem depender do formato de exibição.
- **FR-010f**: O sistema DEVE importar como lançamentos separados as linhas idênticas de uma mesma
  planilha (mesmo fornecedor, valor e data), sem aviso e sem tratá-las como erro ou duplicidade.
- **FR-011**: O sistema DEVE exigir um layout fixo de cabeçalhos e recusar o arquivo cujas colunas
  obrigatórias estejam ausentes, informando quais faltam. Existem dois layouts: um de Autorizações
  e um de Pagamentos, este último usado igualmente pelos cartões da Unidade Social e da Unidade de
  Saúde. Colunas adicionais não previstas no layout são ignoradas, e a ordem das colunas não
  importa.
- **FR-011a**: O sistema DEVE oferecer, em cada cartão, o download de uma planilha modelo com os
  cabeçalhos esperados para aquele layout e uma linha de exemplo.
- **FR-011b**: O sistema DEVE atribuir a unidade (Social ou Saúde) de cada lançamento de pagamento
  pelo cartão em que o arquivo foi enviado, e não por uma coluna da planilha.
- **FR-011c**: O layout de Pagamentos DEVE ser o do relatório de liquidação do ERP, com estes
  cabeçalhos, escritos exatamente assim: TIPO, COD_OPERACAO, NM_OPERACAO,
  SUM_VL_OBRIGACAO_POR_COD_OPERACAO, CD_COMANDO, DT_LIQUIDACAO, CEDENTE, ESPECIE, OBRIGACAO,
  CD_TIPO_TRANSACAO, DOC_GERADOR, DT_EMISSAO, DT_VENCIMENTO, PREV_LIQUIIDACAO,
  VL_SALDO_OBRIGACAO, VL_RECEBIDO, VL_OBRIGACAO, VL_DESCONTO_CONCEDIDO, VL_JUROS_MORA, SITUACAO,
  CD_MOVIMENTO_CONTA, CD_CONTA_CORRENTE, NM_CONTA_CORRENTE, DS_AUDIT.
- **FR-011d**: No layout de Pagamentos, o sistema DEVE ler: o fornecedor em CEDENTE; o valor pago
  em VL_RECEBIDO; a data do pagamento em DT_LIQUIDACAO; o código de operação em COD_OPERACAO e sua
  descrição em NM_OPERACAO; a forma de pagamento em ESPECIE e CD_TIPO_TRANSACAO; o número da
  obrigação em OBRIGACAO; o documento de origem em DOC_GERADOR; e a situação em SITUACAO. As
  demais colunas são guardadas com o lançamento, sem uso neste módulo.
- **FR-011e**: Em cada linha de pagamento, COD_OPERACAO, DT_LIQUIDACAO, CEDENTE, OBRIGACAO,
  VL_RECEBIDO e VL_OBRIGACAO DEVEM estar preenchidos; a falta de qualquer um é erro. As demais
  colunas podem vir vazias. Espaços no início e no fim de cada campo são desconsiderados.
- **FR-011f**: O sistema DEVE aceitar o arquivo .csv como o ERP o exporta: campos separados por
  ponto e vírgula e acentuação na codificação do ERP, exibindo os textos acentuados corretamente
  depois de importados.
- **FR-011g**: Um lançamento de pagamento DEVE ser identificado pelo conjunto número da obrigação
  (OBRIGACAO), código de operação (COD_OPERACAO) e movimento de conta (CD_MOVIMENTO_CONTA).
- **FR-011h**: O layout de Autorizações DEVE ser o da planilha exportada pelo sistema ELO, com
  estes cabeçalhos: MAP_SOLICITACAO, MAP_SETOR_SOLICITANTE, MAP_FUNCIONARIO_SOLICITANTE,
  MAP_DATA_AUTORIZACAO, MAP_APROVADOR, MAP_FORNECEDOR, VALOR, MAP_FORMA_DE_PAGAMENTO,
  MAP_AFRAFEP_CARTAO, MAP_CONDICAO_DE_PAGAMENTO, MAP_VALOR.
- **FR-011i**: No layout de Autorizações, o sistema DEVE ler: a solicitação (descrição e número)
  em MAP_SOLICITACAO; o fornecedor em MAP_FORNECEDOR; o valor em VALOR; a data em
  MAP_DATA_AUTORIZACAO; a forma de pagamento em MAP_FORMA_DE_PAGAMENTO; e o cartão em
  MAP_AFRAFEP_CARTAO. Esses quatro primeiros DEVEM estar preenchidos em toda linha; a falta de
  qualquer um é erro. As demais colunas são guardadas com o lançamento e podem vir vazias. A data
  comparada com o período da sessão é a data da autorização.
- **FR-011j**: Ao ler um valor, o sistema DEVE desconsiderar o símbolo de moeda e os espaços que o
  acompanham ("R$ 3.895,73" é lido como 3.895,73).
- **FR-011k**: O sistema DEVE exigir no arquivo apenas os cabeçalhos das colunas obrigatórias por
  linha (FR-011e para Pagamentos, FR-011i para Autorizações) e recusá-lo quando algum deles
  faltar. As demais colunas do layout são lidas quando presentes; quando ausentes, o arquivo é
  aceito, os campos correspondentes ficam vazios e o cartão informa quais colunas do layout não
  foram encontradas.
- **FR-012**: O sistema DEVE tratar cada envio como tudo ou nada: ou o arquivo inteiro é aceito, ou
  nenhuma de suas linhas é registrada.
- **FR-013**: O sistema DEVE recusar planilhas sem nenhum lançamento, inclusive aquelas em que
  todas as linhas foram ignoradas por valor negativo ou zero. A exceção é a planilha cujas linhas
  já existem em outras sessões do período (FR-003d).
- **FR-014**: Para cada arquivo aceito, o sistema DEVE guardar uma cópia exata do original,
  acessível apenas a usuários autenticados, e permitir seu download.
- **FR-015**: O sistema DEVE permitir substituir a planilha de um cartão enquanto a sessão estiver
  "Aberta"; a planilha anterior só deixa de valer quando a nova é aceita.
- **FR-016**: Enviar o mesmo arquivo mais de uma vez para o mesmo cartão NÃO DEVE duplicar
  lançamentos: o cartão fica com um único arquivo ativo e com a quantidade de lançamentos que o
  arquivo contém, incluídas as linhas idênticas entre si.
- **FR-017**: Um envio interrompido ou recusado NÃO DEVE alterar o estado do cartão nem deixar
  arquivo ou lançamento parcialmente registrado.

**Relatório de erros**

- **FR-018**: Ao recusar uma planilha, o sistema DEVE exibir o total de erros e os 10 primeiros,
  cada um com linha, coluna, valor encontrado e motivo.
- **FR-019**: O sistema DEVE disponibilizar para download o relatório completo de erros da
  planilha recusada, em formato de planilha, com linha, coluna, valor encontrado e motivo.

**Divergência de período**

- **FR-020**: O sistema DEVE identificar lançamentos com data fora do mês/ano da sessão em
  planilhas que passaram na validação e informar a quantidade e o intervalo de datas encontrado.
- **FR-020a**: Em pagamentos, a data comparada com o período da sessão DEVE ser a data do
  pagamento (DT_LIQUIDACAO). As datas de emissão e de vencimento não geram alerta de divergência.
- **FR-021**: O sistema DEVE aceitar uma planilha com divergência de período somente após
  confirmação explícita do usuário, e DEVE descartá-la se a confirmação não ocorrer.
- **FR-022**: O sistema DEVE manter visível no cartão que o arquivo foi aceito com divergência de
  período.

**Execução, travamento e reabertura**

- **FR-023**: O sistema DEVE manter a ação "Executar Conciliação" indisponível enquanto algum dos
  três cartões não estiver "Carregado", indicando quais faltam.
- **FR-024**: Ao receber a solicitação de execução, o sistema DEVE passar a sessão para "Em
  processamento" e recusar envios, substituições e exclusão a partir desse momento.
- **FR-025**: O sistema DEVE impedir que mais de uma execução da mesma sessão ocorra ao mesmo
  tempo.
- **FR-026**: O sistema DEVE exibir o andamento do processamento e permitir que o usuário navegue
  para outras telas sem interrompê-lo.
- **FR-027**: Ao término bem-sucedido, a sessão DEVE passar a "Processada"; em caso de falha, DEVE
  voltar a "Aberta" sem resultado parcial e com os arquivos preservados.
- **FR-027a**: Uma sessão que permaneça "Em processamento" além do tempo máximo configurado, ou
  um envio que permaneça em validação além do seu tempo máximo, DEVE ser tratado como falha: a
  sessão volta a "Aberta" sem resultado parcial e o envio é encerrado sem alterar o cartão.
- **FR-028**: O sistema DEVE permitir reabrir uma sessão "Processada", mediante confirmação,
  devolvendo-a à situação "Aberta" e destravando os cartões.
- **FR-028a**: O sistema DEVE recusar a reabertura enquanto o resultado da sessão tiver decisões
  manuais (confirmações, vínculos manuais ou baixas) ou vínculos com lançamentos de outras
  sessões, informando a quantidade de decisões que precisam ser desfeitas. Vínculos feitos
  automaticamente pelo sistema dentro da própria sessão não impedem a reabertura.
- **FR-029**: Após a reabertura, o sistema DEVE sinalizar que o resultado anterior não é mais
  válido, e DEVE descartar esse resultado quando a conciliação for executada novamente.
- **FR-030**: Ao substituir uma planilha em uma sessão que já foi processada, o sistema DEVE
  manter o arquivo anterior e seus lançamentos como evidência, marcados como substituídos e fora
  de novas conciliações.
- **FR-031**: O sistema DEVE conferir a situação atual da sessão antes de cada ação e recusar a
  ação se a sessão tiver sido travada, reaberta ou excluída desde que a tela foi carregada.

**Exclusão**

- **FR-032**: O sistema DEVE permitir excluir definitivamente uma sessão, com seus arquivos e
  lançamentos, somente se ela nunca tiver sido processada, e sempre mediante confirmação.
- **FR-033**: O sistema DEVE recusar a exclusão de sessões que já foram processadas alguma vez ou
  que estão "Em processamento", explicando o motivo.
- **FR-034**: A exclusão DEVE liberar o espaço ocupado pelos arquivos da sessão.

**Auditoria e acesso**

- **FR-035**: O sistema DEVE registrar na trilha de auditoria: criação de sessão, confirmação de
  divergência de período, substituição de planilha, solicitação de execução, reabertura e
  exclusão, cada registro com usuário, data e hora, tipo de ação, sessão afetada e os dados antes
  e depois da mudança.
- **FR-036**: O registro de auditoria de uma sessão excluída DEVE permanecer após a exclusão e
  identificar o período, o número da sessão, o usuário e os nomes dos arquivos removidos.
- **FR-037**: Os registros de auditoria NÃO DEVEM poder ser alterados ou apagados por nenhum
  usuário.
- **FR-038**: Operadores e Administradores DEVEM poder criar sessões, enviar e substituir
  planilhas, executar a conciliação, reabrir e excluir sessões, independentemente de quem criou a
  sessão e para ambas as unidades.
- **FR-039**: O Administrador DEVE poder consultar, a qualquer momento, o histórico de exclusões e
  reaberturas de sessões.

### Key Entities

- **Sessão de Conciliação**: um ciclo de conciliação de um mês/ano, identificado pelo número e
  não pelo período, que pode se repetir em sessões complementares. Tem número sequencial,
  período, situação (Aberta, Em processamento, Processada), criador, data de criação e a
  indicação de se já foi processada alguma vez. Possui até três arquivos ativos, um por tipo.
- **Arquivo de Importação**: uma planilha aceita em um cartão da sessão. Tem tipo (Autorizações,
  Pagamentos Social, Pagamentos Saúde), layout (Autorizações ou Pagamentos), nome original,
  tamanho, quantidade de lançamentos, quantidade de linhas ignoradas, quantidade de linhas já
  existentes em outras sessões do período, colunas do layout não encontradas, quem enviou e
  quando, indicação de divergência de período e de quem a confirmou, a cópia exata do original e
  a marca de ativo ou substituído.
- **Lançamento Importado**: uma linha de uma planilha aceita, guardada como veio no arquivo e
  ligada ao arquivo de origem e à posição (linha) na planilha. Em pagamentos, tem um
  identificador formado por número da obrigação, código de operação e movimento de conta, além de
  unidade, fornecedor, valor pago, data do pagamento, código de operação e forma de pagamento. É o
  insumo dos módulos de conciliação.
- **Relatório de Erros de Validação**: resultado de um envio recusado. Lista cada erro com linha,
  coluna, valor encontrado e motivo. Existe para consulta e download logo após a recusa.
- **Registro de Auditoria**: evento imutável com usuário, data e hora, tipo de ação, sessão
  afetada e dados antes e depois. Sobrevive à exclusão da sessão a que se refere.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% das planilhas com erro de estrutura ou de conteúdo são recusadas antes de a
  execução da conciliação ficar disponível.
- **SC-002**: Em 100% dos envios recusados, o Operador localiza cada erro por linha e coluna usando
  apenas o relatório de validação, sem ajuda de suporte.
- **SC-003**: Um Operador cria uma sessão e deixa as três planilhas válidas carregadas em menos de
  5 minutos.
- **SC-004**: Uma planilha de 10.000 linhas tem o resultado da validação (aceita ou recusada com
  resumo de erros) apresentado em menos de 60 segundos.
- **SC-005**: Nenhum envio recusado ou interrompido deixa lançamento ou arquivo registrado na
  sessão (0 ocorrências).
- **SC-006**: 100% das reaberturas, substituições, confirmações de divergência e exclusões
  aparecem na trilha de auditoria com usuário, data e hora.
- **SC-007**: O Administrador encontra o registro de qualquer exclusão de sessão em menos de
  1 minuto.
- **SC-008**: Após a exclusão de uma sessão, 100% do espaço ocupado por seus arquivos é liberado.
- **SC-009**: Nenhuma sessão já processada pode ser excluída (0 ocorrências), e o arquivo original
  de toda sessão processada permanece disponível para download.

## Clarifications

### Session 2026-10-08

- Q: Quando o resultado da sessão já tem decisões manuais ou vínculos com outras sessões, o que a
  reabertura faz? → A: É bloqueada até que os vínculos e decisões manuais sejam desfeitos.
- Q: O sistema exige layout fixo de cabeçalhos ou o Operador mapeia as colunas? → A: Layout fixo
  agora; o mapeamento pelo Operador fica para uma feature separada.
- Q: As planilhas de pagamentos da Unidade Social e da Unidade de Saúde têm as mesmas colunas? →
  A: Sim. Há um layout único de pagamentos, e a unidade é dada pelo cartão em que o arquivo é
  enviado.
- Q: O que o sistema faz com lançamentos de valor negativo ou zero? → A: Ignora. As linhas são
  puladas sem erro e aparecem contadas no resumo do cartão.
- Q: Em que formato os valores em dinheiro e as datas aparecem nas planilhas? → A: Valores podem
  vir no formato brasileiro (1.234,56) ou no internacional (1,234.56 ou 1234.56). Datas vêm
  no formato brasileiro (dia/mês/ano) nas autorizações; nos pagamentos, no formato do ERP
  (31-JUL-26), conforme a resposta sobre o relatório de liquidação, mais abaixo.
- Q: O que o sistema faz com linhas idênticas dentro da mesma planilha? → A: Importa todas como
  lançamentos separados, sem aviso.
- Q: Pode existir mais de uma sessão para o mesmo mês/ano? → A: Sim, para complementar. Sessões
  adicionais do mesmo período recebem apenas lançamentos que ainda não existem nas anteriores.
- Q: Qual coluna traz o valor a conciliar em cada planilha? → A: VL_RECEBIDO nos pagamentos e VALOR
  nas autorizações.
- Q: Quais são as colunas reais da planilha de autorizações? → A: As onze colunas da planilha
  exportada pelo sistema ELO, conforme as imagens enviadas, com datas em dia/mês/ano e valores
  no formato "R$ 3.895,73" (FR-011h a FR-011j).
- Q: Quais são as colunas e os formatos reais da planilha de pagamentos? → A: Os do relatório de
  liquidação exportado pelo ERP, conforme os arquivos reais de julho/2026 das duas unidades:
  24 colunas separadas por ponto e vírgula, valores com ponto decimal e datas no padrão
  31-JUL-26 (FR-011c a FR-011f).

## Assumptions

- **Exclusão limitada a sessões nunca processadas**: a descrição original pedia exclusão física de
  qualquer sessão. A constituição do projeto (Princípio VII, v2.0.0) exige reter arquivos e
  lançamentos de sessões conciliadas, então a exclusão física vale apenas para sessões que nunca
  foram processadas. Sessões reabertas contam como já processadas.
- **Registro de auditoria estruturado**: a descrição original pedia um registro de texto simples
  para exclusões. O registro segue o padrão único da trilha de auditoria (usuário, data e hora,
  ação, dados antes e depois), que cobre o texto pedido.
- **Sessões complementares**: um mês/ano pode ter várias sessões. Para que os mesmos lançamentos
  não entrem duas vezes nas pendências acumuladas, cada envio é comparado com os lançamentos
  ativos das outras sessões do mesmo período. A comparação não alcança sessões de outros períodos;
  a duplicidade entre períodos diferentes fica com o Módulo 2.
- **Linha repetida entre sessões contra linha repetida no mesmo arquivo**: linhas idênticas dentro
  de um arquivo são lançamentos distintos (FR-010f). Entre sessões, a repetição é decidida em
  pagamentos pelo identificador do lançamento (FR-011g) e em autorizações pelos campos
  obrigatórios, que incluem a solicitação (FR-011i). Duas compras iguais em fornecedor, valor e
  data, mas de obrigações ou solicitações diferentes, são portanto ambas importadas.
- **Sessão complementar exige os três cartões**: a regra de três planilhas obrigatórias vale
  também para sessões complementares; um cartão pode ficar "Carregado" com zero lançamentos novos.
- **Situação "Em processamento"**: adicionada às situações "Aberta" e "Processada" da descrição
  original, porque a conciliação roda em segundo plano e a sessão precisa ficar travada desde a
  solicitação.
- **Formatos aceitos**: .xlsx e .csv, conforme os demais módulos. Apenas a primeira aba de um
  arquivo .xlsx é lida.
- **Envio em qualquer ordem**: os três cartões são independentes e podem ser preenchidos em
  qualquer sequência.
- **Critério de divergência de período**: um lançamento diverge quando sua data cai fora do
  mês/ano da sessão. A confirmação vale para o arquivo inteiro, não por lançamento.
- **Layout de Autorizações**: fixado a partir de imagens da planilha real do ELO, com dados de
  julho/2026 (FR-011h). Quatro nomes aparecem cortados nas imagens e foram completados
  (MAP_SETOR_SOLICITANTE, MAP_FUNCIONARIO_SOLICITANTE, MAP_FORMA_DE_PAGAMENTO,
  MAP_CONDICAO_DE_PAGAMENTO). Nenhum deles é obrigatório (FR-011k): se a grafia real for outra, o
  arquivo é aceito e o cartão avisa que a coluna não foi encontrada.
- **Valor da autorização**: confirmado que vale a coluna VALOR; MAP_VALOR é apenas guardado.
- **Solicitação como identificador**: MAP_SOLICITACAO traz a descrição seguida de um número
  ("CESTA BASICA PARA DOACAO - 00951"). Por ser campo obrigatório, entra na comparação entre
  sessões (FR-003b), de modo que duas compras iguais em fornecedor, valor e data, mas de
  solicitações diferentes, são ambas importadas. Assume-se que o número não se repete.
- **Autorizações sem unidade**: a planilha do ELO não indica a unidade (Social ou Saúde); o setor
  solicitante não é tratado como unidade.
- **Valor do pagamento**: confirmado que o valor a conciliar é o valor pago (VL_RECEBIDO), que já
  reflete juros e descontos, e não o valor da obrigação (VL_OBRIGACAO).
- **Identificador do pagamento**: nos arquivos reais, o número da obrigação se repete em linhas de
  códigos de operação diferentes; falta confirmar com o time financeiro que não se repete de
  outra forma. O movimento de conta entra no identificador para distinguir duas liquidações
  parciais da mesma obrigação.
- **Dados de folha no relatório**: o relatório do ERP traz pagamentos de salários e ressarcimentos
  com nome de pessoas. Este módulo os guarda como os demais lançamentos; restringir quem os vê não
  está definido.
- **Volume observado**: o relatório mensal da Unidade de Saúde tem alguns milhares de linhas e o da
  Unidade Social, algumas centenas, dentro da meta de 10.000 linhas (SC-004).
- **Mapeamento de colunas adiado**: o mapeamento de colunas pelo Operador com layout salvo
  (Módulo 2, Story 1.1) fica para uma feature separada. Até lá, planilhas fora do layout fixo
  precisam ser ajustadas na origem.
- **Reabertura com decisões manuais**: desfazer as decisões manuais é feito nas telas dos módulos
  de conciliação (Módulos 2 a 4). Este módulo apenas verifica se elas existem e bloqueia a
  reabertura.
- **Acesso**: todo usuário autenticado (Operador ou Administrador) atua sobre todas as sessões e
  ambas as unidades, conforme o Princípio VIII da constituição. Não há dono de sessão.
- **Estornos não são conciliados**: como linhas negativas são ignoradas na importação, estornos e
  devoluções presentes no extrato não entram na conciliação nem nas pendências. A cópia exata do
  arquivo original continua guardada e contém essas linhas.
- **Fora do escopo**: o cálculo de compatibilidade e a classificação dos lançamentos (Módulo 2), o
  tratamento de parcelas (Módulo 3), a detecção de lançamentos duplicados entre sessões
  (Módulo 2), a aplicação dos códigos de operação excluídos (Módulo 5) e a tela de consulta da
  trilha de auditoria (Módulo 6). Este módulo entrega as planilhas validadas, o controle de estado
  da sessão e a gravação dos eventos de auditoria.
- **Dependência**: a execução da conciliação depende do motor do Módulo 2. Até ele existir, este
  módulo pode ser demonstrado até o ponto em que a execução fica disponível.
