<?php

namespace App\Services;

/**
 * Constants for AI Resume Analyzer service.
 *
 * These values define OpenAI API configuration and limits.
 * DO NOT modify without understanding the impact on API costs and response quality.
 */
class AIResumeAnalyzerConstants
{
    // ========== API CONFIGURATION ==========
    /** OpenAI API base URI */
    public const API_BASE_URI = 'https://api.openai.com/v1/';

    /** OpenAI model to use for analysis */
    public const MODEL = 'gpt-4o-mini';

    /** API request timeout in seconds (default from config or 30) */
    public const DEFAULT_TIMEOUT = 30;

    // ========== TEXT PROCESSING LIMITS ==========
    /** Maximum characters to process from resume text (to stay within token limits) */
    public const MAX_RESUME_TEXT_CHARS = 8000;

    /** Maximum tokens in API response */
    public const MAX_RESPONSE_TOKENS = 2000;

    /** Temperature for API requests (controls randomness: 0.0-2.0, lower = more deterministic) */
    public const TEMPERATURE = 0.3;

    // ========== RETRY CONFIGURATION ==========
    /** Maximum number of retry attempts for API requests */
    public const MAX_RETRY_ATTEMPTS = 1;

    /** Backoff delay in seconds for retry attempts */
    public const RETRY_BACKOFF_SECONDS = 1;

    /** Retry attempt number that triggers backoff */
    public const RETRY_BACKOFF_ATTEMPT = 2;

    // ========== ERROR HANDLING ==========
    /** Maximum characters to include in error log content preview */
    public const ERROR_CONTENT_PREVIEW_LENGTH = 200;
}
