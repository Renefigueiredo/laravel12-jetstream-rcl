# Contract: Interfaces da aplicação

O que este módulo oferece aos outros e o que consome do Módulo 1.

## ExcludedOperationCodes (oferecido ao Módulo 2)

Serviço em `app/Services/ExcludedCodes`. É a única forma de o motor consultar a lista.

```php
class ExcludedOperationCodes
{
    /** Fotografia da lista neste instante. */
    public function snapshot(): ExcludedCodeSnapshot;
}

final readonly class ExcludedCodeSnapshot
{
    /** Comparação exata, depois de remover espaços no início e no fim. */
    public function contains(?string $operationCode): bool;

    /** @return list<string> códigos em ordem crescente */
    public function codes(): array;
}
```

Obrigações do motor (Módulo 2), para cumprir FR-020 a FR-025:

- Pedir a fotografia uma vez, no início da execução, e não consultar a lista de novo.
- Não pontuar, sugerir nem vincular pagamento para o qual `contains()` é verdadeiro.
- Marcar esse pagamento como excluído por código, com o código, no resultado da execução.
- Guardar na execução `codes()` e a quantidade de pagamentos excluídos por código.
- Não oferecer esse pagamento para vínculo manual nem listá-lo como pendência.

A fotografia não muda quando a lista muda; isso garante que uma alteração feita durante a
execução, ou depois dela, não altera o resultado (FR-024).

## OperationCode

Normalização e validação em um só lugar, usadas pelo cadastro manual, pela importação e pela
fotografia.

```php
final class OperationCode
{
    /** Remove espaços nas pontas; devolve null quando não sobra nada. */
    public static function normalize(?string $value): ?string;

    /** De 1 a 20 letras ou dígitos. */
    public static function isValid(string $code): bool;
}
```

## Consumido do Módulo 1

| Interface | Uso neste módulo |
|-----------|------------------|
| `SpreadsheetReader` | ler a planilha de códigos, com delimitador e codificação já resolvidos |
| `CellText` | converter a célula em texto, inclusive número inteiro |
| `AuditRecorder` | gravar a auditoria na transação da mudança |
| `ActionRefusedException` | recusar uma ação com a mensagem exibida ao usuário |

Nenhuma dessas interfaces muda.

## Autorização

```php
Gate::define('manage-excluded-codes', fn (User $user): bool => $user->hasPermission(UserPermission::ManageExcludedCodes));
```

`User::hasPermission()` é verdadeiro para todo Administrador. Views e componentes usam o gate;
nenhum compara o papel diretamente.
