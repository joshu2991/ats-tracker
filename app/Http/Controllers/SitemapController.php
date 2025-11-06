<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Generate and return XML sitemap.
     */
    public function index(): Response
    {
        $config = config('seo');
        $baseUrl = rtrim($config['site_url'], '/');

        // Only include public GET routes
        $publicRoutes = [
            [
                'url' => $baseUrl.'/',
                'route' => 'home',
                'changefreq' => $config['sitemap']['changefreq']['home'] ?? 'daily',
                'priority' => $config['sitemap']['priority']['home'] ?? 1.0,
            ],
            [
                'url' => $baseUrl.'/resume-checker',
                'route' => 'resume-checker',
                'changefreq' => $config['sitemap']['changefreq']['resume-checker'] ?? 'weekly',
                'priority' => $config['sitemap']['priority']['resume-checker'] ?? 0.9,
            ],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($publicRoutes as $route) {
            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.htmlspecialchars($route['url'], ENT_XML1, 'UTF-8').'</loc>'."\n";
            $xml .= '    <changefreq>'.htmlspecialchars($route['changefreq'], ENT_XML1, 'UTF-8').'</changefreq>'."\n";
            $xml .= '    <priority>'.htmlspecialchars((string) $route['priority'], ENT_XML1, 'UTF-8').'</priority>'."\n";
            $xml .= '  </url>'."\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
