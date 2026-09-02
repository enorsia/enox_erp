<?php

namespace App\Models;

use App\Support\TrackerTime;
use App\Support\VisitorClassificationLabels;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ActivityEcomUser extends Model
{
    protected $table = 'activity_ecom_user';

    protected $fillable = [
        'session_id',
        'visitor_id',
        'user_id',
        'user_name',
        'user_email',
        'user_phone',
        'ip',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'country',
        'city',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'landing_page',
        'is_logged_in',
        'has_add_to_cart',
        'has_begin_checkout',
        'has_proceed_checkout',
        'has_payment_success',
        'max_order_value',
        'first_payment_at',
        'latest_funnel_stage',
        'is_bot',
        'last_active_at',
        'session_duration_seconds',
        'actions_count',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_logged_in' => 'boolean',
            'has_add_to_cart' => 'boolean',
            'has_begin_checkout' => 'boolean',
            'has_proceed_checkout' => 'boolean',
            'has_payment_success' => 'boolean',
            'max_order_value' => 'decimal:2',
            'first_payment_at' => 'datetime',
            'is_bot' => 'boolean',
            'last_active_at' => 'datetime',
            'session_duration_seconds' => 'integer',
            'actions_count' => 'integer',
        ];
    }

    /**
     * Newest activity first. Uses UTC server timestamps so list order is stable
     * when data is imported from production and the app runs on another OS timezone.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrderByLatestActivity(Builder $query): Builder
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return $query
                ->orderByRaw('CASE WHEN COALESCE(updated_at, created_at) >= COALESCE(last_active_at, created_at) THEN COALESCE(updated_at, created_at) ELSE COALESCE(last_active_at, created_at) END DESC')
                ->orderByDesc('id');
        }

        return $query
            ->orderByRaw('GREATEST(COALESCE(updated_at, created_at), COALESCE(last_active_at, created_at)) DESC')
            ->orderByDesc('id');
    }

    /**
     * UTC timestamp for admin display / comparisons (updated_at wins over stale client times).
     */
    public function latestActivityAt(): ?Carbon
    {
        return TrackerTime::latestActivityUtc(
            $this->getRawOriginal('updated_at'),
            $this->getRawOriginal('last_active_at'),
            $this->getRawOriginal('created_at'),
        );
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ActivityEcomUserAction::class, 'session_id', 'session_id');
    }

    public function botContext(): HasOne
    {
        return $this->hasOne(ActivityEcomUserBotContext::class, 'session_id', 'session_id');
    }

    public function firstAction(): HasOne
    {
        return $this->hasOne(ActivityEcomUserAction::class, 'session_id', 'session_id')
            ->oldestOfMany(['created_at', 'id']);
    }

    public function firstRefererAction(): HasOne
    {
        return $this->hasOne(ActivityEcomUserAction::class, 'session_id', 'session_id')
            ->whereNotNull('referer')
            ->where('referer', '!=', '')
            ->oldestOfMany(['created_at', 'id']);
    }

    public function visitorClassification(): string
    {
        if ($this->relationLoaded('botContext')) {
            $context = $this->botContext;
        } else {
            $context = $this->botContext()->first();
        }

        if ($context === null) {
            return 'unclassified';
        }

        return $context->is_bot ? 'bot' : 'human';
    }

    public function getVisitorTypeLabelAttribute(): string
    {
        return match ($this->visitorClassification()) {
            'bot' => 'Bot',
            'human' => 'Human',
            default => 'Unclassified',
        };
    }

    public function getVisitorTypeBadgeClassAttribute(): string
    {
        $classification = $this->visitorClassification();

        if ($classification === 'unclassified') {
            return 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-600';
        }

        return $this->botContext?->visitor_type_badge_class
            ?? 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-600';
    }

    public function getMarketerTypeLabelAttribute(): string
    {
        return VisitorClassificationLabels::typeLabel($this->visitorClassification());
    }

    public function getMarketerTypeBadgeClassAttribute(): string
    {
        return VisitorClassificationLabels::typeBadgeClass($this->visitorClassification());
    }

    public function getMarketerReasonLabelAttribute(): ?string
    {
        $context = $this->botContext;

        if ($context === null) {
            return null;
        }

        return $context->marketer_reason_label;
    }

    public function getMarketerReasonHelpAttribute(): string
    {
        $context = $this->botContext;

        if ($context === null) {
            return VisitorClassificationLabels::unclassifiedHelp();
        }

        return $context->marketer_reason_help;
    }

    public function getMarketerCountryLabelAttribute(): ?string
    {
        if ($this->botContext?->marketer_country_label) {
            return $this->botContext->marketer_country_label;
        }

        return VisitorClassificationLabels::countryLabel($this->country);
    }

    public function getMarketerCountryCodeAttribute(): ?string
    {
        $code = $this->botContext?->ip_country ?? $this->country;

        if (! filled($code)) {
            return null;
        }

        return strtoupper((string) $code);
    }

    public function isRegisteredUser(): bool
    {
        return $this->is_logged_in && filled($this->user_id);
    }

    public function isGuestCheckout(): bool
    {
        return ! $this->isRegisteredUser()
            && (filled($this->user_name) || filled($this->user_email));
    }

    public function isAnonymousGuest(): bool
    {
        return ! $this->isRegisteredUser() && ! $this->isGuestCheckout();
    }

    public function identitySummary(): string
    {
        if ($this->isRegisteredUser()) {
            $name = trim((string) $this->user_name);

            if ($name === '' && filled($this->user_id)) {
                return 'User #'.$this->user_id;
            }

            if (filled($this->user_email)) {
                return $name !== ''
                    ? $name.' ('.$this->user_email.')'
                    : (string) $this->user_email;
            }

            return $name !== '' ? $name : 'User #'.$this->user_id;
        }

        if ($this->isGuestCheckout()) {
            $name = trim((string) ($this->user_name ?: $this->user_email ?: '—'));

            return $name.' (Guest)';
        }

        return 'Guest';
    }

    public function getRouteKeyName(): string
    {
        return 'session_id';
    }
}
