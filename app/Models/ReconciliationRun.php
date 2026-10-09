<?php

namespace App\Models;

use App\Enums\ReconciliationRunStatus;
use Database\Factories\ReconciliationRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationRun extends Model
{
    /** @use HasFactory<ReconciliationRunFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reconciliation_session_id',
        'status',
        'requested_by',
        'tolerance_cents',
        'tolerance_basis_points',
        'tolerance_cap_cents',
        'surcharge_cap_basis_points',
        'automatic_threshold',
        'suggestion_threshold',
        'supplier_threshold',
        'lookback_months',
        'excluded_codes',
        'totals',
        'started_at',
        'finished_at',
    ];

    /**
     * @return BelongsTo<ReconciliationSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ReconciliationSession::class, 'reconciliation_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return HasMany<ReconciliationLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(ReconciliationLink::class);
    }

    /**
     * @return HasMany<ReconciliationSuggestion, $this>
     */
    public function suggestions(): HasMany
    {
        return $this->hasMany(ReconciliationSuggestion::class);
    }

    /**
     * @return HasMany<ReconciliationSkip, $this>
     */
    public function skips(): HasMany
    {
        return $this->hasMany(ReconciliationSkip::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReconciliationRunStatus::class,
            'tolerance_cents' => 'integer',
            'tolerance_basis_points' => 'integer',
            'tolerance_cap_cents' => 'integer',
            'surcharge_cap_basis_points' => 'integer',
            'automatic_threshold' => 'integer',
            'suggestion_threshold' => 'integer',
            'supplier_threshold' => 'integer',
            'lookback_months' => 'integer',
            'excluded_codes' => 'array',
            'totals' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
