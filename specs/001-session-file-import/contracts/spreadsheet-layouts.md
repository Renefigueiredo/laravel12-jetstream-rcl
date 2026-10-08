# Contract: Layouts das planilhas

Formato dos arquivos que o sistema recebe. É o contrato com o ERP (pagamentos) e com o ELO
(autorizações).

## Regras comuns

- Tipos aceitos: `.xlsx` e `.csv`. Em `.xlsx`, só a primeira aba é lida.
- `.csv`: delimitador `;` ou `,`, detectado no cabeçalho; codificação UTF-8 (com ou sem BOM) ou
  Windows-1252, detectada pelo conteúdo.
- A primeira linha é o cabeçalho. A comparação de nomes ignora maiúsculas, acentos e espaços nas
  pontas. A ordem das colunas é livre e colunas extras são ignoradas.
- Só os cabeçalhos das colunas marcadas "obrigatória na linha" são exigidos; a falta de um deles
  recusa o arquivo. As outras colunas do layout são lidas quando presentes. Quando faltam, o
  arquivo é aceito, o campo fica vazio e o nome da coluna é guardado em
  `import_files.missing_columns` para aviso no cartão.
- Linhas totalmente em branco são puladas.
- Espaços no início e no fim de cada campo são desconsiderados.
- Tamanho máximo: `conciliation.upload.max_size_mb` (50 MB).

## Valores

| Entrada | Centavos | Regra |
|---------|----------|-------|
| `1.234,56` | 123456 | dois separadores: o último é o decimal |
| `1,234.56` | 123456 | idem |
| `1234.56`, `1234,56` | 123456 | um separador seguido de 1 ou 2 dígitos: decimal |
| `4.1` | 410 | idem |
| `.8`, `,8` | 80 | parte inteira vazia |
| `1.234`, `1,234` | 123400 | um separador seguido de 3 dígitos: milhar |
| `1234` | 123400 | sem separador: reais inteiros |
| `R$ 3.895,73` | 389573 | símbolo e espaços desconsiderados |
| célula numérica `3895.73` | 389573 | lida pelo valor |
| `0`, `-10,00` | (linha ignorada) | valor zero ou negativo |
| `1,2345`, `1.2.3`, `abc`, vazio | erro | mais de duas casas, formato inválido, texto ou ausência |

## Datas

| Entrada | Data | Regra |
|---------|------|-------|
| `31-JUL-26` | 2026-07-31 | dia, mês em inglês com três letras, ano com dois dígitos (20aa) |
| `01-dec-25` | 2025-12-01 | sem diferenciar maiúsculas |
| `31/05/2026` | 2026-05-31 | dia/mês/ano |
| célula de data | a própria data | lida pelo valor |
| `2026-05-31`, `05/31/2026`, `31/02/2026`, `31-AGO-26` | erro | formato não aceito ou data inexistente |

## Layout de Pagamentos (cartões Unidade Social e Unidade de Saúde)

Cabeçalhos do layout (24): `TIPO`, `COD_OPERACAO`, `NM_OPERACAO`,
`SUM_VL_OBRIGACAO_POR_COD_OPERACAO`, `CD_COMANDO`, `DT_LIQUIDACAO`, `CEDENTE`, `ESPECIE`,
`OBRIGACAO`, `CD_TIPO_TRANSACAO`, `DOC_GERADOR`, `DT_EMISSAO`, `DT_VENCIMENTO`,
`PREV_LIQUIIDACAO`, `VL_SALDO_OBRIGACAO`, `VL_RECEBIDO`, `VL_OBRIGACAO`,
`VL_DESCONTO_CONCEDIDO`, `VL_JUROS_MORA`, `SITUACAO`, `CD_MOVIMENTO_CONTA`, `CD_CONTA_CORRENTE`,
`NM_CONTA_CORRENTE`, `DS_AUDIT`.

`PREV_LIQUIIDACAO` é a grafia do ERP, com dois "I".

