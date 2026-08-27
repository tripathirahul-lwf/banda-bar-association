<?php
/**
 * Association Fund Transactions Register
 * District Bar Association, Banda
 */

$pageTitle = 'लेन-देन रजिस्टर (Transactions Register)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

// Handle cancellation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect('transactions.php');
    }

    $cancel_id = intval($_POST['cancel_id']);
    $reason = trim($_POST['reason'] ?? '');
    $user_id = $_SESSION['auth']['user_id'];

    if ($cancel_id > 0 && !empty($reason)) {
        try {
            $db->beginTransaction();

            // Fetch old transaction status
            $chk_stmt = $db->prepare("SELECT status, amount, transaction_no, financial_year_id FROM association_fund_transactions WHERE id = ?");
            $chk_stmt->execute([$cancel_id]);
            $tx = $chk_stmt->fetch();

            if ($tx) {
                // Update transaction status to cancelled
                $up = $db->prepare("
                    UPDATE association_fund_transactions 
                    SET status = 'cancelled', cancelled_by = ?, cancelled_at = NOW(), cancellation_reason = ? 
                    WHERE id = ?
                ");
                $up->execute([$user_id, $reason, $cancel_id]);

                // Log fund history
                $hist = $db->prepare("
                    INSERT INTO association_fund_history (transaction_id, financial_year_id, action, old_status, new_status, remarks, performed_by) 
                    VALUES (?, ?, 'Transaction Cancelled', ?, 'cancelled', ?, ?)
                ");
                $hist->execute([$cancel_id, $tx['financial_year_id'], $tx['status'], "Cancelled reason: $reason", $user_id]);

                // Log audit trail
                $audit = $db->prepare("
                    INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                    VALUES (0, 'Fund Transaction Cancelled', 'status', ?, 'cancelled', ?, ?)
                ");
                $audit->execute([$tx['status'], "Fund Transaction Number " . $tx['transaction_no'] . " cancelled", $user_id]);

                $db->commit();
                setFlash('success', 'लेन-देन सफलतापूर्वक निरस्त (Cancelled) कर दिया गया।');
            } else {
                setFlash('danger', 'लेन-देन विवरण नहीं मिला।');
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed to cancel fund transaction: " . $e->getMessage());
            setFlash('danger', 'डेटाबेस त्रुटि: लेन-देन निरस्त करने में असमर्थ।');
        }
        redirect('transactions.php');
    }
}

// Filters loading
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_fy = isset($_GET['fy_id']) ? intval($_GET['fy_id']) : 0;
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$mode_filter = isset($_GET['payment_mode']) ? trim($_GET['payment_mode']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$financial_years = [];
$categories = [];
$transactions = [];
$total_records = 0;

if ($db) {
    try {
        $financial_years = $db->query("SELECT id, name, status FROM financial_years ORDER BY start_date DESC")->fetchAll();
        $categories = $db->query("SELECT id, name, type FROM association_fund_categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();

        // Resolve active year if not selected
        if ($selected_fy <= 0) {
            foreach ($financial_years as $fy) {
                if ($fy['status'] === 'active') {
                    $selected_fy = $fy['id'];
                }
            }
        }

        $conditions = ["t.financial_year_id = ?"];
        $params = [$selected_fy];

        if (!empty($search)) {
            $conditions[] = "(t.transaction_no LIKE ? OR t.receipt_no LIKE ? OR t.voucher_no LIKE ? OR t.party_name LIKE ? OR t.reference_no LIKE ? OR t.description LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }

        if (!empty($type_filter)) {
            $conditions[] = "t.transaction_type = ?";
            $params[] = $type_filter;
        }

        if ($category_filter > 0) {
            $conditions[] = "t.category_id = ?";
            $params[] = $category_filter;
        }

        if (!empty($mode_filter)) {
            $conditions[] = "t.payment_mode = ?";
            $params[] = $mode_filter;
        }

        if (!empty($status_filter)) {
            $conditions[] = "t.status = ?";
            $params[] = $status_filter;
        }

        $where = implode(" AND ", $conditions);

        // Count total records
        $cnt_stmt = $db->prepare("SELECT COUNT(*) FROM association_fund_transactions t WHERE $where");
        $cnt_stmt->execute($params);
        $total_records = $cnt_stmt->fetchColumn();

        // Fetch records
        $stmt = $db->prepare("
            SELECT t.*, c.name as category_name, u.username as creator_name 
            FROM association_fund_transactions t
            LEFT JOIN association_fund_categories c ON c.id = t.category_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE $where
            ORDER BY t.transaction_date DESC, t.id DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $transactions = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Failed to load transactions: " . $e->getMessage());
    }
}

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) $total_pages = 1;

$status_badges = [
    'draft' => 'bg-secondary',
    'pending_approval' => 'bg-warning text-dark',
    'approved' => 'bg-success',
    'cancelled' => 'bg-danger',
    'rejected' => 'bg-danger'
];

$payment_modes = ['Cash', 'UPI', 'Bank Transfer', 'Cheque', 'NEFT / RTGS', 'Other'];
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">लेन-देन रजिस्टर (Transactions Register)</h4>
    <div class="d-flex gap-2">
        <a href="export.php?fy_id=<?php echo $selected_fy; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-file-earmark-excel-fill me-1"></i>एक्सपोर्ट CSV</a>
        <a href="index.php" class="btn btn-xs btn-navy fw-semibold">डैशबोर्ड पर जाएं</a>
    </div>
</div>

<!-- Filters Panel -->
<div class="card p-3 mb-4 border-0 shadow-sm font-hindi small">
    <form method="GET" action="transactions.php" class="row g-3">
        <input type="hidden" name="fy_id" value="<?php echo $selected_fy; ?>">
        
        <div class="col-md-3">
            <label class="form-label text-secondary fw-semibold">खोज (Search)</label>
            <input type="text" class="form-control form-control-sm" name="search" placeholder="लेन-देन सं., प्राप्तकर्ता, विवरण..." value="<?php echo e($search); ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label text-secondary fw-semibold">प्रकार (Type)</label>
            <select class="form-select form-select-sm" name="type">
                <option value="">सभी प्रकार</option>
                <option value="income" <?php echo ($type_filter === 'income') ? 'selected' : ''; ?>>आय (Income)</option>
                <option value="expense" <?php echo ($type_filter === 'expense') ? 'selected' : ''; ?>>व्यय (Expense)</option>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label text-secondary fw-semibold">श्रेणी (Category)</label>
            <select class="form-select form-select-sm" name="category">
                <option value="0">सभी श्रेणियां</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo ($category_filter == $cat['id']) ? 'selected' : ''; ?>>
                        <?php echo e($cat['name']); ?> (<?php echo $cat['type']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label text-secondary fw-semibold">भुगतान माध्यम</label>
            <select class="form-select form-select-sm" name="payment_mode">
                <option value="">सभी माध्यम</option>
                <?php foreach ($payment_modes as $pm): ?>
                    <option value="<?php echo $pm; ?>" <?php echo ($mode_filter === $pm) ? 'selected' : ''; ?>><?php echo $pm; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label text-secondary fw-semibold">स्थिति (Status)</label>
            <select class="form-select form-select-sm" name="status">
                <option value="">सभी स्थिति</option>
                <option value="draft" <?php echo ($status_filter === 'draft') ? 'selected' : ''; ?>>ड्राफ्ट</option>
                <option value="pending_approval" <?php echo ($status_filter === 'pending_approval') ? 'selected' : ''; ?>>लंबित समीक्षा</option>
                <option value="approved" <?php echo ($status_filter === 'approved') ? 'selected' : ''; ?>>स्वीकृत</option>
                <option value="cancelled" <?php echo ($status_filter === 'cancelled') ? 'selected' : ''; ?>>निरस्त</option>
            </select>
        </div>

        <div class="col-12 text-end mt-2">
            <a href="transactions.php?fy_id=<?php echo $selected_fy; ?>" class="btn btn-outline-secondary btn-sm px-3 me-2">रीसेट करें</a>
            <button type="submit" class="btn btn-navy btn-sm px-4 fw-semibold">लागू करें (Filter)</button>
        </div>
    </form>
</div>

<!-- Transactions Table List -->
<div class="table-responsive bg-white rounded shadow-sm border font-hindi small mb-4">
    <table class="table table-hover align-middle mb-0 text-muted">
        <thead class="table-light">
            <tr>
                <th class="py-2">लेनदेन सं.</th>
                <th>तिथि</th>
                <th>प्रकार</th>
                <th>श्रेणी</th>
                <th>प्राप्तकर्ता/भुगतानकर्ता</th>
                <th>माध्यम</th>
                <th>रसीद/वाउचर</th>
                <th>राशि (Amount)</th>
                <th>स्थिति (Status)</th>
                <th class="text-end">कार्य</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="10" class="text-center py-4">कोई लेन-देन रिकॉर्ड नहीं मिला।</td>
                </tr>
            <?php else: ?>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td class="py-2 english-text fw-bold"><?php echo e($t['transaction_no']); ?></td>
                        <td class="english-text"><?php echo date('d-m-Y', strtotime($t['transaction_date'])); ?></td>
                        <td>
                            <span class="badge <?php echo ($t['transaction_type'] === 'income') ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25'; ?> font-size-xs px-2 py-0.5">
                                <?php echo e($t['transaction_type']); ?>
                            </span>
                        </td>
                        <td><?php echo e($t['category_name']); ?></td>
                        <td><?php echo e($t['party_name'] ?: 'N/A'); ?></td>
                        <td><?php echo e($t['payment_mode']); ?></td>
                        <td class="english-text"><?php echo e($t['transaction_type'] === 'income' ? ($t['receipt_no'] ?: '-') : ($t['voucher_no'] ?: '-')); ?></td>
                        <td class="text-end fw-bold <?php echo ($t['transaction_type'] === 'income') ? 'text-success' : 'text-danger'; ?>">
                            ₹<?php echo number_format($t['amount'], 2); ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $status_badges[$t['status']] ?? 'bg-secondary'; ?> font-size-xs">
                                <?php echo e($t['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-xs btn-outline-navy dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown">
                                    विकल्प
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm font-hindi small">
                                    <li><a class="dropdown-item" href="view.php?id=<?php echo $t['id']; ?>"><i class="bi bi-eye me-2 text-navy-custom"></i>विवरण देखें</a></li>
                                    
                                    <?php if ($t['transaction_type'] === 'income' && $t['status'] === 'approved'): ?>
                                        <li><a class="dropdown-item" href="receipt.php?id=<?php echo $t['id']; ?>" target="_blank"><i class="bi bi-printer me-2 text-success"></i>रसीद प्रिंट (Receipt)</a></li>
                                    <?php elseif ($t['transaction_type'] === 'expense' && $t['status'] === 'approved'): ?>
                                        <li><a class="dropdown-item" href="voucher.php?id=<?php echo $t['id']; ?>" target="_blank"><i class="bi bi-printer me-2 text-danger"></i>वाउचर प्रिंट (Voucher)</a></li>
                                    <?php endif; ?>

                                    <?php if ($t['status'] === 'approved'): ?>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" onclick="var r = prompt('निरस्तीकरण का कारण (Cancellation Reason) दर्ज करें:'); if(r){ var f = document.createElement('form'); f.method='POST'; f.action='transactions.php'; var c = document.createElement('input'); c.type='hidden'; c.name='csrf_token'; c.value='<?php echo $_SESSION['csrf_token']; ?>'; f.appendChild(c); var i = document.createElement('input'); i.type='hidden'; i.name='cancel_id'; i.value='<?php echo $t['id']; ?>'; f.appendChild(i); var rm = document.createElement('input'); rm.type='hidden'; rm.name='reason'; rm.value=r; f.appendChild(rm); document.body.appendChild(f); f.submit(); } return false;"><i class="bi bi-x-circle me-2"></i>निरस्त करें (Cancel)</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination navigation -->
<?php if ($total_pages > 1): ?>
    <nav class="font-hindi small mb-5">
        <ul class="pagination pagination-sm justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="transactions.php?page=<?php echo $page - 1; ?>&fy_id=<?php echo $selected_fy; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&payment_mode=<?php echo urlencode($mode_filter); ?>&status=<?php echo $status_filter; ?>">पिछला</a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="transactions.php?page=<?php echo $i; ?>&fy_id=<?php echo $selected_fy; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&payment_mode=<?php echo urlencode($mode_filter); ?>&status=<?php echo $status_filter; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="transactions.php?page=<?php echo $page + 1; ?>&fy_id=<?php echo $selected_fy; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&payment_mode=<?php echo urlencode($mode_filter); ?>&status=<?php echo $status_filter; ?>">अगला</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
