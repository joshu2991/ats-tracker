<?php

namespace App\Services;

/**
 * Constants for ATS Parseability Checker scoring and thresholds.
 *
 * These values were carefully calibrated through manual testing with real resumes
 * to ensure accurate ATS compatibility scoring. DO NOT modify without thorough testing.
 */
class ATSParseabilityCheckerConstants
{
    // ========== SCORING ==========
    /** Starting score for parseability check (stricter than 100 for ResumeWorded alignment) */
    public const STARTING_SCORE = 90;

    /** Minimum score (score cannot go below this) */
    public const MIN_SCORE = 0;

    // ========== PENALTIES ==========
    /** Penalty for scanned image PDF (critical issue) */
    public const PENALTY_SCANNED_IMAGE = 30;

    /** Penalty for table detection */
    public const PENALTY_TABLES = 30;

    /** Penalty for multi-column layout */
    public const PENALTY_MULTI_COLUMN = 25;

    /** Penalty for missing contact info (critical) */
    public const PENALTY_NO_CONTACT = 25;

    /** Penalty for contact not in ideal location */
    public const PENALTY_CONTACT_BAD_LOCATION = 15;

    /** Penalty for date placeholders (critical) */
    public const PENALTY_DATE_PLACEHOLDERS = 20;

    /** Penalty for no dates found (critical) */
    public const PENALTY_NO_DATES = 25;

    /** Penalty for missing name (critical) */
    public const PENALTY_NO_NAME = 20;

    /** Penalty for missing summary */
    public const PENALTY_NO_SUMMARY = 10;

    /** Penalty for lack of quantifiable metrics */
    public const PENALTY_NO_METRICS = 15;

    // ========== LENGTH PENALTIES ==========
    /** Penalty for short resume (< 400 words) */
    public const PENALTY_SHORT_RESUME = 15;

    /** Penalty for long resume (> 800 words) */
    public const PENALTY_LONG_RESUME = 12;

    /** Penalty for page count issues */
    public const PENALTY_PAGE_COUNT = 10;

    /** Additional penalty for experienced candidates with short resumes */
    public const PENALTY_EXPERIENCED_SHORT_RESUME = 10;

    // ========== BULLET POINT PENALTIES ==========
    /** Penalty for very few bullets (< 5) */
    public const PENALTY_VERY_FEW_BULLETS = 20;

    /** Penalty for few bullets (< 8) */
    public const PENALTY_FEW_BULLETS = 15;

    /** Penalty for insufficient bullets (default) */
    public const PENALTY_INSUFFICIENT_BULLETS = 10;

    /** Additional penalty for very few experience bullets (< 3) */
    public const PENALTY_VERY_FEW_EXPERIENCE_BULLETS = 10;

    /** Additional penalty for few experience bullets (< 5) */
    public const PENALTY_FEW_EXPERIENCE_BULLETS = 5;

    // ========== WORD COUNT THRESHOLDS ==========
    /** Minimum optimal word count */
    public const WORD_COUNT_MIN = 400;

    /** Maximum optimal word count */
    public const WORD_COUNT_MAX = 800;

    /** Words per page estimate for page count calculation */
    public const WORDS_PER_PAGE = 400;

    // ========== PAGE COUNT THRESHOLDS ==========
    /** Minimum optimal page count */
    public const PAGE_COUNT_MIN = 1;

    /** Maximum optimal page count */
    public const PAGE_COUNT_MAX = 2;

    // ========== TEXT EXTRACTION THRESHOLDS ==========
    /** Minimum text length for single page PDF (below this = likely scanned) */
    public const TEXT_LENGTH_MIN_SINGLE_PAGE = 20;

    /** Minimum text length for multi-page PDF (below this = likely scanned) */
    public const TEXT_LENGTH_MIN_MULTI_PAGE = 50;

    // ========== CONTACT INFO THRESHOLDS ==========
    /** Characters to check for contact info location (expanded from 200 for PDF header cases) */
    public const CONTACT_CHECK_CHARS = 300;

    /** Lines to check for contact info (for PDF header detection) */
    public const CONTACT_CHECK_LINES = 10;

    /** Characters to check for name detection */
    public const NAME_CHECK_CHARS = 200;

    /** Characters for name fallback check */
    public const NAME_FALLBACK_CHARS = 100;

    // ========== DATE DETECTION THRESHOLDS ==========
    /** Minimum number of dates required (start and end dates for work experience) */
    public const MIN_DATE_COUNT = 2;

    // ========== EXPERIENCE LEVEL THRESHOLDS ==========
    /** Years of experience to be considered "experienced" */
    public const EXPERIENCED_YEARS = 5;

    /** Estimated years if 3+ positions found */
    public const ESTIMATED_YEARS_MANY_POSITIONS = 5;

    /** Estimated years if 2+ positions found */
    public const ESTIMATED_YEARS_FEW_POSITIONS = 3;

    /** Minimum positions to estimate 5 years */
    public const MIN_POSITIONS_FOR_5_YEARS = 3;

    /** Minimum positions to estimate 3 years */
    public const MIN_POSITIONS_FOR_3_YEARS = 2;

