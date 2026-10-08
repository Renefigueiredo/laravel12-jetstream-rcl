# Research: Códigos de Operação Excluídos da Conciliação (Módulo 5)

Decisões da Fase 0. Cada item traz a decisão, o motivo e o que foi descartado. As decisões do
Módulo 1 continuam valendo e são citadas como `001/Rn`
([research do Módulo 1](../001-session-file-import/research.md)).

## R1. Stack e reaproveitamento

- **Decision**: nenhuma dependência nova. O módulo usa o que o Módulo 1 já entregou: leitor de
  planilhas (`SpreadsheetReader` / `OpenSpoutSpreadsheetReader`), `CellText`, `AuditRecorder`,
  `ActionRefusedException`, fila em banco de dados, disco privado, Filament Tables e Actions em
  componente Livewire de página inteira e `lang/pt_BR`.
- **Rationale**: Princípio V (mudanças mínimas). O leitor já resolve delimitador (`;` ou `,`),
  codificação do ERP (Windows-1252) e células de fórmula, o que cobre FR-013.
- **Alternatives considered**: um leitor próprio para um arquivo de duas colunas. Descartado por
  duplicar regras já testadas.
- **Versões em uso** (conferidas em `composer.lock`): PHP 8.5, Laravel 12.69, Livewire 4.4,
  Filament 5.10, Jetstream 5.5, OpenSpout 4.32, PHPUnit 11.

## R2. Permissões por usuário (Princípio VIII, FR-027, FR-028)

- **Decision**: Enum `UserPermission` com o caso `ManageExcludedCodes` e tabela
  `user_permissions` (usuário, permissão, quem concedeu, quando), única por usuário e permissão.
  `User::hasPermission()` devolve verdadeiro para todo Administrador e, para os demais, consulta
  a tabela. O gate `manage-excluded-codes` usa esse método; rota, componente e menu usam o gate.
- **Rationale**: o Módulo 1 só tinha papéis (`001/R15`) e deixou as permissões para este módulo.
  A constituição pede permissões independentes concedidas por usuário. Uma tabela mantém chave
  estrangeira e unicidade no banco (Princípio IV) e serve aos Módulos 2 e 6, que acrescentam
  casos ao Enum.
- **Alternatives considered**: coluna JSON em `users` (sem integridade no banco, difícil de
  consultar); pacote de permissões (dependência nova para quatro permissões).
- **Como conceder**: não existe tela de usuários. A concessão e a revogação são feitas pelos
  comandos `conciliation:grant-permission` e `conciliation:revoke-permission`, que exigem o
  e-mail do Administrador responsável e gravam auditoria. A tela de usuários fica para uma
  feature própria (ver Decisões pendentes no [plan.md](./plan.md)).
- **Limite conhecido**: `--by` identifica o Administrador responsável, mas não o autentica. Quem
  tem acesso ao servidor pode informar qualquer e-mail. Aceito enquanto a concessão for por
  comando.

## R3. Modelo da lista (FR-001 a FR-005, FR-018, FR-019)

- **Decision**: tabela `excluded_operation_codes` com `code` único. A remoção apaga a linha; o
  histórico fica na trilha de auditoria. Recadastrar um código removido cria uma linha nova.
- **Rationale**: a spec diz que o código removido sai da lista ativa e que resta dele apenas a
  auditoria e o registro nas execuções. Sem exclusão lógica, a unicidade é um índice simples e a
  consulta do motor não precisa de filtro.
- **Alternatives considered**: exclusão lógica com `deleted_at`. Exigiria índice único parcial e
  não acrescenta informação que a auditoria já não tenha.
- **Normalização**: `OperationCode::normalize()` remove espaços no início e no fim (incluindo o
  espaço não separável) e nada mais. Maiúsculas e zeros à esquerda são preservados: "00123" e
  "123" são diferentes (Edge Cases), e a comparação é exata (FR-025).
- **Validação**: `^[A-Za-z0-9]{1,20}$` para o código; descrição com até 255 caracteres.
- **Corrida entre dois cadastros** (Edge Cases): o índice único decide. A ação captura a violação
  de unicidade e responde "código já cadastrado".

## R4. Importação em fila (Princípio VI, FR-009, FR-015)

- **Decision**: o envio cria um registro `excluded_code_imports` com situação `Queued` e despacha
  o job `ImportExcludedCodes`. O job lê o arquivo inteiro, valida todas as linhas e só então
  grava, em uma transação. A tela acompanha por `wire:poll` enquanto houver importação em
  andamento, como no painel de sessão.
- **Rationale**: a constituição exige que o processamento de planilhas rode em job de fila com
  progresso. O arquivo é pequeno (5 MB, 10.000 linhas), mas a regra vale e o padrão já existe.
- **Alternatives considered**: processar na própria requisição. Mais simples, porém contraria o
  Princípio VI e exigiria justificativa em Complexity Tracking.
