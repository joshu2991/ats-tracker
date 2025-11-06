<?php

namespace Tests\Unit;

use App\Services\ATSScoreValidator;
use App\Services\ATSScoreValidatorConstants;
use Tests\TestCase;

class ATSScoreValidatorTest extends TestCase
{
    protected ATSScoreValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ATSScoreValidator;
    }

    /** @test */
    public function it_returns_basic_analysis_when_ai_unavailable(): void
    {
        $parseabilityResults = $this->createParseabilityResults();

        $result = $this->validator->validate($parseabilityResults, null);

        $this->assertTrue($result['ai_unavailable'] ?? false);
        $this->assertNotNull($result['ai_error_message']);
        $this->assertArrayHasKey('overall_score', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('parseability_score', $result);
    }

    /** @test */
    public function it_validates_with_ai_results(): void
    {
        $parseabilityResults = $this->createParseabilityResults();
        $aiResults = $this->createAIResults();

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        $this->assertFalse($result['ai_unavailable'] ?? true);
        $this->assertArrayHasKey('overall_score', $result);
        $this->assertArrayHasKey('format_score', $result);
        $this->assertArrayHasKey('keyword_score', $result);
        $this->assertArrayHasKey('contact_score', $result);
        $this->assertArrayHasKey('content_score', $result);
        $this->assertIsInt($result['overall_score']);
    }

    /** @test */
    public function it_applies_scanned_image_penalty(): void
    {
        $parseabilityResults = $this->createParseabilityResults([
            'details' => [
                'text_extractability' => ['is_scanned_image' => true],
            ],
        ]);
        $aiResults = $this->createAIResults(['format_analysis' => ['score' => 80]]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        $this->assertLessThanOrEqual(
            ATSScoreValidatorConstants::PENALTY_SCANNED_IMAGE_MAX,
            $result['format_score']
        );
    }

    /** @test */
    public function it_applies_date_placeholder_penalty(): void
    {
        $parseabilityResults = $this->createParseabilityResults([
            'score' => 60, // Lower score to trigger penalties
            'details' => [
                'date_detection' => ['has_placeholders' => true],
            ],
        ]);
        $aiResults = $this->createAIResults([
            'overall_assessment' => ['ats_compatibility_score' => 60],
            'format_analysis' => ['score' => 80],
        ]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        $this->assertLessThan(80, $result['format_score']);
    }

    /** @test */
    public function it_applies_missing_name_penalty(): void
    {
        $parseabilityResults = $this->createParseabilityResults([
            'score' => 60, // Lower score to trigger penalties
            'details' => [
                'name_detection' => ['has_name' => false],
            ],
        ]);
        $aiResults = $this->createAIResults([
            'overall_assessment' => ['ats_compatibility_score' => 60],
            'format_analysis' => ['score' => 80],
        ]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        $this->assertLessThan(80, $result['format_score']);
    }

    /** @test */
    public function it_applies_missing_summary_penalty(): void
    {
        $parseabilityResults = $this->createParseabilityResults([
            'score' => 60, // Lower score to trigger penalties
            'details' => [
                'summary_detection' => ['has_summary' => false],
            ],
        ]);
        $aiResults = $this->createAIResults([
            'overall_assessment' => ['ats_compatibility_score' => 60],
            'format_analysis' => ['score' => 80],
        ]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        $this->assertLessThan(80, $result['format_score']);
    }

    /** @test */
    public function it_applies_insufficient_bullets_penalty(): void
    {
        $parseabilityResults = $this->createParseabilityResults([
            'details' => [
                'bullet_point_count' => ['count' => 3], // Less than optimal
            ],
        ]);
        $aiResults = $this->createAIResults(['content_quality' => ['score' => 80]]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        $this->assertLessThan(80, $result['content_score']);
    }

    /** @test */
    public function it_applies_no_metrics_penalty(): void
    {
        $parseabilityResults = $this->createParseabilityResults([
            'details' => [
                'metrics_detection' => ['has_metrics' => false],
            ],
        ]);
        $aiResults = $this->createAIResults(['content_quality' => ['score' => 80]]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        $this->assertLessThan(80, $result['content_score']);
    }

    /** @test */
    public function it_applies_table_detection_penalty(): void
    {
        $parseabilityResults = $this->createParseabilityResults([
            'score' => 60, // Lower score to trigger penalties
            'details' => [
                'table_detection' => ['has_tables' => true],
            ],
        ]);
        $aiResults = $this->createAIResults([
            'overall_assessment' => ['ats_compatibility_score' => 60],
            'format_analysis' => ['score' => 80],
        ]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        $this->assertLessThan(80, $result['format_score']);
    }

    /** @test */
    public function it_applies_multi_column_penalty(): void
    {
        $parseabilityResults = $this->createParseabilityResults([
            'score' => 60, // Lower score to trigger penalties
            'details' => [
                'multi_column' => ['has_multi_column' => true],
            ],
        ]);
        $aiResults = $this->createAIResults([
            'overall_assessment' => ['ats_compatibility_score' => 60],
            'format_analysis' => ['score' => 80],
        ]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        $this->assertLessThan(80, $result['format_score']);
    }

    /** @test */
    public function it_applies_thin_content_penalty(): void
    {
        $parseabilityResults = $this->createParseabilityResults([
            'details' => [
                'document_length' => ['word_count' => 300], // Less than 400
            ],
        ]);
        $aiResults = $this->createAIResults([
            'content_quality' => [
                'score' => 80,
                'estimated_word_count' => 300,
                'quantifiable_achievements' => false,
                'achievement_examples' => [],
            ],
        ]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        $this->assertLessThan(80, $result['content_score']);
    }

    /** @test */
    public function it_uses_balanced_weighting_for_good_scores(): void
    {
        $parseabilityResults = $this->createParseabilityResults(['score' => 75]);
        $aiResults = $this->createAIResults([
            'overall_assessment' => ['ats_compatibility_score' => 80],
            'format_analysis' => ['score' => 80],
            'keyword_analysis' => ['total_unique_keywords' => 20],
            'contact_information' => [
                'email_found' => true,
                'email_location' => 'top',
                'phone_found' => true,
                'phone_location' => 'top',
            ],
            'content_quality' => [
                'score' => 80,
                'estimated_word_count' => 600,
                'achievement_examples' => [
                    ['example' => 'Increased sales by 30%'],
                    ['example' => 'Managed team of 5'],
                    ['example' => 'Reduced costs by $50K'],
                ],
            ],
        ]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        // Should use balanced weighting (50/50) when both scores are good
        $this->assertGreaterThan(70, $result['overall_score']);
    }

    /** @test */
    public function it_uses_weighted_average_for_mixed_scores(): void
    {
        $parseabilityResults = $this->createParseabilityResults(['score' => 50]);
        $aiResults = $this->createAIResults([
            'overall_assessment' => ['ats_compatibility_score' => 60],
            'format_analysis' => ['score' => 60],
            'keyword_analysis' => ['total_unique_keywords' => 10],
            'contact_information' => [
                'email_found' => true,
                'email_location' => 'middle',
            ],
            'content_quality' => [
                'score' => 60,
                'estimated_word_count' => 600,
                'achievement_examples' => [
                    ['example' => 'Increased sales'],
                ],
            ],
        ]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        // Should use weighted average when scores are mixed
        $this->assertIsInt($result['overall_score']);
        $this->assertGreaterThanOrEqual(0, $result['overall_score']);
        $this->assertLessThanOrEqual(100, $result['overall_score']);
    }

    /** @test */
    public function it_categorizes_issues_correctly(): void
    {
        $parseabilityResults = $this->createParseabilityResults([
            'critical_issues' => ['No contact information (email or phone) found in the resume.'],
            'warnings' => ['Warning 1'],
        ]);
        $aiResults = $this->createAIResults([
            'ats_red_flags' => ['Red flag 1'],
            'critical_fixes_required' => ['Critical fix 1'],
            'recommended_improvements' => ['Improvement 1'],
        ]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        $this->assertNotEmpty($result['critical_issues']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertNotEmpty($result['suggestions']);
    }

    /** @test */
    public function it_caps_entry_level_resumes(): void
    {
        $parseabilityResults = $this->createParseabilityResults([
            'score' => 90,
            'details' => [
                'document_length' => ['word_count' => 300],
            ],
        ]);
        $aiResults = $this->createAIResults([
            'overall_assessment' => ['ats_compatibility_score' => 85],
            'format_analysis' => ['score' => 85],
            'keyword_analysis' => ['total_unique_keywords' => 20],
            'contact_information' => [
                'email_found' => true,
                'email_location' => 'top',
            ],
            'content_quality' => [
                'score' => 85,
                'estimated_word_count' => 300,
                'achievement_examples' => [], // Less than 3
            ],
        ]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        // Entry-level resumes should be capped
        $this->assertLessThanOrEqual(
            ATSScoreValidatorConstants::ENTRY_LEVEL_CAP_SCORE,
            $result['overall_score']
        );
    }

    /** @test */
    public function it_combines_issues_from_parseability_and_ai(): void
    {
        $parseabilityResults = $this->createParseabilityResults([
            'critical_issues' => ['No contact information (email or phone) found in the resume.'],
            'warnings' => ['Parseability warning'],
        ]);
        $aiResults = $this->createAIResults([
            'ats_red_flags' => ['AI red flag'],
            'critical_fixes_required' => ['AI critical fix'],
            'recommended_improvements' => ['AI improvement'],
        ]);

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        // Should have at least 1 critical issue from parseability
        $this->assertGreaterThanOrEqual(1, count($result['critical_issues']));
        // Should have at least 1 warning from parseability
        $this->assertGreaterThanOrEqual(1, count($result['warnings']));
        // Should have at least 1 suggestion from AI
        $this->assertGreaterThanOrEqual(1, count($result['suggestions']));
    }

    /** @test */
    public function it_calculates_confidence_correctly(): void
    {
        $parseabilityResults = $this->createParseabilityResults([
            'confidence' => 'high',
            'critical_issues' => [],
            'warnings' => [],
        ]);
        $aiResults = $this->createAIResults();

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        $this->assertContains($result['confidence'], ['high', 'medium', 'low']);
    }

    /** @test */
    public function it_returns_correct_structure(): void
    {
        $parseabilityResults = $this->createParseabilityResults();
        $aiResults = $this->createAIResults();

        $result = $this->validator->validate($parseabilityResults, $aiResults);

        $this->assertArrayHasKey('overall_score', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('parseability_score', $result);
        $this->assertArrayHasKey('format_score', $result);
        $this->assertArrayHasKey('keyword_score', $result);
        $this->assertArrayHasKey('contact_score', $result);
        $this->assertArrayHasKey('content_score', $result);
        $this->assertArrayHasKey('critical_issues', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertArrayHasKey('suggestions', $result);
        $this->assertArrayHasKey('estimated_cost', $result);
        $this->assertArrayHasKey('ai_unavailable', $result);
    }

    /**
     * Create parseability results for testing.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function createParseabilityResults(array $overrides = []): array
    {
        return array_merge([
            'score' => 80,
            'critical_issues' => [],
            'warnings' => [],
            'confidence' => 'high',
            'details' => [
                'text_extractability' => ['is_scanned_image' => false],
                'table_detection' => ['has_tables' => false],
                'multi_column' => ['has_multi_column' => false],
                'document_length' => [
                    'is_optimal' => true,
                    'word_count' => 600,
                ],
                'contact_location' => [
                    'email_exists' => true,
                    'email_in_first_300' => true,
                    'phone_exists' => true,
                    'phone_in_first_300' => true,
                ],
                'date_detection' => [
                    'has_valid_dates' => true,
                    'has_placeholders' => false,
                ],
                'name_detection' => ['has_name' => true],
                'summary_detection' => ['has_summary' => true],
                'bullet_point_count' => ['count' => 15],
                'metrics_detection' => ['has_metrics' => true],
            ],
        ], $overrides);
    }

    /**
     * Create AI results for testing.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function createAIResults(array $overrides = []): array
    {
        return array_merge([
            'overall_assessment' => ['ats_compatibility_score' => 75],
            'format_analysis' => ['score' => 75],
            'keyword_analysis' => [
                'total_unique_keywords' => 15,
                'industry_alignment' => 'medium',
            ],
            'contact_information' => [
                'email_found' => true,
                'email_location' => 'top',
                'phone_found' => true,
                'phone_location' => 'top',
            ],
            'content_quality' => [
                'score' => 75,
                'estimated_word_count' => 600,
                'quantifiable_achievements' => true,
                'achievement_examples' => [
                    ['example' => 'Increased sales by 30%'],
                    ['example' => 'Managed team of 5'],
                    ['example' => 'Reduced costs by $50K'],
                ],
                'uses_action_verbs' => true,
                'action_verb_examples' => ['Led', 'Built', 'Managed'],
                'appropriate_length' => true,
                'has_bullet_points' => true,
            ],
            'ats_red_flags' => [],
            'critical_fixes_required' => [],
            'recommended_improvements' => [],
        ], $overrides);
    }
}
