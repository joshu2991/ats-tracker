<?php

namespace Tests\Feature;

use App\Services\ATSParseabilityChecker;
use App\Services\ATSScoreValidator;
use App\Services\ResumeParserService;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResumeAnalysisIntegrationTest extends TestCase
{
    protected ResumeParserService $parser;

    protected ATSParseabilityChecker $parseabilityChecker;

    protected ATSScoreValidator $scoreValidator;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->parser = new ResumeParserService;
        $this->parseabilityChecker = new ATSParseabilityChecker;
        $this->scoreValidator = new ATSScoreValidator;
    }

    #[Test]
    public function it_completes_full_analysis_flow_with_pdf(): void
    {
        // This test requires actual PDF parsing with poppler-utils
        $this->markTestSkipped('Requires actual PDF file parsing with poppler-utils');
    }

    #[Test]
    public function it_handles_analysis_without_ai(): void
    {
        // This test requires actual PDF parsing with poppler-utils
        $this->markTestSkipped('Requires actual PDF file parsing with poppler-utils');
    }

    #[Test]
    public function it_detects_issues_in_poorly_formatted_resume(): void
    {
        // This test requires actual PDF parsing with poppler-utils
        $this->markTestSkipped('Requires actual PDF file parsing with poppler-utils');
    }

    #[Test]
    public function it_handles_scanned_image_detection(): void
    {
        // This test requires actual PDF parsing with poppler-utils
        $this->markTestSkipped('Requires actual PDF file parsing with poppler-utils');
    }

    #[Test]
    public function it_handles_table_detection(): void
    {
        // This test requires actual PDF parsing with poppler-utils
        $this->markTestSkipped('Requires actual PDF file parsing with poppler-utils');
    }

    #[Test]
    public function it_handles_contact_info_detection(): void
    {
        // This test requires actual PDF parsing with poppler-utils
        $this->markTestSkipped('Requires actual PDF file parsing with poppler-utils');
    }

    #[Test]
    public function it_handles_bullet_point_counting(): void
    {
        // This test requires actual PDF parsing with poppler-utils
        $this->markTestSkipped('Requires actual PDF file parsing with poppler-utils');
    }

    #[Test]
    public function it_handles_metrics_detection(): void
    {
        // This test requires actual PDF parsing with poppler-utils
        $this->markTestSkipped('Requires actual PDF file parsing with poppler-utils');
    }

    /**
     * Create a well-formatted resume text for testing.
     */
    protected function createWellFormattedResumeText(): string
    {
        return "John Michael Doe\n"
            ."john.doe@example.com\n"
            ."(555) 123-4567\n"
            ."\n"
            ."Professional Summary\n"
            ."Experienced software engineer with 5+ years of experience building scalable web applications using Laravel, React, and AWS.\n"
            ."\n"
            ."Experience\n"
            ."Senior Software Engineer\n"
            ."Company XYZ\n"
            ."Jan 2020 - Present\n"
            ."• Led development team of 5 engineers\n"
            ."• Increased system performance by 30%\n"
            ."• Reduced costs by $50K\n"
            ."• Managed API integrations\n"
            ."\n"
            ."Software Engineer\n"
            ."Company ABC\n"
            ."Jan 2018 - Dec 2019\n"
            ."• Built scalable applications\n"
            ."• Improved response time by 25%\n"
            ."• Worked with Docker and Kubernetes\n"
            ."\n"
            ."Education\n"
            ."Bachelor of Science in Computer Science\n"
            ."University Name\n"
            ."2014 - 2018\n"
            ."\n"
            ."Skills\n"
            .'Laravel, React, PHP, JavaScript, AWS, Docker, Kubernetes';
    }

    /**
     * Create mock AI results for testing.
     *
     * @return array<string, mixed>
     */
    protected function createMockAIResults(): array
    {
        return [
            'overall_assessment' => ['ats_compatibility_score' => 75],
            'format_analysis' => ['score' => 80],
            'keyword_analysis' => [
                'total_unique_keywords' => 20,
                'industry_alignment' => 'high',
            ],
            'contact_information' => [
                'email_found' => true,
                'email_location' => 'top',
                'phone_found' => true,
                'phone_location' => 'top',
                'linkedin_found' => false,
                'github_found' => false,
            ],
            'content_quality' => [
                'score' => 80,
                'estimated_word_count' => 600,
                'quantifiable_achievements' => true,
                'achievement_examples' => [
                    ['example' => 'Increased sales by 30%'],
                    ['example' => 'Managed team of 5'],
                    ['example' => 'Reduced costs by $50K'],
                ],
                'uses_action_verbs' => true,
                'action_verb_examples' => ['Led', 'Built', 'Managed', 'Increased', 'Reduced'],
                'appropriate_length' => true,
                'has_bullet_points' => true,
            ],
            'ats_red_flags' => [],
            'critical_fixes_required' => [],
            'recommended_improvements' => [
                'Consider adding more technical keywords',
            ],
        ];
    }
}
