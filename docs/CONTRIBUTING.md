# Contributing

## Development Setup

1. Fork the repository
2. Clone your fork: `git clone <your-fork-url>`
3. Install dependencies: `composer install && npm install`
4. Copy `.env.example` to `.env` and configure
5. Generate application key: `php artisan key:generate`
6. Run migrations: `php artisan migrate`
7. Build frontend: `npm run build`

## Development Workflow

1. Create a feature branch (`git checkout -b feature/amazing-feature`)
2. Make your changes
3. Run tests: `php artisan test`
4. Format code: `vendor/bin/pint && npm run format`
5. Lint code: `npm run lint`
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

## Code Standards

### PHP

- Follow Laravel coding standards
- Use Laravel Pint for formatting: `vendor/bin/pint`
- Write PHPDoc blocks for all public methods
- Use type hints for all parameters and return types
- Follow PSR-12 coding standards

### JavaScript/TypeScript

- Use TypeScript for type safety
- Follow ESLint rules: `npm run lint`
- Format with Prettier: `npm run format`
- Use functional components with hooks
- Follow React best practices

## Testing

- Write tests for all new features
- Run tests before committing: `php artisan test`
- Aim for high test coverage
- Test both happy paths and edge cases

## Documentation

- Update README.md for user-facing changes
- Update docs/ for technical documentation
- Add comments for complex logic
- Keep documentation concise and clear

## Security

- Never commit sensitive data (API keys, passwords, etc.)
- Validate all user inputs
- Use Laravel's built-in security features
- Follow security best practices

## Pull Request Process

1. Ensure all tests pass
2. Update documentation if needed
3. Ensure code is formatted and linted
4. Write a clear PR description
5. Reference any related issues

