<?php

namespace App\Models;

use App\Enums\ImportFileStatus;
use App\Enums\ImportSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportFile extends Model
{
    /** @use HasFactory<\Database\Factories\ImportFileFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reconciliation_session_id',
        'slot',
        'status',
        'original_name',
        'disk',
        'path',
        'size_bytes',
        'sha256',
        'sheet_count',
        'missing_columns',
        'rows_imported',
        'rows_skipped_value',
        'rows_skipped_existing',
        'rows_out_of_period',
        'min_date',
        'max_date',
        'period_divergence',
        'divergence_confirmed_by',
        'divergence_confirmed_at',
        'uploaded_by',
        'replaced_at',
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return HasMany<AuthorizationEntry, $this>
     */
    public function authorizationEntries(): HasMany
    {
        return $this->hasMany(AuthorizationEntry::class);
    }

    /**
     * @return HasMany<PaymentEntry, $this>
     */
    public function paymentEntries(): HasMany
    {
        return $this->hasMany(PaymentEntry::class);
    }

    /**
     * @param  Builder<ImportFile>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ImportFileStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === ImportFileStatus::Active;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot' => ImportSlot::class,
            'status' => ImportFileStatus::class,
            'missing_columns' => 'array',
            'min_date' => 'date',
            'max_date' => 'date',
            'period_divergence' => 'boolean',
            'divergence_confirmed_at' => 'datetime',
            'replaced_at' => 'datetime',
        ];
    }
}
