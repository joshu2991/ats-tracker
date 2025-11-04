# Resume ATS Checker

A modern web application that analyzes resumes for ATS (Applicant Tracking System) compatibility. Upload your resume in PDF or DOCX format and receive instant feedback on format, keywords, contact information, and overall ATS readiness.

## Features

### ✨ Core Features

- **File Upload**: Drag & drop or click to upload resumes (PDF or DOCX, max 5MB)
- **Text Extraction**: Automatically extracts text from PDF and DOCX files
- **Section Detection**: Identifies key resume sections (Experience, Education, Skills)
- **Contact Information Extraction**: Detects email, phone, LinkedIn, and GitHub URLs
- **ATS Scoring**: Comprehensive scoring system (0-100 points) across multiple categories:
  - **Format Score** (0-30 pts): Section presence and formatting
  - **Keyword Score** (0-40 pts): Technical keywords detection
  - **Contact Score** (0-10 pts): Contact information completeness
  - **Length & Clarity Score** (0-20 pts): Word count, action verbs, bullet points
- **Actionable Suggestions**: Prioritized recommendations to improve your resume
- **Keyword Analysis**: Lists all technical keywords found and suggests missing ones
- **Visual Dashboard**: Beautiful, responsive UI with color-coded progress indicators
- **Dark Mode Support**: Full dark mode compatibility

### 🎯 Scoring Breakdown

- **Green (80-100)**: Excellent - Your resume is ATS-ready
- **Yellow (60-79)**: Good - Minor improvements needed
- **Red (0-59)**: Needs Improvement - Significant changes recommended

## Tech Stack

### Backend
- **Laravel 12** - PHP framework
- **Inertia.js v2** - Modern monolith architecture
- **smalot/pdfparser** - PDF text extraction
- **phpoffice/phpword** - DOCX text extraction

### Frontend
- **React 19** - UI library
- **TypeScript** - Type safety
- **Tailwind CSS v4** - Styling
- **Inertia.js React** - Server-driven single-page apps

### Development Tools
- **Laravel Pint** - Code formatting
- **PHPUnit** - Testing framework
- **ESLint** - JavaScript linting
- **Prettier** - Code formatting

## Requirements

- PHP 8.2 or higher
- Composer
- Node.js 18+ and npm
- SQLite (default) or any Laravel-supported database

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

### 5. Create Storage Directories

```bash
mkdir -p storage/app/temp
chmod -R 775 storage bootstrap/cache
```

### 6. Build Frontend Assets

For development:
```bash
npm run dev
```

For production:
```bash
npm run build
```

### 7. Run the Application

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
4. Review your ATS score and detailed breakdown
5. Check suggestions for improvement
6. Review detected keywords and recommendations

### File Requirements

- **Formats**: PDF (.pdf) or DOCX (.docx)
- **Maximum Size**: 5MB
- **Text-based**: Images-only PDFs may not work correctly

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
│       ├── ResumeParserService.php
│       ├── SectionDetectorService.php
│       ├── ATSScorerService.php
│       └── KeywordAnalyzerService.php
├── resources/
│   └── js/
│       ├── pages/
│       │   └── ResumeChecker.tsx
│       └── components/
│           ├── ScoreDisplay.tsx
│           ├── ScoreBreakdown.tsx
│           ├── ProgressBar.tsx
│           ├── SuggestionsPanel.tsx
│           └── KeywordsList.tsx
├── routes/
│   └── web.php
├── storage/
│   └── app/
│       └── temp/          # Temporary file storage
└── tests/
    └── Feature/
        └── ResumeAnalysisTest.php
```

## Key Services

### ResumeParserService
Extracts text from PDF and DOCX files. Handles errors gracefully and provides clear error messages.

### SectionDetectorService
Detects resume sections and contact information using regex patterns:
- Sections: Experience, Education, Skills
- Contact: Email, Phone, LinkedIn, GitHub

### ATSScorerService
Calculates scores for:
- Format (0-30 points)
- Contact (0-10 points)
- Length & Clarity (0-20 points)
- Generates actionable suggestions

### KeywordAnalyzerService
Analyzes technical keywords (50+ keywords including languages, frameworks, tools, databases) and calculates keyword score (0-40 points).

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

## Architecture

### Stateless Design
- No database persistence required
- All processing happens in memory
- Temporary files are automatically deleted after analysis
- Perfect for serverless or stateless deployments

### File Processing Flow
1. File uploaded via Inertia form
2. File stored temporarily in `storage/app/temp`
3. Text extracted using appropriate parser
4. Analysis performed on extracted text
5. Results returned to frontend via Inertia
6. Temporary file deleted in `finally` block (critical)

### Security Features
- File type validation (client & server)
- File size limits
- Temporary file cleanup
- Error handling for corrupted files

## Scoring Algorithm

### Format Score (30 points max)
- Experience section: +10 pts
- Education section: +10 pts
- Skills section: +5 pts
- Bullet points detected: +5 pts

### Keyword Score (40 points max)
- 15+ unique keywords: 40 pts
- 10-14 keywords: 30 pts
- 5-9 keywords: 20 pts
- <5 keywords: 10 pts

### Contact Score (10 points max)
- Valid email: +3 pts
- Phone number: +2 pts
- LinkedIn URL: +3 pts
- GitHub/portfolio URL: +2 pts

### Length & Clarity Score (20 points max)
- 400-800 words (ideal): +10 pts
- Action verbs present: +5 pts
- Bullet points present: +5 pts

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
- UI powered by [React](https://react.dev) and [Tailwind CSS](https://tailwindcss.com)
- File parsing by [smalot/pdfparser](https://github.com/smalot/pdfparser) and [PhpOffice/PhpWord](https://github.com/PHPOffice/PHPWord)

