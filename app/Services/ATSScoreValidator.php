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
        // If AI analysis failed, return basic analysis with only hard checks
        if ($aiResults === null) {
            return $this->buildBasicAnalysis($parseabilityResults);
        }

        $parseabilityScore = $parseabilityResults['score'] ?? 0;
        $aiOverallScore = $aiResults['overall_assessment']['ats_compatibility_score'] ?? 0;

        // Extract scores from AI analysis
        $scores = $this->extractAIScores($aiResults);

        // Apply thin content penalties before hard checks
        $scores = $this->applyThinContentPenalties($scores, $aiResults);

        // Apply hard check overrides
        $adjustedScores = $this->applyHardCheckOverrides(
            $parseabilityResults,
            $scores,
            $aiOverallScore
        );

        // Combine issues and suggestions
        $combinedIssues = $this->combineIssues($parseabilityResults, $aiResults);

        // Calculate metrics for overall score
        $metrics = $this->calculateMetrics($parseabilityResults, $aiResults);

        // Calculate overall score
        $overallScore = $this->calculateOverallScore(
            $parseabilityScore,
            $adjustedScores,
            $combinedIssues['critical'],
            $aiOverallScore,
            $metrics['word_count'],
            $metrics['achievement_count'],
            $metrics['experience_roles_count']
        );

        // Categorize issues based on scores
        $categorizedIssues = $this->categorizeIssues(
            $combinedIssues['critical'],
            $combinedIssues['warnings'],
            $combinedIssues['suggestions'],
            $overallScore,
            $adjustedScores
        );

        // Build final result
        return $this->buildFinalResult(
            $parseabilityScore,
            $adjustedScores,
            $overallScore,
            $categorizedIssues,
            $parseabilityResults
        );
    }

    /**
     * Extract all scores from AI analysis results.
     *
     * @param  array<string, mixed>  $aiResults
     * @return array<string, int>
     */
    protected function extractAIScores(array $aiResults): array
    {
        return [
            'format' => $aiResults['format_analysis']['score'] ?? 0,
            'keyword' => $this->extractKeywordScoreFromAI($aiResults['keyword_analysis'] ?? []),
            'contact' => $this->extractContactScoreFromAI($aiResults['contact_information'] ?? []),
            'content' => $this->extractContentScoreFromAI($aiResults['content_quality'] ?? []),
        ];
    }

    /**
     * Apply penalties for thin content (short resumes with few achievements).
     *
     * @param  array<string, int>  $scores
     * @param  array<string, mixed>  $aiResults
     * @return array<string, int>
     */
    protected function applyThinContentPenalties(array $scores, array $aiResults): array
    {
        $wordCount = $aiResults['content_quality']['estimated_word_count'] ?? 0;
        $hasQuantifiableAchievements = $aiResults['content_quality']['quantifiable_achievements'] ?? false;
        $achievementCount = count($aiResults['content_quality']['achievement_examples'] ?? []);

        // If resume is short AND lacks metrics: heavy penalty
        if ($wordCount < ATSScoreValidatorConstants::THIN_RESUME_WORD_COUNT && ! $hasQuantifiableAchievements) {
            $scores['content'] = min($scores['content'], 35);
        }

        // If resume has few achievement examples: penalty
        if ($achievementCount < ATSScoreValidatorConstants::THIN_RESUME_ACHIEVEMENT_COUNT) {
            $scores['content'] = max(ATSScoreValidatorConstants::MIN_SCORE, $scores['content'] - ATSScoreValidatorConstants::PENALTY_INSUFFICIENT_BULLETS);
        }

        return $scores;
    }

    /**
     * Combine issues from parseability results and AI analysis.
     *
     * @param  array<string, mixed>  $parseabilityResults
     * @param  array<string, mixed>  $aiResults
     * @return array<string, array<string>>
     */
    protected function combineIssues(array $parseabilityResults, array $aiResults): array
    {
        $criticalIssues = $parseabilityResults['critical_issues'] ?? [];
        $warnings = $parseabilityResults['warnings'] ?? [];

        return [
            'critical' => array_merge(
                $criticalIssues,
                $aiResults['ats_red_flags'] ?? [],
                $aiResults['critical_fixes_required'] ?? []
            ),
            'warnings' => array_merge(
                $warnings,
                $this->extractWarningsFromAI($aiResults)
            ),
            'suggestions' => $aiResults['recommended_improvements'] ?? [],
        ];
    }

    /**
     * Calculate metrics needed for overall score calculation.
     *
     * @param  array<string, mixed>  $parseabilityResults
     * @param  array<string, mixed>  $aiResults
     * @return array<string, int>
     */
    protected function calculateMetrics(array $parseabilityResults, array $aiResults): array
    {
        // Use parseability checker's word count (more accurate), fallback to AI's estimate
        $parseabilityWordCount = $parseabilityResults['details']['document_length']['word_count'] ?? 0;
        $aiWordCount = $aiResults['content_quality']['estimated_word_count'] ?? 0;
        $wordCount = $parseabilityWordCount > 0 ? $parseabilityWordCount : $aiWordCount;

        return [
            'word_count' => $wordCount,
            'achievement_count' => count($aiResults['content_quality']['achievement_examples'] ?? []),
            'experience_roles_count' => $this->countExperienceRoles($parseabilityResults),
        ];
    }

    /**
     * Build the final result array.
     *
     * @param  array<string, int>  $adjustedScores
     * @param  array<string, array<string>>  $categorizedIssues
     * @param  array<string, mixed>  $parseabilityResults
     * @return array<string, mixed>
     */
    protected function buildFinalResult(int $parseabilityScore, array $adjustedScores, int $overallScore, array $categorizedIssues, array $parseabilityResults): array
    {
        $confidence = $this->calculateConfidence($parseabilityResults, true);
        $estimatedCost = $this->calculateEstimatedCost(true);

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
            'overall_score' => min(ATSScoreValidatorConstants::MAX_SCORE, $parseabilityScore + ATSScoreValidatorConstants::BASIC_ANALYSIS_BONUS), // Cap at max, give some credit for basic checks
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

        // If parseability > threshold AND AI score > threshold AND no critical issues: trust AI scores, minimal adjustments
        if ($parseabilityScore > ATSScoreValidatorConstants::GOOD_SCORE_THRESHOLD && $aiOverallScore > ATSScoreValidatorConstants::GOOD_SCORE_THRESHOLD && ! $hasCriticalIssues) {
            // Only apply critical overrides (scanned image)
            if (($details['text_extractability']['is_scanned_image'] ?? false) === true) {
                $scores['format'] = min(ATSScoreValidatorConstants::PENALTY_SCANNED_IMAGE_MAX, $scores['format']);
                $scores['keyword'] = min(ATSScoreValidatorConstants::PENALTY_SCANNED_IMAGE_MAX, $scores['keyword']);
                $scores['content'] = min(ATSScoreValidatorConstants::PENALTY_SCANNED_IMAGE_MAX, $scores['content']);
            }

            // Return early - no other penalties if both scores are good
            return $scores;
        }

        // Apply penalties only if there are actual issues
        // If scanned image detected: override AI score to max threshold (critical parsing issue)
        if (($details['text_extractability']['is_scanned_image'] ?? false) === true) {
            $scores['format'] = min(ATSScoreValidatorConstants::PENALTY_SCANNED_IMAGE_MAX, $scores['format']);
            $scores['keyword'] = min(ATSScoreValidatorConstants::PENALTY_SCANNED_IMAGE_MAX, $scores['keyword']);
            $scores['content'] = min(ATSScoreValidatorConstants::PENALTY_SCANNED_IMAGE_MAX, $scores['content']);
        }

        // If date placeholders or missing dates detected: reduce format score significantly
        $dateDetection = $details['date_detection'] ?? [];
        if (($dateDetection['has_placeholders'] ?? false) === true) {
            $scores['format'] = max(ATSScoreValidatorConstants::MIN_SCORE, $scores['format'] - ATSScoreValidatorConstants::PENALTY_DATE_PLACEHOLDERS);
        } elseif (! ($dateDetection['has_valid_dates'] ?? true)) {
            $scores['format'] = max(ATSScoreValidatorConstants::MIN_SCORE, $scores['format'] - ATSScoreValidatorConstants::PENALTY_NO_DATES);
        }

        // If name missing: reduce format score significantly
        $nameDetection = $details['name_detection'] ?? [];
        if (! ($nameDetection['has_name'] ?? true)) {
            $scores['format'] = max(ATSScoreValidatorConstants::MIN_SCORE, $scores['format'] - ATSScoreValidatorConstants::PENALTY_NO_NAME);
        }

        // If summary missing: reduce format score
        $summaryDetection = $details['summary_detection'] ?? [];
        if (! ($summaryDetection['has_summary'] ?? false)) {
            $scores['format'] = max(ATSScoreValidatorConstants::MIN_SCORE, $scores['format'] - ATSScoreValidatorConstants::PENALTY_NO_SUMMARY);
        }

        // If insufficient bullet points: reduce content score
        $bulletPointCount = $details['bullet_point_count'] ?? [];
        $bulletCount = $bulletPointCount['count'] ?? 0;
        if ($bulletCount < ATSParseabilityCheckerConstants::BULLETS_MIN_OPTIMAL) {
            $penalty = match (true) {
                $bulletCount < ATSParseabilityCheckerConstants::BULLETS_VERY_FEW => ATSScoreValidatorConstants::PENALTY_VERY_FEW_BULLETS,
                $bulletCount < ATSParseabilityCheckerConstants::BULLETS_FEW => ATSScoreValidatorConstants::PENALTY_FEW_BULLETS,
                default => ATSScoreValidatorConstants::PENALTY_INSUFFICIENT_BULLETS,
            };
            $scores['content'] = max(ATSScoreValidatorConstants::MIN_SCORE, $scores['content'] - $penalty);
        }

        // If no quantifiable metrics: reduce content score significantly
        $metricsDetection = $details['metrics_detection'] ?? [];
        if (! ($metricsDetection['has_metrics'] ?? false)) {
            $scores['content'] = max(ATSScoreValidatorConstants::MIN_SCORE, $scores['content'] - ATSScoreValidatorConstants::PENALTY_NO_METRICS);
        }

        // If tables detected: reduce format score (apply even for warnings)
        // Stricter penalty aligned with ResumeWorded
        if (($details['table_detection']['has_tables'] ?? false) === true) {
            $scores['format'] = max(ATSScoreValidatorConstants::MIN_SCORE, $scores['format'] - ATSScoreValidatorConstants::PENALTY_TABLES);
        }

        // If multi-column layout detected: reduce format score (apply even for warnings)
        // Stricter penalty aligned with ResumeWorded
        if (($details['multi_column']['has_multi_column'] ?? false) === true) {
            $scores['format'] = max(ATSScoreValidatorConstants::MIN_SCORE, $scores['format'] - ATSScoreValidatorConstants::PENALTY_MULTI_COLUMN);
        }

        // If contact info not in first 300 chars: only penalize if contact doesn't exist at all
        $contactLocation = $details['contact_location'] ?? [];
        $emailInAcceptableArea = ($contactLocation['email_in_first_300'] ?? false) || ($contactLocation['email_in_first_10_lines'] ?? false);
        $phoneInAcceptableArea = ($contactLocation['phone_in_first_300'] ?? false) || ($contactLocation['phone_in_first_10_lines'] ?? false);

        if (! $emailInAcceptableArea && ! $phoneInAcceptableArea) {
            // Only reduce if contact doesn't exist at all
            if (! ($contactLocation['email_exists'] ?? false) && ! ($contactLocation['phone_exists'] ?? false)) {
                $scores['contact'] = (int) ($scores['contact'] * ATSScoreValidatorConstants::CONTACT_NO_EXISTS_MULTIPLIER);
            }
            // If contact exists but not in ideal location, AI already accounted for this - don't double-penalize
        }

        // If resume has more than 2 pages: reduce content score
        $documentLength = $details['document_length'] ?? [];
        if (($documentLength['page_count'] ?? 1) > ATSParseabilityCheckerConstants::PAGE_COUNT_MAX && $hasCriticalIssues) {
            $scores['content'] = (int) ($scores['content'] * ATSScoreValidatorConstants::CONTENT_LONG_RESUME_MULTIPLIER);
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
            $totalKeywords >= 20 => ATSScoreValidatorConstants::KEYWORD_SCORE_20_PLUS,
            $totalKeywords >= 15 => ATSScoreValidatorConstants::KEYWORD_SCORE_15_PLUS,
            $totalKeywords >= 10 => ATSScoreValidatorConstants::KEYWORD_SCORE_10_PLUS,
            $totalKeywords >= 5 => ATSScoreValidatorConstants::KEYWORD_SCORE_5_PLUS,
            default => ATSScoreValidatorConstants::KEYWORD_SCORE_DEFAULT,
        };

        // Adjust based on industry alignment (reduced bonus)
        $adjustment = match ($industryAlignment) {
            'high' => ATSScoreValidatorConstants::KEYWORD_BONUS_HIGH_ALIGNMENT,
            'medium' => ATSScoreValidatorConstants::KEYWORD_BONUS_MEDIUM_ALIGNMENT,
            default => 0,
        };

        return min(ATSScoreValidatorConstants::MAX_SCORE, $baseScore + $adjustment);
    }

    /**
     * Extract contact score from AI analysis.
     * AI tells us what contact info exists - convert to score.
     */
    protected function extractContactScoreFromAI(array $contactInfo): int
    {
        $score = 0;

        // Email: points (critical)
        if ($contactInfo['email_found'] ?? false) {
            $score += ATSScoreValidatorConstants::CONTACT_EMAIL_POINTS;
            // Bonus if in top location
            if (($contactInfo['email_location'] ?? '') === 'top') {
                $score += ATSScoreValidatorConstants::CONTACT_EMAIL_TOP_BONUS;
            } elseif (($contactInfo['email_location'] ?? '') === 'middle') {
                $score += ATSScoreValidatorConstants::CONTACT_EMAIL_MIDDLE_BONUS;
            }
        }

        // Phone: points
        if ($contactInfo['phone_found'] ?? false) {
            $score += ATSScoreValidatorConstants::CONTACT_PHONE_POINTS;
            // Bonus if in top location
            if (($contactInfo['phone_location'] ?? '') === 'top') {
                $score += ATSScoreValidatorConstants::CONTACT_PHONE_TOP_BONUS;
            } elseif (($contactInfo['phone_location'] ?? '') === 'middle') {
                $score += ATSScoreValidatorConstants::CONTACT_PHONE_MIDDLE_BONUS;
            }
        }

        // LinkedIn: points
        // LinkedIn as text ("LinkedIn") is acceptable - ATS can still parse it
        // Only full URL format is better, but not critical
        if ($contactInfo['linkedin_found'] ?? false) {
            $score += ATSScoreValidatorConstants::CONTACT_LINKEDIN_POINTS;
            // Check format - only minor deduction if not full URL
            // LinkedIn as text is acceptable, full URL is better but not critical
            if (! ($contactInfo['linkedin_format_correct'] ?? true)) {
                $score -= ATSScoreValidatorConstants::CONTACT_LINKEDIN_FORMAT_DEDUCTION;
            }
        }

        // GitHub: points
        if ($contactInfo['github_found'] ?? false) {
            $score += ATSScoreValidatorConstants::CONTACT_GITHUB_POINTS;
        }

        // Location: points
        if ($contactInfo['location_city_found'] ?? false) {
            $score += ATSScoreValidatorConstants::CONTACT_LOCATION_POINTS;
        }

        return min(ATSScoreValidatorConstants::MAX_SCORE, $score);
    }

    /**
     * Extract content quality score from AI analysis.
     * AI tells us about content quality - convert to score.
     */
    protected function extractContentScoreFromAI(array $contentQuality): int
    {
        $score = 0;

        // Action verbs: points
        if ($contentQuality['uses_action_verbs'] ?? false) {
            $score += ATSScoreValidatorConstants::CONTENT_ACTION_VERBS_POINTS;
            // Bonus if has multiple examples
            $actionVerbCount = count($contentQuality['action_verb_examples'] ?? []);
            if ($actionVerbCount >= 5) {
                $score += ATSScoreValidatorConstants::CONTENT_ACTION_VERBS_5_PLUS_BONUS;
            } elseif ($actionVerbCount >= 3) {
                $score += ATSScoreValidatorConstants::CONTENT_ACTION_VERBS_3_PLUS_BONUS;
            }
        }

        // Quantifiable achievements: points
        if ($contentQuality['quantifiable_achievements'] ?? false) {
            $score += ATSScoreValidatorConstants::CONTENT_ACHIEVEMENTS_POINTS;
            // Bonus if has multiple examples
            $achievementCount = count($contentQuality['achievement_examples'] ?? []);
            if ($achievementCount >= ATSScoreValidatorConstants::THIN_RESUME_ACHIEVEMENT_COUNT) {
                $score += ATSScoreValidatorConstants::CONTENT_ACHIEVEMENTS_3_PLUS_BONUS;
            } elseif ($achievementCount >= 2) {
                $score += ATSScoreValidatorConstants::CONTENT_ACHIEVEMENTS_2_PLUS_BONUS;
            }
        }

        // Appropriate length: points
        if ($contentQuality['appropriate_length'] ?? false) {
            $score += ATSScoreValidatorConstants::CONTENT_LENGTH_POINTS;
        } else {
            // Partial credit based on word count
            $wordCount = $contentQuality['estimated_word_count'] ?? 0;
            if ($wordCount >= 300 && $wordCount < ATSScoreValidatorConstants::THIN_RESUME_WORD_COUNT) {
                $score += ATSScoreValidatorConstants::CONTENT_LENGTH_CLOSE_PARTIAL;
            } elseif ($wordCount >= 800 && $wordCount <= 1000) {
                $score += ATSScoreValidatorConstants::CONTENT_LENGTH_LONG_PARTIAL;
            }
        }

        // Bullet points: points
        if ($contentQuality['has_bullet_points'] ?? false) {
            $score += ATSScoreValidatorConstants::CONTENT_BULLETS_POINTS;
        }

        return min(ATSScoreValidatorConstants::MAX_SCORE, $score);
    }

    /**
     * Calculate overall score combining all factors.
     * Aligned with ResumeWorded: more conservative scoring.
     * If parseability > threshold AND AI score > threshold: use weighted combination (AI 50%, parseability 50%).
     * Otherwise: use weighted average of all categories.
     *
     * @param  array<string, int>  $scores
     * @param  array<string>  $criticalIssues
     */
    protected function calculateOverallScore(int $parseabilityScore, array $scores, array $criticalIssues, int $aiOverallScore, int $wordCount = 0, int $achievementCount = 0, int $experienceRolesCount = 0): int
    {
        // If both scores are good, use balanced weighting
        if ($this->shouldUseBalancedWeighting($parseabilityScore, $aiOverallScore, $criticalIssues)) {
            return $this->calculateBalancedScore($parseabilityScore, $aiOverallScore, $wordCount, $achievementCount);
        }

        // Otherwise, use weighted average of all categories
        $finalScore = $this->calculateWeightedAverage($parseabilityScore, $scores);

        // Apply normalization if needed
        $finalScore = $this->applyScoreNormalization($finalScore, $criticalIssues, $wordCount, $achievementCount);

        // Apply ResumeWorded alignment factor
        $finalScore = $this->applyAlignmentFactor($finalScore, $criticalIssues);

        // Apply content-based penalties and caps
        $finalScore = $this->applyContentBasedAdjustments($finalScore, $scores, $wordCount, $achievementCount);

        return $finalScore;
    }

    /**
     * Determine if balanced weighting should be used (both scores are good).
     */
    protected function shouldUseBalancedWeighting(int $parseabilityScore, int $aiOverallScore, array $criticalIssues): bool
    {
        return $parseabilityScore > ATSScoreValidatorConstants::GOOD_SCORE_THRESHOLD
            && $aiOverallScore > ATSScoreValidatorConstants::GOOD_SCORE_THRESHOLD
            && empty($criticalIssues);
    }

    /**
     * Calculate balanced score using 50/50 weighting of AI and parseability scores.
     */
    protected function calculateBalancedScore(int $parseabilityScore, int $aiOverallScore, int $wordCount, int $achievementCount): int
    {
        $finalScore = (int) round(
            ($aiOverallScore * ATSScoreValidatorConstants::WEIGHT_AI_WHEN_GOOD) +
            ($parseabilityScore * ATSScoreValidatorConstants::WEIGHT_PARSEABILITY_WHEN_GOOD)
        );

        // Apply aggressive cap for thin resumes even if scores are good
        if ($this->isThinResume($wordCount, $achievementCount)) {
            $finalScore = min($finalScore, ATSScoreValidatorConstants::ENTRY_LEVEL_CAP_SCORE);
        }

        return $finalScore;
    }

    /**
     * Calculate weighted average of all score categories.
     *
     * @param  array<string, int>  $scores
     */
    protected function calculateWeightedAverage(int $parseabilityScore, array $scores): int
    {
        return (int) round(
            ($parseabilityScore * ATSScoreValidatorConstants::WEIGHT_PARSEABILITY) +
            ($scores['format'] * ATSScoreValidatorConstants::WEIGHT_FORMAT) +
            ($scores['keyword'] * ATSScoreValidatorConstants::WEIGHT_KEYWORD) +
            ($scores['contact'] * ATSScoreValidatorConstants::WEIGHT_CONTACT) +
            ($scores['content'] * ATSScoreValidatorConstants::WEIGHT_CONTENT)
        );
    }

    /**
     * Apply score normalization (bump minimum score if no critical issues).
     */
    protected function applyScoreNormalization(int $finalScore, array $criticalIssues, int $wordCount, int $achievementCount): int
    {
        $isThinResume = $this->isThinResume($wordCount, $achievementCount);

        if ($finalScore < ATSScoreValidatorConstants::NORMALIZATION_THRESHOLD
            && empty($criticalIssues)
            && ! $isThinResume) {
            $finalScore = max($finalScore, ATSScoreValidatorConstants::NORMALIZED_MIN_SCORE);
        }

        return $finalScore;
    }

    /**
     * Apply ResumeWorded alignment factor based on critical issues.
     */
    protected function applyAlignmentFactor(int $finalScore, array $criticalIssues): int
    {
        $criticalIssueCount = count($criticalIssues);

        $alignmentMultiplier = match (true) {
            $criticalIssueCount >= 2 => ATSScoreValidatorConstants::ALIGNMENT_MULTIPLE_CRITICAL,
            $criticalIssueCount >= 1 => ATSScoreValidatorConstants::ALIGNMENT_ONE_CRITICAL,
            default => ATSScoreValidatorConstants::BASE_ALIGNMENT_MULTIPLIER,
        };

        return (int) round($finalScore * $alignmentMultiplier);
    }

    /**
     * Apply content-based adjustments (penalties and caps).
     *
     * @param  array<string, int>  $scores
     */
    protected function applyContentBasedAdjustments(int $finalScore, array $scores, int $wordCount, int $achievementCount): int
    {
        // Additional penalty if content score is already low from AI (thin content)
        $finalContentScore = $scores['content'] ?? 0;
        if ($finalContentScore < ATSScoreValidatorConstants::POOR_CONTENT_THRESHOLD) {
            $finalScore = max(ATSScoreValidatorConstants::MIN_SCORE, $finalScore - ATSScoreValidatorConstants::PENALTY_POOR_CONTENT);
        }

        // Cap final score if resume has good format but lacks substantial content
        $finalFormatScore = $scores['format'] ?? 0;
        if ($finalFormatScore > ATSScoreValidatorConstants::GOOD_SCORE_THRESHOLD
            && $finalContentScore < ATSScoreValidatorConstants::POOR_CONTENT_THRESHOLD) {
            $finalScore = min($finalScore, ATSScoreValidatorConstants::FORMAT_GOOD_CONTENT_POOR_CAP);
        }

        // Additional aggressive cap for thin resumes
        if ($this->isThinResume($wordCount, $achievementCount)) {
            $finalScore = min($finalScore, ATSScoreValidatorConstants::ENTRY_LEVEL_CAP_SCORE);
        }

        return $finalScore;
    }

    /**
     * Check if resume is thin (short with few achievements).
     */
    protected function isThinResume(int $wordCount, int $achievementCount): bool
    {
        return $wordCount < ATSScoreValidatorConstants::THIN_RESUME_WORD_COUNT
            && $achievementCount < ATSScoreValidatorConstants::THIN_RESUME_ACHIEVEMENT_COUNT;
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

        // Critical issues: only if category score < threshold OR overall score < threshold
        // These are actual parsing problems that break ATS compatibility
        foreach ($criticalIssues as $issue) {
            // Check if it's a true critical issue (parsing problem)
            $isTrueCritical = $overallScore < ATSScoreValidatorConstants::CRITICAL_SCORE_THRESHOLD ||
                $scores['format'] < ATSScoreValidatorConstants::CRITICAL_SCORE_THRESHOLD ||
                $scores['contact'] < ATSScoreValidatorConstants::CRITICAL_SCORE_THRESHOLD ||
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

        // Warnings: if score threshold-60 or category score threshold-60
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
            $score += ATSScoreValidatorConstants::CONTACT_LOCATION_POINTS;
        } elseif ($contactLocation['email_exists'] ?? false) {
            $score += 2; // Partial credit if exists but not in first 200
        }

        if ($contactLocation['phone_in_first_200'] ?? false) {
            $score += 3;
        } elseif ($contactLocation['phone_exists'] ?? false) {
            $score += 1; // Partial credit if exists but not in first 200
        }

        return min(ATSScoreValidatorConstants::MAX_SCORE, $score);
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
