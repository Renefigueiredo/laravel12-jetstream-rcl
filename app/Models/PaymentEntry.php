<?php

namespace App\Models;

use App\Enums\OperatingUnit;
use Database\Factories\PaymentEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentEntry extends Model
{
    /** @use HasFactory<PaymentEntryFactory> */
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
        'unit',
        'supplier_name',
        'amount_cents',
        'obligation_amount_cents',
        'paid_on',
        'operation_code',
        'operation_name',
        'species',
        'transaction_type',
        'obligation_number',
        'source_document',
        'settlement_status',
        'account_movement',
        'card',
        'identity_key',
        'raw',
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
     * @return HasOne<ReconciliationLink, $this>
     */
    public function link(): HasOne
    {
        return $this->hasOne(ReconciliationLink::class);
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
            'unit' => OperatingUnit::class,
            'amount_cents' => 'integer',
            'obligation_amount_cents' => 'integer',
            'paid_on' => 'date',
            'raw' => 'array',
        ];
    }
}
