# Feature Specification: Códigos de Operação Excluídos da Conciliação (Módulo 5)

**Feature Branch**: `002-excluded-operation-codes`
**Created**: 2026-10-08
**Status**: Draft
**Input**: User description: "Na planilha CSV dos pagamentos realizados vêm muitos pagamentos que não devem entrar na conciliação. É preciso uma função para importar de uma planilha, ou cadastrar manualmente, os códigos que devem ser desconsiderados." (complementa a descrição do Módulo 5 - Lista de Códigos de Operação Ignorados)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Cadastrar manualmente um código a desconsiderar (Priority: P1)

O relatório de pagamentos do ERP traz folha de pagamento, impostos, ressarcimentos e outras
operações que nunca terão uma autorização de compra correspondente. Quem administra a conciliação
mantém uma lista dos códigos de operação (COD_OPERACAO) que devem ficar de fora. Na tela "Códigos
de Operação Excluídos" ele informa um código e, se quiser, uma descrição, e o código passa a
constar na lista.

**Why this priority**: É a forma mais simples de montar a lista e já entrega o valor central:
tirar da conciliação os pagamentos que não são compras.

**Independent Test**: Cadastrar um código com descrição, verificar que ele aparece na lista com
data e responsável, e tentar cadastrá-lo de novo para confirmar a recusa por duplicidade.

**Acceptance Scenarios**:

1. **Given** a tela "Códigos de Operação Excluídos", **When** o usuário informa um código válido
   e uma descrição e salva, **Then** o código entra na lista com a data, a hora e o usuário
   responsável, e o sistema confirma o cadastro.
2. **Given** o formulário de cadastro, **When** o usuário salva um código sem descrição, **Then**
   o código é cadastrado com a descrição vazia.
3. **Given** um código já presente na lista, **When** o usuário tenta cadastrá-lo de novo,
   **Then** o sistema recusa e informa que o código já está cadastrado.
4. **Given** o formulário de cadastro, **When** o usuário informa " 20150652 " com espaços,
   **Then** o sistema considera "20150652" tanto para validar quanto para verificar duplicidade.
5. **Given** o formulário de cadastro, **When** o usuário salva com o código em branco ou
   inválido, **Then** nada é cadastrado e o campo mostra o motivo.
6. **Given** pagamentos já importados e um código que nenhum deles usa (por exemplo, digitado com
   um dígito a menos), **When** o usuário informa esse código, **Then** o formulário mostra o
   alerta "nenhum pagamento importado usa este código" e o cadastro continua permitido.

---

### User Story 2 - Desconsiderar na conciliação os pagamentos com código excluído (Priority: P1)

Quando uma sessão é executada, todo pagamento cujo código de operação consta na lista fica fora da
conciliação: não recebe pontuação, não é sugerido como par de nenhuma autorização e não aparece
como pendência. O pagamento continua guardado na sessão, marcado como excluído por código.

**Why this priority**: É o efeito que justifica a lista. Sem ele, a lista é só um cadastro.

**Entrega**: este módulo entrega a lista e a forma de o motor consultá-la sem que ela mude durante
a execução. Deixar os pagamentos de fora, marcá-los e registrar os códigos por execução é feito
pelo motor de conciliação (Módulo 2), que é implementado depois. Os cenários 1 a 3 são validados
com o Módulo 2.

**Independent Test**: Cadastrar um código, executar uma sessão cujos pagamentos incluem esse
código e verificar que esses pagamentos não foram conciliados nem listados como pendentes, e que
estão marcados como excluídos com o código que causou a exclusão.

**Acceptance Scenarios**:

1. **Given** um código na lista e uma sessão com pagamentos desse código, **When** a conciliação
   é executada, **Then** nenhum desses pagamentos é comparado com autorizações e todos ficam
   marcados como excluídos por código.
2. **Given** uma sessão já processada, **When** um código é adicionado à lista ou removido dela,
   **Then** o resultado dessa sessão não muda até que ela seja reaberta e executada de novo.
3. **Given** uma sessão processada, **When** o usuário consulta os dados da execução, **Then** vê
   quais códigos estavam na lista naquele momento e quantos pagamentos cada um excluiu.
