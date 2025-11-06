# Architecture

## Dual Analysis System

The application uses a two-tier analysis approach:

### 1. Parseability Checks (Rule-based)

Hard checks for technical issues:
- Scanned image detection
- Table detection
- Multi-column layout detection
- Document length verification
- Contact info location
- Date detection and validation
- Name detection
- Summary/profile detection
- Bullet point counting (advanced multi-pass detection)
- Quantifiable metrics detection

### 2. AI Analysis (OpenAI GPT-4o-mini)

Intelligent content assessment:
- Format and structure analysis
- Keyword relevance and industry alignment
- Content quality evaluation
- Action verb detection
- Quantifiable achievements identification
- Personalized recommendations

### 3. Score Validation

Combines both analyses with intelligent weighting:
- Applies hard check overrides when critical issues are detected
- Uses weighted average for overall score
- Handles AI unavailability gracefully

## File Processing Flow

1. File uploaded via Inertia form
2. File stored temporarily in `storage/app/temp`
3. Text extracted using appropriate parser (PDF or DOCX)
4. Parseability checks run (rule-based analysis)
5. AI analysis runs (if API key configured and parseability > 0)
6. Results validated and combined
7. Scores calculated with weighted averaging
8. Issues categorized (Critical, Warnings, Improvements)
9. Results returned to frontend via Inertia
10. Temporary file deleted in `finally` block (critical)

## Database Models

The application uses database models for:
- **Feedback**: User ratings and feedback submissions
- **Visitor**: Page visit tracking and analytics
- **Users**: Laravel authentication (if needed)

All analysis results are processed in memory and stored temporarily in sessions for display. Database is primarily used for analytics and feedback collection.

## Project Structure

```
ats-tracker/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── FeedbackController.php   # Feedback submission handler
│   │   │   └── ResumeController.php     # Resume analysis handler
│   │   ├── Middleware/
│   │   │   ├── AddSecurityHeaders.php    # Security headers middleware
│   │   │   └── HandleInertiaRequests.php # Inertia.js middleware
│   │   └── Requests/
│   │       ├── AnalyzeResumeRequest.php  # Resume upload validation
│   │       └── StoreFeedbackRequest.php  # Feedback validation
│   ├── Models/
│   │   ├── Feedback.php                # Feedback model
│   │   ├── User.php                    # User model
│   │   └── Visitor.php                 # Visitor tracking model
│   ├── Providers/
│   │   └── AppServiceProvider.php       # Rate limiters & service registration
│   └── Services/
│       ├── AIResumeAnalyzer.php          # OpenAI GPT-4o-mini integration
│       ├── AIResumeAnalyzerConstants.php # AI service constants
│       ├── ATSParseabilityChecker.php    # Rule-based hard checks
│       ├── ATSParseabilityCheckerConstants.php  # Parseability constants
│       ├── ATSScoreValidator.php         # Score validation & combination
│       ├── ATSScoreValidatorConstants.php # Score validator constants
│       └── ResumeParserService.php       # PDF/DOCX text extraction
├── resources/
│   ├── js/
│   │   ├── pages/
│   │   │   ├── MethodNotAllowed.tsx      # 405 error page
│   │   │   ├── NotFound.tsx             # 404 error page
│   │   │   ├── ResumeChecker.tsx         # Main page component
│   │   │   └── welcome.tsx              # Welcome page
│   │   ├── components/
│   │   │   ├── AnalysisLoadingModal.tsx  # Progress modal with steps
│   │   │   ├── CircularProgress.tsx      # Animated progress rings
│   │   │   ├── ErrorBoundary.tsx         # React error boundary
│   │   │   ├── FeedbackModal.tsx         # Feedback submission modal
│   │   │   ├── ResumeResultsDashboard.tsx # Results dashboard
│   │   │   ├── Toast.tsx                 # Toast notification component
│   │   │   └── ToastContainer.tsx         # Toast container & hook
│   │   └── hooks/
│   │       └── useCountUp.ts             # Number animation hook
│   └── prompts/
│       └── ats-analysis-prompt.txt       # AI analysis prompt template
├── routes/
│   └── web.php
├── storage/
│   └── app/
│       └── temp/                         # Temporary file storage
└── tests/
    ├── Feature/
    │   ├── ResumeAnalysisIntegrationTest.php # Integration tests
    │   └── ResumeAnalysisTest.php         # Feature tests
    └── Unit/
        ├── ATSParseabilityCheckerTest.php    # Parseability unit tests
        └── ATSScoreValidatorTest.php        # Score validator unit tests
```

## Key Services

### AIResumeAnalyzer
Uses OpenAI GPT-4o-mini to analyze resume content. Provides intelligent assessment of format, keywords, contact information, and content quality. Returns structured JSON with scores and recommendations.

### ATSParseabilityChecker
Performs comprehensive rule-based checks for technical parseability issues:
- Scanned image detection
- Table and multi-column layout detection
- Document length verification
- Contact info location validation
- Date detection and validation
- Bullet point counting (sophisticated multi-pass algorithm)
- Quantifiable metrics detection

**Important**: The bullet point detection logic was manually tested with real resumes to ensure accuracy.

### ATSScoreValidator
Validates and combines results from parseability checks and AI analysis. Applies intelligent weighting and hard check overrides. Handles AI unavailability gracefully.

### ResumeParserService
Extracts text from PDF and DOCX files. Uses multiple parsers with fallback mechanisms to ensure maximum compatibility.

