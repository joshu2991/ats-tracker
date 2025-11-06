<?php

namespace App\Services\Detectors;

use App\Services\ATSParseabilityCheckerConstants;

/**
 * Metrics Detector
 *
 * Detects quantifiable metrics (numbers, percentages, etc.) in resumes.
 */
class MetricsDetector
{
    /**
     * Check for quantifiable metrics (numbers, percentages, etc.).
     *
     * @return array{has_metrics: bool, metric_count: int}
     */
    public function checkQuantifiableMetrics(string $text): array
    {
        $metricPatterns = [
            // Percentages: "increased by 30%", "reduced by 25%"
            '/\d+\s*%/',
            // Dollar amounts: "$50K", "$1M", "$500,000"
            '/\$[\d,]+[KM]?/i',
            // Numbers with units: "5 years", "10 team members", "100+ users"
            '/\d+\+?\s*(years?|months?|team\s+members?|users?|customers?|clients?|projects?|employees?|people|hours?|days?)/i',
            // Specific numbers: "from 40 to 8 hours", "reduced from 13 to 1 second"
            '/\d+\s+(to|from|by)\s+\d+/i',
            // Multipliers: "2x", "3x", "10x"
            '/\d+x\b/i',
        ];

        $count = 0;
        foreach ($metricPatterns as $pattern) {
            $matches = preg_match_all($pattern, $text);
            if ($matches !== false) {
                $count += $matches;
            }
        }

        // Need at least 3 quantifiable metrics
        $hasMetrics = $count >= ATSParseabilityCheckerConstants::MIN_METRICS_COUNT;

        return [
            'has_metrics' => $hasMetrics,
            'metric_count' => $count,
        ];
    }
}
