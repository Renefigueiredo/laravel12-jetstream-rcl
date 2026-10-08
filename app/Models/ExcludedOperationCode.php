<?php

namespace App\Models;

use App\Enums\ExcludedCodeSource;
use Database\Factories\ExcludedOperationCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExcludedOperationCode extends Model
{
    /** @use HasFactory<ExcludedOperationCodeFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'description',
        'source',
        'excluded_code_import_id',
        'created_by',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<ExcludedCodeImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(ExcludedCodeImport::class, 'excluded_code_import_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => ExcludedCodeSource::class,
        ];
    }
}