4. **Given** um pagamento cujo código difere de um código da lista apenas por espaços no início
   ou no fim, **When** a conciliação é executada, **Then** o pagamento é excluído.

---

### User Story 3 - Importar códigos de uma planilha (Priority: P2)

Para montar ou ampliar a lista de uma vez, o usuário envia uma planilha com os códigos na primeira
coluna e as descrições na segunda. O sistema confere o arquivo inteiro antes de gravar: se
qualquer linha tiver problema, nada é gravado. Códigos que já estão na lista são apenas ignorados.

**Why this priority**: Os relatórios reais trazem dezenas de códigos a desconsiderar; cadastrar um
a um é lento, mas o cadastro manual já permite operar.

**Independent Test**: Importar um arquivo com 10 códigos novos e 5 já cadastrados e verificar o
resumo "10 adicionados, 5 ignorados"; importar um arquivo com uma linha inválida e verificar que
nenhum código foi gravado e que a linha com problema foi indicada.

**Acceptance Scenarios**:

1. **Given** um arquivo válido com 10 códigos novos e 5 já cadastrados, **When** o usuário o
   importa, **Then** o sistema grava os 10, ignora os 5 e informa "Importação concluída: 10
   códigos adicionados e 5 códigos ignorados por já estarem cadastrados ou repetidos no arquivo."
2. **Given** um arquivo de 100 linhas em que a linha 45 tem o código em branco ou inválido,
   **When** o usuário o importa, **Then** nenhum código é gravado e o sistema indica a linha 45, a
   coluna e o motivo.
3. **Given** um arquivo com mais de uma linha inválida, **When** o usuário o importa, **Then** o
   sistema lista todos os erros, cada um com a linha, a coluna e o motivo.
4. **Given** um arquivo em que o mesmo código aparece três vezes, **When** o usuário o importa,
   **Then** o código é gravado uma vez e as outras duas ocorrências contam como ignoradas.
5. **Given** a tela de importação, **When** o usuário aciona "Baixar Planilha Modelo", **Then**
   recebe um arquivo com os cabeçalhos COD_OPERACAO e DESCRICAO e uma linha de exemplo.
6. **Given** um arquivo cuja primeira linha é o cabeçalho do modelo, **When** o usuário o
   importa, **Then** essa linha não é tratada como código.
7. **Given** um arquivo em que todos os códigos já estão cadastrados, **When** o usuário o
   importa, **Then** a importação conclui com zero adicionados e informa quantos foram ignorados.

---

### User Story 4 - Consultar e remover códigos da lista (Priority: P2)

O usuário consulta a lista completa, procura um código ou um trecho da descrição, ordena pelas
colunas e remove um código que não deve mais ser desconsiderado.

**Why this priority**: A lista cresce e precisa de manutenção, mas o valor inicial vem de incluir
códigos, não de removê-los.

**Independent Test**: Com mais de 50 códigos cadastrados, verificar a paginação, buscar por parte
de uma descrição, ordenar por data e remover um código confirmando a ação.

**Acceptance Scenarios**:

1. **Given** uma lista com mais de 50 códigos, **When** o usuário abre a tela, **Then** vê os
   códigos em páginas, com código, descrição, data de cadastro e responsável.
2. **Given** a lista, **When** o usuário digita na busca, **Then** a lista mostra apenas os
   códigos cujo código ou descrição contém o texto digitado.
3. **Given** a lista, **When** o usuário ordena por código, descrição ou data, **Then** a ordem
   escolhida vale para a lista inteira, não só para a página exibida.
4. **Given** um código da lista, **When** o usuário aciona a remoção e confirma, **Then** o código
   sai da lista e deixa de excluir pagamentos nas próximas execuções.
5. **Given** o pedido de confirmação da remoção, **When** o usuário cancela, **Then** nada muda.
6. **Given** um código removido, **When** o usuário o cadastra de novo, **Then** ele volta à lista
   como um cadastro novo.

---

### User Story 5 - Restringir o acesso e rastrear alterações (Priority: P2)

