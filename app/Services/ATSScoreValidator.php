<?php

namespace App\Services;

class ATSScoreValidator
{
    /**
     * Validate and combine results from parseability checker and AI analyzer.
     *
     * @param  array<string, mixed>  $parseabilityResults
     * @param  array<string, mixed>|null  $aiResults
     * @return array<string, mixed>
     */
    public function validate(array $parseabilityResults, ?array $aiResults): array
    {
        $parseabilityScore = $parseabilityResults['score'] ?? 0;
        $criticalIssues = $parseabilityResults['critical_issues'] ?? [];
        $warnings = $parseabilityResults['warnings'] ?? [];

        // If AI analysis failed, return basic analysis with only hard checks
        if ($aiResults === null) {
            return $this->buildBasicAnalysis($parseabilityResults);
        }

        // Extract scores from AI analysis
        $formatScore = $aiResults['format_analysis']['score'] ?? 0;
        $keywordScore = $this->calculateKeywordScore($aiResults['keyword_analysis'] ?? []);
        $contactScore = $this->calculateContactScore($aiResults['contact_information'] ?? []);
        $contentScore = $this->calculateContentScore($aiResults['content_quality'] ?? []);

        // Apply hard checks overrides
        $adjustedScores = $this->applyHardCheckOverrides(
            $parseabilityResults,
            [
                'format' => $formatScore,
                'keyword' => $keywordScore,
                'contact' => $contactScore,
                'content' => $contentScore,
            ]
        );

        // Combine issues and suggestions
        $allCriticalIssues = array_merge(
            $criticalIssues,
            $aiResults['ats_red_flags'] ?? [],
            $aiResults['critical_fixes_required'] ?? []
        );

        $allWarnings = array_merge(
            $warnings,
            $this->extractWarningsFromAI($aiResults)
        );

        $suggestions = $aiResults['recommended_improvements'] ?? [];

        // Calculate overall score
        $overallScore = $this->calculateOverallScore(
            $parseabilityScore,
            $adjustedScores
        );

        // Calculate confidence level
        $confidence = $this->calculateConfidence(
            $parseabilityResults,
            $aiResults !== null
        );

        // Calculate estimated cost (approximate)
        $estimatedCost = $this->calculateEstimatedCost($aiResults !== null);

        return [
            'overall_score' => $overallScore,
            'confidence' => $confidence,
            'parseability_score' => $parseabilityScore,
            'format_score' => $adjustedScores['format'],
            'keyword_score' => $adjustedScores['keyword'],
            'contact_score' => $adjustedScores['contact'],
            'content_score' => $adjustedScores['content'],
            'critical_issues' => array_unique($allCriticalIssues),
            'warnings' => array_unique($allWarnings),
            'suggestions' => array_unique($suggestions),
            'estimated_cost' => $estimatedCost,
            'ai_unavailable' => false,
            'ai_error_message' => null,
        ];
    }

    /**
     * Build basic analysis when AI is unavailable.
     *
     * @param  array<string, mixed>  $parseabilityResults
     * @return array<string, mixed>
     */
    protected function buildBasicAnalysis(array $parseabilityResults): array
    {
        $parseabilityScore = $parseabilityResults['score'] ?? 0;
        $criticalIssues = $parseabilityResults['critical_issues'] ?? [];
        $warnings = $parseabilityResults['warnings'] ?? [];

        // Generate basic suggestions based on parseability checks
        $suggestions = $this->generateBasicSuggestions($parseabilityResults);

        return [
            'overall_score' => min(100, $parseabilityScore + 20), // Cap at 100, give some credit for basic checks
            'confidence' => 'medium', // Lower confidence without AI
            'parseability_score' => $parseabilityScore,
            'format_score' => 0, // Cannot calculate without AI
            'keyword_score' => 0, // Cannot calculate without AI
            'contact_score' => $this->calculateBasicContactScore($parseabilityResults),
            'content_score' => 0, // Cannot calculate without AI
            'critical_issues' => $criticalIssues,
            'warnings' => array_merge($warnings, [
                'AI analysis is not available. Some insights may be limited.',
            ]),
            'suggestions' => $suggestions,
            'estimated_cost' => 0.00,
            'ai_unavailable' => true,
            'ai_error_message' => 'AI analysis is temporarily unavailable. Please try again later.',
        ];
    }

