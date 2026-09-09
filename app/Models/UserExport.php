<?php

namespace App\Models;

use App\Support\ExportFailureMessage;
use App\Support\ExportTypeRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class UserExport extends Model
{
    public const TYPE_ECOM_ACTIVITY_REPORT = 'ecom_activity_report';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'type',
        'format',
        'filters',
        'status',
        'progress',
        'total_rows',
        'processed_rows',
        'file_path',
        'file_size',
        'sheet_count',
        'error_message',
        'notify_browser',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'notify_browser' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true);
    }

    public function isDownloadReady(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->file_path && Storage::exists($this->file_path);
    }

    public function deleteFile(): void
    {
        if ($this->file_path && Storage::exists($this->file_path)) {
            Storage::delete($this->file_path);
        }
    }

    public function dismiss(): self
    {
        $this->deleteFile();

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => $this->completed_at ?? now(),
            'file_path' => null,
            'file_size' => null,
        ]);

        return $this->fresh();
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function typeLabel(): string
    {
        return ExportTypeRegistry::label($this->type);
    }

    public static function getActiveForUser(int $userId, ?string $type = null): ?self
    {
        $query = self::forUser($userId);

        if ($type) {
            $query->ofType($type);
        }

        $inProgress = (clone $query)
            ->whereIn('status', [self::STATUS_QUEUED, self::STATUS_PROCESSING])
            ->latest()
            ->first();

        if ($inProgress) {
            return $inProgress;
        }

        $completed = (clone $query)
            ->where('status', self::STATUS_COMPLETED)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        if ($completed?->isDownloadReady()) {
            return $completed;
        }

        return null;
    }

    public function downloadDisplayFilename(): string
    {
        $filters = $this->filters ?? [];
        $base = ExportTypeRegistry::filenamePrefix($this->type).'.'.$this->format;

        if (! empty($filters['range_label'])) {
            return $filters['range_label'].' '.$base;
        }

        return $base;
    }

    public function signedDownloadUrl(): ?string
    {
        if (! $this->isDownloadReady()) {
            return null;
        }

        return URL::temporarySignedRoute(
            'admin.exports.download',
            now()->addMinutes(config('exports.download_url_expiry_minutes', 15)),
            ['export' => $this->id]
        );
    }

    public function toFrontendArray(): array
    {
        return [
            'export_id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'status' => $this->status,
            'progress' => $this->progress,
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'format' => $this->format,
            'sheet_count' => $this->sheet_count,
            'file_size' => $this->file_size,
            'error_message' => ExportFailureMessage::forDisplay($this->error_message),
            'download_ready' => $this->isDownloadReady(),
            'download_url' => $this->signedDownloadUrl(),
            'download_filename' => $this->downloadDisplayFilename(),
            'notify_browser' => $this->notify_browser,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'started_at' => optional($this->started_at)?->toIso8601String(),
            'completed_at' => optional($this->completed_at)?->toIso8601String(),
        ];
    }
}
