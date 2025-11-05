<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

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
        $score = 90; // Start lower (90 instead of 100) for stricter ResumeWorded alignment
        $criticalIssues = [];
        $warnings = [];
        $details = [];

        Log::debug('ATSParseabilityChecker: Starting checks', [
            'text_length' => strlen($parsedText),
            'first_500_chars' => substr($parsedText, 0, 500),
        ]);

        // Check 1: Text Extractability (scanned image detection)
        $textExtractability = $this->checkTextExtractability($filePath, $parsedText, $mimeType);
        $details['text_extractability'] = $textExtractability;
        Log::debug('ATSParseabilityChecker: Text extractability check', [
            'is_scanned_image' => $textExtractability['is_scanned_image'],
            'extracted_text_length' => $textExtractability['extracted_text_length'] ?? 0,
            'page_count' => $textExtractability['page_count'] ?? 0,
        ]);
        if ($textExtractability['is_scanned_image']) {
            $score -= 30;
            $criticalIssues[] = $textExtractability['message'];
        }

        // Check 2: Table Detection
        $tableDetection = $this->detectTables($parsedText);
        $details['table_detection'] = $tableDetection;
        Log::debug('ATSParseabilityChecker: Table detection', [
            'has_tables' => $tableDetection['has_tables'],
            'table_locations' => $tableDetection['table_locations'] ?? [],
        ]);
        if ($tableDetection['has_tables']) {
            $score -= 30; // Increased from 20 - stricter penalty aligned with ResumeWorded
            $warnings[] = $tableDetection['message'];
        }

        // Check 3: Multi-Column Layout Detection
        $multiColumn = $this->detectMultiColumnLayout($parsedText);
        $details['multi_column'] = $multiColumn;
        Log::debug('ATSParseabilityChecker: Multi-column detection', [
            'has_multi_column' => $multiColumn['has_multi_column'],
            'confidence_level' => $multiColumn['confidence_level'] ?? 'unknown',
        ]);
        if ($multiColumn['has_multi_column']) {
            $score -= 25; // Increased from 15 - stricter penalty aligned with ResumeWorded
            $warnings[] = $multiColumn['message'];
        }

        // Check 4: Document Length Verification
        $lengthCheck = $this->verifyDocumentLength($filePath, $parsedText, $mimeType);
        $details['document_length'] = $lengthCheck;
        Log::debug('ATSParseabilityChecker: Document length check', [
            'word_count' => $lengthCheck['word_count'] ?? 0,
            'page_count' => $lengthCheck['page_count'] ?? 0,
            'is_optimal' => $lengthCheck['is_optimal'] ?? false,
        ]);
        if (! $lengthCheck['is_optimal']) {
            // Increased penalty for length issues (stricter for ResumeWorded alignment)
            $wordCount = $lengthCheck['word_count'] ?? 0;
            if ($wordCount < 400) {
                $score -= 15; // Increased from 10 for short resumes
            } elseif ($wordCount > 800) {
                $score -= 12; // Increased from 10 for long resumes
            } else {
                $score -= 10; // For page count issues
            }
            $warnings[] = $lengthCheck['message'];
        }

        // Check 5: Contact Info Location (expanded to 300 chars)
        $contactLocation = $this->checkContactInfoLocation($parsedText);
        $details['contact_location'] = $contactLocation;
        Log::debug('ATSParseabilityChecker: Contact location check', [
            'email_in_first_300' => $contactLocation['email_in_first_300'] ?? false,
            'phone_in_first_300' => $contactLocation['phone_in_first_300'] ?? false,
            'email_in_first_10_lines' => $contactLocation['email_in_first_10_lines'] ?? false,
            'phone_in_first_10_lines' => $contactLocation['phone_in_first_10_lines'] ?? false,
            'email_exists' => $contactLocation['email_exists'] ?? false,
            'phone_exists' => $contactLocation['phone_exists'] ?? false,
            'may_be_in_pdf_header' => $contactLocation['may_be_in_pdf_header'] ?? false,
            'first_300_chars' => substr($parsedText, 0, 300),
        ]);

        // Check if contact is in first 300 chars OR first 10 lines (covers PDF header cases)
        $emailInAcceptableArea = $contactLocation['email_in_first_300'] || $contactLocation['email_in_first_10_lines'];
        $phoneInAcceptableArea = $contactLocation['phone_in_first_300'] || $contactLocation['phone_in_first_10_lines'];

        if (! $emailInAcceptableArea && ! $phoneInAcceptableArea) {
            // Only penalize if contact doesn't exist at all OR is really far from top
            if ($contactLocation['email_exists'] || $contactLocation['phone_exists']) {
                $score -= 15; // Reduced penalty - contact exists but not in ideal location
                $warnings[] = 'Contact information not found in first 300 characters or top 10 lines. ATS systems may miss this information if it\'s in a header/footer.';
            } else {
                $score -= 25; // Critical: no contact info found anywhere
                $criticalIssues[] = 'No contact information (email or phone) found in the resume. This is critical for ATS systems.';
            }
        } elseif ($contactLocation['may_be_in_pdf_header']) {
            // Contact appears in first 10 lines but not first 300 chars - likely PDF header
            $warnings[] = 'Contact information may be in PDF header/footer. ATS systems may miss headers/footers - consider moving to main body text.';
        } elseif (! $emailInAcceptableArea && $contactLocation['email_exists']) {
            $warnings[] = 'Email not found in first 300 characters. Consider moving it to the top of the resume for better ATS compatibility.';
        } elseif (! $phoneInAcceptableArea && $contactLocation['phone_exists']) {
            $warnings[] = 'Phone number not found in first 300 characters. Consider moving it to the top of the resume for better ATS compatibility.';
        }

        // Check 6: Date Detection (critical for ATS systems)
        $dateDetection = $this->checkDates($parsedText);
        $details['date_detection'] = $dateDetection;
        Log::debug('ATSParseabilityChecker: Date detection', [
            'has_valid_dates' => $dateDetection['has_valid_dates'],
            'has_placeholders' => $dateDetection['has_placeholders'],
            'date_count' => $dateDetection['date_count'],
            'placeholder_count' => $dateDetection['placeholder_count'],
        ]);
        if ($dateDetection['has_placeholders']) {
            $score -= 20; // Heavy penalty for placeholders like "20XX"
            $criticalIssues[] = $dateDetection['message'];
        } elseif (! $dateDetection['has_valid_dates']) {
            $score -= 25; // Critical: no dates found at all
            $criticalIssues[] = 'No dates found in work experience or education sections. ATS systems require dates to verify employment history and education timeline.';
        }

        // Check 7: Experience Level Detection (for length penalty adjustment)
        $experienceLevel = $this->detectExperienceLevel($parsedText);
        $details['experience_level'] = $experienceLevel;
        Log::debug('ATSParseabilityChecker: Experience level detection', [
            'years_of_experience' => $experienceLevel['years'] ?? 0,
            'is_experienced' => $experienceLevel['is_experienced'] ?? false,
        ]);

        // Adjust length penalty based on experience level
        if (! $lengthCheck['is_optimal'] && ($experienceLevel['is_experienced'] ?? false)) {
            $wordCount = $lengthCheck['word_count'] ?? 0;
            // For experienced candidates (5+ years), short resumes are more critical
            if ($wordCount < 400) {
                $additionalPenalty = 10; // Additional penalty for experienced candidates with short resumes
                $score -= $additionalPenalty;
                $warnings[] = 'Resume is too short for your experience level. With '.($experienceLevel['years'] ?? 0).'+ years of experience, you should have more content to showcase your achievements.';
            }
        }

        // Check 8: Name Detection (critical for ATS systems)
        $nameDetection = $this->checkName($parsedText);
        $details['name_detection'] = $nameDetection;
        Log::debug('ATSParseabilityChecker: Name detection', [
            'has_name' => $nameDetection['has_name'],
            'name_found' => $nameDetection['name'] ?? null,
        ]);
        if (! $nameDetection['has_name']) {
            $score -= 20; // Critical: no name found
            $criticalIssues[] = 'No name found in the resume. ATS systems require a candidate name for proper identification and tracking.';
        }

        // Check 9: Summary/Profile Detection
        $summaryDetection = $this->checkSummary($parsedText);
        $details['summary_detection'] = $summaryDetection;
        Log::debug('ATSParseabilityChecker: Summary detection', [
            'has_summary' => $summaryDetection['has_summary'],
        ]);
        if (! $summaryDetection['has_summary']) {
            $score -= 10; // Penalty for missing summary (not critical but important)
            $warnings[] = 'No summary or professional profile section found. A summary section helps ATS systems and recruiters quickly understand your background and career goals.';
        }

        // Check 10: Bullet Point Count
        $bulletPointCount = $this->countBulletPoints($parsedText);
        $details['bullet_point_count'] = $bulletPointCount;
        Log::debug('ATSParseabilityChecker: Bullet point count', [
            'bullet_count' => $bulletPointCount['count'],
            'is_optimal' => $bulletPointCount['is_optimal'],
        ]);
        if (! $bulletPointCount['is_optimal']) {
            $bulletCount = $bulletPointCount['count'];
            $bySection = $bulletPointCount['by_section'] ?? ['experience' => 0, 'projects' => 0, 'other' => 0];
            $experienceBullets = $bySection['experience'] ?? 0;
            $projectsBullets = $bySection['projects'] ?? 0;
            $otherBullets = $bySection['other'] ?? 0;

            // Ideal: 12-20 bullet points total, with 8+ in Experience section
            // Experience bullets are more important than Projects for ATS systems
            $penalty = match (true) {
                $bulletCount < 5 => 20, // Very few bullets - heavy penalty
                $bulletCount < 8 => 15, // Few bullets - significant penalty
                default => 10, // Some bullets but not enough
            };

            // Additional penalty if Experience section has too few bullets
            if ($experienceBullets < 8) {
                $penalty += match (true) {
                    $experienceBullets < 3 => 10, // Very few experience bullets
                    $experienceBullets < 5 => 5, // Few experience bullets
                    default => 0,
                };
            }

            $score -= $penalty;

            // Check for potential non-standard bullets that weren't detected
            $potentialNonStandardBullets = $bulletPointCount['potential_non_standard_bullets'] ?? 0;
            $nonStandardBySection = $bulletPointCount['non_standard_by_section'] ?? ['experience' => 0, 'projects' => 0, 'other' => 0];
            $hasNonStandardBullets = $potentialNonStandardBullets > 0;

            // Generate specific warning message based on section distribution
            $warningMessage = "Resume has {$bulletCount} total bullet points (recommended: 12-20). ";

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
            if ($hasNonStandardBullets && $bulletCount < 12) {
                $nonStandardProjects = $nonStandardBySection['projects'] ?? 0;
                $nonStandardExperience = $nonStandardBySection['experience'] ?? 0;

                if ($nonStandardProjects > 0 || $nonStandardExperience > 0) {
                    $warningMessage .= "Note: {$potentialNonStandardBullets} potential bullet point(s) detected but not recognized (likely due to non-standard bullet characters). Consider normalizing bullet characters to standard format (•, -, or *) for better ATS compatibility. ";
                }
            }

            // Generate specific recommendation based on section distribution
            $hasProjectsSection = in_array('projects', $sectionsFound, true);

            if ($experienceBullets < 8) {
                $warningMessage .= 'Focus on adding more bullet points in your Experience section (aim for 8-12 bullets). ';
            } elseif ($bulletCount < 12) {
                // If Experience has enough bullets but total is low, suggest adding to specific sections
                // Only suggest adding to Projects if it truly has 0 bullets (not just undetected)
                if ($hasProjectsSection && $projectsBullets === 0 && $experienceBullets >= 8) {
                    $warningMessage .= 'Your Projects section has no bullet points - consider adding bullet points to showcase your work. ';
                } elseif ($hasProjectsSection && $experienceBullets >= 8 && $projectsBullets > 0 && $projectsBullets < 3) {
                    $warningMessage .= 'Consider adding more bullet points to your Projects section (currently has '.$projectsBullets.'). ';
                } elseif ($experienceBullets >= 8 && ($projectsBullets > 0 || ! $hasProjectsSection)) {
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
        Log::debug('ATSParseabilityChecker: Metrics detection', [
            'has_metrics' => $metricsDetection['has_metrics'],
            'metric_count' => $metricsDetection['metric_count'],
        ]);
        if (! $metricsDetection['has_metrics']) {
            $score -= 15; // Penalty for lack of quantifiable achievements
            $warnings[] = 'Resume lacks quantifiable metrics and specific numbers. ATS systems and recruiters value resumes with measurable achievements (e.g., "increased sales by 30%", "managed team of 5", "reduced costs by $50K").';
        }

        // Ensure score doesn't go below 0
        $score = max(0, $score);

        // Determine confidence level
        $issueCount = count($criticalIssues) + count($warnings);
        $confidence = match (true) {
            $issueCount === 0 => 'high',
            $issueCount <= 2 => 'medium',
            default => 'low',
        };

        Log::debug('ATSParseabilityChecker: Final results', [
            'final_score' => $score,
            'confidence' => $confidence,
            'critical_issues_count' => count($criticalIssues),
            'warnings_count' => count($warnings),
        ]);

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

            // If text is very short (< 50 chars) but PDF has multiple pages, likely scanned
            if ($textLength < 50 && $pageCount > 1) {
                return [
                    'is_scanned_image' => true,
                    'message' => 'PDF appears to be a scanned image. ATS systems cannot extract text from images. Consider using OCR or recreating as a text-based PDF.',
                    'page_count' => $pageCount,
                    'text_length' => $textLength,
                ];
            }

            // If text is very short (< 20 chars) even on single page, likely scanned
            if ($textLength < 20) {
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

                if ($columnCount >= 3) {
                    $tableLines[] = $lineNum + 1;
                    $tablePatterns++;
                }
            }
        }

        $hasTables = $tablePatterns >= 3; // Need at least 3 lines with table patterns

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

        for ($i = 0; $i < min(50, $totalLines - 1); $i++) {
            $currentLine = trim($lines[$i]);
            $nextLine = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';

            // Skip empty lines
            if (empty($currentLine) || empty($nextLine)) {
                continue;
            }

            $currentLength = strlen($currentLine);
            $nextLength = strlen($nextLine);

            // Pattern 1: Very short line followed by long line (text jumping)
            if ($currentLength < 30 && $nextLength > 80) {
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
                if (abs($currentLength - $prevLength) > 60 && abs($currentLength - $nextLength) > 60) {
                    $suspiciousLines++;
                }
            }
        }

        $hasMultiColumn = $suspiciousLines >= 10; // Threshold for multi-column detection
        $confidence = match (true) {
            $suspiciousLines >= 20 => 'high',
            $suspiciousLines >= 10 => 'medium',
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
                // Average: ~400-500 words per page
                $pageCount = max(1, (int) ceil($wordCount / 400));
            }
        }

        $isOptimalLength = $wordCount >= 400 && $wordCount <= 800;
        $isOptimalPages = $pageCount >= 1 && $pageCount <= 2;

        $isOptimal = $isOptimalLength && $isOptimalPages;

        $message = match (true) {
            $wordCount < 400 => "Resume is too short ({$wordCount} words, ideal: 400-800). Consider adding more detail about your experience and achievements.",
            $wordCount > 800 => "Resume is too long ({$wordCount} words, ideal: 400-800). Consider condensing to 1-2 pages.",
            $pageCount > 2 => "Resume is too long ({$pageCount} pages, ideal: 1-2 pages). ATS systems and recruiters prefer concise resumes.",
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
        $first300Chars = substr($text, 0, 300);
        $fullText = $text;

        // Also check first 10 lines for header detection
        $lines = explode("\n", $text);
        $first10Lines = implode("\n", array_slice($lines, 0, 10));
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
        $hasValidDates = $dateCount >= 2; // Need at least 2 dates (start and end) for work experience

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
                if ($positionCount >= 3) {
                    $maxYears = 5; // Assume at least 5 years if 3+ positions
                } elseif ($positionCount >= 2) {
                    $maxYears = 3; // Assume at least 3 years if 2+ positions
                }
            }
        }

        return [
            'years' => $maxYears,
            'is_experienced' => $maxYears >= 5, // 5+ years considered "experienced"
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
        $first200Chars = substr($text, 0, 200);
        $lines = explode("\n", $first200Chars);
        $firstFewLines = array_slice(array_filter($lines), 0, 5);

        // Check for common name patterns:
        // 1. Capitalized words (2-3 words) at start of resume
        // 2. Patterns like "John Doe" or "Mary Jane Smith"
        foreach ($firstFewLines as $line) {
            $line = trim($line);
            // Skip empty lines or lines that are clearly not names
            if (empty($line) || strlen($line) > 50) {
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
                if (! $isHeader && $wordCount >= 2 && $wordCount <= 4) {
                    return [
                        'has_name' => true,
                        'name' => $line,
                    ];
                }
            }
        }

        // Fallback: check if there's a capitalized word pattern in first 100 chars
        $first100Chars = substr($text, 0, 100);
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
            if (! $isHeader && strlen($name) <= 30) {
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
                    $afterHeader = substr($text, $position, 300);
                    // Check if there's substantial content (at least 20 words)
                    $wordCount = $this->countWords($afterHeader);
                    if ($wordCount >= 20) {
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
     * Detects various bullet formats: •, ◦, numbers (1. 2. 3.), checkmarks (✓), arrows (→), dashes (-), etc.
     *
     * @return array{count: int, is_optimal: bool, by_section: array{experience: int, projects: int, other: int}, sections_found: array<string>}
     */
    protected function countBulletPoints(string $text): array
    {
        $bulletPatterns = [
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

        $count = 0;
        $lines = explode("\n", $text);
        $processedLines = [];
        $bulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $currentSection = 'other'; // Track current section

        // Section detection patterns (must match at start of line)
        $experiencePatterns = ['/^(professional\s+)?experience|work\s+experience|work\s+history|employment|career\s+history/i'];
        // Projects pattern: match "PROJECTS", "PROJECT", "Projects", "Portfolio", etc. at start of line
        $projectsPatterns = [
            '/^projects?/i',  // PROJECTS or PROJECT
            '/^portfolio/i',  // Portfolio
            '/^personal\s+projects/i',  // Personal Projects
        ];
        $sectionsFound = [];

        // First pass: detect bullets that are on separate lines (bullet character alone)
        // List of bullet characters to check for (including common Unicode bullet points)
        // Include standard bullets and non-standard bullets (from hex ef82b7 - common in PDFs)
        // The hex ef82b7 is a bullet character that appears in some PDFs due to encoding issues
        $nonStandardBullet = hex2bin('ef82b7') ?: ''; // Try to decode the hex bullet
        $bulletChars = ['•', '◦', '▪', '▫', '◘', '◙', '◉', '○', '●', '✓', '✔', '☑', '✅', '→', '⇒', '➜', '➤', '□', '■', '-', '*'];
        // Add non-standard bullet if we can decode it
        if (! empty($nonStandardBullet) && ! in_array($nonStandardBullet, $bulletChars, true)) {
            $bulletChars[] = $nonStandardBullet;
        }
        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);

            // Skip empty lines
            if (empty($trimmedLine)) {
                continue;
            }

            // Log for debugging: track lines after Projects detection
            if ($currentSection === 'projects' && $index > 39) {
                Log::debug('ATSParseabilityChecker: Processing line after Projects (first pass)', [
                    'line_index' => $index,
                    'line_preview' => substr($trimmedLine, 0, 50),
                    'line_length' => mb_strlen($trimmedLine),
                    'current_section' => $currentSection,
                ]);
            }

            // Check if line is a section header and update current section
            // Check Experience first
            $isExperienceSection = false;
            foreach ($experiencePatterns as $pattern) {
                if (preg_match($pattern, $trimmedLine)) {
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
                    // Check if line matches Projects pattern (case-insensitive, at start of line)
                    if (preg_match($pattern, $trimmedLine)) {
                        $oldSection = $currentSection;
                        $currentSection = 'projects';
                        if (! in_array('projects', $sectionsFound, true)) {
                            $sectionsFound[] = 'projects';
                        }
                        Log::debug('ATSParseabilityChecker: Projects section detected (first pass)', [
                            'line' => $trimmedLine,
                            'pattern' => $pattern,
                            'index' => $index,
                            'old_section' => $oldSection,
                            'new_section' => $currentSection,
                            'sections_found' => $sectionsFound,
                            'bullets_by_section' => $bulletsBySection,
                        ]);
                        break;
                    }
                }
            }

            // Prevent other sections from overriding Projects/Experience
            // Only reset to 'other' if we encounter a known section that's not Experience or Projects
            // But for now, we only track Experience and Projects, so this shouldn't be an issue

            // Check if line is ONLY a bullet character (or bullet + whitespace)
            $isOnlyBullet = false;
            $lineLength = mb_strlen($trimmedLine);

            // Log for debugging: track when we're checking for bullets
            if ($currentSection === 'projects' && $lineLength <= 3) {
                Log::debug('ATSParseabilityChecker: Checking if line is bullet (first pass)', [
                    'line_index' => $index,
                    'line_content' => $trimmedLine,
                    'line_length' => $lineLength,
                    'line_hex' => bin2hex($trimmedLine),
                    'current_section' => $currentSection,
                    'first_char_hex' => mb_strlen($trimmedLine) > 0 ? bin2hex(mb_substr($trimmedLine, 0, 1)) : 'empty',
                ]);
            }

            // Very short line (likely just a bullet) - check if it's ONLY a bullet character
            if ($lineLength <= 3) {
                // Method 1: Direct character comparison
                foreach ($bulletChars as $char) {
                    $charHex = bin2hex($char);
                    $lineHex = bin2hex($trimmedLine);
                    $isMatch = $trimmedLine === $char;

                    // Log for debugging Projects bullets
                    if ($currentSection === 'projects' && $lineLength === 1) {
                        Log::debug('ATSParseabilityChecker: Comparing bullet character', [
                            'line_index' => $index,
                            'line_content' => $trimmedLine,
                            'line_hex' => $lineHex,
                            'char' => $char,
                            'char_hex' => $charHex,
                            'is_match' => $isMatch,
                            'current_section' => $currentSection,
                        ]);
                    }

                    if ($isMatch) {
                        $isOnlyBullet = true;
                        // Log for debugging Projects bullets
                        Log::debug('ATSParseabilityChecker: Bullet solo detected (first pass)', [
                            'bullet_line_index' => $index,
                            'bullet_line' => $trimmedLine,
                            'bullet_char' => $char,
                            'current_section' => $currentSection,
                            'line_length' => $lineLength,
                            'is_projects' => $currentSection === 'projects',
                        ]);
                        break;
                    }
                }

                // Method 2: Regex pattern for bullet with optional whitespace
                if (! $isOnlyBullet) {
                    foreach ($bulletChars as $char) {
                        $pattern = '/^[\s]*'.preg_quote($char, '/').'[\s]*$/u';
                        if (preg_match($pattern, $trimmedLine)) {
                            $isOnlyBullet = true;
                            break;
                        }
                    }
                }

                // Method 3: Check if line contains any bullet character (fallback)
                if (! $isOnlyBullet && $lineLength <= 2) {
                    foreach ($bulletChars as $char) {
                        if (mb_strpos($trimmedLine, $char) !== false) {
                            $isOnlyBullet = true;
                            break;
                        }
                    }
                }

                // Method 4: Pattern-based detection - if line is very short (1-3 chars) and next line has content,
                // it's likely a bullet on a separate line (common resume formatting pattern)
                // This catches non-standard bullets that don't match known characters
                if (! $isOnlyBullet && $lineLength <= 3) {
                    $nextLineIndex = $index + 1;
                    $lookAheadLines = 3; // Look up to 3 lines ahead
                    $foundContentAhead = false;

                    // Check if any of the next few lines have substantial content
                    for ($checkIndex = $nextLineIndex; $checkIndex <= $index + $lookAheadLines && $checkIndex < count($lines); $checkIndex++) {
                        if (isset($lines[$checkIndex])) {
                            $checkLine = trim($lines[$checkIndex]);
                            // If line has substantial content (10+ chars), treat current line as bullet
                            if (! empty($checkLine) && mb_strlen($checkLine) >= 10) {
                                // Additional check: current line should not be a header or date
                                $isHeaderOrDate = preg_match('/^(PROFESSIONAL|EXPERIENCE|EDUCATION|PROJECTS|SKILLS|SUMMARY|LANGUAGES|CERTIFICATIONS|LEADERSHIP)/i', $trimmedLine) ||
                                                preg_match('/\d{4}/', $trimmedLine) ||
                                                preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i', $trimmedLine);

                                if (! $isHeaderOrDate) {
                                    $isOnlyBullet = true;
                                    $foundContentAhead = true;

                                    // Log for debugging non-standard bullets
                                    if ($currentSection === 'projects') {
                                        Log::debug('ATSParseabilityChecker: Non-standard bullet detected (Method 4)', [
                                            'bullet_line_index' => $index,
                                            'bullet_line' => $trimmedLine,
                                            'bullet_hex' => bin2hex($trimmedLine),
                                            'content_line_index' => $checkIndex,
                                            'content_preview' => substr($checkLine, 0, 50),
                                            'current_section' => $currentSection,
                                        ]);
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            // If line is ONLY a bullet character, check next lines for content (skip empty lines)
            if ($isOnlyBullet) {
                // Look for the next non-empty line with content
                $nextLineIndex = $index + 1;
                $foundContent = false;
                $searchAttempts = 0;

                Log::debug('ATSParseabilityChecker: Searching for content after bullet', [
                    'bullet_line_index' => $index,
                    'bullet_line' => $trimmedLine,
                    'current_section' => $currentSection,
                    'next_line_index' => $nextLineIndex,
                    'max_search_lines' => 3,
                ]);

                while (isset($lines[$nextLineIndex]) && ! $foundContent) {
                    $nextLine = trim($lines[$nextLineIndex]);
                    $searchAttempts++;

                    Log::debug('ATSParseabilityChecker: Checking line for content', [
                        'bullet_line_index' => $index,
                        'checking_line_index' => $nextLineIndex,
                        'line_content' => $nextLine,
                        'line_length' => mb_strlen($nextLine),
                        'is_empty' => empty($nextLine),
                        'has_sufficient_length' => mb_strlen($nextLine) >= 10,
                        'already_processed' => in_array($nextLine, $processedLines, true),
                        'current_section' => $currentSection,
                    ]);

                    // If next line has content, count it as a bullet point
                    if (! empty($nextLine) && mb_strlen($nextLine) >= 10) {
                        // Skip if next line is already processed
                        if (! in_array($nextLine, $processedLines, true)) {
                            $count++;
                            $processedLines[] = $nextLine; // Mark the content line as processed
                            // Track which section this bullet belongs to
                            $bulletsBySection[$currentSection]++;

                            // Log for debugging Projects bullets (always log to see what's happening)
                            Log::debug('ATSParseabilityChecker: Bullet content found and assigned', [
                                'bullet_line_index' => $index,
                                'bullet_line' => $trimmedLine,
                                'content_line_index' => $nextLineIndex,
                                'content_preview' => substr($nextLine, 0, 50),
                                'current_section' => $currentSection,
                                'is_projects' => $currentSection === 'projects',
                                'bullets_by_section' => $bulletsBySection,
                                'total_bullets' => $count,
                                'processed_lines_count' => count($processedLines),
                            ]);

                            $foundContent = true;
                        } else {
                            // Log if content was already processed
                            Log::debug('ATSParseabilityChecker: Bullet content already processed', [
                                'bullet_line_index' => $index,
                                'content_line_index' => $nextLineIndex,
                                'content_preview' => substr($nextLine, 0, 50),
                                'current_section' => $currentSection,
                                'is_projects' => $currentSection === 'projects',
                                'processed_lines_count' => count($processedLines),
                            ]);
                        }
                    }
                    $nextLineIndex++;
                    // Limit search to next 3 lines to avoid going too far
                    if ($nextLineIndex > $index + 3) {
                        Log::debug('ATSParseabilityChecker: Reached max search lines', [
                            'bullet_line_index' => $index,
                            'max_lines' => 3,
                            'search_attempts' => $searchAttempts,
                            'found_content' => $foundContent,
                        ]);
                        break;
                    }
                }

                if (! $foundContent) {
                    Log::debug('ATSParseabilityChecker: No content found after bullet', [
                        'bullet_line_index' => $index,
                        'bullet_line' => $trimmedLine,
                        'current_section' => $currentSection,
                        'search_attempts' => $searchAttempts,
                    ]);
                }

                continue; // Skip the bullet-only line itself
            }
        }

        // Second pass: detect bullets that are inline with content
        $currentSection = 'other'; // Reset for second pass
        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) {
                continue;
            }

            // Check if line is a section header and update current section
            // Check Experience first
            $isExperienceSection = false;
            foreach ($experiencePatterns as $pattern) {
                if (preg_match($pattern, $trimmedLine)) {
                    $currentSection = 'experience';
                    $isExperienceSection = true;
                    break;
                }
            }

            // Check Projects (only if not Experience)
            if (! $isExperienceSection) {
                foreach ($projectsPatterns as $pattern) {
                    if (preg_match($pattern, $trimmedLine)) {
                        $oldSection = $currentSection;
                        $currentSection = 'projects';
                        if (! in_array('projects', $sectionsFound, true)) {
                            $sectionsFound[] = 'projects'; // Track that we found this section
                        }
                        Log::debug('ATSParseabilityChecker: Projects section detected (second pass)', [
                            'line' => $trimmedLine,
                            'pattern' => $pattern,
                            'index' => $index,
                            'old_section' => $oldSection,
                            'new_section' => $currentSection,
                            'sections_found' => $sectionsFound,
                            'bullets_by_section' => $bulletsBySection,
                        ]);
                        break;
                    }
                }
            }

            $line = $trimmedLine;

            // Skip if already processed
            if (in_array($line, $processedLines, true)) {
                Log::debug('ATSParseabilityChecker: Line already processed (second pass)', [
                    'line_index' => $index,
                    'line_content' => substr($line, 0, 50),
                    'current_section' => $currentSection,
                    'is_projects' => $currentSection === 'projects',
                ]);

                continue;
            }

            // Check if current line starts with a bullet character or pattern
            $isBulletLine = false;
            foreach ($bulletPatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $isBulletLine = true;
                    break;
                }
            }

            // If current line is a bullet WITH content (bullet + text), count it
            if ($isBulletLine) {
                // If bullet line itself has content (after bullet), count it
                if (strlen($line) >= 10) {
                    $count++;
                    $processedLines[] = $line;
                    // Track which section this bullet belongs to
                    $bulletsBySection[$currentSection]++;

                    // Log for debugging Projects bullets
                    Log::debug('ATSParseabilityChecker: Bullet inline detected (second pass)', [
                        'line_index' => $index,
                        'line_preview' => substr($line, 0, 50),
                        'line_length' => strlen($line),
                        'current_section' => $currentSection,
                        'is_projects' => $currentSection === 'projects',
                        'bullets_by_section' => $bulletsBySection,
                        'total_bullets' => $count,
                    ]);

                    continue;
                }
            }

            // Skip very short lines (likely headers or dates) - but only if not a bullet
            if (strlen($line) < 10 && ! $isBulletLine) {
                continue;
            }

            // Check if line starts with a bullet character or pattern (standard check)
            foreach ($bulletPatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $count++;
                    $processedLines[] = $line;
                    // Track which section this bullet belongs to
                    $bulletsBySection[$currentSection]++;
                    break; // Count each line only once
                }
            }
        }

        // Fallback: check if bullet characters appear anywhere in the line (not just at start)
        // This catches cases where there might be indentation or formatting issues
        if ($count < 5) {
            // Try a more permissive pattern: any bullet character in the line
            $bulletChars = ['•', '◦', '▪', '▫', '◘', '◙', '◉', '○', '●', '✓', '✔', '☑', '✅', '→', '⇒', '➜', '➤', '□', '■'];
            $currentSection = 'other'; // Reset for fallback pass
            foreach ($lines as $index => $line) {
                $trimmedLine = trim($line);
                if (empty($trimmedLine)) {
                    continue;
                }

                // Check if line is a section header and update current section
                foreach ($experiencePatterns as $pattern) {
                    if (preg_match($pattern, $trimmedLine)) {
                        $currentSection = 'experience';
                        break;
                    }
                }
                if ($currentSection !== 'experience') {
                    foreach ($projectsPatterns as $pattern) {
                        if (preg_match($pattern, $trimmedLine)) {
                            $currentSection = 'projects';
                            $sectionsFound[] = 'projects'; // Track that we found this section
                            break;
                        }
                    }
                }

                $line = $trimmedLine;

                // Skip if already processed
                if (in_array($line, $processedLines, true)) {
                    continue;
                }

                // Check if line contains a bullet character
                foreach ($bulletChars as $char) {
                    if (str_contains($line, $char)) {
                        // Additional check: make sure it's not in the middle of a word
                        $charPos = strpos($line, $char);
                        // If bullet is in first 5 characters, it's likely a bullet point
                        if ($charPos !== false && $charPos < 5) {
                            // If line is just a bullet (short), check next lines for content (skip empty lines)
                            if (strlen($line) < 10) {
                                $nextLineIndex = $index + 1;
                                $foundContent = false;
                                while (isset($lines[$nextLineIndex]) && ! $foundContent) {
                                    $nextLine = trim($lines[$nextLineIndex]);
                                    if (! empty($nextLine) && strlen($nextLine) >= 10) {
                                        $count++;
                                        $processedLines[] = $nextLine;
                                        $bulletsBySection[$currentSection]++;
                                        $foundContent = true;
                                        break 2; // Break both loops
                                    }
                                    $nextLineIndex++;
                                    // Limit search to next 3 lines to avoid going too far
                                    if ($nextLineIndex > $index + 3) {
                                        break;
                                    }
                                }
                            } else {
                                // Line has bullet and content
                                $count++;
                                $processedLines[] = $line;
                                $bulletsBySection[$currentSection]++;
                                break; // Count each line only once
                            }
                        }
                    }
                }
            }

            // Also check for numbered lists (1. 2. 3. pattern)
            if ($count < 5) {
                $currentSection = 'other'; // Reset for numbered lists check
                foreach ($lines as $index => $line) {
                    $trimmedLine = trim($line);
                    if (empty($trimmedLine)) {
                        continue;
                    }

                    // Check if line is a section header and update current section
                    foreach ($experiencePatterns as $pattern) {
                        if (preg_match($pattern, $trimmedLine)) {
                            $currentSection = 'experience';
                            break;
                        }
                    }
                    if ($currentSection !== 'experience') {
                        foreach ($projectsPatterns as $pattern) {
                            if (preg_match($pattern, $trimmedLine)) {
                                $currentSection = 'projects';
                                break;
                            }
                        }
                    }

                    $line = $trimmedLine;
                    if (strlen($line) < 10) {
                        continue;
                    }

                    // Skip if already processed
                    if (in_array($line, $processedLines, true)) {
                        continue;
                    }

                    // Check for numbered list pattern: starts with number followed by period/parenthesis/dash
                    if (preg_match('/^\d+[.)-]\s+/', $line)) {
                        $count++;
                        $processedLines[] = $line;
                        $bulletsBySection[$currentSection]++;
                    }
                }
            }
        }

        // Additional detection: Look for implicit bullet lists (lines that look like list items)
        // These are lines that appear to be bullets but the bullet character wasn't extracted
        // Common patterns:
        // 1. Short capitalized lines (likely skills/items in a list)
        // 2. Action verb lines (likely experience bullets)
        // 3. Lines that follow a pattern suggesting a list
        if ($count < 5) {
            $implicitBulletCount = 0;
            $consecutiveShortLines = 0;
            $actionVerbLines = 0;

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
                // - 2-4 words, mostly capitalized or title case
                // - Common in skills sections
                if ($lineLength >= 10 && $lineLength <= 60 && $wordCount >= 2 && $wordCount <= 4) {
                    // Check if line is mostly title case or capitalized
                    $titleCaseWords = 0;
                    foreach ($words as $word) {
                        $cleanWord = preg_replace('/[^a-zA-Z]/', '', $word);
                        if (! empty($cleanWord) && (ucfirst(strtolower($cleanWord)) === $cleanWord || ctype_upper($cleanWord))) {
                            $titleCaseWords++;
                        }
                    }
                    // If 50%+ of words are title case, likely a list item
                    if ($titleCaseWords >= ($wordCount * 0.5)) {
                        $implicitBulletCount++;
                        $consecutiveShortLines++;

                        continue;
                    }
                }

                // Pattern 2: Action verb lines (likely experience bullets)
                // - Lines starting with action verbs
                // - Common in experience sections
                $actionVerbs = ['managed', 'developed', 'led', 'created', 'built', 'implemented', 'designed', 'improved',
                    'launched', 'optimized', 'delivered', 'achieved', 'increased', 'reduced', 'established',
                    'coordinated', 'executed', 'transformed', 'enhanced', 'streamlined', 'automated',
                    'architected', 'deployed', 'integrated', 'migrated', 'scaled', 'maintained', 'collaborated',
                    'mentored', 'trained', 'supervised', 'analyzed', 'researched', 'evaluated',
                    'performed', 'prepared', 'maintained', 'monitored', 'reviewed', 'provided', 'compiled',
                    'filed', 'reconciled', 'posted', 'verified', 'acted', 'tracked', 'identified', 'stayed'];
                $firstWord = strtolower(explode(' ', $line)[0]);
                if (in_array($firstWord, $actionVerbs, true) && $lineLength >= 20) {
                    $actionVerbLines++;

                    continue;
                }
            }

            // If we found many implicit bullets, add them to count
            // But be conservative - only count if we're confident they're list items
            if ($implicitBulletCount >= 3 || $actionVerbLines >= 3) {
                // Count implicit bullets but be conservative
                // Only count if we have a clear pattern (3+ consecutive or 3+ action verbs)
                $additionalCount = min($implicitBulletCount, $actionVerbLines > 0 ? max($actionVerbLines, $implicitBulletCount) : $implicitBulletCount);
                // Only add if we're confident (at least 3 items)
                if ($additionalCount >= 3) {
                    $count += $additionalCount;
                }
            }
        }

        // Use bulletsBySection that was tracked during processing
        $experienceBullets = $bulletsBySection['experience'];
        $projectsBullets = $bulletsBySection['projects'];
        $otherBullets = $bulletsBySection['other'];

        // Detect implicit bullets (paragraphs that act like bullets but don't have bullet characters)
        // These are common in resumes where bullets are graphics/images that don't extract
        // Pattern: Lines in Experience section that start with action verbs and are 20-200 chars
        // Only run this if we found Experience section but didn't detect many bullets there
        $implicitBulletCount = 0;
        $implicitBulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $hasExperienceSection = in_array('experience', $sectionsFound, true);
        $hasProjectsSection = in_array('projects', $sectionsFound, true);

        // Only detect implicit bullets if we have an Experience section but few bullets detected
        // This handles cases where bullets are graphics/images that don't extract
        if ($hasExperienceSection && $experienceBullets < 5) {
            $currentSection = 'other';

            $actionVerbs = ['managed', 'develop', 'led', 'created', 'built', 'implemented', 'designed', 'improved',
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
                'won', 'wrote'];

            foreach ($lines as $index => $line) {
                $trimmedLine = trim($line);
                if (empty($trimmedLine)) {
                    continue;
                }

                // Check if line is a section header and update current section
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
                // Allow longer lines (up to 300 chars) for implicit bullets as they may be full sentences
                $lineLength = mb_strlen($trimmedLine);
                if ($lineLength < 20 || $lineLength > 300) {
                    continue;
                }

                // Skip if already processed as a bullet
                if (in_array($trimmedLine, $processedLines, true)) {
                    continue;
                }

                // Skip if it's a header, date, or company name
                $isHeader = preg_match('/^(PROFESSIONAL|EXPERIENCE|EDUCATION|PROJECTS|SKILLS|SUMMARY|LANGUAGES|CERTIFICATIONS|LEADERSHIP|WORK\s+HISTORY|Highlights|Lead|Senior|Staff|Accountant|Branch|Cashier)\s+(Accountant|Developer|Engineer|Manager|Analyst|Specialist|Coordinator|Director|VP|President|CEO|CTO|Service|Specialist)/i', $trimmedLine) ||
                           preg_match('/\d{4}\s+to\s+(Current|Present|\d{4})/i', $trimmedLine) ||
                           preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\.?\s+\d{4}/i', $trimmedLine) ||
                           preg_match('/^(November|September|March|January|February|April|May|June|July|August|October|December)\s+\d{4}/i', $trimmedLine) ||
                           preg_match('/^\d{2}\/\d{4}/i', $trimmedLine) || // 04/2020 format
                           preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+\s+\|/i', $trimmedLine) || // Company Name | Location pattern
                           preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+\s+\d{4}/i', $trimmedLine) || // Company Name 2024 pattern
                           preg_match('/^Company\s+Name/i', $trimmedLine); // Company Name placeholder

                if ($isHeader) {
                    continue;
                }

                // Check if line starts with an action verb (likely a bullet point)
                $firstWord = strtolower(explode(' ', $trimmedLine)[0]);
                $firstWord = preg_replace('/[^a-z]/', '', $firstWord); // Remove punctuation

                // Also check for third person singular forms (Prepares -> prepare, Executes -> execute, Processes -> process)
                // Remove 's' from end if present to match base form
                $baseWord = rtrim($firstWord, 's');
                $baseWordEs = rtrim($firstWord, 'es'); // For processes -> process

                if (in_array($firstWord, $actionVerbs, true) ||
                    in_array($baseWord, $actionVerbs, true) ||
                    in_array($baseWordEs, $actionVerbs, true)) {
                    // Additional check: line should not be a job title or company name
                    $isJobTitle = preg_match('/^(Senior|Junior|Lead|Manager|Developer|Engineer|Analyst|Specialist|Coordinator|Director|VP|President|CEO|CTO|Full Stack|Software|Web|Accountant)\s+/i', $trimmedLine) &&
                                 $lineLength < 80;

                    if (! $isJobTitle && $currentSection === 'experience') {
                        $implicitBulletCount++;
                        $implicitBulletsBySection[$currentSection]++;

                        // Also add to processed lines to avoid double counting
                        if (! in_array($trimmedLine, $processedLines, true)) {
                            $processedLines[] = $trimmedLine;
                            $count++;
                            $bulletsBySection[$currentSection]++;
                        }
                    }
                }
            }
        }

        // Update final counts with implicit bullets
        $experienceBullets = $bulletsBySection['experience'];
        $projectsBullets = $bulletsBySection['projects'];
        $otherBullets = $bulletsBySection['other'];

        // Detect potential non-standard bullets that weren't detected
        // These are short lines (1-3 chars) followed by content that look like bullets
        // but didn't match any known bullet character AND weren't already processed
        $potentialNonStandardBullets = 0;
        $nonStandardBulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $currentSection = 'other';

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) {
                continue;
            }

            // Check if line is a section header and update current section
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
            if ($lineLength >= 1 && $lineLength <= 3) {
                // Skip if this line was already processed as a bullet in the main detection
                // We need to check if the line itself or the next line (content) was already processed
                $wasProcessed = false;

                // Check if the line itself was processed
                if (in_array($trimmedLine, $processedLines, true)) {
                    $wasProcessed = true;
                }

                // Also check if the next line (content) was already processed
                if (! $wasProcessed) {
                    $nextLineIndex = $index + 1;
                    $lookAheadCheck = 3;
                    for ($checkIdx = $nextLineIndex; $checkIdx <= $index + $lookAheadCheck && $checkIdx < count($lines); $checkIdx++) {
                        if (isset($lines[$checkIdx])) {
                            $nextCheckLine = trim($lines[$checkIdx]);
                            if (! empty($nextCheckLine) && mb_strlen($nextCheckLine) >= 10) {
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
                    $lookAheadLines = 3;
                    $foundContentAhead = false;

                    for ($checkIndex = $nextLineIndex; $checkIndex <= $index + $lookAheadLines && $checkIndex < count($lines); $checkIndex++) {
                        if (isset($lines[$checkIndex])) {
                            $checkLine = trim($lines[$checkIndex]);
                            if (! empty($checkLine) && mb_strlen($checkLine) >= 10) {
                                // Check if it's not a header or date
                                $isHeaderOrDate = preg_match('/^(PROFESSIONAL|EXPERIENCE|EDUCATION|PROJECTS|SKILLS|SUMMARY|LANGUAGES|CERTIFICATIONS|LEADERSHIP|WORK\s+HISTORY)/i', $trimmedLine) ||
                                                preg_match('/\d{4}/', $trimmedLine) ||
                                                preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i', $trimmedLine);

                                if (! $isHeaderOrDate) {
                                    $potentialNonStandardBullets++;
                                    $nonStandardBulletsBySection[$currentSection]++;
                                    $foundContentAhead = true;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Debug: Log section distribution
        Log::debug('ATSParseabilityChecker: Bullet section distribution', [
            'total' => $count,
            'experience' => $experienceBullets,
            'projects' => $projectsBullets,
            'other' => $otherBullets,
            'sections_found' => $sectionsFound,
            'potential_non_standard_bullets' => $potentialNonStandardBullets,
            'non_standard_by_section' => $nonStandardBulletsBySection,
        ]);

        // Ideal: 12-20 bullet points for experienced candidates
        // Minimum: 8 bullet points for entry-level
        // Experience bullets are more important than projects
        $isOptimal = $count >= 12 && $experienceBullets >= 8;

        return [
            'count' => $count,
            'is_optimal' => $isOptimal,
            'by_section' => [
                'experience' => $experienceBullets,
                'projects' => $projectsBullets,
                'other' => $otherBullets,
            ],
            'sections_found' => array_values(array_unique($sectionsFound)),
            'potential_non_standard_bullets' => $potentialNonStandardBullets,
            'non_standard_by_section' => $nonStandardBulletsBySection,
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
        $hasMetrics = $count >= 3;

        return [
            'has_metrics' => $hasMetrics,
            'metric_count' => $count,
        ];
    }
}
