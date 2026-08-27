<?php
/**
 * Member User Account Linking and Reset Password Actions
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce login and permission
requireRole(['admin', 'mahasachiv']);
requirePermission('members.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect('../../index.php');
    }

    $action = trim($_POST['action'] ?? '');
    $member_id = intval($_POST['member_id'] ?? 0);
    $current_user_id = $_SESSION['auth']['user_id'];

    if ($member_id <= 0) {
        setFlash('danger', 'अमान्य सदस्य आईडी।');
        redirect('index.php');
    }

    $db = Database::getConnection();
    if (!$db) {
        setFlash('danger', 'डेटाबेस कनेक्शन विफलता।');
        redirect('view.php?id=' . $member_id);
    }

    // Load member
    try {
        $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
        if (!$member) {
            setFlash('danger', 'सदस्य रिकॉर्ड नहीं मिला।');
            redirect('index.php');
        }
    } catch (PDOException $e) {
        error_log("Failed loading member on account-action: " . $e->getMessage());
        redirect('index.php');
    }

    if ($action === 'create_account') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            setFlash('danger', 'यूजरनेम और पासवर्ड आवश्यक हैं।');
            redirect('view.php?id=' . $member_id . '&tab=account');
        }

        try {
            // Check if username exists
            $check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetchColumn() > 0) {
                setFlash('danger', 'यह यूजरनेम पहले से किसी खाते द्वारा उपयोग किया जा रहा है। (Username already exists.)');
                redirect('view.php?id=' . $member_id . '&tab=account');
            }

            // Insert into users
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins_user = $db->prepare("
                INSERT INTO users (member_id, username, email, mobile, password_hash, role, status, must_change_password) 
                VALUES (?, ?, ?, ?, ?, 'member', 'active', 1)
            ");
            $ins_user->execute([$member_id, $username, $member['email'], $member['mobile'], $hash]);
            $new_user_id = $db->lastInsertId();

            // Link user_id back in members table
            $link_memb = $db->prepare("UPDATE members SET user_id = ? WHERE id = ?");
            $link_memb->execute([$new_user_id, $member_id]);

            // Log history
            $hist = $db->prepare("INSERT INTO member_history (member_id, action, remarks, performed_by) VALUES (?, 'Account Linked', ?, ?)");
            $hist->execute([$member_id, "लॉगिन खाता लिंक किया गया। यूजरनेम: " . $username, $current_user_id]);

            setFlash('success', 'लॉगिन खाता सफलतापूर्वक बनाया गया और लिंक किया गया। अस्थायी पासवर्ड: ' . $password);
            redirect('view.php?id=' . $member_id . '&tab=account');
        } catch (PDOException $e) {
            error_log("Failed creating linked user account: " . $e->getMessage());
            setFlash('danger', 'खाता निर्माण विफल: ' . $e->getMessage());
            redirect('view.php?id=' . $member_id . '&tab=account');
        }

    } elseif ($action === 'reset_password') {
        $new_password = trim($_POST['new_password'] ?? '');

        if (empty($new_password)) {
            setFlash('danger', 'पासवर्ड खाली नहीं हो सकता।');
            redirect('view.php?id=' . $member_id . '&tab=account');
        }

        try {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password on linked account
            $up_user = $db->prepare("UPDATE users SET password_hash = ?, must_change_password = 1, password_changed_at = NULL WHERE member_id = ?");
            $up_user->execute([$hash, $member_id]);

            // Log history
            $hist = $db->prepare("INSERT INTO member_history (member_id, action, remarks, performed_by) VALUES (?, 'Password Reset', 'प्रशासक द्वारा पासवर्ड रीसेट किया गया। अस्थायी पासवर्ड लागू।', ?)");
            $hist->execute([$member_id, $current_user_id]);

            setFlash('success', 'पासवर्ड सफलतापूर्वक रीसेट किया गया। अस्थायी नया पासवर्ड: ' . $new_password);
            redirect('view.php?id=' . $member_id . '&tab=account');
        } catch (PDOException $e) {
            error_log("Failed resetting member password: " . $e->getMessage());
            setFlash('danger', 'पासवर्ड रीसेट करने में असमर्थ।');
            redirect('view.php?id=' . $member_id . '&tab=account');
        }

    } elseif ($action === 'toggle_status') {
        $current_status = trim($_POST['current_status'] ?? '');
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';

        try {
            $up_user = $db->prepare("UPDATE users SET status = ? WHERE member_id = ?");
            $up_user->execute([$new_status, $member_id]);

            // Log history
            $hist = $db->prepare("INSERT INTO member_history (member_id, action, remarks, performed_by) VALUES (?, 'Account Status Changed', ?, ?)");
            $hist->execute([$member_id, "खाता स्थिति परिवर्तित: " . $current_status . " -> " . $new_status, $current_user_id]);

            setFlash('success', 'लॉगिन खाता स्थिति सफलतापूर्वक ' . $new_status . ' कर दी गई है।');
            redirect('view.php?id=' . $member_id . '&tab=account');
        } catch (PDOException $e) {
            error_log("Failed toggling member login status: " . $e->getMessage());
            setFlash('danger', 'खाता स्थिति अद्यतन करने में असमर्थ।');
            redirect('view.php?id=' . $member_id . '&tab=account');
        }
    }
} else {
    redirect('index.php');
}
