<?php

namespace App\Models;

use App\Support\VisitorClassificationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityEcomUserBotContext extends Model
{
    protected $table = 'activity_ecom_user_bot_context';

    protected $fillable = [
        'session_id',
        'client_ip',
        'user_agent',
        'ip_country',
        'cf_ray',
        'cf_bot_score',
        'is_bot',
        'bot_confidence',
        'bot_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'cf_bot_score' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ActivityEcomUser::class, 'session_id', 'session_id');
    }

    public function getVisitorTypeLabelAttribute(): string
    {
        return $this->is_bot ? 'Bot' : 'Human';
    }

    public function getVisitorTypeBadgeClassAttribute(): string
    {
        if ($this->is_bot) {
            return match ($this->bot_confidence) {
                'high' => 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-600',
                'medium' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/50',
                default => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600',
            };
        }

        return match ($this->bot_confidence) {
            'medium' => 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/50',
            default => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/50',
        };
    }

    public function getCountryLabelAttribute(): ?string
    {
        if (! $this->ip_country) {
            return null;
        }

        return $this->ip_country;
    }

    public function getIsUkVisitorAttribute(): bool
    {
        return strtoupper((string) $this->ip_country) === 'GB';
    }

    public function getResolvedClientIpAttribute(): ?string
    {
        return $this->client_ip ?: $this->session?->ip;
    }

    public function getMarketerTypeLabelAttribute(): string
    {
        return VisitorClassificationLabels::typeLabel($this->is_bot ? 'bot' : 'human');
    }

    public function getMarketerReasonLabelAttribute(): string
    {
        return VisitorClassificationLabels::reason($this->bot_reason, $this->is_bot)['headline'];
    }

    public function getMarketerReasonHelpAttribute(): string
    {
        return VisitorClassificationLabels::reason($this->bot_reason, $this->is_bot)['help'];
    }

    public function getMarketerConfidenceLabelAttribute(): ?string
    {
        return VisitorClassificationLabels::confidenceLabel($this->bot_confidence);
    }

    public function getMarketerCountryLabelAttribute(): ?string
    {
        return VisitorClassificationLabels::countryLabel($this->ip_country);
    }

    public function getMarketerTrustScoreLabelAttribute(): string
    {
        return VisitorClassificationLabels::trustScoreLabel($this->cf_bot_score);
    }

    public function getMarketerUserAgentHintAttribute(): ?string
    {
        return VisitorClassificationLabels::userAgentHint($this->user_agent);
    }

    public function getMarketerTypeBadgeClassAttribute(): string
    {
        return VisitorClassificationLabels::typeBadgeClass($this->is_bot ? 'bot' : 'human');
    }
}
