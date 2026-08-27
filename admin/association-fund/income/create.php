<?php
/**
 * Create Income Transaction
 * District Bar Association, Banda
 */

$pageTitle = 'आय लेन-देन दर्ज करें (Record Income Transaction)';
require_once __DIR__ . '/../../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

$errors = [];
$success = false;

// Fetch active financial year
$active_fy = null;
if ($db) {
    try {
        $active_fy = $db->query("SELECT * FROM financial_years WHERE status = 'active' LIMIT 1")->fetch();
    } catch (PDOException $e) {
        error_log("Failed to fetch active FY: " . $e->getMessage());
    }
}

// Fetch income categories
$categories = [];
if ($db) {
    try {
        $categories = $db->query("SELECT * FROM association_fund_categories WHERE type = 'income' AND status = 'active' ORDER BY name ASC")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch categories: " . $e->getMessage());
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'सुरक्षा टोकन अमान्य है।';
    }

    if (!$active_fy) {
        $errors[] = 'वर्तमान में कोई सक्रिय वित्तीय वर्ष नहीं है। कृपया पहले एक वर्ष सक्रिय करें।';
    }

    $transaction_date = trim($_POST['transaction_date'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0.00);
    $payment_mode = trim($_POST['payment_mode'] ?? '');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $party_name = trim($_POST['party_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $receipt_no = trim($_POST['receipt_no'] ?? '');
    $action_type = trim($_POST['action_type'] ?? 'submit'); // draft, submit, approve

    // Validation
    if (empty($transaction_date)) {
        $errors[] = 'लेन-देन तिथि दर्ज करना आवश्यक है।';
    } else {
        // Ensure date falls within financial year
        $tx_time = strtotime($transaction_date);
        $start_time = strtotime($active_fy['start_date']);
        $end_time = strtotime($active_fy['end_date']);
        if ($tx_time < $start_time || $tx_time > $end_time) {
            $errors[] = 'तिथि वर्तमान सक्रिय वित्तीय वर्ष (' . e($active_fy['name']) . ') की सीमा में होनी चाहिए।';
        }
    }

    if ($category_id <= 0) {
        $errors[] = 'आय की श्रेणी चुनना आवश्यक है।';
    }

    if ($amount <= 0) {
        $errors[] = 'राशि शून्य से अधिक होनी चाहिए।';
    }

    if (empty($payment_mode)) {
        $errors[] = 'भुगतान का माध्यम चुनना आवश्यक है।';
    }

    if (empty($description)) {
        $errors[] = 'लेन-देन का विवरण लिखना आवश्यक है।';
    }

    // Attachment processing
    $attachment_filename = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file['type'], $allowed_types) || !in_array($ext, $allowed_exts)) {
            $errors[] = 'केवल PDF, JPG, JPEG, या PNG दस्तावेज़ ही अपलोड किए जा सकते हैं।';
        }

        if ($file['size'] > FINANCIAL_ATTACHMENT_MAX_SIZE) {
            $errors[] = 'फ़ाइल का आकार 5MB से कम होना चाहिए।';
        }

        if (empty($errors)) {
            // Generate clean unique filename
            $attachment_filename = 'inc_' . uniqid() . '_' . time() . '.' . $ext;
            $upload_path = __DIR__ . '/../../../storage/financials/' . $attachment_filename;
            
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                $errors[] = 'दस्तावेज़ सहेजने में विफल। कृपया पुन: प्रयास करें।';
                $attachment_filename = null;
            }
        }
    }

    if (empty($errors) && $db) {
        try {
            $db->beginTransaction();

            $user_id = $_SESSION['auth']['user_id'];
            $role = $_SESSION['auth']['role'];

            // Generate unique transaction number
            $transaction_no = generateFundTransactionNumber($db);

            // Determine status
            $status = 'pending_approval';
            if ($action_type === 'draft') {
                $status = 'draft';
            } elseif ($action_type === 'approve' && ($role === 'admin' || !ASSOCIATION_FUND_REQUIRE_APPROVAL)) {
                $status = 'approved';
            }

            // Auto-generate receipt number if blank and approved
            if (empty($receipt_no) && $status === 'approved') {
                $receipt_no = generateReceiptOrVoucherNo($db, 'income');
            }

            $stmt = $db->prepare("
                INSERT INTO association_fund_transactions (
                    financial_year_id, transaction_no, transaction_date, transaction_type, 
                    category_id, amount, payment_mode, reference_no, party_name, 
                    description, receipt_no, attachment_path, status, created_by, 
                    approved_by, approved_at
                ) VALUES (?, ?, ?, 'income', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $approved_by = ($status === 'approved') ? $user_id : null;
            $approved_at = ($status === 'approved') ? date('Y-m-d H:i:s') : null;

            $stmt->execute([
                $active_fy['id'],
                $transaction_no,
                $transaction_date,
                $category_id,
                $amount,
                $payment_mode,
                $reference_no ?: null,
                $party_name ?: null,
                $description,
                $receipt_no ?: null,
                $attachment_filename,
                $status,
                $user_id,
                $approved_by,
                $approved_at
            ]);

            $tx_id = $db->lastInsertId();

            // Record History
            $hist = $db->prepare("
                INSERT INTO association_fund_history (transaction_id, financial_year_id, action, old_status, new_status, remarks, performed_by) 
                VALUES (?, ?, ?, NULL, ?, ?, ?)
            ");
            $action_label = ($status === 'draft') ? 'Draft Saved' : (($status === 'approved') ? 'Created & Approved Directly' : 'Submitted for Approval');
            $remarks = ($status === 'approved') ? 'Directly approved by creator' : 'Sent to approvals queue';
            $hist->execute([$tx_id, $active_fy['id'], $action_label, $status, $remarks, $user_id]);

            // Auditing Log
            $audit = $db->prepare("
                INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                VALUES (0, 'Fund Transaction Created', 'status', NULL, ?, ?, ?)
            ");
            $audit->execute([$status, "Fund Transaction $transaction_no ($amount) recorded as $status", $user_id]);

            $db->commit();
            setFlash('success', 'आय प्रविष्टि सफलतापूर्वक दर्ज कर दी गई है। (' . $transaction_no . ')');
            redirect('../transactions.php');

        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed to create income transaction: " . $e->getMessage());
            $errors[] = 'डेटाबेस प्रविष्टि विफल: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">आय प्रविष्टि दर्ज करें (Add Income)</h4>
    <a href="../index.php" class="btn btn-xs btn-navy fw-semibold">वापस कोष मुख्य पृष्ठ</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger font-hindi small pb-0">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card p-4 border-0 shadow-sm font-hindi small mb-5">
    <form method="POST" action="create.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <div class="row g-3">
            <div class="col-md-6 col-12">
                <label class="form-label text-secondary fw-semibold">लेन-देन की तारीख (Transaction Date) <span class="text-danger">*</span></label>
                <input type="date" class="form-control form-control-sm" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="col-md-6 col-12">
                <label class="form-label text-secondary fw-semibold">आय श्रेणी (Income Category) <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="category_id" required>
                    <option value="">श्रेणी चुनें...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 col-12">
                <label class="form-label text-secondary fw-semibold">कुल राशि (Amount in ₹) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0.01" class="form-control form-control-sm text-end" name="amount" placeholder="0.00" required>
            </div>

            <div class="col-md-6 col-12">
                <label class="form-label text-secondary fw-semibold">भुगतान माध्यम (Payment Mode) <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="payment_mode" required>
                    <option value="">माध्यम चुनें...</option>
                    <option value="Cash">Cash (नकद)</option>
                    <option value="UPI">UPI (GPay/PhonePe/Paytm)</option>
                    <option value="Bank Transfer">Bank Transfer (खाता स्थानांतरण)</option>
                    <option value="Cheque">Cheque (चेक)</option>
                    <option value="NEFT / RTGS">NEFT / RTGS</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="col-md-6 col-12">
                <label class="form-label text-secondary fw-semibold">स्रोत / भुगतानकर्ता का नाम (Source / Party Name)</label>
                <input type="text" class="form-control form-control-sm" name="party_name" placeholder="उदा. स्वैच्छिक योगदानकर्ता का नाम">
            </div>

            <div class="col-md-6 col-12">
                <label class="form-label text-secondary fw-semibold">संदर्भ / यूटीआर / चेक नंबर (Reference / UTR / Cheque No.)</label>
                <input type="text" class="form-control form-control-sm" name="reference_no" placeholder="उदा. TXN1234567890">
            </div>

            <div class="col-md-6 col-12">
                <label class="form-label text-secondary fw-semibold">मैनुअल रसीद संख्या (Receipt No.) <small class="text-muted">(यदि भौतिक बुक का प्रयोग हो)</small></label>
                <input type="text" class="form-control form-control-sm" name="receipt_no" placeholder="उदा. REC-2026/001">
            </div>

            <div class="col-md-6 col-12">
                <label class="form-label text-secondary fw-semibold">प्राप्ति प्रमाण दस्तावेज (Attachment Scan) <small class="text-muted">(PDF, JPG, PNG - Max 5MB)</small></label>
                <input type="file" class="form-control form-control-sm" name="attachment">
            </div>

            <div class="col-12">
                <label class="form-label text-secondary fw-semibold">लेन-देन का विवरण (Description / Remarks) <span class="text-danger">*</span></label>
                <textarea class="form-control form-control-sm" name="description" rows="3" placeholder="उदा. पुस्तकालय निधि विकास हेतु विशेष सहयोग राशि" required></textarea>
            </div>

            <div class="col-12 text-end border-top pt-3 mt-4">
                <button type="submit" name="action_type" value="draft" class="btn btn-outline-secondary btn-sm px-3 me-2">प्रारूप में सहेजें (Save Draft)</button>
                <button type="submit" name="action_type" value="submit" class="btn btn-warning btn-sm px-3 me-2">अनुमोदन हेतु भेजें (Submit for Approval)</button>
                <?php if ($_SESSION['auth']['role'] === 'admin'): ?>
                    <button type="submit" name="action_type" value="approve" class="btn btn-navy btn-sm px-4 fw-semibold">सीधे स्वीकृत करें (Approve Directly)</button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php 
require_once __DIR__ . '/../../../includes/dashboard/footer.php';
?>
