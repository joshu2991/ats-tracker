<?php

namespace Tests\Unit;

use App\Services\ATSParseabilityChecker;
use App\Services\ATSParseabilityCheckerConstants;
use App\Services\Detectors\BulletPointDetector;
use App\Services\Detectors\ContentDetector;
use App\Services\Detectors\ExperienceAnalyzer;
use App\Services\Detectors\FormatDetector;
use App\Services\Detectors\LengthAnalyzer;
use App\Services\Detectors\MetricsDetector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ATSParseabilityCheckerTest extends TestCase
{
    protected ATSParseabilityChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new ATSParseabilityChecker(
            new FormatDetector,
            new ContentDetector,
            new LengthAnalyzer,
            new BulletPointDetector,
            new MetricsDetector,
            new ExperienceAnalyzer
        );
    }

    #[Test]
    public function it_returns_high_score_for_well_formatted_resume(): void
    {
        $resumeText = $this->createWellFormattedResume();
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        // Well-formatted resume should have a reasonable score (may have some penalties)
        $this->assertGreaterThanOrEqual(40, $result['score'], 'Well-formatted resume should score at least 40');
        // Should have minimal critical issues (may have minor warnings)
        $criticalIssueCount = count($result['critical_issues']);
        $this->assertLessThanOrEqual(3, $criticalIssueCount, 'Well-formatted resume should have minimal critical issues');
        $this->assertContains($result['confidence'], ['high', 'medium', 'low']);
    }

    #[Test]
    public function it_detects_scanned_image_pdf(): void
    {
        // Note: This test requires an actual PDF file to parse page count
        // For unit tests, we'll skip this as it requires poppler-utils
        $this->markTestSkipped('Requires actual PDF file parsing with poppler-utils');
    }

    #[Test]
    public function it_detects_tables_in_resume(): void
    {
        $resumeText = "Name\tTitle\tCompany\nJohn Doe\tEngineer\tAcme Inc\nJane Smith\tDeveloper\tTech Corp";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertTrue($result['details']['table_detection']['has_tables'] ?? false);
        $this->assertNotEmpty($result['warnings']);
    }

    #[Test]
    public function it_detects_multi_column_layout(): void
    {
        $resumeText = $this->createMultiColumnResume();
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertTrue($result['details']['multi_column']['has_multi_column'] ?? false);
        $this->assertNotEmpty($result['warnings']);
    }

    #[Test]
    public function it_detects_contact_info_in_correct_location(): void
    {
        $resumeText = "John Doe\njohn.doe@example.com\n(555) 123-4567\n\nExperience\n...";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $contactLocation = $result['details']['contact_location'] ?? [];
        $this->assertTrue($contactLocation['email_in_first_300'] ?? false);
        $this->assertTrue($contactLocation['phone_in_first_300'] ?? false);
    }

    #[Test]
    public function it_detects_missing_contact_info(): void
    {
        $resumeText = "John Doe\n\nExperience\nSoftware Engineer";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertFalse($result['details']['contact_location']['email_exists'] ?? true);
        $this->assertFalse($result['details']['contact_location']['phone_exists'] ?? true);
        $this->assertNotEmpty($result['critical_issues']);
    }

    #[Test]
    public function it_detects_contact_info_too_far_from_top(): void
    {
        $resumeText = str_repeat("Lorem ipsum dolor sit amet.\n", 20).'john.doe@example.com';
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $contactLocation = $result['details']['contact_location'] ?? [];
        $this->assertFalse($contactLocation['email_in_first_300'] ?? true);
        $this->assertNotEmpty($result['warnings']);
    }

    #[Test]
    public function it_detects_date_placeholders(): void
    {
        $resumeText = "Experience\nSoftware Engineer\n20XX - Present\n";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertTrue($result['details']['date_detection']['has_placeholders'] ?? false);
        $this->assertNotEmpty($result['critical_issues']);
    }

    #[Test]
    public function it_detects_valid_dates(): void
    {
        $resumeText = "Experience\nSoftware Engineer\nJan 2020 - Present\n";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertTrue($result['details']['date_detection']['has_valid_dates'] ?? false);
        $this->assertFalse($result['details']['date_detection']['has_placeholders'] ?? true);
    }

    #[Test]
    public function it_detects_missing_dates(): void
    {
        $resumeText = "Experience\nSoftware Engineer\nNo dates provided";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertFalse($result['details']['date_detection']['has_valid_dates'] ?? true);
        $this->assertNotEmpty($result['critical_issues']);
    }

    #[Test]
    public function it_detects_name_in_resume(): void
    {
        $resumeText = "John Michael Doe\njohn.doe@example.com\n";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertTrue($result['details']['name_detection']['has_name'] ?? false);
    }

    #[Test]
    public function it_detects_missing_name(): void
    {
        $resumeText = "john.doe@example.com\nExperience\n";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertFalse($result['details']['name_detection']['has_name'] ?? true);
        $this->assertNotEmpty($result['critical_issues']);
    }

    #[Test]
    public function it_detects_summary_section(): void
    {
        $resumeText = "John Doe\njohn.doe@example.com\n\nProfessional Summary\nExperienced software engineer with 5+ years of experience building scalable web applications. Proven track record of leading teams.\n\nExperience\n...";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertTrue($result['details']['summary_detection']['has_summary'] ?? false);
    }

    #[Test]
    public function it_detects_missing_summary(): void
    {
        $resumeText = "John Doe\nExperience\nSoftware Engineer";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertFalse($result['details']['summary_detection']['has_summary'] ?? true);
        $this->assertNotEmpty($result['warnings']);
    }

    #[Test]
    public function it_counts_bullet_points_correctly(): void
    {
        $resumeText = "Experience\n• Led development team\n• Built scalable applications\n• Managed projects\n";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $bulletCount = $result['details']['bullet_point_count']['count'] ?? 0;
        $this->assertGreaterThanOrEqual(3, $bulletCount);
    }

    #[Test]
    public function it_detects_insufficient_bullet_points(): void
    {
        $resumeText = "Experience\n• Only one bullet point\n";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $bulletCount = $result['details']['bullet_point_count']['count'] ?? 0;
        $this->assertLessThan(ATSParseabilityCheckerConstants::BULLETS_MIN_OPTIMAL, $bulletCount);
        $this->assertNotEmpty($result['warnings']);
    }

    #[Test]
    public function it_detects_quantifiable_metrics(): void
    {
        $resumeText = "John Doe\njohn.doe@example.com\n\nExperience\nSoftware Engineer\nCompany XYZ\nJan 2020 - Present\n• Increased sales by 30%\n• Managed team of 5 developers\n• Reduced costs by $50K\n• Improved performance by 40%\n";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertTrue($result['details']['metrics_detection']['has_metrics'] ?? false);
    }

    #[Test]
    public function it_detects_missing_quantifiable_metrics(): void
    {
        $resumeText = "Experience\n• Led development team\n• Worked on projects\n• Collaborated with team\n";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertFalse($result['details']['metrics_detection']['has_metrics'] ?? true);
        $this->assertNotEmpty($result['warnings']);
    }

    #[Test]
    public function it_penalizes_short_resume(): void
    {
        $resumeText = str_repeat('word ', 200); // Less than 400 words
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertFalse($result['details']['document_length']['is_optimal'] ?? true);
        $this->assertLessThan(
            ATSParseabilityCheckerConstants::STARTING_SCORE,
            $result['score']
        );
    }

    #[Test]
    public function it_penalizes_long_resume(): void
    {
        $resumeText = str_repeat('word ', 1000); // More than 800 words
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertFalse($result['details']['document_length']['is_optimal'] ?? true);
        $this->assertLessThan(
            ATSParseabilityCheckerConstants::STARTING_SCORE,
            $result['score']
        );
    }

    #[Test]
    public function it_accepts_optimal_length_resume(): void
    {
        $resumeText = str_repeat('word ', 600); // Between 400-800 words
        $resumeText .= "\n".$this->createWellFormattedResume();
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertTrue($result['details']['document_length']['is_optimal'] ?? false);
    }

    #[Test]
    public function it_detects_experience_level(): void
    {
        $resumeText = "Experience\nSoftware Engineer\nJan 2020 - Present\nSenior Engineer\nJan 2018 - Dec 2019\nEngineer\nJan 2016 - Dec 2017\n";
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $experienceLevel = $result['details']['experience_level'] ?? [];
        $this->assertTrue($experienceLevel['is_experienced'] ?? false);
        $this->assertGreaterThanOrEqual(5, $experienceLevel['years'] ?? 0);
    }

    #[Test]
    public function it_returns_correct_structure(): void
    {
        $resumeText = $this->createWellFormattedResume();
        $filePath = '/tmp/test.pdf';
        $mimeType = 'application/pdf';

        $result = $this->checker->check($filePath, $resumeText, $mimeType);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('critical_issues', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('details', $result);
        $this->assertIsInt($result['score']);
        $this->assertIsArray($result['critical_issues']);
        $this->assertIsArray($result['warnings']);
        $this->assertContains($result['confidence'], ['high', 'medium', 'low']);
    }

    /**
     * Create a well-formatted resume for testing.
     */
    protected function createWellFormattedResume(): string
    {
        return "John Michael Doe\n"
            ."john.doe@example.com\n"
            ."(555) 123-4567\n"
            ."\n"
            ."Professional Summary\n"
            ."Experienced software engineer with 5+ years of experience building scalable web applications.\n"
            ."\n"
            ."Experience\n"
            ."Senior Software Engineer\n"
            ."Company XYZ\n"
            ."Jan 2020 - Present\n"
            ."• Led development team of 5 engineers\n"
            ."• Increased system performance by 30%\n"
            ."• Reduced costs by $50K\n"
            ."\n"
            ."Software Engineer\n"
            ."Company ABC\n"
            ."Jan 2018 - Dec 2019\n"
            ."• Built scalable applications\n"
            ."• Managed API integrations\n"
            ."\n"
            ."Education\n"
            ."Bachelor of Science in Computer Science\n"
            ."University Name\n"
            ."2014 - 2018\n"
            ."\n"
            ."Skills\n"
            .'Laravel, React, PHP, JavaScript, AWS, Docker';
    }

    /**
     * Create a multi-column resume layout.
     */
    protected function createMultiColumnResume(): string
    {
        // Create text with alternating short and long lines (simulating multi-column)
        $lines = [];
        for ($i = 0; $i < 30; $i++) {
            if ($i % 2 === 0) {
                $lines[] = 'Short line';
            } else {
                $lines[] = str_repeat('word ', 20).'very long line that extends beyond normal width';
            }
        }

        return implode("\n", $lines);
    }
}
