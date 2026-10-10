# Contract: Interfaces de aplicação

Assinaturas que as tarefas implementam. Todas as ações autorizam, abrem transação, gravam
auditoria e lançam `ActionRefusedException` nas recusas.

## Ações novas

| Ação | Assinatura |
|------|------------|
| `SaveInstallmentPlan` | `(User, AuthorizationEntry, list<array{amount_cents: int, expected_month: ?string}>): InstallmentPlan` |
| `RemoveInstallmentPlan` | `(User, AuthorizationEntry): void` |

`SaveInstallmentPlan` cria ou substitui o plano. Recusas: autorização sem saldo; lista vazia ou
com mais de 120 itens; valor menor ou igual a zero; soma diferente do valor autorizado além da
tolerância (a mensagem traz a diferença); menos itens do que a quantidade de pagamentos já vinculados à autorização;
mês inválido.

## Ações reutilizadas, sem mudança de assinatura

`LinkManually`, `ConfirmSuggestion`, `RejectSuggestion`, `RemoveLink`,
`CreateMatchingAuthorization`.

`LinkManually` passa a conferir a autorização por `AuthorizationAvailability`.

## Classes puras (`App\Services\Reconciliation\Matching`)

```php
final class AuthorizationStatement
{
    /**
     * @param  list<StatementEntry>  $links  Vínculos em qualquer ordem
     * @return list<StatementLine>
     */
    public function lines(int $authorizedCents, array $links): array;
}

final class InstallmentSchedule
{
    /**
     * @param  list<array{amount_cents: int, expected_month: ?string}>|null  $plan
     * @param  list<array{id: int, amount_cents: int, paid_on: string}>  $payments  Pagamentos vinculados
     * @param  string|null  $latestProcessedMonth  Y-m-01
     * @return list<ScheduledInstallment>
     */
    public function for(
        int $authorizedCents,
        string $authorizedOn,
        ?string $paymentCondition,
        ?array $plan,
        array $payments,
        ?string $latestProcessedMonth,
        EngineParameters $parameters,
    ): array;

    /** @return list<int> Valores das parcelas em aberto, com repetição */
    public function openAmounts(array $installments): array;
}

final class PaymentConditionParser
{
    public function installments(?string $condition): ?int;   // já existe

    /** @return list<int>|null Prazos em dias, quando a condição os traz */
    public function termDays(?string $condition): ?array;
}
```

`AuthorizationCandidate` ganha `?array $planAmounts = null` (lista de inteiros).

## Serviços

```php
class AuthorizationPanel
{
    /** @return Builder<AuthorizationEntry> Autorizações que o painel mostra */
    public function query(): Builder;

    /** @return array<string, int> Totais do resumo, com os filtros da consulta recebida */
    public function totals(?Builder $filtered = null): array;

    /** @return array{overdue: int, overpaid: int, divergences: int} */
    public function alerts(): array;
}

class AuthorizationAvailability
{
    /** @throws ActionRefusedException */
    public function assertCanReceive(AuthorizationEntry $authorization, PaymentEntry $payment, EngineParameters $parameters): void;

    /** @return Builder<AuthorizationEntry> */
    public function forPayment(PaymentEntry $payment, EngineParameters $parameters): Builder;
}

class InstallmentForecaster
{
    public function refresh(AuthorizationEntry $authorization): ?AuthorizationForecast;

    /** Recalcula todas as autorizações com saldo e parcelas previstas. */
    public function refreshAll(): void;
}
```

## Quem chama o `InstallmentForecaster`

| Momento | Chamada |
|---------|---------|
| Vincular e desvincular (`ReconciliationLinker`) | `refresh` da autorização |
| Salvar e remover plano | `refresh` da autorização |
| Fim da execução (`MatchResultWriter`) | `refreshAll` |
| Reabertura (`ReopenSession`, depois de descartar o resultado) | `refreshAll` |
| Comando `conciliation:refresh-forecasts` (depois da migration, ou para refazer o cache) | `refreshAll` |

## Autorização

Sem gate novo. O painel e suas ações usam o acesso de usuário autenticado e a policy de sessão
(`view`) já existente, aplicada à sessão do pagamento em cada ação. `create-matching-authorization`
continua restrito ao Administrador.

## Auditoria

| Ação | Caso | Entidade | Antes | Depois |
|------|------|----------|-------|--------|
| Salvar plano | `InstallmentPlanSaved` | `InstallmentPlan` | parcelas anteriores, ou nulo | parcelas novas |
| Remover plano | `InstallmentPlanRemoved` | `InstallmentPlan` | parcelas | nulo |