- **Um envio por vez por usuário** (FR-015): a ação recusa o envio quando o usuário tem uma
  importação `Queued` ou `Processing`.
- **Importação travada**: o comando `conciliation:recover-stuck-sessions` passa a marcar como
  `Failed` a importação parada há mais de `conciliation.stale.attempt_minutes`.
- **Limpeza**: o comando `conciliation:prune-import-attempts` passa a apagar as importações
  recusadas ou falhas (registro e arquivo) depois de `conciliation.attempts.retention_hours`.
  As importações concluídas e seus arquivos são retidos (Princípio VII).

## R5. Leitura e validação do arquivo (FR-006 a FR-013)

- **Decision**: `ExcludedCodeFileParser` recebe as linhas do `SpreadsheetReader` e devolve os
  códigos válidos, os erros por linha e as contagens. É uma classe pura, testada por Unit.
- **Regras, na ordem**:
  1. Linha 1 é cabeçalho somente se a primeira célula, sem espaços e sem diferenciar maiúsculas,
     for `COD_OPERACAO`.
  2. Linha com as duas primeiras células vazias é pulada.
  3. Código vazio com descrição preenchida: erro "código em branco".
  4. Código fora do padrão de R3: erro "código inválido".
  5. Descrição com mais de 255 caracteres: erro "descrição acima de 255 caracteres".
  6. Código repetido no arquivo: a primeira ocorrência vale; as demais contam como ignoradas.
- **Código lido como número**: `CellText::from()` já converte número inteiro em texto sem casas
  decimais nem notação científica.
- **Limites**: tamanho conferido na regra de envio do Livewire, antes da fila; quantidade de
  linhas conferida durante a leitura, que para ao passar do limite e recusa o arquivo informando
  o limite, sem lista de erros por linha. Linhas em branco no fim de um `.xlsx` param a leitura
  pelo mesmo limite do Módulo 1 (`conciliation.upload.blank_rows_limit`).
- **Arquivo sem nenhum código**: recusado com mensagem própria.
- **Erros**: guardados em JSON no registro da importação (linha, coluna e motivo, como exige o
  Princípio VI) e exibidos na tela. Não
  há relatório para download: o arquivo tem duas colunas e a lista na tela basta.

## R6. Gravação e contagens (FR-011, SC-002, SC-004)

- **Decision**: `ExcludedCodeImporter` carrega os códigos existentes em uma consulta, separa os
  novos e insere em lotes de `conciliation.upload.insert_chunk` com `insertOrIgnore`, dentro de
  uma transação. "Adicionados" é a soma das linhas de fato inseridas; "ignorados" é o total de
  códigos válidos lidos menos os adicionados. A lista de códigos da auditoria é lida do banco
  depois da gravação, pelos códigos ligados à importação, para não incluir um código que outro
  usuário cadastrou no meio do caminho.
- **Rationale**: `insertOrIgnore` cobre o caso de outro usuário cadastrar o mesmo código entre a
  leitura e a gravação, sem derrubar a importação. A documentação do Laravel 12 (consultada no
  Boost) avisa que o método ignora erros de duplicidade; como a validação já garantiu o formato,
  a duplicidade é o único erro possível.
- **Descrição de código já cadastrado**: não é alterada (Edge Cases).

## R7. Auditoria (Princípio VII, FR-029, FR-030)

- **Decision**: novos casos em `AuditAction`: `ExcludedCodeAdded` (Inclusão manual),
  `ExcludedCodesImported` (Inclusão por arquivo), `ExcludedCodeRemoved` (Exclusão),
  `PermissionGranted` e `PermissionRevoked`. Todos gravados pelo `AuditRecorder` na transação da
  mudança.
- **Inclusão por arquivo**: um registro por importação, apontando para a importação, com o nome
  do arquivo e a lista de códigos adicionados no campo "depois". Importação com zero adicionados
  grava o registro com a lista vazia. Importação recusada não grava nada (US5, cenário 5).
- **Exclusão**: o registro é gravado antes de apagar a linha, com código e descrição no campo
  "antes".
- **Imutabilidade**: já garantida pelo Módulo 1 (`001/R14`): sem rota de alteração e gatilho no
  PostgreSQL.
- **Consulta da trilha**: a tela de auditoria é do Módulo 6. Aqui os testes conferem o banco.

## R8. Efeito na conciliação (FR-020 a FR-026, US2)

- **Decision**: este módulo entrega o serviço `ExcludedOperationCodes`, com o método
  `snapshot()`, que devolve um objeto imutável `ExcludedCodeSnapshot` (`contains()` e `codes()`).
  O motor (Módulo 2) pede a fotografia uma vez, no início da execução, e a usa até o fim.
- **O que fica para o Módulo 2**: marcar o pagamento como excluído por código, guardar na
  execução os códigos vigentes e as quantidades por código, e esconder esses pagamentos das
  pendências. Essas regras dependem da entidade "Execução", que ainda não existe.
