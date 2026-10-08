# Research: Motor de Conciliação e Tratamento de Divergências (Módulo 2)

Decisões da Fase 0. Cada item traz a decisão, o motivo e o que foi descartado. Decisões dos
módulos anteriores são citadas como `001/Rn` e `002/Rn`.

## R1. Stack e reaproveitamento

- **Decision**: nenhuma dependência nova. O motor é PHP puro sobre o que já existe: sessões,
  lançamentos, fila em banco, `AuditRecorder`, `ActionRefusedException`, permissões por usuário
  (`002/R2`), fotografia dos códigos excluídos (`002/R8`), Livewire 4 e Filament Tables/Actions.
- **Rationale**: Princípio V. As funções de texto necessárias (`levenshtein`, `similar_text`,
  `iconv`/`Str::ascii`) são nativas do PHP e do Laravel.
- **Alternatives considered**: extensão `pg_trgm` do PostgreSQL para semelhança de nomes. Daria
  resultado diferente em SQLite (testes) e PostgreSQL (produção), o que fere o Princípio IX.

## R2. Onde o motor se encaixa

- **Decision**: `DatabaseReconciliationEngine` implementa `ReconciliationEngine` (`001`) e é
  registrado em `AppServiceProvider`. `conciliation.engine_enabled` passa a ter `true` como padrão.
  O job `RunReconciliation` e a ação `ExecuteReconciliation` do Módulo 1 não mudam.
- **Mudança no Módulo 1**: `ReopenSession` passa a chamar `discardResult()` na mesma transação da
  reabertura. Motivo: com vínculos entre sessões, um resultado "vencido" de agosto continuaria
  quitando autorizações de julho até a nova execução. Descartar na reabertura mantém os saldos
  corretos o tempo todo. A reabertura continua bloqueada enquanto houver decisão manual.
- **`ReconciliationResultInspector`**: implementado por `ReconciliationDecisionInspector`, que
  conta (a) vínculos com decisão humana cujo pagamento é da sessão e (b) vínculos de qualquer
  origem em que a autorização é da sessão e o pagamento é de outra sessão (FR-031e, FR-042).

## R3. Execução: cálculo em memória, gravação em uma transação

- **Decision**: a execução tem três tempos. (1) Leitura: fotografia dos códigos excluídos,
  parâmetros, pagamentos ativos da sessão, autorizações da sessão e autorizações em aberto de
  sessões anteriores. (2) Cálculo em memória, por uma classe pura (`Matcher`), informando o
  andamento. (3) Gravação de vínculos, sugestões, lançamentos pulados e totais em uma única
  transação.
- **Rationale**: o andamento precisa ser visível durante o cálculo, e uma transação longa o
  esconderia. Gravar tudo no fim garante FR-007: se algo falha, nada foi gravado.
- **Uma execução por vez**: o motor roda sob uma trava atômica global
  (`Cache::lock('conciliation:engine')`). A execução espera a trava por até 10 minutos e, se não
  conseguir, falha com mensagem clara, devolvendo a sessão a aberta. Duas sessões executando ao mesmo tempo poderiam vincular
  pagamentos diferentes à mesma autorização antiga.
- **Mudança de estado durante o cálculo**: na gravação, cada autorização de sessão anterior é
  travada e o saldo é conferido com o que foi lido. Se mudou (outro usuário agiu), o par não é
  vinculado e vira sugestão "Dúbio".
- **Alternatives considered**: gravar por lotes durante o cálculo (deixaria resultado parcial em
  caso de falha).

## R4. Registro da execução (FR-006, FR-004c, Princípio IX)

- **Sessão nunca processada**: uma sessão cuja única execução falhou continua podendo ser
  excluída (Módulo 1). `DeleteSession` passa a apagar as execuções dessa sessão antes de apagá-la;
  o registro de auditoria da exclusão permanece.

