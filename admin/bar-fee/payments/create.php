<?php
/**
 * Record Bar Fee Offline Payment
 * District Bar Association, Banda
 */

$pageTitle = 'अधिवक्ता भुगतान दर्ज करें (Record Advocate Fee Payment)';
require_once __DIR__ . '/../../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';

$pre_member_id = intval($_GET['member_id'] ?? 0);

$members = [];
$outstanding_dues = [];

if ($db) {
    try {
        // Fetch all active/inactive members
        $members = $db->query("SELECT id, full_name, membership_no FROM members WHERE membership_status IN ('active', 'inactive') ORDER BY full_name ASC")->fetchAll();
        
        // Fetch outstanding dues if member pre-selected
        if ($pre_member_id > 0) {
            $stmt = $db->prepare("
                SELECT d.*, t.name as fee_name 
                FROM member_fee_dues d
                JOIN bar_fee_types t ON t.id = d.fee_type_id
                WHERE d.member_id = ? AND d.status IN ('pending', 'partially_paid', 'overdue')
                ORDER BY d.due_date ASC, d.id ASC
            ");
            $stmt->execute([$pre_member_id]);
            $outstanding_dues = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Failed to load payment recorder data: " . $e->getMessage());
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'क्रॉस-साइट सुरक्षा सत्यापन विफल रहा।';
    } else {
        $member_id = intval($_POST['member_id'] ?? 0);
        $payment_date = sanitize(trim($_POST['payment_date'] ?? ''));
        $amount = floatval($_POST['amount'] ?? 0.00);
        $payment_mode = sanitize(trim($_POST['payment_mode'] ?? ''));
        $transaction_reference = sanitize(trim($_POST['transaction_reference'] ?? ''));
        $receipt_mode = sanitize(trim($_POST['receipt_mode'] ?? 'auto'));
        $manual_receipt_no = sanitize(trim($_POST['manual_receipt_no'] ?? ''));
        $remarks = sanitize(trim($_POST['remarks'] ?? ''));
        
        // Manual vs Auto allocation arrays
        $allocations = $_POST['allocations'] ?? []; // format: [due_id => amount]

        if ($member_id <= 0 || empty($payment_date) || $amount <= 0 || empty($payment_mode)) {
            $error = 'कृपया सभी आवश्यक फ़ील्ड जैसे कि सदस्य, दिनांक, भुगतान माध्यम और सही राशि भरें।';
        } else {
            try {
                $db->beginTransaction();

                // 1. Resolve Receipt Number
                $receipt_no = '';
                if ($receipt_mode === 'manual') {
                    if (empty($manual_receipt_no)) {
                        throw new Exception('कृपया रसीद बुक से मैन्युअल रसीद संख्या दर्ज करें।');
                    }
                    // Check duplicate
                    $dup_stmt = $db->prepare("SELECT COUNT(*) FROM bar_fee_payments WHERE receipt_no = ?");
                    $dup_stmt->execute([$manual_receipt_no]);
                    if ($dup_stmt->fetchColumn() > 0) {
                        throw new Exception('रसीद संख्या ' . $manual_receipt_no . ' पूर्व में ही उपयोग हो चुकी है।');
                    }
                    $receipt_no = $manual_receipt_no;
                } else {
                    // Auto receipt generation
                    $receipt_prefix = 'REC-BF';
                    $year = date('Y');
                    $seq_stmt = $db->prepare("SELECT receipt_no FROM bar_fee_payments WHERE receipt_no LIKE :pat ORDER BY id DESC LIMIT 1");
                    $seq_stmt->execute([':pat' => "$receipt_prefix-$year-%"]);
                    $last_receipt = $seq_stmt->fetchColumn();
                    if ($last_receipt) {
                        $parts = explode('-', $last_receipt);
                        $seq = intval(end($parts)) + 1;
                    } else {
                        $seq = 1;
                    }
                    $receipt_no = sprintf("%s-%d-%05d", $receipt_prefix, $year, $seq);
                }

                // 2. Resolve Payment Number
                $payment_no = generateBarFeePaymentNumber($db);

                // 3. Process Dues Allocations oldest-first
                $allocated_total = 0.00;
                $all_allocs = [];

                // Re-fetch outstanding dues from DB (never trust browser values)
                $db_dues_stmt = $db->prepare("
                    SELECT id, payable_amount, paid_amount, outstanding_amount, due_date 
                    FROM member_fee_dues 
                    WHERE member_id = ? AND status IN ('pending', 'partially_paid', 'overdue')
                    ORDER BY due_date ASC, id ASC
                ");
                $db_dues_stmt->execute([$member_id]);
                $db_dues = $db_dues_stmt->fetchAll();

                $allocation_mode = sanitize(trim($_POST['allocation_mode'] ?? 'auto'));

                if ($allocation_mode === 'auto') {
                    // Auto allocate oldest first
                    $remaining_pay = $amount;
                    foreach ($db_dues as $dd) {
                        if ($remaining_pay <= 0) break;
                        $out = floatval($dd['outstanding_amount']);
                        $alloc = min($remaining_pay, $out);
                        if ($alloc > 0) {
                            $all_allocs[$dd['id']] = $alloc;
                            $allocated_total += $alloc;
                            $remaining_pay -= $alloc;
                        }
                    }
                } else {
                    // Manual Allocation
                    foreach ($db_dues as $dd) {
                        $due_id = $dd['id'];
                        $val = floatval($allocations[$due_id] ?? 0.00);
                        if ($val > 0) {
                            $out = floatval($dd['outstanding_amount']);
                            if ($val > $out) {
                                throw new Exception('देय आईडी ' . $due_id . ' के लिए आबंटित राशि शेष बकाया से अधिक नहीं हो सकती।');
                            }
                            $all_allocs[$due_id] = $val;
                            $allocated_total += $val;
                        }
                    }
                }

                // Overpayment Prevention check
                if (abs($allocated_total - $amount) > 0.01) {
                    throw new Exception('आबंटित कुल राशि (₹' . number_format($allocated_total, 2) . ') भुगतान की राशि (₹' . number_format($amount, 2) . ') के बराबर होनी चाहिए।');
                }

                // 4. Insert Payment Record
                $status_code = 'confirmed';
                $ins_pay = $db->prepare("
                    INSERT INTO bar_fee_payments (
                        payment_no, member_id, payment_date, amount, payment_mode,
                        transaction_reference, receipt_no, status, remarks, collected_by,
                        created_by, approved_by, approved_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, NOW()
                    )
                ");
                $ins_pay->execute([
                    $payment_no, $member_id, $payment_date, $amount, $payment_mode,
                    $transaction_reference, $receipt_no, $status_code, $remarks, $_SESSION['user_id'],
                    $_SESSION['user_id'], $_SESSION['user_id']
                ]);
                $payment_id = $db->lastInsertId();

                // 5. Insert Allocations & Update Dues
                foreach ($all_allocs as $due_id => $alloc_amt) {
                    $ins_alloc = $db->prepare("
                        INSERT INTO bar_fee_payment_allocations (payment_id, due_id, allocated_amount)
                        VALUES (?, ?, ?)
                    ");
                    $ins_alloc->execute([$payment_id, $due_id, $alloc_amt]);

                    // Recalculate
                    recalculateMemberFeeDue($due_id, $db);
                }

                // 6. Log payment history
                $hist = $db->prepare("
                    INSERT INTO bar_fee_payment_history (payment_id, action, new_status, remarks, performed_by)
                    VALUES (?, 'Payment Confirmed', 'confirmed', ?, ?)
                ");
                $hist->execute([$payment_id, 'भुगतान और शुल्क आवंटन सफलतापूर्वक दर्ज किया गया।', $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = 'अधिवक्ता भुगतान सफलतापूर्वक रसीद संख्या ' . $receipt_no . ' के साथ दर्ज किया गया।';
                header("Location: ../member-ledger.php?member_id=" . $member_id);
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
    <h4 class="text-navy-custom fw-bold mb-0">भुगतान प्राप्त करें (Record Offline Payment)</h4>
    <a href="../members.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-arrow-left me-1"></i>सदस्य सूची</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row g-4 font-hindi small mb-5">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-cash-stack text-gold-custom me-2"></i>ऑफ़लाइन भुगतान विवरण (Record Payment Form)</h6>
            </div>
            <div class="card-body">
                <form method="POST" id="paymentForm" onsubmit="document.getElementById('submitBtn').disabled = true;">
                    <?php insertCSRF(); ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-navy-custom">अधिवक्ता सदस्य (Select Member) <span class="text-danger">*</span></label>
                        <select name="member_id" id="member_id" class="form-select form-select-sm" required onchange="reloadMemberDues(this.value)">
                            <option value="">-- सदस्य चुनें --</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['id']; ?>" <?php echo ($pre_member_id === intval($m['id'])) ? 'selected' : ''; ?>>
                                    <?php echo e($m['full_name']); ?> (<?php echo e($m['membership_no']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-navy-custom">भुगतान तिथि (Date) <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-navy-custom">भुगतान माध्यम (Mode) <span class="text-danger">*</span></label>
                            <select name="payment_mode" class="form-select form-select-sm" required>
                                <option value="Cash">Cash (नकद)</option>
                                <option value="UPI">UPI (GooglePay/PhonePe/Paytm)</option>
                                <option value="Bank Transfer">Bank Transfer (बैंक ट्रान्सफर)</option>
                                <option value="Cheque">Cheque (चेक)</option>
                                <option value="NEFT / RTGS">NEFT / RTGS</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-navy-custom">रसीद प्रकार (Receipt Mode)</label>
                            <select name="receipt_mode" id="receipt_mode" class="form-select form-select-sm" onchange="toggleManualReceiptBlock()">
                                <option value="auto">Automatic (सिस्टम जनरेटेड रसीद)</option>
                                <option value="manual">Manual (मैन्युअल रसीद बुक से दर्ज करें)</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-none" id="manual_receipt_block">
                            <label class="form-label fw-bold text-navy-custom">रसीद संख्या (Manual Receipt No) <span class="text-danger">*</span></label>
                            <input type="text" name="manual_receipt_no" class="form-control form-control-sm" placeholder="उदा. REC-BF-1002">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-navy-custom">कुल भुगतान राशि (Amount Received) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" name="amount" id="amount" class="form-control fw-bold" required value="0.00" oninput="allocateAmountAutomatically()">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-navy-custom">लेनदेन सन्दर्भ संख्या (UTR / Ref No)</label>
                            <input type="text" name="transaction_reference" class="form-control form-control-sm" placeholder="उदा. UTR संख्या या बैंक संदर्भ">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-navy-custom">आवंटन प्रकार (Allocation Method)</label>
                        <select name="allocation_mode" id="allocation_mode" class="form-select form-select-sm" onchange="toggleAllocationInputs()">
                            <option value="auto">Auto Allocate (प्राथमिकता - पुराना देयता पहले)</option>
                            <option value="manual">Manual Allocation (मैन्युअल रूप से आवंटित करें)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-navy-custom">रिमार्क्स/टिप्पणी (Remarks)</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="भुगतान संदर्भ विवरण लिखें..."></textarea>
                    </div>

                    <div class="text-end border-top pt-3">
                        <button type="submit" id="submitBtn" class="btn btn-success btn-sm px-4"><i class="bi bi-check-circle me-1"></i>भुगतान सुरक्षित करें</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Outstanding Dues Checklist (oldest-first) -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-gold-custom me-2"></i>बकाया शुल्क विवरण (Outstanding Dues Checklist)</h6>
            </div>
            <div class="card-body bg-light">
                <?php if ($pre_member_id <= 0): ?>
                    <p class="text-center text-muted py-4 mb-0">कृपया बकाया सूची देखने के लिए अधिवक्ता सदस्य का चयन करें।</p>
                <?php elseif (empty($outstanding_dues)): ?>
                    <div class="text-center text-success py-4 mb-0">
                        <i class="bi bi-check-circle-fill display-4 mb-1"></i>
                        <p class="fw-bold mb-0">इस सदस्य का कोई बकाया शुल्क नहीं है।</p>
                    </div>
                <?php else: ?>
                    <p class="text-secondary font-size-xs border-bottom pb-2">पुराने बकाया शुल्क तिथि के अनुसार ऊपर दर्शाये गए हैं।</p>
                    <div class="d-flex flex-column gap-2" id="dues_list_container">
                        <?php foreach ($outstanding_dues as $d): ?>
                            <div class="p-2 border bg-white rounded shadow-xs mb-1" id="due_row_<?php echo $d['id']; ?>">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong class="text-navy-custom"><?php echo e($d['fee_name']); ?></strong>
                                    <span class="badge bg-danger text-uppercase font-size-xs"><?php echo e($d['status']); ?></span>
                                </div>
                                <div class="row g-1 text-secondary font-size-xs">
                                    <div class="col-6">वित्तीय वर्ष: <strong class="text-navy-custom english-text"><?php echo e($d['financial_year']); ?></strong></div>
                                    <div class="col-6 text-end">देय तिथि: <span class="english-text"><?php echo date('d-m-Y', strtotime($d['due_date'])); ?></span></div>
                                    <div class="col-6">मूल देयता: ₹<?php echo number_format($d['payable_amount'], 2); ?></div>
                                    <div class="col-6 text-end text-danger fw-bold">शेष: ₹<span class="due-out-val" id="out_val_<?php echo $d['id']; ?>" data-val="<?php echo $d['outstanding_amount']; ?>"><?php echo number_format($d['outstanding_amount'], 2); ?></span></div>
                                </div>
                                <div class="mt-2 alloc-input-block d-none">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text font-size-xs bg-light">आवंटित ₹</span>
                                        <input type="number" step="0.01" name="allocations[<?php echo $d['id']; ?>]" id="alloc_<?php echo $d['id']; ?>" class="form-control alloc-amt-field" value="0.00" oninput="calculateManualAllocSum()">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function reloadMemberDues(memberId) {
    if (memberId) {
        window.location.href = "create.php?member_id=" + memberId;
    }
}

function toggleManualReceiptBlock() {
    const val = document.getElementById('receipt_mode').value;
    const block = document.getElementById('manual_receipt_block');
    if (val === 'manual') {
        block.classList.remove('d-none');
    } else {
        block.classList.add('d-none');
    }
}

function toggleAllocationInputs() {
    const val = document.getElementById('allocation_mode').value;
    const blocks = document.querySelectorAll('.alloc-input-block');
    blocks.forEach(b => {
        if (val === 'manual') {
            b.classList.remove('d-none');
        } else {
            b.classList.add('d-none');
        }
    });
    if (val === 'auto') {
        allocateAmountAutomatically();
    }
}

function allocateAmountAutomatically() {
    const totalAmt = parseFloat(document.getElementById('amount').value) || 0;
    const mode = document.getElementById('allocation_mode').value;
    if (mode !== 'auto') return;

    let remaining = totalAmt;
    const fields = document.querySelectorAll('.alloc-amt-field');
    fields.forEach(f => {
        const dueId = f.id.split('_')[1];
        const outstandingSpan = document.getElementById('out_val_' + dueId);
        if (outstandingSpan) {
            const outAmt = parseFloat(outstandingSpan.getAttribute('data-val')) || 0;
            const alloc = Math.min(remaining, outAmt);
            f.value = alloc.toFixed(2);
            remaining -= alloc;
        }
    });
}

function calculateManualAllocSum() {
    const fields = document.querySelectorAll('.alloc-amt-field');
    let sum = 0;
    fields.forEach(f => {
        sum += parseFloat(f.value) || 0;
    });
    document.getElementById('amount').value = sum.toFixed(2);
}
</script>

<?php 
require_once __DIR__ . '/../../../includes/dashboard/footer.php';
?>
