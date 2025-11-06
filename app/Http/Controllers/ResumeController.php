<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeResumeRequest;
use App\Models\Visitor;
use App\Services\AIResumeAnalyzer;
use App\Services\ATSParseabilityChecker;
use App\Services\ATSScoreValidator;
use App\Services\ResumeParserService;
use App\Services\SEOService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ResumeController extends Controller
{
    public function __construct(
        protected ResumeParserService $parser,
        protected ATSParseabilityChecker $parseabilityChecker,
        protected AIResumeAnalyzer $aiAnalyzer,
        protected ATSScoreValidator $scoreValidator,
        protected SEOService $seoService
    ) {}

    /**
     * Show the resume checker page.
     */
    public function index()
    {
        // Track visitor
        $this->trackVisitor('home');

        // Check if user wants to clear session (e.g., "analyze another" button)
        if (request()->boolean('clear')) {
            Session::forget('resume_analysis');
            Session::forget('resume_analysis_timestamp');

            return Inertia::render('ResumeChecker', [
                'analysis' => null,
            ]);
        }

        // Check if there's analysis data in session
        // Only load if we're preserving from a POST redirect (preserve query param)
        // OR if it's a refresh (referrer matches current route)
        $referer = request()->header('Referer');
        $currentUrl = route('resume-checker');
        $isRefresh = $referer && str_contains($referer, $currentUrl);
        $shouldLoadFromSession = request()->boolean('preserve') || $isRefresh;

        $analysis = null;
        if ($shouldLoadFromSession) {
            $analysis = Session::get('resume_analysis');
            $timestamp = Session::get('resume_analysis_timestamp');

            // Check if analysis has expired (60 minutes)
            if ($analysis && $timestamp) {
                $expired = (now()->timestamp - $timestamp) > (60 * 60); // 60 minutes
                if ($expired) {
                    Session::forget('resume_analysis');
                    Session::forget('resume_analysis_timestamp');
                    $analysis = null;
                }
            }
        }

        // Generate page-specific SEO data
        $seoData = $this->seoService->forPage('resume-checker');

        return Inertia::render('ResumeChecker', [
            'analysis' => $analysis,
            'seo' => $seoData->toArray(),
        ]);
    }

    /**
     * Analyze the uploaded resume.
     */
    public function analyze(AnalyzeResumeRequest $request)
    {
        // Clear any existing analysis from session when starting new analysis
        Session::forget('resume_analysis');

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

            // Store analysis in session (expires in 60 minutes)
            Session::put('resume_analysis', $finalAnalysis);
            Session::put('resume_analysis_timestamp', now()->timestamp);

            // Track visitor (after successful analysis)
            $this->trackVisitor('analyze');

            // Redirect to GET route with preserve flag to avoid POST redirect issues
            return redirect()->route('resume-checker', ['preserve' => true])->with('success', 'Resume analyzed successfully!');
        } catch (\Exception $e) {
            // Log errors for security monitoring
            Log::warning('Resume analysis failed', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);

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

    /**
     * Track visitor page visit.
     */
    protected function trackVisitor(string $pageType): void
    {
        try {
            Visitor::create([
                'page_type' => $pageType,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the request if tracking fails
            Log::warning('Failed to track visitor', [
                'error' => $e->getMessage(),
                'page_type' => $pageType,
            ]);
        }
    }
}
