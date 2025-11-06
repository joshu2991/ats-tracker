# ATS Tracker

A modern, AI-powered web application that analyzes resumes for ATS (Applicant Tracking System) compatibility. Upload your resume and receive comprehensive, actionable feedback on format, keywords, contact information, and overall ATS readiness.

🔗 **Live Demo**: [https://atstracker.northcodelab.com](https://atstracker.northcodelab.com)

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Development](#development)
- [Testing](#testing)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [License](#license)

## Features

- **AI-Powered Analysis**: Uses OpenAI GPT-4o-mini for intelligent content assessment
- **Dual Analysis System**: Combines rule-based parseability checks with AI analysis
- **Comprehensive Scoring**: Multi-dimensional scoring (0-100) across 5 categories
- **Actionable Suggestions**: Prioritized recommendations with impact estimates
- **User Feedback System**: Submit ratings and feedback to improve the service
- **Security**: Rate limiting, security headers, and content-based file validation
- **SEO Optimized**: Comprehensive SEO with structured data, Open Graph, Twitter Cards, and dynamic sitemap
- **Modern UI**: Built with React 19, TypeScript, and Tailwind CSS v4
- **Real-time Analysis**: Fast, responsive analysis with progress indicators

## Tech Stack

### Backend
- **Framework**: Laravel 12
- **PHP**: 8.4
- **Architecture**: Inertia.js v2 (SPA-like experience with server-side rendering)
- **AI Integration**: OpenAI GPT-4o-mini API
- **Email**: AWS SES
- **Database**: SQLite (development) / MySQL (production)

### Frontend
- **Framework**: React 19
- **Language**: TypeScript
- **Styling**: Tailwind CSS v4
- **Animations**: Framer Motion
- **Build Tool**: Vite

### Tools & Quality
- **Testing**: PHPUnit 11
- **Code Formatting**: Laravel Pint, Prettier
- **Linting**: ESLint 9
- **CI/CD**: GitHub Actions

## Prerequisites

Before you begin, ensure you have the following installed:

- **PHP 8.4** or higher
- **Composer** 2.x
- **Node.js** 22.x or higher
- **npm** 9.x or higher
- **SQLite** (for development) or **MySQL** (for production)
- **poppler-utils** (for PDF text extraction on Linux):
  ```bash
  sudo apt-get install poppler-utils  # Ubuntu/Debian
  brew install poppler  # macOS
  ```

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/joshu2991/ats-tracker.git
cd resume-checker
```

### 2. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### 3. Environment Configuration

```bash
# Copy the example environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Configure Environment Variables

Edit the `.env` file and configure the following:

```env
# Application
APP_NAME="ATS Tracker"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (SQLite for development)
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite

# OpenAI API (required for AI analysis)
OPENAI_API_KEY=your_openai_api_key_here

# AWS SES (optional, for email notifications)
AWS_ACCESS_KEY_ID=your_aws_access_key
AWS_SECRET_ACCESS_KEY=your_aws_secret_key
AWS_DEFAULT_REGION=us-east-1

# Google Analytics (optional)
GOOGLE_ANALYTICS_ID=your_ga_id
```

**Note**: For AI analysis to work, you must provide a valid OpenAI API key. The application will fall back to rule-based analysis if the API key is not configured.

### 5. Database Setup

```bash
# Create SQLite database (if using SQLite)
touch database/database.sqlite

# Run migrations
php artisan migrate
```

### 6. Build Frontend Assets

```bash
# Build for production
npm run build

# Or run in development mode (with hot reload)
npm run dev
```

### 7. Start the Development Server

```bash
# Start Laravel development server
php artisan serve

# Or use the combined dev script (requires concurrently)
composer run dev
```

Visit `http://localhost:8000/resume-checker` to get started.

## Configuration

### OpenAI API Setup

1. Sign up for an OpenAI account at [https://platform.openai.com](https://platform.openai.com)
2. Create an API key in your dashboard
3. Add the key to your `.env` file:
   ```env
   OPENAI_API_KEY=sk-your-api-key-here
   ```

### AWS SES Setup (Optional)

If you want email notifications for feedback submissions:

1. Create an AWS account and set up SES
2. Verify your email address in SES
3. Create IAM credentials with SES permissions
4. Add credentials to your `.env` file

### Rate Limiting

The application implements IP-based rate limiting:
- **Resume Analysis**: 10 analyses per hour per IP
- **Feedback Submission**: 5 submissions per hour per IP

These limits can be adjusted in `app/Providers/AppServiceProvider.php`.

### SEO Configuration (Optional)

The application includes comprehensive SEO features:
- **Structured Data**: JSON-LD schemas (Organization, WebSite, WebPage)
- **Social Sharing**: Open Graph and Twitter Card meta tags
- **Dynamic Sitemap**: Auto-generated XML sitemap at `/sitemap.xml`
- **Meta Tags**: Configurable title, description, and keywords per page

All SEO settings are configurable via `config/seo.php` and environment variables. See `.env.example` for available SEO configuration options.

## Usage

1. **Upload Resume**: Navigate to `/resume-checker` and upload a PDF or DOCX file
2. **Analysis**: The system will analyze your resume using both rule-based checks and AI
3. **Review Results**: View your ATS compatibility score and detailed feedback
4. **Improve**: Follow the actionable suggestions to improve your resume

## Development

### Development Workflow

```bash
# Run all development services (server, queue, logs, vite)
composer run dev

# Or run services individually:
php artisan serve          # Laravel server
npm run dev                # Vite dev server
php artisan queue:listen   # Queue worker (if using queues)
```

### Code Quality

```bash
# Format PHP code
vendor/bin/pint

# Format frontend code
npm run format

# Lint frontend code
npm run lint

# Check TypeScript types
npm run types
```

### Project Structure

```
resume-checker/
├── app/
│   ├── Http/
│   │   ├── Controllers/      # Application controllers
│   │   ├── Middleware/       # Custom middleware
│   │   └── Requests/         # Form request validation
│   ├── Models/               # Eloquent models
│   ├── Providers/            # Service providers
│   └── Services/             # Business logic services
│       └── Detectors/        # ATS detection algorithms
├── database/
│   ├── factories/            # Model factories
│   ├── migrations/           # Database migrations
│   └── seeders/              # Database seeders
├── resources/
│   ├── js/
│   │   ├── components/       # React components
│   │   ├── pages/            # Inertia pages
│   │   └── hooks/             # React hooks
│   └── prompts/              # AI prompt templates
├── routes/
│   └── web.php               # Web routes
├── tests/
│   ├── Feature/              # Feature tests
│   └── Unit/                 # Unit tests
└── .github/
    └── workflows/            # CI/CD workflows
```

## Testing

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ResumeAnalysisTest.php

# Run with filter
php artisan test --filter=test_resume_checker_page_loads

# Run with coverage (requires Xdebug)
php artisan test --coverage
```

The project includes:
- **Feature Tests**: Integration tests for resume analysis workflow
- **Unit Tests**: Tests for individual services and detectors
- **CI/CD**: Automated testing on push and pull requests

## Documentation

For detailed documentation, see the [docs](./docs/) directory:

- **[Architecture](./docs/ARCHITECTURE.md)** - System design and project structure
- **[Scoring](./docs/SCORING.md)** - Scoring algorithm and calculations
- **[Troubleshooting](./docs/TROUBLESHOOTING.md)** - Common issues and solutions
- **[Contributing](./docs/CONTRIBUTING.md)** - Development guidelines
- **[Deployment](./docs/DEPLOYMENT.md)** - Deployment guide

## Contributing

Contributions are welcome! Please read our [Contributing Guide](./docs/CONTRIBUTING.md) for details on:

- Code of conduct
- Development setup
- Code standards
- Testing requirements
- Pull request process

### Quick Contribution Steps

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests (`php artisan test`)
5. Format code (`vendor/bin/pint && npm run format`)
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## Acknowledgments

- Built with [Laravel](https://laravel.com/) and [React](https://react.dev/)
- AI analysis powered by [OpenAI](https://openai.com/)
- Icons by [Lucide](https://lucide.dev/)
