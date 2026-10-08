# Contract: Regras do motor

O que o motor garante para as mesmas entradas e os mesmos parâmetros. Cada tabela vira caso de
teste de Unit do `Matcher` e de seus componentes.

## Parâmetros de uma execução

| Parâmetro | Origem | Valor inicial |
|-----------|--------|---------------|
| tolerância em valor | `reconciliation_settings.tolerance_cents` | R$ 0,50 |
| tolerância percentual | `reconciliation_settings.tolerance_basis_points` | nenhuma |
| teto de acréscimo | `reconciliation_settings.surcharge_cap_basis_points` | 10% |
| nota automática | `conciliation.engine.automatic_threshold` | 90 |
| nota mínima de sugestão | `conciliation.engine.suggestion_threshold` | 60 |
| fornecedor mínimo para Parcial/Excedente e parcela | `conciliation.engine.supplier_threshold` | 90 |
| janela de meses anteriores | `conciliation.engine.lookback_months` | 3 |
| candidatos guardados por autorização | `conciliation.engine.suggestions_per_authorization` | 5 |

Tolerância efetiva para um saldo: `max(valor fixo, percentual × saldo)`.

## Normalização do nome do fornecedor

| Entrada | Saída |
|---------|-------|
| `Padaria Pernambucana Ltda.` | `PADARIA PERNAMBUCANA` |
| `60.499.628 LILIAN KARLA GUILHERME SANTOS` | `LILIAN KARLA GUILHERME SANTOS` |
| `12.345.678/0001-90 - COMÉRCIO SÃO JOSÉ S/A` | `COMERCIO SAO JOSE` |
| `J. SILVA   &   CIA  ME` | `J SILVA` |
| `EIRELI MATERIAIS EIRELI` | `MATERIAIS` |
| texto vazio ou só sufixos | texto vazio (compatibilidade 0) |

## Compatibilidade do fornecedor (0 a 100)

| Autorização | Pagamento | Resultado esperado |
|-------------|-----------|--------------------|
| `PADARIA PERNAMBUCANA LTDA` | `PADARIA PERNAMBUCANA` | 100 |
| `PADARIA PERNAMBUCANA` | `PADARIA PERNAMBUCANA COMERCIO DE ALIMENTOS` | 100 (contenção) |
| `LILIAN KARLA GUILHERME SANTOS` | `60.499.628 LILIAN KARLA GUILHERME SANTOS` | 100 |
| `PAPELARIA CENTRAL` | `PAPELARIA CENTRAU` | de 90 a 99 (digitação) |
| `PAPELARIA CENTRAL` | `PAPELARIA DO BAIRRO` | abaixo de 90 |
| `PAPELARIA CENTRAL` | `POSTO DE COMBUSTIVEL ALFA` | abaixo de 60 |
| `SILVA` | `JOSE DA SILVA TRANSPORTES` | abaixo de 60 (nome de uma palavra não usa contenção) |
| `SILVA` | `SILVA` | 100 |

A função é simétrica: trocar os argumentos dá o mesmo resultado.

## Compatibilidade do valor e nota

| Saldo | Pagamento | Tolerância | Valor | Fornecedor | Nota | Classificação |
|-------|-----------|------------|-------|------------|------|---------------|
| 1.250,40 | 1.250,40 | 0,50 | 100 | 100 | 100 | automático |
| 430,00 | 430,30 | 0,50 | 100 | 100 | 100 | automático |
| 430,00 | 430,50 | 0,50 | 100 | 100 | 100 | automático (limite) |
| 430,00 | 430,51 | 0,50 | 99 | 100 | 99 | Excedente |
| 1.000,00 | 1.008,00 | 0,50 e 1% | 100 | 100 | 100 | automático |
| 1.000,00 | 1.000,00 | 0,50 | 100 | 90 | 90 | automático (limite) |
| 1.000,00 | 1.000,00 | 0,50 | 100 | 89 | 89 | Dúbio |
| 1.000,00 | 1.000,00 | 0,50 | 100 | 60 | 60 | Dúbio (limite) |
| 1.000,00 | 1.000,00 | 0,50 | 100 | 59 | 59 | sem sugestão |
| 3.000,00 | 1.000,00 | 0,50 | 33 | 100 | 33 | Parcial |
| 500,00 | 650,00 | 0,50 | 70 | 100 | 70 | Excedente |
| 3.000,00 | 1.000,00 | 0,50 | 33 | 89 | 33 | sem sugestão |

`valor = 100` dentro da tolerância; fora, `100 × (1 − diferença ÷ saldo)`, arredondado para
baixo, com piso em 0. `nota = min(fornecedor, valor)`.

## Quando o motor vincula sozinho

Todas as condições precisam valer:

1. nota ≥ nota automática;
2. diferença dentro da tolerância efetiva;
3. data do pagamento igual ou posterior à data da autorização;
4. os cartões não são diferentes (quando os dois lados têm cartão identificado);
5. o par não está bloqueado (rejeitado ou desvinculado antes);
6. o pagamento é o único de melhor nota para a autorização, e a autorização é a única de melhor
   nota para o pagamento, depois do desempate pelo cartão e, em seguida, pela forma de pagamento.

