<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SEO Site Configuration
    |--------------------------------------------------------------------------
    |
    | These values define the default SEO settings for your application.
    | All values can be overridden via environment variables.
    |
    */

    'site_name' => env('SEO_SITE_NAME', config('app.name')),
    'site_url' => env('SEO_SITE_URL', config('app.url')),
    'default_author' => env('SEO_DEFAULT_AUTHOR', null),

    /*
    |--------------------------------------------------------------------------
    | Social Media Configuration
    |--------------------------------------------------------------------------
    |
    | Social media accounts for Open Graph and Twitter Card tags.
    |
    */

    'social' => [
        'linkedin' => env('SEO_LINKEDIN_URL', 'https://www.linkedin.com'),
        'twitter' => env('SEO_TWITTER_URL', null),
        'facebook' => env('SEO_FACEBOOK_URL', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Meta Tags
    |--------------------------------------------------------------------------
    |
    | Default meta tags used across all pages unless overridden.
    |
    */

    'defaults' => [
        'title' => env('SEO_DEFAULT_TITLE', 'ATS Tracker - Free AI-Powered Resume ATS Compatibility Checker'),
        'description' => env('SEO_DEFAULT_DESCRIPTION', 'Free AI-powered ATS resume checker. Upload your resume and get comprehensive feedback on format, keywords, contact information, and overall ATS readiness.'),
        'keywords' => env('SEO_DEFAULT_KEYWORDS', 'ATS checker, resume analyzer, ATS compatibility, resume scanner, free resume checker, AI resume analysis'),
        'robots' => env('SEO_DEFAULT_ROBOTS', 'index, follow'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Open Graph Configuration
    |--------------------------------------------------------------------------
    |
    | Default Open Graph tags for social media sharing.
    |
    */

    'open_graph' => [
        'type' => 'website',
        'site_name' => env('SEO_SITE_NAME', config('app.name')),
        'locale' => env('SEO_OG_LOCALE', 'en_US'),
        'image' => [
            'path' => env('SEO_OG_IMAGE_PATH', '/images/og-default.png'),
            'width' => 1200,
            'height' => 630,
            'alt' => env('SEO_OG_IMAGE_ALT', 'ATS Tracker - Free AI-Powered Resume ATS Compatibility Checker'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Twitter Card Configuration
    |--------------------------------------------------------------------------
    |
    | Default Twitter Card settings.
    |
    */

    'twitter' => [
        'card' => 'summary_large_image',
        'site' => env('SEO_TWITTER_SITE', null),
        'creator' => env('SEO_TWITTER_CREATOR', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Page-Specific SEO Overrides
    |--------------------------------------------------------------------------
    |
    | Define page-specific SEO settings by route name.
    | These will override the defaults for specific pages.
    |
    */

    'pages' => [
        'home' => [
            'title' => 'ATS Tracker - Free AI-Powered Resume ATS Compatibility Checker',
            'description' => 'Free AI-powered ATS resume checker. Upload your resume and get comprehensive feedback on format, keywords, contact information, and overall ATS readiness.',
            'keywords' => 'ATS checker, resume analyzer, ATS compatibility, resume scanner, free resume checker, AI resume analysis',
        ],
        'resume-checker' => [
            'title' => 'Free Resume ATS Checker - AI-Powered Analysis | ATS Tracker',
            'description' => 'Upload your resume for free AI-powered ATS analysis. Get instant feedback on format, keywords, contact info, and ATS compatibility with actionable suggestions.',
            'keywords' => 'resume ATS checker, free resume analyzer, ATS compatibility test, resume format checker, AI resume analysis',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta Description Templates
    |--------------------------------------------------------------------------
    |
    | Templates for generating meta descriptions. Use {value} placeholders
    | that will be replaced with actual content.
    |
    */

    'description_templates' => [
        'home' => 'Free AI-powered ATS resume checker. Upload your resume and get comprehensive feedback on format, keywords, contact information, and overall ATS readiness.',
        'resume-checker' => 'Upload your resume for free AI-powered ATS analysis. Get instant feedback on format, keywords, contact info, and ATS compatibility with actionable suggestions.',
        'default' => 'Free AI-powered ATS resume checker. Get comprehensive feedback on your resume\'s ATS compatibility.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Structured Data Configuration
    |--------------------------------------------------------------------------
    |
    | Default structured data (JSON-LD) settings.
    |
    */

    'structured_data' => [
        'organization' => [
            'name' => env('SEO_SITE_NAME', config('app.name')),
            'url' => env('SEO_SITE_URL', config('app.url')),
            'logo' => env('SEO_ORG_LOGO', null),
        ],
        'website' => [
            'name' => env('SEO_SITE_NAME', config('app.name')),
            'url' => env('SEO_SITE_URL', config('app.url')),
            'description' => env('SEO_DEFAULT_DESCRIPTION', 'Free AI-powered ATS resume checker. Upload your resume and get comprehensive feedback on format, keywords, contact information, and overall ATS readiness.'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap Configuration
    |--------------------------------------------------------------------------
    |
    | Sitemap generation settings.
    |
    */

    'sitemap' => [
        'enabled' => env('SEO_SITEMAP_ENABLED', true),
        'changefreq' => [
            'home' => 'daily',
            'resume-checker' => 'weekly',
        ],
        'priority' => [
            'home' => 1.0,
            'resume-checker' => 0.9,
        ],
    ],

];