    // ========== SUMMARY DETECTION THRESHOLDS ==========
    /** Characters to check after summary header */
    public const SUMMARY_CHECK_CHARS = 300;

    /** Minimum words required after summary header */
    public const SUMMARY_MIN_WORDS = 20;

    // ========== BULLET POINT THRESHOLDS ==========
    /** Minimum optimal bullet points for experienced candidates */
    public const BULLETS_MIN_OPTIMAL = 12;

    /** Maximum optimal bullet points */
    public const BULLETS_MAX_OPTIMAL = 20;

    /** Minimum experience bullets required */
    public const BULLETS_EXPERIENCE_MIN = 8;

    /** Threshold for very few bullets */
    public const BULLETS_VERY_FEW = 5;

    /** Threshold for few bullets */
    public const BULLETS_FEW = 8;

    /** Threshold for very few experience bullets */
    public const BULLETS_EXPERIENCE_VERY_FEW = 3;

    /** Threshold for few experience bullets */
    public const BULLETS_EXPERIENCE_FEW = 5;

    /** Minimum bullets to trigger fallback detection */
    public const BULLETS_FALLBACK_THRESHOLD = 5;

    /** Minimum implicit bullets to count */
    public const BULLETS_IMPLICIT_MIN = 3;

    /** Minimum experience bullets to trigger implicit detection */
    public const BULLETS_EXPERIENCE_IMPLICIT_THRESHOLD = 5;

    // ========== BULLET POINT DETECTION THRESHOLDS ==========
    /** Maximum line length to check for bullet-only lines */
    public const BULLET_LINE_MAX_LENGTH = 3;

    /** Minimum content length to count as bullet content */
    public const BULLET_CONTENT_MIN_LENGTH = 10;

    /** Maximum look-ahead lines for bullet detection */
    public const BULLET_LOOKAHEAD_LINES = 3;

    /** Maximum character position for bullet in line (for fallback detection) */
    public const BULLET_MAX_POSITION = 5;

    /** Minimum line length for implicit bullet detection */
    public const BULLET_IMPLICIT_MIN_LENGTH = 20;

    /** Maximum line length for implicit bullet detection */
    public const BULLET_IMPLICIT_MAX_LENGTH = 300;

    /** Minimum line length for short list item pattern */
    public const BULLET_SHORT_ITEM_MIN_LENGTH = 10;

    /** Maximum line length for short list item pattern */
    public const BULLET_SHORT_ITEM_MAX_LENGTH = 60;

    /** Minimum words for short list item pattern */
    public const BULLET_SHORT_ITEM_MIN_WORDS = 2;

    /** Maximum words for short list item pattern */
    public const BULLET_SHORT_ITEM_MAX_WORDS = 4;

    /** Minimum title case percentage for short list items */
    public const BULLET_SHORT_ITEM_TITLE_CASE_RATIO = 0.5;

    /** Maximum line length for job title check */
    public const JOB_TITLE_MAX_LENGTH = 80;

    // ========== TABLE DETECTION THRESHOLDS ==========
    /** Minimum lines with table patterns to detect tables */
    public const TABLE_MIN_PATTERNS = 3;

    /** Minimum columns to consider a line as table-like */
    public const TABLE_MIN_COLUMNS = 3;

    // ========== MULTI-COLUMN DETECTION THRESHOLDS ==========
    /** Maximum lines to check for multi-column patterns */
    public const MULTI_COLUMN_CHECK_LINES = 50;

    /** Short line length threshold for multi-column detection */
    public const MULTI_COLUMN_SHORT_LINE = 30;

    /** Long line length threshold for multi-column detection */
    public const MULTI_COLUMN_LONG_LINE = 80;

    /** Length difference threshold for inconsistent pattern */
    public const MULTI_COLUMN_LENGTH_DIFF = 60;

    /** Minimum suspicious patterns to detect multi-column */
    public const MULTI_COLUMN_MIN_PATTERNS = 10;

    /** High confidence threshold for multi-column */
    public const MULTI_COLUMN_HIGH_CONFIDENCE = 20;

    // ========== METRICS DETECTION THRESHOLDS ==========
    /** Minimum quantifiable metrics required */
    public const MIN_METRICS_COUNT = 3;

    // ========== CONFIDENCE LEVEL THRESHOLDS ==========
    /** Maximum issues for high confidence */
    public const CONFIDENCE_HIGH_MAX_ISSUES = 0;

    /** Maximum issues for medium confidence */
    public const CONFIDENCE_MEDIUM_MAX_ISSUES = 2;

    // ========== NAME DETECTION THRESHOLDS ==========
    /** Maximum line length to consider as name */
    public const NAME_MAX_LINE_LENGTH = 50;

    /** Minimum words in name */
    public const NAME_MIN_WORDS = 2;

    /** Maximum words in name */
    public const NAME_MAX_WORDS = 4;

    /** Maximum name length for all-caps check */
    public const NAME_MAX_LENGTH = 30;

    /** Maximum lines to check for name */
    public const NAME_CHECK_LINES = 5;
}