- **Decision**: tabela `reconciliation_runs`, uma linha por execução, com situação (`Running`,
  `Completed`, `Failed`, `Discarded`), quem solicitou, início e fim, todos os parâmetros usados, a
  lista de códigos excluídos vigente e os totais por classificação. As linhas nunca são apagadas;
  ao descartar um resultado, apagam-se os filhos (vínculos automáticos, sugestões, pulados) e a
  execução fica `Discarded`, com parâmetros e totais preservados.
- **Rationale**: a constituição exige que cada execução registre os parâmetros. Manter as
  execuções descartadas dá o histórico sem custo.
- **Quantidade excluída por código**: contada no banco a partir de `reconciliation_skips`, e
  copiada para os totais da execução ao terminar (para sobreviver ao descarte).

## R5. Parâmetros

- **Decision**: tolerância (valor fixo e percentual) e teto de acréscimo ficam em banco, na tabela
  `reconciliation_settings` (linha única), porque o Administrador os altera pela tela (FR-034,
  FR-028b). Limites de nota, limite de fornecedor para Parcial/Excedente, janela de meses e
  quantidade de candidatos guardados ficam em `config/conciliation.php` (`engine.*`), lidos de
  variáveis de ambiente.
- **Valores iniciais**: tolerância R$ 0,50 sem percentual; teto de acréscimo 10%; nota automática
  90; nota mínima de sugestão 60; fornecedor mínimo para Parcial/Excedente 90; janela 3 meses; 5
  candidatos por autorização.
- **Percentuais**: guardados como inteiros em pontos-base (1% = 100), para não usar ponto
  flutuante (Princípio IV).
- **Tolerância efetiva**: o maior entre o valor fixo e o percentual aplicado ao saldo comparado.
- **Permissão**: novo caso `UserPermission::ConfigureTolerance` e gate `configure-tolerance`.

## R6. Compatibilidade do fornecedor (FR-009, FR-010)

- **Decision**: `SupplierNameNormalizer` deixa o nome em maiúsculas sem acentos, troca pontuação
  por espaço, remove números de documento (sequências de 8 ou mais dígitos, com ou sem pontos,
  barras e hífen), remove sufixos societários como palavra inteira (LTDA, ME, MEI, EPP, EIRELI,
  SA, S A, CIA, SS) e junta espaços. `SupplierSimilarity` devolve de 0 a 100: o maior entre
  (a) a semelhança de Levenshtein normalizada entre os nomes inteiros e (b) a contenção de
  palavras, isto é, a fração das palavras do nome mais curto que aparecem no mais longo, valendo
  só quando o nome mais curto tem ao menos duas palavras. Nome de uma palavra só ("SILVA") usa
  apenas o Levenshtein, para não casar com qualquer fornecedor que a contenha.
- **Rationale**: nos arquivos reais, o nome do pagamento costuma ser o nome da autorização com
  complemento, ou com o CNPJ na frente ("60.499.628 LILIAN KARLA GUILHERME SANTOS"). A contenção
  cobre esses casos; o Levenshtein cobre erros de digitação. Só regras de texto e aritmética.
- **Alternatives considered**: `similar_text` (resultado depende da ordem dos argumentos);
  comparar só o prefixo (falha quando o CNPJ vem na frente).
- **Risco**: os limites 90 e 60 precisam ser calibrados nos arquivos reais de julho/2026 durante a
  implementação. O teste de calibração roda localmente e os arquivos não entram no repositório.

## R7. Compatibilidade do valor e nota (FR-011, FR-012, FR-013)

- **Decision**: a compatibilidade do valor é 100 quando a diferença cabe na tolerância efetiva;
  fora dela, `100 × (1 − diferença / saldo)`, com piso em 0. A nota do par é o **menor** dos dois
  eixos.
- **Rationale**: cumpre a suposição da spec (dentro da tolerância, a nota é a do fornecedor) e é
  explicável a um auditor em uma frase. Usa os dois eixos, como exige o Princípio IX.
