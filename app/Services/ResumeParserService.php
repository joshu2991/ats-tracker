<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use Spatie\PdfToText\Pdf;

class ResumeParserService
{
    /**
     * Parse the resume file and extract text content.
     */
    public function parse(string $filePath, string $mimeType): string
    {
        // Check file extension as fallback for MIME type variations
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'pdf' || $mimeType === 'application/pdf') {
            return $this->parsePDF($filePath);
        }

        if ($extension === 'docx' || str_contains($mimeType, 'wordprocessingml') || str_contains($mimeType, 'officedocument')) {
            return $this->parseDOCX($filePath);
        }

        throw new \InvalidArgumentException("Unsupported file type: {$mimeType}. Only PDF and DOCX files are supported.");
    }

    /**
     * Parse PDF file and extract text using Spatie/pdf-to-text first (preserves layout better),
     * with fallback to Smalot/PdfParser if Spatie fails or pdftotext is not available.
     */
    protected function parsePDF(string $filePath): string
    {
        try {
            $extractionMethod = 'unknown';
            $text = null;
            $pages = [];

            // Try Spatie/pdf-to-text first (uses pdftotext from poppler-utils - preserves layout much better)
            try {
                $text = Pdf::getText($filePath);
                $extractionMethod = 'spatie_pdftotext';
            } catch (\Exception $e) {
                // Spatie failed - log and try fallback
                Log::warning('Spatie/pdf-to-text extraction failed, trying fallback', [
                    'error' => $e->getMessage(),
                    'note' => 'pdftotext (poppler-utils) may not be installed. Install with: apt-get install poppler-utils',
                ]);
            }

            // Fallback to Smalot/PdfParser if Spatie failed or returned empty
            if (empty(trim($text ?? ''))) {
                try {
                    $parser = new PdfParser;
                    $pdf = $parser->parseFile($filePath);
                    $pages = $pdf->getPages();

                    // Extract text page by page to preserve layout order
                    $text = '';
                    foreach ($pages as $page) {
                        $pageText = $page->getText();
                        if (! empty($pageText)) {
                            $text .= $pageText."\n";
                        }
                    }

                    // Fallback to full text extraction if page-by-page fails
                    if (empty(trim($text))) {
                        $text = $pdf->getText();
                    }

                    $extractionMethod = 'smalot_pdfparser';

                } catch (\Exception $e) {
                    Log::error('Both PDF extraction methods failed', [
                        'spatie_error' => 'Spatie/pdf-to-text failed',
                        'smalot_error' => $e->getMessage(),
                    ]);
                }
            }

            if (empty(trim($text ?? ''))) {
                throw new \RuntimeException('Unable to extract text from PDF. The file may be corrupted or contain only images. If using Spatie/pdf-to-text, ensure poppler-utils is installed: apt-get install poppler-utils');
            }

            // Clean and normalize UTF-8 encoding
            $cleanedText = $this->cleanText($text);

            return $cleanedText;
        } catch (\RuntimeException $e) {
            // Re-throw RuntimeExceptions (they already have proper messages)
            throw $e;
        } catch (\Exception $e) {
            Log::error('PDF parsing failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to parse PDF file. Please ensure the file is not corrupted.');
        }
    }

    /**
     * Parse DOCX file and extract text.
     */
    protected function parseDOCX(string $filePath): string
    {
        try {
            $phpWord = WordIOFactory::load($filePath);
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText()."\n";
                    }
                }
            }

            if (empty(trim($text))) {
                throw new \RuntimeException('Unable to extract text from DOCX. The file may be corrupted or empty.');
            }

            // Clean and normalize UTF-8 encoding
            return $this->cleanText(trim($text));
        } catch (\Exception $e) {
            Log::error('DOCX parsing failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to parse DOCX file. Please ensure the file is not corrupted.');
        }
    }

    /**
     * Clean and normalize text to ensure valid UTF-8 encoding.
     */
    protected function cleanText(string $text): string
    {
        $originalLength = strlen($text);

        // First, try to detect and fix encoding issues
        if (! mb_check_encoding($text, 'UTF-8')) {
            // Try to detect the encoding
            $detectedEncoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($detectedEncoding && $detectedEncoding !== 'UTF-8') {
                $text = mb_convert_encoding($text, 'UTF-8', $detectedEncoding);
            } else {
                // Fallback: remove invalid UTF-8 sequences
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            }
        }

        // Use iconv to remove invalid UTF-8 characters
        $text = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if ($text === false) {
            $text = '';
            Log::warning('Iconv conversion failed, text set to empty');
        }

        // Remove non-printable characters except newlines, tabs, and carriage returns
        $beforeNonPrintable = strlen($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $nonPrintableRemoved = $beforeNonPrintable - strlen($text);

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove excessive whitespace but preserve structure
        $beforeWhitespace = strlen($text);
        $text = preg_replace('/[ \t]+/', ' ', $text); // Multiple spaces/tabs to single space
        $text = preg_replace('/\n{3,}/', "\n\n", $text); // Multiple newlines to double newline
        $whitespaceRemoved = $beforeWhitespace - strlen($text);

        // Final UTF-8 validation
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // Ensure it's valid UTF-8 for JSON encoding
        $text = json_decode(json_encode($text), true) ?? $text;

        return trim($text);
    }
}
