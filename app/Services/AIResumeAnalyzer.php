<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class AIResumeAnalyzer
{
    protected Client $client;

    protected string $apiKey;

    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
        $this->timeout = config('services.openai.timeout', 30);

        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * Analyze resume using OpenAI GPT-4o-mini.
     *
     * @return array<string, mixed>|null
     */
    public function analyze(string $resumeText): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('OpenAI API key not configured');

            return null; // Return null instead of throwing exception - handled by controller
        }

        // Truncate resume text if too long (to stay within token limits)
        $maxChars = 8000;
        if (strlen($resumeText) > $maxChars) {
            $resumeText = substr($resumeText, 0, $maxChars).'... [truncated]';
            Log::info('Resume text truncated for OpenAI analysis', ['original_length' => strlen($resumeText)]);
        }

        $prompt = $this->buildPrompt($resumeText);

        try {
            $response = $this->makeApiRequest($prompt);

            return $this->parseResponse($response);
        } catch (\Exception $e) {
            Log::error('OpenAI API analysis failed', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);

            // Return null to indicate failure (handled by controller)
            return null;
        }
    }

    /**
     * Build the prompt for OpenAI.
     */
    protected function buildPrompt(string $resumeText): string
    {
        return <<<PROMPT
You are an ATS (Applicant Tracking System) compatibility analyzer with expertise in how major ATS systems like Taleo, Greenhouse, Workday, and Lever parse resumes.

IMPORTANT CONTEXT ABOUT ATS BEHAVIOR:
- ATS systems fail to parse tables correctly - text gets scrambled or lost
- Multi-column layouts cause text to be read in wrong order
- Headers and footers are often ignored completely
- Images, charts, and graphics are not readable
- Standard section headers are critical: Experience, Education, Skills, not creative names
- Keyword matching is literal and case-insensitive but requires exact words
- Contact information must be in plain text in the first 100-150 words
- File formats: DOCX parses better than PDF in most systems
- Special characters and unusual fonts can cause parsing errors

SCORING CALIBRATION RULES:
- Accept section header variations: "Technical Skills" = "Skills" = "TECHNICAL SKILLS", "Work Experience" = "Experience" = "PROFESSIONAL EXPERIENCE", "Professional Experience" = "Experience"
- "Professional Summary" = "Summary" = "PROFESSIONAL SUMMARY" = "Profile Overview" = "PROFILE OVERVIEW" - DO NOT flag "Professional Summary", "Profile Overview", or "PROFILE OVERVIEW" as missing Summary section
- DO NOT flag "Technical Skills" or "TECHNICAL SKILLS" as missing Skills section - these are valid variations
- DO NOT flag "Professional Summary" or "PROFESSIONAL SUMMARY" as missing Summary section - these are valid variations
- Colon-separated format like "Front End: HTML, React" is GOOD formatting, NOT a table - do not flag as table issue
- Skills section with categories like "Languages:", "Frameworks & Libraries:", "Tools & Databases:" is GOOD formatting - do NOT suggest reformatting or clearer separation
- DO NOT generate contradictory warnings like "well-formatted but could benefit from clearer separation" - if it's well-formatted, it's acceptable
- Contact in first 300 characters (not just 200) is acceptable for scoring
- Standard resume with clear sections should score 55-70 (good structure)
- Excellent resumes score 75-85, not 80-90
- Only major penalties for actual parsing problems: image PDFs, real tables (3+ columns with borders), unreadable text
- Be lenient on style variations - strict on format problems that break parsing
- A resume with minor style issues but good structure should score 55-65, not 70-85
- Be conservative with scoring - use strict industry standards

CONTENT QUALITY REQUIREMENTS (penalize heavily if missing):
- Quantifiable Achievements: Each role should have at least 1-2 metrics (percentages, numbers, $ amounts)
  * "Developed a chatbot" WITHOUT metrics: -10 points per role
  * Example good: "Reduced query time by 40%" 
  * Example bad: "Developed a chatbot" (no impact shown)
- Resume Length & Detail:
  * 346 words with only 1 job = -15 points (too thin)
  * Fewer than 12 bullet points total in experience = -10 points
  * Each role should have 3-5 detailed bullets with metrics
- For mid-level developers (2-3 years):
  * Should have 400-700 words
  * Should have 12-20 bullets across all roles
  * Should show progression/impact with numbers
- A resume with good format but no metrics should score 35-50, NOT 70+.

Analyze this resume for ATS compatibility:

{$resumeText}

Provide a JSON analysis based on documented ATS parsing behavior from industry research:

{
  "format_analysis": {
    "score": 0-100,
        "section_headers_found": ["Professional Experience", "Education", "Technical Skills", "Professional Summary", "Profile Overview"],
    "section_headers_missing": [],
    "non_standard_headers": ["My Journey instead of Experience"],
    "has_appropriate_structure": true/false,
    "structure_issues": ["list specific formatting problems"],
    "dates_found": true/false,
    "dates_valid": true/false,
    "date_placeholders_found": false,
    "date_issues": ["list date-related issues if any"]
  },
  "keyword_analysis": {
    "technical_keywords_found": ["Laravel", "React", "AWS"],
    "total_unique_keywords": 15,
    "keyword_density": "appropriate/too_sparse/keyword_stuffing",
    "missing_common_keywords": ["Docker", "CI/CD"],
    "industry_alignment": "high/medium/low"
  },
  "contact_information": {
    "email_found": true/false,
    "email_location": "top/middle/bottom/not_found",
    "phone_found": true/false,
    "phone_location": "top/middle/bottom/not_found",
    "linkedin_found": true/false,
    "linkedin_format_correct": true/false,
    "github_found": true/false,
    "location_city_found": true/false
  },
  "content_quality": {
    "uses_action_verbs": true/false,
    "action_verb_examples": ["Led", "Built", "Optimized"],
    "quantifiable_achievements": true/false,
    "achievement_examples": ["reduced time by 80%", "increased signups by 100"],
    "appropriate_length": true/false,
    "estimated_word_count": 650,
    "has_bullet_points": true/false,
    "bullet_point_count": 15,
    "bullet_points_optimal": true/false,
    "quantifiable_metrics_count": 5,
    "needs_more_metrics": true/false
  },
  "ats_red_flags": [
    "Contact info appears to be in header/footer - will likely be missed by ATS",
    "Skills section uses table format - may not parse correctly",
    "Creative section header 'My Superpowers' instead of 'Skills'",
    "No dates found in work experience or education - ATS systems require dates",
    "Date placeholders like '20XX' found instead of actual dates - ATS cannot parse these"
  ],
  "critical_fixes_required": [
    "Move email and phone to main body text at top of resume",
    "Replace skills table with simple bullet list",
    "Change 'My Journey' header to 'Professional Experience'",
    "Add actual dates (e.g., '2023', 'Jan 2023', '2023-2024') to work experience and education - replace any placeholders like '20XX'"
  ],
  "recommended_improvements": [
    "Add 5-8 more industry-standard technical keywords",
    "Include LinkedIn profile URL in contact section",
    "Add more quantifiable achievements with numbers",
    "Use stronger action verbs: Architected, Spearheaded, Orchestrated"
  ],
  "overall_assessment": {
    "ats_compatibility_score": 0-100,
    "likelihood_to_pass_ats": "high/medium/low",
    "confidence_level": "high/medium/low",
    "primary_concern": "brief description of biggest issue"
  }
}

Base your analysis strictly on documented ATS parsing behavior and industry best practices. Be specific about WHY something is a problem, not just that it is. Reference which ATS systems specifically struggle with certain formats when relevant.
PROMPT;
    }

    /**
     * Make API request to OpenAI with retry logic.
     *
     * @return array<string, mixed>
     */
    protected function makeApiRequest(string $prompt): array
    {
        $maxRetries = 1;
        $attempt = 0;

        while ($attempt <= $maxRetries) {
            try {
                $response = $this->client->post('chat/completions', [
                    'json' => [
                        'model' => 'gpt-4o-mini',
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => $prompt,
                            ],
                        ],
                        'temperature' => 0.3,
                        'response_format' => ['type' => 'json_object'],
                        'max_tokens' => 2000,
                    ],
                ]);

                $body = json_decode($response->getBody()->getContents(), true);

                if (! isset($body['choices'][0]['message']['content'])) {
                    throw new \RuntimeException('Invalid response format from OpenAI API');
                }

                return $body;
            } catch (GuzzleException $e) {
                $attempt++;

                if ($attempt > $maxRetries) {
                    // Exponential backoff on retry
                    if ($attempt === 2) {
                        sleep(1);
                    }

                    throw $e;
                }

                // Log retry attempt
                Log::warning('OpenAI API request failed, retrying...', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new \RuntimeException('Failed to get response from OpenAI API after retries');
    }

    /**
     * Parse and validate OpenAI response.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function parseResponse(array $response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';

        if (empty($content)) {
            throw new \RuntimeException('Empty response from OpenAI API');
        }

        $analysis = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse OpenAI JSON response', [
                'json_error' => json_last_error_msg(),
                'content_preview' => substr($content, 0, 200),
            ]);
            throw new \RuntimeException('Invalid JSON response from OpenAI API');
        }

        // Validate structure matches expected format
        $this->validateResponseStructure($analysis);

        return $analysis;
    }

    /**
     * Validate that response structure matches expected format.
     *
     * @param  array<string, mixed>  $analysis
     */
    protected function validateResponseStructure(array $analysis): void
    {
        $requiredKeys = [
            'format_analysis',
            'keyword_analysis',
            'contact_information',
            'content_quality',
            'ats_red_flags',
            'critical_fixes_required',
            'recommended_improvements',
            'overall_assessment',
        ];

        foreach ($requiredKeys as $key) {
            if (! isset($analysis[$key])) {
                Log::warning("Missing key in OpenAI response: {$key}");
            }
        }
    }
}
