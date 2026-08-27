<?php
/**
 * Create Member Individual Due Form
 * District Bar Association, Banda
 */

$pageTitle = 'व्यक्तिगत देयता जोड़ें (Add Member Individual Due)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';

$pre_member_id = intval($_GET['member_id'] ?? 0);

// Fetch active fee types
$fee_types = [];
// Fetch financial years
$financial_years = [];
// Fetch active members
$members = [];

if ($db) {
    try {
        $fee_types = $db->query("SELECT * FROM bar_fee_types WHERE status = 'active'")->fetchAll();
        $financial_years = $db->query("SELECT * FROM financial_years ORDER BY name DESC")->fetchAll();
        $members = $db->query("SELECT id, full_name, membership_no FROM members WHERE membership_status IN ('active', 'inactive') ORDER BY full_name ASC")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading creation form dependency: " . $e->getMessage());
    }
}

// Process Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'क्रॉस-साइट अनुरोध सुरक्षा सत्यापन विफल हुआ।';
    } else {
        $member_id = intval($_POST['member_id'] ?? 0);
        $fee_type_id = intval($_POST['fee_type_id'] ?? 0);
        $financial_year = sanitize(trim($_POST['financial_year'] ?? ''));
        $period_label = sanitize(trim($_POST['period_label'] ?? ''));
        $due_date = sanitize(trim($_POST['due_date'] ?? ''));
        $original_amount = floatval($_POST['original_amount'] ?? 0.00);
        $discount_amount = floatval($_POST['discount_amount'] ?? 0.00);
        $penalty_amount = floatval($_POST['penalty_amount'] ?? 0.00);
        $remarks = sanitize(trim($_POST['remarks'] ?? ''));

        // Basic validations
        if ($member_id <= 0 || $fee_type_id <= 0 || empty($due_date) || $original_amount <= 0) {
            $error = 'कृपया सदस्य, शुल्क प्रकार, देय तिथि और मूल राशि सही भरें।';
        } else {
            try {
                // Calculate payable amount
                $payable_amount = $original_amount - $discount_amount + $penalty_amount;
                if ($payable_amount < 0) $payable_amount = 0.00;
                
                $outstanding_amount = $payable_amount;
                $status = 'pending';
                if (date('Y-m-d') > $due_date) {
                    $status = 'overdue';
                }

                $db->beginTransaction();

                // Insert into member_fee_dues
                $stmt = $db->prepare("
                    INSERT INTO member_fee_dues (
                        member_id, fee_type_id, financial_year, period_label, due_date,
                        original_amount, discount_amount, penalty_amount, payable_amount,
                        paid_amount, outstanding_amount, status, remarks, created_by
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        0.00, ?, ?, ?, ?
                    )
                ");
                $stmt->execute([
                    $member_id, $fee_type_id, $financial_year, $period_label, $due_date,
                    $original_amount, $discount_amount, $penalty_amount, $payable_amount,
                    $outstanding_amount, $status, $remarks, $_SESSION['user_id']
                ]);
                $due_id = $db->lastInsertId();

                // Log into due history
                $hist = $db->prepare("
                    INSERT INTO bar_fee_due_history (due_id, action, new_amount, remarks, performed_by)
                    VALUES (?, 'Due Created', ?, ?, ?)
                ");
                $hist->execute([$due_id, $payable_amount, 'व्यक्तिगत देय निर्मित की गई', $_SESSION['user_id']]);

                $db->commit();
                
                $_SESSION['flash_success'] = 'अधिवक्ता देय शुल्क सफलतापूर्वक पंजीकृत किया गया।';
                header("Location: member-ledger.php?member_id=" . $member_id);
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'त्रुटि: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">व्यक्तिगत देयता जोड़ें (Create Individual Due)</h4>
    <a href="members.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-arrow-left me-1"></i>सदस्य सूची</a>
</div>

<div class="card border-0 shadow-sm font-hindi small mb-5" style="max-width: 650px;">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle text-gold-custom me-2"></i>नया देयता फॉर्म (New Due Entry)</h6>
    </div>
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="create-due.php" onsubmit="document.getElementById('submitBtn').disabled = true;">
            <?php insertCSRF(); ?>

            <div class="mb-3">
                <label class="form-label fw-bold text-navy-custom">अधिवक्ता सदस्य (Select Member) <span class="text-danger">*</span></label>
                <select name="member_id" class="form-select form-select-sm" required>
                    <option value="">-- अधिवक्ता सदस्य चुनें --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo ($pre_member_id === intval($m['id'])) ? 'selected' : ''; ?>>
                            <?php echo e($m['full_name']); ?> (<?php echo e($m['membership_no']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold text-navy-custom">शुल्क का प्रकार (Fee Type) <span class="text-danger">*</span></label>
                    <select name="fee_type_id" id="fee_type_id" class="form-select form-select-sm" required onchange="updateDefaultAmount()">
                        <option value="">-- शुल्क का प्रकार --</option>
                        <?php foreach ($fee_types as $ft): ?>
                            <option value="<?php echo $ft['id']; ?>" data-amount="<?php echo $ft['amount']; ?>"><?php echo e($ft['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-navy-custom">वित्तीय वर्ष (Financial Year)</label>
                    <select name="financial_year" class="form-select form-select-sm">
                        <option value="">-- वित्तीय वर्ष --</option>
                        <?php foreach ($financial_years as $fy): ?>
                            <option value="<?php echo e($fy['name']); ?>" <?php echo ($fy['status'] === 'active') ? 'selected' : ''; ?>><?php echo e($fy['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold text-navy-custom">अवधि/विवरण लेबल (Period/Label)</label>
                    <input type="text" name="period_label" id="period_label" class="form-control form-control-sm" placeholder="उदा. Annual Bar Fee 2026-27">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-navy-custom">अंतिम देय तिथि (Due Date) <span class="text-danger">*</span></label>
                    <input type="date" name="due_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold text-navy-custom">मूल राशि (Original Amt) <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">₹</span>
                        <input type="number" step="0.01" name="original_amount" id="original_amount" class="form-control" required value="0.00" oninput="calculatePayable()">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-navy-custom">छूट (Discount Amt)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">₹</span>
                        <input type="number" step="0.01" name="discount_amount" id="discount_amount" class="form-control" value="0.00" oninput="calculatePayable()">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-navy-custom">दंड राशि (Penalty Amt)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">₹</span>
                        <input type="number" step="0.01" name="penalty_amount" id="penalty_amount" class="form-control" value="0.00" oninput="calculatePayable()">
                    </div>
                </div>
            </div>

            <div class="p-3 bg-light rounded mb-3">
                <span class="text-secondary fw-semibold d-block mb-1">कुल देय गणना (Calculated Payable):</span>
                <h4 class="fw-bold mb-0 text-navy-custom">₹<span id="payable_display">0.00</span></h4>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-navy-custom">रिमार्क्स/टिप्पणी (Remarks)</label>
                <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="देयता उत्पन्न करने का कारण लिखें"></textarea>
            </div>

            <div class="text-end">
                <button type="submit" id="submitBtn" class="btn btn-navy btn-sm px-4 font-hindi"><i class="bi bi-check-circle me-1"></i>देयता उत्पन्न करें</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateDefaultAmount() {
    const sel = document.getElementById('fee_type_id');
    const opt = sel.options[sel.selectedIndex];
    if (opt) {
        const amt = parseFloat(opt.getAttribute('data-amount')) || 0.00;
        document.getElementById('original_amount').value = amt.toFixed(2);
        
        // Auto prefill period label
        if (opt.text && opt.value) {
            const year = new Date().getFullYear();
            document.getElementById('period_label').value = opt.text + " " + year + "-" + ((year+1)%100);
        }
        calculatePayable();
    }
}

function calculatePayable() {
    const orig = parseFloat(document.getElementById('original_amount').value) || 0;
    const disc = parseFloat(document.getElementById('discount_amount').value) || 0;
    const pen = parseFloat(document.getElementById('penalty_amount').value) || 0;
    let pay = orig - disc + pen;
    if (pay < 0) pay = 0;
    document.getElementById('payable_display').textContent = pay.toFixed(2);
}
</script>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