| Situação | Resultado |
|----------|-----------|
| Autorização do cartão 0798, dois pagamentos empatados, das faturas 0798 e 4931 | vincula o do cartão 0798 |
| Autorização do cartão 0798, dois pagamentos empatados, os dois da fatura 0798 | Dúbio para os dois, `is_tie` |
| Autorização de cartão sem número, dois pagamentos empatados, um de fatura de cartão e outro não | vincula o de fatura |
| Mesmo caso, os dois de fatura de cartão ou nenhum | Dúbio para os dois, `is_tie` |
| Autorização do cartão 0798, um único pagamento compatível, da fatura 4931 | Dúbio, `card_mismatch` |
| Autorização do cartão 0798, um único pagamento compatível, que não é de fatura | vincula |
| Autorização sem cartão (boleto), um único pagamento compatível, de fatura de cartão | vincula |
| Duas autorizações idênticas, um pagamento | Dúbio para as duas, `is_tie` |
| Autorização da sessão e autorização antiga empatadas para um pagamento | Dúbio para as duas |
| Pagamento de 10/07, autorização de 15/07, valor e fornecedor iguais | Dúbio, `paid_before_authorization` |
| Mesma data nos dois | vincula |
| Par rejeitado em execução anterior | não vincula, não sugere |
| Pagamento com código excluído | não entra; `reconciliation_skips` |

## Espécie do pagamento → cartão

| Espécie | Cartão |
|---------|--------|
| `FATURA CARTAO 0798` | `0798` |
| `FATURA CARTAO 7607 (7613)` | `7607` |
| `FATURA CARTAO 4931 SOCIAL` | `4931` |
| `Fatura Cartão 5352 (8347)` | `5352` |
| `FATURA CARTAO` (sem dígitos), `FATURA CARTAO 79` | nulo |
| `NOTA FISCAL FORNECED`, vazio | nulo |

## Condição de pagamento → parcelas previstas

| Texto | Parcelas |
|-------|----------|
| `A vista`, `À VISTA`, `a vista` | 1 |
| `1x`, `1 X` | 1 |
| `2x`, `10X`, `3 x` | 2, 10, 3 |
| `30 dias`, `10 DIAS` | 1 |
| `30/60 dias` | 2 |
| `30/60/90 dias`, `30 / 60 / 90` | 3 |
| `488,02`, vazio, `conforme contrato` | nulo (sem indício) |
| `0x`, `1000x` | nulo |

## Vínculo automático de parcela

Acontece depois do vínculo exato, para autorização com saldo. Neste vínculo, o eixo do valor
compara o pagamento com o valor da parcela de referência, e a nota (o menor dos dois eixos)
precisa atingir a nota automática.

| Saldo | Parcela de referência | Pagamento | Fornecedor | Valor | Nota | Resultado |
|-------|-----------------------|-----------|------------|-------|------|-----------|
| 900,00 | 300,00 | 300,00 | 100 | 100 | 100 | parcela |
| 900,00 | 300,00 | 300,40 | 95 | 100 | 95 | parcela |
| 900,00 | 300,00 | 300,00 | 89 | 100 | 89 | sugestão (fornecedor abaixo do limite) |
| 900,00 | 300,00 | 300,51 | 100 | 99 | 99 | sugestão Parcial (fora da tolerância da parcela) |

| Autorização | Pagamentos disponíveis | Resultado |
|-------------|------------------------|-----------|
| 900,00, `3x`, sem vínculo | 300,00 | vincula como parcela; saldo 600,00 |
| 900,00, `3x`, saldo 300,00 | 300,00 | vínculo exato quita (regra geral) |
| 1.000,00, `3x` | 333,33; 333,33; 333,34 em três sessões | três vínculos; Conciliada |
| 900,00, `3x` | 450,00 | não é parcela; sugestão Parcial |
| 900,00, `3x` | 900,00 | vínculo exato pelo total |
| 1.000,00, `A vista`, sem vínculo | 100,00 | sugestão Parcial |
| 1.000,00, `A vista`, um vínculo de 100,00 "Ainda falta pagar" | 100,00 | vincula como parcela |
| 900,00, `3x` | 300,00 e 300,00 na mesma sessão | os dois, em ordem de data |
| 900,00, `3x`, saldo 200,00 | 300,00 | não cabe; sugestão Excedente |
| Duas autorizações `3x` de 900,00 do mesmo fornecedor | 300,00 | nenhum vínculo; Dúbio para as duas |
| 900,00, `3x` | 300,00 com data anterior à autorização | sugestão, `paid_before_authorization` |
| 900,00, `3x`, cartão 0798 | 300,00 da fatura do cartão 4931 | sugestão, `card_mismatch` |

## Sessões anteriores

| Autorização | Lida? |
|-------------|-------|
| Da sessão em execução, sem saldo zerado | sim |
| De sessão processada, até `lookback_months` meses antes, com saldo | sim, pelo saldo |
| De sessão processada, mais antiga que a janela, sem nenhum vínculo | não |
| De sessão processada, mais antiga que a janela, com vínculo e saldo | sim (parcelamento) |
| De sessão anterior aberta ou em processamento | não |
| De sessão de período posterior | não |
| Com a mesma chave de identidade de outra já lida de outro período | não; `DuplicateOfOtherPeriod` |

## Determinismo

- Executar duas vezes com as mesmas entradas e parâmetros produz os mesmos vínculos, sugestões,
  notas e posições.
- Todo desempate termina em data do pagamento e, depois, em identificador crescente.
- Nenhuma regra usa relógio, sorteio ou a ordem de leitura do banco.
