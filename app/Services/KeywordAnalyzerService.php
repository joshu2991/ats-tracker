<?php

namespace App\Services;

class KeywordAnalyzerService
{
    /**
     * Technical keywords to search for.
     */
    protected array $keywords = [
        // Languages
        'Laravel', 'React', 'Python', 'JavaScript', 'TypeScript', 'PHP', 'Java', 'C#', 'C++', 'Go', 'Rust', 'Ruby', 'Swift', 'Kotlin', 'Scala',
        // Frontend Frameworks
        'Vue', 'Angular', 'Svelte', 'Next.js', 'Nuxt', 'Remix',
        // Backend Frameworks
        'Node.js', 'Express', 'Django', 'Flask', 'FastAPI', 'Spring', 'ASP.NET', 'Rails',
        // Databases
        'MySQL', 'PostgreSQL', 'MongoDB', 'Redis', 'Elasticsearch', 'Cassandra', 'DynamoDB', 'SQLite', 'Oracle',
        // Cloud & DevOps
        'AWS', 'Docker', 'Kubernetes', 'Git', 'CI/CD', 'Jenkins', 'GitHub Actions', 'GitLab CI', 'Terraform', 'Ansible', 'Azure', 'GCP', 'Heroku',
        // Tools & Technologies
        'REST API', 'GraphQL', 'Microservices', 'Agile', 'Scrum', 'Jira', 'Confluence', 'Postman', 'Swagger',
    ];

    /**
     * Analyze keywords in the resume text.
     *
     * @return array{keywords: array<string, int>, uniqueCount: int, score: int}
     */
    public function analyze(string $text): array
    {
        $foundKeywords = $this->countKeywords($text);
        $uniqueCount = count($foundKeywords);
        $score = $this->calculateKeywordScore($uniqueCount);

        return [
            'keywords' => $foundKeywords,
            'uniqueCount' => $uniqueCount,
            'score' => $score,
        ];
    }

    /**
     * Count keyword occurrences in the text.
     *
     * @return array<string, int>
     */
    protected function countKeywords(string $text): array
    {
        $normalizedText = strtolower($text);
        $foundKeywords = [];

        foreach ($this->keywords as $keyword) {
            $normalizedKeyword = strtolower($keyword);
            // Use word boundaries to avoid partial matches
            $pattern = '/\b'.preg_quote($normalizedKeyword, '/').'\b/i';
            $matches = preg_match_all($pattern, $normalizedText);

            if ($matches > 0) {
                $foundKeywords[$keyword] = $matches;
            }
        }

        return $foundKeywords;
    }

    /**
     * Calculate keyword score based on unique keyword count.
     */
    protected function calculateKeywordScore(int $uniqueCount): int
    {
        return match (true) {
            $uniqueCount >= 15 => 40,
            $uniqueCount >= 10 => 30,
            $uniqueCount >= 5 => 20,
            default => 10,
        };
    }
}

