<?php
/**
 * Admin General Settings Console
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'सामान्य वेबसाइट विन्यास (General Settings)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin permission
requireRole(['admin']);

$db = Database::getConnection();
$error = '';
$success = '';

$keys = [
    'association_name_en', 'association_name_hi', 'established_year',
    'address', 'district', 'state', 'pincode', 'contact_number',
    'alternate_number', 'official_email', 'office_timing', 'website_url',
    'footer_text', 'maintenance_mode'
];

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

            foreach ($keys as $key) {
                $stmt_get->execute([$key]);
                $old_val = $stmt_get->fetchColumn();
                $old_values[$key] = $old_val;

                // Value from POST
                $new_val = $_POST[$key] ?? '';
                if ($key === 'maintenance_mode') {
                    $new_val = isset($_POST['maintenance_mode']) ? '1' : '0';
                } else {
                    $new_val = sanitize(trim($new_val));
                }

                $new_values[$key] = $new_val;

                // Update settings
                $stmt_up->execute([$new_val, $_SESSION['user_id'], $key]);
            }

            // Write to centralized audit trail (Requirement 24)
            logAudit('settings', 'Updated General Settings', 'system_settings', null, 'General website configuration updated.', $old_values, $new_values);

            $db->commit();
            $success = 'वेबसाइट सेटिंग्स सफलतापूर्वक अद्यतन की गईं।';
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'अद्यतन विफल: ' . $e->getMessage();
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
    <h4 class="text-navy-custom fw-bold mb-0">सामान्य वेबसाइट विन्यास (General Settings)</h4>
    <div class="d-flex gap-2">
        <a href="branding.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-palette me-1"></i>ब्रांडिंग विन्यास</a>
        <a href="homepage.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-layout-text-window-reverse me-1"></i>होमपेज विन्यास</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm font-hindi small text-navy-custom">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold">संघीय सामान्य विन्यास फॉर्म</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php insertCSRF(); ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">संघ का नाम (English) *</label>
                    <input type="text" name="association_name_en" class="form-control form-control-sm" required value="<?php echo e($config['association_name_en']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">संघ का नाम (हिन्दी) *</label>
                    <input type="text" name="association_name_hi" class="form-control form-control-sm" required value="<?php echo e($config['association_name_hi']); ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">स्थापना वर्ष (Estd. Year) *</label>
                    <input type="text" name="established_year" class="form-control form-control-sm" required value="<?php echo e($config['established_year']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">वेबसाइट यूआरएल (Website URL) *</label>
                    <input type="url" name="website_url" class="form-control form-control-sm" required value="<?php echo e($config['website_url']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">कार्यालय समय (Office Timing) *</label>
                    <input type="text" name="office_timing" class="form-control form-control-sm" required value="<?php echo e($config['office_timing']); ?>" placeholder="उदा. 10:00 AM - 05:00 PM">
                </div>
            </div>

            <hr class="my-3 border-light">
            <h6 class="fw-bold text-navy-custom mb-3"><i class="bi bi-telephone-fill text-gold-dark me-1"></i>सम्पर्क एवं पता (Contact Details)</h6>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">सम्पर्क नंबर (Primary Phone) *</label>
                    <input type="text" name="contact_number" class="form-control form-control-sm" required value="<?php echo e($config['contact_number']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">वैकल्पिक सम्पर्क नंबर (Alternate Phone)</label>
                    <input type="text" name="alternate_number" class="form-control form-control-sm" value="<?php echo e($config['alternate_number']); ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">आधिकारिक ईमेल (Official Email) *</label>
                    <input type="email" name="official_email" class="form-control form-control-sm" required value="<?php echo e($config['official_email']); ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">संघ का कार्यालय पता (Address) *</label>
                <textarea name="address" class="form-control form-control-sm" required rows="2"><?php echo e($config['address']); ?></textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">जनपद (District) *</label>
                    <input type="text" name="district" class="form-control form-control-sm" required value="<?php echo e($config['district']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">राज्य (State) *</label>
                    <input type="text" name="state" class="form-control form-control-sm" required value="<?php echo e($config['state']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">पिन कोड (PIN Code) *</label>
                    <input type="text" name="pincode" class="form-control form-control-sm" required value="<?php echo e($config['pincode']); ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">फुटर टेक्स्ट (Footer Copyright Text) *</label>
                <input type="text" name="footer_text" class="form-control form-control-sm" required value="<?php echo e($config['footer_text']); ?>">
            </div>

            <hr class="my-3 border-light">
            <h6 class="fw-bold text-navy-custom mb-3"><i class="bi bi-shield-lock-fill text-gold-dark me-1"></i>सिस्टम सुरक्षा (System Control)</h6>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintSwitch" <?php echo $config['maintenance_mode'] ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-bold text-danger" for="maintSwitch">वेबसाइट रख-रखाव मोड (Maintenance Mode) सक्रिय करें</label>
                </div>
                <small class="text-muted d-block mt-1">सक्रिय होने पर, सार्वजनिक उपयोगकर्ता वेबसाइट नहीं देख सकेंगे (उन्हें रख-रखाव का संदेश दिखेगा)। एडमिन उपयोगकर्ता सामान्य लॉगिन और कार्य जारी रख सकते हैं।</small>
            </div>

            <button type="submit" class="btn btn-gold btn-sm text-navy-custom fw-bold"><i class="bi bi-check-circle-fill me-1"></i>विन्यास सहेजें</button>
        </form>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
