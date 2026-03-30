# 🎓 ScholarHub - Scholarship Management System

A modern, comprehensive scholarship management system built with PHP and MySQL.

![Status](https://img.shields.io/badge/Status-Production%20Ready-success)
![Completion](https://img.shields.io/badge/Completion-100%25-brightgreen)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)

## ✨ Features

### 🔐 Authentication & Security
- User registration with email verification
- Secure login with BCrypt password hashing
- Login rate limiting with account lockout
- Role-based access control (Admin, Staff, Student)
- Password reset with email codes
- Session management with CSRF protection on all forms
- Comprehensive audit logging

### 👥 User Management
- Create, edit, delete users (Admin)
- Activate/deactivate accounts
- Role assignment and management
- Profile picture upload
- User statistics dashboard

### 🎓 Scholarship Management
- Create and manage scholarships
- Eligibility requirements configuration
- Deadline and amount management
- Auto-close on deadline/max scholars
- Scholarship archive system
- Status workflow (Open/Closed/Archived)

### 📝 Application System
- 7-step multi-page application form
- Personal, academic, and financial information
- Document upload (multiple files)
- Draft saving functionality
- Application status tracking
- Intelligent screening system
- Status workflow (Draft → Submitted → Under Review → Approved/Rejected)

### 📄 Document Management
- Multi-file upload support
- Document verification workflow
- Bulk document operations
- Status tracking (Pending/Verified/Rejected)
- Staff document review interface

### 💰 Financial Tracking & Disbursements
- Auto-create disbursement on application approval
- Disbursement status workflow (Pending/Processing/Completed/Failed)
- Admin and staff disbursement management
- Student payout history view
- Batch processing support

### 🔍 Fraud Detection
- Automated fraud scoring on application submission
- Duplicate detection (name, email, documents)
- Suspicious pattern flagging
- Admin fraud review dashboard
- Flag/dismiss workflow with audit trail

### 📅 Interview Booking
- Admin/staff create interview slots
- Student self-booking interface
- Online and in-person interview types
- Booking status management
- Interview schedule display on application view

### 📋 Surveys & Feedback
- Admin survey builder with multiple question types
- Survey assignment to scholarship recipients
- Student survey submission
- Staff/admin results viewer
- Student feedback submission system

### 📊 Analytics & Reporting
- Real-time statistics dashboard
- Application status distribution
- Top scholarships analysis
- CSV/Excel/XLSX export
- PDF report generation
- Interactive charts (Chart.js)

### 📧 Email Integration
- SMTP configuration (Gmail support)
- Email queue system
- Application status notifications
- Password reset emails
- Deadline reminders

### 🔔 Notifications
- In-app notification system
- Application status updates
- Read/unread status tracking

### 📋 Audit Logging
- Comprehensive activity tracking
- User action logging with IP tracking
- Advanced filtering
- CSV export of logs

### ⚙️ Automation (Cron)
- Auto-close scholarships on deadline
- Auto-archive closed scholarships
- Email queue processor
- Deadline reminder system
- Manual trigger UI for staff

## 🚀 Quick Start

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer
- Web server (Apache/Nginx or XAMPP)

### Installation

1. Clone the repository
```bash
git clone https://github.com/yourusername/scholarhub.git
cd scholarhub
```

2. Install dependencies
```bash
composer install
```

3. Import database
```bash
mysql -u root -p < database/scholarshipmanagement.sql
```

4. Configure database connection — edit `config/db.php`

5. Configure email (optional) — edit `config/email.php`

6. Set upload permissions
```bash
chmod 755 uploads/
chmod 755 uploads/profiles/
```

7. Access the system
```
http://localhost/ScholarshipManagement/
```

See [INSTALLATION.md](INSTALLATION.md) for detailed setup instructions.

## 📁 Project Structure

```
ScholarshipManagement/
├── admin/              # Admin dashboard pages
├── staff/              # Staff dashboard pages
├── member/             # Student dashboard pages
├── auth/               # Authentication pages
├── assets/             # CSS, JS, images
├── config/             # Database and email config
├── controllers/        # Business logic controllers
├── helpers/            # Helper functions
├── includes/           # Shared header/sidebar/footer
├── cron/               # Automation scripts
├── migrations/         # Database migration files
├── uploads/            # User uploaded files
├── database/           # SQL schema
└── vendor/             # Composer dependencies
```

## 🎯 User Roles

| Role | Access |
|------|--------|
| **Admin** | Full system access, user management, all reports |
| **Staff** | Scholarship management, application review, documents |
| **Student** | Browse scholarships, apply, track status, surveys |

## 📊 System Statistics

- **Total Pages**: 50+
- **Database Tables**: 20+
- **User Roles**: 3
- **Application Statuses**: 7
- **Export Formats**: 3 (CSV, Excel, PDF)
- **Completion**: 100%

## 🔧 Technologies

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+ (PDO)
- **Frontend**: HTML5, CSS3, JavaScript, Chart.js
- **Exports**: PhpSpreadsheet, DOMPDF
- **Email**: PHPMailer (SMTP)
- **Security**: BCrypt, CSRF tokens, PDO prepared statements

## 🔒 Security

- BCrypt password hashing
- CSRF protection on every POST form and controller
- SQL injection prevention via PDO prepared statements
- XSS protection via input sanitization and `htmlspecialchars`
- File upload validation (type, size, extension)
- Login rate limiting with lockout
- Role-based access control
- Full audit trail

## 📧 Email Configuration

Edit `config/email.php`:
```
SMTP_HOST: smtp.gmail.com
SMTP_PORT: 587
SMTP_USERNAME: your-email@gmail.com
SMTP_PASSWORD: your-app-password
```

For Gmail: enable 2FA and generate an App Password.

## 🤖 Cron Jobs

| Script | Purpose |
|--------|---------|
| `cron/auto_close_scholarships.php` | Close scholarships past deadline |
| `cron/auto_archive_scholarships.php` | Archive closed scholarships |
| `cron/process_email_queue.php` | Send queued emails |
| `cron/send_deadline_reminders.php` | Notify students of upcoming deadlines |

Staff can also manually trigger these from the Automation page.

## 🐛 Known Issues

None. System is 100% complete and production-ready.

## 📝 License

This project is proprietary software. All rights reserved.

---

**Version**: 2.0.0
**Last Updated**: March 16, 2026
**Status**: Production Ready
**Completion**: 100%

Made with ❤️ for scholarship management
