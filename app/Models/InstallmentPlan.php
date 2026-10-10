<?php

namespace App\Models;

use Database\Factories\InstallmentPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentPlan extends Model
{
    /** @use HasFactory<InstallmentPlanFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'authorization_identity_key',
        'created_by',
        'updated_by',
    ];

    /**
     * @return HasMany<InstallmentPlanItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InstallmentPlanItem::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The instalments as the pure schedule reads them.
     *
     * @return list<array{amount_cents: int, expected_month: string|null}>
     */
    public function toSchedule(): array
    {
        return $this->items
            ->map(fn (InstallmentPlanItem $item): array => [
                'amount_cents' => $item->amount_cents,
                'expected_month' => $item->expected_month?->toDateString(),
            ])
            ->values()
            ->all();
    }
}