    /**
     * Apply hard check overrides to AI scores.
     *
     * @param  array<string, mixed>  $parseabilityResults
     * @param  array<string, int>  $scores
     * @return array<string, int>
     */
    protected function applyHardCheckOverrides(array $parseabilityResults, array $scores): array
    {
        $details = $parseabilityResults['details'] ?? [];

        // If scanned image detected: override AI score to max 20
        if (($details['text_extractability']['is_scanned_image'] ?? false) === true) {
            $scores['format'] = min(20, $scores['format']);
            $scores['keyword'] = min(20, $scores['keyword']);
            $scores['content'] = min(20, $scores['content']);
        }

        // If tables detected: reduce format score by 30 points
        if (($details['table_detection']['has_tables'] ?? false) === true) {
            $scores['format'] = max(0, $scores['format'] - 30);
        }

        // If multi-column layout detected: reduce format score by 20 points
        if (($details['multi_column']['has_multi_column'] ?? false) === true) {
            $scores['format'] = max(0, $scores['format'] - 20);
        }

        // If contact info not in first 200 chars: contact score max 30% of original
        $contactLocation = $details['contact_location'] ?? [];
        if (! ($contactLocation['email_in_first_200'] ?? false) && ! ($contactLocation['phone_in_first_200'] ?? false)) {
            $scores['contact'] = (int) ($scores['contact'] * 0.3);
        }

        // If resume has more than 2 pages: reduce content score by 40%
        $documentLength = $details['document_length'] ?? [];
        if (($documentLength['page_count'] ?? 1) > 2) {
            $scores['content'] = (int) ($scores['content'] * 0.6); // Reduce by 40%
        }

        return $scores;
    }

    /**
     * Calculate keyword score from AI analysis.
     */
    protected function calculateKeywordScore(array $keywordAnalysis): int
    {
        $totalKeywords = $keywordAnalysis['total_unique_keywords'] ?? 0;
        $industryAlignment = $keywordAnalysis['industry_alignment'] ?? 'low';

        // Base score on number of unique keywords
        $score = match (true) {
            $totalKeywords >= 15 => 40,
            $totalKeywords >= 10 => 30,
            $totalKeywords >= 5 => 20,
            default => 10,
        };

        // Adjust based on industry alignment
        $adjustment = match ($industryAlignment) {
            'high' => 10,
            'medium' => 5,
            default => 0,
        };

        return min(100, $score + $adjustment);
    }

    /**
     * Calculate contact score from AI analysis.
     */
    protected function calculateContactScore(array $contactInfo): int
    {
        $score = 0;

        // Email: 3 points
        if ($contactInfo['email_found'] ?? false) {
            $score += 3;
            // Bonus if in top location
            if (($contactInfo['email_location'] ?? '') === 'top') {
                $score += 2;
            }
        }

        // Phone: 2 points
        if ($contactInfo['phone_found'] ?? false) {
            $score += 2;
            // Bonus if in top location
            if (($contactInfo['phone_location'] ?? '') === 'top') {
                $score += 1;
            }
        }

        // LinkedIn: 3 points
        if ($contactInfo['linkedin_found'] ?? false) {
            $score += 3;
            // Check format
            if (! ($contactInfo['linkedin_format_correct'] ?? true)) {
                $score -= 1; // Deduct if format is incorrect
            }
        }

        // GitHub: 2 points
        if ($contactInfo['github_found'] ?? false) {
            $score += 2;
        }

        return min(100, $score);
    }

