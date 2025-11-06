# Resume ATS Checker

A modern, AI-powered web application that analyzes resumes for ATS (Applicant Tracking System) compatibility. Upload your resume in PDF or DOCX format and receive comprehensive, actionable feedback on format, keywords, contact information, content quality, and overall ATS readiness.

## Features

### ✨ Core Features

- **File Upload**: Drag & drop or click to upload resumes (PDF or DOCX, max 5MB)
- **Dual Analysis System**: Combines rule-based parseability checks with AI-powered analysis
- **AI-Powered Analysis**: Uses OpenAI GPT-4o-mini for intelligent content assessment
- **Text Extraction**: Automatically extracts text from PDF and DOCX files with multiple parser fallbacks
- **Section Detection**: Identifies key resume sections (Experience, Education, Skills, Projects)
- **Contact Information Extraction**: Detects email, phone, LinkedIn, and GitHub URLs
- **Comprehensive ATS Scoring**: Multi-dimensional scoring system (0-100 points) across 5 categories:
  - **Parseability Score** (0-100 pts): PDF text extraction, table detection, multi-column layouts, scanned images
  - **Format & Structure Score** (0-100 pts): Section headers, document structure, dates, formatting
  - **Keywords & Skills Score** (0-100 pts): Technical keywords detection and industry alignment
  - **Contact Info Score** (0-100 pts): Contact information completeness and location
  - **Content Quality Score** (0-100 pts): Action verbs, quantifiable metrics, bullet points, achievements
- **Actionable Suggestions**: Prioritized recommendations with impact estimates and difficulty ratings
- **Issue Categorization**: Critical fixes, warnings, and improvements with clear priorities
- **Visual Dashboard**: Beautiful, responsive UI with animated circular progress rings
- **Real-time Analysis**: Fast analysis with progress indicators and estimated time

### 🎯 Scoring Breakdown

- **Green (70-100)**: Excellent - Your resume is ATS-ready
- **Amber (50-69)**: Good - Minor improvements needed
- **Rose (0-49)**: Needs Improvement - Significant changes recommended

### 🧠 AI Integration

The application uses OpenAI GPT-4o-mini to provide intelligent analysis:
- Context-aware content quality assessment
- Industry-standard keyword evaluation
- Format structure analysis based on ATS best practices
- Personalized recommendations based on resume content
- Graceful fallback to rule-based analysis if AI is unavailable

## Tech Stack

### Backend
- **Laravel 12** - PHP framework
- **Inertia.js v2** - Modern monolith architecture
- **OpenAI API (GPT-4o-mini)** - AI-powered analysis
- **Guzzle HTTP** - HTTP client for API requests
- **smalot/pdfparser** - PDF text extraction
- **spatie/pdf-to-text** - Alternative PDF parser (fallback)
- **phpoffice/phpword** - DOCX text extraction

### Frontend
- **React 19** - UI library
- **TypeScript** - Type safety
- **Tailwind CSS v4** - Utility-first styling
- **Framer Motion** - Animations and transitions
- **Lucide React** - Icon library
- **Canvas Confetti** - Celebration animations
- **Inertia.js React** - Server-driven single-page apps

### Development Tools
- **Laravel Pint** - PHP code formatting
- **PHPUnit** - Testing framework
- **ESLint** - JavaScript linting
- **Prettier** - Code formatting
- **TypeScript** - Static type checking

## Requirements

- PHP 8.2 or higher
- Composer
- Node.js 18+ and npm
- SQLite (default) or any Laravel-supported database
- OpenAI API key (optional, for AI analysis)

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd resume-checker
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Node Dependencies

```bash
npm install
```

### 4. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 5. Configure OpenAI API (Optional)

Add your OpenAI API key to `.env`:

```env
OPENAI_API_KEY=your-api-key-here
```

The application will work without an API key, but will only provide basic rule-based analysis.

### 6. Create Storage Directories

```bash
mkdir -p storage/app/temp
chmod -R 775 storage bootstrap/cache
```

### 7. Build Frontend Assets

For development:
```bash
npm run dev
```

For production:
```bash
npm run build
```

### 8. Run the Application

```bash
php artisan serve
```

Visit `http://localhost:8000/resume-checker` in your browser.

## Usage

### Analyzing a Resume

1. Navigate to `/resume-checker`
2. Upload your resume (PDF or DOCX format, max 5MB)
   - Drag and drop the file, or
   - Click to browse and select
3. Click "Analyze Resume"
4. Wait for analysis to complete (usually 15-30 seconds)
5. Review your comprehensive ATS score and detailed breakdown
6. Check categorized issues (Critical, Warnings, Improvements)
7. Review actionable suggestions with impact estimates
8. Use "Quick Wins" section for prioritized fixes

### File Requirements

- **Formats**: PDF (.pdf) or DOCX (.docx)
- **Maximum Size**: 5MB
- **Text-based**: Images-only PDFs may not work correctly (will be detected as scanned)

## Architecture

### Dual Analysis System

The application uses a two-tier analysis approach:

1. **Parseability Checks** (Rule-based): Hard checks for technical issues
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

2. **AI Analysis** (OpenAI GPT-4o-mini): Intelligent content assessment
   - Format and structure analysis
   - Keyword relevance and industry alignment
   - Content quality evaluation
   - Action verb detection
   - Quantifiable achievements identification
   - Personalized recommendations

3. **Score Validation**: Combines both analyses with intelligent weighting
   - Applies hard check overrides when critical issues are detected
   - Uses weighted average for overall score
   - Handles AI unavailability gracefully

### File Processing Flow

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

### Stateless Design

- No database persistence required
- All processing happens in memory
- Temporary files are automatically deleted after analysis
- Perfect for serverless or stateless deployments

