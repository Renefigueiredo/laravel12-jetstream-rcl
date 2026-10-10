<?php

namespace App\Models;

use App\Enums\AuthorizationStatus;
use App\Services\Reconciliation\Matching\PaymentConditionParser;
use Database\Factories\AuthorizationEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AuthorizationEntry extends Model
{
    /** @use HasFactory<AuthorizationEntryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'import_file_id',
        'reconciliation_session_id',
        'row_number',
        'request',
        'supplier_name',
        'amount_cents',
        'authorized_on',
        'payment_method',
        'card',
        'payment_condition',
        'identity_key',
        'raw',
        'created_by',
        'source_payment_entry_id',
    ];

    /**
     * @return BelongsTo<ImportFile, $this>
     */
    public function importFile(): BelongsTo
    {
        return $this->belongsTo(ImportFile::class);
    }

    /**
     * @return BelongsTo<ReconciliationSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ReconciliationSession::class, 'reconciliation_session_id');
    }

    /**
     * @return HasOne<AuthorizationState, $this>
     */
    public function state(): HasOne
    {
        return $this->hasOne(AuthorizationState::class);
    }

    /**
     * @return HasMany<ReconciliationLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(ReconciliationLink::class);
    }

    /**
     * The plan of instalments the operator informed. It follows the identity of the
     * authorization, so it survives the spreadsheet being replaced.
     *
     * @return HasOne<InstallmentPlan, $this>
     */
    public function plan(): HasOne
    {
        return $this->hasOne(InstallmentPlan::class, 'authorization_identity_key', 'identity_key');
    }

    /**
     * @return HasOne<AuthorizationForecast, $this>
     */
    public function forecast(): HasOne
    {
        return $this->hasOne(AuthorizationForecast::class);
    }

    /**
     * @return HasMany<ReconciliationSuggestion, $this>
     */
    public function suggestions(): HasMany
    {
        return $this->hasMany(ReconciliationSuggestion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<PaymentEntry, $this>
     */
    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(PaymentEntry::class, 'source_payment_entry_id');
    }

    /**
     * Instalments foreseen by the payment condition as it was informed, when it is recognised.
     */
    public function foreseenInstallments(): ?int
    {
        return app(PaymentConditionParser::class)->installments($this->payment_condition);
    }

    /**
     * The condition as informed, followed by the instalments it foresees when there is more than one.
     */
    public function paymentConditionLabel(): ?string
    {
        $installments = $this->foreseenInstallments();

        if (blank($this->payment_condition)) {
            return null;
        }

        return $installments !== null && $installments > 1
            ? __('conciliation.reconciliation.columns.condition_with_installments', ['condition' => $this->payment_condition, 'count' => $installments])
            : $this->payment_condition;
    }

    /**
     * An authorization without a source file was created during the reconciliation.
     */
    public function isCreatedInReconciliation(): bool
    {
        return $this->import_file_id === null;
    }

    /**
     * What is still to be paid; derived from the linked payments, never edited.
     */
    public function balanceCents(): int
    {
        return $this->state?->balance_cents ?? $this->amount_cents;
    }

    public function status(): AuthorizationStatus
    {
        return $this->state?->status ?? AuthorizationStatus::Open;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'authorized_on' => 'date',
            'raw' => 'array',
        ];
    }
}
