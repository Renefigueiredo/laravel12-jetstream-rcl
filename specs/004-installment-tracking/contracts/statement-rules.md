# Contract: Regras do extrato, das parcelas e da previsão

O que as classes puras garantem. Cada tabela vira caso de teste de Unit.

## Linhas do extrato (`AuthorizationStatement`)

Entrada: valor autorizado e os vínculos em ordem de data do pagamento, depois de identificador.

| Vínculo | Linhas geradas | Saldo depois |
|---------|----------------|--------------|
| Pagamento igual ao saldo | pagamento | 0 |
| Pagamento menor, "Ainda falta pagar" | pagamento | saldo − pagamento |
| Pagamento menor, dentro da tolerância | pagamento; baixa por tolerância (o que faltou) | 0 |
| Pagamento maior, dentro da tolerância | pagamento | 0 |
| Pagamento menor, encerrado com desconto | pagamento; desconto (o que faltou), com justificativa | 0 |
| Pagamento maior, "Acréscimo aceito" | pagamento; acréscimo aceito (o excedente, informativo) | 0 |
| Pagamento maior, "Pagamento a maior" | pagamento; pagamento a maior (o excedente, informativo) | 0 |
| Encerramento com desconto sem pagamento novo | desconto | 0 |

| Autorização | Vínculos | Extrato esperado |
|-------------|----------|------------------|
| 900,00 | 300,00 (10/07), 300,00 (10/08) | saldos 600,00 e 300,00 |
| 1.000,00 | 333,33; 333,33; 333,34 | saldos 666,67; 333,34; 0,00 |
| 100,00 | 50,00 "falta pagar"; 48,50 dentro da tolerância de 2,00 | saldos 50,00; 1,50; baixa de 1,50; 0,00 |
| 500,00 | 520,00 "Acréscimo aceito" | pagamento; linha de acréscimo de 20,00; saldo 0,00 |
| 900,00 | nenhum | sem linhas; saldo 900,00 |

- O saldo nunca fica negativo.
- O saldo da última linha é igual ao saldo guardado da autorização.
- Desfazer um pagamento do meio muda o saldo de todas as linhas seguintes.

## Parcelas previstas (`InstallmentSchedule`)

Origem, nesta ordem: plano informado; condição de pagamento com mais de uma parcela; nenhuma.

| Autorizado | Origem | Parcelas previstas |
|------------|--------|--------------------|
| 900,00 | condição `3x` | 300,00; 300,00; 300,00 |
| 1.000,00 | condição `3x` | 333,33; 333,33; 333,34 |
| 1.000,00 | plano 400,00; 300,00; 300,00 | 400,00; 300,00; 300,00 |
| 1.000,00 | plano informado e condição `3x` | as do plano |
| 1.000,00 | condição `A vista`, sem plano | nenhuma |
| 1.000,00 | condição `488,02`, sem plano | nenhuma |

## Ocupação das parcelas

Os pagamentos entram em ordem de data e, no empate, de identificador. Cada um ocupa a primeira
parcela livre cujo valor esteja dentro da tolerância do valor da parcela.

| Parcelas | Pagamentos vinculados | Ocupação |
|----------|-----------------------|----------|
| 400; 300; 300 | 400 | 1ª paga; 2ª e 3ª em aberto |
| 400; 300; 300 | 300 | 2ª paga; 1ª e 3ª em aberto |
| 400; 300; 300 | 300; 300; 400 | todas pagas |
| 400; 300; 300 | 400; 400 | 1ª paga; o segundo fica fora do plano |
| 400; 300; 300 | 250 | nenhuma paga; o pagamento fica fora do plano |
| 333,33; 333,33; 333,34 | 333,34 | 1ª paga (dentro da tolerância) |
| 300; 300; 300 | 300,40 com tolerância de 0,50 | 1ª paga |
| 300; 300; 300 | 300,51 com tolerância de 0,50 | nenhuma; fora do plano |

- Um pagamento ocupa no máximo uma parcela; uma parcela recebe no máximo um pagamento.
- A tolerância é a que está em vigor no momento da leitura.
- O pagamento fora do plano continua abatendo o saldo.

## Mês esperado

| Autorização em | Origem | Meses esperados |
|----------------|--------|-----------------|
| 05/07/2026 | plano com meses informados | os informados |
| 05/07/2026 | `30/60/90 dias` | 08/2026; 09/2026; 10/2026 |
| 28/07/2026 | `30/60 dias` | 08/2026; 09/2026 |
| 05/07/2026 | `3x` | 08/2026; 09/2026; 10/2026 |
| 05/07/2026 | plano sem meses | uma por mês a partir de 08/2026 |
| 05/07/2026 | `A vista` | sem previsão |

## Atraso

Parcela em aberto com mês esperado igual ou anterior ao mês da sessão processada mais recente.

| Parcela esperada em | Sessão processada mais recente | Paga? | Situação |
|---------------------|--------------------------------|-------|----------|
| 08/2026 | 07/2026 | não | em aberto |
| 08/2026 | 08/2026 | não | atrasada |
| 08/2026 | 10/2026 (09 nunca processado) | não | atrasada |
| 08/2026 | 09/2026 | sim, em 09/2026 | paga |
| 08/2026 | 09/2026 | sim, em 07/2026 | paga |
| sem mês | qualquer | não | em aberto |

## Condição de pagamento → prazos em dias

| Texto | Parcelas | Prazos |
|-------|----------|--------|
| `30/60/90 dias` | 3 | 30, 60, 90 |
| `30 / 60` | 2 | 30, 60 |
| `30 dias` | 1 | 30 |
| `3x` | 3 | nenhum |
| `A vista` | 1 | nenhum |

## O motor com plano informado

Acrescenta à tabela "Vínculo automático de parcela" do Módulo 2. Fornecedor 100 em todos.

| Autorização | Parcelas em aberto do plano | Pagamentos da sessão | Resultado |
|-------------|-----------------------------|----------------------|-----------|
| 1.000,00 | 400; 300; 300 | 400,00 | vincula como parcela |
| 1.000,00, saldo 600,00 | 300; 300 | 400,00 | não vincula; sugestão Parcial |
| 1.000,00 | 400; 300; 300 | 300,00 e 300,00 | os dois, em ordem de data |
| 1.000,00, saldo 600,00 | 300; 300 | 300,00; 300,00; 300,00 | dois vinculam; o terceiro não |
| 1.000,00, condição `2x` | 400; 300; 300 | 500,00 | não vincula: vale o plano, não a condição |
| 1.000,00, com 250,00 já vinculado "falta pagar" | 400; 300; 300 | 250,00 | não vincula: vale o plano |
| 1.000,00 | 400; 300; 300 | 1.000,00 | vínculo exato pelo total |
| 1.000,00, cartão 0798 | 400; 300; 300 | 400,00 da fatura 4931 | sugestão, `card_mismatch` |
| duas autorizações com parcela de 400,00 em aberto | | 400,00 | nenhum vínculo; fica para o Operador |
