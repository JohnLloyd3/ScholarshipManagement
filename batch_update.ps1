# PowerShell Script to Update All Pages with Modern Design
Write-Host "🎨 Starting Batch Update of All Pages..." -ForegroundColor Cyan
Write-Host ""

$updated = 0
$failed = 0

# Function to update a file
function Update-Page {
    param($file, $title, $hasSidebar)
    
    if (-not (Test-Path $file)) {
        Write-Host "⚠️  $file not found" -ForegroundColor Yellow
        return $false
    }
    
    # Backup
    Copy-Item $file "$file.backup" -Force
    
    # Read content
    $content = Get-Content $file -Raw
    
    # Check if already updated
    if ($content -match 'modern-theme.css') {
        Write-Host "✓ $file already updated" -ForegroundColor Green
        return $true
    }
    
    # Extract PHP logic (everything before HTML)
    if ($content -match '(?s)(.*?)(<\!DOCTYPE|<\!doctype|<html)') {
        $phpLogic = $matches[1]
        $htmlPart = $content.Substring($matches[1].Length)
    } else {
        Write-Host "❌ Cannot parse $file" -ForegroundColor Red
        return $false
    }
    
    # Extract body content
    if ($htmlPart -match '(?s)<body[^>]*>(.*?)</body>') {
        $bodyContent = $matches[1]
    } else {
        Write-Host "❌ Cannot find body in $file" -ForegroundColor Red
        return $false
    }
    
    # Clean up old wrappers
    $bodyContent = $bodyContent -replace '<div class="dashboard-app">.*?</div>', ''
    $bodyContent = $bodyContent -replace '<\?php include.*?nav\.php.*?\?>', ''
    
    # Build new content
    $basePath = if ($file -match '^(member|staff|admin|auth)/') { '../' } else { './' }
    
    $newContent = $phpLogic
    $newContent += "`n<?php`n"
    $newContent += "`$page_title = '$title';`n"
    $newContent += "`$base_path = '$basePath';`n"
    $newContent += "require_once __DIR__ . '/$basePath" + "includes/modern-header.php';`n"
    
    if ($hasSidebar) {
        $newContent += "require_once __DIR__ . '/$basePath" + "includes/modern-sidebar.php';`n"
    }
    
    $newContent += "?>`n`n"
    $newContent += $bodyContent
    $newContent += "`n<?php require_once __DIR__ . '/$basePath" + "includes/modern-footer.php'; ?>`n"
    
    # Write new content
    Set-Content $file $newContent -NoNewline
    
    Write-Host "✅ Updated $file" -ForegroundColor Green
    return $true
}

# Update all pages
$pages = @(
    @{file='member/profile.php'; title='My Profile - ScholarHub'; sidebar=$true},
    @{file='member/notifications.php'; title='Notifications - ScholarHub'; sidebar=$true},
    @{file='member/apply_scholarship.php'; title='Apply for Scholarship - ScholarHub'; sidebar=$true},
    @{file='member/scholarship_view.php'; title='Scholarship Details - ScholarHub'; sidebar=$true},
    @{file='member/document_view.php'; title='Document Viewer - ScholarHub'; sidebar=$true},
    @{file='staff/dashboard.php'; title='Staff Dashboard - ScholarHub'; sidebar=$true},
    @{file='staff/scholarships.php'; title='Manage Scholarships - ScholarHub'; sidebar=$true},
    @{file='staff/applications.php'; title='Review Applications - ScholarHub'; sidebar=$true},
    @{file='staff/application_view.php'; title='Application Details - ScholarHub'; sidebar=$true},
    @{file='staff/reports.php'; title='Reports - ScholarHub'; sidebar=$true},
    @{file='staff/analytics.php'; title='Analytics - ScholarHub'; sidebar=$true},
    @{file='staff/post_scholarship.php'; title='Post Scholarship - ScholarHub'; sidebar=$true},
    @{file='staff/scholarship_form.php'; title='Scholarship Form - ScholarHub'; sidebar=$true},
    @{file='staff/scholarships_manage.php'; title='Manage Scholarships - ScholarHub'; sidebar=$true},
    @{file='staff/pending_applications.php'; title='Pending Applications - ScholarHub'; sidebar=$true},
    @{file='staff/documents.php'; title='Documents - ScholarHub'; sidebar=$true},
    @{file='staff/audit_logs.php'; title='Audit Logs - ScholarHub'; sidebar=$true},
    @{file='staff/cron.php'; title='Cron Jobs - ScholarHub'; sidebar=$true},
    @{file='staff/cron_log.php'; title='Cron Logs - ScholarHub'; sidebar=$true},
    @{file='staff/search.php'; title='Search - ScholarHub'; sidebar=$true},
    @{file='admin/dashboard.php'; title='Admin Dashboard - ScholarHub'; sidebar=$true},
    @{file='admin/users.php'; title='User Management - ScholarHub'; sidebar=$true},
    @{file='admin/scholarships.php'; title='Scholarship Management - ScholarHub'; sidebar=$true},
    @{file='admin/applications.php'; title='Application Management - ScholarHub'; sidebar=$true},
    @{file='admin/announcements.php'; title='Announcements - ScholarHub'; sidebar=$true},
    @{file='admin/analytics.php'; title='System Analytics - ScholarHub'; sidebar=$true},
    @{file='admin/activity_logs.php'; title='Activity Logs - ScholarHub'; sidebar=$true},
    @{file='admin/email_queue.php'; title='Email Queue - ScholarHub'; sidebar=$true},
    @{file='admin/scholarship_archive.php'; title='Scholarship Archive - ScholarHub'; sidebar=$true},
    @{file='auth/forgot_password.php'; title='Forgot Password - ScholarHub'; sidebar=$false},
    @{file='auth/reset_password.php'; title='Reset Password - ScholarHub'; sidebar=$false},
    @{file='auth/applicant_register.php'; title='Applicant Registration - ScholarHub'; sidebar=$false}
)

foreach ($page in $pages) {
    if (Update-Page -file $page.file -title $page.title -hasSidebar $page.sidebar) {
        $updated++
    } else {
        $failed++
    }
}

Write-Host ""
Write-Host "================================" -ForegroundColor Cyan
Write-Host "🎉 Batch Update Complete!" -ForegroundColor Green
Write-Host "✅ Updated: $updated files" -ForegroundColor Green
Write-Host "❌ Failed: $failed files" -ForegroundColor Red
Write-Host ""
Write-Host "📝 Backup files created with .backup extension" -ForegroundColor Yellow
Write-Host "🔍 Test the pages and remove .backup files when satisfied" -ForegroundColor Yellow
