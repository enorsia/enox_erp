<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Bot Management score threshold
    |--------------------------------------------------------------------------
    |
    | Scores range from 1 (likely bot) to 99 (likely human).
    | Requests with a score below this value are treated as bots.
    |
    */
    'cloudflare_bot_score_threshold' => (int) env('BOT_DETECTION_CF_SCORE_THRESHOLD', 30),

    /*
    |--------------------------------------------------------------------------
    | Known bot / script User-Agent substrings (case-insensitive)
    |--------------------------------------------------------------------------
    */
    'known_bot_user_agents' => [
        'Googlebot',
        'Google-InspectionTool',
        'AdsBot-Google',
        'Mediapartners-Google',
        'Bingbot',
        'BingPreview',
        'Slurp',
        'DuckDuckBot',
        'Baiduspider',
        'YandexBot',
        'Sogou',
        'AhrefsBot',
        'SemrushBot',
        'MJ12bot',
        'DotBot',
        'PetalBot',
        'Applebot',
        'facebookexternalhit',
        'Facebot',
        'Twitterbot',
        'LinkedInBot',
        'Pinterestbot',
        'Discordbot',
        'TelegramBot',
        'WhatsApp',
        'Slackbot',
        'GPTBot',
        'ClaudeBot',
        'anthropic-ai',
        'CCBot',
        'python-requests',
        'curl/',
        'Wget/',
        'Scrapy',
        'Go-http-client',
        'Java/',
        'libwww-perl',
        'HttpClient',
        'okhttp',
        'HeadlessChrome',
        'PhantomJS',
        'Puppeteer',
        'Playwright',
        'Selenium',
        'Bytespider',
        'DataForSeoBot',
        'rogerbot',
        'SeznamBot',
        'MauiBot',
    ],

];
