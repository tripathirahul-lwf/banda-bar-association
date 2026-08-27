<?php
/**
 * Advocate Bar Fee Members List
 * District Bar Association, Banda
 */

$pageTitle = 'अधिवक्ता सदस्य शुल्क सूची (Advocate Bar Fee Members)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

// Search and Filter variables
$search = sanitize(trim($_GET['search'] ?? ''));
$status = sanitize(trim($_GET['status'] ?? ''));
$fee_type = intval($_GET['fee_type'] ?? 0);
$financial_year = sanitize(trim($_GET['financial_year'] ?? ''));

// Pagination variables
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$fee_types = [];
$financial_years = [];
$members = [];
$total_records = 0;

if ($db) {
    try {
        // Fetch fee types
        $fee_types = $db->query("SELECT id, name FROM bar_fee_types WHERE status = 'active'")->fetchAll();
        // Fetch financial years
        $financial_years = $db->query("SELECT id, name FROM financial_years ORDER BY start_date DESC")->fetchAll();

        // Build base query
        $where_clauses = ["m.membership_status IN ('active', 'inactive')"];
        $params = [];

        if (!empty($search)) {
            $where_clauses[] = "(m.full_name LIKE :search OR m.membership_no LIKE :search OR m.enrollment_no LIKE :search OR m.mobile LIKE :search)";
            $params[':search'] = "%$search%";
        }

        // Subquery mapping of status / fee filter
        $due_filters = [];
        $due_params = [];
        if (!empty($status)) {
            $due_filters[] = "d.status = :status";
            $due_params[':status'] = $status;
        }
        if ($fee_type > 0) {
            $due_filters[] = "d.fee_type_id = :fee_type";
            $due_params[':fee_type'] = $fee_type;
        }
        if (!empty($financial_year)) {
            $due_filters[] = "d.financial_year = :fy";
            $due_params[':fy'] = $financial_year;
        }

        // Build final query to get members and aggregated dues
        $where_sql = implode(" AND ", $where_clauses);
        
        $count_query = "SELECT COUNT(*) FROM members m WHERE $where_sql";
        $count_stmt = $db->prepare($count_query);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();

        // Query member parameters with summed dues
        $query = "
            SELECT 
                m.id, m.full_name, m.membership_no, m.enrollment_no, m.membership_status,
                (SELECT SUM(payable_amount) FROM member_fee_dues WHERE member_id = m.id AND status != 'cancelled') as total_payable,
                (SELECT SUM(paid_amount) FROM member_fee_dues WHERE member_id = m.id AND status != 'cancelled') as total_paid,
                (SELECT SUM(outstanding_amount) FROM member_fee_dues WHERE member_id = m.id AND status != 'cancelled') as total_outstanding,
                (SELECT MAX(payment_date) FROM bar_fee_payments WHERE member_id = m.id AND status = 'confirmed') as last_payment_date
            FROM members m
            WHERE $where_sql
            ORDER BY m.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $db->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $members = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Failed to load fee members list: " . $e->getMessage());
    }
}

$total_pages = ceil($total_records / $limit);
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">अधिवक्ता सदस्य शुल्क सूची (Members Fee Register)</h4>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-speedometer2 me-1"></i>डैशबोर्ड</a>
        <a href="create-due.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-plus-circle me-1"></i>नया शुल्क</a>
        <a href="bulk-assign.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-collection-fill me-1"></i>थोक शुल्क</a>
        <a href="payments/create.php" class="btn btn-xs btn-success fw-semibold"><i class="bi bi-cash-stack me-1"></i>भुगतान प्राप्त करें</a>
    </div>
</div>

