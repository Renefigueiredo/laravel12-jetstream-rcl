# Research: Gestão de Sessões e Importação de Arquivos (Módulo 1)

**Date**: 2026-10-08 | **Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)

Fontes: `composer.json`, `composer.lock`, `package.json`, `config/`, `phpunit.xml`, os relatórios
reais de julho/2026 (ERP e ELO) e a constituição v2.0.0. O servidor Laravel Boost não respondeu
nesta sessão porque `vendor/` não está instalado; as decisões que dependem da API de Livewire 4,
Filament 5 e OpenSpout estão marcadas com **[verificar no Boost]** e devem ser conferidas com
`search-docs` antes de codificar (Princípio V).

## R1. Estado real da stack

- **Decision**: planejar sobre o que está no `composer.lock`: Laravel 12.53, Livewire 4.2,
  Filament 5.3 (forms, tables, actions, notifications, infolists), Tailwind CSS 4.2, Jetstream 5.4,
  PHPUnit 11.
- **Rationale**: o `CLAUDE.md` gerado pelo Boost cita Livewire 3 e Tailwind 3, mas o lock e o
  `package.json` têm as versões 4. A constituição já pede Livewire 4 e Tailwind 4, então o
  TODO(UI_VERSIONS) da constituição está resolvido; o que está desatualizado é o `CLAUDE.md`.
- **Alternatives considered**: nenhuma; é constatação.

## R2. Leitura e escrita de planilhas

- **Decision**: usar `openspout/openspout` (4.32, já presente no lock como dependência de
  `filament/actions`) para ler .xlsx e .csv e para gerar a planilha modelo e o relatório de erros.
  Declarar o pacote em `require` do `composer.json`. Toda leitura passa por uma interface própria,
  `App\Contracts\SpreadsheetReader`.
- **Rationale**: leitura em fluxo, linha a linha, com memória constante, o que atende 10.000
  linhas em menos de 60 s (SC-004) e o limite de 50 MB. Nenhum código novo entra no projeto: a
  biblioteca já é baixada hoje. Declará-la evita depender de uma dependência transitiva que o
  Filament pode trocar. A interface cumpre o Princípio VI (fonte externa atrás de interface da
  aplicação).
- **Alternatives considered**: `maatwebsite/excel` (traz PhpSpreadsheet, carrega a planilha
  inteira em memória, dependência nova de fato); `league/csv` (também já no lock, mas não lê
  .xlsx); `fgetcsv` nativo mais leitura manual de .xlsx (reinventa a biblioteca).
- **Aprovação**: declaração em `composer.json` aprovada pelo responsável em 2026-10-08.
- **[verificar no Boost]**: opções do leitor CSV (delimitador, codificação) e do leitor XLSX
  (datas devolvidas como `DateTimeImmutable`, não como texto formatado).

## R3. CSV do ERP: delimitador e codificação (FR-011f)

- **Decision**: detectar o delimitador pela linha de cabeçalho (o que ocorrer mais entre `;` e
  `,`). Detectar a codificação pelo conteúdo: se o arquivo é UTF-8 válido, lê-se como UTF-8 (com
  ou sem BOM); caso contrário, converte-se de Windows-1252. O texto é sempre gravado em UTF-8.
- **Rationale**: os relatórios reais vêm com `;` e com acentos fora de UTF-8. Windows-1252 é
  superconjunto prático de ISO-8859-1 para texto em português, então uma única conversão cobre os
  dois casos.
- **Alternatives considered**: pedir a codificação ao Operador (atrito sem ganho); assumir sempre
  Windows-1252 (quebra arquivos salvos pelo Excel como "CSV UTF-8").

## R4. Interpretação de valores (FR-010c, FR-010e, FR-011j)

- **Decision**: uma classe pura, `MoneyParser`, converte texto, inteiro ou decimal em centavos
  inteiros ou devolve um motivo de erro. Passos para texto: remover espaços, `R$` e espaços
  não separáveis; aplicar a regra de separadores da spec; aceitar parte inteira vazia (`.8` = 80
  centavos); recusar mais de duas casas decimais. Célula numérica nativa: multiplicar por 100,
  arredondar e aceitar somente se a diferença para o valor original for menor que 0,000001.
