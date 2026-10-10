# Contract: Rotas e telas

## Rota

| Rota | Nome | Componente | Acesso |
|------|------|------------|--------|
| `GET /dashboard` | `dashboard` | `App\Livewire\Dashboard\Show` | autenticado e verificado (Operador e Administrador) |

- É o destino depois do login (já configurado).
- Parâmetros na URL: `aba` (`abertas`, padrão; `conciliadas`; `divergencias`), `busca`,
  `filtros`, `page`. Valor inválido de `aba` cai em `abertas`.

## Página (`Dashboard\Show`)

| Elemento | Conteúdo |
|----------|----------|
| Resumo | total autorizado, total pago, saldo em aberto, descontos, acréscimos aceitos, pagamentos a maior; quantidade de Abertas, Parciais e Conciliadas |
| Alertas | autorizações com parcela atrasada; autorizações com pagamento a maior; divergências pendentes. Cada um leva à aba já filtrada |
| Sem sessão processada | totais zerados e orientação com link para "Sessões" |
| Abas | "Em aberto / Parcial", "Conciliadas", "Divergências" |

Atualiza-se ao receber o evento `reconciliation-changed`.

## Abas "Em aberto / Parcial" e "Conciliadas" (`Dashboard\AuthorizationsTable`)

Fonte: autorizações do painel (research R3), com saldo maior que zero ou igual a zero.

| Elemento | Comportamento |
|----------|---------------|
| Linha | fornecedor, pedido, situação, autorizado, pago, saldo, quantidade de pagamentos, condição com parcelas previstas, cartão, sessão de origem; marca "Criada na conciliação"; alerta de pagamento a maior; aviso de parcela atrasada |
| Faixa de totais | quantidade, autorizado, pago e saldo do que está listado; acompanha a busca e os filtros |
| Abrir a linha | mostra o extrato; abrir e fechar pelo mouse e pelo teclado |
| Busca | fornecedor |
| Filtros | situação (Aberta, Parcial), sessão de origem, cartão, com parcela atrasada, com pagamento a maior |
| Ordenação padrão | "Em aberto": atrasadas primeiro, depois a mais antiga; "Conciliadas": último pagamento mais recente |
| Paginação | 25 por página |

### Extrato (dentro da linha)

| Bloco | Conteúdo |
|-------|----------|
| Pagamentos | data, fornecedor do pagamento, valor, unidade, sessão, forma do vínculo, quem decidiu e quando, parcela ocupada, saldo depois |
| Abatimentos | desconto, baixa por tolerância, acréscimo aceito, pagamento a maior, com valor e justificativa |
| Parcelas que faltam | posição, valor, mês esperado, situação (em aberto, atrasada) |
| Sem pagamento | mensagem e, se houver, as parcelas previstas |

| Ação | Onde | Entrada | Resultado | Recusas |
|------|------|---------|-----------|---------|
| Desfazer | cada pagamento do extrato | confirmação | vínculo desfeito; saldo recalculado; pagamento volta às divergências; auditoria | vínculo já desfeito; sessão do pagamento não processada |
| Informar plano de parcelas | autorização com saldo | lista de valores e, opcional, mês de cada um | plano salvo; previsão recalculada; auditoria | soma diferente do autorizado; menos parcelas do que as já pagas; mais de 120 parcelas; valor zero ou negativo |
| Remover plano | autorização com plano | confirmação | plano removido; volta a valer a condição de pagamento; auditoria | sem plano |
| Ver dados da autorização | linha | | modal já existente | |
| Ver dados do pagamento | cada pagamento do extrato | | modal já existente | |

## Aba "Divergências" (`Dashboard\DivergencesTable`)

Fonte: visão de pendências, todas as sessões, tipos "Sem autorização" e "Excedente".

| Elemento | Comportamento |
|----------|---------------|
| Colunas | tipo, unidade, fornecedor do pagamento, data, valor, código e nome da operação, sessão; no Excedente, a autorização sugerida e quanto passa do saldo |
| Busca | fornecedor |
| Filtros | tipo, unidade, sessão, código de operação, cartão |
| Paginação | 25 por página |

| Ação | Tipo | Entrada | Resultado | Recusas |
|------|------|---------|-----------|---------|
| Vincular | Sem autorização | autorização em aberto que possa receber o pagamento; tratamento da diferença | vínculo manual; auditoria | autorização quitada ou indisponível; pagamento já vinculado |
| Confirmar | Excedente | "Pagamento a maior" ou "Acréscimo aceito" com justificativa | vínculo; auditoria | acréscimo acima do teto; sugestão não mais pendente |
| Rejeitar | Excedente | confirmação | sugestão rejeitada; o pagamento passa a "Sem autorização" | sugestão não mais pendente |
| Criar autorização | Sem autorização, só Administrador | justificativa | autorização criada e vinculada; auditoria | pagamento já vinculado |
| Ver dados | os dois | | modais já existentes | |

## Mudanças em telas existentes

- A escolha de autorização no "Vincular" a partir do pagamento (Pendências, Fila de investigação,
  Por cartão) passa a listar também as autorizações em aberto de sessões anteriores que podem
  receber o pagamento.
- A recusa de reabrir a sessão passa a dizer onde desfazer os vínculos.
