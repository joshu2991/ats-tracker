<?php

namespace App\Services;

use App\Data\SEOData;

class SEOService
{
    /**
     * Generate SEO data for a specific page.
     *
     * @param  string  $page  Route name or page identifier
     * @param  array<string, mixed>  $overrides  Page-specific overrides
     */
    public function forPage(string $page, array $overrides = []): SEOData
    {
        $config = config('seo');
        $pageConfig = $config['pages'][$page] ?? [];

        // Get title
        $title = $overrides['title']
            ?? $pageConfig['title']
            ?? $config['defaults']['title'];

        // Get description
        $description = $overrides['description']
            ?? $pageConfig['description']
            ?? $this->generateMetaDescription($page, $overrides);

        // Get keywords
        $keywords = $overrides['keywords']
            ?? $pageConfig['keywords']
            ?? $config['defaults']['keywords'];

        // Get canonical URL
        $canonical = $overrides['canonical'] ?? $this->getCanonicalUrl($page);

        // Generate Open Graph data
        $openGraph = $this->getOpenGraphData([
            'title' => $title,
            'description' => $description,
            'url' => $canonical,
            'image' => $overrides['og_image'] ?? null,
            'type' => $overrides['og_type'] ?? $config['open_graph']['type'],
        ]);

        // Generate Twitter Card data
        $twitter = $this->getTwitterCardData([
            'title' => $title,
            'description' => $description,
            'image' => $overrides['twitter_image'] ?? $openGraph['image'],
        ]);

        // Generate structured data
        $structuredData = $this->generateStructuredData($page, [
            'title' => $title,
            'description' => $description,
            'url' => $canonical,
        ]);

        // Get robots meta
        $robots = $overrides['robots'] ?? $config['defaults']['robots'];

        return new SEOData(
            title: $title,
            description: $description,
            keywords: $keywords,
            canonical: $canonical,
            openGraph: $openGraph,
            twitter: $twitter,
            structuredData: $structuredData,
            robots: $robots
        );
    }

    /**
     * Generate meta description for a page.
     *
     * @param  string  $page  Route name or page identifier
     * @param  array<string, mixed>  $context  Additional context
     */
    public function generateMetaDescription(string $page, array $context = []): string
    {
        $config = config('seo');
        $templates = $config['description_templates'];

        // Get template for page or use default
        $template = $templates[$page] ?? $templates['default'] ?? $config['defaults']['description'];

        // Replace placeholders if any
        $description = str_replace(
            ['{value}', '{site_name}'],
            [$context['value'] ?? '', config('seo.site_name')],
            $template
        );

        // Ensure description is within limits (150-160 chars)
        if (strlen($description) > 160) {
            $description = substr($description, 0, 157).'...';
        }

        return $description;
    }

    /**
     * Get canonical URL for a page.
     *
     * @param  string|null  $page  Route name or page identifier
     */
    public function getCanonicalUrl(?string $page = null): string
    {
        $baseUrl = rtrim(config('seo.site_url'), '/');

        if ($page === null) {
            return $baseUrl;
        }

        // Map route names to URLs
        $routeMap = [
            'home' => '/',
            'resume-checker' => '/resume-checker',
        ];

        $path = $routeMap[$page] ?? '/';

        return $baseUrl.$path;
    }

    /**
     * Generate Open Graph data.
     *
     * @param  array<string, mixed>  $data  Open Graph data
     * @return array<string, mixed>
     */
    public function getOpenGraphData(array $data): array
    {
        $config = config('seo');
        $ogConfig = $config['open_graph'];
        $baseUrl = rtrim(config('seo.site_url'), '/');

        // Build image URL
        $imagePath = $data['image'] ?? $ogConfig['image']['path'];
        $imageUrl = str_starts_with($imagePath, 'http') ? $imagePath : $baseUrl.$imagePath;

        return [
            'title' => $data['title'] ?? $ogConfig['site_name'],
            'description' => $data['description'] ?? config('seo.defaults.description'),
            'image' => $imageUrl,
            'url' => $data['url'] ?? $baseUrl,
            'type' => $data['type'] ?? $ogConfig['type'],
            'site_name' => $ogConfig['site_name'],
            'locale' => $ogConfig['locale'],
            'image:width' => $ogConfig['image']['width'],
            'image:height' => $ogConfig['image']['height'],
            'image:alt' => $ogConfig['image']['alt'],
        ];
    }

