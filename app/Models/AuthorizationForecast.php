<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What is foreseen for an authorization paid in instalments. A cache derived from the plan, the
 * payment condition and the links: it can be rebuilt at any time.
 */
class AuthorizationForecast extends Model
{
    public const CREATED_AT = null;

    public $incrementing = false;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'authorization_entry_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'authorization_entry_id',
        'expected_count',
        'paid_count',
        'overdue_count',
        'next_expected_month',
        'from_plan',
    ];

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
            'expected_count' => 'integer',
            'paid_count' => 'integer',
            'overdue_count' => 'integer',
            'next_expected_month' => 'date',
            'from_plan' => 'boolean',
        ];
    }
}
