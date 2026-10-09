<?php

namespace App\Models;

use App\Enums\PendingItemKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A row of the pending list of a processed session. Backed by a database view: read only.
 */
class PendingItem extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'reconciliation_pending_items';

    /**
     * The data type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::saving(function (): never {
            throw new LogicException('Pending items are derived and cannot be written.');
        });

        static::deleting(function (): never {
            throw new LogicException('Pending items are derived and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<ReconciliationSuggestion, $this>
     */
    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(ReconciliationSuggestion::class, 'suggestion_id');
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
            'kind' => PendingItemKind::class,
            'score' => 'integer',
            'difference_cents' => 'integer',
            'paid_before_authorization' => 'boolean',
            'card_mismatch' => 'boolean',
        ];
    }
}
