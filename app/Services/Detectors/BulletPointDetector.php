<?php

namespace App\Services\Detectors;

use App\Services\ATSParseabilityCheckerConstants;

/**
 * Bullet Point Detector
 *
 * Detects and counts bullet points in resumes using a multi-pass algorithm.
 *
 */
class BulletPointDetector
{
    /**
     * Count bullet points in resume by section.
     *
     * @return array{
     *     count: int,
     *     is_optimal: bool,
     *     by_section: array{experience: int, projects: int, other: int},
     *     sections_found: array<string>,
     *     potential_non_standard_bullets: int,
     *     non_standard_by_section: array{experience: int, projects: int, other: int}
     * }
     */
    public function countBulletPoints(string $text): array
    {
        // Initialize tracking variables
        $count = 0;
        $lines = explode("\n", $text);
        $processedLines = [];
        $bulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $sectionsFound = [];

        // Get detection patterns and characters
        $bulletPatterns = $this->getBulletPatterns();
        $bulletChars = $this->getBulletCharacters();
        $experiencePatterns = $this->getExperienceSectionPatterns();
        $projectsPatterns = $this->getProjectsSectionPatterns();

        // First pass: detect bullets on separate lines
        $firstPassResult = $this->detectBulletsFirstPass(
            $lines,
            $bulletChars,
            $experiencePatterns,
            $projectsPatterns,
            $processedLines,
            $sectionsFound
        );
        $count += $firstPassResult['count'];
        $bulletsBySection = $this->mergeSectionCounts($bulletsBySection, $firstPassResult['by_section']);
        $processedLines = array_merge($processedLines, $firstPassResult['processed_lines']);

        // Second pass: detect bullets inline with content
        $secondPassResult = $this->detectBulletsSecondPass(
            $lines,
            $bulletPatterns,
            $experiencePatterns,
            $projectsPatterns,
            $processedLines,
            $sectionsFound
        );
        $count += $secondPassResult['count'];
        $bulletsBySection = $this->mergeSectionCounts($bulletsBySection, $secondPassResult['by_section']);
        $processedLines = array_merge($processedLines, $secondPassResult['processed_lines']);

        // Fallback pass: more permissive detection
        if ($count < ATSParseabilityCheckerConstants::BULLETS_FALLBACK_THRESHOLD) {
            $fallbackResult = $this->detectBulletsFallbackPass(
                $lines,
                $bulletChars,
                $experiencePatterns,
                $projectsPatterns,
                $processedLines,
                $sectionsFound
            );
            $count += $fallbackResult['count'];
            $bulletsBySection = $this->mergeSectionCounts($bulletsBySection, $fallbackResult['by_section']);
            $processedLines = array_merge($processedLines, $fallbackResult['processed_lines']);

            // Numbered lists detection
            if ($count < ATSParseabilityCheckerConstants::BULLETS_FALLBACK_THRESHOLD) {
                $numberedResult = $this->detectNumberedLists(
                    $lines,
                    $experiencePatterns,
                    $projectsPatterns,
                    $processedLines
                );
                $count += $numberedResult['count'];
                $bulletsBySection = $this->mergeSectionCounts($bulletsBySection, $numberedResult['by_section']);
                $processedLines = array_merge($processedLines, $numberedResult['processed_lines']);
            }

            // Implicit bullet detection
            if ($count < ATSParseabilityCheckerConstants::BULLETS_FALLBACK_THRESHOLD) {
                $implicitResult = $this->detectImplicitBullets($lines, $processedLines);
                $count += $implicitResult['count'];
            }
        }

        // Get final section counts
        $experienceBullets = $bulletsBySection['experience'];
        $projectsBullets = $bulletsBySection['projects'];
        $otherBullets = $bulletsBySection['other'];

        // Final implicit detection for experience section
        if (in_array('experience', $sectionsFound, true) && $experienceBullets < ATSParseabilityCheckerConstants::BULLETS_EXPERIENCE_IMPLICIT_THRESHOLD) {
            $finalImplicitResult = $this->detectImplicitExperienceBullets(
                $lines,
                $experiencePatterns,
                $projectsPatterns,
                $processedLines,
                $bulletsBySection
            );
            $count += $finalImplicitResult['count'];
            $bulletsBySection = $this->mergeSectionCounts($bulletsBySection, $finalImplicitResult['by_section']);
            $processedLines = array_merge($processedLines, $finalImplicitResult['processed_lines']);

            // Update final counts
            $experienceBullets = $bulletsBySection['experience'];
            $projectsBullets = $bulletsBySection['projects'];
            $otherBullets = $bulletsBySection['other'];
        }

        // Detect non-standard bullets
        $nonStandardResult = $this->detectNonStandardBullets(
            $lines,
            $experiencePatterns,
            $projectsPatterns,
            $processedLines
        );

        // Calculate optimal status
        $isOptimal = $count >= ATSParseabilityCheckerConstants::BULLETS_MIN_OPTIMAL && $experienceBullets >= ATSParseabilityCheckerConstants::BULLETS_EXPERIENCE_MIN;

        return [
            'count' => $count,
            'is_optimal' => $isOptimal,
            'by_section' => [
                'experience' => $experienceBullets,
                'projects' => $projectsBullets,
                'other' => $otherBullets,
            ],
            'sections_found' => array_values(array_unique($sectionsFound)),
            'potential_non_standard_bullets' => $nonStandardResult['count'],
            'non_standard_by_section' => $nonStandardResult['by_section'],
        ];
    }