Só quem tem a permissão de gerenciar códigos excluídos vê a tela e altera a lista. Cada inclusão
ou remoção fica registrada com quem fez, quando e o que mudou.

**Why this priority**: A lista altera o resultado de toda conciliação futura; uma mudança sem dono
compromete a confiança no resultado.

**Independent Test**: Entrar com um usuário sem a permissão e verificar que a tela não aparece no
menu e que o acesso direto é negado; entrar com um usuário com a permissão, incluir e remover um
código e verificar os dois registros na trilha de auditoria.

**Acceptance Scenarios**:

1. **Given** um usuário sem a permissão, **When** ele tenta abrir a tela, **Then** o sistema nega
   o acesso e a opção não aparece no menu dele.
2. **Given** um usuário com a permissão, **When** ele cadastra um código manualmente, **Then** a
   trilha de auditoria registra usuário, data e hora, a ação "Inclusão manual" e o código.
3. **Given** uma importação que gravou 10 códigos, **When** ela termina, **Then** a trilha de
   auditoria registra a ação "Inclusão por arquivo", o nome do arquivo e os códigos adicionados.
4. **Given** a remoção de um código, **When** ela é confirmada, **Then** a trilha de auditoria
   registra a ação "Exclusão" com o código e a descrição que ele tinha.
5. **Given** uma importação recusada por erro, **When** ela termina, **Then** nenhum registro de
   inclusão é gravado na trilha de auditoria.

---

### Edge Cases

- **Arquivo sem cabeçalho**: a primeira linha só é tratada como cabeçalho quando a primeira célula
  é COD_OPERACAO; caso contrário é lida como código.
- **Arquivo com colunas invertidas** (descrição na primeira coluna): as linhas falham na validação
  do código e a importação inteira é recusada.
- **Arquivo com mais de duas colunas**: as colunas a partir da terceira são desconsideradas. Isso
  permite importar um recorte do próprio relatório do ERP com código e nome da operação.
- **Linhas totalmente vazias**: são puladas e não contam como erro.
- **Arquivo sem nenhum código** (vazio ou só com cabeçalho): é recusado com a mensagem de que não
  há códigos para importar.
- **Arquivo acima do limite**: com mais de 5 MB, é recusado antes de ser lido; com mais de 10.000
  linhas, é recusado durante a leitura, sem lista de erros por linha. Nos dois casos o sistema
  informa o limite.
- **Arquivo de tipo não aceito**: é recusado informando os tipos aceitos (.csv e .xlsx).
- **Código lido como número pela planilha** (20150652 guardado como número): é tratado como o
  texto "20150652", sem casas decimais nem notação científica.
- **Código com zeros à esquerda**: "00123" e "123" são códigos diferentes.
- **Descrição diferente para código já cadastrado**: o código é ignorado como duplicado e a
  descrição cadastrada não é alterada.
- **Dois usuários cadastram o mesmo código ao mesmo tempo**: um cadastro vale; o outro recebe o
  aviso de código já cadastrado.
- **Lista alterada enquanto uma conciliação está em execução**: a execução usa a lista como estava
  quando começou.
- **Código da lista que não aparece em nenhum pagamento**: não tem efeito e não é erro.
- **Remoção de código que já excluiu pagamentos em sessões processadas**: é permitida; as sessões
  processadas mantêm o resultado e o registro de que o código valia naquela execução.

## Requirements *(mandatory)*

### Functional Requirements

**Lista e cadastro manual**

- **FR-001**: O sistema DEVE manter uma única lista de códigos de operação excluídos, válida para
  os pagamentos das duas unidades.
- **FR-002**: O sistema DEVE permitir cadastrar um código com descrição opcional, registrando o
  usuário responsável e a data e hora.
- **FR-003**: Um código DEVE ter de 1 a 20 caracteres, somente letras sem acento (A a Z, a a z)
  e dígitos. A descrição PODE ter até 255 caracteres.
- **FR-004**: O sistema DEVE desconsiderar espaços no início e no fim do código e da descrição
  antes de validar e de verificar duplicidade, no cadastro manual e na importação.
