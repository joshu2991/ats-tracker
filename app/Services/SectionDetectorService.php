<?php

namespace App\Services;

/**
 * Legacy Section Detector Service
 *
 * NOTE: This service is legacy and NOT used in the actual application flow.
 * It is kept for backward compatibility with existing tests only.
 * The current application uses ATSParseabilityChecker for section and contact detection.
 */
class SectionDetectorService
{
    /**
     * Detect resume sections and contact information.
     *
     * @return array{sections: array<string, bool>, contact: array{email: string|null, phone: string|null, linkedin: string|null, github: string|null}}
     */
    public function detect(string $text): array
    {
        return [
            'sections' => $this->detectSections($text),
            'contact' => $this->detectContactInfo($text),
        ];
    }

    /**
     * Detect resume sections.
     *
     * @return array{experience: bool, education: bool, skills: bool}
     */
    protected function detectSections(string $text): array
    {
        $normalizedText = strtolower($text);

        // Experience section patterns (case insensitive)
        $experiencePatterns = [
            '/\b(work\s+)?experience\b/i',
            '/\bemployment\b/i',
            '/\bprofessional\s+experience\b/i',
            '/\bcareer\s+history\b/i',
            '/\bwork\s+history\b/i',
        ];

        // Education section patterns (case insensitive)
        $educationPatterns = [
            '/\beducation\b/i',
            '/\bacademic\b/i',
            '/\bqualifications?\b/i',
            '/\bcredentials?\b/i',
            '/\bdegrees?\b/i',
        ];

        // Skills section patterns (case insensitive)
        $skillsPatterns = [
            '/\b(technical\s+)?skills\b/i',
            '/\bcore\s+competencies\b/i',
            '/\bproficiencies\b/i',
            '/\bcompetencies\b/i',
            '/\bexpertise\b/i',
            '/\btechnologies?\b/i',
        ];

        $hasExperience = false;
        $hasEducation = false;
        $hasSkills = false;

        foreach ($experiencePatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $hasExperience = true;
                break;
            }
        }

        foreach ($educationPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $hasEducation = true;
                break;
            }
        }

        foreach ($skillsPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $hasSkills = true;
                break;
            }
        }

        return [
            'experience' => $hasExperience,
            'education' => $hasEducation,
            'skills' => $hasSkills,
        ];
    }

    /**
     * Detect contact information.
     *
     * @return array{email: string|null, phone: string|null, linkedin: string|null, github: string|null}
     */
    protected function detectContactInfo(string $text): array
    {
        $email = null;
        $phone = null;
        $linkedin = null;
        $github = null;

        // Email pattern
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $text, $matches)) {
            $email = $matches[0];
        }

        // Phone patterns (CA/US/MX formats)
        // Matches: (123) 456-7890, 123-456-7890, 123.456.7890, +1 123-456-7890, etc.
        $phonePatterns = [
            '/\+?1?\s*\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/', // US/CA format
            '/\+?52\s*\(?\d{2}\)?[\s.-]?\d{4}[\s.-]?\d{4}/', // MX format
        ];

        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $phone = trim($matches[0]);
                break;
            }
        }

        // LinkedIn URL pattern - also detect "LinkedIn" as text (acceptable format)
        if (preg_match('/(?:https?:\/\/)?(?:www\.)?linkedin\.com\/(?:in|pub|profile)\/[a-zA-Z0-9_-]+/i', $text, $matches)) {
            $linkedin = $matches[0];
        } elseif (preg_match('/\bLinkedIn\b/i', $text)) {
            // LinkedIn mentioned as text (acceptable - ATS can still parse it)
            $linkedin = 'LinkedIn'; // Indicate it exists but not as full URL
        }

        // GitHub URL pattern
        if (preg_match('/(?:https?:\/\/)?(?:www\.)?github\.com\/[a-zA-Z0-9_-]+/i', $text, $matches)) {
            $github = $matches[0];
        }

        return [
            'email' => $email,
            'phone' => $phone,
            'linkedin' => $linkedin,
            'github' => $github,
        ];
    }
}
