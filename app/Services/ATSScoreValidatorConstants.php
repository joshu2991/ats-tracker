<?php

namespace App\Services;

/**
 * Constants for ATS Score Validator service.
 *
 * These values define scoring thresholds, weights, multipliers, and caps.
 * DO NOT modify without thorough testing to ensure scoring accuracy.
 */
class ATSScoreValidatorConstants
{
    // ========== SCORE THRESHOLDS ==========
    /** Maximum score value */
    public const MAX_SCORE = 100;

    /** Minimum score value */
    public const MIN_SCORE = 0;

    /** Score threshold for "good" parseability/AI scores */
    public const GOOD_SCORE_THRESHOLD = 70;

    /** Score threshold for critical issues */
    public const CRITICAL_SCORE_THRESHOLD = 30;

    /** Score threshold for warning level */
    public const WARNING_SCORE_THRESHOLD = 60;

    /** Score threshold for normalization (bump minimum) */
    public const NORMALIZATION_THRESHOLD = 50;

    /** Normalized minimum score when no critical issues */
    public const NORMALIZED_MIN_SCORE = 52;

    /** Score threshold for poor content */
    public const POOR_CONTENT_THRESHOLD = 40;

    /** Entry-level resume cap score */
    public const ENTRY_LEVEL_CAP_SCORE = 40;

    /** Format score cap when content is poor */
    public const FORMAT_GOOD_CONTENT_POOR_CAP = 50;

    // ========== SCORE WEIGHTS ==========
    /** Weight for parseability score in weighted average */
    public const WEIGHT_PARSEABILITY = 0.25;

    /** Weight for format score in weighted average */
    public const WEIGHT_FORMAT = 0.25;

    /** Weight for keyword score in weighted average */
    public const WEIGHT_KEYWORD = 0.25;

    /** Weight for contact score in weighted average */
    public const WEIGHT_CONTACT = 0.10;

    /** Weight for content score in weighted average */
    public const WEIGHT_CONTENT = 0.15;

    /** Weight for AI score when both scores are good */
    public const WEIGHT_AI_WHEN_GOOD = 0.5;

    /** Weight for parseability score when both scores are good */
    public const WEIGHT_PARSEABILITY_WHEN_GOOD = 0.5;

    // ========== SCORE MULTIPLIERS ==========
    /** Base alignment multiplier for ResumeWorded alignment */
    public const BASE_ALIGNMENT_MULTIPLIER = 0.92;

    /** Alignment multiplier with 1 critical issue */
    public const ALIGNMENT_ONE_CRITICAL = 0.90;

    /** Alignment multiplier with 2+ critical issues */
    public const ALIGNMENT_MULTIPLE_CRITICAL = 0.88;

    /** Contact score multiplier when contact doesn't exist */
    public const CONTACT_NO_EXISTS_MULTIPLIER = 0.3;

    /** Content score multiplier for long resumes */
    public const CONTENT_LONG_RESUME_MULTIPLIER = 0.8;

    // ========== PENALTIES ==========
    /** Penalty for scanned image (critical parsing issue) */
    public const PENALTY_SCANNED_IMAGE_MAX = 20;

    /** Penalty for date placeholders */
    public const PENALTY_DATE_PLACEHOLDERS = 25;

    /** Penalty for no dates at all */
    public const PENALTY_NO_DATES = 30;

    /** Penalty for missing name */
    public const PENALTY_NO_NAME = 20;

    /** Penalty for missing summary */
    public const PENALTY_NO_SUMMARY = 10;

    /** Penalty for very few bullets (< 5) */
    public const PENALTY_VERY_FEW_BULLETS = 25;

    /** Penalty for few bullets (< 8) */
    public const PENALTY_FEW_BULLETS = 20;

    /** Penalty for insufficient bullets */
    public const PENALTY_INSUFFICIENT_BULLETS = 15;

    /** Penalty for lack of metrics */
    public const PENALTY_NO_METRICS = 20;

