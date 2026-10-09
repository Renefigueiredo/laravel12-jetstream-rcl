<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationSettings extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'reconciliation_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tolerance_cents',
        'tolerance_basis_points',
        'tolerance_cap_cents',
        'surcharge_cap_basis_points',
        'updated_by',
    ];

    /**
     * The single row with the parameters in effect.
     */
    public static function current(): self
    {
        return self::query()->orderBy('id')->firstOrCreate([], [
            'tolerance_cents' => 50,
            'tolerance_basis_points' => 100,
            'tolerance_cap_cents' => 20000,
            'surcharge_cap_basis_points' => 1000,
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tolerance_cents' => 'integer',
            'tolerance_basis_points' => 'integer',
            'tolerance_cap_cents' => 'integer',
            'surcharge_cap_basis_points' => 'integer',
        ];
    }
}
