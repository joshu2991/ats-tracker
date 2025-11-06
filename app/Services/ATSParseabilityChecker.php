<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

/**
 * ATS Parseability Checker Service
 *
 * This service performs comprehensive checks on resume files to determine
 * ATS (Applicant Tracking System) compatibility. It detects format issues,
 * parseability problems, and content quality indicators.
 *
 * IMPORTANT: The bullet point detection logic (countBulletPoints method)
 * was manually tested with real resumes to ensure accuracy. Any changes
 * to this logic must be thoroughly tested before deployment.
 */
class ATSParseabilityChecker
{
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
        $textExtractability = $this->checkTextExtractability($filePath, $parsedText, $mimeType);
        $details['text_extractability'] = $textExtractability;

        if ($textExtractability['is_scanned_image']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_SCANNED_IMAGE;
            $criticalIssues[] = $textExtractability['message'];
        }

        // Check 2: Table Detection
        $tableDetection = $this->detectTables($parsedText);
        $details['table_detection'] = $tableDetection;

        if ($tableDetection['has_tables']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_TABLES;
            $warnings[] = $tableDetection['message'];
        }

        // Check 3: Multi-Column Layout Detection
        $multiColumn = $this->detectMultiColumnLayout($parsedText);
        $details['multi_column'] = $multiColumn;

        if ($multiColumn['has_multi_column']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_MULTI_COLUMN;
            $warnings[] = $multiColumn['message'];
        }

        // Check 4: Document Length Verification
        $lengthCheck = $this->verifyDocumentLength($filePath, $parsedText, $mimeType);
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
        $contactLocation = $this->checkContactInfoLocation($parsedText);
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
        $dateDetection = $this->checkDates($parsedText);
        $details['date_detection'] = $dateDetection;

        if ($dateDetection['has_placeholders']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_DATE_PLACEHOLDERS;
            $criticalIssues[] = $dateDetection['message'];
        } elseif (! $dateDetection['has_valid_dates']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_NO_DATES;
            $criticalIssues[] = 'No dates found in work experience or education sections. ATS systems require dates to verify employment history and education timeline.';
        }

        // Check 7: Experience Level Detection (for length penalty adjustment)
        $experienceLevel = $this->detectExperienceLevel($parsedText);
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
        $nameDetection = $this->checkName($parsedText);
        $details['name_detection'] = $nameDetection;

        if (! $nameDetection['has_name']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_NO_NAME;
            $criticalIssues[] = 'No name found in the resume. ATS systems require a candidate name for proper identification and tracking.';
        }

        // Check 9: Summary/Profile Detection
        $summaryDetection = $this->checkSummary($parsedText);
        $details['summary_detection'] = $summaryDetection;

        if (! $summaryDetection['has_summary']) {
            $score -= ATSParseabilityCheckerConstants::PENALTY_NO_SUMMARY;
            $warnings[] = 'No summary or professional profile section found. A summary section helps ATS systems and recruiters quickly understand your background and career goals.';
        }

        // Check 10: Bullet Point Count
        // NOTE: This method uses complex logic that was manually tested with real resumes.
        // Any changes to countBulletPoints() must be thoroughly tested.
        $bulletPointCount = $this->countBulletPoints($parsedText);
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
        $metricsDetection = $this->checkQuantifiableMetrics($parsedText);
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

