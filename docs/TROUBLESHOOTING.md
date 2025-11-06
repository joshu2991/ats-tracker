# Troubleshooting

## Vite Manifest Error

If you see "Unable to locate file in Vite manifest", build the frontend assets:

```bash
npm run build
```

## File Upload Issues

- Ensure `storage/app/temp` directory exists and is writable
- Check file size is under 5MB
- Verify file is PDF or DOCX format

## Parsing Errors

- Ensure the PDF/DOCX contains extractable text (not just images)
- Try a different file to rule out corruption
- Check application logs for detailed error messages

## AI Analysis Not Working

- Verify `OPENAI_API_KEY` is set in `.env`
- Check API key is valid and has credits
- Application will fall back to rule-based analysis if AI fails
- Check logs for API error messages

## Database Connection Issues

```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

## Permission Issues

```bash
# Fix storage permissions
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

## Cache Issues

```bash
# Clear all caches
php artisan optimize:clear
```

## Frontend Build Issues

```bash
# Rebuild frontend assets
rm -rf node_modules package-lock.json
npm install
npm run build
```

## Rate Limiting

The application implements IP-based rate limiting:
- **Resume Analysis**: 10 analyses per hour per IP address
- **Feedback Submission**: 5 feedback submissions per hour per IP address

When rate limits are exceeded, users receive friendly error messages via toast notifications.

## Email Notifications

If email notifications via AWS SES are not working:
- Verify AWS credentials are configured in `.env`
- Check AWS SES region is correct
- Ensure email address is verified in AWS SES (for sandbox mode)
- Check application logs for detailed error messages

