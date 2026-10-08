# Contract: Rotas e ações de tela

Aplicação web renderizada no servidor; não há API pública. Todas as rotas exigem autenticação
(`auth:sanctum`, sessão do Jetstream, `verified`) e passam por policy ou gate.

## Rotas HTTP

| Método | URI | Nome | Destino | Autorização |
|--------|-----|------|---------|-------------|
| GET | `/sessoes` | `sessions.index` | Livewire `Sessions\Index` | `viewAny` de sessão |
| GET | `/sessoes/{session}` | `sessions.show` | Livewire `Sessions\Show` | `view` |
| GET | `/sessoes/{session}/arquivos/{importFile}/original` | `sessions.files.download` | `ImportFileDownloadController` | `view` |
| GET | `/sessoes/tentativas/{importAttempt}/erros` | `sessions.attempts.errors` | `ImportErrorReportController` | `view` da sessão da tentativa |
| GET | `/planilhas-modelo/{layout}` | `templates.download` | `SpreadsheetTemplateController` | autenticado |
| GET | `/sessoes/historico` | `sessions.history` | Livewire `Sessions\History` | gate `view-session-history` (Administrador) |

Regras:

- `{session}` aceita apenas números, para não colidir com `/sessoes/historico` e
  `/sessoes/tentativas`.
- `{importFile}` precisa pertencer a `{session}`; caso contrário, 404.
- Downloads respondem com o nome original e não expõem o caminho no disco.
- `{layout}` aceita `autorizacoes` e `pagamentos`; outro valor, 404.
- Sessão inexistente: 404 em rota HTTP; em ação Livewire, aviso "Sessão não encontrada" e
  redirecionamento para `sessions.index`.
- Filtros de `sessions.index` ficam na URL: `?periodo=05/2026&situacao=processed`.

## Ações Livewire

Cada ação delega a uma classe em `app/Actions/Conciliation` e confere a situação atual da sessão
no servidor.

| Componente | Ação | Entrada | Resultado | Recusas |
|------------|------|---------|-----------|---------|
| `Sessions\Index` | criar sessão | período `MM/AAAA`; confirmação se o período já tem sessão | sessão `Open`; redireciona ao painel | período inválido ou futuro |
| `Sessions\Show` | enviar planilha | cartão, arquivo | tentativa `Queued`; cartão mostra progresso | sessão não `Open`; tipo ou tamanho; mesmo arquivo no outro cartão de pagamentos |
| `Sessions\Show` | confirmar divergência | tentativa | tentativa `Persisting`; auditoria | tentativa não `AwaitingConfirmation`; sessão não `Open` |
| `Sessions\Show` | cancelar divergência | tentativa | tentativa `Cancelled`; arquivo descartado | |
| `Sessions\Show` | executar conciliação | confirmação | sessão `Processing`; auditoria; job despachado | cartão faltando; sessão não `Open`; motor desligado |
| `Sessions\Show` | reabrir | confirmação | sessão `Open`, `result_stale`; auditoria | sessão não `Processed`; decisões manuais existentes (informa a quantidade) |
| `Sessions\Show` | excluir | confirmação | sessão apagada; auditoria; redireciona à lista | sessão já processada alguma vez; sessão `Processing` |

## Estado exibido por cartão

| Campo | Origem |
|-------|--------|
| status `Pendente` ou `Carregado` | existência de `import_files` ativo no cartão |
| nome do arquivo, lançamentos, ignoradas por valor, já existentes, quem, quando | `import_files` |
| indicador de divergência de período | `import_files.period_divergence` |
| aviso "apenas a primeira aba foi lida" | `import_files.sheet_count > 1` |
| aviso de colunas do layout não encontradas | `import_files.missing_columns` |
| progresso do envio, resumo de erros, pedido de confirmação | `import_attempts` mais recente do cartão |
| travado | `reconciliation_sessions.status != open` |
