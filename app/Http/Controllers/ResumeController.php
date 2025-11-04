<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeResumeRequest;
use App\Services\ATSScorerService;
use App\Services\KeywordAnalyzerService;
use App\Services\ResumeParserService;
use App\Services\SectionDetectorService;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ResumeController extends Controller
{
    public function __construct(
        protected ResumeParserService $parser,
        protected SectionDetectorService $sectionDetector,
        protected ATSScorerService $scorer,
        protected KeywordAnalyzerService $keywordAnalyzer
    ) {
    }

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

            // Detect sections and contact info
            $detection = $this->sectionDetector->detect($parsedText);

            // Calculate scores
            $formatScore = $this->scorer->calculateFormatScore($parsedText, $detection['sections']);
            $contactScore = $this->scorer->calculateContactScore($detection['contact']);
            $keywordAnalysis = $this->keywordAnalyzer->analyze($parsedText);
            $lengthScore = $this->scorer->calculateLengthScore($parsedText);

            // Calculate total ATS score (0-100)
            $totalScore = $formatScore['score'] + $contactScore['score'] + $keywordAnalysis['score'] + $lengthScore['score'];

            // Ensure all text data is valid UTF-8 for JSON encoding
            $parsedText = mb_convert_encoding($parsedText, 'UTF-8', 'UTF-8');
            if (! mb_check_encoding($parsedText, 'UTF-8')) {
                $parsedText = iconv('UTF-8', 'UTF-8//IGNORE', $parsedText) ?: '';
            }

            // Prepare analysis data
            $analysisData = [
                'filename' => $file->getClientOriginalName(),
                'parsedText' => $parsedText,
                'sections' => $detection['sections'],
                'contact' => $detection['contact'],
                'formatScore' => $formatScore,
                'contactScore' => $contactScore,
                'keywordAnalysis' => $keywordAnalysis,
                'lengthScore' => $lengthScore,
                'totalScore' => $totalScore,
            ];

            // Generate suggestions
            $suggestions = $this->scorer->generateSuggestions($analysisData);
            $analysisData['suggestions'] = $suggestions;

            return Inertia::render('ResumeChecker', [
                'analysis' => $analysisData,
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