    /**
     * Get bullet detection regex patterns.
     *
     * @return array<string>
     */
    protected function getBulletPatterns(): array
    {
        return [
            // Standard bullet characters (various Unicode bullets) - with or without space after
            '/^\s*[•◦▪▫◘◙◉○●]\s*/m', // Bullet characters at start of line (space optional)
            '/^\s*[•◦▪▫◘◙◉○●]/m', // Bullet characters at start of line (no space required)
            // Numbers as bullets: "1. ", "2) ", "3- ", etc.
            '/^\s*\d+[.)-]\s+/m', // Number followed by period, parenthesis, or dash
            // Checkmarks and check icons: ✓, ✔, ☑, ✅
            '/^\s*[✓✔☑✅]\s*/u', // Checkmarks at start of line (space optional)
            '/^\s*[✓✔☑✅]/u', // Checkmarks at start of line (no space required)
            // Dash or asterisk at start of line
            '/^\s*[-*]\s+/m',
            // Lowercase 'o' as bullet at start of line
            '/^\s*o\s+/m',
            // Arrows: →, →, ⇒, ➜, ➤
            '/^\s*[→⇒➜➤]\s*/u', // Arrows at start of line (space optional)
            '/^\s*[→⇒➜➤]/u', // Arrows at start of line (no space required)
            // Square brackets: [ ], □, ■
            '/^\s*[□■]\s*/u', // Square bullets (space optional)
            '/^\s*[□■]/u', // Square bullets (no space required)
            // Other common bullet alternatives
            '/^\s*[▪▫]\s*/u', // Square bullets variants (space optional)
            '/^\s*[▪▫]/u', // Square bullets variants (no space required)
        ];
    }

    /**
     * Get all bullet characters including non-standard ones from PDF encoding.
     *
     * @return array<string>
     */
    protected function getBulletCharacters(): array
    {
        $bulletChars = ['•', '◦', '▪', '▫', '◘', '◙', '◉', '○', '●', '✓', '✔', '☑', '✅', '→', '⇒', '➜', '➤', '□', '■', '-', '*'];

        // Add non-standard bullet (from hex ef82b7 - common in PDFs due to encoding issues)
        $nonStandardBullet = hex2bin('ef82b7') ?: '';
        if (! empty($nonStandardBullet) && ! in_array($nonStandardBullet, $bulletChars, true)) {
            $bulletChars[] = $nonStandardBullet;
        }

        return $bulletChars;
    }

    /**
     * Get experience section detection patterns.
     *
     * @return array<string>
     */
    protected function getExperienceSectionPatterns(): array
    {
        return ['/^(professional\s+)?experience|work\s+experience|work\s+history|employment|career\s+history/i'];
    }

    /**
     * Get projects section detection patterns.
     *
     * @return array<string>
     */
    protected function getProjectsSectionPatterns(): array
    {
        return [
            '/^projects?/i',  // PROJECTS or PROJECT
            '/^portfolio/i',  // Portfolio
            '/^personal\s+projects/i',  // Personal Projects
        ];
    }

    /**
     * Update section tracking based on line content.
     *
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $sectionsFound
     * @return array{section: string, sections_found: array<string>}
     */
    protected function updateSectionTracking(
        string $line,
        array $experiencePatterns,
        array $projectsPatterns,
        string $currentSection,
        array $sectionsFound
    ): array {
        // Check Experience first
        $isExperienceSection = false;
        foreach ($experiencePatterns as $pattern) {
            if (preg_match($pattern, $line)) {
                $currentSection = 'experience';
                $isExperienceSection = true;
                if (! in_array('experience', $sectionsFound, true)) {
                    $sectionsFound[] = 'experience';
                }
                break;
            }
        }

        // Check Projects (only if not Experience)
        if (! $isExperienceSection) {
            foreach ($projectsPatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $currentSection = 'projects';
                    if (! in_array('projects', $sectionsFound, true)) {
                        $sectionsFound[] = 'projects';
                    }
                    break;
                }
            }
        }

