<?php
/**
 * Secure Login Action Processor
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Prevent direct GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method not allowed.");
}

// 0. Rate Limiting Check (Requirement 43)
if (!checkRateLimit('login_attempts', 10, 60)) {
    setFlash('danger', 'बहुत अधिक लॉगिन प्रयास। कृपया कुछ समय बाद पुनः प्रयास करें। (Too many login attempts. Please try again later.)');
    redirect(SITE_URL . '/login.php');
}

// 1. Verify CSRF Token
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!verify_csrf_token($csrf_token)) {
    setFlash('danger', 'सुरक्षा टोकन अमान्य है। (Invalid CSRF Token.)');
    redirect(SITE_URL . '/login.php');
}

// 2. Fetch and Sanitize input fields
$role = isset($_POST['role']) ? strtolower(trim($_POST['role'])) : '';
$identifier = isset($_POST['identifier']) ? trim($_POST['identifier']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

$allowed_roles = ['admin', 'president', 'mahasachiv', 'member'];
if (!in_array($role, $allowed_roles)) {
    setFlash('danger', 'अमान्य लॉग इन भूमिका चयनित है।');
    redirect(SITE_URL . '/login.php');
}

if (empty($identifier) || empty($password)) {
    setFlash('danger', 'कृपया सभी आवश्यक फ़ील्ड भरें।');
    redirect(SITE_URL . '/login.php?role=' . urlencode($role));
}

// Get DB connection
$db = Database::getConnection();
if (!$db) {
    setFlash('danger', 'डेटाबेस कनेक्शन एरर। कृपया बाद में प्रयास करें। (Database connection error.)');
    redirect(SITE_URL . '/login.php?role=' . urlencode($role));
}

// Helper variables for audit logging
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

try {
    $user = null;
    
    // 3. Query User based on Role type
    if ($role === 'member') {
        // Bar Members can log in via Membership Number (e.g. DBA-001) OR Mobile Number
        if (stripos($identifier, 'DBA-') === 0) {
            $stmt = $db->prepare("
                SELECT u.*, m.full_name as member_name, m.membership_status as member_status, m.membership_no 
                FROM users u 
                INNER JOIN members m ON u.member_id = m.id 
                WHERE m.membership_no = :membership_no AND u.role = 'member'
            ");
            $stmt->execute([':membership_no' => $identifier]);
            $user = $stmt->fetch();
        } else {
            $stmt = $db->prepare("
                SELECT u.*, m.full_name as member_name, m.membership_status as member_status, m.membership_no 
                FROM users u 
                LEFT JOIN members m ON u.member_id = m.id 
                WHERE u.mobile = :mobile AND u.role = 'member'
            ");
            $stmt->execute([':mobile' => $identifier]);
            $user = $stmt->fetch();
        }
    } else {
        // Admin, President, Mahasachiv can log in via Username, Email, or Mobile
        $stmt = $db->prepare("
            SELECT u.*, m.full_name as member_name, m.membership_status as member_status, m.membership_no 
            FROM users u 
            LEFT JOIN members m ON u.member_id = m.id 
            WHERE (u.username = :ident1 OR u.email = :ident2 OR u.mobile = :ident3) AND u.role = :role
        ");
        $stmt->execute([
            ':ident1' => $identifier,
            ':ident2' => $identifier,
            ':ident3' => $identifier,
            ':role' => $role
        ]);
        $user = $stmt->fetch();
    }

    // 4. Handle Lockout Inactivity Checks
    if ($user) {
        $now = date('Y-m-d H:i:s');
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            // Write log
            log_attempt($db, $user['id'], $identifier, $role, $ip_address, $user_agent, 'locked');
            setFlash('danger', 'बहुत अधिक असफल प्रयास हुए हैं। कृपया कुछ समय बाद पुनः प्रयास करें। (Account temporarily locked.)');
            redirect(SITE_URL . '/login.php?role=' . urlencode($role));
        }
        
        // If locked_until has expired, reset failed attempts
        if ($user['locked_until'] && strtotime($user['locked_until']) <= time()) {
            $reset_stmt = $db->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, status = 'active' WHERE id = ?");
            $reset_stmt->execute([$user['id']]);
            $user['failed_login_attempts'] = 0;
            $user['locked_until'] = null;
            $user['status'] = 'active';
        }
    }

    // 5. General Credentials Validation
    if ($user && password_verify($password, $user['password_hash'])) {
        // Enforce account status active
        if ($user['status'] !== 'active') {
            setFlash('danger', 'आपका खाता निष्क्रिय, निलंबित या लॉक किया गया है। कृपया संघ सचिव से संपर्क करें।');
            redirect(SITE_URL . '/login.php?role=' . urlencode($role));
        }

        // For members, verify linked member record exists and is Active
        if ($role === 'member') {
            if (empty($user['member_id'])) {
                setFlash('danger', 'लॉगिन विफल: सदस्य रिकॉर्ड लिंक नहीं है।');
                redirect(SITE_URL . '/login.php?role=' . urlencode($role));
            }
            if (strtolower($user['member_status']) !== 'active') {
                setFlash('danger', 'लॉगिन विफल: आपकी बार संघ सदस्यता सक्रिय (Active) नहीं है।');
                redirect(SITE_URL . '/login.php?role=' . urlencode($role));
            }
        }

        // Success: Reset failures and write log
        $update_stmt = $db->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ? WHERE id = ?");
        $update_stmt->execute([$ip_address, $user['id']]);

        log_attempt($db, $user['id'], $identifier, $role, $ip_address, $user_agent, 'success');

        // Set session variables
        $displayName = $user['member_name'] ? $user['member_name'] : ($user['username'] ? $user['username'] : ucfirst($role));
        
        $_SESSION['auth'] = [
            'user_id' => $user['id'],
            'role' => $user['role'],
            'member_id' => $user['member_id'],
            'name' => $displayName,
            'logged_in' => true,
            'last_activity' => time()
        ];
        
        // Populate fallback fields
        $_SESSION['username'] = $displayName;
        $_SESSION['user_role'] = $user['role'];

        session_regenerate_id(true);

        setFlash('success', 'लॉगिन सफल। आपका स्वागत है! (Login Successful.)');
        redirectByRole();
    } else {
        // Failed Login: Brute force tracking
        $user_id = null;
        if ($user) {
            $user_id = $user['id'];
            $new_attempts = $user['failed_login_attempts'] + 1;
            
            if ($new_attempts >= 5) {
                // Lock account for 15 minutes
                $lock_time = date('Y-m-d H:i:s', time() + 900); // 15 minutes = 900s
                $lock_stmt = $db->prepare("UPDATE users SET failed_login_attempts = :attempts, locked_until = :locked, status = 'locked' WHERE id = :id");
                $lock_stmt->execute([
                    ':attempts' => $new_attempts,
                    ':locked' => $lock_time,
                    ':id' => $user_id
                ]);
                log_attempt($db, $user_id, $identifier, $role, $ip_address, $user_agent, 'locked');
                setFlash('danger', 'बहुत अधिक असफल प्रयास हुए हैं। कृपया कुछ समय बाद पुनः प्रयास करें।');
            } else {
                $failed_stmt = $db->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?");
                $failed_stmt->execute([$new_attempts, $user_id]);
                log_attempt($db, $user_id, $identifier, $role, $ip_address, $user_agent, 'failed');
                setFlash('danger', 'Login details सही नहीं हैं। कृपया पुनः प्रयास करें।');
            }
        } else {
            // User not found, log with null user_id
            log_attempt($db, null, $identifier, $role, $ip_address, $user_agent, 'failed');
            setFlash('danger', 'Login details सही नहीं हैं। कृपया पुनः प्रयास करें।');
        }
        
        redirect(SITE_URL . '/login.php?role=' . urlencode($role));
    }
} catch (PDOException $e) {
    error_log("Login processing query error: " . $e->getMessage());
    setFlash('danger', 'सिस्टम एरर (DB Error): ' . $e->getMessage());
    redirect(SITE_URL . '/login.php?role=' . urlencode($role));
} catch (Exception $e) {
    error_log("Login processing general error: " . $e->getMessage());
    setFlash('danger', 'सिस्टम एरर (General Error): ' . $e->getMessage());
    redirect(SITE_URL . '/login.php?role=' . urlencode($role));
}

/**
 * Log the login attempt in audit logs database table.
 */
function log_attempt($db, $user_id, $identifier, $role, $ip, $ua, $status) {
    try {
        $log_stmt = $db->prepare("
            INSERT INTO login_logs (user_id, identifier, role_attempted, ip_address, user_agent, status) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $log_stmt->execute([$user_id, $identifier, $role, $ip, $ua, $status]);
    } catch (PDOException $e) {
        error_log("Failed to insert audit login log: " . $e->getMessage());
    }
}