- **Rationale**: dinheiro em centavos inteiros (Princípio IV). A regra precisa de testes de
  fronteira exaustivos (Princípio III), o que só é barato em uma classe sem dependências.
- **Alternatives considered**: `NumberFormatter` do intl (depende de localidade e não cobre a
  regra mista da spec); `bcmath` (desnecessário para duas casas).

## R5. Interpretação de datas (FR-010d, FR-010e)

- **Decision**: uma classe pura, `DateParser`, aceita `dd-MON-yy` com mês em inglês (JAN a DEC,
  sem diferenciar maiúsculas), `dd/mm/aaaa` e célula de data nativa. Ano de dois dígitos vira
  `2000 + aa`. A data é conferida com `checkdate`. O resultado é uma data sem hora.
- **Rationale**: são os dois formatos reais (ERP e ELO). Data sem hora evita deslocamento de dia
  por fuso.
- **Alternatives considered**: `Carbon::parse` livre (aceita formatos ambíguos como 05/31/2026 e
  trocaria dia por mês em silêncio).

## R6. Ordem das regras por linha

- **Decision**: para cada linha de dados: (1) linha totalmente em branco é pulada; (2) o valor é
  interpretado, e valor ilegível é erro; (3) valor zero ou negativo faz a linha ser ignorada, sem
  validar os demais campos; (4) os demais campos são validados; (5) linhas válidas são comparadas
  com as outras sessões do período; (6) o que sobra é importado.
- **Rationale**: a spec diz que linhas de valor zero ou negativo são puladas "sem erro". No
  relatório do ERP elas são ruído (obrigações quitadas por desconto), e recusar o arquivo por um
  campo vazio em uma linha que seria descartada de qualquer modo só geraria retrabalho.
- **Alternatives considered**: validar tudo antes de ignorar (mais rígido, recusa arquivos reais
  por linhas irrelevantes).

## R7. Processamento do envio

- **Decision**: o envio cria uma **tentativa de importação** e despacha um job na fila. O job lê o
  arquivo duas vezes: a primeira valida tudo e conta; a segunda, só se não houve erro, grava o
  arquivo e os lançamentos em uma transação, em lotes de 500. O cartão acompanha a tentativa por
  consulta periódica (`wire:poll`) enquanto ela não termina.
- **Rationale**: Princípio VI exige fila com progresso e validação completa antes de gravar. Duas
  passadas mantêm a memória constante e garantem tudo ou nada sem guardar 10.000 linhas na
  memória.
- **Alternatives considered**: validar dentro da requisição do Livewire (estoura o tempo em
  arquivos grandes e contraria o Princípio VI); gravar em tabela de preparação e promover depois
  (mais tabelas, sem ganho neste volume).

## R8. Divergência de período (FR-020 a FR-022)

- **Decision**: a validação conta as linhas fora do mês/ano e guarda a menor e a maior data. Se
  houver divergência, a tentativa para em "aguardando confirmação" sem gravar lançamentos.
  "Confirmar mesmo assim" despacha a gravação; "Cancelar" descarta. Tentativas aguardando
  confirmação são canceladas quando quem as enviou abre o painel de novo, quando outro arquivo é
  enviado ao mesmo cartão e, por segurança, por um comando agendado depois de 30 minutos. Abrir o
  painel não cancela a confirmação pendente de outro operador.
- **Rationale**: "sair da tela" não é detectável com segurança no servidor; cancelar na próxima
  abertura do painel produz o mesmo resultado visível (o cartão volta ao estado anterior).
- **Alternatives considered**: gravar e desfazer se não confirmar (deixa dado parcial visível,
  contra FR-017).

## R9. Linhas já existentes em outra sessão do período (FR-003b a FR-003d)

