<?php
/**
 * Admin Homepage Structured Content Configurator
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'होमपेज विन्यास (Homepage Settings)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin permission
requireRole(['admin']);

$db = Database::getConnection();
$error = '';
$success = '';

$keys = [
    'hero_heading', 'hero_description', 'primary_cta', 'secondary_cta', 'about_preview',
    'president_message_visibility', 'office_bearers_visibility', 'important_notices_visibility',
    'election_section_visibility', 'association_statistics_visibility'
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
                // Fetch old value
                $stmt_get->execute([$key]);
                $old_values[$key] = $stmt_get->fetchColumn();

                // Build new value
                if (in_array($key, [
                    'president_message_visibility', 'office_bearers_visibility', 
                    'important_notices_visibility', 'election_section_visibility', 
                    'association_statistics_visibility'
                ])) {
                    $new_val = isset($_POST[$key]) ? '1' : '0';
                } else {
                    $new_val = sanitize(trim($_POST[$key] ?? ''));
                }

                $new_values[$key] = $new_val;
                $stmt_up->execute([$new_val, $_SESSION['user_id'], $key]);
            }

            // Log Audit
            logAudit('settings', 'Updated Homepage Settings', 'system_settings', null, 'Homepage hero titles and section visibilities updated.', $old_values, $new_values);

            $db->commit();
            $success = 'होमपेज सेटिंग्स सफलतापूर्वक अद्यतन की गईं।';
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'होमपेज अद्यतन विफल: ' . $e->getMessage();
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
    <h4 class="text-navy-custom fw-bold mb-0">होमपेज विन्यास (Homepage Settings)</h4>
    <div class="d-flex gap-2">
        <a href="general.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-gear-fill me-1"></i>सामान्य विन्यास</a>
        <a href="branding.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-palette me-1"></i>ब्रांडिंग विन्यास</a>
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
        <h6 class="mb-0 fw-bold">मुख्य बैनर एवं अनुभाग नियंत्रण फॉर्म</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php insertCSRF(); ?>

            <div class="mb-3">
                <label class="form-label fw-bold">मुख्य बैनर हेडिंग (Hero Heading) *</label>
                <input type="text" name="hero_heading" class="form-control form-control-sm" required value="<?php echo e($config['hero_heading']); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">मुख्य बैनर उप-विवरण (Hero Description) *</label>
                <textarea name="hero_description" class="form-control form-control-sm" required rows="2"><?php echo e($config['hero_description']); ?></textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">प्राथमिक बटन टेक्स्ट (Primary CTA) *</label>
                    <input type="text" name="primary_cta" class="form-control form-control-sm" required value="<?php echo e($config['primary_cta']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">द्वितीयक बटन टेक्स्ट (Secondary CTA) *</label>
                    <input type="text" name="secondary_cta" class="form-control form-control-sm" required value="<?php echo e($config['secondary_cta']); ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">संघ का संक्षिप्त परिचय (About Summary) *</label>
                <textarea name="about_preview" class="form-control form-control-sm" required rows="3"><?php echo e($config['about_preview']); ?></textarea>
            </div>

            <hr class="my-3 border-light">
            <h6 class="fw-bold text-navy-custom mb-3"><i class="bi bi-eye-fill text-gold-dark me-1"></i>अनुभाग प्रदर्शन नियंत्रण (Homepage Sections Visibility)</h6>

            <div class="mb-3">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="president_message_visibility" id="presMsgVis" <?php echo $config['president_message_visibility'] ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-bold" for="presMsgVis">अध्यक्ष की कलम से (President Message Section)</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="office_bearers_visibility" id="bearersVis" <?php echo $config['office_bearers_visibility'] ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-bold" for="bearersVis">मुख्य पदाधिकारी (Office Bearers Panel)</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="important_notices_visibility" id="noticesVis" <?php echo $config['important_notices_visibility'] ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-bold" for="noticesVis">महत्वपूर्ण सूचनाएं (Notices Board)</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="election_section_visibility" id="elecVis" <?php echo $config['election_section_visibility'] ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-bold" for="elecVis">सक्रिय चुनाव अलर्ट (Elections ticker)</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="association_statistics_visibility" id="statsVis" <?php echo $config['association_statistics_visibility'] ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-bold" for="statsVis">संघ सांख्यिकी काउंटर (Stats Counters)</label>
                </div>
            </div>

            <button type="submit" class="btn btn-navy btn-sm"><i class="bi bi-check-circle-fill me-1"></i>होमपेज विन्यास सहेजें</button>
        </form>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
