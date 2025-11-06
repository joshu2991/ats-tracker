<?php

namespace App\Services\Detectors;

use App\Services\ATSParseabilityCheckerConstants;

/**
 * Experience Analyzer
 *
 * Detects experience level from resume text.
 */
class ExperienceAnalyzer
{
    /**
     * Detect experience level from resume text.
     *
     * @return array{years: int, is_experienced: bool}
     */
    public function detectExperienceLevel(string $text): array
    {
        $experiencePatterns = [
            // "X+ years of experience"
            '/\b(\d+)\+?\s*years?\s+of\s+experience\b/i',
            // "X years experience"
            '/\b(\d+)\+?\s*years?\s+experience\b/i',
            // "X years in"
            '/\b(\d+)\+?\s*years?\s+in\b/i',
            // "X years"
            '/\b(\d+)\+?\s*years?\s+(of\s+)?(professional|work|industry|relevant)\b/i',
        ];

        $maxYears = 0;

        foreach ($experiencePatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $years = (int) $match;
                    $maxYears = max($maxYears, $years);
                }
            }
        }

        // If no explicit mention, try to infer from work history
        // Count work experience entries (looking for company names or job titles followed by dates)
        if ($maxYears === 0) {
            // Try to count work experience entries
            $workExperiencePattern = '/\b(?:work\s+)?experience|employment|professional\s+experience|career\s+history\b/i';
            if (preg_match($workExperiencePattern, $text)) {
                // Estimate based on number of positions mentioned
                // This is a heuristic - not perfect but better than nothing
                $positionCount = preg_match_all('/\b(?:Senior|Junior|Lead|Manager|Developer|Engineer|Analyst|Specialist|Coordinator|Director|VP|President|CEO|CTO|Founder|Co-founder)\b/i', $text);
                if ($positionCount >= ATSParseabilityCheckerConstants::MIN_POSITIONS_FOR_5_YEARS) {
                    $maxYears = ATSParseabilityCheckerConstants::ESTIMATED_YEARS_MANY_POSITIONS;
                } elseif ($positionCount >= ATSParseabilityCheckerConstants::MIN_POSITIONS_FOR_3_YEARS) {
                    $maxYears = ATSParseabilityCheckerConstants::ESTIMATED_YEARS_FEW_POSITIONS;
                }
            }
        }

        return [
            'years' => $maxYears,
            'is_experienced' => $maxYears >= ATSParseabilityCheckerConstants::EXPERIENCED_YEARS,
        ];
    }
}
