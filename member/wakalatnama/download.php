<?php
/**
 * Secure Wakalatnama PDF Download Endpoint
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// 1. Authenticated check
if (!isset($_SESSION['auth'])) {
    http_response_code(403);
    exit("Direct access forbidden. Log in required.");
}

$user = currentUser();
$user_id = $user['id'];
$member_id = $user['member_id'] ?? 0;
$role = $user['role'];

// 2. Validate request method & CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'अमान्य अनुरोध विधि (Invalid Request).');
    redirect('index.php');
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
    redirect('index.php');
}

$waka_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($waka_id <= 0) {
    setFlash('danger', 'अमान्य दस्तावेज आईडी।');
    redirect('index.php');
}

$db = Database::getConnection();

if (!$db) {
    setFlash('danger', 'डेटाबेस कनेक्शन विफल।');
    redirect('index.php');
}

try {
    // 3. Confirm membership status is active (Only required for role 'member')
    if ($role === 'member') {
        if ($member_id <= 0) {
            setFlash('danger', 'आपकी प्रोफाइल सदस्य रिकॉर्ड से लिंक नहीं है।');
            redirect('index.php');
        }
        
        $m_stmt = $db->prepare("SELECT membership_status FROM members WHERE id = ?");
        $m_stmt->execute([$member_id]);
        $membership_status = $m_stmt->fetchColumn();
        
        if ($membership_status !== 'active' || $user['status'] !== 'active') {
            setFlash('danger', 'आपकी वर्तमान सदस्यता स्थिति के कारण Wakalatnama डाउनलोड उपलब्ध नहीं है। कृपया संघ कार्यालय से संपर्क करें।');
            redirect('index.php');
        }
    }

    // 4. Fetch Wakalatnama details
    $w_stmt = $db->prepare("SELECT * FROM wakalatnamas WHERE id = ?");
    $w_stmt->execute([$waka_id]);
    $doc = $w_stmt->fetch();

    if (!$doc) {
        setFlash('danger', 'दस्तावेज रिकॉर्ड नहीं मिला।');
        redirect('index.php');
    }

    // 5. Confirm document is active & effective
    // If user is Admin/Mahasachiv/President, allow download even if draft/archived for preview purposes
    $is_staff = in_array($role, ['admin', 'mahasachiv', 'president']);
    if (!$is_staff && !isWakalatnamaAvailable($doc)) {
        setFlash('danger', 'यह दस्तावेज़ वर्तमान में उपलब्ध नहीं है (Inactive/Expired document).');
        redirect('index.php');
    }

    // 6. Rate Limit Protection
    $rate_limit = defined('WAKALATNAMA_DOWNLOAD_RATE_LIMIT_SECONDS') ? WAKALATNAMA_DOWNLOAD_RATE_LIMIT_SECONDS : 5;
    $last_download = $_SESSION['last_download_time'] ?? 0;
    $time_since = time() - $last_download;
    
    if (!$is_staff && $time_since < $rate_limit) {
        setFlash('warning', 'कृपया कुछ क्षण रुककर पुनः डाउनलोड करें (Rate Limit - Slow down).');
        redirect('index.php');
    }

    // 7. Resolve File Path and verify physical file exists
    $storage_dir = __DIR__ . '/../../storage/wakalatnama/';
    $file_name = $doc['file_path'];
    $full_path = $storage_dir . $file_name;

    // Remove any path traversal patterns for safety
    if (strpos($file_name, '..') !== false || strpos($file_name, '/') !== false || strpos($file_name, '\\') !== false) {
        setFlash('danger', 'सुरक्षा कारणों से फ़ाइल पथ अवरुद्ध किया गया है।');
        redirect('index.php');
    }

    if (!file_exists($full_path)) {
        setFlash('danger', 'भौतिक फ़ाइल सर्वर पर नहीं मिली (File not found on server).');
        redirect('index.php');
    }

    // 8. Log Successful Download
    $log_stmt = $db->prepare("
        INSERT INTO wakalatnama_downloads (wakalatnama_id, member_id, user_id, version, ip_address, user_agent, download_status, remarks) 
        VALUES (?, ?, ?, ?, ?, ?, 'success', 'दस्तावेज़ सफलतापूर्वक डाउनलोड किया गया।')
    ");
    $log_stmt->execute([
        $doc['id'],
        $member_id,
        $user_id,
        $doc['version'],
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    // Update last download timer
    $_SESSION['last_download_time'] = time();

    // 9. Stream file securely
    if (ob_get_length()) ob_clean();

    $clean_orig_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $doc['original_file_name']);

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
    error_log("Secure download failure: " . $e->getMessage());
    setFlash('danger', 'दस्तावेज डाउनलोड के दौरान सर्वर त्रुटि हुई।');
    redirect('index.php');
}
