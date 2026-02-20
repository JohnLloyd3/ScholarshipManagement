# Capstone Feature Map – Scholarship Management System

This document maps your capstone requirements (Sections 2 & 3) to the implemented features in this system.

---

## Section 2: Scholarship Management

| # | Requirement | Implementation |
|---|-------------|----------------|
| **2.2** | Applicant registration form | `auth/register.php` – Full registration with username, password, name, email, phone, address, role (student/staff/reviewer), secret question/answer. Handled by `controllers/AuthController.php` (action=register). |
| **2.3** | Scholarship posting form | `admin/scholarships.php` – Create form: title, description, organization, status (open/closed), eligibility requirements. Staff/Admin only. `AdminController.php` (create_scholarship). |
| **2.4** | Scholarship requirements validation | `controllers/ApplicationController.php` – On submit, validates applicant GPA and full-time status against `eligibility_requirements` (e.g. "GPA >= 3.5", "Enrolled full-time"). Blocks submission if requirements not met. |
| **2.5** | Scholarship list management (add/edit/delete) | `admin/scholarships.php` – Table of all scholarships with Edit and Delete. Create/Edit form at top. `AdminController.php`: create_scholarship, update_scholarship, delete_scholarship. |
| **2.6** | Prevent duplicate scholarship entries | Database: `scholarships` has UNIQUE key on `(title, organization)`. `AdminController.php` catches duplicate key and shows: "A scholarship with this title and organization already exists." |
| **2.7** | Scholarship status tracking (open/closed) | `scholarships.status` enum ('open','closed'). Admin form has Status dropdown. Only **open** scholarships appear on member Apply page and can be applied for. |

---

## Section 3: Application Management

| # | Requirement | Implementation |
|---|-------------|----------------|
| **3.1** | Scholarship Application Form | `member/apply_scholarship.php` – Select scholarship, then form: application title, GPA, full-time checkbox, additional info, application details (required), optional document upload. Shows eligibility requirements for selected scholarship. |
| **3.2** | Student application submission | `controllers/ApplicationController.php` (action=create). Saves to `applications` with user_id, scholarship_id, title, details, document path, status=submitted. Prevents duplicate application per user per scholarship. |
| **3.3** | Validate Application Entries | Server-side in `ApplicationController.php`: scholarship selected, title required, GPA required and 0–4.0, requirements validation (2.4). Form uses `required` on title, details, GPA. |
| **3.4** | Review Applicant List | `admin/applications.php` – Table of all applications with search and filters (status, reviewer, scholarship). Edit link opens form to update title, details, status, reviewer, view document. |
| **3.5** | Approve/Reject Applications | `admin/applications.php` – Per row: Approve / Reject / Pending buttons (set_application_status). Edit form: Status dropdown (approved/rejected). Both paths update `applications.status` and `reviews.status`. |
| **3.6** | Notify Applicants | On approve/reject: (1) Insert into `notifications` for applicant (in-app). (2) Email via `config/email.php` – `sendApplicationDecisionEmail()`. Optional reviewer comments in admin edit form are included in notification message and email. Member can view all notifications at `member/notifications.php` (mark as read). |

---

## Quick Reference – Main Files

- **Registration:** `auth/register.php`, `controllers/AuthController.php`
- **Scholarship CRUD:** `admin/scholarships.php`, `controllers/AdminController.php` (create_scholarship, update_scholarship, delete_scholarship)
- **Apply & submit:** `member/apply_scholarship.php`, `controllers/ApplicationController.php`
- **Review list & approve/reject:** `admin/applications.php`, `controllers/AdminController.php` (set_application_status, update_application)
- **Notifications (in-app + email):** `config/email.php` (sendApplicationDecisionEmail), `member/notifications.php`, `notifications` table

All features above are implemented and wired for your capstone.
