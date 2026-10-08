<?php

namespace App\Models;

use App\Enums\ImportAttemptStatus;
use App\Enums\ImportSlot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ImportAttempt extends Model
{
    /** @use HasFactory<\Database\Factories\ImportAttemptFactory> */
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
        'user_id',
        'original_name',
        'disk',
        'path',
        'size_bytes',
        'sha256',
        'progress',
        'sheet_count',
        'missing_columns',
        'rows_total',
        'rows_valid',
        'rows_skipped_value',
        'rows_out_of_period',
        'min_date',
        'max_date',
        'error_count',
        'first_errors',
        'error_report_path',
        'message',
        'divergence_confirmed_by',
        'divergence_confirmed_at',
        'import_file_id',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'progress' => 0,
        'error_count' => 0,
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ImportFile, $this>
     */
    public function importFile(): BelongsTo
    {
        return $this->belongsTo(ImportFile::class);
    }

    /**
     * Absolute path of the received file, as needed by the spreadsheet reader.
     */
    public function absolutePath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    /**
     * Remove the received file, which is not the definitive copy.
     */
    public function deleteReceivedFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }

    public function deleteErrorReport(): void
    {
        if ($this->error_report_path !== null) {
            Storage::disk($this->disk)->delete($this->error_report_path);
        }
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
            'status' => ImportAttemptStatus::class,
            'missing_columns' => 'array',
            'min_date' => 'date',
            'max_date' => 'date',
            'first_errors' => 'array',
            'divergence_confirmed_at' => 'datetime',
        ];
    }
}
