<?php

namespace App\Services;

use App\Services\Detectors\BulletPointDetector;
use App\Services\Detectors\ContentDetector;
use App\Services\Detectors\ExperienceAnalyzer;
use App\Services\Detectors\FormatDetector;
use App\Services\Detectors\LengthAnalyzer;
use App\Services\Detectors\MetricsDetector;

/**
 * ATS Parseability Checker Service
 *
 * This service performs comprehensive checks on resume files to determine
 * ATS (Applicant Tracking System) compatibility. It detects format issues,
 * parseability problems, and content quality indicators.
 * 
 */
class ATSParseabilityChecker
{
    public function __construct(
        protected FormatDetector $formatDetector,
        protected ContentDetector $contentDetector,
        protected LengthAnalyzer $lengthAnalyzer,
        protected BulletPointDetector $bulletPointDetector,
        protected MetricsDetector $metricsDetector,
        protected ExperienceAnalyzer $experienceAnalyzer
    ) {}

    /**
     * Check PDF parseability and detect technical issues.
     *
     * @return array{
     *     score: int,
     *     critical_issues: array<string>,
     *     warnings: array<string>,
     *     confidence: 'high'|'medium'|'low',
     *     details: array<string, mixed>
     * }
     */
    public function check(string $filePath, string $parsedText, string $mimeType): array
    {
        $score = ATSParseabilityCheckerConstants::STARTING_SCORE;
        $criticalIssues = [];
        $warnings = [];
        $details = [];

        // Check 1: Text Extractability (scanned image detection)
        $textExtractability = $this->formatDetector->checkTextExtractability($filePath, $parsedText, $mimeType);
        $details['text_extractability'] = $textExtractability;

        if ($textExtractability['is_scanned_image']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_SCANNED_IMAGE;
            $criticalIssues[] = $textExtractability['message'];
        }

        // Check 2: Table Detection
        $tableDetection = $this->formatDetector->detectTables($parsedText);
        $details['table_detection'] = $tableDetection;

        if ($tableDetection['has_tables']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_TABLES;
            $warnings[] = $tableDetection['message'];
        }

        // Check 3: Multi-Column Layout Detection
        $multiColumn = $this->formatDetector->detectMultiColumnLayout($parsedText);
        $details['multi_column'] = $multiColumn;

        if ($multiColumn['has_multi_column']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_MULTI_COLUMN;
            $warnings[] = $multiColumn['message'];
        }

        // Check 4: Document Length Verification
        $lengthCheck = $this->lengthAnalyzer->verifyDocumentLength($filePath, $parsedText, $mimeType, [$this->lengthAnalyzer, 'countWords']);
        $details['document_length'] = $lengthCheck;

        if (! $lengthCheck['is_optimal']) {
            $wordCount = $lengthCheck['word_count'] ?? 0;
            if ($wordCount < ATSParseabilityCheckerConstants::WORD_COUNT_MIN) {
                $score -= ATSParseabilityCheckerConstants::PENALTY_SHORT_RESUME;
            } elseif ($wordCount > ATSParseabilityCheckerConstants::WORD_COUNT_MAX) {
                $score -= ATSParseabilityCheckerConstants::PENALTY_LONG_RESUME;
            } else {
                $score -= ATSParseabilityCheckerConstants::PENALTY_PAGE_COUNT;
            }
            $warnings[] = $lengthCheck['message'];
        }

        // Check 5: Contact Info Location (expanded to 300 chars for PDF header cases)
        $contactLocation = $this->contentDetector->checkContactInfoLocation($parsedText);
        $details['contact_location'] = $contactLocation;

        // Check if contact is in first 300 chars OR first 10 lines (covers PDF header cases)
        $emailInAcceptableArea = $contactLocation['email_in_first_300'] || $contactLocation['email_in_first_10_lines'];
        $phoneInAcceptableArea = $contactLocation['phone_in_first_300'] || $contactLocation['phone_in_first_10_lines'];

        if (! $emailInAcceptableArea && ! $phoneInAcceptableArea) {
            // Only penalize if contact doesn't exist at all OR is really far from top
            if ($contactLocation['email_exists'] || $contactLocation['phone_exists']) {
                $score -= ATSParseabilityCheckerConstants::PENALTY_CONTACT_BAD_LOCATION;
                $warnings[] = 'Contact information not found in first '.ATSParseabilityCheckerConstants::CONTACT_CHECK_CHARS.' characters or top '.ATSParseabilityCheckerConstants::CONTACT_CHECK_LINES.' lines. ATS systems may miss this information if it\'s in a header/footer.';
            } else {
                $score -= ATSParseabilityCheckerConstants::PENALTY_NO_CONTACT;
                $criticalIssues[] = 'No contact information (email or phone) found in the resume. This is critical for ATS systems.';
            }
        } elseif ($contactLocation['may_be_in_pdf_header']) {
            // Contact appears in first 10 lines but not first 300 chars - likely PDF header
            $warnings[] = 'Contact information may be in PDF header/footer. ATS systems may miss headers/footers - consider moving to main body text.';
        } elseif (! $emailInAcceptableArea && $contactLocation['email_exists']) {
            $warnings[] = 'Email not found in first '.ATSParseabilityCheckerConstants::CONTACT_CHECK_CHARS.' characters. Consider moving it to the top of the resume for better ATS compatibility.';
        } elseif (! $phoneInAcceptableArea && $contactLocation['phone_exists']) {
            $warnings[] = 'Phone number not found in first '.ATSParseabilityCheckerConstants::CONTACT_CHECK_CHARS.' characters. Consider moving it to the top of the resume for better ATS compatibility.';
        }

        // Check 6: Date Detection (critical for ATS systems)
        $dateDetection = $this->contentDetector->checkDates($parsedText);
        $details['date_detection'] = $dateDetection;

        if ($dateDetection['has_placeholders']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_DATE_PLACEHOLDERS;
            $criticalIssues[] = $dateDetection['message'];
        } elseif (! $dateDetection['has_valid_dates']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_NO_DATES;
            $criticalIssues[] = 'No dates found in work experience or education sections. ATS systems require dates to verify employment history and education timeline.';
        }

        // Check 7: Experience Level Detection (for length penalty adjustment)
        $experienceLevel = $this->experienceAnalyzer->detectExperienceLevel($parsedText);
        $details['experience_level'] = $experienceLevel;

        // Adjust length penalty based on experience level
        if (! $lengthCheck['is_optimal'] && ($experienceLevel['is_experienced'] ?? false)) {
            $wordCount = $lengthCheck['word_count'] ?? 0;
            // For experienced candidates (5+ years), short resumes are more critical
            if ($wordCount < ATSParseabilityCheckerConstants::WORD_COUNT_MIN) {
                $score -= ATSParseabilityCheckerConstants::PENALTY_EXPERIENCED_SHORT_RESUME;
                $warnings[] = 'Resume is too short for your experience level. With '.($experienceLevel['years'] ?? 0).'+ years of experience, you should have more content to showcase your achievements.';
            }
        }

        // Check 8: Name Detection (critical for ATS systems)
        $nameDetection = $this->contentDetector->checkName($parsedText);
        $details['name_detection'] = $nameDetection;

        if (! $nameDetection['has_name']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_NO_NAME;
            $criticalIssues[] = 'No name found in the resume. ATS systems require a candidate name for proper identification and tracking.';
        }

        // Check 9: Summary/Profile Detection
        $summaryDetection = $this->contentDetector->checkSummary($parsedText, [$this->lengthAnalyzer, 'countWords']);
        $details['summary_detection'] = $summaryDetection;

        if (! $summaryDetection['has_summary']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_NO_SUMMARY;
            $warnings[] = 'No summary or professional profile section found. A summary section helps ATS systems and recruiters quickly understand your background and career goals.';
        }

        // Check 10: Bullet Point Count
        $bulletPointCount = $this->bulletPointDetector->countBulletPoints($parsedText);
        $details['bullet_point_count'] = $bulletPointCount;

        if (! $bulletPointCount['is_optimal']) {
            $bulletCount = $bulletPointCount['count'];
            $bySection = $bulletPointCount['by_section'] ?? ['experience' => 0, 'projects' => 0, 'other' => 0];
            $experienceBullets = $bySection['experience'] ?? 0;
            $projectsBullets = $bySection['projects'] ?? 0;
            $otherBullets = $bySection['other'] ?? 0;

            // Ideal: 12-20 bullet points total, with 8+ in Experience section
            // Experience bullets are more important than Projects for ATS systems
            $penalty = match (true) {
                $bulletCount < ATSParseabilityCheckerConstants::BULLETS_VERY_FEW => ATSParseabilityCheckerConstants::PENALTY_VERY_FEW_BULLETS,
                $bulletCount < ATSParseabilityCheckerConstants::BULLETS_FEW => ATSParseabilityCheckerConstants::PENALTY_FEW_BULLETS,
                default => ATSParseabilityCheckerConstants::PENALTY_INSUFFICIENT_BULLETS,
            };

            // Additional penalty if Experience section has too few bullets
            if ($experienceBullets < ATSParseabilityCheckerConstants::BULLETS_EXPERIENCE_MIN) {
                $penalty += match (true) {
                    $experienceBullets < ATSParseabilityCheckerConstants::BULLETS_EXPERIENCE_VERY_FEW => ATSParseabilityCheckerConstants::PENALTY_VERY_FEW_EXPERIENCE_BULLETS,
                    $experienceBullets < ATSParseabilityCheckerConstants::BULLETS_EXPERIENCE_FEW => ATSParseabilityCheckerConstants::PENALTY_FEW_EXPERIENCE_BULLETS,
                    default => 0,
                };
            }

            $score -= $penalty;

            // Check for potential non-standard bullets that weren't detected
            $potentialNonStandardBullets = $bulletPointCount['potential_non_standard_bullets'] ?? 0;
            $nonStandardBySection = $bulletPointCount['non_standard_by_section'] ?? ['experience' => 0, 'projects' => 0, 'other' => 0];
            $hasNonStandardBullets = $potentialNonStandardBullets > 0;

            // Generate specific warning message based on section distribution
            $warningMessage = "Resume has {$bulletCount} total bullet points (recommended: ".ATSParseabilityCheckerConstants::BULLETS_MIN_OPTIMAL.'-'.ATSParseabilityCheckerConstants::BULLETS_MAX_OPTIMAL.'). ';

            // Always show breakdown if we detected sections
            $sectionsFound = $bulletPointCount['sections_found'] ?? [];
            if (! empty($sectionsFound) || $experienceBullets > 0 || $projectsBullets > 0 || $otherBullets > 0) {
                $warningMessage .= 'Breakdown: ';
                $sections = [];
                $sections[] = "{$experienceBullets} in Experience";
                // Always show Projects if section exists (even if 0 bullets) OR if it has bullets
                if (in_array('projects', $sectionsFound, true) || $projectsBullets > 0) {
                    $sections[] = "{$projectsBullets} in Projects";
                }
                if ($otherBullets > 0) {
                    $sections[] = "{$otherBullets} in other sections";
                }
                $warningMessage .= implode(', ', $sections).'. ';
            }

            // Add warning about non-standard bullets if detected
            if ($hasNonStandardBullets && $bulletCount < ATSParseabilityCheckerConstants::BULLETS_MIN_OPTIMAL) {
                $nonStandardProjects = $nonStandardBySection['projects'] ?? 0;
                $nonStandardExperience = $nonStandardBySection['experience'] ?? 0;

                if ($nonStandardProjects > 0 || $nonStandardExperience > 0) {
                    $warningMessage .= "Note: {$potentialNonStandardBullets} potential bullet point(s) detected but not recognized (likely due to non-standard bullet characters). Consider normalizing bullet characters to standard format (•, -, or *) for better ATS compatibility. ";
                }
            }

            // Generate specific recommendation based on section distribution
            $hasProjectsSection = in_array('projects', $sectionsFound, true);

            if ($experienceBullets < ATSParseabilityCheckerConstants::BULLETS_EXPERIENCE_MIN) {
                $warningMessage .= 'Focus on adding more bullet points in your Experience section (aim for '.ATSParseabilityCheckerConstants::BULLETS_EXPERIENCE_MIN.'-'.ATSParseabilityCheckerConstants::BULLETS_MIN_OPTIMAL.' bullets). ';
            } elseif ($bulletCount < ATSParseabilityCheckerConstants::BULLETS_MIN_OPTIMAL) {
                // If Experience has enough bullets but total is low, suggest adding to specific sections
                // Only suggest adding to Projects if it truly has 0 bullets (not just undetected)
                if ($hasProjectsSection && $projectsBullets === 0 && $experienceBullets >= ATSParseabilityCheckerConstants::BULLETS_EXPERIENCE_MIN) {
                    $warningMessage .= 'Your Projects section has no bullet points - consider adding bullet points to showcase your work. ';
                } elseif ($hasProjectsSection && $experienceBullets >= ATSParseabilityCheckerConstants::BULLETS_EXPERIENCE_MIN && $projectsBullets > 0 && $projectsBullets < ATSParseabilityCheckerConstants::BULLETS_IMPLICIT_MIN) {
                    $warningMessage .= 'Consider adding more bullet points to your Projects section (currently has '.$projectsBullets.'). ';
                } elseif ($experienceBullets >= ATSParseabilityCheckerConstants::BULLETS_EXPERIENCE_MIN && ($projectsBullets > 0 || ! $hasProjectsSection)) {
                    $warningMessage .= 'Consider adding more bullet points to highlight achievements and metrics. ';
                } else {
                    $warningMessage .= 'Consider adding more bullet points across all sections to highlight achievements and metrics. ';
                }
            } else {
                $warningMessage .= 'More bullet points with specific achievements and metrics will improve ATS compatibility and readability.';
            }

            $warnings[] = $warningMessage;
        }

        // Check 11: Quantifiable Metrics Detection
        $metricsDetection = $this->metricsDetector->checkQuantifiableMetrics($parsedText);
        $details['metrics_detection'] = $metricsDetection;

        if (! $metricsDetection['has_metrics']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_NO_METRICS;
            $warnings[] = 'Resume lacks quantifiable metrics and specific numbers. ATS systems and recruiters value resumes with measurable achievements (e.g., "increased sales by 30%", "managed team of 5", "reduced costs by $50K").';
        }

        // Ensure score doesn't go below 0
        $score = max(ATSParseabilityCheckerConstants::MIN_SCORE, $score);

        // Determine confidence level
        $issueCount = count($criticalIssues) + count($warnings);
        $confidence = match (true) {
            $issueCount === ATSParseabilityCheckerConstants::CONFIDENCE_HIGH_MAX_ISSUES => 'high',
            $issueCount <= ATSParseabilityCheckerConstants::CONFIDENCE_MEDIUM_MAX_ISSUES => 'medium',
            default => 'low',
        };

        return [
            'score' => $score,
            'critical_issues' => $criticalIssues,
            'warnings' => $warnings,
            'confidence' => $confidence,
            'details' => $details,
        ];
    }
}