    /**
     * Calculate content quality score from AI analysis.
     */
    protected function calculateContentScore(array $contentQuality): int
    {
        $score = 0;

        // Action verbs: 5 points
        if ($contentQuality['uses_action_verbs'] ?? false) {
            $score += 5;
        }

        // Quantifiable achievements: 5 points
        if ($contentQuality['quantifiable_achievements'] ?? false) {
            $score += 5;
        }

        // Appropriate length: 5 points
        if ($contentQuality['appropriate_length'] ?? false) {
            $score += 5;
        }

        // Bullet points: 5 points
        if ($contentQuality['has_bullet_points'] ?? false) {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Calculate overall score combining all factors.
     *
     * @param  array<string, int>  $scores
     */
    protected function calculateOverallScore(int $parseabilityScore, array $scores): int
    {
        // Weighted average:
        // Parseability: 20%
        // Format: 25%
        // Keywords: 30%
        // Contact: 10%
        // Content: 15%

        $weightedScore = (
            ($parseabilityScore * 0.20) +
            ($scores['format'] * 0.25) +
            ($scores['keyword'] * 0.30) +
            ($scores['contact'] * 0.10) +
            ($scores['content'] * 0.15)
        );

        return (int) round($weightedScore);
    }

    /**
     * Calculate confidence level.
     *
     * @param  array<string, mixed>  $parseabilityResults
     */
    protected function calculateConfidence(array $parseabilityResults, bool $hasAiResults): string
    {
        $parseabilityConfidence = $parseabilityResults['confidence'] ?? 'medium';
        $issueCount = count($parseabilityResults['critical_issues'] ?? []) + count($parseabilityResults['warnings'] ?? []);

        if (! $hasAiResults) {
            return 'medium'; // Lower confidence without AI
        }

        // High: all hard checks passed, AI gave complete response
        if ($parseabilityConfidence === 'high' && $issueCount === 0) {
            return 'high';
        }

        // Medium: some hard checks failed but AI is consistent
        if ($parseabilityConfidence === 'medium' || ($parseabilityConfidence === 'high' && $issueCount > 0)) {
            return 'medium';
        }

        // Low: hard checks contradicted or significant issues
        return 'low';
    }

    /**
     * Extract warnings from AI analysis.
     *
     * @param  array<string, mixed>  $aiResults
     * @return array<string>
     */
    protected function extractWarningsFromAI(array $aiResults): array
    {
        $warnings = [];

        $formatAnalysis = $aiResults['format_analysis'] ?? [];
        if (! ($formatAnalysis['has_appropriate_structure'] ?? true)) {
            $warnings[] = 'Resume structure may not be optimal for ATS parsing.';
        }

        $keywordAnalysis = $aiResults['keyword_analysis'] ?? [];
        if (($keywordAnalysis['keyword_density'] ?? '') === 'too_sparse') {
            $warnings[] = 'Keyword density is too sparse. Consider adding more relevant technical keywords.';
        }

        return $warnings;
    }

    /**
     * Generate basic suggestions from parseability checks.
     *
     * @param  array<string, mixed>  $parseabilityResults
     * @return array<string>
     */
    protected function generateBasicSuggestions(array $parseabilityResults): array
    {
        $suggestions = [];
        $details = $parseabilityResults['details'] ?? [];

        if (($details['text_extractability']['is_scanned_image'] ?? false) === true) {
            $suggestions[] = 'Convert scanned PDF to text-based format for better ATS compatibility';
        }

        if (($details['table_detection']['has_tables'] ?? false) === true) {
            $suggestions[] = 'Replace tables with simple bullet points for better ATS parsing';
        }

        if (($details['multi_column']['has_multi_column'] ?? false) === true) {
            $suggestions[] = 'Use single-column layout for better ATS compatibility';
        }

        $documentLength = $details['document_length'] ?? [];
        if (! ($documentLength['is_optimal'] ?? true)) {
            $wordCount = $documentLength['word_count'] ?? 0;
            if ($wordCount < 400) {
                $suggestions[] = 'Resume is too short. Consider adding more detail (ideal: 400-800 words)';
            } elseif ($wordCount > 800) {
                $suggestions[] = 'Resume is too long. Consider condensing to 1-2 pages (ideal: 400-800 words)';
            }
        }

        $contactLocation = $details['contact_location'] ?? [];
        if (! ($contactLocation['email_in_first_200'] ?? false) && ($contactLocation['email_exists'] ?? false)) {
            $suggestions[] = 'Move email address to the top of the resume (first 200 characters)';
        }

        if (! ($contactLocation['phone_in_first_200'] ?? false) && ($contactLocation['phone_exists'] ?? false)) {
            $suggestions[] = 'Move phone number to the top of the resume (first 200 characters)';
        }

        return $suggestions;
    }

    /**
     * Calculate basic contact score from parseability results.
     */
    protected function calculateBasicContactScore(array $parseabilityResults): int
    {
        $contactLocation = $parseabilityResults['details']['contact_location'] ?? [];
        $score = 0;

        if ($contactLocation['email_in_first_200'] ?? false) {
            $score += 5;
        } elseif ($contactLocation['email_exists'] ?? false) {
            $score += 2; // Partial credit if exists but not in first 200
        }

        if ($contactLocation['phone_in_first_200'] ?? false) {
            $score += 3;
        } elseif ($contactLocation['phone_exists'] ?? false) {
            $score += 1; // Partial credit if exists but not in first 200
        }

        return min(100, $score);
    }

    /**
     * Calculate estimated cost in USD.
     */
    protected function calculateEstimatedCost(bool $usedAi): float
    {
        if (! $usedAi) {
            return 0.00;
        }

        // Approximate cost for gpt-4o-mini:
        // Input: ~$0.15 per 1M tokens
        // Output: ~$0.60 per 1M tokens
        // Average resume: ~2000 input tokens, ~500 output tokens
        // Estimated cost per analysis: ~$0.0005 (very small)
        return 0.001; // Round up to 0.001 for display
    }
}
