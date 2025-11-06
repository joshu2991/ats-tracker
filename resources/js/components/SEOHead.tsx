import { Head } from '@inertiajs/react';
import { useEffect } from 'react';

interface SEOProps {
    title?: string;
    description?: string;
    keywords?: string;
    canonical?: string;
    og?: {
        title?: string;
        description?: string;
        image?: string;
        url?: string;
        type?: string;
        site_name?: string;
        locale?: string;
        'image:width'?: number;
        'image:height'?: number;
        'image:alt'?: string;
    };
    twitter?: {
        card?: string;
        title?: string;
        description?: string;
        image?: string;
        site?: string;
        creator?: string;
    };
    structuredData?: object | object[];
    robots?: string;
}

export default function SEOHead({
    title,
    description,
    keywords,
    canonical,
    og,
    twitter,
    structuredData,
    robots,
}: SEOProps) {
    // Helper function to safely convert to string
    const safeString = (value: unknown): string | undefined => {
        if (value === null || value === undefined) {
            return undefined;
        }
        if (typeof value === 'symbol') {
            return undefined;
        }
        try {
            return String(value);
        } catch {
            return undefined;
        }
    };

    // Sanitize title - ensure it's a string
    const safeTitle = title ? safeString(title) : undefined;

    // Inject structured data into document head using useEffect
    useEffect(() => {
        if (!structuredData) {
            return;
        }

        try {
            const dataArray = Array.isArray(structuredData) ? structuredData : [structuredData];

            // Remove existing structured data scripts
            const existingScripts = document.querySelectorAll('script[type="application/ld+json"][data-seo-structured]');
            existingScripts.forEach((script) => script.remove());

            // Add new structured data scripts
            dataArray.forEach((data, index) => {
                try {
                    // Ensure data is serializable and doesn't contain Symbols
                    const serialized = JSON.parse(JSON.stringify(data));
                    const jsonString = JSON.stringify(serialized);

                    const script = document.createElement('script');
                    script.type = 'application/ld+json';
                    script.setAttribute('data-seo-structured', 'true');
                    script.textContent = jsonString;
                    document.head.appendChild(script);
                } catch (error) {
                    console.warn(`Failed to serialize structured data item ${index}:`, error);
                }
            });
        } catch (error) {
            console.warn('Failed to process structured data:', error);
        }

        // Cleanup function to remove scripts on unmount
        return () => {
            const scripts = document.querySelectorAll('script[type="application/ld+json"][data-seo-structured]');
            scripts.forEach((script) => script.remove());
        };
    }, [structuredData]);

    // Build meta tags array
    const metaTags: Array<{ name?: string; property?: string; content: string }> = [];

    if (description) {
        const safeDesc = safeString(description);
        if (safeDesc) {
            metaTags.push({ name: 'description', content: safeDesc });
        }
    }

    if (keywords) {
        const safeKeywords = safeString(keywords);
        if (safeKeywords) {
            metaTags.push({ name: 'keywords', content: safeKeywords });
        }
    }

    if (robots) {
        const safeRobots = safeString(robots);
        if (safeRobots) {
            metaTags.push({ name: 'robots', content: safeRobots });
        }
    }

    // Open Graph tags
    if (og) {
        if (og.title) {
            const safe = safeString(og.title);
            if (safe) metaTags.push({ property: 'og:title', content: safe });
        }
        if (og.description) {
            const safe = safeString(og.description);
            if (safe) metaTags.push({ property: 'og:description', content: safe });
        }
        if (og.image) {
            const safe = safeString(og.image);
            if (safe) metaTags.push({ property: 'og:image', content: safe });
        }
        if (og.url) {
            const safe = safeString(og.url);
            if (safe) metaTags.push({ property: 'og:url', content: safe });
        }
        if (og.type) {
            const safe = safeString(og.type);
            if (safe) metaTags.push({ property: 'og:type', content: safe });
        }
        if (og.site_name) {
            const safe = safeString(og.site_name);
            if (safe) metaTags.push({ property: 'og:site_name', content: safe });
        }
        if (og.locale) {
            const safe = safeString(og.locale);
            if (safe) metaTags.push({ property: 'og:locale', content: safe });
        }
        if (og['image:width'] != null) {
            const safe = safeString(og['image:width']);
            if (safe) metaTags.push({ property: 'og:image:width', content: safe });
        }
        if (og['image:height'] != null) {
            const safe = safeString(og['image:height']);
            if (safe) metaTags.push({ property: 'og:image:height', content: safe });
        }
        if (og['image:alt']) {
            const safe = safeString(og['image:alt']);
            if (safe) metaTags.push({ property: 'og:image:alt', content: safe });
        }
    }

    // Twitter Card tags
    if (twitter) {
        if (twitter.card) {
            const safe = safeString(twitter.card);
            if (safe) metaTags.push({ name: 'twitter:card', content: safe });
        }
        if (twitter.title) {
            const safe = safeString(twitter.title);
            if (safe) metaTags.push({ name: 'twitter:title', content: safe });
        }
        if (twitter.description) {
            const safe = safeString(twitter.description);
            if (safe) metaTags.push({ name: 'twitter:description', content: safe });
        }
        if (twitter.image) {
            const safe = safeString(twitter.image);
            if (safe) metaTags.push({ name: 'twitter:image', content: safe });
        }
        if (twitter.site) {
            const safe = safeString(twitter.site);
            if (safe) metaTags.push({ name: 'twitter:site', content: safe });
        }
        if (twitter.creator) {
            const safe = safeString(twitter.creator);
            if (safe) metaTags.push({ name: 'twitter:creator', content: safe });
        }
    }

    return (
        <Head title={safeTitle}>
            {metaTags.map((meta, index) => (
                <meta
                    key={`meta-${index}`}
                    {...(meta.name ? { name: meta.name } : {})}
                    {...(meta.property ? { property: meta.property } : {})}
                    content={meta.content}
                />
            ))}
            {canonical && <link rel="canonical" href={safeString(canonical) || ''} />}
        </Head>
    );
}

