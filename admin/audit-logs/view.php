<?php
/**
 * View Detailed Audit Log Entry
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'आडिट लॉग विवरण (Audit Log Details)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin permission
requireRole(['admin']);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$db = Database::getConnection();

$log = null;
if ($db && $id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT a.*, u.username AS performer_name
            FROM audit_logs a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $log = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to load audit log detail: " . $e->getMessage());
    }
}

if (!$log) {
    echo '<h3>त्रुटि: ऑडिट रिकॉर्ड नहीं मिला।</h3>';
    exit;
}

// JSON Decoding with safety filters (Requirement 26: Exclude passwords/hashes)
$old_val = json_decode($log['old_values'] ?? '{}', true);
$new_val = json_decode($log['new_values'] ?? '{}', true);

function filterSensitiveValues($arr) {
    if (!is_array($arr)) return $arr;
    $sensitiveKeys = ['password', 'password_hash', 'pass', 'hash', 'csrf_token', 'token'];
    foreach ($arr as $key => $val) {
        if (in_array(strtolower($key), $sensitiveKeys)) {
            $arr[$key] = '******** (HIDDEN FOR SECURITY)';
        } elseif (is_array($val)) {
            $arr[$key] = filterSensitiveValues($val);
        }
    }
    return $arr;
}

$old_val = filterSensitiveValues($old_val);
$new_val = filterSensitiveValues($new_val);
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">ऑडिट लॉग प्रविष्टि विवरण (#<?php echo $log['id']; ?>)</h4>
    <a href="index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>ऑडिट सूची</a>
</div>

<div class="row g-4 font-hindi small text-navy-custom">
    <!-- Meta Info Column -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold">ऑडिट सामान्य विवरण (Metadata)</h6>
            </div>
            <div class="card-body py-2 px-3">
                <table class="table table-sm table-borderless mb-0" style="font-size:0.75rem;">
                    <tbody>
                        <tr class="border-bottom border-light">
                            <td class="fw-bold" style="width: 40%;">लॉग आईडी (Log ID):</td>
                            <td class="english-text">#<?php echo $log['id']; ?></td>
                        </tr>
                        <tr class="border-bottom border-light">
                            <td class="fw-bold">दिनांक एवं समय:</td>
                            <td class="english-text"><?php echo date('d-m-Y h:i:A', strtotime($log['created_at'])); ?></td>
                        </tr>
                        <tr class="border-bottom border-light">
                            <td class="fw-bold">उपयोगकर्ता (User):</td>
                            <td><?php echo e($log['performer_name'] ?: 'System'); ?></td>
                        </tr>
                        <tr class="border-bottom border-light">
                            <td class="fw-bold">सुरक्षा रोल (Role):</td>
                            <td><span class="badge bg-light text-navy-custom border"><?php echo e($log['role']); ?></span></td>
                        </tr>
                        <tr class="border-bottom border-light">
                            <td class="fw-bold">क्रिया (Action):</td>
                            <td class="text-dark fw-bold"><?php echo e($log['action']); ?></td>
                        </tr>
                        <tr class="border-bottom border-light">
                            <td class="fw-bold">सम्बद्ध मॉड्यूल:</td>
                            <td class="text-uppercase text-secondary fw-bold"><?php echo e($log['module']); ?></td>
                        </tr>
                        <tr class="border-bottom border-light">
                            <td class="fw-bold">रिकॉर्ड प्रकार (ID):</td>
                            <td class="english-text"><?php echo e($log['record_type'] ?: '-'); ?> (<?php echo $log['record_id'] ?: '-'; ?>)</td>
                        </tr>
                        <tr class="border-bottom border-light">
                            <td class="fw-bold">आईपी पता (IP):</td>
                            <td class="english-text"><?php echo e($log['ip_address']); ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">डिवाइस विवरण (UA):</td>
                            <td class="english-text" style="font-size: 0.65rem; word-break: break-all;"><?php echo e($log['user_agent']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($log['remarks'])): ?>
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body">
                    <h6 class="fw-bold mb-1"><i class="bi bi-chat-left-text me-1 text-gold-dark"></i>टिप्पणी (Remarks):</h6>
                    <p class="mb-0 text-muted" style="line-height: 1.5;"><?php echo e($log['remarks']); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Data States Change Diff Column -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light py-2">
                <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-file-diff-fill text-gold-dark me-1"></i>डेटा परिवर्तन स्थिति (Data States Diff)</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-danger"><i class="bi bi-dash-circle me-1"></i>पूर्व मान (Old Values)</label>
                        <pre class="bg-light p-2 rounded border font-monospace text-muted text-start" style="font-size: 0.65rem; max-height: 300px; overflow-y: auto;"><?php 
                            echo !empty($old_val) ? e(json_encode($old_val, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : '{}'; 
                        ?></pre>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-success"><i class="bi bi-plus-circle me-1"></i>नवीन मान (New Values)</label>
                        <pre class="bg-light p-2 rounded border font-monospace text-dark text-start" style="font-size: 0.65rem; max-height: 300px; overflow-y: auto;"><?php 
                            echo !empty($new_val) ? e(json_encode($new_val, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : '{}'; 
                        ?></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
