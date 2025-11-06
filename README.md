# ATS Tracker

A modern, AI-powered web application that analyzes resumes for ATS (Applicant Tracking System) compatibility. Upload your resume and receive comprehensive, actionable feedback on format, keywords, contact information, and overall ATS readiness.

🔗 **Live Demo**: [https://atstracker.northcodelab.com](https://atstracker.northcodelab.com)

## Features

- **AI-Powered Analysis**: Uses OpenAI GPT-4o-mini for intelligent content assessment
- **Dual Analysis System**: Combines rule-based parseability checks with AI analysis
- **Comprehensive Scoring**: Multi-dimensional scoring (0-100) across 5 categories
- **Actionable Suggestions**: Prioritized recommendations with impact estimates
- **User Feedback System**: Submit ratings and feedback to improve the service
- **Security**: Rate limiting, security headers, and content-based file validation

## Tech Stack

**Backend**: Laravel 12, Inertia.js v2, OpenAI API, AWS SES  
**Frontend**: React 19, TypeScript, Tailwind CSS v4, Framer Motion  
**Tools**: PHPUnit, Laravel Pint, ESLint, Prettier

## Quick Start

```bash
# 1. Clone and install
git clone <repository-url>
cd ats-tracker
composer install && npm install

# 2. Configure environment
cp .env.example .env
php artisan key:generate

# 3. Setup database
php artisan migrate

# 4. Build assets
npm run build

# 5. Run application
php artisan serve
```

Visit `http://localhost:8000/resume-checker` to get started.

## Documentation

For detailed documentation, see the [docs](./docs/) directory:

- [Architecture](./docs/ARCHITECTURE.md) - System design and project structure
- [Scoring](./docs/SCORING.md) - Scoring algorithm and calculations
- [Troubleshooting](./docs/TROUBLESHOOTING.md) - Common issues and solutions
- [Contributing](./docs/CONTRIBUTING.md) - Development guidelines
- [Deployment](./DEPLOYMENT.md) - Deployment guide

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
