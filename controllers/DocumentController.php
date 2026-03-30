<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/AuditHelper.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'Please log in.';
    header('Location: ../auth/login.php');
    exit;
}

$action = $_POST['action'] ?? '';
$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user']['role'] ?? '';

// Only staff and admin can verify documents
if (!in_array($user_role, ['staff', 'admin'])) {
    $_SESSION['flash'] = 'Access denied.';
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = 'Invalid request. Please try again.';
    header('Location: ../staff/documents.php');
    exit;
}

if ($action === 'verify_documents_bulk') {
    $document_ids = $_POST['document_ids'] ?? [];
    $new_status = $_POST['new_status'] ?? 'verified';
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($document_ids)) {
        $_SESSION['flash'] = 'Please select at least one document.';
        header('Location: ../staff/documents.php');
        exit;
    }
    
    // Validate status
    if (!in_array($new_status, ['verified', 'rejected', 'needs_resubmission'])) {
        $_SESSION['flash'] = 'Invalid status.';
        header('Location: ../staff/documents.php');
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $updated_count = 0;
        $stmt = $pdo->prepare('UPDATE documents SET verification_status = :status, verified_by = :verifier, verified_at = NOW(), notes = :notes WHERE id = :id');
        
        foreach ($document_ids as $doc_id) {
            $doc_id = (int)$doc_id;
            if ($doc_id <= 0) continue;
            
            $stmt->execute([
                ':status' => $new_status,
                ':verifier' => $user_id,
                ':notes' => $notes,
                ':id' => $doc_id
            ]);
            
            if ($stmt->rowCount() > 0) {
                $updated_count++;
                // Log audit trail
                logAudit($pdo, $user_id, 'DOCUMENT_VERIFIED', 'document', $doc_id, null, $new_status);
            }
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "Successfully updated $updated_count document(s) to status: " . ucfirst(str_replace('_', ' ', $new_status));
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[DocumentController] Error: ' . $e->getMessage());
        $_SESSION['flash'] = 'Failed to update documents. Please try again.';
    }
    
    header('Location: ../staff/documents.php');
    exit;
}

if ($action === 'verify_single') {
    $doc_id = (int)($_POST['document_id'] ?? 0);
    $new_status = $_POST['status'] ?? 'verified';
    $notes = trim($_POST['notes'] ?? '');
    
    if ($doc_id <= 0) {
        $_SESSION['flash'] = 'Invalid document ID.';
        header('Location: ../staff/documents.php');
        exit;
    }
    
    if (!in_array($new_status, ['verified', 'rejected', 'needs_resubmission'])) {
        $_SESSION['flash'] = 'Invalid status.';
        header('Location: ../staff/documents.php');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare('UPDATE documents SET verification_status = :status, verified_by = :verifier, verified_at = NOW(), notes = :notes WHERE id = :id');
        $stmt->execute([
            ':status' => $new_status,
            ':verifier' => $user_id,
            ':notes' => $notes,
            ':id' => $doc_id
        ]);
        
        if ($stmt->rowCount() > 0) {
            logAudit($pdo, $user_id, 'DOCUMENT_VERIFIED', 'document', $doc_id, null, $new_status);
            $_SESSION['success'] = 'Document status updated to: ' . ucfirst(str_replace('_', ' ', $new_status));
        } else {
            $_SESSION['flash'] = 'Document not found or already updated.';
        }
        
    } catch (Exception $e) {
        error_log('[DocumentController] Error: ' . $e->getMessage());
        $_SESSION['flash'] = 'Failed to update document.';
    }
    
    header('Location: ../staff/documents.php');
    exit;
}

// Unsupported action
$_SESSION['flash'] = 'Invalid action.';
header('Location: ../staff/documents.php');
exit;