- **Decision**: cada lançamento guarda uma **chave de identidade**. Pagamento: unidade +
  OBRIGACAO + COD_OPERACAO + CD_MOVIMENTO_CONTA. Autorização: hash SHA-256 de MAP_SOLICITACAO,
  MAP_FORNECEDOR, VALOR (em centavos) e MAP_DATA_AUTORIZACAO, normalizados (sem espaços nas pontas,
  maiúsculas). Na gravação, o sistema conta, por chave, quantos lançamentos ativos existem nas
  outras sessões do mesmo período e importa apenas as ocorrências do arquivo que excedem essa
  contagem.
- **Rationale**: atende a comparação por quantidade pedida na spec com uma consulta agregada no
  banco (Princípio IV) e um índice simples.
- **Alternatives considered**: comparar linha a linha em PHP (carrega coleções, contra o
  Princípio IV); restrição única no banco (impediria as linhas idênticas legítimas de FR-010f).

## R10. Substituição de arquivo e retenção (FR-015, FR-030)

- **Decision**: toda substituição marca o arquivo anterior como "substituído" e mantém arquivo e
  lançamentos, tenha a sessão sido processada ou não. Só a exclusão da sessão inteira apaga
  arquivos. Um índice único parcial garante no máximo um arquivo ativo por sessão e cartão.
- **Rationale**: o Princípio VII manda reter arquivos e linhas importadas; a única exceção é a
  exclusão de sessão nunca processada. Uma regra única é mais simples de testar do que duas.
- **Alternatives considered**: apagar o arquivo anterior em sessões nunca processadas (economiza
  disco, mas cria um segundo caminho de exclusão sem necessidade).

## R11. Mesmo arquivo enviado de novo (FR-016)

- **Decision**: guardar o SHA-256 do arquivo. Reenvio do mesmo conteúdo para o mesmo cartão não
  cria nada e informa que o arquivo já está carregado. O mesmo conteúdo no outro cartão de
  pagamentos da sessão é recusado.
- **Rationale**: idempotência exigida pelo Princípio VI e pela spec, sem reprocessar.
- **Alternatives considered**: comparar por nome do arquivo (o ERP gera nomes iguais para
  conteúdos diferentes).

## R12. Execução, travamento e dependência do Módulo 2

- **Decision**: a ação "Executar" abre uma transação, trava a linha da sessão (`lockForUpdate`),
  confere situação e os três arquivos ativos, muda para "Em processamento", grava a auditoria e
  despacha o job após o commit. O job chama a interface `App\Contracts\ReconciliationEngine`.
  Enquanto o Módulo 2 não fornecer a implementação, a chave de configuração
  `conciliation.engine_enabled` fica desligada e o botão aparece indisponível com a explicação.
  Os testes usam um motor falso.
- **Rationale**: entrega o controle de estado completo e testado sem marcar sessões como
  "Processada" sem resultado, o que as tornaria impossíveis de excluir. O travamento por linha e a
  conferência de situação resolvem execução dupla e ações em outra aba (FR-025, FR-031).
- **Alternatives considered**: motor vazio que conclui sem fazer nada (gera sessões "Processada"
  falsas); travas em cache (dependem do driver de cache e não participam da transação).

## R13. Reabertura (FR-028, FR-028a)

- **Decision**: a ação consulta a interface `App\Contracts\ReconciliationResultInspector`, que
  devolve a quantidade de decisões manuais e de vínculos com outras sessões. A implementação
  padrão deste módulo devolve zero; os Módulos 2 a 4 a substituem.
- **Rationale**: este módulo não conhece as tabelas de resultado. A interface registra a regra
  agora e a deixa testável com um falso.
- **Alternatives considered**: adiar FR-028a para o Módulo 2 (a regra ficaria sem dono e sem
  teste).

## R14. Trilha de auditoria (Princípio VII, FR-035 a FR-037)

- **Decision**: tabela única `audit_logs`, usada por todos os módulos, com usuário, data e hora em
  UTC, ação (Enum), entidade afetada (tipo e id, sem chave estrangeira para sobreviver à exclusão),
  rótulo da entidade e os dados antes e depois em JSON. O modelo recusa atualização e exclusão; no
  PostgreSQL, um gatilho recusa `UPDATE` e `DELETE` na tabela. A gravação ocorre na mesma
  transação da mudança.
