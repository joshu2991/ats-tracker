<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeResumeRequest;
use App\Services\AIResumeAnalyzer;
use App\Services\ATSParseabilityChecker;
use App\Services\ATSScoreValidator;
use App\Services\ResumeParserService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ResumeController extends Controller
{
    public function __construct(
        protected ResumeParserService $parser,
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

            // Step 1: Run parseability checks (hard checks)
            $parseabilityResults = $this->parseabilityChecker->check($fullPath, $parsedText, $mimeType);

            // Step 2: Run AI analysis (only if parseability > 0)
            $aiResults = null;
            $aiError = null;

            if ($parseabilityResults['score'] > 0) {
                try {
                    $aiResults = $this->aiAnalyzer->analyze($parsedText);
                } catch (\Exception $e) {
                    $aiError = $e->getMessage();
                    Log::error('AI analysis failed', [
                        'error' => $e->getMessage(),
                        'error_type' => get_class($e),
                    ]);
                }
            }

            // Step 3: Validate and combine results
            $finalAnalysis = $this->scoreValidator->validate($parseabilityResults, $aiResults);

            // If AI failed, add error message to response
            if ($aiResults === null && $parseabilityResults['score'] > 0) {
                $finalAnalysis['ai_unavailable'] = true;
                $finalAnalysis['ai_error_message'] = $aiError ?? 'AI analysis is temporarily unavailable. Please try again later.';
            }

            // Add filename to analysis
            $finalAnalysis['filename'] = $file->getClientOriginalName();

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
