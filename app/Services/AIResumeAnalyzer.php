<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class AIResumeAnalyzer
{
    protected Client $client;

    protected ?string $apiKey;

    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
        $this->timeout = config('services.openai.timeout', 30);

        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer '.($this->apiKey ?? ''),
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
        $promptTemplate = $this->loadPromptTemplate();

        // Replace placeholder with actual resume text
        return str_replace('{RESUME_TEXT}', $resumeText, $promptTemplate);
    }

    /**
     * Load the ATS analysis prompt template.
     */
    protected function loadPromptTemplate(): string
    {
        $templatePath = resource_path('prompts/ats-analysis-prompt.txt');

        if (! file_exists($templatePath)) {
            Log::error('ATS analysis prompt template not found', ['path' => $templatePath]);
            throw new \RuntimeException('ATS analysis prompt template file not found');
        }

        $template = file_get_contents($templatePath);

        if ($template === false) {
            Log::error('Failed to read ATS analysis prompt template', ['path' => $templatePath]);
            throw new \RuntimeException('Failed to read ATS analysis prompt template file');
        }

        return $template;
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