    /**
     * Generate Twitter Card data.
     *
     * @param  array<string, mixed>  $data  Twitter Card data
     * @return array<string, mixed>
     */
    public function getTwitterCardData(array $data): array
    {
        $config = config('seo');
        $twitterConfig = $config['twitter'];
        $baseUrl = rtrim(config('seo.site_url'), '/');

        // Build image URL
        $imagePath = $data['image'] ?? config('seo.open_graph.image.path');
        $imageUrl = str_starts_with($imagePath, 'http') ? $imagePath : $baseUrl.$imagePath;

        return [
            'card' => $twitterConfig['card'],
            'title' => $data['title'] ?? config('seo.defaults.title'),
            'description' => $data['description'] ?? config('seo.defaults.description'),
            'image' => $imageUrl,
            'site' => $twitterConfig['site'],
            'creator' => $twitterConfig['creator'],
        ];
    }

    /**
     * Generate structured data (JSON-LD).
     *
     * @param  string  $page  Route name or page identifier
     * @param  array<string, mixed>  $data  Page-specific data
     * @return array<string, mixed>
     */
    public function generateStructuredData(string $page, array $data = []): array
    {
        $structuredData = [];

        // Always include Organization schema
        $structuredData[] = $this->generateOrganizationSchema();

        // Always include WebSite schema
        $structuredData[] = $this->generateWebSiteSchema();

        // Add page-specific WebPage schema
        $structuredData[] = $this->generateWebPageSchema($page, $data);

        return $structuredData;
    }

    /**
     * Generate Organization schema (JSON-LD).
     *
     * @return array<string, mixed>
     */
    public function generateOrganizationSchema(): array
    {
        $config = config('seo');
        $orgConfig = $config['structured_data']['organization'];
        $baseUrl = rtrim(config('seo.site_url'), '/');

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Person', // Personal project, not Organization
            'name' => $orgConfig['name'],
            'url' => $orgConfig['url'],
        ];

        // Add LinkedIn if available
        $linkedin = config('seo.social.linkedin');
        if ($linkedin) {
            $schema['sameAs'] = [$linkedin];
        }

        // Add logo if available
        if ($orgConfig['logo']) {
            $logoUrl = str_starts_with($orgConfig['logo'], 'http') ? $orgConfig['logo'] : $baseUrl.$orgConfig['logo'];
            $schema['image'] = $logoUrl;
        }

        return $schema;
    }

    /**
     * Generate WebSite schema (JSON-LD).
     *
     * @return array<string, mixed>
     */
    public function generateWebSiteSchema(): array
    {
        $config = config('seo');
        $websiteConfig = $config['structured_data']['website'];
        $baseUrl = rtrim(config('seo.site_url'), '/');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $websiteConfig['name'],
            'url' => $websiteConfig['url'],
            'description' => $websiteConfig['description'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $baseUrl.'/resume-checker',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * Generate WebPage schema (JSON-LD).
     *
     * @param  string  $page  Route name or page identifier
     * @param  array<string, mixed>  $data  Page-specific data
     * @return array<string, mixed>
     */
    public function generateWebPageSchema(string $page, array $data = []): array
    {
        $baseUrl = rtrim(config('seo.site_url'), '/');

        // Map route names to page names
        $pageNames = [
            'home' => 'Home',
            'resume-checker' => 'Resume Checker',
        ];

        $pageName = $pageNames[$page] ?? ucfirst(str_replace('-', ' ', $page));

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $data['title'] ?? $pageName,
            'description' => $data['description'] ?? config('seo.defaults.description'),
            'url' => $data['url'] ?? $this->getCanonicalUrl($page),
        ];

        // Add breadcrumb if applicable
        if ($page !== 'home') {
            $schema['breadcrumb'] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => $baseUrl.'/',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $pageName,
                        'item' => $data['url'] ?? $this->getCanonicalUrl($page),
                    ],
                ],
            ];
        }

        return $schema;
    }
}