    /** Penalty for tables detected */
    public const PENALTY_TABLES = 20;

    /** Penalty for multi-column layout */
    public const PENALTY_MULTI_COLUMN = 15;

    /** Penalty for poor content score */
    public const PENALTY_POOR_CONTENT = 10;

    // ========== KEYWORD SCORING ==========
    /** Base score for 20+ keywords */
    public const KEYWORD_SCORE_20_PLUS = 75;

    /** Base score for 15+ keywords */
    public const KEYWORD_SCORE_15_PLUS = 65;

    /** Base score for 10+ keywords */
    public const KEYWORD_SCORE_10_PLUS = 55;

    /** Base score for 5+ keywords */
    public const KEYWORD_SCORE_5_PLUS = 45;

    /** Base score for < 5 keywords */
    public const KEYWORD_SCORE_DEFAULT = 25;

    /** Bonus for high industry alignment */
    public const KEYWORD_BONUS_HIGH_ALIGNMENT = 10;

    /** Bonus for medium industry alignment */
    public const KEYWORD_BONUS_MEDIUM_ALIGNMENT = 5;

    // ========== CONTACT SCORING ==========
    /** Points for email found */
    public const CONTACT_EMAIL_POINTS = 30;

    /** Bonus for email in top location */
    public const CONTACT_EMAIL_TOP_BONUS = 20;

    /** Bonus for email in middle location */
    public const CONTACT_EMAIL_MIDDLE_BONUS = 10;

    /** Points for phone found */
    public const CONTACT_PHONE_POINTS = 20;

    /** Bonus for phone in top location */
    public const CONTACT_PHONE_TOP_BONUS = 10;

    /** Bonus for phone in middle location */
    public const CONTACT_PHONE_MIDDLE_BONUS = 5;

    /** Points for LinkedIn found */
    public const CONTACT_LINKEDIN_POINTS = 15;

    /** Deduction for LinkedIn incorrect format */
    public const CONTACT_LINKEDIN_FORMAT_DEDUCTION = 2;

    /** Points for GitHub found */
    public const CONTACT_GITHUB_POINTS = 10;

    /** Points for location/city found */
    public const CONTACT_LOCATION_POINTS = 5;

    // ========== CONTENT SCORING ==========
    /** Points for using action verbs */
    public const CONTENT_ACTION_VERBS_POINTS = 25;

    /** Bonus for 5+ action verb examples */
    public const CONTENT_ACTION_VERBS_5_PLUS_BONUS = 10;

    /** Bonus for 3+ action verb examples */
    public const CONTENT_ACTION_VERBS_3_PLUS_BONUS = 5;

    /** Points for quantifiable achievements */
    public const CONTENT_ACHIEVEMENTS_POINTS = 25;

    /** Bonus for 3+ achievement examples */
    public const CONTENT_ACHIEVEMENTS_3_PLUS_BONUS = 10;

    /** Bonus for 2+ achievement examples */
    public const CONTENT_ACHIEVEMENTS_2_PLUS_BONUS = 5;

    /** Points for appropriate length */
    public const CONTENT_LENGTH_POINTS = 20;

    /** Partial credit for length close to optimal (300-400 words) */
    public const CONTENT_LENGTH_CLOSE_PARTIAL = 10;

    /** Partial credit for length slightly long (800-1000 words) */
    public const CONTENT_LENGTH_LONG_PARTIAL = 10;

    /** Points for bullet points */
    public const CONTENT_BULLETS_POINTS = 20;

    // ========== THIN RESUME THRESHOLDS ==========
    /** Minimum word count threshold for thin resume detection */
    public const THIN_RESUME_WORD_COUNT = 400;

    /** Minimum achievement count threshold for thin resume detection */
    public const THIN_RESUME_ACHIEVEMENT_COUNT = 3;

    // ========== BASIC ANALYSIS ==========
    /** Bonus to add to parseability score for basic analysis (when AI unavailable) */
    public const BASIC_ANALYSIS_BONUS = 20;
}
