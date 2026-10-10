<?php

namespace App\Models;

use App\Enums\DifferenceTreatment;
use App\Enums\DifferenceType;
use App\Enums\JustificationCategory;
use App\Enums\LinkOrigin;
use App\Enums\MatchClassification;
use Database\Factories\ReconciliationLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationLink extends Model
{
    /** @use HasFactory<ReconciliationLinkFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'authorization_entry_id',
        'payment_entry_id',
        'reconciliation_run_id',
        'origin',
        'is_installment',
        'engine_classification',
        'score',
        'supplier_score',
        'amount_score',
        'difference_type',
        'difference_cents',
        'excess_cents',
        'treatment',
        'discount_cents',
        'tolerance_writeoff_cents',
        'surcharge_cap_basis_points',
        'justification_category',
        'justification',
        'paid_before_authorization',
        'card_mismatch',
        'decided_by',
        'decided_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_installment' => false,
        'difference_cents' => 0,
        'excess_cents' => 0,
        'discount_cents' => 0,
        'tolerance_writeoff_cents' => 0,
        'paid_before_authorization' => false,
        'card_mismatch' => false,
    ];

    /**
     * @return BelongsTo<AuthorizationEntry, $this>
     */
    public function authorization(): BelongsTo
    {
        return $this->belongsTo(AuthorizationEntry::class, 'authorization_entry_id');
    }

    /**
     * @return BelongsTo<PaymentEntry, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentEntry::class, 'payment_entry_id');
    }

    /**
     * @return BelongsTo<ReconciliationRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

    /**
     * An authorization created in the reconciliation exists only for its payment: undoing its
     * single link removes the authorization too.
     */
    public function undoingDeletesTheAuthorization(): bool
    {
        return $this->authorization->isCreatedInReconciliation()
            && ($this->authorization->state?->links_count ?? 0) <= 1;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * A link carries a human decision once someone confirmed it, created it or treated its difference.
     */
    public function hasHumanDecision(): bool
    {
        return $this->decided_by !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => LinkOrigin::class,
            'is_installment' => 'boolean',
            'engine_classification' => MatchClassification::class,
            'score' => 'integer',
            'supplier_score' => 'integer',
            'amount_score' => 'integer',
            'difference_type' => DifferenceType::class,
            'difference_cents' => 'integer',
            'excess_cents' => 'integer',
            'treatment' => DifferenceTreatment::class,
            'discount_cents' => 'integer',
            'tolerance_writeoff_cents' => 'integer',
            'surcharge_cap_basis_points' => 'integer',
            'justification_category' => JustificationCategory::class,
            'paid_before_authorization' => 'boolean',
            'card_mismatch' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }
}
