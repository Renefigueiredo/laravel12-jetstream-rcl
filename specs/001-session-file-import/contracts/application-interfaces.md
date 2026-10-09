# Contract: Interfaces da aplicação

Pontos de troca entre este módulo e o que fica fora dele. Ficam em `app/Contracts`.

## SpreadsheetReader

Isola a biblioteca de leitura (Princípio VI).

```php
interface SpreadsheetReader
{
    /** @return iterable<int, array<int, string|int|float|\DateTimeInterface|null>> linhas da primeira aba, indexadas pelo número da linha */
    public function rows(string $absolutePath): iterable;

    public function sheetCount(string $absolutePath): int;
}
```

- Não interpreta valores nem datas; devolve o conteúdo de cada célula.
- Em `.csv`, resolve delimitador e codificação e devolve texto em UTF-8.
- Implementação deste módulo: `OpenSpoutSpreadsheetReader`.

## SpreadsheetLayout

Uma implementação por layout: `AuthorizationsLayout`, `PaymentsLayout`.

```php
interface SpreadsheetLayout
{
    /** @return list<string> todos os cabeçalhos do layout */
    public function headers(): array;

    /** @return list<string> cabeçalhos sem os quais o arquivo é recusado */
    public function requiredHeaders(): array;

    /** Valor que decide se a linha é ignorada (zero ou negativo). */
    public function amountColumn(): string;

    /** Data comparada com o período da sessão. */
    public function periodDateColumn(): string;

    /**
     * @param  array<string, mixed>  $row  células indexadas pelo cabeçalho normalizado
     * @return ParsedRow|list<RowError>
     */
    public function parse(array $row, int $rowNumber): ParsedRow|array;
}
```

`ParsedRow` leva os atributos tipados do lançamento, a chave de identidade e o `raw`. `RowError`
leva linha, coluna, valor encontrado e motivo.

## ReconciliationEngine

Implementada pelo Módulo 2. Este módulo só a chama.

```php
interface ReconciliationEngine
{
    /** @param  callable(int $percent): void  $reportProgress */
    public function run(ReconciliationSession $session, callable $reportProgress): void;

    /** Apaga o resultado anterior da sessão. Chamado antes de `run`, em caso de falha e na reabertura. */
    public function discardResult(ReconciliationSession $session): void;
}
```

- `run` lê os lançamentos ativos da sessão e não altera sessão, arquivos nem lançamentos.
- Se `run` lança exceção, o job chama `discardResult` e devolve a sessão a `Open`.
- `ReopenSession` chama `discardResult` dentro da transação da reabertura, depois de conferir que
  não há decisões manuais nem vínculos com outras sessões (Módulo 2).
- Sem implementação registrada, `conciliation.engine_enabled` fica `false` e a execução não é
  oferecida.

## ReconciliationResultInspector

Implementada pelos Módulos 2 a 4. A implementação padrão deste módulo devolve zero.

```php
interface ReconciliationResultInspector
{
    /** Confirmações, vínculos manuais e baixas, mais vínculos com lançamentos de outras sessões. */
    public function blockingDecisionCount(ReconciliationSession $session): int;
}
```

A reabertura é recusada quando o resultado é maior que zero, informando a quantidade.

## AuditRecorder

Serviço próprio, usado por todos os módulos.

```php
final class AuditRecorder
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(User $actor, AuditAction $action, Model $entity, string $label, ?array $before, ?array $after): AuditLog;
}
```

- Só pode ser chamado dentro de uma transação aberta; fora dela, lança exceção.
- Não há método para alterar ou apagar registros.