- **Rationale**: é o requisito inegociável da constituição. O gatilho protege contra caminhos que
  não passam pelo modelo.
- **Alternatives considered**: pacote de auditoria de terceiros (dependência nova, e nenhum
  garante "somente acréscimo" no banco); log em arquivo (não consultável, não transacional).

## R15. Papéis e permissões (Princípio VIII, FR-038, FR-039)

- **Decision**: coluna `role` em `users` com o Enum `UserRole` (Administrador, Operador). Uma
  policy de sessão libera todas as ações deste módulo aos dois papéis. Um gate libera o histórico
  de exclusões e reaberturas apenas ao Administrador.
- **Rationale**: este módulo não precisa de permissões granulares; elas chegam com os Módulos 5 e
  6. Autorizar por policy desde já evita comparar papel em tela.
- **Auto-registro e exclusão de conta**: aprovado pelo responsável em 2026-10-08 desligar
  `Features::registration()` (Princípio VIII) e `Features::accountDeletion()` (Princípio VII,
  usuários não são apagados). Os testes do Jetstream para esses recursos já se pulam quando o
  recurso está desligado. Usuários passam a ser criados pelo comando
  `conciliation:create-administrator` e, em desenvolvimento, por um seeder.
- **Jetstream Teams**: continua ligado, sem uso para unidades ou papéis. Removê-lo é limpeza, fora
  deste plano.

## R16. Banco de dados em desenvolvimento e testes

- **Decision**: migrações portáteis, escritas para PostgreSQL e executáveis em SQLite: JSON pelo
  tipo `json`/`jsonb` do Laravel, índice único parcial por `DB::statement` (aceito pelos dois), e
  o gatilho de auditoria criado somente quando o driver é `pgsql`. A suíte continua em SQLite em
  memória, como hoje.
- **Rationale**: não muda a infraestrutura de testes sem decisão do responsável
  (TODO(DEV_DATABASE) da constituição).
- **Risco**: `lockForUpdate` não tem efeito em SQLite, então a concorrência real (dois envios no
  mesmo cartão) só é provada pelo índice único parcial. Recomenda-se rodar a suíte também em
  PostgreSQL antes da entrega.
- **Alternatives considered**: mover os testes para PostgreSQL agora (mais fiel, mas depende de
  uma decisão ainda aberta).

## R17. Fila e Horizon

- **Decision**: jobs comuns do Laravel, independentes de driver. O projeto está com
  `QUEUE_CONNECTION=database` e sem Horizon.
- **Rationale**: a constituição v2.1.0 (Princípio VI) define a fila em banco de dados como padrão
  e o Horizon como opcional. O volume é de algumas importações e uma conciliação por mês. Os jobs
  funcionam sem mudança se o Horizon for adotado. Trabalhos que falham ficam em `failed_jobs`.
- **Alternatives considered**: instalar o Horizon agora (exige Redis, um serviço a mais para
  operar, sem ganho neste volume).

## R18. Interface

- **Decision**: componentes Livewire 4 de página inteira no layout do Jetstream. Lista de sessões
  com Filament Tables (busca por período e filtro por situação gravados na URL). Modais e
  confirmações com Filament Actions; avisos com Filament Notifications. Envio de arquivo com
  `WithFileUploads`. Textos em `lang/pt_BR`.
- **Rationale**: Princípio II.
- **Limites de envio**: o padrão do Livewire para arquivo temporário é 12 MB e o PHP local está
  com `upload_max_filesize=2M` e `post_max_size=8M`. Para 50 MB é preciso publicar
  `config/livewire.php`, ajustar a regra de tamanho a partir de `config/conciliation.php` e
  configurar o PHP e o servidor web (ver [quickstart.md](./quickstart.md)).
- **Direção visual**: padrão do Jetstream (componentes Blade e layout existentes), definida pelo
  responsável em 2026-10-08.
- **[verificar no Boost]**: forma recomendada de componentes no Livewire 4 e a integração de
  Filament Tables/Actions 5 em componente Livewire fora de painel.

## R19. Fuso e período