## Project Structure

```
resume-checker/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── ResumeController.php
│   │   └── Requests/
│   │       └── AnalyzeResumeRequest.php
│   └── Services/
│       ├── AIResumeAnalyzer.php          # OpenAI GPT-4o-mini integration
│       ├── ATSParseabilityChecker.php    # Rule-based hard checks
│       ├── ATSParseabilityCheckerConstants.php  # Scoring thresholds & constants
│       ├── ATSScoreValidator.php         # Score validation & combination
│       ├── ATSScorerService.php          # Legacy scoring (test-only, not used in app)
│       ├── KeywordAnalyzerService.php    # Legacy keyword detection (test-only, not used in app)
│       ├── ResumeParserService.php       # PDF/DOCX text extraction
│       └── SectionDetectorService.php    # Legacy section detection (test-only, not used in app)
├── resources/
│   └── js/
│       ├── pages/
│       │   └── ResumeChecker.tsx         # Main page component
│       ├── components/
│       │   ├── AnalysisLoadingModal.tsx  # Progress modal with steps
│       │   ├── CircularProgress.tsx      # Animated progress rings
│       │   └── ResumeResultsDashboard.tsx # Results dashboard
│       └── hooks/
│           └── useCountUp.ts             # Number animation hook
├── routes/
│   └── web.php
├── storage/
│   └── app/
│       └── temp/                         # Temporary file storage
└── tests/
    └── Feature/
        └── ResumeAnalysisTest.php
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

### Legacy Services (Test-Only)
**Note**: The following services are legacy and not used in the actual application flow. They are kept for backward compatibility with existing tests:

- **SectionDetectorService**: Legacy section and contact detection (replaced by `ATSParseabilityChecker`)
- **KeywordAnalyzerService**: Legacy keyword analysis (replaced by AI analysis in `AIResumeAnalyzer`)
- **ATSScorerService**: Legacy scoring logic (replaced by `ATSScoreValidator` and AI analysis)

## Scoring Algorithm

### Overall Score Calculation

The overall score is calculated using a weighted average:

- **Parseability**: 25% (rule-based technical checks)
- **Format & Structure**: 25% (AI + rule-based)
- **Keywords & Skills**: 25% (AI + rule-based)
- **Contact Info**: 10% (AI + rule-based)
- **Content Quality**: 15% (AI + rule-based)

### Parseability Score (0-100)

Starts at 90 points, penalties applied for:
- Scanned images: -30
- Tables detected: -30
- Multi-column layouts: -25
- Missing contact info: -25
- Date placeholders: -20
- Missing dates: -25
- Missing name: -20
- Missing summary: -10
- Length issues: -10 to -15
- Insufficient bullets: -10 to -20
- Lack of metrics: -15

### Category Scores (0-100)

Each category is scored by AI analysis with adjustments from parseability checks:
- **Format**: Section presence, structure, dates, formatting
- **Keywords**: Technical keyword density and relevance
- **Contact**: Completeness and optimal location (first 300 chars)
- **Content**: Action verbs, quantifiable achievements, bullet points

### Issue Categorization

- **Critical** (< 30 score): Unparseable, no contact, critical format issues
- **Warnings** (30-60 score): Contact in header, few keywords, minor issues
- **Improvements** (60-100 score): Could use more keywords, stronger verbs, enhancements

## Testing

Run the test suite:

```bash
php artisan test
```

Run specific test file:

```bash
php artisan test --filter=ResumeAnalysisTest
```

### Test Coverage

The test suite includes:
- File upload validation
- Section detection accuracy
- Contact information extraction
- Score calculation verification
- Keyword analysis
- Suggestions generation
- File cleanup verification
- Bullet point detection
- Action verb detection

## Development

### Code Formatting

Format PHP code:
```bash
vendor/bin/pint
```

Format JavaScript/TypeScript code:
```bash
npm run format
```

### Linting

Lint JavaScript/TypeScript:
```bash
npm run lint
```

### Running in Development Mode

Use the dev script to run server, queue, logs, and Vite concurrently:

```bash
composer run dev
```

Or manually:
```bash
php artisan serve
npm run dev
```

## Security Features

- File type validation (client & server)
- File size limits (5MB max)
- Temporary file cleanup (automatic deletion)
- Error handling for corrupted files
- UTF-8 encoding validation
- MIME type verification

## Troubleshooting

### Vite Manifest Error
If you see "Unable to locate file in Vite manifest", build the frontend assets:

```bash
npm run build
```

### File Upload Issues
- Ensure `storage/app/temp` directory exists and is writable
- Check file size is under 5MB
- Verify file is PDF or DOCX format

### Parsing Errors
- Ensure the PDF/DOCX contains extractable text (not just images)
- Try a different file to rule out corruption
- Check application logs for detailed error messages

### AI Analysis Not Working
- Verify `OPENAI_API_KEY` is set in `.env`
- Check API key is valid and has credits
- Application will fall back to rule-based analysis if AI fails
- Check logs for API error messages

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Acknowledgments

- Built with [Laravel](https://laravel.com)
- UI powered by [React](https://react.dev), [Tailwind CSS](https://tailwindcss.com), and [Framer Motion](https://www.framer.com/motion/)
- AI analysis powered by [OpenAI](https://openai.com)
- File parsing by [smalot/pdfparser](https://github.com/smalot/pdfparser), [Spatie PDF to Text](https://github.com/spatie/pdf-to-text), and [PhpOffice/PhpWord](https://github.com/PHPOffice/PHPWord)
- Analysis based on documented ATS best practices from industry research including TopResume, Jobscan, ResumeWorded, and Harvard Career Services