- **Classificação**: nota ≥ 90 e valor na tolerância → candidato a automático; nota de 60 a 89 e
  valor na tolerância → Dúbio; fornecedor ≥ 90 e valor fora da tolerância → Parcial (pago a
  menos) ou Excedente (pago a mais); o resto não gera sugestão.
- **Alternatives considered**: média ponderada dos eixos (um fornecedor medíocre com valor exato
  chegaria a 90).

## R8. Ordem do cruzamento (determinismo, FR-005)

- **Decision**: o `Matcher` trabalha nesta ordem, sempre com desempate final por data do
  pagamento e depois por identificador, para que a mesma entrada dê a mesma saída.
  1. Tirar os pagamentos excluídos por código e os duplicados de outro período.
  2. Montar os candidatos de cada autorização com um índice invertido por palavra do fornecedor e
     um índice por valor; só os candidatos recebem nota.
  3. Descartar pares bloqueados (rejeitados ou desvinculados antes, R11).
  4. Rodadas de vínculo exato: vincula o par em que o pagamento é o único melhor da autorização e
     a autorização é a única melhor do pagamento, com nota ≥ 90, valor na tolerância e data
     válida. Repete até não haver novo vínculo. Empate na melhor nota tenta o desempate pela forma
     de pagamento (FR-012a); persistindo, ninguém é vinculado.
  5. Parcelas (R9).
  6. Sugestões para o que sobrou, até o limite de candidatos por autorização.
- **Candidatos por índice**: evita comparar todos com todos. Com 10.000 lançamentos, a comparação
  completa passaria de dezenas de milhões de pares; o índice reduz a poucos por autorização
  (SC-002).

## R9. Parcelamento (FR-043 a FR-050)

- **Decision**: `PaymentConditionParser` devolve o número de parcelas previstas ou nulo: `Nx` →
  N; prazos separados por barra → um por prazo; "à vista" ou um prazo só → 1; outro texto → nulo.
  O vínculo automático de parcela acontece depois do vínculo exato, para autorizações com saldo:
  - valor de referência: `valor autorizado ÷ parcelas previstas` (FR-044) ou o valor de um
    pagamento já vinculado como "Ainda falta pagar" (FR-045);
  - o pagamento precisa ter esse valor dentro da tolerância, fornecedor ≥ 90, data válida, caber
    no saldo e não ser candidato de nenhuma outra autorização em aberto;
  - vários pagamentos para a mesma autorização entram em ordem de data, até o saldo (FR-049).
- **Nota da parcela** (Princípio IX): no vínculo de parcela, o eixo do valor compara o pagamento
  com o valor de referência, e não com o saldo inteiro. A nota continua sendo o menor dos dois
  eixos e precisa atingir o limite automático. Assim, todo vínculo automático, exato ou de
  parcela, acontece com nota igual ou superior ao limite, como a constituição exige.
- **Arredondamento**: a divisão é inteira em centavos; a tolerância absorve o centavo da última
  parcela (R$ 1.000,00 em 3 → 333,33; 333,34 cabe na tolerância).
- **Fora da janela**: a autorização com saldo e ao menos um vínculo é sempre lida (FR-047).

## R10. Saldo e situação da autorização (FR-019, FR-033, Princípio IX)

- **Decision**: tabela `authorization_states`, uma linha por autorização que já teve vínculo, com
  valor pago, descontos, diferença absorvida pela tolerância, saldo e situação. É sempre
  recalculada por `AuthorizationStateCalculator`, com agregações no banco, dentro da transação de
  qualquer mudança de vínculo e com a linha da autorização travada (`lockForUpdate`). Nenhuma
  tela ou ação escreve nela diretamente.
- **Fórmula**: `saldo = autorizado − Σ pagamentos vinculados − Σ descontos − Σ absorvido pela
  tolerância`, com piso em zero. Sem vínculo: Aberta. Saldo zero: Conciliada. Caso contrário:
  Parcial.
