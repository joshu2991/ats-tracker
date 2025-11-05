<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeResumeRequest;
use App\Services\AIResumeAnalyzer;
use App\Services\ATSParseabilityChecker;
use App\Services\ATSScorerService;
use App\Services\ATSScoreValidator;
use App\Services\KeywordAnalyzerService;
use App\Services\ResumeParserService;
use App\Services\SectionDetectorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ResumeController extends Controller
{
    public function __construct(
        protected ResumeParserService $parser,
        protected SectionDetectorService $sectionDetector,
        protected ATSScorerService $scorer,
        protected KeywordAnalyzerService $keywordAnalyzer,
        protected ATSParseabilityChecker $parseabilityChecker,
        protected AIResumeAnalyzer $aiAnalyzer,
        protected ATSScoreValidator $scoreValidator
    ) {}

    /**
     * Show the resume checker page.
     */
    public function index()
    {
        return Inertia::render('ResumeChecker');
    }

    /**
     * Analyze the uploaded resume.
     */
    public function analyze(AnalyzeResumeRequest $request)
    {
        $file = $request->file('resume');
        $tempPath = null;

        if (! $file) {
            return back()->withErrors([
                'resume' => 'No file was uploaded.',
            ]);
        }

        try {
            // Ensure temp directory exists and is writable
            $tempDir = storage_path('app/temp');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            if (! is_writable($tempDir)) {
                throw new \RuntimeException('Storage directory is not writable. Please check permissions on storage/app/temp');
            }

            // Store file temporarily
            $tempPath = $file->store('temp', 'local');

            if (! $tempPath) {
                throw new \RuntimeException('Failed to store uploaded file. Storage operation returned null.');
            }

            $fullPath = storage_path('app/'.$tempPath);

            if (! file_exists($fullPath)) {
                throw new \RuntimeException("Failed to store uploaded file temporarily. Expected path: {$fullPath}");
            }

            // Get MIME type with fallback
            $mimeType = $file->getMimeType() ?: $file->getClientMimeType();
            if (! $mimeType) {
                // Fallback to extension-based detection
                $extension = strtolower($file->getClientOriginalExtension());
                $mimeType = match ($extension) {
                    'pdf' => 'application/pdf',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    default => 'application/pdf', // Default fallback
                };
            }

            // Parse the resume
            $parsedText = $this->parser->parse($fullPath, $mimeType);

            // Ensure all text data is valid UTF-8 for JSON encoding
            $parsedText = mb_convert_encoding($parsedText, 'UTF-8', 'UTF-8');
            if (! mb_check_encoding($parsedText, 'UTF-8')) {
                $parsedText = iconv('UTF-8', 'UTF-8//IGNORE', $parsedText) ?: '';
            }

            // Log FULL extracted text for debugging (save to file)
            $debugLogPath = storage_path('logs/resume_analysis_'.date('Y-m-d_H-i-s').'.txt');
            file_put_contents($debugLogPath, "=== RESUME ANALYSIS DEBUG LOG ===\n\n");
            file_put_contents($debugLogPath, "File: {$file->getClientOriginalName()}\n", FILE_APPEND);
            file_put_contents($debugLogPath, "MIME Type: {$mimeType}\n", FILE_APPEND);
            file_put_contents($debugLogPath, "Full Path: {$fullPath}\n", FILE_APPEND);
            file_put_contents($debugLogPath, 'Extracted Text Length: '.strlen($parsedText)." characters\n\n", FILE_APPEND);
            file_put_contents($debugLogPath, "=== FULL EXTRACTED TEXT ===\n\n", FILE_APPEND);
            file_put_contents($debugLogPath, $parsedText, FILE_APPEND);
            file_put_contents($debugLogPath, "\n\n", FILE_APPEND);

            Log::info('Resume text extracted and saved to debug log', [
                'debug_log_file' => basename($debugLogPath),
                'full_text_length' => strlen($parsedText),
                'first_2000_chars' => substr($parsedText, 0, 2000),
            ]);

            // Step 1: Run parseability checks (hard checks)
            $parseabilityResults = $this->parseabilityChecker->check($fullPath, $parsedText, $mimeType);

            // Log parseability results to debug file
            file_put_contents($debugLogPath, "=== PARSEABILITY CHECKS ===\n\n", FILE_APPEND);
            file_put_contents($debugLogPath, "Score: {$parseabilityResults['score']}/100\n", FILE_APPEND);
            file_put_contents($debugLogPath, "Confidence: {$parseabilityResults['confidence']}\n", FILE_APPEND);
            file_put_contents($debugLogPath, 'Critical Issues: '.count($parseabilityResults['critical_issues'] ?? [])."\n", FILE_APPEND);
            foreach ($parseabilityResults['critical_issues'] ?? [] as $issue) {
                file_put_contents($debugLogPath, "  - {$issue}\n", FILE_APPEND);
            }
            file_put_contents($debugLogPath, 'Warnings: '.count($parseabilityResults['warnings'] ?? [])."\n", FILE_APPEND);
            foreach ($parseabilityResults['warnings'] ?? [] as $warning) {
                file_put_contents($debugLogPath, "  - {$warning}\n", FILE_APPEND);
            }
            file_put_contents($debugLogPath, "\n=== DETAILED PARSEABILITY RESULTS ===\n\n", FILE_APPEND);
            file_put_contents($debugLogPath, json_encode($parseabilityResults['details'] ?? [], JSON_PRETTY_PRINT), FILE_APPEND);
            file_put_contents($debugLogPath, "\n\n", FILE_APPEND);

            Log::info('Parseability checks completed', [
                'parseability_score' => $parseabilityResults['score'],
                'confidence' => $parseabilityResults['confidence'],
                'critical_issues_count' => count($parseabilityResults['critical_issues'] ?? []),
                'warnings_count' => count($parseabilityResults['warnings'] ?? []),
                'debug_log_file' => basename($debugLogPath),
            ]);

            // Step 2: Run AI analysis (only if parseability > 0)
            $aiResults = null;
            $aiError = null;

            if ($parseabilityResults['score'] > 0) {
                try {
                    file_put_contents($debugLogPath, "=== AI ANALYSIS ===\n\n", FILE_APPEND);
                    file_put_contents($debugLogPath, 'Sending text to OpenAI (length: '.strlen($parsedText)." chars)\n", FILE_APPEND);
                    file_put_contents($debugLogPath, "Text sent to AI (first 2000 chars):\n".substr($parsedText, 0, 2000)."\n\n", FILE_APPEND);

                    $aiResults = $this->aiAnalyzer->analyze($parsedText);

                    if ($aiResults !== null) {
                        file_put_contents($debugLogPath, "=== AI ANALYSIS RESULTS ===\n\n", FILE_APPEND);
                        file_put_contents($debugLogPath, json_encode($aiResults, JSON_PRETTY_PRINT), FILE_APPEND);
                        file_put_contents($debugLogPath, "\n\n", FILE_APPEND);

                        Log::info('AI analysis completed', [
                            'ai_score' => $aiResults['overall_assessment']['ats_compatibility_score'] ?? 0,
                            'ai_confidence' => $aiResults['overall_assessment']['confidence_level'] ?? 'unknown',
                            'debug_log_file' => basename($debugLogPath),
                        ]);
                    }
                } catch (\Exception $e) {
                    $aiError = $e->getMessage();
                    file_put_contents($debugLogPath, "=== AI ANALYSIS ERROR ===\n\n", FILE_APPEND);
                    file_put_contents($debugLogPath, "Error: {$e->getMessage()}\n", FILE_APPEND);
                    file_put_contents($debugLogPath, 'Error Type: '.get_class($e)."\n\n", FILE_APPEND);
                    Log::error('AI analysis failed', [
                        'error' => $e->getMessage(),
                        'error_type' => get_class($e),
                        'debug_log_file' => basename($debugLogPath),
                    ]);
                }
            } else {
                file_put_contents($debugLogPath, "=== AI ANALYSIS SKIPPED ===\n\n", FILE_APPEND);
                file_put_contents($debugLogPath, "Reason: Low parseability score ({$parseabilityResults['score']})\n\n", FILE_APPEND);
                Log::info('Skipping AI analysis due to low parseability score');
            }

            // Step 3: Validate and combine results
            file_put_contents($debugLogPath, "=== SCORE VALIDATION ===\n\n", FILE_APPEND);
            file_put_contents($debugLogPath, "Combining parseability results with AI analysis...\n\n", FILE_APPEND);

            $finalAnalysis = $this->scoreValidator->validate($parseabilityResults, $aiResults);

            // If AI failed, add error message to response
            if ($aiResults === null && $parseabilityResults['score'] > 0) {
                $finalAnalysis['ai_unavailable'] = true;
                $finalAnalysis['ai_error_message'] = $aiError ?? 'AI analysis is temporarily unavailable. Please try again later.';
            }

            // Add filename to analysis
            $finalAnalysis['filename'] = $file->getClientOriginalName();

            // Log final scores to debug file
            file_put_contents($debugLogPath, "=== FINAL ANALYSIS RESULTS ===\n\n", FILE_APPEND);
            file_put_contents($debugLogPath, "Overall Score: {$finalAnalysis['overall_score']}/100\n", FILE_APPEND);
            file_put_contents($debugLogPath, "Confidence: {$finalAnalysis['confidence']}\n", FILE_APPEND);
            file_put_contents($debugLogPath, "Parseability Score: {$finalAnalysis['parseability_score']}/100\n", FILE_APPEND);
            file_put_contents($debugLogPath, "Format Score: {$finalAnalysis['format_score']}/100\n", FILE_APPEND);
            file_put_contents($debugLogPath, "Keyword Score: {$finalAnalysis['keyword_score']}/100\n", FILE_APPEND);
            file_put_contents($debugLogPath, "Contact Score: {$finalAnalysis['contact_score']}/100\n", FILE_APPEND);
            file_put_contents($debugLogPath, "Content Score: {$finalAnalysis['content_score']}/100\n", FILE_APPEND);
            file_put_contents($debugLogPath, "\n=== SCORE BREAKDOWN ===\n\n", FILE_APPEND);
            file_put_contents($debugLogPath, json_encode($finalAnalysis, JSON_PRETTY_PRINT), FILE_APPEND);
            file_put_contents($debugLogPath, "\n\n", FILE_APPEND);

            Log::info('Final analysis completed', [
                'overall_score' => $finalAnalysis['overall_score'],
                'confidence' => $finalAnalysis['confidence'],
                'parseability_score' => $finalAnalysis['parseability_score'],
                'format_score' => $finalAnalysis['format_score'],
                'keyword_score' => $finalAnalysis['keyword_score'],
                'contact_score' => $finalAnalysis['contact_score'],
                'content_score' => $finalAnalysis['content_score'],
                'ai_unavailable' => $finalAnalysis['ai_unavailable'] ?? false,
                'debug_log_file' => basename($debugLogPath),
            ]);

            return Inertia::render('ResumeChecker', [
                'analysis' => $finalAnalysis,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors([
                'resume' => $e->getMessage(),
            ]);
        } finally {
            // Critical: Always delete temp file
            if ($tempPath && Storage::disk('local')->exists($tempPath)) {
                Storage::disk('local')->delete($tempPath);
            }
        }
    }
}
