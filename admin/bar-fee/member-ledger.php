<?php
/**
 * Member Advocate Bar Fee Ledger
 * District Bar Association, Banda
 */

$pageTitle = 'अधिवक्ता सदस्य शुल्क बहीखाता (Member Fee Ledger)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';

$member_id = intval($_GET['member_id'] ?? 0);
$member = null;
$dues = [];
$payments = [];
$ledger_items = [];

// Fetch member summaries
$summary = [
    'total_due' => 0.00,
    'total_paid' => 0.00,
    'total_outstanding' => 0.00,
    'overdue_amount' => 0.00
];

if ($db && $member_id > 0) {
    try {
        // Fetch member details
        $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
        
        if (!$member) {
            $_SESSION['flash_error'] = 'सदस्य रिकॉर्ड नहीं मिला।';
            header("Location: members.php");
            exit;
        }

        // Fetch Dues
        $dues_stmt = $db->prepare("
            SELECT d.*, t.name as fee_name 
            FROM member_fee_dues d
            JOIN bar_fee_types t ON t.id = d.fee_type_id
            WHERE d.member_id = ? AND d.status != 'cancelled'
            ORDER BY d.created_at ASC
        ");
        $dues_stmt->execute([$member_id]);
        $dues = $dues_stmt->fetchAll();

        // Fetch Payments
        $pay_stmt = $db->prepare("
            SELECT p.* 
            FROM bar_fee_payments p
            WHERE p.member_id = ? AND p.status = 'confirmed'
            ORDER BY p.payment_date ASC, p.id ASC
        ");
        $pay_stmt->execute([$member_id]);
        $payments = $pay_stmt->fetchAll();

        // Build chronological Ledger items
        foreach ($dues as $d) {
            $ledger_items[] = [
                'date' => $d['created_at'],
                'particulars' => $d['fee_name'] . ' (' . ($d['period_label'] ?: $d['financial_year']) . ')',
                'type' => 'due',
                'due_amount' => floatval($d['original_amount']),
                'payment_amount' => 0.00,
                'adjustment_amount' => floatval($d['discount_amount']) * -1 + floatval($d['penalty_amount']),
                'receipt_no' => '',
                'status' => $d['status']
            ];
        }

        foreach ($payments as $p) {
            $ledger_items[] = [
                'payment_id' => $p['id'],
                'date' => $p['payment_date'],
                'particulars' => 'भुगतान रसीद सं. ' . $p['receipt_no'] . ' (' . $p['payment_mode'] . ')',
                'type' => 'payment',
                'due_amount' => 0.00,
                'payment_amount' => floatval($p['amount']),
                'adjustment_amount' => 0.00,
                'receipt_no' => $p['receipt_no'],
                'status' => $p['status']
            ];
        }

        // Sort ledger by date
        usort($ledger_items, function($a, $b) {
            return strtotime($a['date']) <=> strtotime($b['date']);
        });

        // Get live calculated stats
        $summary = getMemberFeeSummary($member_id, $db);

    } catch (PDOException $e) {
        error_log("Failed compiling member ledger: " . $e->getMessage());
    }
} else {
    $_SESSION['flash_error'] = 'अवैध सदस्य आईडी प्रेषित।';
    header("Location: members.php");
    exit;
}

// Handle Payment Cancellation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_payment'])) {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $payment_id = intval($_POST['payment_id'] ?? 0);
        $reason = sanitize(trim($_POST['cancellation_reason'] ?? ''));
        if ($payment_id <= 0 || empty($reason)) {
            $error = 'कृपया रद्दीकरण का कारण अवश्य लिखें।';
        } else {
            try {
                $db->beginTransaction();
                
                // Fetch payment details
                $pay_stmt = $db->prepare("SELECT * FROM bar_fee_payments WHERE id = ? AND status = 'confirmed'");
                $pay_stmt->execute([$payment_id]);
                $pay = $pay_stmt->fetch();
                if (!$pay) {
                    throw new Exception('भुगतान रिकॉर्ड सक्रिय नहीं है या पहले ही रद्द हो चुका है।');
                }

                // Fetch allocations to figure out dues affected
                $alloc_stmt = $db->prepare("SELECT due_id, allocated_amount FROM bar_fee_payment_allocations WHERE payment_id = ?");
                $alloc_stmt->execute([$payment_id]);
                $allocs = $alloc_stmt->fetchAll();

                // Update payment status
                $up_stmt = $db->prepare("UPDATE bar_fee_payments SET status = 'cancelled', cancelled_by = ?, cancelled_at = NOW(), cancellation_reason = ? WHERE id = ?");
                $up_stmt->execute([$_SESSION['user_id'], $reason, $payment_id]);

                // Delete allocations so recalculator doesn't count them
                $del_stmt = $db->prepare("DELETE FROM bar_fee_payment_allocations WHERE payment_id = ?");
                $del_stmt->execute([$payment_id]);

                // Recalculate affected dues
                foreach ($allocs as $al) {
                    recalculateMemberFeeDue($al['due_id'], $db);
                    
                    // Log to due history
                    $h_stmt = $db->prepare("INSERT INTO bar_fee_due_history (due_id, action, remarks, performed_by) VALUES (?, 'Payment Cancelled Reversal', ?, ?)");
                    $h_stmt->execute([$al['due_id'], 'भुगतान रसीद सं. ' . $pay['receipt_no'] . ' निरस्त होने के कारण समायोजन वापस लिया गया', $_SESSION['user_id']]);
                }

                // Log payment history
                $hist = $db->prepare("INSERT INTO bar_fee_payment_history (payment_id, action, old_status, new_status, remarks, performed_by) VALUES (?, 'Payment Cancelled', 'confirmed', 'cancelled', ?, ?)");
                $hist->execute([$payment_id, $reason, $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = 'भुगतान सफलतापूर्वक निरस्त किया गया और बकाये पुनर्स्थापित किये गए।';
                header("Location: member-ledger.php?member_id=" . $member_id);
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'निरस्त करने में विफलता: ' . $e->getMessage();
            }
        }
    }
}

// Handle Waiver / Discount Adjustment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $due_id = intval($_POST['due_id'] ?? 0);
        $action_type = sanitize($_POST['action_type']);
        $adjust_amount = floatval($_POST['adjust_amount'] ?? 0.00);
        $reason = sanitize(trim($_POST['reason'] ?? ''));

        if ($due_id <= 0 || $adjust_amount <= 0 || empty($reason)) {
            $error = 'कृपया सही देयता रिकॉर्ड, समायोजन राशि और उचित कारण भरें।';
        } else {
            try {
                $db->beginTransaction();

                // Fetch due record
                $stmt = $db->prepare("SELECT * FROM member_fee_dues WHERE id = ?");
                $stmt->execute([$due_id]);
                $due_record = $stmt->fetch();

                if (!$due_record) {
                    throw new Exception('संबंधित देयता रिकॉर्ड डेटाबेस में नहीं मिला।');
                }

                if ($action_type === 'waiver') {
                    $new_discount = floatval($due_record['discount_amount']) + $adjust_amount;
                    // Update discount
                    $up = $db->prepare("UPDATE member_fee_dues SET discount_amount = ? WHERE id = ?");
                    $up->execute([$new_discount, $due_id]);

                    // Audit Log
                    $hist = $db->prepare("INSERT INTO bar_fee_due_history (due_id, action, old_amount, new_amount, remarks, performed_by) VALUES (?, 'Waiver Applied', ?, ?, ?, ?)");
                    $hist->execute([$due_id, $due_record['discount_amount'], $new_discount, $reason, $_SESSION['user_id']]);
                } elseif ($action_type === 'penalty') {
                    $new_penalty = floatval($due_record['penalty_amount']) + $adjust_amount;
                    // Update penalty
                    $up = $db->prepare("UPDATE member_fee_dues SET penalty_amount = ? WHERE id = ?");
                    $up->execute([$new_penalty, $due_id]);

                    // Audit Log
                    $hist = $db->prepare("INSERT INTO bar_fee_due_history (due_id, action, old_amount, new_amount, remarks, performed_by) VALUES (?, 'Penalty Applied', ?, ?, ?, ?)");
                    $hist->execute([$due_id, $due_record['penalty_amount'], $new_penalty, $reason, $_SESSION['user_id']]);
                }

                recalculateMemberFeeDue($due_id, $db);
                $db->commit();

                $_SESSION['flash_success'] = 'शुल्क समायोजन सफलतापूर्वक सहेजा गया।';
                header("Location: member-ledger.php?member_id=" . $member_id);
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'समायोजन करने में विफलता: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">सदस्य शुल्क बहीखाता (Member Ledger Statement)</h4>
    <div class="d-flex gap-2">
        <a href="members.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-arrow-left me-1"></i>सदस्य सूची</a>
        <a href="statement.php?member_id=<?php echo $member_id; ?>" target="_blank" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-printer me-1"></i>स्टेटमेंट प्रिंट</a>
        <a href="payments/create.php?member_id=<?php echo $member_id; ?>" class="btn btn-xs btn-success fw-semibold"><i class="bi bi-cash-stack me-1"></i>भुगतान रिकॉर्ड करें</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Member Profile Overview and Metrics -->
<div class="row g-3 mb-4 font-hindi small text-navy-custom">
    <div class="col-lg-5">
        <div class="card p-3 border-0 bg-light-custom h-100 shadow-xs">
            <h6 class="fw-bold mb-2 border-bottom pb-2"><i class="bi bi-person-circle text-gold-dark me-2"></i>अधिवक्ता सदस्य प्रोफ़ाइल</h6>
            <div class="row g-1">
                <div class="col-5 text-secondary">अधिवक्ता का नाम:</div>
                <div class="col-7 fw-bold"><?php echo e($member['full_name']); ?></div>
                <div class="col-5 text-secondary">सदस्यता संख्या:</div>
                <div class="col-7 english-text fw-bold"><?php echo e($member['membership_no']); ?></div>
                <div class="col-5 text-secondary">नामांकन संख्या:</div>
                <div class="col-7 english-text"><?php echo e($member['enrollment_no']); ?></div>
                <div class="col-5 text-secondary">मोबाइल नम्बर:</div>
                <div class="col-7 english-text"><?php echo e($member['mobile']); ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-7">
        <div class="card p-3 border-0 bg-white border border-light h-100 shadow-xs">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-bank text-gold-dark me-2"></i>वित्तीय सारांश (Financial Summary)</h6>
            <div class="row g-2 text-center">
                <div class="col-4">
                    <span class="text-secondary font-size-xs d-block mb-1">कुल देय (Total Dues)</span>
                    <strong class="text-navy-custom fs-5">₹<?php echo number_format($summary['total_due'], 2); ?></strong>
                </div>
                <div class="col-4 border-start">
                    <span class="text-secondary font-size-xs d-block mb-1">कुल भुगतान (Paid)</span>
                    <strong class="text-success fs-5">₹<?php echo number_format($summary['total_paid'], 2); ?></strong>
                </div>
                <div class="col-4 border-start">
                    <span class="text-secondary font-size-xs d-block mb-1">शेष बकाया (Outstanding)</span>
                    <strong class="text-danger fs-5">₹<?php echo number_format($summary['total_outstanding'], 2); ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ledger Statements Table -->
<div class="card border-0 shadow-sm font-hindi small mb-5">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-spreadsheet text-gold-custom me-2"></i>अधिवक्ता बहीखाता विवरण (Chronological Ledger)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2">तिथि (Date)</th>
                        <th>विवरण (Particulars)</th>
                        <th class="text-end">शुल्क देय (Debit)</th>
                        <th class="text-end">प्राप्त जमा (Credit)</th>
                        <th class="text-end">छूट/समायोजन (Adjustment)</th>
                        <th>दस्तावेज़</th>
                        <th>स्थिति</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledger_items)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">इस सदस्य के लिए कोई बहीखाता विवरण प्रविष्टि उपलब्ध नहीं है।</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $running_bal = 0.00;
                        foreach ($ledger_items as $li): 
                            $debit = $li['due_amount'];
                            $credit = $li['payment_amount'];
                            $adj = $li['adjustment_amount'];
                        ?>
                            <tr>
                                <td class="py-2 english-text"><?php echo date('d-m-Y', strtotime($li['date'])); ?></td>
                                <td class="fw-bold text-navy-custom"><?php echo e($li['particulars']); ?></td>
                                <td class="text-end text-navy-custom english-text"><?php echo $debit > 0 ? '₹' . number_format($debit, 2) : '-'; ?></td>
                                <td class="text-end text-success english-text"><?php echo $credit > 0 ? '₹' . number_format($credit, 2) : '-'; ?></td>
                                <td class="text-end text-secondary-custom english-text"><?php echo $adj != 0 ? '₹' . number_format($adj, 2) : '-'; ?></td>
                                <td>
                                    <?php if (!empty($li['receipt_no'])): ?>
                                        <a href="receipt.php?receipt_no=<?php echo urlencode($li['receipt_no']); ?>" target="_blank" class="english-text text-navy-custom fw-semibold"><i class="bi bi-file-earmark-pdf me-1 text-danger"></i><?php echo e($li['receipt_no']); ?></a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $st = $li['status'];
                                    $c = ($st === 'paid' || $st === 'confirmed') ? 'bg-success' : (($st === 'pending') ? 'bg-danger' : (($st === 'cancelled') ? 'bg-secondary' : 'bg-warning text-dark'));
                                    ?>
                                    <span class="badge <?php echo $c; ?> font-size-xs"><?php echo e(ucfirst($st)); ?></span>
                                    <?php if ($li['type'] === 'payment' && $st === 'confirmed' && $_SESSION['role'] === 'admin'): ?>
                                        <form method="POST" class="d-inline ms-1" onsubmit="return confirm('क्या आप वास्तव में इस भुगतान को निरस्त करना चाहते हैं? सभी आबंटन वापस लिए जायेंगे।');">
                                            <?php insertCSRF(); ?>
                                            <input type="hidden" name="cancel_payment" value="1">
                                            <input type="hidden" name="payment_id" value="<?php echo $li['payment_id']; ?>">
                                            <input type="text" name="cancellation_reason" class="form-control form-control-xs d-inline-block px-1 py-0" style="width: 80px; font-size:0.7rem;" placeholder="कारण" required>
                                            <button type="submit" class="btn btn-xs btn-danger p-0 px-1 font-hindi" style="font-size: 0.7rem;">निरस्त</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Dues Adjustments Panel (Waivers, Penalties) -->
<div class="row g-4 font-hindi small mb-5">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-shield-lock-fill text-gold-custom me-2"></i>शुल्क समायोजन (Waiver / Penalty Adjustments)</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php insertCSRF(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-navy-custom">समायोजन हेतु देय शुल्क चुनें (Select Due Item) <span class="text-danger">*</span></label>
                        <select name="due_id" class="form-select form-select-sm" required>
                            <option value="">-- देयता चुनें --</option>
                            <?php foreach ($dues as $d): ?>
                                <?php if ($d['outstanding_amount'] > 0): ?>
                                    <option value="<?php echo $d['id']; ?>">
                                        <?php echo e($d['fee_name']); ?> - FY <?php echo e($d['financial_year']); ?> (बकाया: ₹<?php echo number_format($d['outstanding_amount'], 2); ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold text-navy-custom">समायोजन प्रकार (Adjustment Type) <span class="text-danger">*</span></label>
                            <select name="action_type" class="form-select form-select-sm" required>
                                <option value="waiver">Waiver / Discount (छूट/माफी)</option>
                                <option value="penalty">Penalty (दंड राशि जोड़ें)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold text-navy-custom">समायोजन राशि (Amount) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" name="adjust_amount" class="form-control" required value="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-navy-custom">समायोजन का स्पष्टीकरण/कारण (Reason) <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control form-control-sm" rows="2" required placeholder="उदा. कार्यकारिणी स्वीकृति दिनांक 20-08-2026 के अंतर्गत प्राप्त विशेष छूट"></textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-navy btn-sm"><i class="bi bi-shield-fill-check me-1"></i>समायोजन लागू करें</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Active Dues Summary List -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm bg-light">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-card-checklist text-gold-custom me-2"></i>वर्तमान बकाया मदें (Active Due Items)</h6>
            </div>
            <div class="card-body">
                <?php 
                $has_active_dues = false;
                foreach ($dues as $d):
                    if ($d['outstanding_amount'] > 0):
                        $has_active_dues = true;
                ?>
                    <div class="p-2 border bg-white rounded shadow-xs mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <strong class="text-navy-custom"><?php echo e($d['fee_name']); ?></strong>
                            <span class="badge bg-danger text-uppercase font-size-xs"><?php echo e($d['status']); ?></span>
                        </div>
                        <div class="row g-1 text-secondary font-size-xs">
                            <div class="col-6">लेबल: <?php echo e($d['period_label'] ?: 'FY ' . $d['financial_year']); ?></div>
                            <div class="col-6 text-end">अंतिम तिथि: <?php echo date('d-m-Y', strtotime($d['due_date'])); ?></div>
                            <div class="col-6">मूल देयता: ₹<?php echo number_format($d['payable_amount'], 2); ?></div>
                            <div class="col-6 text-end text-danger fw-bold">शेष बकाया: ₹<?php echo number_format($d['outstanding_amount'], 2); ?></div>
                        </div>
                    </div>
                <?php 
                    endif;
                endforeach; 
                if (!$has_active_dues):
                ?>
                    <div class="text-center text-success py-4">
                        <i class="bi bi-check-circle-fill display-4"></i>
                        <p class="fw-bold mt-2">इस अधिवक्ता सदस्य का कोई बकाया शुल्क नहीं है।</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
