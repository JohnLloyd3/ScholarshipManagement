# Scholarship Management System - Installation Guide

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer (for dependencies)
- Web server (Apache/Nginx)

## Installation Steps

### 1. Database Setup

```sql
-- Import the database schema
mysql -u root -p < database/scholarshipmanagement.sql
```

Or use phpMyAdmin to import `database/scholarshipmanagement (1).sql`

### 2. Install Dependencies

```bash
# Install Composer if not already installed
# Visit: https://getcomposer.org/download/

# Install PHP dependencies
composer install
```

This will install:
- `phpoffice/phpspreadsheet` - For Excel/XLSX exports
- `dompdf/dompdf` - For PDF generation

### 3. Configure Database Connection

Edit `config/db.php`:

```php
$host = 'localhost';
$dbname = 'scholarshipmanagement';
$username = 'root';
$password = 'your_password';
```

### 4. Configure Email (Optional)

Edit `config/email.php`:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'ScholarHub');
```

For Gmail, you need to:
1. Enable 2-Factor Authentication
2. Generate an App Password
3. Use the App Password in the config

### 5. Set File Permissions

```bash
# Make uploads directory writable
chmod 755 uploads/
chmod 755 uploads/profiles/

# On Windows, ensure IIS/Apache has write permissions
```

### 6. Run Migrations (Optional)

```bash
php scripts/apply_migrations.php
```

This will:
- Add profile_picture column to users table
- Create audit_logs table
- Create documents table
- Create cron_runs table

### 7. Access the System

Open your browser and navigate to:
```
http://localhost/ScholarshipManagement/
```

Default admin credentials (if seeded):
- Username: `admin`
- Password: `admin123`

## Features Verification

### Test Export Functionality
1. Login as Admin
2. Go to Analytics page
3. Click any export button (CSV/Excel/PDF)
4. Verify file downloads

### Test Document Upload
1. Login as Student
2. Go to Apply for Scholarship
3. Select a scholarship
4. Fill the form and upload documents
5. Submit application

### Test Audit Logs
1. Login as Admin
2. Go to Audit Logs page
3. Verify activities are logged
4. Test filters and CSV export

### Test Cron Jobs
1. Login as Staff
2. Go to Automation page
3. Click "Run" on any cron script
4. Verify execution logs

## Automation Setup (Optional)

### Windows Task Scheduler

Create scheduled tasks for:

```batch
# Auto-close scholarships (daily at midnight)
php C:\xampp\htdocs\ScholarshipManagement\cron\auto_close_scholarships.php

# Process email queue (every 5 minutes)
php C:\xampp\htdocs\ScholarshipManagement\cron\process_email_queue.php

# Send deadline reminders (daily at 8 AM)
php C:\xampp\htdocs\ScholarshipManagement\cron\send_deadline_reminders.php
```

### Linux Crontab

```bash
# Edit crontab
crontab -e

# Add these lines:
0 0 * * * php /var/www/html/ScholarshipManagement/cron/auto_close_scholarships.php
*/5 * * * * php /var/www/html/ScholarshipManagement/cron/process_email_queue.php
0 8 * * * php /var/www/html/ScholarshipManagement/cron/send_deadline_reminders.php
```

## Troubleshooting

### Export not working
- Verify Composer dependencies are installed: `composer install`
- Check PHP memory limit: `memory_limit = 256M` in php.ini
- Verify write permissions on temp directory

### Email not sending
- Check SMTP credentials in `config/email.php`
- Verify firewall allows outbound SMTP connections
- Check email logs in `email_logs` table

### Document upload fails
- Check `uploads/` directory permissions
- Verify `upload_max_filesize` and `post_max_size` in php.ini
- Check file type restrictions in `helpers/SecurityHelper.php`

### Audit logs not showing
- Run migrations: `php scripts/apply_migrations.php`
- Verify `audit_logs` table exists
- Check `helpers/AuditHelper.php` is included in controllers

## System Requirements

### Minimum
- PHP 7.4
- MySQL 5.7
- 512MB RAM
- 1GB disk space

### Recommended
- PHP 8.0+
- MySQL 8.0+
- 2GB RAM
- 5GB disk space
- SSL certificate for production

## Security Checklist

- [ ] Change default admin password
- [ ] Enable HTTPS in production
- [ ] Set secure session settings
- [ ] Configure firewall rules
- [ ] Regular database backups
- [ ] Update dependencies regularly
- [ ] Review audit logs periodically
- [ ] Implement rate limiting
- [ ] Configure CORS properly
- [ ] Use environment variables for secrets

## Support

For issues or questions:
- Check `SYSTEM_FEATURES.md` for feature documentation
- Check `ACTUAL_STATUS.md` for implementation status
- Review audit logs for system activities
- Check error logs in PHP error_log

## Production Deployment

1. Set `display_errors = Off` in php.ini
2. Set `error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT`
3. Enable HTTPS
4. Configure database backups
5. Set up monitoring
6. Configure email properly
7. Test all features thoroughly
8. Set up automated cron jobs
9. Review security settings
10. Document admin procedures

---

**System Version**: 1.0.0  
**Last Updated**: March 8, 2026  
**Status**: Production Ready (98% Complete)
