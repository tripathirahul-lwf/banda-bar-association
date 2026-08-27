<?php
/**
 * Change Password Page
 * District Bar Association, Banda
 */

$pageTitle = 'पासवर्ड बदलें (Change Password)';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Enforce login
requireLogin();

$errors = [];
$user_id = $_SESSION['auth']['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है। (Invalid CSRF Token.)');
        redirect(SITE_URL . '/account/change-password.php');
    }

    // 2. Fetch and Validate Inputs
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password)) {
        $errors['current_password'] = 'वर्तमान पासवर्ड दर्ज करें।';
    }
    
    if (empty($new_password)) {
        $errors['new_password'] = 'नया पासवर्ड दर्ज करें।';
    } elseif (strlen($new_password) < 8) {
        $errors['new_password'] = 'नया पासवर्ड कम से कम 8 वर्णों का होना चाहिए।';
    }
    
    if ($new_password !== $confirm_password) {
        $errors['confirm_password'] = 'नया पासवर्ड और पुष्टि पासवर्ड मेल नहीं खाते।';
    }

    if (empty($errors)) {
        $db = Database::getConnection();
        if ($db) {
            try {
                // Fetch current user hash
                $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if ($user && password_verify($current_password, $user['password_hash'])) {
                    // Update to new password
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $db->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?");
                    $update_stmt->execute([$new_hash, $user_id]);

                    setFlash('success', 'पासवर्ड सफलतापूर्वक बदल दिया गया है। (Password changed successfully.)');
                    redirectByRole();
                } else {
                    $errors['current_password'] = 'दर्ज किया गया वर्तमान पासवर्ड सही नहीं है।';
                    setFlash('danger', 'वर्तमान पासवर्ड सत्यापन विफल रहा।');
                }
            } catch (PDOException $e) {
                error_log("Password update database error: " . $e->getMessage());
                setFlash('danger', 'सिस्टम त्रुटि। कृपया बाद में प्रयास करें।');
            }
        } else {
            setFlash('danger', 'डेटाबेस कनेक्शन उपलब्ध नहीं है।');
        }
    } else {
        setFlash('danger', 'कृपया प्रपत्र में त्रुटियों को ठीक करें।');
    }
}

$csrf_token = generate_csrf_token();
?>

<div class="row justify-content-center py-4">
    <div class="col-lg-5 col-md-8 col-12">
        <div class="bg-white p-4 p-md-5 rounded-3 shadow-sm border border-light">
            
            <div class="text-center mb-4">
                <div class="logo-placeholder bg-navy-custom text-gold-custom d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 60px; height: 60px;">
                    <i class="bi bi-key-fill fs-3"></i>
                </div>
                <h3 class="fw-bold font-hindi text-navy-custom mb-1">पासवर्ड बदलें</h3>
                <span class="badge bg-gold-custom text-navy-custom px-3 py-1 font-hindi text-uppercase fw-bold">
                    Change Password
                </span>
            </div>

            <form id="changePasswordForm" method="POST" action="change-password.php" novalidate>
                <!-- CSRF Token -->
                <?php csrfField(); ?>

                <!-- Current Password -->
                <div class="mb-3">
                    <label for="current_password" class="form-label font-hindi small fw-semibold text-secondary">वर्तमान पासवर्ड (Current Password) *</label>
                    <input type="password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" id="current_password" name="current_password" required>
                    <?php if (isset($errors['current_password'])): ?>
                        <div class="invalid-feedback fw-medium font-size-xs mt-1"><?php echo $errors['current_password']; ?></div>
                    <?php endif; ?>
                </div>

                <!-- New Password -->
                <div class="mb-3">
                    <label for="new_password" class="form-label font-hindi small fw-semibold text-secondary">नया पासवर्ड (New Password) *</label>
                    <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" id="new_password" name="new_password" placeholder="At least 8 characters" required>
                    <?php if (isset($errors['new_password'])): ?>
                        <div class="invalid-feedback fw-medium font-size-xs mt-1"><?php echo $errors['new_password']; ?></div>
                    <?php endif; ?>
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label for="confirm_password" class="form-label font-hindi small fw-semibold text-secondary">नए पासवर्ड की पुष्टि (Confirm New Password) *</label>
                    <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="invalid-feedback fw-medium font-size-xs mt-1"><?php echo $errors['confirm_password']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-navy font-hindi fw-semibold py-2">
                        पासवर्ड अपडेट करें (Update Password)
                    </button>
                    <a href="javascript:history.back()" class="btn btn-outline-secondary font-hindi fw-semibold py-2">
                        रद्द करें (Cancel)
                    </a>
                </div>
            </form>

        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php';
?>
