<?php

namespace App\Support;

class UserAgentParser
{
    /**
     * @return array{device_type: string, browser: string, os: string}
     */
    public static function parse(?string $userAgent): array
    {
        $ua = $userAgent ?? '';

        return [
            'device_type' => self::deviceType($ua),
            'browser' => self::browser($ua),
            'os' => self::os($ua),
        ];
    }

    private static function deviceType(string $ua): string
    {
        if (preg_match('/ipad|tablet|playbook|silk/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/mobile|android|iphone|ipod|blackberry|phone/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    private static function browser(string $ua): string
    {
        $patterns = [
            'Edge' => '/Edg\//i',
            'Chrome' => '/Chrome\//i',
            'Firefox' => '/Firefox\//i',
            'Safari' => '/Safari\//i',
            'Opera' => '/OPR\//i',
            'IE' => '/MSIE|Trident/i',
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $ua)) {
                if ($name === 'Safari' && preg_match('/Chrome\//i', $ua)) {
                    continue;
                }

                return $name;
            }
        }

        return 'Unknown';
    }

    private static function os(string $ua): string
    {
        $patterns = [
            'iOS' => '/iPhone|iPad|iPod/i',
            'Android' => '/Android/i',
            'Windows' => '/Windows/i',
            'macOS' => '/Macintosh|Mac OS X/i',
            'Linux' => '/Linux/i',
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }

        return 'Unknown';
    }
}
