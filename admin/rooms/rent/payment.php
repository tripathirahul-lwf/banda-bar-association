<?php
/**
 * Room / Chamber Rent Offline Payment Recording Console
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'चैंबर किराया भुगतान दर्ज करें (Record Rent Payment)';
require_once __DIR__ . '/../../../includes/dashboard/header.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';

$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
$member = null;
$chamber = null;
$outstanding_dues = [];

// Resolve Member & Active Allotment
if ($db && $member_id > 0) {
    try {
        $m_stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $m_stmt->execute([$member_id]);
        $member = $m_stmt->fetch();
        
        if ($member) {
            $chamber = getMemberChamberSummary($member_id, $db);
            
            if ($chamber['has_chamber']) {
                // Fetch outstanding dues
                $d_stmt = $db->prepare("
                    SELECT * FROM chamber_rent_dues 
                    WHERE allotment_id = ? AND status NOT IN ('paid', 'waived', 'cancelled')
                    ORDER BY rent_month ASC, id ASC
                ");
                $d_stmt->execute([$chamber['allotment_id']]);
                $outstanding_dues = $d_stmt->fetchAll();
            }
        }
    } catch (PDOException $e) {
        error_log("Failed loading member details: " . $e->getMessage());
    }
}

// Fetch list of all active chamber allottees for dropdown select search
$allottees = [];
if ($db) {
    try {
        $allottees = $db->query("
            SELECT m.id, m.full_name, m.membership_no, c.chamber_no 
            FROM chamber_allotments a
            JOIN members m ON m.id = a.member_id
            JOIN chambers c ON c.id = a.chamber_id
            WHERE a.status = 'active'
            ORDER BY c.chamber_no ASC
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading active occupants: " . $e->getMessage());
    }
}

// Handle Payment Submission POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $amount = floatval($_POST['amount'] ?? 0.00);
        $pay_date = sanitize(trim($_POST['payment_date'] ?? ''));
        $pay_mode = sanitize(trim($_POST['payment_mode'] ?? 'Cash'));
        $ref_no = sanitize(trim($_POST['reference_no'] ?? ''));
        $receipt_no = sanitize(trim($_POST['receipt_no'] ?? ''));
        $remarks = sanitize(trim($_POST['remarks'] ?? ''));

        if ($amount <= 0 || empty($pay_date)) {
            $error = 'कृपया भुगतान राशि एवं भुगतान तिथि सही भरें।';
        } elseif (!$chamber['has_chamber']) {
            $error = 'इस सदस्य के पास कोई सक्रिय चैंबर आवंटन नहीं है।';
        } else {
            try {
                $db->beginTransaction();

                // If receipt number provided manually, verify uniqueness
                if (!empty($receipt_no)) {
                    $check_stmt = $db->prepare("SELECT COUNT(*) FROM chamber_rent_payments WHERE receipt_no = ?");
                    $check_stmt->execute([$receipt_no]);
                    if ($check_stmt->fetchColumn() > 0) {
                        throw new Exception("रसीद संख्या '$receipt_no' पहले से ही उपयोग की जा चुकी है।");
                    }
                } else {
                    // Autogenerate Receipt Number
                    $receipt_no = 'REC-RENT-' . date('Y') . '-' . sprintf("%05d", rand(1000, 9999));
                }

                // Generate Unique Payment Number
                $payment_no = generateChamberPaymentNumber($db);

                // Insert Rent Payment Record
                $pay_ins = $db->prepare("
                    INSERT INTO chamber_rent_payments 
                    (payment_no, member_id, allotment_id, payment_date, amount, payment_mode, reference_no, receipt_no, status, remarks, collected_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?)
                ");
                $pay_ins->execute([$payment_no, $member_id, $chamber['allotment_id'], $pay_date, $amount, $pay_mode, $ref_no, $receipt_no, $remarks, $_SESSION['user_id']]);
                $payment_id = $db->lastInsertId();

                // Allocate payment oldest-first
                $remaining_amount = $amount;
                foreach ($outstanding_dues as $due) {
                    if ($remaining_amount <= 0) break;

                    $due_payable = floatval($due['payable_amount']) - floatval($due['paid_amount']);
                    if ($due_payable <= 0) continue;

                    $allocated = min($remaining_amount, $due_payable);
                    
                    // Insert allocation
                    $alloc_ins = $db->prepare("
                        INSERT INTO chamber_rent_payment_allocations (payment_id, rent_due_id, allocated_amount) 
                        VALUES (?, ?, ?)
                    ");
                    $alloc_ins->execute([$payment_id, $due['id'], $allocated]);

                    // Recalculate rent due
                    recalculateChamberRentDue($due['id'], $db);

                    $remaining_amount -= $allocated;
                }

                // Audit Log
                $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, old_value, new_value, remarks, performed_by) VALUES ('Rent Payment Recorded', NULL, ?, ?, ?)");
                $audit->execute([$payment_no, "रसीद सं: $receipt_no, राशि: ₹$amount, आवंटी: {$member['full_name']}", $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = "किराया भुगतान ₹$amount सफलतापूर्वक दर्ज किया गया। रसीद संख्या: $receipt_no";
                
                header("Location: ../view.php?id=" . $chamber['allotment_id']);
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'भुगतान दर्ज करने में विफलता: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">चैंबर किराया भुगतान दर्ज करें (Chamber Rent Collection)</h4>
    <a href="../index.php" class="btn btn-xs btn-outline-navy"><i class="bi bg-grid me-1"></i>चैंबर रजिस्टर</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Member Selection Dropdown -->
<div class="card border-0 shadow-xs mb-4 font-hindi small">
    <div class="card-body p-3 bg-light rounded border border-light">
        <form method="GET" action="" class="row g-2">
            <div class="col-md-9">
                <label class="form-label fw-bold text-navy-custom">भुगतान करने वाले आवंटी सदस्य को चुनें (Select Active Allottee) *</label>
                <select name="member_id" class="form-select form-select-sm" required onchange="this.form.submit();">
                    <option value="">-- आवंटी चुनें --</option>
                    <?php foreach ($allottees as $al): ?>
                        <option value="<?php echo $al['id']; ?>" <?php echo ($member_id === $al['id']) ? 'selected' : ''; ?>>
                            <?php echo e($al['chamber_no']); ?> - <?php echo e($al['full_name']); ?> (<?php echo e($al['membership_no']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-navy btn-sm w-100"><i class="bi bi-person-check-fill me-1"></i>खाता विवरण लोड करें</button>
            </div>
        </form>
    </div>
</div>

<?php if ($member && $chamber['has_chamber']): ?>
    <div class="row g-4 font-hindi small text-navy-custom">
        <!-- Left: Outstanding Dues List -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-navy-custom text-white py-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-gold-custom me-2"></i>लंबित मासिक बकाया विवरणी (Unpaid Dues)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="font-size:0.75rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>किराया माह</th>
                                    <th>देय तिथि</th>
                                    <th class="text-end">कुल देय</th>
                                    <th class="text-end">प्राप्त जमा</th>
                                    <th class="text-end">लंबित बकाया</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($outstanding_dues)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-success fw-bold">कोई किराया बकाया शेष नहीं है। (All Dues Paid)</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($outstanding_dues as $od): ?>
                                        <tr>
                                            <td class="english-text fw-bold"><?php echo date('F Y', strtotime($od['rent_month'] . '-01')); ?></td>
                                            <td class="english-text"><?php echo date('d-m-Y', strtotime($od['due_date'])); ?></td>
                                            <td class="text-end english-text">₹<?php echo number_format($od['payable_amount'], 2); ?></td>
                                            <td class="text-end text-success english-text">₹<?php echo number_format($od['paid_amount'], 2); ?></td>
                                            <td class="text-end text-danger fw-bold english-text">₹<?php echo number_format($od['outstanding_amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Offline payment recorder form -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-navy-custom text-white py-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-cash-stack text-gold-custom me-2"></i>भुगतान विवरण दर्ज करें (Rent Collection Details)</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php insertCSRF(); ?>
                        <input type="hidden" name="record_payment" value="1">

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold">1. प्राप्त भुगतान राशि (Amount) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" name="amount" class="form-control fw-bold fs-6 text-danger" required placeholder="0.00" value="<?php echo $chamber['outstanding_rent']; ?>">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">2. भुगतान तिथि (Date) *</label>
                                <input type="date" name="payment_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold">3. भुगतान माध्यम (Mode) *</label>
                                <select name="payment_mode" class="form-select form-select-sm" required>
                                    <option value="Cash">Cash (नकद)</option>
                                    <option value="UPI">UPI (GooglePay/PhonePe)</option>
                                    <option value="Bank Transfer">Bank Transfer (IMPS/NEFT)</option>
                                    <option value="Cheque">Cheque (बैंक चेक)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">4. लेनदेन सन्दर्भ संख्या (Ref. No)</label>
                                <input type="text" name="reference_no" class="form-control form-control-sm" placeholder="उदा. UTR संख्या या चेक नं.">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">5. रसीद संख्या (Manual Receipt No - Optional)</label>
                            <input type="text" name="receipt_no" class="form-control form-control-sm" placeholder="खाली छोड़ने पर स्वतः रसीद संख्या जनरेट होगी।">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">6. विशेष विवरण / टिप्पणी (Remarks)</label>
                            <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="उदा. वार्षिक अग्रिम या विशेष किराया भुगतान..."></textarea>
                        </div>

                        <div class="alert alert-secondary border-0 p-2 mb-3" style="font-size: 0.68rem; line-height: 1.3;">
                            <i class="bi bi-info-circle me-1 text-primary"></i><strong>ऑटो-एलोकेशन नीति:</strong> प्राप्त जमा राशि को बकाया महीनों की तिथि के अनुसार (सबसे पुराने महीने से पहले) आबंटित किया जाएगा।
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-success btn-sm w-100 py-2"><i class="bi bi-check-circle-fill me-1"></i>भुगतान सहेजें एवं रसीद निर्गत करें</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($member_id > 0): ?>
    <div class="alert alert-danger font-hindi text-center my-4 py-3 border-0 rounded">
        <strong>त्रुटि: चयनित सदस्य के पास कोई सक्रिय कक्ष आवंटन नहीं है।</strong>
    </div>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../../../includes/dashboard/footer.php';
?>
