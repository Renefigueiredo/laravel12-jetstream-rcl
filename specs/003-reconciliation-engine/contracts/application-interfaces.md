# Contract: Interfaces da aplicação

O que este módulo implementa dos contratos anteriores, o que consome e o que oferece.

## Implementado: ReconciliationEngine (do Módulo 1)

```php
class DatabaseReconciliationEngine implements ReconciliationEngine
{
    /** @param  callable(int $percent): void  $reportProgress */
    public function run(ReconciliationSession $session, callable $reportProgress): void;

    public function discardResult(ReconciliationSession $session): void;
}
```

`run`:

- roda sob a trava global `conciliation:engine`, esperando por até 10 minutos; sem a trava,
  falha com mensagem clara;
- cria a execução `Running` com os parâmetros e a fotografia dos códigos excluídos;
- lê, calcula em memória e informa o andamento (10, 30, 60, 90);
- grava vínculos, sugestões, pulados, situações de autorização e totais em uma transação, e muda
  a execução para `Completed`, com o registro de auditoria `ReconciliationCompleted`;
- em caso de exceção, muda a execução para `Failed` e relança; nada do resultado fica gravado.

`discardResult`:

- apaga os vínculos sem decisão humana das execuções da sessão e recalcula as autorizações
  afetadas, inclusive as de sessões anteriores;
- apaga sugestões e pulados dessas execuções;
- muda as execuções `Completed` ou `Running` da sessão para `Discarded`;
- não toca em vínculos com decisão humana (a reabertura já foi bloqueada antes, se existiam) nem
  em `reconciliation_pair_blocks`;
- é idempotente.

## Implementado: ReconciliationResultInspector (do Módulo 1)

```php
class ReconciliationDecisionInspector implements ReconciliationResultInspector
{
    public function blockingDecisionCount(ReconciliationSession $session): int;
}
```

Soma, contadas no banco:

1. vínculos com `decided_by` preenchido cujo pagamento é da sessão;
2. vínculos de qualquer origem cuja autorização é da sessão e cujo pagamento é de outra sessão.

## Consumido: ExcludedOperationCodes (do Módulo 5)

O motor chama `snapshot()` uma vez, no início de `run`, guarda `codes()` na execução e usa
`contains()` para cada pagamento. Cumpre as obrigações listadas em
[specs/002-excluded-operation-codes/contracts/application-interfaces.md](../../002-excluded-operation-codes/contracts/application-interfaces.md).

## Núcleo puro: Matcher

Sem banco, sem relógio, sem configuração global. É o alvo dos testes de Unit do Princípio III.

```php
final class Matcher
{
    /**
     * @param  list<AuthorizationCandidate>  $authorizations
     * @param  list<PaymentCandidate>  $payments
     * @param  list<array{authorization: string, unit: string, payment: string}>  $blockedPairs  chaves de identidade
     * @param  callable(int $percent): void  $reportProgress
     */
    public function match(array $authorizations, array $payments, array $blockedPairs, EngineParameters $parameters, callable $reportProgress): MatchResult;
}
```

Componentes, cada um com seus testes de fronteira (ver [matching-rules.md](./matching-rules.md)):

| Classe | Responsabilidade |
|--------|------------------|
| `SupplierNameNormalizer` | normalizar o nome |
| `SupplierSimilarity` | compatibilidade do fornecedor, de 0 a 100, simétrica |
| `PairScorer` | compatibilidade do valor, nota e classificação de um par |
| `PaymentConditionParser` | parcelas previstas |
| `CardNumberExtractor` | cartão do pagamento, a partir da espécie |
| `PaymentMethodMatcher` | cartão diferente; desempate pelo cartão e pela forma de pagamento |
| `EngineParameters` | parâmetros e tolerância efetiva |

## Serviços de gravação

```php
class ReconciliationLinker
{
    /** Cria o vínculo, recalcula a autorização e resolve as sugestões afetadas. Exige transação aberta. */
    public function link(AuthorizationEntry $authorization, PaymentEntry $payment, LinkAttributes $attributes): ReconciliationLink;

    /** Desfaz o vínculo e recalcula a autorização. Exige transação aberta. */
    public function unlink(ReconciliationLink $link): void;
}

class AuthorizationStateCalculator
{
    /** Recalcula `authorization_states` a partir dos vínculos, com a autorização travada. */
    public function recalculate(int $authorizationEntryId): AuthorizationState|null;
}
```

- Toda criação ou remoção de vínculo, pelo motor ou por ação do usuário, passa por
  `ReconciliationLinker`.
- `link` trava a autorização, lê o saldo, calcula tipo de diferença, valor excedido e resto
  absorvido pela tolerância, e recusa pagamento já vinculado.
- A tolerância usada é a da execução vigente da sessão do pagamento
  (`EngineParameters::fromRun()`); o teto de acréscimo é o da configuração vigente na decisão.
- `unlink` devolve a `Pending` as sugestões `Superseded` da mesma autorização e do mesmo
  pagamento, na execução vigente, quando as duas pontas ficam livres e o par não está bloqueado.

## Ações (`app/Actions/Conciliation`)

| Ação | Assinatura resumida |
|------|---------------------|
| `ConfirmSuggestion` | `(User, ReconciliationSuggestion, ?DifferenceTreatment, ?JustificationCategory, ?string $justification): ReconciliationLink` |
| `RejectSuggestion` | `(User, ReconciliationSuggestion): void` |
| `LinkManually` | `(User, AuthorizationEntry, PaymentEntry, ?DifferenceTreatment, ?JustificationCategory, ?string $justification): ReconciliationLink` |
| `RemoveLink` | `(User, ReconciliationLink): void` |
| `CloseAuthorizationWithDiscount` | `(User, AuthorizationEntry, JustificationCategory, string $justification): ReconciliationLink` |
| `CreateMatchingAuthorization` | `(User, PaymentEntry): ReconciliationLink` |
| `UpdateReconciliationSettings` | `(User, int $toleranceCents, ?int $toleranceBasisPoints, int $surchargeCapBasisPoints): ReconciliationSettings` |

Todas: autorizam, abrem transação, gravam auditoria e lançam `ActionRefusedException` nas recusas.

## Autorização

```php
Gate::define('configure-tolerance', fn (User $user): bool => $user->hasPermission(UserPermission::ConfigureTolerance));
```

Executar e decidir pendências usa a policy de sessão já existente (`view`, `execute`), válida para
Operador e Administrador (FR-041).

## Mudança em contrato do Módulo 1

`ReopenSession` passa a chamar `ReconciliationEngine::discardResult()` dentro da transação da
reabertura, depois de conferir `blockingDecisionCount()`. O comentário do contrato do Módulo 1
("chamado antes de `run` e em caso de falha") ganha "e na reabertura".
