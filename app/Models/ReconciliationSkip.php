<?php

namespace App\Models;

use App\Enums\SkipReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationSkip extends Model
{
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reconciliation_run_id',
        'payment_entry_id',
        'authorization_entry_id',
        'reason',
        'operation_code',
        'original_session_id',
    ];

    /**
     * @return BelongsTo<ReconciliationRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

    /**
     * @return BelongsTo<PaymentEntry, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentEntry::class, 'payment_entry_id');
    }

    /**
     * @return BelongsTo<AuthorizationEntry, $this>
     */
    public function authorization(): BelongsTo
    {
        return $this->belongsTo(AuthorizationEntry::class, 'authorization_entry_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => SkipReason::class,
        ];
    }
}
