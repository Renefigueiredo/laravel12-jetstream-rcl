# Contract: Planilha de códigos excluídos

Formato do arquivo aceito pela importação. A leitura é por posição de coluna, não por nome.

## Arquivo

| Regra | Valor |
|-------|-------|
| Tipos | `.csv`, `.xlsx` |
| Tamanho máximo | 5 MB (`conciliation.excluded_codes.max_size_mb`) |
| Linhas de dados, no máximo | 10.000 (`conciliation.excluded_codes.max_rows`) |
| `.csv`: separador | `;` ou `,`, detectado pela primeira linha |
| `.csv`: codificação | UTF-8 ou Windows-1252 |
| `.xlsx` | primeira aba |

## Colunas

| Posição | Conteúdo | Regras |
|---------|----------|--------|
| 1 | código de operação | obrigatório; 1 a 20 caracteres; só letras e dígitos; espaços nas pontas são removidos |
| 2 | descrição | opcional; até 255 caracteres; espaços nas pontas são removidos |
| 3 em diante | qualquer coisa | desconsiderado |

## Linhas

| Caso | Tratamento |
|------|------------|
| Linha 1 com a primeira célula `COD_OPERACAO` (sem diferenciar maiúsculas) | cabeçalho; pulada |
| Linha 1 com outro conteúdo | lida como código |
| Colunas 1 e 2 vazias | pulada; não é erro |
| Código numérico na planilha (20150652) | lido como o texto "20150652" |
| Código com zeros à esquerda ("00123") | preservado; diferente de "123" |
| Código vazio com descrição preenchida | erro na coluna `COD_OPERACAO`: "Código em branco" |
| Código com caractere não aceito ou com mais de 20 caracteres | erro na coluna `COD_OPERACAO`: "Código inválido: use de 1 a 20 letras sem acento ou dígitos" |
| Descrição com mais de 255 caracteres | erro na coluna `DESCRICAO`: "Descrição com mais de 255 caracteres" |
| Descrição na primeira coluna (colunas invertidas) | falha como código inválido; a importação é recusada |
| Código repetido no arquivo | a primeira ocorrência vale; as demais contam como ignoradas |
| Código já cadastrado | ignorado; a descrição cadastrada não muda |

## Resultado

| Situação do arquivo | Resultado |
|---------------------|-----------|
| Todas as linhas válidas | grava os códigos novos; informa adicionados e ignorados |
| Ao menos uma linha inválida | nada é gravado; lista todos os erros, cada um com linha, coluna e motivo |
| Nenhum código (vazio ou só cabeçalho) | recusado: "O arquivo não tem códigos para importar." |
| Mais de 10.000 linhas de dados | recusado, informando o limite; sem lista de erros por linha |
| Todos os códigos já cadastrados | concluído com zero adicionados |

O número da linha informado é o da linha no arquivo, contando o cabeçalho. A coluna é informada
pelo nome do modelo (`COD_OPERACAO` ou `DESCRICAO`), mesmo quando o arquivo não tem cabeçalho. Uma
linha com dois problemas gera dois erros.

## Exemplo

```text
COD_OPERACAO;DESCRICAO
20150652;Folha de pagamento
11018953;Convênio de reciprocidade
```
