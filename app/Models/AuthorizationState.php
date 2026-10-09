<?php

namespace App\Models;

use App\Enums\AuthorizationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationState extends Model
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
        'links_count',
        'paid_cents',
        'discount_cents',
        'writeoff_cents',
        'balance_cents',
        'status',
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
            'links_count' => 'integer',
            'paid_cents' => 'integer',
            'discount_cents' => 'integer',
            'writeoff_cents' => 'integer',
            'balance_cents' => 'integer',
            'status' => AuthorizationStatus::class,
        ];
    }
}
