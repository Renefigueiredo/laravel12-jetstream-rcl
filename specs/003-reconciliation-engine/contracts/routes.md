# Contract: Rotas e ações de tela

Aplicação web renderizada no servidor; não há API pública. As rotas ficam no grupo autenticado
dos módulos anteriores.

## Rotas HTTP

| Método | URI | Nome | Destino | Autorização |
|--------|-----|------|---------|-------------|
| GET | `/sessoes/{session}/conciliacao` | `reconciliation.show` | Livewire `Reconciliation\Show` | `view` da sessão |
| GET | `/configuracoes/tolerancia` | `reconciliation.settings` | Livewire `Reconciliation\Settings` | gate `configure-tolerance` |

Regras:

- `{session}` aceita apenas números.
- Sessão que não está `Processed`: a página mostra o aviso da situação (aberta, em processamento
  ou reaberta sem resultado) e o atalho para o painel da sessão, sem listas.
- A aba fica na URL: `?aba=pendencias` (padrão), `investigacao`, `conciliados`, `antes`,
  `cartoes`, `excluidos`. Filtros e busca de cada aba também ficam na URL.
- O painel da sessão (`sessions.show`) ganha o resumo da execução e o botão "Abrir conciliação".
- O menu ganha "Tolerância" para quem tem a permissão.

## Página `Reconciliation\Show`

Cabeçalho, sempre visível:

- período e número da sessão; quem executou e quando; tolerância e limites usados;
- totais: conciliadas automaticamente (quantidade e percentual das autorizações da sessão),
  conciliadas manualmente, Dúbios, Parciais, Excedentes, Sem pagamento, Sem autorização,
  autorizações de sessões anteriores conciliadas por esta sessão, pagos antes da autorização,
  descontos, acréscimos aceitos e pagamentos a maior (quantidade e soma), excluídos por código;
- itens que aguardam decisão.

### Aba Pendências (`Reconciliation\PendingTable`)

Fonte: visão `reconciliation_pending_items`, filtrada pela sessão. A lista padrão ("Todos") traz
Dúbios, Parciais, Excedentes, Sem pagamento e Saldo em aberto. Os pagamentos sem autorização
ficam na aba Fila de investigação e só entram nesta lista pelo filtro "Sem autorização".

| Elemento | Comportamento |
|----------|---------------|
| Colunas | autorização (fornecedor, valor, saldo, data, forma e condição de pagamento, período de origem se for de outra sessão, parcelas previstas e pagas) e pagamento (unidade, fornecedor, valor, data, operação, espécie, outras linhas da mesma obrigação), nota, eixos, diferença, avisos ("Pago antes da autorização", "Cartão diferente") |
| Filtro rápido | Todos, Dúbios, Parciais, Excedentes, Sem pagamento, Saldo em aberto, Sem autorização |
| Filtro | cartão (os cartões presentes na sessão) |
| Busca | por fornecedor da autorização ou do pagamento |
| Paginação | 25 por página |

Ações por linha:

| Ação | Disponível em | Entrada | Resultado | Recusas |
|------|---------------|---------|-----------|---------|
| Confirmar | Dúbio | confirmação | vínculo manual; auditoria | sugestão não está mais pendente; pagamento já vinculado |
| Confirmar | Parcial | "Ainda falta pagar" ou "Encerrar com desconto" + categoria e texto da justificativa | vínculo; saldo ou desconto | idem; categoria ou texto ausente |
| Confirmar | Excedente | "Pagamento a maior" ou "Acréscimo aceito" + categoria e texto da justificativa | vínculo; alerta ou acréscimo | idem; acréscimo acima do teto |
| Rejeitar | qualquer sugestão | confirmação | sugestão rejeitada; par bloqueado; próximo candidato aparece | sugestão não está mais pendente |
| Ver outros candidatos | sugestão com mais candidatos | nenhuma | lista os demais, com nota | nenhuma |
| Vincular | Sem pagamento, Saldo em aberto | pagamento escolhido em busca (fornecedor, valor), ordenada por nota; tratamento da diferença | vínculo manual | pagamento já vinculado; excluído por código; de outra sessão |
| Encerrar com desconto | Saldo em aberto | categoria e texto da justificativa | desconto do saldo no vínculo mais recente | sem vínculo; saldo zero |

Ação em lote: confirmar vários Dúbios selecionados; cada um é vinculado e auditado
separadamente, e os recusados são informados.

### Aba Fila de investigação (`Reconciliation\InvestigationTable`)

Fonte: pagamentos sem autorização da sessão.