<!-- Search & Filters Card -->
<div class="card border-0 bg-light-custom p-3 font-hindi small mb-4">
    <form method="GET" action="members.php" class="row g-2">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="नाम, सदस्यता या नामांकन सं., मोबाइल" value="<?php echo e($search); ?>">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">-- बकाया स्थिति --</option>
                <option value="pending" <?php echo ($status === 'pending') ? 'selected' : ''; ?>>Pending (लंबित)</option>
                <option value="partially_paid" <?php echo ($status === 'partially_paid') ? 'selected' : ''; ?>>Partially Paid (आंशिक)</option>
                <option value="paid" <?php echo ($status === 'paid') ? 'selected' : ''; ?>>Paid (पूर्ण भुगतान)</option>
                <option value="overdue" <?php echo ($status === 'overdue') ? 'selected' : ''; ?>>Overdue (अवधिपार)</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="fee_type" class="form-select form-select-sm">
                <option value="">-- शुल्क का प्रकार --</option>
                <?php foreach ($fee_types as $ft): ?>
                    <option value="<?php echo $ft['id']; ?>" <?php echo ($fee_type === intval($ft['id'])) ? 'selected' : ''; ?>><?php echo e($ft['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="financial_year" class="form-select form-select-sm">
                <option value="">-- वित्तीय वर्ष --</option>
                <?php foreach ($financial_years as $fy): ?>
                    <option value="<?php echo e($fy['name']); ?>" <?php echo ($financial_year === $fy['name']) ? 'selected' : ''; ?>><?php echo e($fy['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-1">
            <button type="submit" class="btn btn-navy btn-sm flex-fill font-hindi"><i class="bi bi-search me-1"></i>खोजें</button>
            <a href="members.php" class="btn btn-secondary btn-sm font-hindi">रीसेट</a>
        </div>
    </form>
</div>

<!-- Members Fee Table -->
<div class="card border-0 shadow-sm font-hindi small">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2">अधिवक्ता (Advocate)</th>
                        <th>सदस्यता सं. (Membership)</th>
                        <th>नामांकन सं. (Enrollment)</th>
                        <th class="text-end">कुल देय (Due)</th>
                        <th class="text-end">कुल भुगतान (Paid)</th>
                        <th class="text-end">शेष बकाया (Outstanding)</th>
                        <th>अंतिम भुगतान</th>
                        <th>शुल्क स्थिति</th>
                        <th class="text-end">कार्यवाही (Actions)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">कोई सदस्य शुल्क रिकॉर्ड उपलब्ध नहीं है।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $m): ?>
                            <?php 
                            $payable = floatval($m['total_payable'] ?: 0.00);
                            $paid = floatval($m['total_paid'] ?: 0.00);
                            $outstanding = floatval($m['total_outstanding'] ?: 0.00);
                            
                            $status_badge = '<span class="badge bg-success">Paid</span>';
                            if ($outstanding > 0) {
                                if ($paid > 0) {
                                    $status_badge = '<span class="badge bg-warning text-dark">Partially Paid</span>';
                                } else {
                                    $status_badge = '<span class="badge bg-danger">Pending</span>';
                                }
                            } elseif ($payable <= 0) {
                                $status_badge = '<span class="badge bg-secondary">No Due</span>';
                            }
                            ?>
                            <tr>
                                <td class="py-2">
                                    <strong class="text-navy-custom"><?php echo e($m['full_name']); ?></strong>
                                </td>
                                <td class="english-text fw-bold"><?php echo e($m['membership_no']); ?></td>
                                <td class="english-text"><?php echo e($m['enrollment_no']); ?></td>
                                <td class="text-end fw-semibold text-secondary-custom">₹<?php echo number_format($payable, 2); ?></td>
                                <td class="text-end fw-semibold text-success">₹<?php echo number_format($paid, 2); ?></td>
                                <td class="text-end fw-bold text-danger">₹<?php echo number_format($outstanding, 2); ?></td>
                                <td class="english-text"><?php echo $m['last_payment_date'] ? date('d-m-Y', strtotime($m['last_payment_date'])) : '<span class="text-secondary">-</span>'; ?></td>
                                <td><?php echo $status_badge; ?></td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-xs btn-outline-navy dropdown-toggle font-hindi py-0.5" type="button" data-bs-toggle="dropdown">
                                            एक्शन
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end font-hindi small">
                                            <li><a class="dropdown-item" href="member-ledger.php?member_id=<?php echo $m['id']; ?>"><i class="bi bi-file-earmark-spreadsheet me-2 text-navy-custom"></i>बहीखाता (Ledger)</a></li>
                                            <li><a class="dropdown-item" href="payments/create.php?member_id=<?php echo $m['id']; ?>"><i class="bi bi-cash-stack me-2 text-success"></i>भुगतान दर्ज करें</a></li>
                                            <li><a class="dropdown-item" href="create-due.php?member_id=<?php echo $m['id']; ?>"><i class="bi bi-plus-circle me-2 text-primary"></i>नया देय जोड़ें</a></li>
                                            <li><a class="dropdown-item" href="statement.php?member_id=<?php echo $m['id']; ?>" target="_blank"><i class="bi bi-printer me-2 text-secondary"></i>स्टेटमेंट प्रिंट</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <nav class="d-flex justify-content-center mt-4">
        <ul class="pagination pagination-sm font-hindi">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&fee_type=<?php echo $fee_type; ?>&financial_year=<?php echo urlencode($financial_year); ?>&page=<?php echo ($page - 1); ?>">पूर्व</a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&fee_type=<?php echo $fee_type; ?>&financial_year=<?php echo urlencode($financial_year); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&fee_type=<?php echo $fee_type; ?>&financial_year=<?php echo urlencode($financial_year); ?>&page=<?php echo ($page + 1); ?>">अगला</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<div class="mb-5"></div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
