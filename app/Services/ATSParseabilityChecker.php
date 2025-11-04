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
        $score = 100;
        $criticalIssues = [];
        $warnings = [];
        $details = [];

        // Check 1: Text Extractability (scanned image detection)
        $textExtractability = $this->checkTextExtractability($filePath, $parsedText, $mimeType);
        $details['text_extractability'] = $textExtractability;
        if ($textExtractability['is_scanned_image']) {
            $score -= 30;
            $criticalIssues[] = $textExtractability['message'];
        }

        // Check 2: Table Detection
        $tableDetection = $this->detectTables($parsedText);
        $details['table_detection'] = $tableDetection;
        if ($tableDetection['has_tables']) {
            $score -= 20;
            $warnings[] = $tableDetection['message'];
        }

        // Check 3: Multi-Column Layout Detection
        $multiColumn = $this->detectMultiColumnLayout($parsedText);
        $details['multi_column'] = $multiColumn;
        if ($multiColumn['has_multi_column']) {
            $score -= 15;
            $warnings[] = $multiColumn['message'];
        }

        // Check 4: Document Length Verification
        $lengthCheck = $this->verifyDocumentLength($filePath, $parsedText, $mimeType);
        $details['document_length'] = $lengthCheck;
        if (! $lengthCheck['is_optimal']) {
            $score -= 10;
            $warnings[] = $lengthCheck['message'];
        }

        // Check 5: Contact Info Location
        $contactLocation = $this->checkContactInfoLocation($parsedText);
        $details['contact_location'] = $contactLocation;
        if (! $contactLocation['email_in_first_200'] && ! $contactLocation['phone_in_first_200']) {
            $score -= 25;
            $criticalIssues[] = 'Contact information (email or phone) not found in first 200 characters. ATS systems may miss this critical information.';
        } elseif (! $contactLocation['email_in_first_200']) {
            $score -= 15;
            $warnings[] = 'Email not found in first 200 characters. Consider moving it to the top of the resume.';
        } elseif (! $contactLocation['phone_in_first_200']) {
            $score -= 10;
            $warnings[] = 'Phone number not found in first 200 characters. Consider moving it to the top of the resume.';
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
        $wordCount = str_word_count($parsedText);
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
     * Check if contact info is in first 200 characters.
     */
    protected function checkContactInfoLocation(string $text): array
    {
        $first200Chars = substr($text, 0, 200);
        $fullText = $text;

        // Check for email in first 200 chars
        $emailInFirst200 = false;
        $emailPosition = null;
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $first200Chars, $matches)) {
            $emailInFirst200 = true;
            $emailPosition = strpos($first200Chars, $matches[0]);
        }

        // Check for phone in first 200 chars
        $phoneInFirst200 = false;
        $phonePosition = null;
        $phonePatterns = [
            '/\+?1?\s*\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/', // US/CA format
            '/\+?52\s*\(?\d{2}\)?[\s.-]?\d{4}[\s.-]?\d{4}/', // MX format
        ];

        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $first200Chars, $matches)) {
                $phoneInFirst200 = true;
                $phonePosition = strpos($first200Chars, $matches[0]);
                break;
            }
        }

        // Also check if email/phone exists in full text (for reference)
        $emailExists = (bool) preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $fullText);
        $phoneExists = false;
        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $fullText)) {
                $phoneExists = true;
                break;
            }
        }

        return [
            'email_in_first_200' => $emailInFirst200,
            'phone_in_first_200' => $phoneInFirst200,
            'email_position' => $emailPosition,
            'phone_position' => $phonePosition,
            'email_exists' => $emailExists,
            'phone_exists' => $phoneExists,
        ];
    }
}
