# Data Model: Códigos de Operação Excluídos da Conciliação (Módulo 5)

Três tabelas novas e nenhuma alteração nas tabelas do Módulo 1. Datas e horas em UTC. Decisões em
[research.md](./research.md).

## Enums (`app/Enums`)

| Enum | Casos | Uso |
|------|-------|-----|
| `UserPermission` | `ManageExcludedCodes` (`manage_excluded_codes`) | Permissões além do papel. Os Módulos 2 e 6 acrescentam casos. |
| `ExcludedCodeSource` | `Manual` (`manual`), `File` (`file`) | Por qual meio o código entrou na lista. |
| `ExcludedCodeImportStatus` | `Queued`, `Processing`, `Completed`, `Rejected`, `Failed` | Situação de um envio de planilha de códigos. |
| `AuditAction` (existente) | novos: `ExcludedCodeAdded`, `ExcludedCodesImported`, `ExcludedCodeRemoved`, `PermissionGranted`, `PermissionRevoked` | Trilha de auditoria. |

`ExcludedCodeImportStatus::isInProgress()` é verdadeiro para `Queued` e `Processing`.

## user_permissions

Permissões concedidas a um usuário além do papel.

| Coluna | Tipo | Regras |
|--------|------|--------|
| `id` | bigint | PK |
| `user_id` | FK `users` | obrigatório; `restrictOnDelete` |
| `permission` | string | valor de `UserPermission` |
| `granted_by` | FK `users` | obrigatório; `restrictOnDelete` |
| `created_at` | timestamp | obrigatório |

- Único: (`user_id`, `permission`).
- O Administrador tem todas as permissões pelo papel; não precisa de linha nesta tabela.
- Revogar apaga a linha; a auditoria guarda quem tinha e quem revogou.

Modelo `UserPermissionGrant` (tabela `user_permissions`), com `user()` e `grantor()`. Em `User`:
`permissionGrants()` e `hasPermission(UserPermission $permission): bool`.

## excluded_operation_codes

A lista ativa. Uma linha por código.

| Coluna | Tipo | Regras |
|--------|------|--------|
| `id` | bigint | PK |
| `code` | string(20) | obrigatório; único; só letras e dígitos; sem espaços nas pontas |
| `description` | string(255) | opcional |
| `source` | string | valor de `ExcludedCodeSource` |
| `excluded_code_import_id` | FK `excluded_code_imports` | preenchido quando `source` é `File`; `restrictOnDelete` |
| `created_by` | FK `users` | obrigatório; `restrictOnDelete` |
| `created_at` | timestamp | obrigatório; indexado |

- Não há `updated_at`: um código não é editado (fora do escopo da spec).
- A remoção apaga a linha. Recadastrar cria uma linha nova, com novo `id` e nova data.
- A comparação com o código do pagamento é exata, depois de remover espaços nas pontas (FR-025).

Modelo `ExcludedOperationCode`, com `creator()` e `import()`.

## excluded_code_imports

Um envio de planilha de códigos.

| Coluna | Tipo | Regras |
|--------|------|--------|
| `id` | bigint | PK |
| `user_id` | FK `users` | quem enviou; `restrictOnDelete` |
| `status` | string | valor de `ExcludedCodeImportStatus`; indexado |
| `original_name` | string | nome do arquivo enviado |
| `disk` | string | disco privado em uso |
| `path` | string | caminho do arquivo em `conciliation/excluded-codes/`, sem alteração do conteúdo |
| `size_bytes` | unsignedBigInteger | tamanho do arquivo |
| `added_count` | unsignedInteger | padrão 0 |
| `ignored_count` | unsignedInteger | padrão 0 |
| `errors` | json | opcional; lista de `{row, column, reason}` quando recusada |
| `failure_message` | string | opcional; motivo da recusa geral ou da falha |
| `started_at` | timestamp | opcional |
| `finished_at` | timestamp | opcional |
| `created_at`, `updated_at` | timestamp | |

Transições:

```text
Queued → Processing → Completed   (grava códigos e auditoria na mesma transação)
                    → Rejected    (linha inválida, sem códigos, acima do limite de linhas)
                    → Failed      (erro inesperado ou importação parada)
```

- `Rejected` com erros por linha preenche `errors`; recusa geral preenche `failure_message`.
- `Completed` é retido para sempre, com o arquivo (Princípio VII).
- `Rejected` e `Failed` são apagados, com o arquivo, depois do prazo de retenção.
- Um usuário não pode ter duas importações em andamento (FR-015).

Modelo `ExcludedCodeImport`, com `user()` e `codes()`.

## Registros de auditoria

| Ação | Entidade | `label` | `before` | `after` |
|------|----------|---------|----------|---------|
| `ExcludedCodeAdded` | código | o código | nulo | `{code, description}` |
| `ExcludedCodesImported` | importação | nome do arquivo | nulo | `{file, added, ignored, codes: [...]}` |
| `ExcludedCodeRemoved` | código | o código | `{code, description, source, created_at}` | nulo |
| `PermissionGranted` | concessão | e-mail do usuário | nulo | `{user_id, email, permission}` |
| `PermissionRevoked` | concessão | e-mail do usuário | `{user_id, email, permission}` | nulo |

O mapa de tipos polimórficos ganha `excluded_operation_code`, `excluded_code_import` e
`user_permission`. O usuário não entra no mapa, para não mudar o tipo gravado pelos tokens do
Sanctum e por outras relações polimórficas já existentes.

## Objetos sem tabela

- **`ExcludedCodeSnapshot`**: fotografia imutável da lista, com `contains(string $code): bool` e
  `codes(): list<string>`. É o que o motor do Módulo 2 consome.
- **`ParsedExcludedCodeFile`**: resultado da leitura, com os códigos válidos (código e
  descrição), os erros (linha, coluna e motivo), a quantidade de repetidos no arquivo e o motivo de recusa
  geral, se houver.

## O que este módulo não cria

A marca de "excluído por código" no pagamento e o registro, por execução, dos códigos vigentes e
das quantidades excluídas (FR-022, FR-023) dependem da entidade Execução e ficam no modelo de
dados do Módulo 2.