- **FR-005**: O sistema DEVE recusar o cadastro manual de um código que já esteja na lista.
- **FR-005a**: No cadastro manual, o sistema DEVE alertar quando nenhum pagamento já importado
  usa o código informado, antes de salvar e na confirmação do cadastro. O alerta NÃO DEVE impedir
  o cadastro e NÃO é exibido enquanto não houver pagamento importado. (Acrescentado em
  2026-10-08, a pedido do responsável, depois de um erro de digitação no teste manual.)

**Importação por planilha**

- **FR-006**: O sistema DEVE aceitar a importação de arquivos .csv e .xlsx.
- **FR-007**: O sistema DEVE ler o código na primeira coluna e a descrição na segunda,
  desconsiderando as demais colunas. No arquivo .xlsx, lê a primeira aba.
- **FR-008**: O sistema DEVE tratar a primeira linha como cabeçalho somente quando a primeira
  célula for COD_OPERACAO, sem diferenciar maiúsculas de minúsculas.
- **FR-009**: O sistema DEVE validar todas as linhas antes de gravar e, havendo qualquer linha
  inválida, não gravar nenhum código.
- **FR-010**: Na recusa, o sistema DEVE informar, para cada erro, o número da linha, a coluna
  (COD_OPERACAO ou DESCRICAO) e o motivo.
- **FR-011**: O sistema DEVE ignorar, sem erro, os códigos que já estão na lista e os repetidos
  dentro do próprio arquivo, e informar ao final quantos foram adicionados e quantos ignorados.
- **FR-012**: O sistema DEVE recusar arquivos com mais de 5 MB ou mais de 10.000 linhas e arquivos
  sem nenhum código.
- **FR-013**: O sistema DEVE aceitar o arquivo .csv separado por ponto e vírgula ou por vírgula e
  exibir corretamente as descrições acentuadas, inclusive na codificação usada pelo ERP.
- **FR-014**: O sistema DEVE oferecer o download de uma planilha modelo com os cabeçalhos
  COD_OPERACAO e DESCRICAO e uma linha de exemplo.
- **FR-015**: O sistema DEVE indicar que a importação está em andamento e impedir um segundo envio
  pelo mesmo usuário até ela terminar.

**Consulta e remoção**

- **FR-016**: O sistema DEVE listar os códigos em páginas, com código, descrição, data de cadastro
  e responsável.
- **FR-017**: O sistema DEVE permitir buscar por trecho do código ou da descrição e ordenar por
  código, descrição ou data, aplicando busca e ordenação à lista inteira.
- **FR-018**: O sistema DEVE permitir remover um código da lista mediante confirmação.
- **FR-019**: Um código removido DEVE poder ser cadastrado de novo.

**Efeito na conciliação**

FR-020 a FR-023 são cumpridos pelo motor de conciliação (Módulo 2), a partir da lista entregue
por este módulo. FR-024 a FR-026 são cumpridos aqui.

- **FR-020**: Ao executar a conciliação de uma sessão, o sistema DEVE deixar de fora todo
  pagamento cujo código de operação conste na lista naquele momento.
- **FR-021**: Um pagamento deixado de fora NÃO DEVE receber pontuação, ser sugerido como par, ser
  vinculado manualmente nem aparecer como pagamento pendente.
- **FR-022**: O sistema DEVE manter o pagamento deixado de fora na sessão, marcado como excluído
  por código, com a indicação do código que causou a exclusão.
- **FR-023**: O sistema DEVE registrar, em cada execução, os códigos que compunham a lista e a
  quantidade de pagamentos excluídos por código.
- **FR-024**: Alterações na lista NÃO DEVEM mudar o resultado de sessões já processadas; passam a
  valer na próxima execução de cada sessão.
- **FR-025**: A comparação entre o código do pagamento e o código da lista DEVE ser exata, depois
  de desconsiderados os espaços no início e no fim.
- **FR-026**: A importação das planilhas de pagamentos (Módulo 1) NÃO DEVE ser afetada pela lista:
  os pagamentos com código excluído continuam sendo importados.

**Acesso e auditoria**

- **FR-027**: O sistema DEVE permitir consultar e alterar a lista somente a usuários com a
  permissão de gerenciar códigos excluídos, e negar o acesso aos demais.
