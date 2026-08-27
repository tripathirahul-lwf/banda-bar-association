<?php
/**
 * Room / Chamber Rent Bulk Generation Console
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'कक्ष किराया बिलिंग जनरेशन (Generate Rent Dues)';
require_once __DIR__ . '/../../../includes/dashboard/header.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';
$preview_mode = false;
$preview_results = [];

// Handle Preview and Submission POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $rent_month = sanitize(trim($_POST['rent_month'] ?? '')); // Format: YYYY-MM
        $due_date = sanitize(trim($_POST['due_date'] ?? ''));
        $billing_scope = sanitize($_POST['billing_scope'] ?? 'all');
        
        if (empty($rent_month) || empty($due_date)) {
            $error = 'कृपया किराया माह एवं देय तिथि सही भरें।';
        } else {
            // Find active allotments
            try {
                $sql = "
                    SELECT a.*, c.chamber_no, m.full_name 
                    FROM chamber_allotments a
                    JOIN chambers c ON c.id = a.chamber_id
                    JOIN members m ON m.id = a.member_id
                    WHERE a.status = 'active'
                ";
                $stmt = $db->query($sql);
                $active_allots = $stmt->fetchAll();
                
                if (empty($active_allots)) {
                    $error = 'वर्तमान में कोई सक्रिय चैंबर आवंटन नहीं मिला।';
                } else {
                    if (isset($_POST['preview'])) {
                        $preview_mode = true;
                        $total_bills = 0;
                        $total_amount = 0.00;
                        $skipped_count = 0;
                        
                        foreach ($active_allots as $al) {
                            // Check if duplicate exists
                            $dup_check = $db->prepare("SELECT COUNT(*) FROM chamber_rent_dues WHERE allotment_id = ? AND rent_month = ?");
                            $dup_check->execute([$al['id'], $rent_month]);
                            $is_dup = ($dup_check->fetchColumn() > 0);
                            
                            if ($is_dup) {
                                $skipped_count++;
                            } else {
                                $total_bills++;
                                $total_amount += floatval($al['monthly_rent']);
                            }
                        }
                        
                        $preview_results = [
                            'rent_month' => $rent_month,
                            'due_date' => $due_date,
                            'billing_scope' => $billing_scope,
                            'active_count' => count($active_allots),
                            'total_bills' => $total_bills,
                            'total_amount' => $total_amount,
                            'skipped_count' => $skipped_count
                        ];
                    } elseif (isset($_POST['confirm_generate'])) {
                        // Perform Database Transaction Billing Generation
                        $db->beginTransaction();
                        $generated_count = 0;
                        $skipped_count = 0;
                        
                        foreach ($active_allots as $al) {
                            // Manual check + database level duplicate protection
                            $dup_check = $db->prepare("SELECT COUNT(*) FROM chamber_rent_dues WHERE allotment_id = ? AND rent_month = ? FOR UPDATE");
                            $dup_check->execute([$al['id'], $rent_month]);
                            if ($dup_check->fetchColumn() > 0) {
                                $skipped_count++;
                                continue;
                            }
                            
                            $rent_amount = floatval($al['monthly_rent']);
                            
                            // Initialize Status
                            $status = 'pending';
                            if (date('Y-m-d') > $due_date) {
                                $status = 'overdue';
                            }
                            
                            // Insert Due record
                            $ins = $db->prepare("
                                INSERT INTO chamber_rent_dues 
                                (allotment_id, rent_month, due_date, rent_amount, payable_amount, outstanding_amount, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            $ins->execute([$al['id'], $rent_month, $due_date, $rent_amount, $rent_amount, $rent_amount, $status]);
                            $generated_count++;
                        }
                        
                        // Audit log
                        $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, remarks, performed_by) VALUES ('Rent Generated', ?, ?)");
                        $audit->execute(["माह $rent_month के लिए कक्ष किराया जनरेट किया गया। सफल: $generated_count, डुप्लिकेट छोड़े: $skipped_count", $_SESSION['user_id']]);
                        
                        $db->commit();
                        $success = "सफलतापूर्वक कक्ष किराया देय जनरेट किया गया। कुल सफल देय प्रविष्टियां: $generated_count | छोड़े गए डुप्लीकेट रिकॉर्ड: $skipped_count";
                    }
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'किराया जनरेशन प्रक्रिया विफल: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">मासिक कक्ष किराया बिलिंग जनरेशन (Rent Dues Generator)</h4>
    <a href="../index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-grid-fill me-1"></i>चैंबर रजिस्टर</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row g-4 font-hindi small text-navy-custom">
    <!-- Left Column: Generator Form -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-gear-fill text-gold-custom me-2"></i>मासिक किराया बिलिंग मानक (Billing Parameters)</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php insertCSRF(); ?>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">1. बिलिंग किराया महीना (Rent Month) <span class="text-danger">*</span></label>
                        <input type="month" name="rent_month" class="form-control form-control-sm" required value="<?php echo date('Y-m'); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">2. किराया भुगतान देय तिथि (Due Date) <span class="text-danger">*</span></label>
                        <input type="date" name="due_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-10', strtotime('next month')); ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">3. बिलिंग क्षेत्र दायरा (Scope)</label>
                        <select name="billing_scope" class="form-select form-select-sm">
                            <option value="all">सभी सक्रिय चैंबर आवंटन (All Active Allotments)</option>
                        </select>
                    </div>

                    <div class="text-end">
                        <button type="submit" name="preview" value="1" class="btn btn-navy btn-sm px-4"><i class="bi bi-eye-fill me-1"></i>पूर्वावलोकन देखें (Preview Billing)</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Generation Preview Results -->
    <div class="col-lg-6">
        <?php if ($preview_mode): ?>
            <div class="card border-0 shadow-sm border-start border-3 border-warning">
                <div class="card-header bg-light py-2">
                    <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-shield-check me-2"></i>किराया जनरेशन प्रीव्यू (Billing Summary)</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-4 fs-6">
                        <div class="col-7 text-secondary">किराया महीना:</div>
                        <div class="col-5 fw-bold english-text"><?php echo date('F Y', strtotime($preview_results['rent_month'] . '-01')); ?></div>
                        <div class="col-7 text-secondary">प्रस्तावित देय तिथि:</div>
                        <div class="col-5 fw-bold english-text"><?php echo date('d-m-Y', strtotime($preview_results['due_date'])); ?></div>
                        <div class="col-7 text-secondary">सक्रिय चैंबर आवंटन कतार:</div>
                        <div class="col-5 fw-bold english-text"><?php echo $preview_results['active_count']; ?></div>
                        <div class="col-7 text-secondary text-success">बिल जनरेट होने योग्य:</div>
                        <div class="col-5 fw-bold text-success english-text"><?php echo $preview_results['total_bills']; ?></div>
                        <div class="col-7 text-secondary text-danger">छोड़े जाने योग्य (डुप्लीकेट):</div>
                        <div class="col-5 fw-bold text-danger english-text"><?php echo $preview_results['skipped_count']; ?></div>
                        <div class="col-12 border-top my-2"></div>
                        <div class="col-7 text-secondary fw-bold">अनुमानित कुल किराया मांग:</div>
                        <div class="col-5 fw-bold text-danger fs-5 english-text">₹<?php echo number_format($preview_results['total_amount'], 2); ?></div>
                    </div>

                    <?php if ($preview_results['skipped_count'] > 0): ?>
                        <div class="alert alert-warning py-2 mb-3" style="font-size:0.7rem;">
                            <i class="bi bi-info-circle me-1"></i><strong>सुरक्षा चेतावनी:</strong> कतार में कुछ आवंटनों के लिए इस महीने का किराया पूर्व में ही जनरेट हो चुका है। डुप्लीकेट प्रोटेक्शन के अंतर्गत इन्हें छोड़ (Skip) दिया जाएगा।
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?php insertCSRF(); ?>
                        <input type="hidden" name="rent_month" value="<?php echo $preview_results['rent_month']; ?>">
                        <input type="hidden" name="due_date" value="<?php echo $preview_results['due_date']; ?>">
                        <input type="hidden" name="billing_scope" value="<?php echo $preview_results['billing_scope']; ?>">
                        
                        <button type="submit" name="confirm_generate" value="1" class="btn btn-success btn-sm w-100 py-2 fw-bold"><i class="bi bi-play-circle-fill me-1"></i>बिल जनरेट करें (Confirm & Generate)</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-5 bg-white border rounded shadow-sm text-muted">
                <i class="bi bi-play-circle fs-2 d-block mb-2 text-secondary"></i>
                पैरामीटर भरने के उपरांत बिलिंग गणना की समीक्षा हेतु "पूर्वावलोकन देखें" बटन पर क्लिक करें।
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../../includes/dashboard/footer.php';
?>
