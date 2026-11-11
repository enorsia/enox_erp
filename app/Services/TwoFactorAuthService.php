<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTrustedDevice;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthService
{
    public const COOKIE_NAME = 'enox_trusted_device';

    public const SESSION_USER_KEY = 'login.two_factor.user_id';

    public const SESSION_REMEMBER_KEY = 'login.two_factor.remember';

    public const SESSION_VERIFIED_KEY = 'two_factor_authenticated';

    public function __construct(
        protected Google2FA $google2fa
    ) {}

    public function requiresTwoFactor(User $user): bool
    {
        return (bool) $user->two_factor_enabled;
    }

    public function isEnrolled(User $user): bool
    {
        return $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function verifyCode(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        return (bool) $this->google2fa->verifyKey(
            decrypt($user->two_factor_secret),
            preg_replace('/\D/', '', $code) ?: '',
            1
        );
    }

    public function provisioningUri(User $user, ?string $secret = null): string
    {
        $secret = $secret ?? ($user->two_factor_secret ? decrypt($user->two_factor_secret) : $this->generateSecret());

        return $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );
    }

    public function qrCodeDataUri(string $otpAuthUrl, int $size = 180): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 0),
            new SvgImageBackEnd()
        );
        $svg = (new Writer($renderer))->writeString($otpAuthUrl);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function assignSecret(User $user, string $secret): void
    {
        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function markConfirmed(User $user): void
    {
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    public function trustedDeviceTokenFromRequest(Request $request): ?string
    {
        return $request->cookie(self::COOKIE_NAME);
    }

    public function hasValidTrustedDevice(User $user, ?string $cookieValue): bool
    {
        if (! $cookieValue || ! $user->two_factor_enabled) {
            return false;
        }

        [$cookieUserId, $plainToken] = $this->parseTrustedCookie($cookieValue);
        if ((int) $cookieUserId !== (int) $user->id || ! $plainToken) {
            return false;
        }

        $hash = hash('sha256', $plainToken);

        $device = UserTrustedDevice::query()
            ->where('user_id', $user->id)
            ->where('token_hash', $hash)
            ->first();

        if (! $device) {
            return false;
        }

        if ($user->two_factor_devices_reset_at && $device->created_at->lte($user->two_factor_devices_reset_at)) {
            return false;
        }

        $device->forceFill(['last_used_at' => now()])->save();

        return true;
    }

    public function rememberDevice(User $user, Request $request): void
    {
        $plain = Str::random(64);
        $hash = hash('sha256', $plain);

        UserTrustedDevice::query()->create([
            'user_id' => $user->id,
            'token_hash' => $hash,
            'label' => $this->deviceLabel($request),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'last_used_at' => now(),
        ]);

        $payload = $user->id.'|'.$plain;

        Cookie::queue(Cookie::make(
            self::COOKIE_NAME,
            $payload,
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        ));
    }

    public function revokeAllTrustedDevices(User $user): void
    {
        UserTrustedDevice::query()->where('user_id', $user->id)->delete();
        $user->forceFill(['two_factor_devices_reset_at' => now()])->save();
    }

    public function markSessionVerified(): void
    {
        session([self::SESSION_VERIFIED_KEY => true]);
    }

    public function clearSessionVerified(): void
    {
        session()->forget(self::SESSION_VERIFIED_KEY);
    }

    public function isSessionVerified(): bool
    {
        return session(self::SESSION_VERIFIED_KEY, false) === true;
    }

    protected function deviceLabel(Request $request): string
    {
        $ua = (string) $request->userAgent();

        return Str::limit($ua !== '' ? $ua : 'Unknown device', 120, '');
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function parseTrustedCookie(string $cookieValue): array
    {
        if (! str_contains($cookieValue, '|')) {
            return [null, null];
        }

        [$userId, $token] = explode('|', $cookieValue, 2);

        return [$userId, $token];
    }
}
