<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /**
     * Generate and return robots.txt dynamically.
     */
    public function index(): Response
    {
        $config = config('seo');
        $sitemapUrl = rtrim($config['site_url'], '/').'/sitemap.xml';

        $content = "User-agent: *\n";
        $content .= "Disallow:\n\n";
        $content .= "Sitemap: {$sitemapUrl}\n";

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
