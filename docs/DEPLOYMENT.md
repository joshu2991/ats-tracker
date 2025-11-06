# Deployment Guide

This guide documents the deployment process for ATS Tracker on AWS Lightsail.

## Prerequisites

- SSH access to the Lightsail server
- Git repository access
- Composer installed on the server
- Node.js 18+ and npm installed on the server
- MySQL database configured
- Environment variables configured in `.env` file
- AWS SES credentials configured (for email notifications)

## Manual Deployment Steps

### 1. SSH into Server

```bash
ssh username@your-server-ip
```

### 2. Navigate to Project Directory

```bash
cd /path/to/your/project
```

### 3. Pull Latest Changes

```bash
git pull origin main
```

### 4. Install/Update PHP Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 5. Install Frontend Dependencies

```bash
npm ci
```

### 6. Build Frontend Assets

```bash
npm run build
```

### 7. Run Database Migrations

```bash
php artisan migrate --force
```

**Note**: Use `--force` only in production when you're sure about the migrations. In development, omit this flag.

### 8. Clear and Cache Configuration

```bash
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 9. Clear Application Cache

```bash
php artisan cache:clear
```

### 10. Optimize Autoloader

```bash
composer dump-autoload --optimize
```

## Production Checklist

Before deploying, ensure:

- [ ] Environment variables are configured in `.env`
- [ ] Database connection is working (`php artisan migrate:status`)
- [ ] Storage permissions are correct (`chmod -R 775 storage bootstrap/cache`)
- [ ] Queue workers are running (if using queues): `php artisan queue:work`
- [ ] Log rotation is configured (if applicable)
- [ ] SSL certificates are valid (if using HTTPS)
- [ ] AWS SES is configured and verified
- [ ] OpenAI API key is set 
- [ ] Google Analytics ID is set (if using analytics)

## Troubleshooting

### Database Connection Issues

```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

### Permission Issues

```bash
# Fix storage permissions
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

### Cache Issues

```bash
# Clear all caches
php artisan optimize:clear
```

### Frontend Build Issues

```bash
# Rebuild frontend assets
rm -rf node_modules package-lock.json
npm install
npm run build
```

### Migration Issues

```bash
# Check migration status
php artisan migrate:status

# Rollback last migration (if needed)
php artisan migrate:rollback
```

## Rollback Procedure

If a deployment fails, you can rollback:

### 1. Revert to Previous Git Commit

```bash
git log --oneline -10  # View recent commits
git checkout <previous-commit-hash>
```

### 2. Rebuild Assets

```bash
npm run build
```

### 3. Clear Caches

```bash
php artisan optimize:clear
```

### 4. Run Migrations (if needed)

```bash
php artisan migrate --force
```

## CI/CD Integration

This process is now automated via `.github/workflows/deploy.yml`.

Manual deployment should only be used for:
- Initial server setup
- Recovery from deployment failures
- Emergency hotfixes

### Automated Deployment

The GitHub Actions workflow automatically:
1. Runs tests before deployment
2. SSH into the server
3. Pulls latest changes
4. Installs dependencies
5. Builds frontend assets
6. Runs migrations
7. Clears and caches configuration
8. Optimizes autoloader

### Required GitHub Secrets

Configure these secrets in your GitHub repository settings:

- `SSH_PRIVATE_KEY`: Private SSH key for server access
- `SSH_USER`: SSH username
- `SSH_HOST`: Server IP address or hostname
- `SSH_PATH`: Full path to project directory on server

## Post-Deployment Verification

After deployment, verify:

1. **Application is accessible**: Visit the application URL
2. **Health check**: Visit `/health` endpoint
3. **Database**: Check that migrations ran successfully
4. **Logs**: Monitor `storage/logs/laravel.log` for errors
5. **Features**: Test resume upload and analysis
6. **Email**: Test feedback submission (if configured)

## Maintenance

### Regular Tasks

- Monitor application logs: `tail -f storage/logs/laravel.log`
- Check database backups (if configured)
- Monitor server resources (CPU, memory, disk)
- Update dependencies periodically: `composer update && npm update`

### Log Rotation

Configure log rotation to prevent disk space issues:

```bash
# Edit logrotate configuration
sudo nano /etc/logrotate.d/laravel

# Add configuration:
/path/to/project/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

