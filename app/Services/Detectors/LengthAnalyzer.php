<?php

namespace App\Services\Detectors;

use App\Services\ATSParseabilityCheckerConstants;
use Smalot\PdfParser\Parser;

/**
 * Length Analyzer
 *
 * Analyzes document length and word count for optimal ATS compatibility.
 */
class LengthAnalyzer
{
    /**
     * Verify document length is optimal.
     */
    public function verifyDocumentLength(string $filePath, string $parsedText, string $mimeType, callable $countWords): array
    {
        $wordCount = $countWords($parsedText);
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
     * Count words accurately, handling special characters, emails, URLs.
     */
    public function countWords(string $text): int
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
}
