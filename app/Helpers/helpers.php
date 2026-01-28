<?php

if (!function_exists('cloudflareImage')) {
    function cloudflareImage($imagePath, $width = null)
    {
        if (!$imagePath) return null;
        $cloudflareBaseUrl = config('cloudflare.image_base_url');
        $imageId = basename($imagePath);
        if ($width === null) {
            return $cloudflareBaseUrl . $imageId . '/public';
        } else {
            return $cloudflareBaseUrl . $imageId . '/w=' . $width;
        }
    }
}

if (!function_exists('cloudflareImageWithoutCache')) {
    function cloudflareImageWithoutCache($imagePath, $width = null)
    {
        if (!$imagePath) return null;
        $cloudflareBaseUrl = config('cloudflare.image_base_url');
        $fullUrl = $width == null ? $cloudflareBaseUrl . basename($imagePath) . '/public' : $cloudflareBaseUrl . basename($imagePath) . "/w=" . $width;
        return $fullUrl;
    }
}

if (!function_exists('zeroToString')) {
    function zeroToString($value)
    {
        return $value == 0 ? '0' : $value;
    }
}