| Coluna | Obrigatória na linha | Tipo | Campo |
|--------|----------------------|------|-------|
| `COD_OPERACAO` | sim | texto | `operation_code` |
| `DT_LIQUIDACAO` | sim | data | `paid_on` (data comparada com o período) |
| `CEDENTE` | sim | texto | `supplier_name` |
| `OBRIGACAO` | sim | texto | `obligation_number` |
| `VL_RECEBIDO` | sim | valor | `amount_cents` (decide se a linha é ignorada) |
| `VL_OBRIGACAO` | sim | valor | `obligation_amount_cents` |
| `NM_OPERACAO` | não | texto | `operation_name` |
| `ESPECIE` | não | texto | `species` |
| `CD_TIPO_TRANSACAO` | não | texto | `transaction_type` |
| `DOC_GERADOR` | não | texto | `source_document` |
| `SITUACAO` | não | texto | `settlement_status` |
| `CD_MOVIMENTO_CONTA` | não | texto | `account_movement` |
| demais | não | texto | apenas em `raw` |

Identidade do lançamento: unidade (do cartão) + `OBRIGACAO` + `COD_OPERACAO` +
`CD_MOVIMENTO_CONTA`.

## Layout de Autorizações

Cabeçalhos do layout (11): `MAP_SOLICITACAO`, `MAP_SETOR_SOLICITANTE`,
`MAP_FUNCIONARIO_SOLICITANTE`, `MAP_DATA_AUTORIZACAO`, `MAP_APROVADOR`, `MAP_FORNECEDOR`,
`VALOR`, `MAP_FORMA_DE_PAGAMENTO`, `MAP_AFRAFEP_CARTAO`, `MAP_CONDICAO_DE_PAGAMENTO`,
`MAP_VALOR`.

Quatro nomes foram completados a partir de imagens cortadas: `MAP_SETOR_SOLICITANTE`,
`MAP_FUNCIONARIO_SOLICITANTE`, `MAP_FORMA_DE_PAGAMENTO`, `MAP_CONDICAO_DE_PAGAMENTO`. Nenhum é
obrigatório; se a grafia real for outra, o cartão avisa que a coluna não foi encontrada.

| Coluna | Obrigatória na linha | Tipo | Campo |
|--------|----------------------|------|-------|
| `MAP_SOLICITACAO` | sim | texto | `request` |
| `MAP_FORNECEDOR` | sim | texto | `supplier_name` |
| `VALOR` | sim | valor | `amount_cents` (decide se a linha é ignorada) |
| `MAP_DATA_AUTORIZACAO` | sim | data | `authorized_on` (data comparada com o período) |
| `MAP_FORMA_DE_PAGAMENTO` | não | texto | `payment_method` |
| `MAP_AFRAFEP_CARTAO` | não | texto | `card` |
| `MAP_CONDICAO_DE_PAGAMENTO` | não | texto | `payment_condition` |
| demais | não | texto | apenas em `raw` |

Identidade do lançamento: `MAP_SOLICITACAO` + `MAP_FORNECEDOR` + `VALOR` +
`MAP_DATA_AUTORIZACAO`.

## Motivos de recusa do arquivo

| Motivo | Quando |
|--------|--------|
| tipo não aceito | extensão diferente de `.xlsx` e `.csv` |
| acima do limite | tamanho maior que o configurado |
| coluna ausente | lista das colunas obrigatórias que faltam |
| sem lançamentos | só cabeçalho, vazio, ou todas as linhas ignoradas por valor |
| erros de linha | um registro por erro: linha, coluna, valor encontrado, motivo |
| mesmo arquivo no outro cartão de pagamentos | `sha256` igual ao do arquivo ativo do outro cartão |

Não é recusa: todas as linhas válidas já existirem em outra sessão do período (aceito com zero
lançamentos novos).

## Relatório de erros (.xlsx)

Colunas: `Linha`, `Coluna`, `Valor encontrado`, `Motivo`. Uma linha por erro, na ordem do arquivo.

## Planilhas modelo

`autorizacoes` em `.xlsx` e `pagamentos` em `.csv` com `;`, cada uma com o cabeçalho completo e
uma linha de exemplo fictícia.
