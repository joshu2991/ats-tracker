<?php

namespace App\Services\Detectors;

use App\Services\ATSParseabilityCheckerConstants;

/**
 * Content Detector
 *
 * Detects content-related issues:
 * - Contact information location
 * - Date detection and validation
 * - Name detection
 * - Summary/profile detection
 */
class ContentDetector
{
    /**
     * Check if contact info is in first 300 characters (expanded from 200).
     * Expanded check accounts for PDF headers/footers that may be extracted in different order.
     * Also checks if contact appears after line 10 but PDF shows it at top (may be in PDF header).
     */
    public function checkContactInfoLocation(string $text): array
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
    public function checkDates(string $text): array
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
     * Check if resume has a name (critical for ATS systems).
     *
     * @return array{has_name: bool, name: string|null}
     */
    public function checkName(string $text): array
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
    public function checkSummary(string $text, callable $countWords): array
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
                    $wordCount = $countWords($afterHeader);
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
}
