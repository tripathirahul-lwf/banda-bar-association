<?php
/**
 * Secure Notice PDF Attachment Download Endpoint
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce login
if (!isset($_SESSION['auth'])) {
    http_response_code(403);
    exit("Direct access forbidden. Login required.");
}

$user = currentUser();
$member_id = $user['member_id'] ?? 0;
$role = $user['role'];

$notice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($notice_id <= 0) {
    http_response_code(400);
    exit("Invalid request parameters.");
}

$db = Database::getConnection();
if (!$db) {
    http_response_code(500);
    exit("Server database connection failure.");
}

try {
    // Check membership eligibility for member role
    if ($role === 'member') {
        if ($member_id <= 0) {
            http_response_code(403);
            exit("Your profile is not linked to a member record.");
        }
        $m_stmt = $db->prepare("SELECT membership_status FROM members WHERE id = ?");
        $m_stmt->execute([$member_id]);
        $membership_status = $m_stmt->fetchColumn();
        
        if ($membership_status !== 'active' || $user['status'] !== 'active') {
            http_response_code(403);
            exit("Access Blocked. Inactive membership status.");
        }
    }

    // Fetch Notice details
    $stmt = $db->prepare("SELECT title, visibility, attachment_path, attachment_name, attachment_type FROM notices WHERE id = ?");
    $stmt->execute([$notice_id]);
    $notice = $stmt->fetch();

    if (!$notice || empty($notice['attachment_path'])) {
        http_response_code(404);
        exit("Notice or attachment record not found.");
    }

    // Resolve physical path
    // Public attachments are placed in uploads/notices/
    // Members only attachments are placed in storage/notices/
    if ($notice['visibility'] === 'members_only') {
        $storage_dir = __DIR__ . '/../../storage/notices/';
    } else {
        $storage_dir = __DIR__ . '/../../uploads/notices/';
    }
    
    $file_name = $notice['attachment_path'];
    $full_path = $storage_dir . $file_name;

    // Safety checks against traversal path attempts
    if (strpos($file_name, '..') !== false || strpos($file_name, '/') !== false || strpos($file_name, '\\') !== false) {
        http_response_code(403);
        exit("Security Alert: Path traversal blocked.");
    }

    if (!file_exists($full_path)) {
        http_response_code(404);
        exit("File attachment not found on server.");
    }

    // Clean buffer
    if (ob_get_length()) ob_clean();

    $clean_orig_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $notice['attachment_name']);

    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $clean_orig_name . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($full_path));
    
    readfile($full_path);
    exit();

} catch (PDOException $e) {
    error_log("Secure notice download error: " . $e->getMessage());
    http_response_code(500);
    exit("Server error streaming document.");
}
