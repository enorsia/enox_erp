<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityEcomUser extends Model
{
    protected $table = 'activity_ecom_user';

    protected $fillable = [
        'session_id',
        'visitor_id',
        'user_id',
        'user_name',
        'user_email',
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
        'last_active_at',
        'session_duration_seconds',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_logged_in' => 'boolean',
            'last_active_at' => 'datetime',
            'session_duration_seconds' => 'integer',
        ];
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ActivityEcomUserAction::class, 'session_id', 'session_id');
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