- **Decision**: datas e horas gravadas em UTC; exibição e a regra "período posterior ao mês
  corrente" usam `conciliation.display_timezone` (padrão `America/Sao_Paulo`). O período é gravado
  como a data do primeiro dia do mês.
- **Rationale**: Princípio IV. Perto da meia-noite, o mês corrente em UTC difere do mês no Brasil.
- **Alternatives considered**: ano e mês em duas colunas (dificulta ordenar e filtrar por
  intervalo).

## R20. Relatório de erros (FR-018, FR-019)

- **Decision**: durante a validação, cada erro é escrito em um arquivo .xlsx no disco privado
  (linha, coluna, valor encontrado, motivo). Os 10 primeiros ficam também na tentativa, em JSON.
  O relatório é baixado por rota autorizada e apagado pelo comando agendado depois de 24 horas.
- **Rationale**: o relatório pode ter dezenas de milhares de linhas; gravá-lo no banco não tem
  uso além do download.
- **Alternatives considered**: tabela de erros (volume sem valor de consulta); relatório em .csv
  (abre com acentos errados no Excel se não houver BOM).

## R21. Cabeçalhos exigidos (FR-011k)

- **Decision**: o arquivo é recusado só pela falta de cabeçalho de coluna obrigatória por linha
  (6 em Pagamentos, 4 em Autorizações). As outras colunas do layout são lidas se existirem; as
  que faltarem ficam em `import_files.missing_columns` e viram aviso no cartão.
- **Rationale**: quatro nomes do layout de Autorizações foram deduzidos de imagens cortadas.
  Exigir todos os cabeçalhos faria um nome errado recusar toda planilha real. O ERP também pode
  deixar de exportar uma coluna que o sistema não usa.
- **Alternatives considered**: exigir todos os cabeçalhos (frágil); não avisar sobre coluna
  ausente (esconderia, por exemplo, a falta da forma de pagamento).

## R22. Processamento interrompido (FR-027a)

- **Decision**: comandos agendados tratam como falha o envio parado em fila, validação ou gravação
  além de `stale.attempt_minutes` e a sessão parada em "Em processamento" além de
  `stale.processing_minutes`, descartando o resultado parcial.
- **Rationale**: se o processo da fila morre, o método de falha do job não roda e a sessão ficaria
  travada para sempre.
- **Alternatives considered**: botão manual de destravar (exige alguém perceber o problema).

## R23. Conferência no Laravel Boost e no código instalado (T006)

- **OpenSpout 4.32**: o leitor CSV aceita `FIELD_DELIMITER` e `ENCODING` (converte para UTF-8); o
  leitor XLSX com `SHOULD_FORMAT_DATES = false` devolve células de data como `DateTimeImmutable`,
  desde que a célula tenha formato de data. `SHOULD_PRESERVE_EMPTY_ROWS = true` mantém o número
  da linha igual ao do arquivo. Confirmado no código em `vendor/openspout` e pelos testes.
- **Livewire 4.2**: páginas são registradas com `Route::livewire()`. O projeto usa componentes de
  classe (`make:livewire --class`) em `app/Livewire`, com a view em `resources/views/livewire`.
  O layout padrão é `layouts::app`, o mesmo do Jetstream. A variável `$slots` é reservada nas
  views de componente; por isso o painel usa `$importSlots`. Confirmado no `search-docs`.
- **Filament 5.3 fora de painel**: o componente implementa `HasTable`, `HasActions` e
  `HasSchemas` com os traits `InteractsWith*`; a view chama `{{ $this->table }}` e
  `<x-filament-actions::modals />`; o layout recebe `@filamentStyles` e `@filamentScripts`; o
  `app.css` importa os estilos dos pacotes. Busca e filtros vão para a URL redeclarando
  `$tableSearch` e `$tableFilters` com `#[Url]`, com a mesma assinatura dos traits. O Filament
  não está no índice do `search-docs`; conferido no código e na documentação em `vendor/filament`.
- **Limite de envio**: `config/livewire.php` foi publicado e a regra do arquivo temporário lê
  `CONCILIATION_UPLOAD_MAX_SIZE_MB`.

