<?php

namespace App\Models;

use App\Enums\ImportFileStatus;
use App\Enums\ImportSlot;
use App\Enums\ReconciliationRunStatus;
use App\Enums\SessionStatus;
use Database\Factories\ReconciliationSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReconciliationSession extends Model
{
    /** @use HasFactory<ReconciliationSessionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'period',
        'status',
        'created_by',
        'first_processed_at',
        'processing_started_at',
        'processed_at',
        'progress',
        'result_stale',
        'last_failure',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
        'result_stale' => false,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ImportFile, $this>
     */
    public function importFiles(): HasMany
    {
        return $this->hasMany(ImportFile::class);
    }

    /**
     * @return HasMany<ImportFile, $this>
     */
    public function activeFiles(): HasMany
    {
        return $this->importFiles()->where('status', ImportFileStatus::Active);
    }

    /**
     * @return HasMany<ImportAttempt, $this>
     */
    public function importAttempts(): HasMany
    {
        return $this->hasMany(ImportAttempt::class);
    }

    /**
     * @return HasMany<ReconciliationRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ReconciliationRun::class);
    }

    /**
     * The run whose result is in effect: the latest completed one.
     *
     * @return HasOne<ReconciliationRun, $this>
     */
    public function currentRun(): HasOne
    {
        return $this->hasOne(ReconciliationRun::class)
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('status', ReconciliationRunStatus::Completed));
    }

    /**
     * The cards named by the authorizations or found in the card invoices of the session.
     *
     * @return list<string>
     */
    public function cards(): array
    {
        return AuthorizationEntry::query()
            ->where('reconciliation_session_id', $this->id)
            ->whereNotNull('card')
            ->toBase()
            ->select('card')
            ->union(PaymentEntry::query()
                ->where('reconciliation_session_id', $this->id)
                ->whereNotNull('card')
                ->toBase()
                ->select('card'))
            ->pluck('card')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Slots that still have no active file.
     *
     * @return list<ImportSlot>
     */
    public function missingSlots(): array
    {
        $loadedSlots = $this->activeFiles->map(fn (ImportFile $file): string => $file->slot->value)->all();

        return array_values(array_filter(
            ImportSlot::cases(),
            fn (ImportSlot $slot): bool => ! in_array($slot->value, $loadedSlots, true),
        ));
    }

    public function isOpen(): bool
    {
        return $this->status === SessionStatus::Open;
    }

    public function hasEverBeenProcessed(): bool
    {
        return $this->first_processed_at !== null;
    }

    /**
     * The reference month and year, as shown to users (05/2026).
     */
    public function periodLabel(): string
    {
        return $this->period->format('m/Y');
    }

    /**
     * The text that identifies the session in lists and in the audit trail.
     */
    public function label(): string
    {
        return __('conciliation.sessions.label', ['number' => $this->id, 'period' => $this->periodLabel()]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => 'date',
            'status' => SessionStatus::class,
            'first_processed_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
            'progress' => 'integer',
            'result_stale' => 'boolean',
        ];
    }
}
