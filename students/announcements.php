<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
requireLogin();
requireRole('student', 'Student access required');
$pdo = getPDO();

try {
    $stmt = $pdo->query("SELECT title, message, type, published_at FROM announcements WHERE published = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY published_at DESC");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $announcements = [];
}

$page_title = 'Announcements - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<style>
.announcements-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
  margin-bottom: 2rem;
}

@media (max-width: 1200px) {
  .announcements-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
  .announcements-grid { grid-template-columns: 1fr; }
}

.announcement-box {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  padding: 1.5rem;
  transition: all 0.3s ease;
  cursor: pointer;
  border: 2px solid transparent;
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.announcement-box::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background: var(--primary-color);
}

.announcement-box.type-info::before { background: #3b82f6; }
.announcement-box.type-success::before { background: #10b981; }
.announcement-box.type-warning::before { background: #f59e0b; }
.announcement-box.type-urgent::before { background: #ef4444; }

.announcement-box:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  transform: translateY(-2px);
  border-color: var(--primary-color);
}

.announcement-box.type-info:hover { border-color: #3b82f6; }
.announcement-box.type-success:hover { border-color: #10b981; }
.announcement-box.type-warning:hover { border-color: #f59e0b; }
.announcement-box.type-urgent:hover { border-color: #ef4444; }

.announcement-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  margin-bottom: 1rem;
}

.announcement-box.type-info .announcement-icon {
  background: #dbeafe;
  color: #3b82f6;
}

.announcement-box.type-success .announcement-icon {
  background: #d1fae5;
  color: #10b981;
}

.announcement-box.type-warning .announcement-icon {
  background: #fef3c7;
  color: #f59e0b;
}

.announcement-box.type-urgent .announcement-icon {
  background: #fee2e2;
  color: #ef4444;
}

.announcement-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 0.75rem 0;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.announcement-message {
  font-size: 0.875rem;
  color: #6b7280;
  line-height: 1.6;
  margin: 0 0 1rem 0;
  flex: 1;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.announcement-date {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  color: #9ca3af;
  margin-top: auto;
  padding-top: 1rem;
  border-top: 1px solid #f3f4f6;
}

.announcement-date i {
  font-size: 0.875rem;
}

.announcement-type-badge {
  position: absolute;
  top: 1rem;
  right: 1rem;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.announcement-box.type-info .announcement-type-badge {
  background: #dbeafe;
  color: #1e40af;
}

.announcement-box.type-success .announcement-type-badge {
  background: #d1fae5;
  color: #065f46;
}

.announcement-box.type-warning .announcement-type-badge {
  background: #fef3c7;
  color: #92400e;
}

.announcement-box.type-urgent .announcement-type-badge {
  background: #fee2e2;
  color: #991b1b;
}

.announcement-expand-icon {
  position: absolute;
  bottom: 1rem;
  right: 1rem;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: rgba(0,0,0,0.05);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  transition: all 0.3s ease;
}

.announcement-box:hover .announcement-expand-icon {
  background: var(--primary-color);
  color: white;
  transform: scale(1.1);
}
</style>

<div class="page-header">
  <h1><i class="fas fa-bullhorn"></i> Announcements</h1>
  <p class="text-muted">Stay updated with the latest news and information</p>
</div>

<?php if (!empty($announcements)): ?>
<div class="announcements-grid">
  <?php foreach ($announcements as $idx => $ann): ?>
    <?php 
      $announcementId = 'announcement-' . $idx;
      $type = $ann['type'] ?? 'info';
      $icon = match($type) {
        'success' => 'fa-check-circle',
        'warning' => 'fa-exclamation-triangle',
        'urgent' => 'fa-exclamation-circle',
        default => 'fa-info-circle'
      };
    ?>
    <div 
      class="announcement-box type-<?= htmlspecialchars($type) ?>" 
      onclick="toggleAnnouncementModal('<?= $announcementId ?>')"
    >
      <span class="announcement-type-badge"><?= htmlspecialchars($type) ?></span>
      
      <div class="announcement-icon">
        <i class="fas <?= $icon ?>"></i>
      </div>
      
      <h3 class="announcement-title"><?= htmlspecialchars($ann['title']) ?></h3>
      
      <p class="announcement-message"><?= htmlspecialchars($ann['message']) ?></p>
      
      <div class="announcement-date">
        <i class="fas fa-calendar"></i>
        <span><?= date('F d, Y', strtotime($ann['published_at'])) ?></span>
      </div>
      
      <div class="announcement-expand-icon">
        <i class="fas fa-chevron-right"></i>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Announcement Detail Modals -->
<?php foreach ($announcements as $idx => $ann): ?>
  <?php 
    $announcementId = 'announcement-' . $idx;
    $type = $ann['type'] ?? 'info';
    $icon = match($type) {
      'success' => 'fa-check-circle',
      'warning' => 'fa-exclamation-triangle',
      'urgent' => 'fa-exclamation-circle',
      default => 'fa-info-circle'
    };
    $bgColor = match($type) {
      'success' => '#10b981',
      'warning' => '#f59e0b',
      'urgent' => '#ef4444',
      default => '#3b82f6'
    };
  ?>
  <div id="<?= $announcementId ?>" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 700px;">
      <div class="modal-header">
        <div style="display: flex; align-items: center; gap: 1rem;">
          <div style="width: 48px; height: 48px; border-radius: 12px; background: <?= $bgColor ?>; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
            <i class="fas <?= $icon ?>"></i>
          </div>
          <div>
            <h2 style="margin: 0;"><?= htmlspecialchars($ann['title']) ?></h2>
            <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem;">
              <span class="status-badge" style="background: <?= $bgColor ?>; color: white; text-transform: uppercase; font-size: 0.75rem;">
                <?= htmlspecialchars($type) ?>
              </span>
              <span style="font-size: 0.875rem; color: #9ca3af;">
                <i class="fas fa-calendar"></i> <?= date('F d, Y', strtotime($ann['published_at'])) ?>
              </span>
            </div>
          </div>
        </div>
        <button class="modal-close" onclick="toggleAnnouncementModal('<?= $announcementId ?>')">&times;</button>
      </div>
      
      <div style="padding: 2rem;">
        <div style="font-size: 1rem; line-height: 1.8; color: #374151; white-space: pre-wrap;">
<?= htmlspecialchars($ann['message']) ?>
        </div>
        
        <?php if (!empty($ann['expires_at'])): ?>
        <div style="margin-top: 2rem; padding: 1rem; background: #f3f4f6; border-radius: 8px; display: flex; align-items: center; gap: 0.75rem;">
          <i class="fas fa-clock" style="color: #6b7280;"></i>
          <span style="font-size: 0.875rem; color: #6b7280;">
            This announcement expires on <?= date('F d, Y', strtotime($ann['expires_at'])) ?>
          </span>
        </div>
        <?php endif; ?>
      </div>
      
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="toggleAnnouncementModal('<?= $announcementId ?>')">Close</button>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<script>
function toggleAnnouncementModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal.style.display === 'none' || modal.style.display === '') {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  } else {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
  if (event.target.classList.contains('modal')) {
    event.target.style.display = 'none';
    document.body.style.overflow = 'auto';
  }
});
</script>

<?php else: ?>
<div class="content-card">
  <div class="empty-state">
    <div class="empty-state-icon"><i class="fas fa-bullhorn"></i></div>
    <h3 class="empty-state-title">No Announcements</h3>
    <p class="empty-state-description">Check back later for updates.</p>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
