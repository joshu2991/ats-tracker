<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use Illuminate\Support\Facades\Log;

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
     * Parse PDF file and extract text.
     */
    protected function parsePDF(string $filePath): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();

            if (empty(trim($text))) {
                throw new \RuntimeException('Unable to extract text from PDF. The file may be corrupted or contain only images.');
            }

            // Clean and normalize UTF-8 encoding
            return $this->cleanText($text);
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
        }

        // Remove non-printable characters except newlines, tabs, and carriage returns
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove excessive whitespace but preserve structure
        $text = preg_replace('/[ \t]+/', ' ', $text); // Multiple spaces/tabs to single space
        $text = preg_replace('/\n{3,}/', "\n\n", $text); // Multiple newlines to double newline

        // Final UTF-8 validation
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        
        // Ensure it's valid UTF-8 for JSON encoding
        $text = json_decode(json_encode($text), true) ?? $text;

        return trim($text);
    }
}

