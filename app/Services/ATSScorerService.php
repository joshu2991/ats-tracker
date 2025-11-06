<?php

namespace App\Services;

/**
 * Legacy ATS Scorer Service
 *
 * NOTE: This service is legacy and NOT used in the actual application flow.
 * It is kept for backward compatibility with existing tests only.
 * The current application uses ATSParseabilityChecker + AIResumeAnalyzer + ATSScoreValidator.
 */
class ATSScorerService
{
    /**
     * Calculate format score (0-30 points).
     *
     * @return array{score: int, breakdown: array{experience: int, education: int, skills: int, bullets: int}}
     */
    public function calculateFormatScore(string $text, array $sections): array
    {
        $score = 0;
        $breakdown = [
            'experience' => 0,
            'education' => 0,
            'skills' => 0,
            'bullets' => 0,
        ];

        // +10 pts if Experience section found
        if ($sections['experience']) {
            $score += 10;
            $breakdown['experience'] = 10;
        }

        // +10 pts if Education section found
        if ($sections['education']) {
            $score += 10;
            $breakdown['education'] = 10;
        }

        // +5 pts if Skills section found
        if ($sections['skills']) {
            $score += 5;
            $breakdown['skills'] = 5;
        }

        // +5 pts if bullet points detected (• or -)
        if ($this->hasBulletPoints($text)) {
            $score += 5;
            $breakdown['bullets'] = 5;
        }

        return [
            'score' => $score,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate contact score (0-10 points).
     *
     * @return array{score: int, breakdown: array{email: int, phone: int, linkedin: int, github: int}}
     */
    public function calculateContactScore(array $contact): array
    {
        $score = 0;
        $breakdown = [
            'email' => 0,
            'phone' => 0,
            'linkedin' => 0,
            'github' => 0,
        ];

        // +3 pts if valid email found
        if (! empty($contact['email']) && filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
            $score += 3;
            $breakdown['email'] = 3;
        }

        // +2 pts if phone found
        if (! empty($contact['phone'])) {
            $score += 2;
            $breakdown['phone'] = 2;
        }

        // +3 pts if LinkedIn URL found
        if (! empty($contact['linkedin'])) {
            $score += 3;
            $breakdown['linkedin'] = 3;
        }

        // +2 pts if GitHub/portfolio URL found
        if (! empty($contact['github'])) {
            $score += 2;
            $breakdown['github'] = 2;
        }

        return [
            'score' => $score,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate length and clarity score (0-20 points).
     *
     * @return array{score: int, wordCount: int, breakdown: array{length: int, actionVerbs: int, bullets: int}}
     */
    public function calculateLengthScore(string $text): array
    {
        $score = 0;
        $wordCount = $this->countWords($text);
        $breakdown = [
            'length' => 0,
            'actionVerbs' => 0,
            'bullets' => 0,
        ];

        // +10 pts if 400-800 words (1-2 pages ideal)
        if ($wordCount >= 400 && $wordCount <= 800) {
            $score += 10;
            $breakdown['length'] = 10;
        }

        // +5 pts if action verbs detected
        if ($this->hasActionVerbs($text)) {
            $score += 5;
            $breakdown['actionVerbs'] = 5;
        }

        // +5 pts if bullet points present
        if ($this->hasBulletPoints($text)) {
            $score += 5;
            $breakdown['bullets'] = 5;
        }

        return [
            'score' => $score,
            'wordCount' => $wordCount,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Check if text contains bullet points.
     */
    protected function hasBulletPoints(string $text): bool
    {
        // Check for common bullet point characters
        $bulletPatterns = [
            '/•/', // Bullet character (U+2022)
            '/^\s*[-*]\s+/m', // Dash or asterisk at start of line
            '/^\s*o\s+/m', // Lowercase 'o' as bullet (removed brackets)
        ];

        foreach ($bulletPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if text contains action verbs.
     */
    protected function hasActionVerbs(string $text): bool
    {
        $actionVerbs = [
            'led', 'built', 'managed', 'created', 'developed', 'designed', 'implemented', 'improved',
            'launched', 'optimized', 'delivered', 'achieved', 'increased', 'reduced', 'established',
            'coordinated', 'executed', 'transformed', 'enhanced', 'streamlined', 'automated',
            'architected', 'deployed', 'integrated', 'migrated', 'scaled', 'maintained', 'collaborated',
            'mentored', 'trained', 'supervised', 'analyzed', 'researched', 'evaluated',
        ];

        $normalizedText = strtolower($text);

        foreach ($actionVerbs as $verb) {
            // Use word boundaries to match whole words only
            if (preg_match('/\b'.preg_quote($verb, '/').'\b/i', $normalizedText)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate actionable suggestions based on analysis.
     *
     * @param  array<string, mixed>  $analysis
     * @return array<string>
     */
    public function generateSuggestions(array $analysis): array
    {
        $suggestions = [];

        // If missing email
        if (empty($analysis['contact']['email'])) {
            $suggestions[] = 'Add a valid email address';
        }

        // If <10 unique keywords
        if (($analysis['keywordAnalysis']['uniqueCount'] ?? 0) < 10) {
            $suggestions[] = 'Add more technical skills (React, AWS, Docker, etc.)';
        }

        // If no LinkedIn
        if (empty($analysis['contact']['linkedin'])) {
            $suggestions[] = 'Add your LinkedIn profile URL';
        }

        // If >900 words
        if (($analysis['lengthScore']['wordCount'] ?? 0) > 900) {
            $suggestions[] = 'Consider reducing to 1-2 pages (400-800 words)';
        }

        // If no bullet points
        if (($analysis['formatScore']['breakdown']['bullets'] ?? 0) === 0) {
            $suggestions[] = 'Use bullet points to improve readability';
        }

        // If no GitHub/portfolio
        if (empty($analysis['contact']['github'])) {
            $suggestions[] = 'Add your GitHub profile or portfolio URL';
        }

        // Prioritize by impact (highest score potential first)
        // Return max 5 suggestions
        return array_slice($suggestions, 0, 5);
    }

    /**
     * Count words accurately, handling special characters, emails, URLs.
     */
    protected function countWords(string $text): int
    {
        // Remove excessive whitespace but preserve structure
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Remove non-printable characters except spaces
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Split by whitespace and filter empty strings
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Count words (handle emails, URLs, hyphenated words as single words)
        $count = 0;
        foreach ($words as $word) {
            // Remove special characters but keep alphanumeric, hyphens, dots, @, /
            $cleanWord = preg_replace('/[^\w\-.@\/]/', '', $word);
            if (! empty(trim($cleanWord))) {
                $count++;
            }
        }

        return $count;
    }
}