- **Absorvido pela tolerância**: quando um vínculo deixa um resto que cabe na tolerância da
  execução, o resto é gravado no próprio vínculo. Assim a situação não depende da tolerância
  vigente no futuro (uma mudança de parâmetro não altera resultado já produzido). Ao desvincular,
  o valor absorvido dos vínculos restantes é zerado se a autorização deixar de estar quitada.
- **Rationale**: as listas filtram e ordenam por situação e saldo; calcular por subconsulta em
  cada linha seria lento e repetiria a regra em vários lugares.
- **Duas ações simultâneas**: a trava na autorização serializa; a segunda ação lê o saldo já
  atualizado. Um pagamento só pode ter um vínculo, garantido por índice único.

## R11. Sugestões, rejeição e desvínculo (FR-025, FR-026, FR-030)

- **Decision**: `reconciliation_suggestions` guarda os candidatos de cada autorização (até 5),
  com nota, eixos, diferença, classificação, posição e situação (`Pending`, `Confirmed`,
  `Rejected`, `Superseded`). A tela mostra, por autorização, os pendentes de melhor posição.
  Rejeitar um promove o seguinte.
- **Pares bloqueados**: `reconciliation_pair_blocks` guarda, pela chave de identidade dos dois
  lançamentos, os pares rejeitados ou desvinculados. O motor nunca volta a sugeri-los nem a
  vinculá-los sozinho. Usa a chave de identidade, e não o id, porque uma planilha substituída
  gera lançamentos com ids novos.
- **Ao confirmar**: as outras sugestões do mesmo pagamento ficam `Superseded`; as da mesma
  autorização são reavaliadas contra o novo saldo (ou `Superseded`, se ela foi quitada).
- **Ao desvincular**: as sugestões `Superseded` da mesma autorização e do mesmo pagamento, na
  execução vigente, voltam a `Pending` quando as duas pontas estão livres e o par não está
  bloqueado. O par desvinculado ganha bloqueio e não volta.
- **Rejeição não bloqueia a reabertura**: não há vínculo; o bloqueio do par sobrevive à nova
  execução.

## R12. Vínculo e tratamento da diferença (FR-027, FR-028, FR-029)

- **Decision**: `reconciliation_links`, com índice único em `payment_entry_id`. Cada vínculo
  guarda origem (automático ou manual), se é parcela, nota e classificação do motor, tipo de
  diferença (exata, parcial, excedente), valor excedido, tratamento (`StillOwed`, `Discount`,
  `AcceptedSurcharge`, `Overpayment`), desconto, justificativa, o aviso de pago antes da
  autorização, quem decidiu e quando.
- **Desconto**: sempre o saldo restante no momento, gravado no vínculo. "Encerrar com desconto o
  saldo" de uma autorização Parcial grava o desconto no vínculo mais recente dela (FR-028c), e
  desvincular esse pagamento desfaz o desconto (FR-028e).
- **Categoria de justificativa** (Princípio VII): desconto e acréscimo aceito exigem uma
  categoria (`JustificationCategory`: desconto comercial, juros ou multa, frete, reajuste de
  preço, arredondamento, outro) e o texto. O resto absorvido pela tolerância é gravado com a
  categoria "arredondamento", sem texto, porque é uma baixa automática de centavos.
- **Tolerância nas decisões**: confirmação e vínculo manual usam os parâmetros da execução
  vigente da sessão do pagamento (`EngineParameters::fromRun()`), para que uma mudança de
  tolerância não altere o que a execução já produziu. Só o teto de acréscimo vem da configuração
  vigente no momento da decisão.
- **Acréscimo aceito**: permitido quando `valor excedido ≤ teto × valor autorizado`, com o teto
  vigente na decisão, que também fica gravado no vínculo.
- **Decisão humana**: todo vínculo com `decided_by` preenchido conta como decisão manual para a
  reabertura, mesmo que tenha nascido automático (por exemplo, parcela automática depois encerrada
  com desconto).