        return [
            'section' => $currentSection,
            'sections_found' => $sectionsFound,
        ];
    }

    /**
     * Check if line is only a bullet character (with various detection methods).
     *
     * @param  array<string>  $bulletChars
     * @param  array<string>  $lines
     */
    protected function isLineOnlyBullet(string $line, int $lineLength, array $bulletChars, array $lines, int $index): bool
    {
        if ($lineLength > ATSParseabilityCheckerConstants::BULLET_LINE_MAX_LENGTH) {
            return false;
        }

        // Method 1: Direct character comparison
        foreach ($bulletChars as $char) {
            if ($line === $char) {
                return true;
            }
        }

        // Method 2: Regex pattern for bullet with optional whitespace
        foreach ($bulletChars as $char) {
            $pattern = '/^[\s]*'.preg_quote($char, '/').'[\s]*$/u';
            if (preg_match($pattern, $line)) {
                return true;
            }
        }

        // Method 3: Check if line contains any bullet character (fallback)
        if ($lineLength <= ATSParseabilityCheckerConstants::BULLET_LINE_MAX_LENGTH) {
            foreach ($bulletChars as $char) {
                if (mb_strpos($line, $char) !== false) {
                    return true;
                }
            }
        }

        // Method 4: Pattern-based detection - if line is very short (1-3 chars) and next line has content,
        // it's likely a bullet on a separate line (common resume formatting pattern)
        if ($lineLength <= ATSParseabilityCheckerConstants::BULLET_LINE_MAX_LENGTH) {
            $nextLineIndex = $index + 1;
            $lookAheadLines = ATSParseabilityCheckerConstants::BULLET_LOOKAHEAD_LINES;

            // Check if any of the next few lines have substantial content
            for ($checkIndex = $nextLineIndex; $checkIndex <= $index + $lookAheadLines && $checkIndex < count($lines); $checkIndex++) {
                if (isset($lines[$checkIndex])) {
                    $checkLine = trim($lines[$checkIndex]);
                    // If line has substantial content (10+ chars), treat current line as bullet
                    if (! empty($checkLine) && mb_strlen($checkLine) >= ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                        // Additional check: current line should not be a header or date
                        if (! $this->isLineHeaderOrDate($line)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if line is a header or date.
     */
    protected function isLineHeaderOrDate(string $line): bool
    {
        return preg_match('/^(PROFESSIONAL|EXPERIENCE|EDUCATION|PROJECTS|SKILLS|SUMMARY|LANGUAGES|CERTIFICATIONS|LEADERSHIP|WORK\s+HISTORY)/i', $line) ||
               preg_match('/\d{4}/', $line) ||
               preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i', $line);
    }

    /**
     * Find next content line after bullet-only line.
     *
     * @param  array<string>  $lines
     * @param  array<string>  $processedLines
     * @return array{found: bool, line: string|null, index: int|null}
     */
    protected function findNextContentLine(array $lines, int $startIndex, array $processedLines): array
    {
        $nextLineIndex = $startIndex + 1;
        $foundContent = false;

        while (isset($lines[$nextLineIndex]) && ! $foundContent) {
            $nextLine = trim($lines[$nextLineIndex]);

            // If next line has content, count it as a bullet point
            if (! empty($nextLine) && mb_strlen($nextLine) >= ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                // Skip if next line is already processed
                if (! in_array($nextLine, $processedLines, true)) {
                    return [
                        'found' => true,
                        'line' => $nextLine,
                        'index' => $nextLineIndex,
                    ];
                }
            }
            $nextLineIndex++;
        }

        return [
            'found' => false,
            'line' => null,
            'index' => null,
        ];
    }

    /**
     * First pass: detect bullets on separate lines (bullet character alone, content on next line).
     *
     * @param  array<string>  $lines
     * @param  array<string>  $bulletChars
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $processedLines
     * @param  array<string>  $sectionsFound
     * @return array{count: int, by_section: array{experience: int, projects: int, other: int}, processed_lines: array<string>, sections_found: array<string>}
     */
    protected function detectBulletsFirstPass(
        array $lines,
        array $bulletChars,
        array $experiencePatterns,
        array $projectsPatterns,
        array $processedLines,
        array $sectionsFound
    ): array {
        $count = 0;
        $bulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $currentSection = 'other';

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);

            // Skip empty lines
            if (empty($trimmedLine)) {
                continue;
            }

            // Update section tracking
            $sectionResult = $this->updateSectionTracking(
                $trimmedLine,
                $experiencePatterns,
                $projectsPatterns,
                $currentSection,
                $sectionsFound
            );
            $currentSection = $sectionResult['section'];
            $sectionsFound = $sectionResult['sections_found'];

            // Check if line is ONLY a bullet character
            $lineLength = mb_strlen($trimmedLine);
            $isOnlyBullet = $this->isLineOnlyBullet($trimmedLine, $lineLength, $bulletChars, $lines, $index);

            // If line is ONLY a bullet character, check next lines for content
            if ($isOnlyBullet) {
                $contentResult = $this->findNextContentLine($lines, $index, $processedLines);

                if ($contentResult['found'] && $contentResult['line'] !== null) {
                    $count++;
                    $processedLines[] = $contentResult['line'];
                    $bulletsBySection[$currentSection]++;
                }
            }
        }

        return [
            'count' => $count,
            'by_section' => $bulletsBySection,
            'processed_lines' => $processedLines,
            'sections_found' => $sectionsFound,
        ];
    }

    /**
     * Second pass: detect bullets inline with content (bullet + text on same line).
     *
     * @param  array<string>  $lines
     * @param  array<string>  $bulletPatterns
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $processedLines
     * @param  array<string>  $sectionsFound
     * @return array{count: int, by_section: array{experience: int, projects: int, other: int}, processed_lines: array<string>, sections_found: array<string>}
     */
    protected function detectBulletsSecondPass(
        array $lines,
        array $bulletPatterns,
        array $experiencePatterns,
        array $projectsPatterns,
        array $processedLines,
        array $sectionsFound
    ): array {
        $count = 0;
        $bulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $currentSection = 'other';

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) {
                continue;
            }

            // Update section tracking
            $sectionResult = $this->updateSectionTracking(
                $trimmedLine,
                $experiencePatterns,
                $projectsPatterns,
                $currentSection,
                $sectionsFound
            );
            $currentSection = $sectionResult['section'];
            $sectionsFound = $sectionResult['sections_found'];

            // Skip if already processed
            if (in_array($trimmedLine, $processedLines, true)) {
                continue;
            }

            // Check if current line starts with a bullet character or pattern
            $isBulletLine = false;
            foreach ($bulletPatterns as $pattern) {
                if (preg_match($pattern, $trimmedLine)) {
                    $isBulletLine = true;
                    break;
                }
            }

            // If current line is a bullet WITH content (bullet + text), count it
            if ($isBulletLine) {
                // If bullet line itself has content (after bullet), count it
                if (strlen($trimmedLine) >= ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                    $count++;
                    $processedLines[] = $trimmedLine;
                    $bulletsBySection[$currentSection]++;

                    continue;
                }
            }

            // Skip very short lines (likely headers or dates) - but only if not a bullet
            if (strlen($trimmedLine) < ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH && ! $isBulletLine) {
                continue;
            }

            // Check if line starts with a bullet character or pattern (standard check)
            foreach ($bulletPatterns as $pattern) {
                if (preg_match($pattern, $trimmedLine)) {
                    $count++;
                    $processedLines[] = $trimmedLine;
                    $bulletsBySection[$currentSection]++;
                    break; // Count each line only once
                }
            }
        }

        return [
            'count' => $count,
            'by_section' => $bulletsBySection,
            'processed_lines' => $processedLines,
            'sections_found' => $sectionsFound,
        ];
    }

    /**
     * Fallback pass: more permissive detection for indented or formatted bullets.
     *
     * @param  array<string>  $lines
     * @param  array<string>  $bulletChars
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $processedLines
     * @param  array<string>  $sectionsFound
     * @return array{count: int, by_section: array{experience: int, projects: int, other: int}, processed_lines: array<string>, sections_found: array<string>}
     */
    protected function detectBulletsFallbackPass(
        array $lines,
        array $bulletChars,
        array $experiencePatterns,
        array $projectsPatterns,
        array $processedLines,
        array $sectionsFound
    ): array {
        $count = 0;
        $bulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $currentSection = 'other';

        // Remove non-standard bullet from list for this pass (use standard set)
        $bulletChars = ['•', '◦', '▪', '▫', '◘', '◙', '◉', '○', '●', '✓', '✔', '☑', '✅', '→', '⇒', '➜', '➤', '□', '■'];

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) {
                continue;
            }

            // Update section tracking
            $sectionResult = $this->updateSectionTracking(
                $trimmedLine,
                $experiencePatterns,
                $projectsPatterns,
                $currentSection,
                $sectionsFound
            );
            $currentSection = $sectionResult['section'];
            $sectionsFound = $sectionResult['sections_found'];

            // Skip if already processed
            if (in_array($trimmedLine, $processedLines, true)) {
                continue;
            }

            // Check if line contains a bullet character
            foreach ($bulletChars as $char) {
                if (str_contains($trimmedLine, $char)) {
                    // Additional check: make sure it's not in the middle of a word
                    $charPos = strpos($trimmedLine, $char);
                    // If bullet is in first 5 characters, it's likely a bullet point
                    if ($charPos !== false && $charPos < ATSParseabilityCheckerConstants::BULLET_MAX_POSITION) {
                        // If line is just a bullet (short), check next lines for content
                        if (strlen($trimmedLine) < ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                            $nextLineIndex = $index + 1;
                            $foundContent = false;
                            while (isset($lines[$nextLineIndex]) && ! $foundContent) {
                                $nextLine = trim($lines[$nextLineIndex]);
                                if (! empty($nextLine) && strlen($nextLine) >= ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                                    $count++;
                                    $processedLines[] = $nextLine;
                                    $bulletsBySection[$currentSection]++;
                                    $foundContent = true;
                                    break 2; // Break both loops
                                }
                                $nextLineIndex++;
                                // Limit search to next 3 lines to avoid going too far
                                if ($nextLineIndex > $index + ATSParseabilityCheckerConstants::BULLET_LOOKAHEAD_LINES) {
                                    break;
                                }
                            }
                        } else {
                            // Line has bullet and content
                            $count++;
                            $processedLines[] = $trimmedLine;
                            $bulletsBySection[$currentSection]++;
                            break; // Count each line only once
                        }
                    }
                }
            }
        }

        return [
            'count' => $count,
            'by_section' => $bulletsBySection,
            'processed_lines' => $processedLines,
            'sections_found' => $sectionsFound,
        ];
    }

    /**
     * Detect numbered lists (1. 2. 3. pattern).
     *
     * @param  array<string>  $lines
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $processedLines
     * @return array{count: int, by_section: array{experience: int, projects: int, other: int}, processed_lines: array<string>}
     */
    protected function detectNumberedLists(
        array $lines,
        array $experiencePatterns,
        array $projectsPatterns,
        array $processedLines
    ): array {
        $count = 0;
        $bulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $currentSection = 'other';

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) {
                continue;
            }

            // Update section tracking
            $sectionResult = $this->updateSectionTracking(
                $trimmedLine,
                $experiencePatterns,
                $projectsPatterns,
                $currentSection,
                []
            );
            $currentSection = $sectionResult['section'];

            if (strlen($trimmedLine) < ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                continue;
            }

            // Skip if already processed
            if (in_array($trimmedLine, $processedLines, true)) {
                continue;
            }

            // Check for numbered list pattern: starts with number followed by period/parenthesis/dash
            if (preg_match('/^\d+[.)-]\s+/', $trimmedLine)) {
                $count++;
                $processedLines[] = $trimmedLine;
                $bulletsBySection[$currentSection]++;
            }
        }

        return [
            'count' => $count,
            'by_section' => $bulletsBySection,
            'processed_lines' => $processedLines,
        ];
    }

    /**
     * Detect implicit bullets (short capitalized lines and action verb lines).
     *
     * @param  array<string>  $lines
     * @param  array<string>  $processedLines
     * @return array{count: int}
     */
    protected function detectImplicitBullets(array $lines, array $processedLines): array
    {
        $implicitBulletCount = 0;
        $consecutiveShortLines = 0;
        $actionVerbLines = 0;
        $actionVerbs = $this->getActionVerbsList();

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Skip if already processed
            if (in_array($line, $processedLines, true)) {
                continue;
            }

            $lineLength = strlen($line);
            $words = explode(' ', $line);
            $wordCount = count($words);

            // Pattern 1: Short lines that look like list items (skills, etc.)
            if ($lineLength >= ATSParseabilityCheckerConstants::BULLET_SHORT_ITEM_MIN_LENGTH &&
                $lineLength <= ATSParseabilityCheckerConstants::BULLET_SHORT_ITEM_MAX_LENGTH &&
                $wordCount >= ATSParseabilityCheckerConstants::BULLET_SHORT_ITEM_MIN_WORDS &&
                $wordCount <= ATSParseabilityCheckerConstants::BULLET_SHORT_ITEM_MAX_WORDS) {
                // Check if line is mostly title case or capitalized
                $titleCaseWords = 0;
                foreach ($words as $word) {
                    $cleanWord = preg_replace('/[^a-zA-Z]/', '', $word);
                    if (! empty($cleanWord) && (ucfirst(strtolower($cleanWord)) === $cleanWord || ctype_upper($cleanWord))) {
                        $titleCaseWords++;
                    }
                }
                // If 50%+ of words are title case, likely a list item
                if ($titleCaseWords >= ($wordCount * ATSParseabilityCheckerConstants::BULLET_SHORT_ITEM_TITLE_CASE_RATIO)) {
                    $implicitBulletCount++;
                    $consecutiveShortLines++;

                    continue;
                }
            }

            // Pattern 2: Action verb lines (likely experience bullets)
            $firstWord = strtolower(explode(' ', $line)[0]);
            if (in_array($firstWord, $actionVerbs, true) && $lineLength >= ATSParseabilityCheckerConstants::BULLET_IMPLICIT_MIN_LENGTH) {
                $actionVerbLines++;

                continue;
            }
        }

        // If we found many implicit bullets, add them to count
        // But be conservative - only count if we're confident they're list items
        $additionalCount = 0;
        if ($implicitBulletCount >= ATSParseabilityCheckerConstants::BULLETS_IMPLICIT_MIN || $actionVerbLines >= ATSParseabilityCheckerConstants::BULLETS_IMPLICIT_MIN) {
            // Count implicit bullets but be conservative
            $additionalCount = min($implicitBulletCount, $actionVerbLines > 0 ? max($actionVerbLines, $implicitBulletCount) : $implicitBulletCount);
            // Only add if we're confident (at least 3 items)
            if ($additionalCount >= ATSParseabilityCheckerConstants::BULLETS_IMPLICIT_MIN) {
                // Don't add to count here - this is just for detection, not counting
                // The actual counting happens in the main method
            }
        }

        return [
            'count' => $additionalCount,
        ];
    }

    /**
     * Detect implicit bullets in experience section (action verb lines).
     *
     * @param  array<string>  $lines
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $processedLines
     * @param  array{experience: int, projects: int, other: int}  $bulletsBySection
     * @return array{count: int, by_section: array{experience: int, projects: int, other: int}, processed_lines: array<string>}
     */
    protected function detectImplicitExperienceBullets(
        array $lines,
        array $experiencePatterns,
        array $projectsPatterns,
        array $processedLines,
        array $bulletsBySection
    ): array {
        $implicitBulletCount = 0;
        $currentSection = 'other';
        $actionVerbs = $this->getExtendedActionVerbsList();

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) {
                continue;
            }

            // Update section tracking
            $isExperienceHeader = false;
            foreach ($experiencePatterns as $pattern) {
                if (preg_match($pattern, $trimmedLine)) {
                    $currentSection = 'experience';
                    $isExperienceHeader = true;
                    break;
                }
            }

            if (! $isExperienceHeader) {
                foreach ($projectsPatterns as $pattern) {
                    if (preg_match($pattern, $trimmedLine)) {
                        $currentSection = 'projects';
                        break;
                    }
                }
            }

            // Skip section headers, dates, and very short/long lines
            $lineLength = mb_strlen($trimmedLine);
            if ($lineLength < ATSParseabilityCheckerConstants::BULLET_IMPLICIT_MIN_LENGTH || $lineLength > ATSParseabilityCheckerConstants::BULLET_IMPLICIT_MAX_LENGTH) {
                continue;
            }

            // Skip if already processed as a bullet
            if (in_array($trimmedLine, $processedLines, true)) {
                continue;
            }

            // Skip if it's a header, date, or company name
            if ($this->isLineHeaderDateOrCompany($trimmedLine)) {
                continue;
            }

            // Check if line starts with an action verb (likely a bullet point)
            $firstWord = strtolower(explode(' ', $trimmedLine)[0]);
            $firstWord = preg_replace('/[^a-z]/', '', $firstWord); // Remove punctuation

            // Also check for third person singular forms (Prepares -> prepare, Executes -> execute, Processes -> process)
            $baseWord = rtrim($firstWord, 's');
            $baseWordEs = rtrim($firstWord, 'es'); // For processes -> process

            if (in_array($firstWord, $actionVerbs, true) ||
                in_array($baseWord, $actionVerbs, true) ||
                in_array($baseWordEs, $actionVerbs, true)) {
                // Additional check: line should not be a job title or company name
                $isJobTitle = preg_match('/^(Senior|Junior|Lead|Manager|Developer|Engineer|Analyst|Specialist|Coordinator|Director|VP|President|CEO|CTO|Full Stack|Software|Web|Accountant)\s+/i', $trimmedLine) &&
                             $lineLength < ATSParseabilityCheckerConstants::JOB_TITLE_MAX_LENGTH;

                if (! $isJobTitle && $currentSection === 'experience') {
                    $implicitBulletCount++;

                    // Also add to processed lines to avoid double counting
                    if (! in_array($trimmedLine, $processedLines, true)) {
                        $processedLines[] = $trimmedLine;
                        $bulletsBySection[$currentSection]++;
                    }
                }
            }
        }

        return [
            'count' => $implicitBulletCount,
            'by_section' => $bulletsBySection,
            'processed_lines' => $processedLines,
        ];
    }

    /**
     * Detect non-standard bullets that weren't detected by other methods.
     *
     * @param  array<string>  $lines
     * @param  array<string>  $experiencePatterns
     * @param  array<string>  $projectsPatterns
     * @param  array<string>  $processedLines
     * @return array{count: int, by_section: array{experience: int, projects: int, other: int}}
     */
    protected function detectNonStandardBullets(
        array $lines,
        array $experiencePatterns,
        array $projectsPatterns,
        array $processedLines
    ): array {
        $potentialNonStandardBullets = 0;
        $nonStandardBulletsBySection = ['experience' => 0, 'projects' => 0, 'other' => 0];
        $currentSection = 'other';

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) {
                continue;
            }

            // Update section tracking
            $isExperienceSection = false;
            foreach ($experiencePatterns as $pattern) {
                if (preg_match($pattern, $trimmedLine)) {
                    $currentSection = 'experience';
                    $isExperienceSection = true;
                    break;
                }
            }

            if (! $isExperienceSection) {
                foreach ($projectsPatterns as $pattern) {
                    if (preg_match($pattern, $trimmedLine)) {
                        $currentSection = 'projects';
                        break;
                    }
                }
            }

            // Check if this line might be a non-standard bullet (short line followed by content)
            $lineLength = mb_strlen($trimmedLine);
            if ($lineLength >= 1 && $lineLength <= ATSParseabilityCheckerConstants::BULLET_LINE_MAX_LENGTH) {
                // Skip if this line was already processed as a bullet
                $wasProcessed = false;

                // Check if the line itself was processed
                if (in_array($trimmedLine, $processedLines, true)) {
                    $wasProcessed = true;
                }

                // Also check if the next line (content) was already processed
                if (! $wasProcessed) {
                    $nextLineIndex = $index + 1;
                    $lookAheadCheck = ATSParseabilityCheckerConstants::BULLET_LOOKAHEAD_LINES;
                    for ($checkIdx = $nextLineIndex; $checkIdx <= $index + $lookAheadCheck && $checkIdx < count($lines); $checkIdx++) {
                        if (isset($lines[$checkIdx])) {
                            $nextCheckLine = trim($lines[$checkIdx]);
                            if (! empty($nextCheckLine) && mb_strlen($nextCheckLine) >= ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                                if (in_array($nextCheckLine, $processedLines, true)) {
                                    $wasProcessed = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                // Only count as non-standard if it wasn't processed AND next line has content
                if (! $wasProcessed) {
                    $nextLineIndex = $index + 1;
                    $lookAheadLines = ATSParseabilityCheckerConstants::BULLET_LOOKAHEAD_LINES;

                    for ($checkIndex = $nextLineIndex; $checkIndex <= $index + $lookAheadLines && $checkIndex < count($lines); $checkIndex++) {
                        if (isset($lines[$checkIndex])) {
                            $checkLine = trim($lines[$checkIndex]);
                            if (! empty($checkLine) && mb_strlen($checkLine) >= ATSParseabilityCheckerConstants::BULLET_CONTENT_MIN_LENGTH) {
                                // Check if it's not a header or date
                                if (! $this->isLineHeaderOrDate($trimmedLine)) {
                                    $potentialNonStandardBullets++;
                                    $nonStandardBulletsBySection[$currentSection]++;
                                    break 2; // Break both loops
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'count' => $potentialNonStandardBullets,
            'by_section' => $nonStandardBulletsBySection,
        ];
    }

    /**
     * Check if line is a header, date, or company name.
     */
    protected function isLineHeaderDateOrCompany(string $line): bool
    {
        return preg_match('/^(PROFESSIONAL|EXPERIENCE|EDUCATION|PROJECTS|SKILLS|SUMMARY|LANGUAGES|CERTIFICATIONS|LEADERSHIP|WORK\s+HISTORY|Highlights|Lead|Senior|Staff|Accountant|Branch|Cashier)\s+(Accountant|Developer|Engineer|Manager|Analyst|Specialist|Coordinator|Director|VP|President|CEO|CTO|Service|Specialist)/i', $line) ||
               preg_match('/\d{4}\s+to\s+(Current|Present|\d{4})/i', $line) ||
               preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\.?\s+\d{4}/i', $line) ||
               preg_match('/^(November|September|March|January|February|April|May|June|July|August|October|December)\s+\d{4}/i', $line) ||
               preg_match('/^\d{2}\/\d{4}/i', $line) || // 04/2020 format
               preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+\s+\|/i', $line) || // Company Name | Location pattern
               preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+\s+\d{4}/i', $line) || // Company Name 2024 pattern
               preg_match('/^Company\s+Name/i', $line); // Company Name placeholder
    }

    /**
     * Get basic action verbs list.
     *
     * @return array<string>
     */
    protected function getActionVerbsList(): array
    {
        return [
            'managed', 'developed', 'led', 'created', 'built', 'implemented', 'designed', 'improved',
            'launched', 'optimized', 'delivered', 'achieved', 'increased', 'reduced', 'established',
            'coordinated', 'executed', 'transformed', 'enhanced', 'streamlined', 'automated',
            'architected', 'deployed', 'integrated', 'migrated', 'scaled', 'maintained', 'collaborated',
            'mentored', 'trained', 'supervised', 'analyzed', 'researched', 'evaluated',
            'performed', 'prepared', 'monitored', 'reviewed', 'provided', 'compiled',
            'filed', 'reconciled', 'posted', 'verified', 'acted', 'tracked', 'identified', 'stayed',
        ];
    }

    /**
     * Get extended action verbs list (for implicit detection).
     *
     * @return array<string>
     */
    protected function getExtendedActionVerbsList(): array
    {
        return [
            'managed', 'develop', 'led', 'created', 'built', 'implemented', 'designed', 'improved',
            'launched', 'optimized', 'delivered', 'achieved', 'increased', 'reduced', 'established',
            'coordinated', 'execute', 'transformed', 'enhanced', 'streamlined', 'automated',
            'architected', 'deployed', 'integrated', 'migrated', 'scaled', 'maintained', 'collaborated',
            'mentored', 'trained', 'supervised', 'analyzed', 'researched', 'evaluated',
            'performed', 'prepare', 'monitored', 'reviewed', 'provided', 'compiled',
            'filed', 'reconciled', 'posted', 'verified', 'acted', 'tracked', 'identified', 'stayed',
            'tested', 'maintained', 'monitored', 'prepared', 'compiled', 'executed', 'managed', 'reviewed',
            'strengthened', 'overlooked', 'assessed', 'ensured', 'process', 'organized', 'completed',
            'handled', 'assisted', 'supported', 'improved', 'optimized', 'streamlined', 'enhanced', 'expanded',
            'initiated', 'facilitated', 'generated', 'produced', 'administered', 'coordinated', 'directed', 'guided',
            'influenced', 'negotiated', 'persuaded', 'presented', 'promoted', 'recommended', 'resolved', 'secured',
            'solved', 'standardized', 'structured', 'synthesized', 'systematized', 'validated', 'verified', 'wrote',
            'authored', 'composed', 'constructed', 'cultivated', 'demonstrated', 'documented', 'educated', 'established',
            'evaluated', 'examined', 'explored', 'formulated', 'fostered', 'generated', 'implemented', 'improved',
            'innovated', 'inspired', 'instructed', 'introduced', 'investigated', 'leveraged', 'maximized', 'minimized',
            'modernized', 'motivated', 'navigated', 'negotiated', 'orchestrated', 'organized', 'overhauled', 'pioneered',
            'planned', 'positioned', 'prioritized', 'produced', 'programmed', 'projected', 'promoted', 'proposed',
            'qualified', 'quantified', 'rationalized', 'realized', 'rebuilt', 'recommended', 'reconciled', 'recruited',
            'redesigned', 'reduced', 'refined', 'regulated', 'reinforced', 'reorganized', 'repaired', 'replaced',
            'reported', 'represented', 'researched', 'resolved', 'restored', 'restructured', 'retained', 'revamped',
            'reviewed', 'revised', 'saved', 'scheduled', 'secured', 'selected', 'separated', 'served', 'simplified',
            'solved', 'sorted', 'spearheaded', 'specialized', 'specified', 'standardized', 'started', 'streamlined',
            'strengthened', 'structured', 'studied', 'submitted', 'substituted', 'succeeded', 'suggested', 'summarized',
            'supervised', 'supplied', 'supported', 'sustained', 'synthesized', 'systematized', 'targeted', 'taught',
            'teamed', 'tested', 'trained', 'transferred', 'transformed', 'translated', 'troubleshot', 'turned',
            'unified', 'united', 'updated', 'upgraded', 'utilized', 'validated', 'valued', 'verified', 'volunteered',
            'won', 'wrote',
        ];
    }

    /**
     * Merge section counts from two arrays.
     *
     * @param  array{experience: int, projects: int, other: int}  $base
     * @param  array{experience: int, projects: int, other: int}  $additional
     * @return array{experience: int, projects: int, other: int}
     */
    protected function mergeSectionCounts(array $base, array $additional): array
    {
        return [
            'experience' => $base['experience'] + $additional['experience'],
            'projects' => $base['projects'] + $additional['projects'],
            'other' => $base['other'] + $additional['other'],
        ];
    }
}
