## Scholarship Management System – Feature Overview

### 1. User Roles & Access Control
- **Role-based accounts**: Admin, Staff, Student (Applicant).
- **Role-aware dashboards**: Separate home screens for admin, staff, and students.
- **Session-based authentication**: Login, logout, and access checks on all protected pages.
- **Account activation**: Admin can activate/deactivate user accounts.
- **Role management**: Admin can change a user’s role (student ↔ staff ↔ admin).

### 2. Authentication & Security
- **User registration (students)**: Public registration form with validation for name, email, username, password, and contact details.
- **Admin-created accounts**: Admin can create staff and additional admin accounts from the Users module.
- **Password hashing**: All passwords stored using PHP’s `password_hash`.
- **Forgot password / reset by email code**:
  - Students, staff, and admins can request a reset using username or email.
  - System generates a 6‑digit code, stores it in session, and emails it to the account’s Gmail.
  - User enters the code plus a new password to complete reset.
- **CSRF protection**: Hidden CSRF tokens on sensitive POST forms (user management, scholarships, etc.).

### 3. User Management (Admin)
- **User statistics**: Dashboard cards showing counts of Admins, Staff, and Students.
- **User list with filters**: View all users or filter by role (Admins, Staff, Students).
- **Create user** (admin panel):
  - First/last name, email, username, strong password, phone, address.
- **Edit user**: Update first name, last name, and email via modal.
- **Activate / deactivate user**: Toggle account status (e.g., suspend users).
- **Delete user**: Confirmation modal with checkbox to prevent accidental deletion.
- **Inline role changes**: Change a user’s role from a dropdown in the table.

### 4. Scholarship Management (Admin & Staff)
- **Create scholarships**:
  - Title, description, organization, eligibility requirements, renewal rules, amount, deadline.
  - Default status set to “open”.
- **Edit scholarships**: Update all fields and change status (open / closed / cancelled).
- **Delete scholarships**: Soft administrative cleanup via admin interface.
- **Scholarship status tracking**: Open / closed / cancelled states shown with badges.
- **Application counts per scholarship**: Admin view shows how many applications each scholarship has received.
- **Scholarship archive**: Separate view for archived or closed scholarships (admin).

### 5. Student / Applicant Features
- **Student dashboard**:
  - Counts for submitted applications, active scholarships, pending reviews/messages.
  - Quick actions: apply for a scholarship, view applications.
- **Scholarship browsing**: Students see all open scholarships they are eligible for.
- **Apply for scholarship**:
  - Detailed application form (personal data, academic info, family/financial info, chosen scholarship, etc.).
  - File uploads / document handling where required by the scholarship.
- **Application tracking**:
  - View list of applications with status (submitted, pending, approved, rejected, etc.).
  - See per‑application details and decisions.

### 6. Staff Features
- **Staff dashboard**:
  - Count of open scholarships.
  - Quick actions for creating/editing scholarships and viewing applications.
- **Manage scholarships (staff view)**:
  - Similar to admin, but limited to scholarship creation and maintenance.
- **Application review workflow (simplified)**:
  - Staff can view applications assigned to a scholarship and update statuses.

### 7. Documents & Requirements
- **Scholarship documents configuration**:
  - `scholarship_documents` table tracks required documents per scholarship.
- **Applicant document uploads**:
  - Students can upload required files when applying.
- **Staff document view**:
  - Staff can see and download submitted documents per application.

### 8. Notifications & Messaging
- **In‑app notifications**:
  - `notifications` table stores user notifications (info, success, warning, error, application, deadline).
  - Student dashboard shows count of unseen messages/notifications.
- **Announcements (admin)**:
  - Admin can create announcements with type (info/success/warning/urgent).
  - Students see system‑wide announcements on dashboards.

### 9. Email & Communication
- **SMTP email integration (Gmail app password)**:
  - Central configuration in `config/email.php` using TLS and LOGIN auth.