## R13. Autorização criada na conciliação (FR-032)

- **Decision**: a autorização criada é uma linha de `authorization_entries` sem arquivo de origem.
  A coluna `import_file_id` passa a aceitar nulo e entram `created_by` e
  `source_payment_entry_id`. Ela copia fornecedor, valor e data do pagamento, pertence à sessão do
  pagamento e nasce vinculada. Desvincular apaga a autorização criada; a auditoria guarda o
  registro.
- **Leitura pelo motor**: autorizações ativas são as de arquivo ativo mais as criadas na
  conciliação.
- **Alternatives considered**: tabela separada para autorizações criadas (dobraria todas as
  consultas e as chaves estrangeiras dos vínculos).

## R14. Lançamentos pulados (FR-004, FR-031c)

- **Decision**: `reconciliation_skips` registra, por execução, cada lançamento que ficou fora da
  comparação e o motivo: `ExcludedCode` (com o código) ou `DuplicateOfOtherPeriod` (com a sessão
  de origem). Duplicado é o lançamento cuja chave de identidade já existe em sessão processada de
  outro período.
- **Chave de identidade**: a da autorização é o hash de solicitação, fornecedor, valor e data da
  autorização (Módulo 1). A mesma compra repetida em outro mês tem data diferente e, portanto,
  não é duplicada; só o mesmo lançamento enviado em sessões de períodos diferentes é.
- **Rationale**: a marca é "por execução" (spec do Módulo 5); apagar as linhas no descarte devolve
  os pagamentos ao estado neutro sem tocar nos lançamentos importados.

## R15. Sessões anteriores (FR-031, FR-047)

- **Decision**: o motor lê autorizações com saldo de sessões **processadas** cujo período é
  anterior ao da sessão em execução e está dentro da janela de meses, mais as que já têm vínculo
  e saldo, qualquer que seja a idade. Só os pagamentos da sessão em execução são comparados.
- **Reabertura da sessão posterior**: o descarte apaga os vínculos automáticos que ela fez com
  autorizações antigas e recalcula o saldo delas (FR-031f).
- **Sessão complementar do mesmo período** (`001/R9`): é tratada como sessão do mesmo período; as
  autorizações em aberto das outras sessões do período também são lidas.

## R16. Pagamento anterior à autorização (FR-012b, FR-023a)

- **Decision**: o par cuja data de pagamento é anterior à data da autorização nunca entra no
  vínculo exato nem no de parcela. Vira sugestão, com a marca `paid_before_authorization`, que é
  copiada para o vínculo quando o Operador confirma. A aba própria lista sugestões e vínculos com
  a marca.

## R17. Cartão e forma de pagamento (FR-012a, FR-012c, FR-023b, FR-023c)

- **Decision**: `CardNumberExtractor` lê os quatro primeiros dígitos depois de `FATURA CARTAO` na
  espécie normalizada do pagamento ("FATURA CARTAO 7607 (7613)" → `7607`). O resultado é gravado
  em uma coluna nova, `payment_entries.card`, preenchida na importação e, para os pagamentos já
  importados, pela própria migração. A autorização já tem a coluna `card`.
- **Rationale**: com o cartão em coluna, o resumo por cartão, o filtro e a conferência da fatura
  são agregações e filtros no banco (Princípio IV), e não leitura de texto em PHP.
- **No motor**: (1) cartões identificados e diferentes impedem o vínculo exato e o de parcela; o
  par vira sugestão com a marca `card_mismatch`, copiada para o vínculo na confirmação. (2) No
  empate, vence o único candidato de mesmo cartão; persistindo, vence o único que combina na
  forma de pagamento (`PaymentMethodMatcher`: autorização cuja forma normalizada contém `CARTAO`
  com pagamento que tem cartão). Os trechos de texto ficam em `config/conciliation.php`.
- **Só um lado com cartão**: a regra de cartão diferente não se aplica (a autorização pode ter
  sido paga por boleto ou PIX).
