<?php

namespace App\Models;

use App\Enums\MatchClassification;
use App\Enums\SuggestionStatus;
use Database\Factories\ReconciliationSuggestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationSuggestion extends Model
{
    /** @use HasFactory<ReconciliationSuggestionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reconciliation_run_id',
        'authorization_entry_id',
        'payment_entry_id',
        'classification',
        'score',
        'supplier_score',
        'amount_score',
        'difference_cents',
        'position',
        'is_tie',
        'paid_before_authorization',
        'card_mismatch',
        'status',
        'decided_by',
        'decided_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'position' => 1,
        'is_tie' => false,
        'paid_before_authorization' => false,
        'card_mismatch' => false,
    ];

    /**
     * @return BelongsTo<ReconciliationRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification' => MatchClassification::class,
            'score' => 'integer',
            'supplier_score' => 'integer',
            'amount_score' => 'integer',
            'difference_cents' => 'integer',
            'position' => 'integer',
            'is_tie' => 'boolean',
            'paid_before_authorization' => 'boolean',
            'card_mismatch' => 'boolean',
            'status' => SuggestionStatus::class,
            'decided_at' => 'datetime',
        ];
    }
}
