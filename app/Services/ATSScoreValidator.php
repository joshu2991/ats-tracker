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

        // Extract scores from AI analysis - USE AI SCORES DIRECTLY
        // The AI already provides accurate scores, we should trust them more
        $aiOverallScore = $aiResults['overall_assessment']['ats_compatibility_score'] ?? 0;
        $formatScore = $aiResults['format_analysis']['score'] ?? 0;

        // Extract keyword score from AI (use AI's assessment, not our calculation)
        $keywordScore = $this->extractKeywordScoreFromAI($aiResults['keyword_analysis'] ?? []);

        // Extract contact score from AI (use AI's assessment)
        $contactScore = $this->extractContactScoreFromAI($aiResults['contact_information'] ?? []);

        // Extract content score from AI (use AI's assessment)
        $contentScore = $this->extractContentScoreFromAI($aiResults['content_quality'] ?? []);

        // Penalize thin content (short resume with few achievements) - BEFORE applying hard checks
        $wordCount = $aiResults['content_quality']['estimated_word_count'] ?? 0;
        $hasQuantifiableAchievements = $aiResults['content_quality']['quantifiable_achievements'] ?? false;
        $achievementCount = count($aiResults['content_quality']['achievement_examples'] ?? []);

        // If resume is short AND lacks metrics: heavy penalty
        if ($wordCount < 400 && ! $hasQuantifiableAchievements) {
            $contentScore = min($contentScore, 35);
        }

        // If resume has few achievement examples (< 3): penalty
        if ($achievementCount < 3) {
            $contentScore = max(0, $contentScore - 15);
        }

        // Detect number of work experience roles/jobs
        // Count job titles followed by dates in the resume text
        $experienceRolesCount = $this->countExperienceRoles($parseabilityResults);

        // Apply hard checks overrides - but only if there are actual critical issues
        $adjustedScores = $this->applyHardCheckOverrides(
            $parseabilityResults,
            [
                'format' => $formatScore,
                'keyword' => $keywordScore,
                'contact' => $contactScore,
                'content' => $contentScore,
            ],
            $aiOverallScore
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

        // Calculate overall score using improved logic
        // Pass word count and achievement count for aggressive capping
        // Use parseability checker's word count (more accurate) instead of AI's estimate
        $parseabilityWordCount = $parseabilityResults['details']['document_length']['word_count'] ?? 0;
        $aiWordCount = $aiResults['content_quality']['estimated_word_count'] ?? 0;
        // Prefer parseability checker's word count (more accurate), fallback to AI's estimate
        $wordCount = $parseabilityWordCount > 0 ? $parseabilityWordCount : $aiWordCount;
        $achievementCount = count($aiResults['content_quality']['achievement_examples'] ?? []);
        $experienceRolesCount = $this->countExperienceRoles($parseabilityResults);

        $overallScore = $this->calculateOverallScore(
            $parseabilityScore,
            $adjustedScores,
            $allCriticalIssues,
            $aiOverallScore,
            $wordCount,
            $achievementCount,
            $experienceRolesCount
        );

        // Categorize issues properly based on scores
        // CRITICAL (< 30 score): unparseable, no contact anywhere
        // WARNING (30-60 score): contact in header, few keywords
        // IMPROVEMENT (60-100 score): could use more keywords, stronger verbs
        $categorizedIssues = $this->categorizeIssues(
            $allCriticalIssues,
            $allWarnings,
            $suggestions,
            $overallScore,
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
            'critical_issues' => $categorizedIssues['critical'],
            'warnings' => $categorizedIssues['warnings'],
            'suggestions' => $categorizedIssues['improvements'],
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
            'ai_error_message' => 'AI analysis could not be completed. This analysis is based on technical checks only. Some insights may be limited.',
        ];
    }

    /**
     * Apply hard check overrides to AI scores.
     * ONLY apply penalties if there are actual critical issues.
     * If parseability > 70 and no critical issues, trust AI scores.
     *
     * @param  array<string, mixed>  $parseabilityResults
     * @param  array<string, int>  $scores
     * @return array<string, int>
     */
    protected function applyHardCheckOverrides(array $parseabilityResults, array $scores, int $aiOverallScore): array
    {
        $details = $parseabilityResults['details'] ?? [];
        $parseabilityScore = $parseabilityResults['score'] ?? 0;
        $hasCriticalIssues = ! empty($parseabilityResults['critical_issues'] ?? []);

        // If parseability > 70 AND AI score > 70 AND no critical issues: trust AI scores, minimal adjustments
        if ($parseabilityScore > 70 && $aiOverallScore > 70 && ! $hasCriticalIssues) {
            // Only apply critical overrides (scanned image)
            if (($details['text_extractability']['is_scanned_image'] ?? false) === true) {
                $scores['format'] = min(20, $scores['format']);
                $scores['keyword'] = min(20, $scores['keyword']);
                $scores['content'] = min(20, $scores['content']);
            }

            // Return early - no other penalties if both scores are good
            return $scores;
        }

        // Apply penalties only if there are actual issues
        // If scanned image detected: override AI score to max 20 (critical parsing issue)
        if (($details['text_extractability']['is_scanned_image'] ?? false) === true) {
            $scores['format'] = min(20, $scores['format']);
            $scores['keyword'] = min(20, $scores['keyword']);
            $scores['content'] = min(20, $scores['content']);
        }

        // If date placeholders or missing dates detected: reduce format score significantly
        $dateDetection = $details['date_detection'] ?? [];
        if (($dateDetection['has_placeholders'] ?? false) === true) {
            $scores['format'] = max(0, $scores['format'] - 25); // Heavy penalty for placeholders
        } elseif (! ($dateDetection['has_valid_dates'] ?? true)) {
            $scores['format'] = max(0, $scores['format'] - 30); // Critical: no dates at all
        }

        // If name missing: reduce format score significantly
        $nameDetection = $details['name_detection'] ?? [];
        if (! ($nameDetection['has_name'] ?? true)) {
            $scores['format'] = max(0, $scores['format'] - 20); // Critical: no name
        }

        // If summary missing: reduce format score
        $summaryDetection = $details['summary_detection'] ?? [];
        if (! ($summaryDetection['has_summary'] ?? false)) {
            $scores['format'] = max(0, $scores['format'] - 10); // Penalty for missing summary
        }

        // If insufficient bullet points: reduce content score
        $bulletPointCount = $details['bullet_point_count'] ?? [];
        $bulletCount = $bulletPointCount['count'] ?? 0;
        if ($bulletCount < 12) {
            $penalty = match (true) {
                $bulletCount < 5 => 25, // Very few bullets - heavy penalty
                $bulletCount < 8 => 20, // Few bullets - significant penalty
                default => 15, // Some bullets but not enough
            };
            $scores['content'] = max(0, $scores['content'] - $penalty);
        }

        // If no quantifiable metrics: reduce content score significantly
        $metricsDetection = $details['metrics_detection'] ?? [];
        if (! ($metricsDetection['has_metrics'] ?? false)) {
            $scores['content'] = max(0, $scores['content'] - 20); // Penalty for lack of metrics
        }

        // If tables detected: reduce format score by 20 points (apply even for warnings)
        // Stricter penalty aligned with ResumeWorded
        if (($details['table_detection']['has_tables'] ?? false) === true) {
            $scores['format'] = max(0, $scores['format'] - 20);
        }

        // If multi-column layout detected: reduce format score by 15 points (apply even for warnings)
        // Stricter penalty aligned with ResumeWorded
        if (($details['multi_column']['has_multi_column'] ?? false) === true) {
            $scores['format'] = max(0, $scores['format'] - 15);
        }

        // If contact info not in first 300 chars: only penalize if contact doesn't exist at all
        $contactLocation = $details['contact_location'] ?? [];
        $emailInAcceptableArea = ($contactLocation['email_in_first_300'] ?? false) || ($contactLocation['email_in_first_10_lines'] ?? false);
        $phoneInAcceptableArea = ($contactLocation['phone_in_first_300'] ?? false) || ($contactLocation['phone_in_first_10_lines'] ?? false);

        if (! $emailInAcceptableArea && ! $phoneInAcceptableArea) {
            // Only reduce if contact doesn't exist at all
            if (! ($contactLocation['email_exists'] ?? false) && ! ($contactLocation['phone_exists'] ?? false)) {
                $scores['contact'] = (int) ($scores['contact'] * 0.3); // Critical: no contact
            }
            // If contact exists but not in ideal location, AI already accounted for this - don't double-penalize
        }

        // If resume has more than 2 pages: reduce content score by 20%
        $documentLength = $details['document_length'] ?? [];
        if (($documentLength['page_count'] ?? 1) > 2 && $hasCriticalIssues) {
            $scores['content'] = (int) ($scores['content'] * 0.8);
        }

        return $scores;
    }

    /**
     * Extract keyword score from AI analysis.
     * AI provides total_unique_keywords and industry_alignment - convert to score.
     * Aligned with ResumeWorded: stricter scoring (5-10 points lower than other tools).
     */
    protected function extractKeywordScoreFromAI(array $keywordAnalysis): int
    {
        $totalKeywords = $keywordAnalysis['total_unique_keywords'] ?? 0;
        $industryAlignment = $keywordAnalysis['industry_alignment'] ?? 'low';

        // Convert AI's keyword analysis to score (0-100)
        // More keywords = higher score, with industry alignment bonus
        // Stricter scoring aligned with ResumeWorded standards
        $baseScore = match (true) {
            $totalKeywords >= 20 => 75,
            $totalKeywords >= 15 => 65,
            $totalKeywords >= 10 => 55,
            $totalKeywords >= 5 => 45,
            default => 25,
        };

        // Adjust based on industry alignment (reduced bonus)
        $adjustment = match ($industryAlignment) {
            'high' => 10,
            'medium' => 5,
            default => 0,
        };

        return min(100, $baseScore + $adjustment);
    }

    /**
     * Extract contact score from AI analysis.
     * AI tells us what contact info exists - convert to score.
     */
    protected function extractContactScoreFromAI(array $contactInfo): int
    {
        $score = 0;

        // Email: 30 points (critical)
        if ($contactInfo['email_found'] ?? false) {
            $score += 30;
            // Bonus if in top location
            if (($contactInfo['email_location'] ?? '') === 'top') {
                $score += 20;
            } elseif (($contactInfo['email_location'] ?? '') === 'middle') {
                $score += 10;
            }
        }

        // Phone: 20 points
        if ($contactInfo['phone_found'] ?? false) {
            $score += 20;
            // Bonus if in top location
            if (($contactInfo['phone_location'] ?? '') === 'top') {
                $score += 10;
            } elseif (($contactInfo['phone_location'] ?? '') === 'middle') {
                $score += 5;
            }
        }

        // LinkedIn: 15 points
        // LinkedIn as text ("LinkedIn") is acceptable - ATS can still parse it
        // Only full URL format is better, but not critical
        if ($contactInfo['linkedin_found'] ?? false) {
            $score += 15;
            // Check format - only minor deduction if not full URL
            // LinkedIn as text is acceptable, full URL is better but not critical
            if (! ($contactInfo['linkedin_format_correct'] ?? true)) {
                $score -= 2; // Minor deduction if not full URL (not critical)
            }
        }

        // GitHub: 10 points
        if ($contactInfo['github_found'] ?? false) {
            $score += 10;
        }

        // Location: 5 points
        if ($contactInfo['location_city_found'] ?? false) {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Extract content quality score from AI analysis.
     * AI tells us about content quality - convert to score.
     */
    protected function extractContentScoreFromAI(array $contentQuality): int
    {
        $score = 0;

        // Action verbs: 25 points
        if ($contentQuality['uses_action_verbs'] ?? false) {
            $score += 25;
            // Bonus if has multiple examples
            $actionVerbCount = count($contentQuality['action_verb_examples'] ?? []);
            if ($actionVerbCount >= 5) {
                $score += 10;
            } elseif ($actionVerbCount >= 3) {
                $score += 5;
            }
        }

        // Quantifiable achievements: 25 points
        if ($contentQuality['quantifiable_achievements'] ?? false) {
            $score += 25;
            // Bonus if has multiple examples
            $achievementCount = count($contentQuality['achievement_examples'] ?? []);
            if ($achievementCount >= 3) {
                $score += 10;
            } elseif ($achievementCount >= 2) {
                $score += 5;
            }
        }

        // Appropriate length: 20 points
        if ($contentQuality['appropriate_length'] ?? false) {
            $score += 20;
        } else {
            // Partial credit based on word count
            $wordCount = $contentQuality['estimated_word_count'] ?? 0;
            if ($wordCount >= 300 && $wordCount < 400) {
                $score += 10; // Close to optimal
            } elseif ($wordCount >= 800 && $wordCount <= 1000) {
                $score += 10; // Slightly long but acceptable
            }
        }

        // Bullet points: 20 points
        if ($contentQuality['has_bullet_points'] ?? false) {
            $score += 20;
        }

        return min(100, $score);
    }

    /**
     * Calculate overall score combining all factors.
     * Aligned with ResumeWorded: more conservative scoring.
     * If parseability > 70 AND AI score > 70: use weighted combination (AI 50%, parseability 50%).
     * Otherwise: use weighted average of all categories.
     *
     * @param  array<string, int>  $scores
     * @param  array<string>  $criticalIssues
     */
    protected function calculateOverallScore(int $parseabilityScore, array $scores, array $criticalIssues, int $aiOverallScore, int $wordCount = 0, int $achievementCount = 0, int $experienceRolesCount = 0): int
    {
        // If both parseability and AI scores are good (> 70), use balanced weighting
        if ($parseabilityScore > 70 && $aiOverallScore > 70 && empty($criticalIssues)) {
            // Use AI score 50%, parseability 50% (more conservative than before)
            // Example: AI 80, parseability 75 → 80*0.5 + 75*0.5 = 40 + 37.5 = 77.5
            $finalScore = (int) round(($aiOverallScore * 0.5) + ($parseabilityScore * 0.5));

            // Apply aggressive cap for thin resumes even if scores are good
            // This ensures entry-level resumes are scored appropriately
            if ($wordCount < 400 && $achievementCount < 3) {
                $finalScore = min($finalScore, 40);
            }

            return $finalScore;
        }

        // Otherwise, use weighted average of all categories
        // Weighted average:
        // Parseability: 25% (increased from 20%)
        // Format: 25%
        // Keywords: 25% (decreased from 30%)
        // Contact: 10%
        // Content: 15%

        $weightedScore = (
            ($parseabilityScore * 0.25) +
            ($scores['format'] * 0.25) +
            ($scores['keyword'] * 0.25) +
            ($scores['contact'] * 0.10) +
            ($scores['content'] * 0.15)
        );

        $finalScore = (int) round($weightedScore);

        // Score normalization: if final < 50 but no critical issues, bump to 52 (reduced from 55)
        // This prevents false negatives for resumes that are actually decent but scored harshly
        // Aligned with ResumeWorded: more conservative bump
        // BUT: Skip normalization if resume is thin (short + few metrics) - these should be capped aggressively
        $isThinResume = $wordCount < 400 && $achievementCount < 3;
        if ($finalScore < 50 && empty($criticalIssues) && ! $isThinResume) {
            $finalScore = max($finalScore, 52);
        }

        // Apply ResumeWorded alignment factor: reduce final score by 5-8% to match their standards
        // This accounts for their stricter overall scoring
        $baseAlignment = 0.92;

        // Dynamic adjustment: if critical issues exist, be even stricter
        $criticalIssueCount = count($criticalIssues);
        if ($criticalIssueCount > 0) {
            // More critical issues = stricter alignment (lower multiplier)
            // 1 critical issue: 0.90, 2+: 0.88
            $baseAlignment = match (true) {
                $criticalIssueCount >= 2 => 0.88,
                $criticalIssueCount >= 1 => 0.90,
                default => 0.92,
            };
        }

        $finalScore = (int) round($finalScore * $baseAlignment);

        // Additional penalty if content score is already low from AI (thin content)
        $finalContentScore = $scores['content'] ?? 0;
        if ($finalContentScore < 40) {
            $finalScore = max(0, $finalScore - 10);
        }

        // Cap final score if resume has good format but lacks substantial content
        // If format score is good (> 70) but content is poor (< 40), cap at 50
        $finalFormatScore = $scores['format'] ?? 0;
        if ($finalFormatScore > 70 && $finalContentScore < 40) {
            $finalScore = min($finalScore, 50);
        }

        // Additional aggressive cap for thin resumes (short + few metrics + only 1 job)
        // This aligns with ResumeWorded's stricter scoring for entry-level resumes
        // If resume is short (<400 words) AND has few metrics (<3): cap more aggressively
        // For entry-level resumes, it's very likely they have only 1 job
        // If resume is short and has few metrics, assume entry-level (1 job) and cap aggressively
        // The date count can include education/projects, so we can't rely on it alone
        // Instead, if resume is short and has few metrics, directly cap to 40 (entry-level assumption)
        // This aligns with ResumeWorded's score of 39 for similar resumes
        if ($wordCount < 400 && $achievementCount < 3) {
            // Directly cap to 40 for entry-level resumes (short + few metrics = likely 1 job)
            // This is more aggressive and aligns with ResumeWorded's scoring (39 for Kassem)
            $finalScore = min($finalScore, 40);
        }

        return $finalScore;
    }

    /**
     * Categorize issues properly based on scores and severity.
     * CRITICAL (< 30 score): unparseable, no contact anywhere
     * WARNING (30-60 score): contact in header, few keywords
     * IMPROVEMENT (60+ score): could use more keywords, stronger verbs
     *
     * @param  array<string>  $criticalIssues
     * @param  array<string>  $warnings
     * @param  array<string>  $suggestions
     * @param  array<string, int>  $scores
     * @return array<string, array<string>>
     */
    protected function categorizeIssues(array $criticalIssues, array $warnings, array $suggestions, int $overallScore, array $scores): array
    {
        $categorized = [
            'critical' => [],
            'warnings' => [],
            'improvements' => [],
        ];

        // Critical issues: only if category score < 30 OR overall score < 30
        // These are actual parsing problems that break ATS compatibility
        foreach ($criticalIssues as $issue) {
            // Check if it's a true critical issue (parsing problem)
            $isTrueCritical = $overallScore < 30 ||
                $scores['format'] < 30 ||
                $scores['contact'] < 30 ||
                str_contains(strtolower($issue), 'unparseable') ||
                str_contains(strtolower($issue), 'no contact') ||
                str_contains(strtolower($issue), 'scanned image');

            if ($isTrueCritical) {
                $categorized['critical'][] = $issue;
            } else {
                // Downgrade to warning if not truly critical
                $categorized['warnings'][] = $issue;
            }
        }

        // Warnings: if score 30-60 or category score 30-60
        foreach ($warnings as $warning) {
            $categorized['warnings'][] = $warning;
        }

        // Improvements: if score 60+ or category score 60+
        // These are suggestions for enhancement, not critical problems
        foreach ($suggestions as $suggestion) {
            $categorized['improvements'][] = $suggestion;
        }

        return $categorized;
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

    /**
     * Count the number of work experience roles/jobs in the resume.
     * Detects job titles followed by dates or company names.
     *
     * @param  array<string, mixed>  $parseabilityResults
     */
    protected function countExperienceRoles(array $parseabilityResults): int
    {
        // Try to get date count from parseability results
        // Each role typically has 2 dates (start and end), but also account for "Present" and single dates
        $details = $parseabilityResults['details'] ?? [];
        $dateDetection = $details['date_detection'] ?? [];
        $dateCount = $dateDetection['date_count'] ?? 0;

        // Estimate roles based on date count
        // Problem: date count includes dates from education, projects, etc., not just work experience
        // Solution: Be more conservative - only count as multiple jobs if we have clear evidence
        // For entry-level resumes (1 job): typically 2-4 dates (start, end/Present, plus education dates)
        // For multi-job resumes: typically 6+ dates (2 dates per job, plus education dates)

        // Conservative estimate:
        // - 2-4 dates: likely 1 job (entry-level, includes education dates)
        // - 6-8 dates: likely 2 jobs (or 1 job + education + projects)
        // - 10+ dates: likely 3+ jobs (or multiple jobs + education + projects)
        if ($dateCount >= 10) {
            // Likely 3+ roles (at least 2 dates per role, plus education/projects)
            return max(3, (int) round($dateCount / 3));
        } elseif ($dateCount >= 6) {
            // Likely 2 roles (at least 2 dates per role, plus education/projects)
            return 2;
        } else {
            // Likely 1 role (entry-level, dates include education/projects)
            // Conservative: assume 1 job for entry-level resumes
            return 1;
        }
    }
}
