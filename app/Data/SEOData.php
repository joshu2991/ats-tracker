<?php

namespace App\Data;

/**
 * SEO Data Transfer Object
 *
 * Represents all SEO-related data for a page.
 */
class SEOData
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $keywords = null,
        public ?string $canonical = null,
        public ?array $openGraph = null,
        public ?array $twitter = null,
        public ?array $structuredData = null,
        public ?string $robots = null,
    ) {}

    /**
     * Convert to array for Inertia props.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'canonical' => $this->canonical,
            'og' => $this->openGraph,
            'twitter' => $this->twitter,
            'structuredData' => $this->structuredData,
            'robots' => $this->robots,
        ];
    }

    /**
     * Validate SEO data.
     */
    public function validate(): bool
    {
        if (empty($this->title)) {
            return false;
        }

        if (empty($this->description)) {
            return false;
        }

        if (strlen($this->description) > 160) {
            return false;
        }

        return true;
    }
}