- **Rationale**: FR-020 a FR-023 descrevem o comportamento do motor. Implementá-los aqui exigiria
  criar a execução antes do motor. A fotografia garante FR-024 e o caso "lista alterada durante a
  execução" (Edge Cases) por construção.
- **Consequência**: a User Story 2 não é validável de ponta a ponta só com este módulo. Aqui ela é
  coberta por testes do serviço (comparação exata após remover espaços, fotografia que não muda
  quando a lista muda). Os cenários de aceitação 1 a 3 entram no plano do Módulo 2.
- **FR-026**: nenhuma mudança na importação de pagamentos. Um teste confirma que pagamentos com
  código da lista continuam sendo importados.

## R9. Interface (Princípio II)

- **Decision**: uma tela, `ExcludedCodes\Index`, em `/codigos-excluidos`, no layout do Jetstream.
  Tabela do Filament com busca por código e descrição, ordenação por código, descrição e data, e
  paginação. Busca e ordenação ficam na URL. Cadastro manual em modal do Jetstream, como "Nova
  sessão". Remoção por ação de linha do Filament com confirmação. Envio de arquivo com
  `WithFileUploads`, em um bloco acima da tabela que mostra a importação em andamento, o resumo
  da última e os erros da recusa.
- **Rationale**: mesmos padrões do Módulo 1 (`001/R18`, `001/R23`), já conferidos no código
  instalado. A direção visual é a definida pelo responsável em 2026-10-08: padrão do Jetstream.
- **Planilha modelo**: `.xlsx` com as células de código em formato texto, para não perder zeros à
  esquerda ao abrir no Excel. Servida por rota própria, protegida pelo mesmo gate.
- **Boost**: a busca de documentação não trouxe conteúdo do Filament nesta sessão. As APIs de
  tabela e de ação usadas são as mesmas de `Sessions\Index` e `Sessions\History`, que estão em
  produção e cobertas por teste.

## R10. Configuração

- **Decision**: novo bloco em `config/conciliation.php`:
  `excluded_codes.max_size_mb` (5) e `excluded_codes.max_rows` (10.000), lidos de variáveis de
  ambiente.
- **Rationale**: Princípio VI: limites de envio vêm de configuração e são aplicados no servidor.

## R11. Banco de dados em testes

- **Decision**: testes em SQLite, como no Módulo 1 (`001/R16`). A unicidade de `code` é sensível a
  maiúsculas nos dois bancos.
- **Risco**: o comportamento de `insertOrIgnore` sob concorrência real só se prova em PostgreSQL.
  A pendência de rodar a suíte em PostgreSQL continua aberta desde o Módulo 1.

## R12. Decisões tomadas durante a implementação

- **Importação parada**: tratada em `conciliation:prune-import-attempts`, e não em
  `conciliation:recover-stuck-sessions` como o plano previa. É nesse comando que o Módulo 1 já
  marca os envios parados, e os dois usam o mesmo limite (`conciliation.stale.attempt_minutes`).
- **Gravação sem filtro prévio**: o importador não consulta os códigos existentes antes de
  gravar. Ele insere todos os códigos válidos com `insertOrIgnore` e depois lê do banco os que
  ficaram ligados à importação. Assim, o código já cadastrado e o código cadastrado por outro
  usuário no meio do caminho seguem o mesmo caminho, que é o coberto pelos testes.
- **Arquivo ilegível**: um `.xlsx` corrompido é recusado com a mensagem "Não foi possível ler o
  arquivo", em vez de falhar o job.
- **Auditoria de permissão**: a entidade do registro é a concessão (`user_permission`), não o
  usuário. Pôr o usuário no mapa polimórfico mudaria o tipo gravado pelos tokens do Sanctum.
- **Ordenação na URL**: um parâmetro só, `ordem=coluna:direção`, que é como o Filament 5 guarda a
  ordenação.
- **Busca**: a busca do Filament separa as palavras; todas precisam aparecer no código ou na
  descrição.
- **Validação do envio**: tipo e tamanho são conferidos na ação `SubmitExcludedCodeImport`, como
  no envio de planilhas do Módulo 1, e não em regra do componente.
- **Alerta de código sem uso** (FR-005a): acrescentado depois do teste manual, em que o código
  da Farmácia foi cadastrado com um dígito a menos. O alerta aparece no modal enquanto o usuário
  digita e na mensagem de confirmação, e nunca bloqueia. A importação por planilha não alerta.
- **Resultado dos testes**: 88 testes novos; suíte completa com 378 aprovados e 10 pulados
  (os mesmos do Jetstream de antes). Em SQLite, 10.000 códigos são importados e listados dentro
  dos limites de SC-004 e SC-006.
- **Roteiro manual (T056)**: pendente. Depende de navegador e é feito pelo responsável.