- **Dados reais**: cinco cartões nas faturas de julho/2026 (0798, 4931, 5352, 7607 e 7222), 78
  linhas, uma fatura por cartão; quatro cartões nas autorizações.
- **Códigos excluídos**: valem para qualquer forma de pagamento, por decisão do responsável. Em
  julho, o código da Farmácia tira 6 linhas da fatura do cartão 0798.
- **Alternatives considered**: extrair o cartão na leitura, sem coluna (impede agregação e filtro
  no banco); tratar o número entre parênteses como outro cartão (não bate com a autorização).

## R18. Telas (Princípio II)

- **Decision**: uma página `Reconciliation\Show` em `/sessoes/{session}/conciliacao`, com o resumo
  da execução e abas na URL. Cada aba é um componente Livewire com uma tabela do Filament:
  Pendências, Fila de investigação, Conciliados, Pagos antes da autorização e Excluídos por
  código. A configuração de tolerância é outra página, `Reconciliation\Settings`. Decisões abrem
  modais do Filament (confirmação, tratamento da diferença, justificativa). Direção visual: padrão
  do Jetstream, já definida.
- **Lista de pendências**: é uma visão de banco, `reconciliation_pending_items`, que une as
  sugestões pendentes de melhor posição, as autorizações sem pagamento, as autorizações com
  saldo em aberto e os pagamentos sem autorização. A lista padrão deixa de fora os pagamentos
  sem autorização, que ficam na aba Fila de investigação e no filtro próprio. Assim o filtro "Todos", a busca e a paginação acontecem no banco, em uma consulta.
- **Alternatives considered**: tabela materializada de itens pendentes (exigiria sincronizar a
  cada ação); uma tabela por tipo sem "Todos" (não cumpre FR-022).
- **Painel da sessão** (`Sessions\Show`): ganha o resumo da execução e o atalho para a
  conciliação quando a sessão está processada.

## R19. Auditoria (Princípio VII, FR-038 a FR-040)

- **Decision**: novos casos em `AuditAction`: `ReconciliationCompleted`, `SuggestionConfirmed`,
  `SuggestionRejected`, `ManualLinkCreated`, `LinkRemoved`, `AuthorizationClosedWithDiscount`,
  `AuthorizationCreatedInReconciliation` e `ReconciliationSettingsChanged`. Todos na transação da
  mudança. O registro de desvínculo leva nota, classificação original e quem havia vinculado.
- **Vínculos automáticos**: sem registro individual; o registro `ReconciliationCompleted` leva os
  parâmetros e os totais da execução, em nome de quem solicitou.

## R20. Desempenho (SC-002)

- **Decision**: leitura com `select` só das colunas usadas e `toBase()`; índices por palavra e
  por valor (R8); gravação em lotes de `conciliation.upload.insert_chunk`. Um teste gera 10.000
  lançamentos e exige menos de 2 minutos.
- **Risco**: a medida em SQLite é otimista em relação ao PostgreSQL remoto; a gravação em lotes
  mantém o número de idas ao banco baixo.

## R21. Banco de dados em testes

- **Decision**: SQLite, como nos módulos anteriores. A visão de pendências usa só SQL padrão
  (`UNION ALL`, `NOT EXISTS`, subconsulta correlacionada), sem funções de janela específicas.
- **Risco**: travas de linha e a visão só se provam de fato em PostgreSQL. Continua aberta a
  pendência de rodar a suíte em PostgreSQL, que neste módulo passa a pesar mais.

## Pendências que dependem do responsável

1. **Rodar a suíte em PostgreSQL** antes de produção (R21).
2. **Calibrar os limites de nota** com os arquivos reais (R6).
3. **Empate entre compras idênticas**: duas autorizações iguais e dois pagamentos iguais ficam
   todos como Dúbio, pela regra de empate da spec. Se isso gerar muitos cliques nos dados reais,
   vale uma regra de desempate por data em uma evolução.
