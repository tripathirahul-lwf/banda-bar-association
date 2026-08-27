<?php
/**
 * Bulk Bar Fee Assignment
 * District Bar Association, Banda
 */

$pageTitle = 'थोक शुल्क आवंटन (Bulk Fee Assignment)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';
$summary = null;

$fee_types = [];
$financial_years = [];

if ($db) {
    try {
        $fee_types = $db->query("SELECT * FROM bar_fee_types WHERE status = 'active'")->fetchAll();
        $financial_years = $db->query("SELECT * FROM financial_years ORDER BY name DESC")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load bulk assignment lists: " . $e->getMessage());
    }
}

// Process Post Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'क्रॉस-साइट अनुरोध सुरक्षा सत्यापन विफल हुआ।';
    } else {
        $fee_type_id = intval($_POST['fee_type_id'] ?? 0);
        $financial_year = sanitize(trim($_POST['financial_year'] ?? ''));
        $period_label = sanitize(trim($_POST['period_label'] ?? ''));
        $due_date = sanitize(trim($_POST['due_date'] ?? ''));
        $amount = floatval($_POST['amount'] ?? 0.00);
        $group_type = sanitize(trim($_POST['group_type'] ?? 'all_active'));
        $membership_category = sanitize(trim($_POST['membership_category'] ?? ''));

        if ($fee_type_id <= 0 || empty($due_date) || $amount <= 0) {
            $error = 'कृपया शुल्क प्रकार, देय तिथि और राशि सही भरें।';
        } else {
            try {
                // Find eligible members
                $where = ["membership_status = 'active'"];
                $params = [];
                if ($group_type === 'by_category' && !empty($membership_category)) {
                    $where[] = "membership_category = :cat";
                    $params[':cat'] = $membership_category;
                }

                $where_sql = implode(" AND ", $where);
                $member_stmt = $db->prepare("SELECT id, full_name, membership_no FROM members WHERE $where_sql");
                $member_stmt->execute($params);
                $eligible_members = $member_stmt->fetchAll();

                if (empty($eligible_members)) {
                    $error = 'चयनित समूह के अंतर्गत कोई पात्र सदस्य नहीं मिला।';
                } else {
                    $created = 0;
                    $skipped = 0;
                    $failed = 0;

                    $status = 'pending';
                    if (date('Y-m-d') > $due_date) {
                        $status = 'overdue';
                    }

                    // Check for duplicates and insert
                    foreach ($eligible_members as $member) {
                        // Duplicate check query
                        $dup_stmt = $db->prepare("
                            SELECT COUNT(*) FROM member_fee_dues 
                            WHERE member_id = ? AND fee_type_id = ? AND financial_year = ? AND period_label = ? AND status != 'cancelled'
                        ");
                        $dup_stmt->execute([$member['id'], $fee_type_id, $financial_year, $period_label]);
                        $exists = $dup_stmt->fetchColumn() > 0;

                        if ($exists) {
                            $skipped++;
                            continue;
                        }

                        try {
                            $db->beginTransaction();
                            
                            $ins = $db->prepare("
                                INSERT INTO member_fee_dues (
                                    member_id, fee_type_id, financial_year, period_label, due_date,
                                    original_amount, discount_amount, penalty_amount, payable_amount,
                                    paid_amount, outstanding_amount, status, remarks, created_by
                                ) VALUES (
                                    ?, ?, ?, ?, ?,
                                    ?, 0.00, 0.00, ?,
                                    0.00, ?, ?, 'थोक शुल्क आवंटन द्वारा उत्पन्न', ?
                                )
                            ");
                            $ins->execute([
                                $member['id'], $fee_type_id, $financial_year, $period_label, $due_date,
                                $amount, $amount, $amount, $status, $_SESSION['user_id']
                            ]);
                            $due_id = $db->lastInsertId();

                            // Log due history
                            $hist = $db->prepare("
                                INSERT INTO bar_fee_due_history (due_id, action, new_amount, remarks, performed_by)
                                VALUES (?, 'Due Created', ?, 'थोक शुल्क आवंटन', ?)
                            ");
                            $hist->execute([$due_id, $amount, $_SESSION['user_id']]);

                            $db->commit();
                            $created++;
                        } catch (Exception $e) {
                            if ($db->inTransaction()) {
                                $db->rollBack();
                            }
                            $failed++;
                            error_log("Failed generating bulk due for member " . $member['id'] . ": " . $e->getMessage());
                        }
                    }

                    $summary = [
                        'created' => $created,
                        'skipped' => $skipped,
                        'failed' => $failed,
                        'total' => count($eligible_members)
                    ];
                }
            } catch (PDOException $e) {
                $error = 'डाटाबेस त्रुटि: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">थोक शुल्क आवंटन (Bulk Fee Assignment)</h4>
    <a href="members.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-arrow-left me-1"></i>सदस्य सूची</a>
</div>

<div class="row g-4 font-hindi mb-5">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm small">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-collection-fill text-gold-custom me-2"></i>थोक आवंटन मापदंड (Assignment Criteria)</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="bulk-assign.php" onsubmit="return confirm('क्या आप वास्तव में चयनित समूह के लिए थोक शुल्क उत्पन्न करना चाहते हैं?');">
                    <?php insertCSRF(); ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-navy-custom">शुल्क का प्रकार (Fee Type) <span class="text-danger">*</span></label>
                        <select name="fee_type_id" id="fee_type_id" class="form-select form-select-sm" required onchange="updateBulkAmount()">
                            <option value="">-- शुल्क प्रकार चुनें --</option>
                            <?php foreach ($fee_types as $ft): ?>
                                <option value="<?php echo $ft['id']; ?>" data-amount="<?php echo $ft['amount']; ?>"><?php echo e($ft['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold text-navy-custom">वित्तीय वर्ष (Financial Year)</label>
                            <select name="financial_year" class="form-select form-select-sm">
                                <option value="">-- वित्तीय वर्ष --</option>
                                <?php foreach ($financial_years as $fy): ?>
                                    <option value="<?php echo e($fy['name']); ?>" <?php echo ($fy['status'] === 'active') ? 'selected' : ''; ?>><?php echo e($fy['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold text-navy-custom">देय तिथि (Due Date) <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold text-navy-custom">अवधि/विवरण लेबल (Label)</label>
                            <input type="text" name="period_label" id="period_label" class="form-control form-control-sm" placeholder="उदा. Annual Bar Fee 2026-27">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold text-navy-custom">आवंटन राशि (Fee Amount) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" name="amount" id="amount" class="form-control" required value="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-navy-custom">लक्षित सदस्य समूह (Target Group)</label>
                        <select name="group_type" id="group_type" class="form-select form-select-sm" onchange="toggleCategorySelect()" required>
                            <option value="all_active">सभी सक्रिय सदस्य (All Active Members)</option>
                            <option value="by_category">विशिष्ट सदस्यता श्रेणी द्वारा (By Category)</option>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="category_select_block">
                        <label class="form-label fw-bold text-navy-custom">सदस्यता श्रेणी (Membership Category) <span class="text-danger">*</span></label>
                        <select name="membership_category" class="form-select form-select-sm">
                            <option value="Regular Member">Regular Member (नियमित सदस्य)</option>
                            <option value="Life Member">Life Member (आजीवन सदस्य)</option>
                            <option value="Senior Member">Senior Member (वरिष्ठ सदस्य)</option>
                        </select>
                    </div>

                    <div class="text-end border-top pt-3">
                        <button type="submit" class="btn btn-navy btn-sm px-4"><i class="bi bi-collection-fill me-1"></i>थोक शुल्क उत्पन्न करें</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Result Display Panel -->
    <div class="col-lg-6">
        <?php if ($summary): ?>
            <div class="card border-0 shadow-sm alert-success">
                <div class="card-header bg-success text-white py-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-check-circle-fill me-2"></i>आवंटन प्रसंस्करण रिपोर्ट (Bulk Report)</h6>
                </div>
                <div class="card-body">
                    <p class="mb-3 fw-semibold">शुल्क देयता थोक प्रक्रिया का विवरण:</p>
                    <table class="table table-sm table-bordered bg-white mb-0">
                        <tbody>
                            <tr>
                                <td>कुल योग्य सदस्य पाए गए:</td>
                                <td class="fw-bold text-end"><?php echo $summary['total']; ?></td>
                            </tr>
                            <tr>
                                <td class="text-success">सफलतापूर्वक देय उत्पन्न:</td>
                                <td class="fw-bold text-success text-end"><?php echo $summary['created']; ?></td>
                            </tr>
                            <tr>
                                <td class="text-warning">छोड़े गए (पूर्व में देयता मौजूद):</td>
                                <td class="fw-bold text-warning text-end"><?php echo $summary['skipped']; ?></td>
                            </tr>
                            <tr>
                                <td class="text-danger">त्रुटि / विफल आवंटन:</td>
                                <td class="fw-bold text-danger text-end"><?php echo $summary['failed']; ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="mt-3 text-secondary text-center small"><i class="bi bi-shield-fill-check me-1"></i>डुप्लिकेट सुरक्षा सक्रिय: पहले से आवंटित शुल्क दोबारा नहीं जोड़े गए।</div>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm bg-white p-3 h-100 d-flex flex-column justify-content-center text-center text-muted">
                <i class="bi bi-shield-fill-check text-navy-custom display-4 mb-2"></i>
                <h6 class="fw-bold text-navy-custom">सुरक्षित थोक आवंटन नियंत्रण</h6>
                <p class="mb-0 px-3">यह सिस्टम बार संघ के अधिवक्ताओं के लिए डुप्लिकेट चेक के साथ सामूहिक शुल्क आवंटन (जैसे कि वार्षिक शुल्क) करता है। पूर्व-आवंटित होने पर कोई अतिरिक्त प्रविष्टि स्वतः नहीं की जाती है।</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateBulkAmount() {
    const sel = document.getElementById('fee_type_id');
    const opt = sel.options[sel.selectedIndex];
    if (opt) {
        const amt = parseFloat(opt.getAttribute('data-amount')) || 0.00;
        document.getElementById('amount').value = amt.toFixed(2);
        
        // Prefill period label
        if (opt.text && opt.value) {
            const year = new Date().getFullYear();
            document.getElementById('period_label').value = opt.text + " " + year + "-" + ((year+1)%100);
        }
    }
}

function toggleCategorySelect() {
    const val = document.getElementById('group_type').value;
    const block = document.getElementById('category_select_block');
    if (val === 'by_category') {
        block.classList.remove('d-none');
    } else {
        block.classList.add('d-none');
    }
}
</script>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