## R24. Decisões tomadas durante a implementação

- **Telas no padrão do Jetstream**: os modais de nova sessão e de confirmação usam os componentes
  Blade do Jetstream (`x-dialog-modal`, `x-confirmation-modal`) e os avisos usam o `x-banner`. As
  listas usam Filament Tables.
- **Menu sem time**: o menu do Jetstream supunha que todo usuário tem um time atual. Como os
  usuários agora são criados sem time, o seletor de times só aparece para quem tem um.
- **Validação e erros desde a US1**: o validador já nasce com a coleta de erros e a contagem de
  datas fora do período; as US2 e US3 acrescentaram apenas telas, ações e testes.
- **Tipo e tamanho recusados antes da fila**: arquivo de tipo não aceito ou acima do limite é
  recusado na hora, com mensagem no cartão, sem criar tentativa.

## R25. Achados do teste com os arquivos reais de julho/2026

- **Pagamentos (ERP)**: os dois CSVs entraram sem ajuste. Saúde: 3.461 lançamentos e 47 linhas
  ignoradas por valor; Social: 172 lançamentos. Todos os identificadores da Saúde são distintos.
- **Autorizações (ELO)**: arquivo de 10 MB com 136 autorizações. A coluna VALOR é a fórmula
  `IF(VALUE(K2)<=0,"",VALUE(K2))` arrastada até a linha 1.048.576, e o arquivo traz ainda uma aba
  de gráficos e uma tabela dinâmica. Dois defeitos apareceram: a fórmula era lida como texto, o
  que tornava inválida a coluna VALOR de toda linha, e o sistema percorria o milhão de linhas.
- **Decision**: ler células com fórmula pelo valor calculado e encerrar a leitura depois de 10.000
  linhas em branco seguidas (`upload.blank_rows_limit`). A validação desse arquivo caiu de mais
  de dez minutos, sem terminar, para cerca de um segundo.
- **Alternatives considered**: ler todas as linhas com indicador de progresso (cerca de 80
  segundos por passada para 136 autorizações); confiar na dimensão declarada pela aba (ela
  declara o milhão de linhas).
- **Limite de envio**: o PHP de linha de comando aceita 2 MB por padrão; sem ajuste, a planilha
  do ELO nem chega ao servidor.

## R26. Roteiro manual no navegador (T079)

Executado em 2026-10-08 com os arquivos reais de julho/2026: carga dos três cartões, planilha no
cartão errado, mesmo arquivo nos dois cartões de pagamento, alerta de datas fora do período com
cancelamento, exclusão de sessão e consulta ao histórico. Execução da conciliação e reabertura
não foram exercitadas na tela, porque dependem do motor do Módulo 2.

Defeitos encontrados e corrigidos, nenhum deles detectável pelos testes automatizados:

- **Modal atrás do fundo escuro**: o componente de modal do Jetstream dependia da classe
  `transform`, que no Tailwind 4 não cria mais uma camada própria. O painel ganhou `relative`.
- **Tabelas em modo escuro**: os estilos escuros do Filament seguiam o sistema operacional. A
  variante `dark` passou a valer só sob uma classe `.dark`, que a aplicação não usa.
- **Mensagem de envio em inglês**: faltava `lang/pt_BR/validation.php`.
- **Mensagem de colunas ausentes**: repetia a mesma lista; agora informa qual planilha o cartão
  recebe.

## Pendências que dependem do responsável

| # | Assunto | Efeito no plano |
|---|---------|-----------------|
| 2 | PostgreSQL nos testes (TODO(DEV_DATABASE)) | Risco de concorrência não provada |
| 4 | Unicidade de OBRIGACAO + COD_OPERACAO + CD_MOVIMENTO_CONTA | Suposição da spec |
| 5 | Grafia de quatro cabeçalhos do ELO | Não bloqueia (R21) |
| 6 | Backup fora do servidor da pasta de arquivos | Exigido pelo Princípio VI; depende do servidor de produção |
| 7 | Visibilidade das linhas de folha de pagamento | Hoje todo usuário as vê |
