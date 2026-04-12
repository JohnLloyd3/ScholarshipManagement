# 📖 ScholarHub - User Manual

> **Version**: 2.0.0 | **Last Updated**: March 16, 2026

---

## Table of Contents

1. [Getting Started](#1-getting-started)
2. [Admin Guide](#2-admin-guide)
3. [Staff Guide](#3-staff-guide)
4. [Student Guide](#4-student-guide)
5. [Troubleshooting](#5-troubleshooting)

---

## 1. Getting Started

### 1.1 Accessing the System

Open your browser and go to:
```
http://localhost/ScholarshipManagement/
```

### 1.2 Login

1. Click **Login** on the homepage
2. Enter your **username** and **password**
3. Click **Sign In**

> If you enter the wrong password 5 times, your account will be locked for 15 minutes.

### 1.3 Default Admin Account

| Field    | Value               |
|----------|---------------------|
| Email    | admin@scholarhub.com |
| Password | (set during setup)  |

### 1.4 Forgot Password

1. Click **Forgot Password** on the login page
2. Enter your registered username or email
3. Check your email for a reset code
4. Enter the code and set a new password

### 1.5 User Roles Overview

| Role    | What They Can Do                                      |
|---------|-------------------------------------------------------|
| Admin   | Full access — users, scholarships, reports, settings  |
| Staff   | Manage scholarships, review applications, documents   |
| Student | Browse scholarships, apply, track status, surveys     |

---

## 2. Admin Guide

### 2.1 Dashboard

The admin dashboard shows a real-time overview:
- Total users, scholarships, applications
- Recent activity feed
- Quick action buttons

Navigate using the **left sidebar**.

---

### 2.2 User Management

**Path**: Sidebar → Users

#### Create a User
1. Click **Add User**
2. Fill in name, email, role (Admin / Staff / Student)
3. Set a temporary password
4. Click **Save**

#### Edit a User
1. Find the user in the list
2. Click the **Edit** (pencil) icon
3. Update fields and click **Save**

#### Activate / Deactivate
- Click the **toggle** next to the user to enable or disable their account

#### Delete a User
1. Click the **Delete** (trash) icon
2. Confirm the action

---

### 2.3 Scholarship Management

**Path**: Sidebar → Scholarships

#### Create a Scholarship
1. Click **Add Scholarship**
2. Fill in:
   - Title, description
   - Amount and slots
   - Eligibility requirements (GPA, year level, etc.)
   - Application deadline
3. Set status to **Open** to make it visible to students
4. Click **Save**

#### Edit / Close / Archive
- **Edit**: Click the pencil icon on any scholarship
- **Close**: Change status to Closed (stops new applications)
- **Archive**: Move closed scholarships to the archive for record-keeping

#### Scholarship Archive
**Path**: Sidebar → Scholarship Archive

View and restore archived scholarships.

---

### 2.4 Application Management

**Path**: Sidebar → Applications

#### Review an Application
1. Click an application row to open it
2. Review personal, academic, and financial info
3. Check uploaded documents
4. See the fraud score badge (if flagged)
5. Change status:
   - **Under Review** → being evaluated
   - **Approved** → triggers auto-disbursement
   - **Rejected** → notifies the student

#### Bulk Status Update
1. Check multiple applications using the checkboxes
2. Select a status from the **Bulk Action** dropdown
3. Click **Apply**

---

### 2.5 Document Management

**Path**: Sidebar → Applications → (open application) → Documents tab

#### Verify a Document
1. Click on a document to preview it
2. Click **Verify** to mark it as verified
3. Or click **Reject** with a reason

#### Bulk Operations
- Select multiple documents and use **Bulk Verify** or **Bulk Reject**

---

### 2.6 Disbursements

**Path**: Sidebar → Disbursements

Disbursements are **auto-created** when an application is approved.

#### Update Disbursement Status
1. Find the disbursement record
2. Click **Edit**
3. Change status:
   - **Pending** → waiting to process
   - **Processing** → payment in progress
   - **Completed** → paid out
   - **Failed** → payment failed
4. Click **Save**

#### Batch Processing
1. Filter disbursements by status = Pending
2. Select multiple records
3. Click **Batch Process**

---

### 2.7 Fraud Detection

**Path**: Sidebar → Fraud Detection

The system automatically scores each application for fraud risk.

#### Fraud Score Levels
| Score  | Level    | Meaning                        |
|--------|----------|--------------------------------|
| 0–30   | Low      | No suspicious activity         |
| 31–60  | Medium   | Some flags, review recommended |
| 61–100 | High     | Strong fraud indicators        |

#### Review a Flagged Application
1. Click on a flagged application
2. Review the fraud indicators (duplicate name, email, documents, etc.)
3. Choose:
   - **Flag** — mark as fraudulent, restrict application
   - **Dismiss** — clear the flag, mark as legitimate

All fraud actions are logged in the audit trail.

---

### 2.8 Interview Booking

**Path**: Sidebar → Interview Slots / Interview Bookings

#### Create an Interview Slot
1. Go to **Interview Slots**
2. Click **Add Slot**
3. Set date, time, type (Online / In-Person), and location/link
4. Click **Save**

#### View Bookings
- Go to **Interview Bookings** to see all student bookings
- Update booking status (Scheduled / Completed / Cancelled)

---

### 2.9 Surveys

**Path**: Sidebar → Surveys

#### Build a Survey
1. Click **Create Survey**
2. Add a title and description
3. Click **Add Question** and choose type:
   - Text, Multiple Choice, Rating, Yes/No
4. Click **Save**

#### Assign a Survey
1. Open a survey
2. Click **Assign**
3. Select the scholarship or specific recipients
4. Click **Assign Survey**

#### View Results
- Go to **Survey Results** to see all responses per survey

---

### 2.10 Feedback

**Path**: Sidebar → Feedback

View all student feedback submissions. You can:
- Filter by scholarship or date
- Mark feedback as reviewed
- Delete inappropriate entries

---

### 2.11 Analytics & Reports

**Path**: Sidebar → Analytics

View charts and statistics:
- Application status distribution (doughnut chart)
- Top scholarships by applications (bar chart)
- User role breakdown

#### Export Reports
1. Set a date range (optional)
2. Click **Export CSV**, **Export Excel**, or **Export PDF**

---

### 2.12 Email Queue

**Path**: Sidebar → Email Queue

View all outgoing emails and their delivery status. Failed emails can be retried manually.

---

### 2.13 Audit Logs

**Path**: Sidebar → Audit Logs

Every action in the system is logged here.

#### Filter Logs
- By user, action type, entity, or date range

#### Export
- Click **Export CSV** to download the log

---

### 2.14 Automation (Cron)

**Path**: Sidebar → Automation (Staff page, also accessible to Admin)

Manually trigger scheduled tasks:
- Auto-close scholarships past deadline
- Auto-archive closed scholarships
- Process email queue
- Send deadline reminders

> These also run automatically if cron jobs are configured on the server.

---

## 3. Staff Guide

Staff have access to most features except user management and system-level settings.

### 3.1 Dashboard

Shows pending applications, recent activity, and quick stats.

---

### 3.2 Post a Scholarship

**Path**: Sidebar → Post Scholarship

1. Fill in scholarship details (title, amount, slots, deadline, eligibility)
2. Click **Submit**

The scholarship goes live immediately with Open status.

---

### 3.3 Manage Scholarships

**Path**: Sidebar → Scholarships

- Edit existing scholarships
- Close or archive them
- View applicant counts per scholarship

---

### 3.4 Review Applications

**Path**: Sidebar → Applications or Pending Applications

1. Click an application to open it
2. Review all submitted information and documents
3. Check the interview schedule (if booked)
4. Update the application status
5. Add notes if needed

---

### 3.5 Document Verification

**Path**: Sidebar → Documents

1. Filter by status = Pending
2. Click a document to preview
3. Click **Verify** or **Reject**

---

### 3.6 Disbursements

**Path**: Sidebar → Disbursements

View and update disbursement records for approved scholars. Staff can update status but cannot delete records.

---

### 3.7 Interview Slots

**Path**: Sidebar → Interview Slots

Create and manage interview time slots. Students will see available slots and book them.

---

### 3.8 Survey Results

**Path**: Sidebar → Survey Results

View responses submitted by students for assigned surveys.

---

### 3.9 Feedback

**Path**: Sidebar → Feedback

View and manage student feedback submissions.

---

### 3.10 Reports & Analytics

**Path**: Sidebar → Analytics / Reports

Same as admin — view charts and export reports in CSV, Excel, or PDF.

---

### 3.11 Audit Logs

**Path**: Sidebar → Audit Logs

View-only access to the system audit trail.

---

### 3.12 Automation

**Path**: Sidebar → Automation

Manually trigger cron jobs and view the cron execution log.

---

## 4. Student Guide

### 4.1 Registration

1. Go to the homepage and click **Register**
2. Fill in your name, email, and password
3. Submit the registration form
4. Log in with your new account

---

### 4.2 Dashboard

Your dashboard shows:
- Active applications and their statuses
- Available scholarships
- Upcoming interview schedules
- Unread notifications

---

### 4.3 Browse Scholarships

**Path**: Sidebar → Scholarships

1. Browse the list of open scholarships
2. Click a scholarship to view details:
   - Amount, slots, deadline
   - Eligibility requirements
3. Click **Apply Now** if you qualify

---

### 4.4 Submitting an Application

The application is a 7-step form:

| Step | What to Fill In                        |
|------|----------------------------------------|
| 1    | Personal Information                   |
| 2    | Contact & Address Details              |
| 3    | Academic Information (GPA, year level) |
| 4    | Financial Information                  |
| 5    | Essay / Statement of Purpose           |
| 6    | Document Upload                        |
| 7    | Review & Submit                        |

- You can **Save as Draft** at any step and continue later
- Once submitted, you cannot edit the application

#### Required Documents (typical)
- School ID or enrollment certificate
- Grade/transcript of records
- Income certificate or financial documents
- Any other documents specified by the scholarship

---

### 4.5 Track Your Application

**Path**: Sidebar → My Applications

View all your applications and their current status:

| Status       | Meaning                                      |
|--------------|----------------------------------------------|
| Draft        | Not yet submitted                            |
| Submitted    | Received, awaiting review                    |
| Under Review | Being evaluated by staff                     |
| Approved     | Congratulations! Disbursement will follow    |
| Rejected     | Not selected for this scholarship            |
| Waitlisted   | On hold, may be reconsidered                 |

---

### 4.6 Interview Booking

**Path**: Sidebar → Interview Booking

If your application reaches the interview stage:
1. Go to Interview Booking
2. Browse available slots
3. Click **Book** on your preferred slot
4. You'll receive a confirmation notification

---

### 4.7 Document View

**Path**: Sidebar → My Documents

Check the verification status of your uploaded documents:
- **Pending** — not yet reviewed
- **Verified** — accepted
- **Rejected** — needs to be re-uploaded (check the reason)

---

### 4.8 Payouts / Disbursements

**Path**: Sidebar → My Payouts

Once your application is approved, a disbursement record is created. Track your payout status here:
- Pending → Processing → Completed

---

### 4.9 Surveys

**Path**: Sidebar → Surveys

If you are a scholarship recipient, you may be assigned surveys. Complete them by:
1. Clicking the survey
2. Answering all questions
3. Clicking **Submit**

---

### 4.10 Feedback

**Path**: Sidebar → Feedback

Submit feedback about your scholarship experience:
1. Select the scholarship
2. Write your feedback
3. Click **Submit**

---

### 4.11 Notifications

**Path**: Sidebar → Notifications (bell icon)

All system notifications appear here:
- Application status changes
- Interview booking confirmations
- Deadline reminders
- Survey assignments

Click a notification to mark it as read.

---

### 4.12 Profile

**Path**: Sidebar → Profile

Update your:
- Name and contact info
- Profile picture (2x2 photo)
- Password

---

## 5. Troubleshooting

### Can't Log In
- Check your email and password
- Account may be locked after 5 failed attempts — wait 15 minutes
- Use **Forgot Password** to reset

### Application Won't Submit
- Make sure all required fields are filled
- Check that all required documents are uploaded
- File size must be under the allowed limit (usually 5MB per file)
- Allowed file types: JPG, PNG, PDF

### Email Not Received
- Check your spam/junk folder
- Confirm your email address is correct in your profile
- Contact the administrator to check the email queue

### Document Rejected
- Open **My Documents** to see the rejection reason
- Re-upload the correct document
- Contact staff if you need clarification

### Page Shows an Error
- Try refreshing the page
- Clear your browser cache
- Log out and log back in
- Contact the system administrator with the error message

---

## 📞 Support

For technical issues:
- Check the **Audit Logs** (Admin/Staff)
- Review PHP error logs on the server
- Contact your system administrator

---

**ScholarHub v2.0.0** | Production Ready | Made with ❤️ for scholarship management