- **Email queue & logs**:
  - `email_logs` table records queued/sent/failed emails with body and attempts.
  - Background script `cron/process_email_queue.php` can resend queued emails.
- **Email templates**:
  - Email verification (where used).
  - Login code (legacy).
  - Password reset code.
  - Application decision (approved / rejected) with optional comments.
- **Developer test scripts**:
  - CLI tools to send test codes and debug SMTP conversation.

### 10. Applications, Reviews & Awards
- **Applications table**:
  - Stores scholarship applications linked to users and scholarships.
  - Tracks status (submitted, pending, under_review, approved, rejected, etc.).
- **Analytics helper**:
  - Aggregates counts for applications, approvals, rejections, etc. for dashboards.
- **Awards & disbursements**:
  - `awards` table for approved scholarship awards (amount, status, notes).
  - `disbursements` table for payments tied to awards (amount, date, method, status).

### 11. Analytics & Reporting
- **Admin analytics dashboard**:
  - Total applications, open scholarships, pending applications, approved/rejected counts, total users.
  - Recent applications table with applicant + scholarship.
- **Analytics exports**:
  - CSV / Excel exports of top scholarships and application datasets.
  - Sample export files included to verify formatting.
- **Helper scripts**:
  - Export test scripts for CSV, XLSX, PDF to validate libraries.

### 12. Automation & Cron Jobs
- **Auto‑close scholarships**:
  - `cron/auto_close_scholarships_new.php` closes scholarships when deadlines pass.
- **Auto‑archive scholarships**:
  - `cron/auto_archive_scholarships.php` moves old/closed scholarships into an archive.
- **Email queue processor**:
  - `cron/process_email_queue.php` sends queued emails and updates statuses.
- **Deadline reminder framework**:
  - `deadline_reminders` table prepared for 7‑day / 1‑day / deadline alerts.

### 13. Auditing & Logging
- **Audit logs**:
  - `audit_logs` table records who changed what (user, entity type/id, old/new values, IP, user agent).
- **Login attempts**:
  - `login_attempts` table tracks email, IP, success flag, and timestamp for security analysis.
- **Error logging**:
  - Key places log to PHP error log when DB or SMTP issues occur.

### 14. Database & Setup Utilities
- **Auto schema migration**:
  - `config/db.php` ensures all required tables exist on first connection.
- **Connection tester UI**:
  - `test_connection.php` visual page that checks DB connection, table existence, and row counts.
- **SQL dump**:
  - `database/scholarshipmanagement (1).sql` sample schema/data for seeding.
- **Migration scripts**:
  - `migrations/` and `scripts/apply_migrations.php` for evolving the schema.

### 15. Developer Utilities
- **Dev test scripts** under `scripts/dev_tests/`:
  - Registration + reset end‑to‑end tests.
  - Email delivery diagnostics (including reading Apache/PHP logs).
  - Export tests for applications and reports.
- **Sample data files**:
  - CSV/PDF/XLSX samples for exports and reporting validation.

### 16. UI Features — Prioritized

- **Must-have (Launch)**: Applicant catalog with search/filters, scholarship detail pages, multi-step application form with save-as-draft and file uploads, applicant dashboard (application status), registration/login/password reset, profile management, role-based navigation, responsive layout, basic document view/download, inline form validation and progress indicators.
- **Important (Phase 2)**: Staff application management (view/update status, assign staff (staff/admin)), review queue & review UI (score/checklist performed by staff), in-app + email notifications and reminder settings, scholarship create/edit/publish UI for staff, global search across users/scholarships/applications, audit/activity logs UI, basic reports & CSV/XLS exports.
- **Nice-to-have (Later)**: Analytics dashboards and visualizations, advanced document previews and verification workflows, automation/cron control UI, localization/i18n support, optional 2FA and session management UI, help/FAQ and contextual tooltips, feature flags/admin toggles, accessibility enhancements and WCAG compliance.

Add these prioritized UI items to guide development and release planning — implement Must-have first, then roll out Important and Nice-to-have features in subsequent phases.

