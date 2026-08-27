<?php
/**
 * Admin Branding Configuration Settings
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'वेबसाइट ब्रांडिंग विन्यास (Branding Settings)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin permission
requireRole(['admin']);

$db = Database::getConnection();
$error = '';
$success = '';

$keys = ['logo', 'favicon', 'emblem', 'primary_color', 'secondary_color', 'accent_color'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        try {
            $db->beginTransaction();

            $old_values = [];
            $new_values = [];

            $stmt_get = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt_up = $db->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");

            // Fetch current values
            foreach ($keys as $key) {
                $stmt_get->execute([$key]);
                $old_values[$key] = $stmt_get->fetchColumn();
            }

            // A. Colors validation (Requirement 17: Use safe hex colors)
            $colors = ['primary_color', 'secondary_color', 'accent_color'];
            foreach ($colors as $color_key) {
                $color_val = trim($_POST[$color_key] ?? '');
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color_val)) {
                    throw new Exception("अमान्य रंग कोड प्रारूप ($color_key)। प्रारूप '#RRGGBB' होना चाहिए।");
                }
                $new_values[$color_key] = $color_val;
                $stmt_up->execute([$color_val, $_SESSION['user_id'], $color_key]);
            }

            // B. File Uploads (Logo & Favicon)
            $upload_keys = ['logo', 'favicon', 'emblem'];
            foreach ($upload_keys as $up_key) {
                if (!empty($_FILES[$up_key]['name'])) {
                    $target_dir = __DIR__ . '/../../storage/branding/';
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }

                    $file_ext = strtolower(pathinfo($_FILES[$up_key]['name'], PATHINFO_EXTENSION));
                    $allowed_exts = ($up_key === 'favicon') ? ['ico', 'png'] : ['png', 'jpg', 'jpeg'];
                    
                    if (!in_array($file_ext, $allowed_exts)) {
                        throw new Exception("अमान्य फ़ाइल प्रकार।");
                    }

                    $new_filename = $up_key . '_' . time() . '.' . $file_ext;
                    $target_file = $target_dir . $new_filename;

                    if (move_uploaded_file($_FILES[$up_key]['tmp_name'], $target_file)) {
                        $stored_path = 'storage/branding/' . $new_filename;
                        $new_values[$up_key] = $stored_path;
                        $stmt_up->execute([$stored_path, $_SESSION['user_id'], $up_key]);

                        // Delete old file if exists
                        if (!empty($old_values[$up_key])) {
                            deleteStoredFileSafely($old_values[$up_key]);
                        }
                    } else {
                        throw new Exception("फ़ाइल अपलोड करने में असमर्थ।");
                    }
                } else {
                    $new_values[$up_key] = $old_values[$up_key];
                }
            }

            // Log Audit
            logAudit('settings', 'Updated Branding Settings', 'system_settings', null, 'Website branding colors and logos updated.', $old_values, $new_values);

            $db->commit();
            $success = 'वेबसाइट ब्रांडिंग सेटिंग्स सफलतापूर्वक अद्यतन की गईं।';
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'ब्रांडिंग अद्यतन विफल: ' . $e->getMessage();
        }
    }
}

// Fetch settings again to display
$config = [];
foreach ($keys as $k) {
    $config[$k] = getSetting($k, '');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">वेबसाइट ब्रांडिंग विन्यास (Branding Settings)</h4>
    <div class="d-flex gap-2">
        <a href="general.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-gear-fill me-1"></i>सामान्य विन्यास</a>
        <a href="homepage.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-layout-text-window-reverse me-1"></i>होमपेज विन्यास</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row g-4 font-hindi small text-navy-custom">
    <!-- Upload Logos Form -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold">लोगो एवं आइकन अपलोड प्रपत्र</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <?php insertCSRF(); ?>

                    <div class="row align-items-center mb-4">
                        <div class="col-md-3 text-center">
                            <img src="<?php echo SITE_URL; ?>/<?php echo $config['logo'] ?: 'assets/images/logo.png'; ?>" class="img-thumbnail border" style="max-height: 80px;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/logo.png'">
                            <span class="d-block text-muted mt-1" style="font-size: 0.65rem;">वर्तमान लोगो (Logo)</span>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fw-bold">नया संघ लोगो अपलोड करें (PNG preferred)</label>
                            <input type="file" name="logo" class="form-control form-control-sm" accept=".png, .jpg, .jpeg">
                            <small class="text-muted">हेडर और आधिकारिक रिपोर्टों में इस्तेमाल किया जाएगा।</small>
                        </div>
                    </div>

                    <div class="row align-items-center mb-4">
                        <div class="col-md-3 text-center">
                            <img src="<?php echo SITE_URL; ?>/<?php echo $config['favicon'] ?: 'favicon.ico'; ?>" class="img-thumbnail border" style="max-height: 48px;" onerror="this.src='<?php echo SITE_URL; ?>/favicon.ico'">
                            <span class="d-block text-muted mt-1" style="font-size: 0.65rem;">वर्तमान फेविकॉन</span>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fw-bold">नया फेविकॉन (.ico, .png)</label>
                            <input type="file" name="favicon" class="form-control form-control-sm" accept=".ico, .png">
                            <small class="text-muted">ब्राउज़र टैब पर दिखाई देगा।</small>
                        </div>
                    </div>

                    <hr class="border-light my-3">
                    <h6 class="fw-bold text-navy-custom mb-3"><i class="bi bi-palette-fill text-gold-dark me-1"></i>रंग योजना विन्यास (Theme Colors Palette)</h6>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">प्राथमिक रंग (Primary Color) *</label>
                            <div class="input-group input-group-sm">
                                <input type="color" class="form-control-color border" name="primary_color" value="<?php echo e($config['primary_color']); ?>" style="width: 45px; height: 31px;">
                                <input type="text" class="form-control" name="primary_color_text" value="<?php echo e($config['primary_color']); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">द्वितीयक रंग (Secondary Color) *</label>
                            <div class="input-group input-group-sm">
                                <input type="color" class="form-control-color border" name="secondary_color" value="<?php echo e($config['secondary_color']); ?>" style="width: 45px; height: 31px;">
                                <input type="text" class="form-control" name="secondary_color_text" value="<?php echo e($config['secondary_color']); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">उभार रंग (Accent Gold Color) *</label>
                            <div class="input-group input-group-sm">
                                <input type="color" class="form-control-color border" name="accent_color" value="<?php echo e($config['accent_color']); ?>" style="width: 45px; height: 31px;">
                                <input type="text" class="form-control" name="accent_color_text" value="<?php echo e($config['accent_color']); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-navy btn-sm"><i class="bi bi-cloud-upload-fill me-1"></i>ब्रांडिंग सहेजें</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Branding Rules Card -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body">
                <h6 class="fw-bold text-navy-custom"><i class="bi bi-info-circle me-1"></i>ब्रांडिंग निर्देश</h6>
                <p class="text-muted leading-relaxed" style="font-size: 0.72rem;">
                    1. **सुरक्षित रंग सीमाएं:** रंग मानों को सुरक्षित हेक्स मानों (#RRGGBB) में ही सहेजने की अनुमति है। यह किसी भी रूप में अवांछित कस्टम सीएसएस इंजेक्शन को रोकता है।<br><br>
                    2. **लोगो छवि:** स्पष्ट पारदर्शी पृष्ठभूमि (Transparent PNG) का लोगो अपलोड करना वेबसाइट हेडर में बेहतर दृश्यता प्रदान करता है।
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sync color picker with text boxes
    const pickers = ['primary_color', 'secondary_color', 'accent_color'];
    pickers.forEach(id => {
        const picker = document.querySelector(`input[type="color"][name="${id}"]`);
        const text = document.querySelector(`input[type="text"][name="${id}_text"]`);
        if (picker && text) {
            picker.addEventListener('input', function() {
                text.value = this.value;
            });
        }
    });
});
</script>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
