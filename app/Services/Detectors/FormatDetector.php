<?php

namespace App\Services\Detectors;

use App\Services\ATSParseabilityCheckerConstants;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

/**
 * Format Detector
 *
 * Detects format-related issues that affect ATS parseability:
 * - Scanned image PDFs
 * - Table structures
 * - Multi-column layouts
 */
class FormatDetector
{
    /**
     * Check if PDF is a scanned image (no extractable text).
     *
     * @return array{
     *     is_scanned_image: bool,
     *     message: string,
     *     page_count?: int,
     *     text_length?: int
     * }
     */
    public function checkTextExtractability(string $filePath, string $parsedText, string $mimeType): array
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
     * @return array{
     *     has_tables: bool,
     *     message: string,
     *     table_line_count: int,
     *     approximate_lines: array<int>
     * }
     */
    public function detectTables(string $text): array
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
     * @return array{
     *     has_multi_column: bool,
     *     message: string,
     *     confidence: 'high'|'medium'|'low',
     *     suspicious_patterns: int
     * }
     */
    public function detectMultiColumnLayout(string $text): array
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
}
