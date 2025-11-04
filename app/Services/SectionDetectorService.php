<?php

namespace App\Services;

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

        // Experience section patterns
        $experiencePatterns = [
            '/\b(work\s+)?experience\b/',
            '/\bemployment\b/',
            '/\bprofessional\s+experience\b/',
            '/\bcareer\s+history\b/',
            '/\bwork\s+history\b/',
        ];

        // Education section patterns
        $educationPatterns = [
            '/\beducation\b/',
            '/\bacademic\b/',
            '/\bqualifications?\b/',
            '/\bcredentials?\b/',
            '/\bdegrees?\b/',
        ];

        // Skills section patterns
        $skillsPatterns = [
            '/\b(technical\s+)?skills\b/',
            '/\bcore\s+competencies\b/',
            '/\bproficiencies\b/',
            '/\bcompetencies\b/',
            '/\bexpertise\b/',
            '/\btechnologies?\b/',
        ];

        $hasExperience = false;
        $hasEducation = false;
        $hasSkills = false;

        foreach ($experiencePatterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                $hasExperience = true;
                break;
            }
        }

        foreach ($educationPatterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                $hasEducation = true;
                break;
            }
        }

        foreach ($skillsPatterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
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

        // LinkedIn URL pattern
        if (preg_match('/(?:https?:\/\/)?(?:www\.)?linkedin\.com\/(?:in|pub|profile)\/[a-zA-Z0-9_-]+/i', $text, $matches)) {
            $linkedin = $matches[0];
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