    /**
     * Check if PDF is a scanned image (no extractable text).
     *
     * Scanned PDFs are images of documents, not actual text-based PDFs. ATS systems
     * cannot extract text from scanned images, making them incompatible.
     *
     * Detection Strategy:
     * - For multi-page PDFs: If text length < 50 chars, likely scanned
     * - For single-page PDFs: If text length < 20 chars, likely scanned
     * - Uses PDF parser to get actual page count for accurate detection
     *
     * @return array{
     *     is_scanned_image: bool,
     *     message: string,
     *     page_count?: int,
     *     text_length?: int
     * }
     */
    protected function checkTextExtractability(string $filePath, string $parsedText, string $mimeType): array
    {
        if ($mimeType !== 'application/pdf' && ! str_ends_with($filePath, '.pdf')) {
            return [
                'is_scanned_image' => false,
                'message' => 'Not a PDF file',
            ];
        }

        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($filePath);
            $pages = $pdf->getPages();
            $pageCount = count($pages);

            $textLength = strlen(trim($parsedText));

            // If text is very short but PDF has multiple pages, likely scanned
            if ($textLength < ATSParseabilityCheckerConstants::TEXT_LENGTH_MIN_MULTI_PAGE && $pageCount > 1) {
                return [
                    'is_scanned_image' => true,
                    'message' => 'PDF appears to be a scanned image. ATS systems cannot extract text from images. Consider using OCR or recreating as a text-based PDF.',
                    'page_count' => $pageCount,
                    'text_length' => $textLength,
                ];
            }

            // If text is very short even on single page, likely scanned
            if ($textLength < ATSParseabilityCheckerConstants::TEXT_LENGTH_MIN_SINGLE_PAGE) {
                return [
                    'is_scanned_image' => true,
                    'message' => 'PDF appears to be a scanned image with minimal text extraction. ATS systems may struggle to parse this document.',
                    'page_count' => $pageCount,
                    'text_length' => $textLength,
                ];
            }

            return [
                'is_scanned_image' => false,
                'message' => 'Text extraction successful',
                'page_count' => $pageCount,
                'text_length' => $textLength,
            ];
        } catch (\Exception $e) {
            Log::warning('PDF parseability check failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'is_scanned_image' => false,
                'message' => 'Could not verify text extractability',
            ];
        }
    }

    /**
     * Detect if resume uses tables.
     *
     * Tables are problematic for ATS systems because they can cause text to be
     * read in the wrong order or scrambled. This method detects table-like structures
     * by looking for patterns of multiple columns (3+ spaces or tabs separating content).
     *
     * Detection Strategy:
     * - Looks for lines with 3+ spaces or tabs (indicating columns)
     * - Counts columns by splitting on whitespace
     * - Requires at least 3 lines with table patterns to confirm tables exist
     *
     * @return array{
     *     has_tables: bool,
     *     message: string,
     *     table_line_count: int,
     *     approximate_lines: array<int>
     * }
     */
    protected function detectTables(string $text): array
    {
        $lines = explode("\n", $text);
        $tableLines = [];
        $tablePatterns = 0;

        foreach ($lines as $lineNum => $line) {
            // Check for multiple spaces (3+ spaces) or tabs indicating columns
            if (preg_match('/\s{3,}/', $line) || str_contains($line, "\t")) {
                // Count how many columns (separated by 3+ spaces or tabs)
                $columns = preg_split('/\s{3,}|\t+/', trim($line));
                $columnCount = count(array_filter($columns, fn ($col) => trim($col) !== ''));

                if ($columnCount >= ATSParseabilityCheckerConstants::TABLE_MIN_COLUMNS) {
                    $tableLines[] = $lineNum + 1;
                    $tablePatterns++;
                }
            }
        }

        $hasTables = $tablePatterns >= ATSParseabilityCheckerConstants::TABLE_MIN_PATTERNS;

        return [
            'has_tables' => $hasTables,
            'message' => $hasTables
                ? 'Table-like structure detected. ATS systems often fail to parse tables correctly, causing text to be scrambled or lost. Consider using simple bullet points instead.'
                : 'No table structure detected',
            'table_line_count' => count($tableLines),
            'approximate_lines' => $hasTables ? array_slice($tableLines, 0, 5) : [],
        ];
    }

    /**
     * Detect multi-column layout.
     *
     * Multi-column layouts (like newspaper columns) cause ATS systems to read text
     * in the wrong order. ATS systems read left-to-right, top-to-bottom, but
     * multi-column layouts require reading down one column, then the next.
     *
     * Detection Strategy:
     * - Pattern 1: Very short line followed by long line (text jumping between columns)
     * - Pattern 2: Lines with text on both ends (left-right alignment)
     * - Pattern 3: Very inconsistent line lengths (short, long, short, long pattern)
     * - Requires at least 10 suspicious patterns to confirm multi-column layout
     *
     * @return array{
     *     has_multi_column: bool,
     *     message: string,
     *     confidence: 'high'|'medium'|'low',
     *     suspicious_patterns: int
     * }
     */
    protected function detectMultiColumnLayout(string $text): array
    {
        $lines = explode("\n", $text);
        $suspiciousLines = 0;
        $totalLines = count($lines);

        // Check for patterns that suggest multi-column layout:
        // 1. Short lines followed by long lines (text jumping between columns)
        // 2. Lines that seem to have text on both ends (left-right alignment)
        // 3. Inconsistent line lengths in adjacent lines

        for ($i = 0; $i < min(ATSParseabilityCheckerConstants::MULTI_COLUMN_CHECK_LINES, $totalLines - 1); $i++) {
            $currentLine = trim($lines[$i]);
            $nextLine = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';

            // Skip empty lines
            if (empty($currentLine) || empty($nextLine)) {
                continue;
            }

            $currentLength = strlen($currentLine);
            $nextLength = strlen($nextLine);

            // Pattern 1: Very short line followed by long line (text jumping)
            if ($currentLength < ATSParseabilityCheckerConstants::MULTI_COLUMN_SHORT_LINE && $nextLength > ATSParseabilityCheckerConstants::MULTI_COLUMN_LONG_LINE) {
                $suspiciousLines++;
            }

            // Pattern 2: Lines with text on both ends (left-right alignment)
            // Check if line has text that seems to be at start and end
            if (preg_match('/^.{1,20}.*\s{10,}.*.{1,20}$/', $currentLine)) {
                $suspiciousLines++;
            }

            // Pattern 3: Very inconsistent lengths (short, long, short, long pattern)
            if ($i > 0 && isset($lines[$i - 1])) {
                $prevLength = strlen(trim($lines[$i - 1]));
                if (abs($currentLength - $prevLength) > ATSParseabilityCheckerConstants::MULTI_COLUMN_LENGTH_DIFF && abs($currentLength - $nextLength) > ATSParseabilityCheckerConstants::MULTI_COLUMN_LENGTH_DIFF) {
                    $suspiciousLines++;
                }
            }
        }

        $hasMultiColumn = $suspiciousLines >= ATSParseabilityCheckerConstants::MULTI_COLUMN_MIN_PATTERNS;
        $confidence = match (true) {
            $suspiciousLines >= ATSParseabilityCheckerConstants::MULTI_COLUMN_HIGH_CONFIDENCE => 'high',
            $suspiciousLines >= ATSParseabilityCheckerConstants::MULTI_COLUMN_MIN_PATTERNS => 'medium',
            default => 'low',
        };

        return [
            'has_multi_column' => $hasMultiColumn,
            'message' => $hasMultiColumn
                ? 'Multi-column layout detected. ATS systems read text left-to-right, top-to-bottom. Multi-column layouts can cause text to be read in the wrong order.'
                : 'No multi-column layout detected',
            'confidence' => $confidence,
            'suspicious_patterns' => $suspiciousLines,
        ];
    }

    /**
     * Verify document length is optimal.
     */
    protected function verifyDocumentLength(string $filePath, string $parsedText, string $mimeType): array
    {
        $wordCount = $this->countWords($parsedText);
        $pageCount = 1;

        // Try to get page count for PDFs
        if ($mimeType === 'application/pdf' || str_ends_with($filePath, '.pdf')) {
            try {
                $parser = new Parser;
                $pdf = $parser->parseFile($filePath);
                $pages = $pdf->getPages();
                $pageCount = count($pages);
            } catch (\Exception $e) {
                // If we can't get page count, estimate based on word count
                $pageCount = max(ATSParseabilityCheckerConstants::PAGE_COUNT_MIN, (int) ceil($wordCount / ATSParseabilityCheckerConstants::WORDS_PER_PAGE));
            }
        }

        $isOptimalLength = $wordCount >= ATSParseabilityCheckerConstants::WORD_COUNT_MIN && $wordCount <= ATSParseabilityCheckerConstants::WORD_COUNT_MAX;
        $isOptimalPages = $pageCount >= ATSParseabilityCheckerConstants::PAGE_COUNT_MIN && $pageCount <= ATSParseabilityCheckerConstants::PAGE_COUNT_MAX;

        $isOptimal = $isOptimalLength && $isOptimalPages;

        $message = match (true) {
            $wordCount < ATSParseabilityCheckerConstants::WORD_COUNT_MIN => "Resume is too short ({$wordCount} words, ideal: ".ATSParseabilityCheckerConstants::WORD_COUNT_MIN.'-'.ATSParseabilityCheckerConstants::WORD_COUNT_MAX.'). Consider adding more detail about your experience and achievements.',
            $wordCount > ATSParseabilityCheckerConstants::WORD_COUNT_MAX => "Resume is too long ({$wordCount} words, ideal: ".ATSParseabilityCheckerConstants::WORD_COUNT_MIN.'-'.ATSParseabilityCheckerConstants::WORD_COUNT_MAX.'). Consider condensing to '.ATSParseabilityCheckerConstants::PAGE_COUNT_MIN.'-'.ATSParseabilityCheckerConstants::PAGE_COUNT_MAX.' pages.',
            $pageCount > ATSParseabilityCheckerConstants::PAGE_COUNT_MAX => "Resume is too long ({$pageCount} pages, ideal: ".ATSParseabilityCheckerConstants::PAGE_COUNT_MIN.'-'.ATSParseabilityCheckerConstants::PAGE_COUNT_MAX.' pages). ATS systems and recruiters prefer concise resumes.',
            default => 'Document length is optimal',
        };

        return [
            'is_optimal' => $isOptimal,
            'word_count' => $wordCount,
            'page_count' => $pageCount,
            'message' => $isOptimal ? 'Document length is optimal' : $message,
        ];
    }

    /**
     * Check if contact info is in first 300 characters (expanded from 200).
     * Expanded check accounts for PDF headers/footers that may be extracted in different order.
     * Also checks if contact appears after line 10 but PDF shows it at top (may be in PDF header).
     */
    protected function checkContactInfoLocation(string $text): array
    {
        // Expanded check: first 300 chars (was 200) to account for PDF parsing variations
        $first300Chars = substr($text, 0, ATSParseabilityCheckerConstants::CONTACT_CHECK_CHARS);
        $fullText = $text;

        // Also check first 10 lines for header detection
        $lines = explode("\n", $text);
        $first10Lines = implode("\n", array_slice($lines, 0, ATSParseabilityCheckerConstants::CONTACT_CHECK_LINES));
        $checkArea = $first300Chars."\n".$first10Lines; // Combine both for comprehensive check

        // Check for email in first 300 chars
        $emailInFirst300 = false;
        $emailPosition = null;
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $first300Chars, $matches)) {
            $emailInFirst300 = true;
            $emailPosition = strpos($first300Chars, $matches[0]);
        }

        // Check for phone in first 300 chars
        $phoneInFirst300 = false;
        $phonePosition = null;
        $phonePatterns = [
            '/\+?1?\s*\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/', // US/CA format
            '/\+?52\s*\(?\d{2}\)?[\s.-]?\d{4}[\s.-]?\d{4}/', // MX format
        ];

        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $first300Chars, $matches)) {
                $phoneInFirst300 = true;
                $phonePosition = strpos($first300Chars, $matches[0]);
                break;
            }
        }

        // Check if contact info exists in full text
        $emailExists = (bool) preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $fullText);
        $phoneExists = false;
        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $fullText)) {
                $phoneExists = true;
                break;
            }
        }

        // Check if contact appears after line 10 but might be in PDF header
        // This helps detect cases where PDF header is extracted but appears later in text
        $emailInFirst10Lines = false;
        $phoneInFirst10Lines = false;
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $first10Lines)) {
            $emailInFirst10Lines = true;
        }
        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $first10Lines)) {
                $phoneInFirst10Lines = true;
                break;
            }
        }

        return [
            'email_in_first_300' => $emailInFirst300,
            'phone_in_first_300' => $phoneInFirst300,
            'email_in_first_10_lines' => $emailInFirst10Lines,
            'phone_in_first_10_lines' => $phoneInFirst10Lines,
            'email_position' => $emailPosition,
            'phone_position' => $phonePosition,
            'email_exists' => $emailExists,
            'phone_exists' => $phoneExists,
            'may_be_in_pdf_header' => ($emailInFirst10Lines || $phoneInFirst10Lines) && ! ($emailInFirst300 || $phoneInFirst300),
        ];
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

    /**
     * Check for dates in resume - critical for ATS systems.
     *
     * @return array{
     *     has_valid_dates: bool,
     *     has_placeholders: bool,
     *     date_count: int,
     *     placeholder_count: int,
     *     message: string
     * }
     */
    protected function checkDates(string $text): array
    {
        $validDatePatterns = [
            // YYYY-MM-DD, YYYY/MM/DD, YYYY.MM.DD
            '/\b(19|20)\d{2}[-.\/](0[1-9]|1[0-2])[-.\/](0[1-9]|[12][0-9]|3[01])\b/',
            // MM/YYYY, MM-YYYY, MM.YYYY
            '/\b(0[1-9]|1[0-2])[-.\/](19|20)\d{2}\b/',
            // Month YYYY (e.g., "Jan 2023", "January 2023")
            '/\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|January|February|March|April|May|June|July|August|September|October|November|December)\s+(19|20)\d{2}\b/i',
            // YYYY only (valid for education, but less ideal)
            '/\b(19|20)\d{2}\b/',
        ];

        $placeholderPatterns = [
            '/\b20XX\b/i',
            '/\b20xx\b/i',
            '/\b20XX-20XX\b/i',
            '/\b(19|20)XX\b/i',
            '/\b(19|20)xx\b/i',
            '/\bPresent\b/i',
            '/\bCurrent\b/i',
            // Note: "Present" and "Current" are valid, but we check if they're used with placeholders
        ];

        $dateCount = 0;
        $placeholderCount = 0;

        // Count valid dates
        foreach ($validDatePatterns as $pattern) {
            $matches = preg_match_all($pattern, $text);
            if ($matches !== false) {
                $dateCount += $matches;
            }
        }

        // Count placeholders (20XX, etc.)
        foreach ($placeholderPatterns as $pattern) {
            $matches = preg_match_all($pattern, $text);
            if ($matches !== false) {
                $placeholderCount += $matches;
            }
        }

        // Check if "Present" or "Current" are used but no actual dates found
        $hasPresent = (bool) preg_match('/\b(Present|Current)\b/i', $text);
        $hasValidDates = $dateCount >= ATSParseabilityCheckerConstants::MIN_DATE_COUNT;

        // If has placeholders like "20XX", it's a critical issue
        if ($placeholderCount > 0 && preg_match('/\b20XX\b/i', $text)) {
            return [
                'has_valid_dates' => $hasValidDates,
                'has_placeholders' => true,
                'date_count' => $dateCount,
                'placeholder_count' => $placeholderCount,
                'message' => 'Resume contains date placeholders (e.g., "20XX") instead of actual dates. ATS systems cannot parse placeholder dates - you must include real dates (e.g., "2023", "Jan 2023", "2023-2024").',
            ];
        }

        // If no valid dates found at all
        if ($dateCount < 2) {
            return [
                'has_valid_dates' => false,
                'has_placeholders' => false,
                'date_count' => $dateCount,
                'placeholder_count' => $placeholderCount,
                'message' => 'No dates found in work experience or education sections. ATS systems require dates to verify employment history and education timeline.',
            ];
        }

        return [
            'has_valid_dates' => true,
            'has_placeholders' => false,
            'date_count' => $dateCount,
            'placeholder_count' => $placeholderCount,
            'message' => 'Dates found and appear to be valid',
        ];
    }

    /**
     * Detect experience level from resume text.
     *
     * @return array{years: int, is_experienced: bool}
     */
    protected function detectExperienceLevel(string $text): array
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

    /**
     * Check if resume has a name (critical for ATS systems).
     *
     * @return array{has_name: bool, name: string|null}
     */
    protected function checkName(string $text): array
    {
        // Look for name patterns in first 200 characters (where name typically appears)
        $first200Chars = substr($text, 0, ATSParseabilityCheckerConstants::NAME_CHECK_CHARS);
        $lines = explode("\n", $first200Chars);
        $firstFewLines = array_slice(array_filter($lines), 0, ATSParseabilityCheckerConstants::NAME_CHECK_LINES);

        // Check for common name patterns:
        // 1. Capitalized words (2-3 words) at start of resume
        // 2. Patterns like "John Doe" or "Mary Jane Smith"
        foreach ($firstFewLines as $line) {
            $line = trim($line);
            // Skip empty lines or lines that are clearly not names
            if (empty($line) || strlen($line) > ATSParseabilityCheckerConstants::NAME_MAX_LINE_LENGTH) {
                continue;
            }

            // Check for name pattern: 2-4 capitalized words, possibly with middle initial
            // Pattern: "John Doe" or "Mary J. Smith" or "John-Doe" or "MARIAM KANJ" (all caps)
            // Also check for all caps names
            $isAllCaps = ctype_upper(preg_replace('/[^A-Z]/', '', $line));
            $isTitleCase = preg_match('/^[A-Z][a-z]+(?:\s+[A-Z]\.?)?\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)?$/', $line);

            if ($isAllCaps || $isTitleCase) {
                // Additional check: not a section header (common headers are usually one word)
                $lowerLine = strtolower($line);
                $commonHeaders = ['experience', 'education', 'skills', 'summary', 'profile', 'objective', 'contact', 'professional', 'technical'];
                // Check if line contains common headers
                $isHeader = false;
                foreach ($commonHeaders as $header) {
                    if (str_contains($lowerLine, $header)) {
                        $isHeader = true;
                        break;
                    }
                }

                // If it's a short line (2-4 words) and not a header, it's likely a name
                $wordCount = count(explode(' ', $line));
                if (! $isHeader && $wordCount >= ATSParseabilityCheckerConstants::NAME_MIN_WORDS && $wordCount <= ATSParseabilityCheckerConstants::NAME_MAX_WORDS) {
                    return [
                        'has_name' => true,
                        'name' => $line,
                    ];
                }
            }
        }

        // Fallback: check if there's a capitalized word pattern in first 100 chars
        $first100Chars = substr($text, 0, ATSParseabilityCheckerConstants::NAME_FALLBACK_CHARS);
        // Try title case first
        if (preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+/', $first100Chars, $matches)) {
            return [
                'has_name' => true,
                'name' => $matches[0],
            ];
        }
        // Try all caps (e.g., "MARIAM KANJ")
        if (preg_match('/^[A-Z]+\s+[A-Z]+/', $first100Chars, $matches)) {
            $name = trim($matches[0]);
            // Verify it's not a header (all caps headers are usually longer or contain common words)
            $lowerName = strtolower($name);
            $commonHeaders = ['experience', 'education', 'skills', 'summary', 'profile', 'objective', 'contact', 'professional', 'technical'];
            $isHeader = false;
            foreach ($commonHeaders as $header) {
                if (str_contains($lowerName, $header)) {
                    $isHeader = true;
                    break;
                }
            }
            if (! $isHeader && strlen($name) <= ATSParseabilityCheckerConstants::NAME_MAX_LENGTH) {
                return [
                    'has_name' => true,
                    'name' => $name,
                ];
            }
        }

        return [
            'has_name' => false,
            'name' => null,
        ];
    }

    /**
     * Check if resume has a summary/profile section.
     *
     * @return array{has_summary: bool}
     */
    protected function checkSummary(string $text): array
    {
        $normalizedText = strtolower($text);

        $summaryPatterns = [
            '/\b(summary|profile|professional\s+summary|executive\s+summary|career\s+summary|objective|career\s+objective)\b/i',
        ];

        foreach ($summaryPatterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                // Check if there's actual content after the header (not just a header)
                $matches = preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE);
                if ($matches) {
                    $position = $match[0][1];
                    // Get text after the header (next 300 chars)
                    $afterHeader = substr($text, $position, ATSParseabilityCheckerConstants::SUMMARY_CHECK_CHARS);
                    // Check if there's substantial content (at least 20 words)
                    $wordCount = $this->countWords($afterHeader);
                    if ($wordCount >= ATSParseabilityCheckerConstants::SUMMARY_MIN_WORDS) {
                        return [
                            'has_summary' => true,
                        ];
                    }
                }
            }
        }

        return [
            'has_summary' => false,
        ];
    }

    /**
     * Count bullet points in resume by section.
     *
     * This method uses a multi-pass detection algorithm to identify bullet points across
     * different sections of a resume. It handles various bullet formats including:
     * - Standard bullets: •, ◦, ▪, ▫, ◘, ◙, ◉, ○, ●
     * - Checkmarks: ✓, ✔, ☑, ✅
     * - Arrows: →, ⇒, ➜, ➤
     * - Dashes and asterisks: -, *
     * - Numbered lists: 1. 2. 3.
     * - Non-standard bullets (from PDF encoding issues)
     *
     * Detection Strategy:
     * 1. First Pass: Detects bullets on separate lines (bullet character alone, content on next line)
     * 2. Second Pass: Detects bullets inline with content (bullet + text on same line)
     * 3. Fallback: More permissive detection for indented or formatted bullets
     * 4. Numbered Lists: Detects numbered list patterns
     * 5. Implicit Detection: Detects action-verb lines and short capitalized lines that act as bullets
     *
     * Section Tracking:
     * - Tracks which section each bullet belongs to (Experience, Projects, Other)
     * - Uses regex patterns to detect section headers
     * - Maintains section context throughout all passes
     *
     * IMPORTANT: This logic was manually tested with real resumes to ensure accuracy.
     * The thresholds and detection patterns are calibrated based on actual resume formats.
     * Any changes to this method must be thoroughly tested with real resume samples.
     *
     * @return array{
     *     count: int,
     *     is_optimal: bool,
     *     by_section: array{experience: int, projects: int, other: int},
     *     sections_found: array<string>,
     *     potential_non_standard_bullets: int,
     *     non_standard_by_section: array{experience: int, projects: int, other: int}
     * }
     */
    protected function countBulletPoints(string $text): array
    {
        // Initialize tracking variables
        $count = 0;
        $lines = explode("\n", $text);
        $processedLines = [];
        $bulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $sectionsFound = [];

        // Get detection patterns and characters
        $bulletPatterns = $this->getBulletPatterns();
        $bulletChars = $this->getBulletCharacters();
        $experiencePatterns = $this->getExperienceSectionPatterns();
        $projectsPatterns = $this->getProjectsSectionPatterns();

        // First pass: detect bullets on separate lines
        $firstPassResult = $this->detectBulletsFirstPass(
            $lines,
            $bulletChars,
            $experiencePatterns,
            $projectsPatterns,
            $processedLines,
            $sectionsFound
        );
        $count += $firstPassResult['count'];
        $bulletsBySection = $this->mergeSectionCounts($bulletsBySection, $firstPassResult['by_section']);
        $processedLines = array_merge($processedLines, $firstPassResult['processed_lines']);

        // Second pass: detect bullets inline with content
        $secondPassResult = $this->detectBulletsSecondPass(
            $lines,
            $bulletPatterns,
            $experiencePatterns,
            $projectsPatterns,
            $processedLines,
            $sectionsFound
        );
        $count += $secondPassResult['count'];
        $bulletsBySection = $this->mergeSectionCounts($bulletsBySection, $secondPassResult['by_section']);
        $processedLines = array_merge($processedLines, $secondPassResult['processed_lines']);

        // Fallback pass: more permissive detection
        if ($count < ATSParseabilityCheckerConstants::BULLETS_FALLBACK_THRESHOLD) {
            $fallbackResult = $this->detectBulletsFallbackPass(
                $lines,
                $bulletChars,
                $experiencePatterns,
                $projectsPatterns,
                $processedLines,
                $sectionsFound
            );
            $count += $fallbackResult['count'];
            $bulletsBySection = $this->mergeSectionCounts($bulletsBySection, $fallbackResult['by_section']);
            $processedLines = array_merge($processedLines, $fallbackResult['processed_lines']);

            // Numbered lists detection
            if ($count < ATSParseabilityCheckerConstants::BULLETS_FALLBACK_THRESHOLD) {
                $numberedResult = $this->detectNumberedLists(
                    $lines,
                    $experiencePatterns,
                    $projectsPatterns,
                    $processedLines
                );
                $count += $numberedResult['count'];
                $bulletsBySection = $this->mergeSectionCounts($bulletsBySection, $numberedResult['by_section']);
                $processedLines = array_merge($processedLines, $numberedResult['processed_lines']);
            }

            // Implicit bullet detection
            if ($count < ATSParseabilityCheckerConstants::BULLETS_FALLBACK_THRESHOLD) {
                $implicitResult = $this->detectImplicitBullets($lines, $processedLines);
                $count += $implicitResult['count'];
            }
        }

        // Get final section counts
        $experienceBullets = $bulletsBySection['experience'];
        $projectsBullets = $bulletsBySection['projects'];
        $otherBullets = $bulletsBySection['other'];

        // Final implicit detection for experience section
        if (in_array('experience', $sectionsFound, true) && $experienceBullets < ATSParseabilityCheckerConstants::BULLETS_EXPERIENCE_IMPLICIT_THRESHOLD) {
            $finalImplicitResult = $this->detectImplicitExperienceBullets(
                $lines,
                $experiencePatterns,
                $projectsPatterns,
                $processedLines,
                $bulletsBySection
            );
            $count += $finalImplicitResult['count'];
            $bulletsBySection = $this->mergeSectionCounts($bulletsBySection, $finalImplicitResult['by_section']);
            $processedLines = array_merge($processedLines, $finalImplicitResult['processed_lines']);

            // Update final counts
            $experienceBullets = $bulletsBySection['experience'];
            $projectsBullets = $bulletsBySection['projects'];
            $otherBullets = $bulletsBySection['other'];
        }

        // Detect non-standard bullets
        $nonStandardResult = $this->detectNonStandardBullets(
            $lines,
            $experiencePatterns,
            $projectsPatterns,
            $processedLines
        );

        // Calculate optimal status
        $isOptimal = $count >= ATSParseabilityCheckerConstants::BULLETS_MIN_OPTIMAL && $experienceBullets >= ATSParseabilityCheckerConstants::BULLETS_EXPERIENCE_MIN;

        return [
            'count' => $count,
            'is_optimal' => $isOptimal,
            'by_section' => [
                'experience' => $experienceBullets,
                'projects' => $projectsBullets,
                'other' => $otherBullets,
            ],
            'sections_found' => array_values(array_unique($sectionsFound)),
            'potential_non_standard_bullets' => $nonStandardResult['count'],
            'non_standard_by_section' => $nonStandardResult['by_section'],
        ];
    }

    /**
     * Get bullet detection regex patterns.
     *
     * @return array<string>
     */
    protected function getBulletPatterns(): array
    {
        return [
            // Standard bullet characters (various Unicode bullets) - with or without space after
            '/^\s*[•◦▪▫◘◙◉○●]\s*/m', // Bullet characters at start of line (space optional)
            '/^\s*[•◦▪▫◘◙◉○●]/m', // Bullet characters at start of line (no space required)
            // Numbers as bullets: "1. ", "2) ", "3- ", etc.
            '/^\s*\d+[.)-]\s+/m', // Number followed by period, parenthesis, or dash
            // Checkmarks and check icons: ✓, ✔, ☑, ✅
            '/^\s*[✓✔☑✅]\s*/u', // Checkmarks at start of line (space optional)
            '/^\s*[✓✔☑✅]/u', // Checkmarks at start of line (no space required)
            // Dash or asterisk at start of line
            '/^\s*[-*]\s+/m',
            // Lowercase 'o' as bullet at start of line
            '/^\s*o\s+/m',
            // Arrows: →, →, ⇒, ➜, ➤
            '/^\s*[→⇒➜➤]\s*/u', // Arrows at start of line (space optional)
            '/^\s*[→⇒➜➤]/u', // Arrows at start of line (no space required)
            // Square brackets: [ ], □, ■
            '/^\s*[□■]\s*/u', // Square bullets (space optional)
            '/^\s*[□■]/u', // Square bullets (no space required)
            // Other common bullet alternatives
            '/^\s*[▪▫]\s*/u', // Square bullets variants (space optional)
            '/^\s*[▪▫]/u', // Square bullets variants (no space required)
        ];
    }

    /**
     * Get all bullet characters including non-standard ones from PDF encoding.
     *
     * @return array<string>
     */
    protected function getBulletCharacters(): array
    {
        $bulletChars = ['•', '◦', '▪', '▫', '◘', '◙', '◉', '○', '●', '✓', '✔', '☑', '✅', '→', '⇒', '➜', '➤', '□', '■', '-', '*'];

        // Add non-standard bullet (from hex ef82b7 - common in PDFs due to encoding issues)
        $nonStandardBullet = hex2bin('ef82b7') ?: '';
        if (! empty($nonStandardBullet) && ! in_array($nonStandardBullet, $bulletChars, true)) {
            $bulletChars[] = $nonStandardBullet;
        }

        return $bulletChars;
    }

    /**
     * Get experience section detection patterns.
     *
     * @return array<string>
     */
    protected function getExperienceSectionPatterns(): array
    {
        return ['/^(professional\s+)?experience|work\s+experience|work\s+history|employment|career\s+history/i'];
    }

    /**
     * Get projects section detection patterns.
     *
     * @return array<string>
     */
    protected function getProjectsSectionPatterns(): array
    {
        return [
            '/^projects?/i',  // PROJECTS or PROJECT
            '/^portfolio/i',  // Portfolio
            '/^personal\s+projects/i',  // Personal Projects
        ];
    }

    /**
     * Update section tracking based on line content.
     *
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $sectionsFound
     * @return array{section: string, sections_found: array<string>}
     */
    protected function updateSectionTracking(
        string $line,
        array $experiencePatterns,
        array $projectsPatterns,
        string $currentSection,
        array $sectionsFound
    ): array {
            // Check Experience first
            $isExperienceSection = false;
            foreach ($experiencePatterns as $pattern) {
            if (preg_match($pattern, $line)) {
                    $currentSection = 'experience';
                    $isExperienceSection = true;
                    if (! in_array('experience', $sectionsFound, true)) {
                        $sectionsFound[] = 'experience';
                    }
                    break;
                }
            }

            // Check Projects (only if not Experience)
            if (! $isExperienceSection) {
                foreach ($projectsPatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                        $currentSection = 'projects';
                        if (! in_array('projects', $sectionsFound, true)) {
                            $sectionsFound[] = 'projects';
                        }
                        break;
                    }
                }
            }

        return [
            'section' => $currentSection,
            'sections_found' => $sectionsFound,
        ];
    }

    /**
     * Check if line is only a bullet character (with various detection methods).
     *
     * @param  array<string>  $bulletChars
     * @param  array<string>  $lines
     */
    protected function isLineOnlyBullet(string $line, int $lineLength, array $bulletChars, array $lines, int $index): bool
    {
        if ($lineLength > ATSParseabilityCheckerConstants::BULLET_LINE_MAX_LENGTH) {
            return false;
        }

                // Method 1: Direct character comparison
                foreach ($bulletChars as $char) {
            if ($line === $char) {
                return true;
                    }
                }

                // Method 2: Regex pattern for bullet with optional whitespace
                    foreach ($bulletChars as $char) {
                        $pattern = '/^[\s]*'.preg_quote($char, '/').'[\s]*$/u';
            if (preg_match($pattern, $line)) {
                return true;
                    }
                }

                // Method 3: Check if line contains any bullet character (fallback)
        if ($lineLength <= ATSParseabilityCheckerConstants::BULLET_LINE_MAX_LENGTH) {
                    foreach ($bulletChars as $char) {
                if (mb_strpos($line, $char) !== false) {
                    return true;
                        }
                    }
                }

                // Method 4: Pattern-based detection - if line is very short (1-3 chars) and next line has content,
                // it's likely a bullet on a separate line (common resume formatting pattern)
        if ($lineLength <= ATSParseabilityCheckerConstants::BULLET_LINE_MAX_LENGTH) {
                    $nextLineIndex = $index + 1;
                    $lookAheadLines = ATSParseabilityCheckerConstants::BULLET_LOOKAHEAD_LINES;

                    // Check if any of the next few lines have substantial content
                    for ($checkIndex = $nextLineIndex; $checkIndex <= $index + $lookAheadLines && $checkIndex < count($lines); $checkIndex++) {
                        if (isset($lines[$checkIndex])) {
                            $checkLine = trim($lines[$checkIndex]);
                            // If line has substantial content (10+ chars), treat current line as bullet
                            if (! empty($checkLine) && mb_strlen($checkLine) >= ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                                // Additional check: current line should not be a header or date
                        if (! $this->isLineHeaderOrDate($line)) {
                            return true;
                                }
                            }
                        }
                    }
                }

        return false;
    }

    /**
     * Check if line is a header or date.
     */
    protected function isLineHeaderOrDate(string $line): bool
    {
        return preg_match('/^(PROFESSIONAL|EXPERIENCE|EDUCATION|PROJECTS|SKILLS|SUMMARY|LANGUAGES|CERTIFICATIONS|LEADERSHIP|WORK\s+HISTORY)/i', $line) ||
               preg_match('/\d{4}/', $line) ||
               preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i', $line);
    }

    /**
     * Find next content line after bullet-only line.
     *
     * @param  array<string>  $lines
     * @param  array<string>  $processedLines
     * @return array{found: bool, line: string|null, index: int|null}
     */
    protected function findNextContentLine(array $lines, int $startIndex, array $processedLines): array
    {
        $nextLineIndex = $startIndex + 1;
                $foundContent = false;

                while (isset($lines[$nextLineIndex]) && ! $foundContent) {
                    $nextLine = trim($lines[$nextLineIndex]);

                    // If next line has content, count it as a bullet point
                    if (! empty($nextLine) && mb_strlen($nextLine) >= ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                        // Skip if next line is already processed
                        if (! in_array($nextLine, $processedLines, true)) {
                    return [
                        'found' => true,
                        'line' => $nextLine,
                        'index' => $nextLineIndex,
                    ];
                        }
                    }
                    $nextLineIndex++;
        }

        return [
            'found' => false,
            'line' => null,
            'index' => null,
        ];
    }

    /**
     * First pass: detect bullets on separate lines (bullet character alone, content on next line).
     *
     * @param  array<string>  $lines
     * @param  array<string>  $bulletChars
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $processedLines
     * @param  array<string>  $sectionsFound
     * @return array{count: int, by_section: array{experience: int, projects: int, other: int}, processed_lines: array<string>, sections_found: array<string>}
     */
    protected function detectBulletsFirstPass(
        array $lines,
        array $bulletChars,
        array $experiencePatterns,
        array $projectsPatterns,
        array $processedLines,
        array $sectionsFound
    ): array {
        $count = 0;
        $bulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $currentSection = 'other';

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);

            // Skip empty lines
            if (empty($trimmedLine)) {
                continue;
            }

            // Update section tracking
            $sectionResult = $this->updateSectionTracking(
                $trimmedLine,
                $experiencePatterns,
                $projectsPatterns,
                $currentSection,
                $sectionsFound
            );
            $currentSection = $sectionResult['section'];
            $sectionsFound = $sectionResult['sections_found'];

            // Check if line is ONLY a bullet character
            $lineLength = mb_strlen($trimmedLine);
            $isOnlyBullet = $this->isLineOnlyBullet($trimmedLine, $lineLength, $bulletChars, $lines, $index);

            // If line is ONLY a bullet character, check next lines for content
            if ($isOnlyBullet) {
                $contentResult = $this->findNextContentLine($lines, $index, $processedLines);

                if ($contentResult['found'] && $contentResult['line'] !== null) {
                    $count++;
                    $processedLines[] = $contentResult['line'];
                    $bulletsBySection[$currentSection]++;
                }
            }
        }

        return [
            'count' => $count,
            'by_section' => $bulletsBySection,
            'processed_lines' => $processedLines,
            'sections_found' => $sectionsFound,
        ];
    }

    /**
     * Second pass: detect bullets inline with content (bullet + text on same line).
     *
     * @param  array<string>  $lines
     * @param  array<string>  $bulletPatterns
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $processedLines
     * @param  array<string>  $sectionsFound
     * @return array{count: int, by_section: array{experience: int, projects: int, other: int}, processed_lines: array<string>, sections_found: array<string>}
     */
    protected function detectBulletsSecondPass(
        array $lines,
        array $bulletPatterns,
        array $experiencePatterns,
        array $projectsPatterns,
        array $processedLines,
        array $sectionsFound
    ): array {
        $count = 0;
        $bulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $currentSection = 'other';

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) {
                continue;
            }

            // Update section tracking
            $sectionResult = $this->updateSectionTracking(
                $trimmedLine,
                $experiencePatterns,
                $projectsPatterns,
                $currentSection,
                $sectionsFound
            );
            $currentSection = $sectionResult['section'];
            $sectionsFound = $sectionResult['sections_found'];

            // Skip if already processed
            if (in_array($trimmedLine, $processedLines, true)) {
                continue;
            }

            // Check if current line starts with a bullet character or pattern
            $isBulletLine = false;
            foreach ($bulletPatterns as $pattern) {
                if (preg_match($pattern, $trimmedLine)) {
                    $isBulletLine = true;
                    break;
                }
            }

            // If current line is a bullet WITH content (bullet + text), count it
            if ($isBulletLine) {
                // If bullet line itself has content (after bullet), count it
                if (strlen($trimmedLine) >= ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                    $count++;
                    $processedLines[] = $trimmedLine;
                    $bulletsBySection[$currentSection]++;

                    continue;
                }
            }

            // Skip very short lines (likely headers or dates) - but only if not a bullet
            if (strlen($trimmedLine) < ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH && ! $isBulletLine) {
                continue;
            }

            // Check if line starts with a bullet character or pattern (standard check)
            foreach ($bulletPatterns as $pattern) {
                if (preg_match($pattern, $trimmedLine)) {
                    $count++;
                    $processedLines[] = $trimmedLine;
                    $bulletsBySection[$currentSection]++;
                    break; // Count each line only once
                }
            }
        }

        return [
            'count' => $count,
            'by_section' => $bulletsBySection,
            'processed_lines' => $processedLines,
            'sections_found' => $sectionsFound,
        ];
    }

    /**
     * Fallback pass: more permissive detection for indented or formatted bullets.
     *
     * @param  array<string>  $lines
     * @param  array<string>  $bulletChars
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $processedLines
     * @param  array<string>  $sectionsFound
     * @return array{count: int, by_section: array{experience: int, projects: int, other: int}, processed_lines: array<string>, sections_found: array<string>}
     */
    protected function detectBulletsFallbackPass(
        array $lines,
        array $bulletChars,
        array $experiencePatterns,
        array $projectsPatterns,
        array $processedLines,
        array $sectionsFound
    ): array {
        $count = 0;
        $bulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $currentSection = 'other';

        // Remove non-standard bullet from list for this pass (use standard set)
            $bulletChars = ['•', '◦', '▪', '▫', '◘', '◙', '◉', '○', '●', '✓', '✔', '☑', '✅', '→', '⇒', '➜', '➤', '□', '■'];

            foreach ($lines as $index => $line) {
                $trimmedLine = trim($line);
                if (empty($trimmedLine)) {
                    continue;
                }

            // Update section tracking
            $sectionResult = $this->updateSectionTracking(
                $trimmedLine,
                $experiencePatterns,
                $projectsPatterns,
                $currentSection,
                $sectionsFound
            );
            $currentSection = $sectionResult['section'];
            $sectionsFound = $sectionResult['sections_found'];

                // Skip if already processed
            if (in_array($trimmedLine, $processedLines, true)) {
                    continue;
                }

                // Check if line contains a bullet character
                foreach ($bulletChars as $char) {
                if (str_contains($trimmedLine, $char)) {
                        // Additional check: make sure it's not in the middle of a word
                    $charPos = strpos($trimmedLine, $char);
                        // If bullet is in first 5 characters, it's likely a bullet point
                        if ($charPos !== false && $charPos < ATSParseabilityCheckerConstants::BULLET_MAX_POSITION) {
                        // If line is just a bullet (short), check next lines for content
                        if (strlen($trimmedLine) < ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                                $nextLineIndex = $index + 1;
                                $foundContent = false;
                                while (isset($lines[$nextLineIndex]) && ! $foundContent) {
                                    $nextLine = trim($lines[$nextLineIndex]);
                                    if (! empty($nextLine) && strlen($nextLine) >= ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                                        $count++;
                                        $processedLines[] = $nextLine;
                                        $bulletsBySection[$currentSection]++;
                                        $foundContent = true;
                                        break 2; // Break both loops
                                    }
                                    $nextLineIndex++;
                                    // Limit search to next 3 lines to avoid going too far
                                    if ($nextLineIndex > $index + ATSParseabilityCheckerConstants::BULLET_LOOKAHEAD_LINES) {
                                        break;
                                    }
                                }
                            } else {
                                // Line has bullet and content
                                $count++;
                            $processedLines[] = $trimmedLine;
                                $bulletsBySection[$currentSection]++;
                                break; // Count each line only once
                            }
                        }
                    }
                }
            }

        return [
            'count' => $count,
            'by_section' => $bulletsBySection,
            'processed_lines' => $processedLines,
            'sections_found' => $sectionsFound,
        ];
    }

    /**
     * Detect numbered lists (1. 2. 3. pattern).
     *
     * @param  array<string>  $lines
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $processedLines
     * @return array{count: int, by_section: array{experience: int, projects: int, other: int}, processed_lines: array<string>}
     */
    protected function detectNumberedLists(
        array $lines,
        array $experiencePatterns,
        array $projectsPatterns,
        array $processedLines
    ): array {
        $count = 0;
        $bulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $currentSection = 'other';

                foreach ($lines as $index => $line) {
                    $trimmedLine = trim($line);
                    if (empty($trimmedLine)) {
                        continue;
                    }

            // Update section tracking
            $sectionResult = $this->updateSectionTracking(
                $trimmedLine,
                $experiencePatterns,
                $projectsPatterns,
                $currentSection,
                []
            );
            $currentSection = $sectionResult['section'];

            if (strlen($trimmedLine) < ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                        continue;
                    }

                    // Skip if already processed
            if (in_array($trimmedLine, $processedLines, true)) {
                        continue;
                    }

                    // Check for numbered list pattern: starts with number followed by period/parenthesis/dash
            if (preg_match('/^\d+[.)-]\s+/', $trimmedLine)) {
                        $count++;
                $processedLines[] = $trimmedLine;
                        $bulletsBySection[$currentSection]++;
                    }
                }

        return [
            'count' => $count,
            'by_section' => $bulletsBySection,
            'processed_lines' => $processedLines,
        ];
    }

    /**
     * Detect implicit bullets (short capitalized lines and action verb lines).
     *
     * @param  array<string>  $lines
     * @param  array<string>  $processedLines
     * @return array{count: int}
     */
    protected function detectImplicitBullets(array $lines, array $processedLines): array
    {
            $implicitBulletCount = 0;
            $consecutiveShortLines = 0;
            $actionVerbLines = 0;
        $actionVerbs = $this->getActionVerbsList();

            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                // Skip if already processed
                if (in_array($line, $processedLines, true)) {
                    continue;
                }

                $lineLength = strlen($line);
                $words = explode(' ', $line);
                $wordCount = count($words);

                // Pattern 1: Short lines that look like list items (skills, etc.)
            if ($lineLength >= ATSParseabilityCheckerConstants::BULLET_SHORT_ITEM_MIN_LENGTH &&
                $lineLength <= ATSParseabilityCheckerConstants::BULLET_SHORT_ITEM_MAX_LENGTH &&
                $wordCount >= ATSParseabilityCheckerConstants::BULLET_SHORT_ITEM_MIN_WORDS &&
                $wordCount <= ATSParseabilityCheckerConstants::BULLET_SHORT_ITEM_MAX_WORDS) {
                    // Check if line is mostly title case or capitalized
                    $titleCaseWords = 0;
                    foreach ($words as $word) {
                        $cleanWord = preg_replace('/[^a-zA-Z]/', '', $word);
                        if (! empty($cleanWord) && (ucfirst(strtolower($cleanWord)) === $cleanWord || ctype_upper($cleanWord))) {
                            $titleCaseWords++;
                        }
                    }
                    // If 50%+ of words are title case, likely a list item
                    if ($titleCaseWords >= ($wordCount * ATSParseabilityCheckerConstants::BULLET_SHORT_ITEM_TITLE_CASE_RATIO)) {
                        $implicitBulletCount++;
                        $consecutiveShortLines++;

                        continue;
                    }
                }

                // Pattern 2: Action verb lines (likely experience bullets)
                $firstWord = strtolower(explode(' ', $line)[0]);
                if (in_array($firstWord, $actionVerbs, true) && $lineLength >= ATSParseabilityCheckerConstants::BULLET_IMPLICIT_MIN_LENGTH) {
                    $actionVerbLines++;

                    continue;
                }
            }

            // If we found many implicit bullets, add them to count
            // But be conservative - only count if we're confident they're list items
        $additionalCount = 0;
            if ($implicitBulletCount >= ATSParseabilityCheckerConstants::BULLETS_IMPLICIT_MIN || $actionVerbLines >= ATSParseabilityCheckerConstants::BULLETS_IMPLICIT_MIN) {
                // Count implicit bullets but be conservative
                $additionalCount = min($implicitBulletCount, $actionVerbLines > 0 ? max($actionVerbLines, $implicitBulletCount) : $implicitBulletCount);
                // Only add if we're confident (at least 3 items)
                if ($additionalCount >= ATSParseabilityCheckerConstants::BULLETS_IMPLICIT_MIN) {
                // Don't add to count here - this is just for detection, not counting
                // The actual counting happens in the main method
            }
        }

        return [
            'count' => $additionalCount,
        ];
    }

    /**
     * Detect implicit bullets in experience section (action verb lines).
     *
     * @param  array<string>  $lines
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $processedLines
     * @param  array{experience: int, projects: int, other: int}  $bulletsBySection
     * @return array{count: int, by_section: array{experience: int, projects: int, other: int}, processed_lines: array<string>}
     */
    protected function detectImplicitExperienceBullets(
        array $lines,
        array $experiencePatterns,
        array $projectsPatterns,
        array $processedLines,
        array $bulletsBySection
    ): array {
        $implicitBulletCount = 0;
            $currentSection = 'other';
        $actionVerbs = $this->getExtendedActionVerbsList();

            foreach ($lines as $index => $line) {
                $trimmedLine = trim($line);
                if (empty($trimmedLine)) {
                    continue;
                }

            // Update section tracking
                $isExperienceHeader = false;
                foreach ($experiencePatterns as $pattern) {
                    if (preg_match($pattern, $trimmedLine)) {
                        $currentSection = 'experience';
                        $isExperienceHeader = true;
                        break;
                    }
                }

                if (! $isExperienceHeader) {
                    foreach ($projectsPatterns as $pattern) {
                        if (preg_match($pattern, $trimmedLine)) {
                            $currentSection = 'projects';
                            break;
                        }
                    }
                }

                // Skip section headers, dates, and very short/long lines
                $lineLength = mb_strlen($trimmedLine);
                if ($lineLength < ATSParseabilityCheckerConstants::BULLET_IMPLICIT_MIN_LENGTH || $lineLength > ATSParseabilityCheckerConstants::BULLET_IMPLICIT_MAX_LENGTH) {
                    continue;
                }

                // Skip if already processed as a bullet
                if (in_array($trimmedLine, $processedLines, true)) {
                    continue;
                }

                // Skip if it's a header, date, or company name
            if ($this->isLineHeaderDateOrCompany($trimmedLine)) {
                    continue;
                }

                // Check if line starts with an action verb (likely a bullet point)
                $firstWord = strtolower(explode(' ', $trimmedLine)[0]);
                $firstWord = preg_replace('/[^a-z]/', '', $firstWord); // Remove punctuation

                // Also check for third person singular forms (Prepares -> prepare, Executes -> execute, Processes -> process)
                $baseWord = rtrim($firstWord, 's');
                $baseWordEs = rtrim($firstWord, 'es'); // For processes -> process

                if (in_array($firstWord, $actionVerbs, true) ||
                    in_array($baseWord, $actionVerbs, true) ||
                    in_array($baseWordEs, $actionVerbs, true)) {
                    // Additional check: line should not be a job title or company name
                    $isJobTitle = preg_match('/^(Senior|Junior|Lead|Manager|Developer|Engineer|Analyst|Specialist|Coordinator|Director|VP|President|CEO|CTO|Full Stack|Software|Web|Accountant)\s+/i', $trimmedLine) &&
                                 $lineLength < ATSParseabilityCheckerConstants::JOB_TITLE_MAX_LENGTH;

                    if (! $isJobTitle && $currentSection === 'experience') {
                        $implicitBulletCount++;

                        // Also add to processed lines to avoid double counting
                        if (! in_array($trimmedLine, $processedLines, true)) {
                            $processedLines[] = $trimmedLine;
                            $bulletsBySection[$currentSection]++;
                        }
                    }
                }
            }

        return [
            'count' => $implicitBulletCount,
            'by_section' => $bulletsBySection,
            'processed_lines' => $processedLines,
        ];
    }

    /**
     * Detect non-standard bullets that weren't detected by other methods.
     *
     * @param  array<string>  $lines
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $processedLines
     * @return array{count: int, by_section: array{experience: int, projects: int, other: int}}
     */
    protected function detectNonStandardBullets(
        array $lines,
        array $experiencePatterns,
        array $projectsPatterns,
        array $processedLines
    ): array {
        $potentialNonStandardBullets = 0;
        $nonStandardBulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $currentSection = 'other';

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) {
                continue;
            }

            // Update section tracking
            $isExperienceSection = false;
            foreach ($experiencePatterns as $pattern) {
                if (preg_match($pattern, $trimmedLine)) {
                    $currentSection = 'experience';
                    $isExperienceSection = true;
                    break;
                }
            }

            if (! $isExperienceSection) {
                foreach ($projectsPatterns as $pattern) {
                    if (preg_match($pattern, $trimmedLine)) {
                        $currentSection = 'projects';
                        break;
                    }
                }
            }

            // Check if this line might be a non-standard bullet (short line followed by content)
            $lineLength = mb_strlen($trimmedLine);
            if ($lineLength >= 1 && $lineLength <= ATSParseabilityCheckerConstants::BULLET_LINE_MAX_LENGTH) {
                // Skip if this line was already processed as a bullet
                $wasProcessed = false;

                // Check if the line itself was processed
                if (in_array($trimmedLine, $processedLines, true)) {
                    $wasProcessed = true;
                }

                // Also check if the next line (content) was already processed
                if (! $wasProcessed) {
                    $nextLineIndex = $index + 1;
                    $lookAheadCheck = ATSParseabilityCheckerConstants::BULLET_LOOKAHEAD_LINES;
                    for ($checkIdx = $nextLineIndex; $checkIdx <= $index + $lookAheadCheck && $checkIdx < count($lines); $checkIdx++) {
                        if (isset($lines[$checkIdx])) {
                            $nextCheckLine = trim($lines[$checkIdx]);
                            if (! empty($nextCheckLine) && mb_strlen($nextCheckLine) >= ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                                if (in_array($nextCheckLine, $processedLines, true)) {
                                    $wasProcessed = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                // Only count as non-standard if it wasn't processed AND next line has content
                if (! $wasProcessed) {
                    $nextLineIndex = $index + 1;
                    $lookAheadLines = ATSParseabilityCheckerConstants::BULLET_LOOKAHEAD_LINES;

                    for ($checkIndex = $nextLineIndex; $checkIndex <= $index + $lookAheadLines && $checkIndex < count($lines); $checkIndex++) {
                        if (isset($lines[$checkIndex])) {
                            $checkLine = trim($lines[$checkIndex]);
                            if (! empty($checkLine) && mb_strlen($checkLine) >= ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                                // Check if it's not a header or date
                                if (! $this->isLineHeaderOrDate($trimmedLine)) {
                                    $potentialNonStandardBullets++;
                                    $nonStandardBulletsBySection[$currentSection]++;
                                    break 2; // Break both loops
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'count' => $potentialNonStandardBullets,
            'by_section' => $nonStandardBulletsBySection,
        ];
    }

    /**
     * Check if line is a header, date, or company name.
     */
    protected function isLineHeaderDateOrCompany(string $line): bool
    {
        return preg_match('/^(PROFESSIONAL|EXPERIENCE|EDUCATION|PROJECTS|SKILLS|SUMMARY|LANGUAGES|CERTIFICATIONS|LEADERSHIP|WORK\s+HISTORY|Highlights|Lead|Senior|Staff|Accountant|Branch|Cashier)\s+(Accountant|Developer|Engineer|Manager|Analyst|Specialist|Coordinator|Director|VP|President|CEO|CTO|Service|Specialist)/i', $line) ||
               preg_match('/\d{4}\s+to\s+(Current|Present|\d{4})/i', $line) ||
               preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\.?\s+\d{4}/i', $line) ||
               preg_match('/^(November|September|March|January|February|April|May|June|July|August|October|December)\s+\d{4}/i', $line) ||
               preg_match('/^\d{2}\/\d{4}/i', $line) || // 04/2020 format
               preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+\s+\|/i', $line) || // Company Name | Location pattern
               preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+\s+\d{4}/i', $line) || // Company Name 2024 pattern
               preg_match('/^Company\s+Name/i', $line); // Company Name placeholder
    }

    /**
     * Get basic action verbs list.
     *
     * @return array<string>
     */
    protected function getActionVerbsList(): array
    {
        return [
            'managed', 'developed', 'led', 'created', 'built', 'implemented', 'designed', 'improved',
            'launched', 'optimized', 'delivered', 'achieved', 'increased', 'reduced', 'established',
            'coordinated', 'executed', 'transformed', 'enhanced', 'streamlined', 'automated',
            'architected', 'deployed', 'integrated', 'migrated', 'scaled', 'maintained', 'collaborated',
            'mentored', 'trained', 'supervised', 'analyzed', 'researched', 'evaluated',
            'performed', 'prepared', 'monitored', 'reviewed', 'provided', 'compiled',
            'filed', 'reconciled', 'posted', 'verified', 'acted', 'tracked', 'identified', 'stayed',
        ];
    }

    /**
     * Get extended action verbs list (for implicit detection).
     *
     * @return array<string>
     */
    protected function getExtendedActionVerbsList(): array
    {
        return [
            'managed', 'develop', 'led', 'created', 'built', 'implemented', 'designed', 'improved',
            'launched', 'optimized', 'delivered', 'achieved', 'increased', 'reduced', 'established',
            'coordinated', 'execute', 'transformed', 'enhanced', 'streamlined', 'automated',
            'architected', 'deployed', 'integrated', 'migrated', 'scaled', 'maintained', 'collaborated',
            'mentored', 'trained', 'supervised', 'analyzed', 'researched', 'evaluated',
            'performed', 'prepare', 'monitored', 'reviewed', 'provided', 'compiled',
            'filed', 'reconciled', 'posted', 'verified', 'acted', 'tracked', 'identified', 'stayed',
            'tested', 'maintained', 'monitored', 'prepared', 'compiled', 'executed', 'managed', 'reviewed',
            'strengthened', 'overlooked', 'assessed', 'ensured', 'process', 'organized', 'completed',
            'handled', 'assisted', 'supported', 'improved', 'optimized', 'streamlined', 'enhanced', 'expanded',
            'initiated', 'facilitated', 'generated', 'produced', 'administered', 'coordinated', 'directed', 'guided',
            'influenced', 'negotiated', 'persuaded', 'presented', 'promoted', 'recommended', 'resolved', 'secured',
            'solved', 'standardized', 'structured', 'synthesized', 'systematized', 'validated', 'verified', 'wrote',
            'authored', 'composed', 'constructed', 'cultivated', 'demonstrated', 'documented', 'educated', 'established',
            'evaluated', 'examined', 'explored', 'formulated', 'fostered', 'generated', 'implemented', 'improved',
            'innovated', 'inspired', 'instructed', 'introduced', 'investigated', 'leveraged', 'maximized', 'minimized',
            'modernized', 'motivated', 'navigated', 'negotiated', 'orchestrated', 'organized', 'overhauled', 'pioneered',
            'planned', 'positioned', 'prioritized', 'produced', 'programmed', 'projected', 'promoted', 'proposed',
            'qualified', 'quantified', 'rationalized', 'realized', 'rebuilt', 'recommended', 'reconciled', 'recruited',
            'redesigned', 'reduced', 'refined', 'regulated', 'reinforced', 'reorganized', 'repaired', 'replaced',
            'reported', 'represented', 'researched', 'resolved', 'restored', 'restructured', 'retained', 'revamped',
            'reviewed', 'revised', 'saved', 'scheduled', 'secured', 'selected', 'separated', 'served', 'simplified',
            'solved', 'sorted', 'spearheaded', 'specialized', 'specified', 'standardized', 'started', 'streamlined',
            'strengthened', 'structured', 'studied', 'submitted', 'substituted', 'succeeded', 'suggested', 'summarized',
            'supervised', 'supplied', 'supported', 'sustained', 'synthesized', 'systematized', 'targeted', 'taught',
            'teamed', 'tested', 'trained', 'transferred', 'transformed', 'translated', 'troubleshot', 'turned',
            'unified', 'united', 'updated', 'upgraded', 'utilized', 'validated', 'valued', 'verified', 'volunteered',
            'won', 'wrote',
        ];
    }

    /**
     * Merge section counts from two arrays.
     *
     * @param  array{experience: int, projects: int, other: int}  $base
     * @param  array{experience: int, projects: int, other: int}  $additional
     * @return array{experience: int, projects: int, other: int}
     */
    protected function mergeSectionCounts(array $base, array $additional): array
    {
        return [
            'experience' => $base['experience'] + $additional['experience'],
            'projects' => $base['projects'] + $additional['projects'],
            'other' => $base['other'] + $additional['other'],
        ];
    }

    /**
     * Check for quantifiable metrics (numbers, percentages, etc.).
     *
     * @return array{has_metrics: bool, metric_count: int}
     */
    protected function checkQuantifiableMetrics(string $text): array
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