| Elemento | Comportamento |
|----------|---------------|
| Colunas | unidade, fornecedor, valor, data, código e nome da operação, espécie, forma |
| Filtros | unidade, código de operação, forma de pagamento, cartão |
| Busca | fornecedor |

| Ação | Entrada | Resultado | Recusas |
|------|---------|-----------|---------|
| Vincular | autorização da sessão ou em aberto de sessão anterior na janela; tratamento da diferença | vínculo manual | autorização quitada; pagamento já vinculado |
| Criar autorização correspondente | confirmação | autorização "Criada na conciliação" já vinculada; auditoria | pagamento já vinculado; excluído por código |

### Aba Conciliados (`Reconciliation\LinksTable`)

Fonte: vínculos cujo pagamento é da sessão, mais os de autorizações da sessão pagas em sessões
posteriores (somente leitura para estes, com a indicação da sessão do pagamento).

| Elemento | Comportamento |
|----------|---------------|
| Colunas | autorização, pagamento, origem (automático, manual, parcela), nota, tipo de diferença, tratamento, saldo da autorização, avisos |
| Filtros | origem; diferença (com diferença, pago a menor, pago a maior); tratamento (inclui "Pagamento a maior"); só parcelas; cartão |
| Busca | fornecedor |

| Ação | Entrada | Resultado | Recusas |
|------|---------|-----------|---------|
| Desvincular | confirmação | vínculo desfeito; par bloqueado para o motor; sugestões descartadas por aquele vínculo voltam a pendentes; saldo recalculado; autorização criada na conciliação é apagada; auditoria com nota e classificação originais | vínculo já desfeito; pagamento de outra sessão |

### Aba Pagos antes da autorização (`Reconciliation\EarlyPaymentsTable`)

Dois blocos. "Conciliados": a tabela de vínculos (`Reconciliation\LinksTable`) restrita aos que
têm `paid_before_authorization`, sejam automáticos ou por decisão. "Aguardando decisão ou
rejeitados": as sugestões com a marca, com as duas datas e a situação.

### Aba Por cartão (`Reconciliation\CardsTable`)

Fonte: agregações no banco sobre autorizações e pagamentos da sessão, agrupadas por cartão.

| Coluna | Conteúdo |
|--------|----------|
| Cartão | os quatro dígitos |
| Autorizações | quantidade e total autorizado |
| Linhas de fatura | quantidade e total pago (sem as excluídas por código) |
| Conciliadas | quantidade de vínculos entre os dois |
| Autorizações sem fatura | quantidade e total; em geral vêm na fatura do mês seguinte |
| Linhas sem autorização | quantidade e total |
| Excluídas por código | quantidade de linhas de fatura fora da conciliação |

Ao escolher um cartão (`?cartao=0798`), a aba mostra a conferência dele em três blocos, cada um
paginado: linhas da fatura com a autorização vinculada ou sugerida (com nota e avisos);
autorizações do cartão sem fatura; linhas de fatura sem autorização. As ações de cada linha são
as mesmas das abas Pendências, Fila de investigação e Conciliados. Um cartão que só aparece nas
faturas é listado com zero autorizações.

### Aba Excluídos por código (`Reconciliation\ExcludedTable`)

Fonte: `reconciliation_skips` da execução vigente. Mostra os códigos vigentes com a quantidade de
pagamentos de cada um e, abaixo, os pagamentos excluídos, filtráveis por código. Sem ações.

## Página `Reconciliation\Settings`

| Campo | Regras |
|-------|--------|
| Tolerância em valor | reais, com vírgula; de 0,00 a 9.999,99 |
| Tolerância percentual | opcional; de 0 a 100, com até duas casas |
| Teto da tolerância percentual | opcional; reais, com vírgula |
| Teto de acréscimo aceito | de 0 a 100, com até duas casas |

Salvar grava a configuração e a auditoria com os valores anterior e novo. A página avisa que a
mudança vale para as próximas execuções e para as próximas decisões de acréscimo.

## Regras comuns das ações

- Toda ação confere a autorização no servidor (`view` da sessão ou o gate) e delega a uma classe
  em `app/Actions/Conciliation`.
- Toda ação que cria ou desfaz vínculo exige que a sessão do pagamento esteja `Processed`.
- Recusas mostram a mensagem e atualizam a linha com a situação atual.
- Depois de cada ação, o cabeçalho de totais é recalculado.

## Mudança feita pelo Módulo 3

A escolha de autorização no "Vincular" a partir do pagamento (Pendências, Fila de investigação e
Por cartão) lista as autorizações que podem receber o pagamento pela mesma regra da ação
(`AuthorizationAvailability`): as da sessão, as em aberto de sessões processadas dentro da janela
e as mais antigas que já têm pagamento. Antes listava só as da própria sessão.
