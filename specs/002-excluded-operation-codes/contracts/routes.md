# Contract: Rotas, ações de tela e comandos

Aplicação web renderizada no servidor; não há API pública. As rotas ficam no mesmo grupo
autenticado do Módulo 1 (`auth:sanctum`, sessão do Jetstream, `verified`).

## Rotas HTTP

| Método | URI | Nome | Destino | Autorização |
|--------|-----|------|---------|-------------|
| GET | `/codigos-excluidos` | `excluded-codes.index` | Livewire `ExcludedCodes\Index` | gate `manage-excluded-codes` |
| GET | `/codigos-excluidos/modelo` | `excluded-codes.template` | `ExcludedCodeTemplateController` | gate `manage-excluded-codes` |

Regras:

- Sem a permissão, as duas rotas respondem 403 e o item de menu não é exibido.
- A planilha modelo é `modelo-codigos-excluidos.xlsx`, com os cabeçalhos `COD_OPERACAO` e
  `DESCRICAO` e uma linha de exemplo.
- Busca e ordenação ficam na URL: `?busca=folha&ordem=code:asc`. A ordenação usa o formato
  `coluna:direção` das tabelas do Filament. A busca com várias palavras exige que todas apareçam
  no código ou na descrição.

## Ações Livewire (`ExcludedCodes\Index`)

Cada ação confere o gate de novo e delega a uma classe em `app/Actions/Conciliation`.

| Ação | Entrada | Resultado | Recusas |
|------|---------|-----------|---------|
| adicionar código | código, descrição opcional | código na lista; auditoria; aviso de sucesso | código em branco ou inválido; descrição acima de 255; código já cadastrado |
| remover código (ação da linha) | confirmação | código fora da lista; auditoria | código já removido por outro usuário (aviso e lista atualizada) |
| enviar planilha | arquivo `.csv` ou `.xlsx` | importação `Queued`; bloco de importação mostra o andamento | tipo não aceito; acima de 5 MB; usuário com importação em andamento |

Bloco de importação, conforme a situação da última importação do usuário:

| Situação | O que a tela mostra |
|----------|---------------------|
| `Queued`, `Processing` | "Importação em andamento"; envio desabilitado; atualização automática |
| `Completed` | "Importação concluída: N códigos adicionados e M códigos ignorados por já estarem cadastrados ou repetidos no arquivo." |
| `Rejected` com erros | "Nenhum código foi importado." e a lista de erros, cada um com linha, coluna e motivo |
| `Rejected` geral | o motivo: sem códigos para importar, ou acima de 10.000 linhas |
| `Failed` | "A importação não pôde ser concluída. Envie o arquivo novamente." |

## Comandos Artisan

| Comando | Uso |
|---------|-----|
| `conciliation:grant-permission {email} {permission} --by={email do administrador}` | Concede a permissão e grava auditoria. Recusa se `--by` não for Administrador, se o usuário não existir ou se a permissão não existir. Conceder de novo não duplica. |
| `conciliation:revoke-permission {email} {permission} --by={email do administrador}` | Revoga e grava auditoria. Revogar o que não existe não é erro. |
| `conciliation:prune-import-attempts` (existente) | Passa a marcar como `Failed` a importação de códigos parada além do limite e a apagar as importações `Rejected` e `Failed` vencidas, com o arquivo. |

`{permission}` aceita o valor do Enum, por exemplo `manage_excluded_codes`.

`--by` identifica o Administrador responsável, mas não o autentica: quem tem acesso ao servidor
pode informar qualquer e-mail. É um limite conhecido, aceito enquanto não houver tela de usuários.

## Nomes na tela

O título da tela é "Códigos de Operação Excluídos". O item de menu usa a forma curta "Códigos
excluídos".