- **FR-028**: O sistema NÃO DEVE mostrar a opção de menu a quem não tem a permissão.
- **FR-029**: O sistema DEVE registrar na trilha de auditoria cada inclusão manual, inclusão por
  arquivo e exclusão, com usuário, data e hora, ação e os dados do código antes e depois.
- **FR-030**: Os registros de auditoria NÃO DEVEM poder ser alterados nem apagados.

### Key Entities

- **Código de Operação Excluído**: um código de operação do ERP que não deve entrar na
  conciliação. Tem código (único na lista), descrição opcional, quem cadastrou, quando e por qual
  meio (manual ou arquivo).
- **Importação de Códigos**: um envio de planilha de códigos. Tem nome do arquivo, quem enviou,
  quando, quantos códigos foram adicionados e quantos ignorados.
- **Lançamento de Pagamento** (do Módulo 1): passa a ter a indicação de excluído por código e o
  código que causou a exclusão, por execução.
- **Execução da Conciliação** (do Módulo 2): guarda a lista de códigos vigente no momento e as
  quantidades de pagamentos excluídos por código.
- **Registro de Auditoria**: descrito no Módulo 1; aqui recebe as ações de inclusão manual,
  inclusão por arquivo e exclusão de código.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Nenhum pagamento com código presente na lista no momento da execução é conciliado,
  sugerido ou listado como pendente. (Verificado com o Módulo 2.)
- **SC-002**: Nenhuma importação com ao menos uma linha inválida altera a lista.
- **SC-003**: Um usuário cadastra um código manualmente em menos de 30 segundos.
- **SC-004**: Uma planilha com 10.000 códigos é importada em menos de 1 minuto.
- **SC-005**: Toda alteração na lista pode ser atribuída a um usuário e a um momento, com precisão
  de segundos.
- **SC-006**: Com 10.000 códigos cadastrados, a lista responde a busca, ordenação e troca de
  página em menos de 2 segundos.
- **SC-007**: Para qualquer sessão processada, é possível saber quais códigos valiam na execução
  e quantos pagamentos cada um excluiu. (Verificado com o Módulo 2.)

## Assumptions

- **Perfis**: a descrição original cita "Administrador" e "Gerente Financeiro". O sistema tem os
  papéis Administrador e Operador com permissões granulares (constituição, Princípio VIII). O
  acesso passa a depender da permissão de gerenciar códigos excluídos, concedida por padrão ao
  Administrador e atribuível a outros usuários.
- **Momento da exclusão**: os pagamentos com código excluído são importados normalmente e deixados
  de fora na execução da conciliação, não na importação. Assim, mudar a lista não exige reenviar
  planilhas, e os pagamentos excluídos continuam disponíveis para o relatório "Excluídos por
  Código" (Módulo 6).
- **Sessões já processadas**: para aplicar uma lista alterada a uma sessão processada, ela precisa
  ser reaberta e executada de novo, respeitando as regras de reabertura do Módulo 1.
- **Formato do código**: nos relatórios reais de julho/2026 os códigos são numéricos. Aceitam-se
  também letras para não depender disso.
- **Critério único**: a exclusão é só pelo código de operação. Excluir por tipo, espécie, conta
  corrente ou fornecedor não faz parte desta funcionalidade.
- **Lista única**: não há lista separada por unidade nem por período.
- **Histórico de códigos removidos**: um código removido sai da lista ativa; o que resta dele é o
  registro de auditoria e o registro nas execuções em que valeu.
- **Limites de arquivo**: 5 MB e 10.000 linhas, conforme sugerido na descrição original.
- **Fora do escopo**: marcar um código como excluído a partir da tela de pagamentos de uma sessão;
  editar a descrição de um código cadastrado (remove-se e cadastra-se de novo); o relatório
  "Excluídos por Código" (Módulo 6); o motor de conciliação em si (Módulo 2).
- **Ajustes à descrição original**: a leitura "estritamente por posição" foi mantida, com a
  ressalva de que a linha de cabeçalho do modelo é reconhecida e pulada; o registro de auditoria
  segue a estrutura da constituição (Princípio VII) em vez de uma aba própria do módulo.
