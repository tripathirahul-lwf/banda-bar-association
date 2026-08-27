<?php
/**
 * President ID Card Monitoring & Approvals Panel
 * District Bar Association, Banda
 */

$pageTitle = 'पहचान पत्र अनुमोदन (ID Card Approvals)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce president role
requireRole('president');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$db = Database::getConnection();
$applications = [];
$total_records = 0;

$require_approval = defined('ID_CARD_REQUIRE_PRESIDENT_APPROVAL') ? ID_CARD_REQUIRE_PRESIDENT_APPROVAL : false;

if ($db) {
    try {
        $conditions = [];
        $params = [];
        
        // If approval is required, default to pending/under_review states for action
        if ($require_approval && empty($status_filter)) {
            $conditions[] = "a.status IN ('submitted', 'under_review')";
        } elseif (!empty($status_filter)) {
            $conditions[] = "a.status = ?";
            $params[] = $status_filter;
        }

        if (!empty($search)) {
            $conditions[] = "(m.full_name LIKE ? OR m.membership_no LIKE ? OR m.enrollment_no LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $where_clause = '';
        if (!empty($conditions)) {
            $where_clause = "WHERE " . implode(" AND ", $conditions);
        }

        // Count total
        $count_stmt = $db->prepare("
            SELECT COUNT(*) FROM id_card_applications a
            JOIN members m ON m.id = a.member_id
            $where_clause
        ");
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();

        // Fetch applications
        $select_stmt = $db->prepare("
            SELECT a.*, m.full_name, m.membership_no, m.enrollment_no, m.photo 
            FROM id_card_applications a
            JOIN members m ON m.id = a.member_id
            $where_clause
            ORDER BY a.id DESC
            LIMIT $limit OFFSET $offset
        ");
        $select_stmt->execute($params);
        $applications = $select_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("President card list loading error: " . $e->getMessage());
    }
}

$status_labels = [
    'submitted' => 'प्रस्तुत (Submitted)',
    'under_review' => 'समीक्षाधीन (Under Review)',
    'clarification_required' => 'स्पष्टीकरण अपेक्षित',
    'approved' => 'स्वीकृत (Approved)',
    'rejected' => 'अस्वीकृत',
    'generated' => 'जनरेटेड'
];

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) $total_pages = 1;
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">पहचान पत्र अनुमोदन पटल (Presidential approvals)</h4>
    <span class="badge <?php echo $require_approval ? 'bg-success' : 'bg-secondary'; ?>">
        <?php echo $require_approval ? 'अनुमोदन प्रणाली: सक्रिय' : 'अनुमोदन प्रणाली: निष्क्रिय (केवल अवलोकन)'; ?>
    </span>
</div>

<!-- Info Alert -->
<?php if (!$require_approval): ?>
    <div class="alert alert-info border-0 font-hindi shadow-xs mb-4">
        <i class="bi bi-info-circle-fill me-2"></i>
        अध्यक्षीय आईडी कार्ड अनुमोदन वर्तमान में अक्षम है। आप केवल बार सदस्यों के आवेदनों की सूची देख सकते हैं। प्रशासनिक अनुमोदन मुख्य रूप से महासचिव/एडमिन द्वारा किया जाता है।
    </div>
<?php endif; ?>

<!-- Search Form -->
<div class="card p-3 mb-4 border-0 shadow-sm font-hindi small">
    <form method="GET" action="index.php" class="row g-3">
        <div class="col-md-5">
            <input type="text" class="form-control form-control-sm" name="search" placeholder="नाम, सदस्य संख्या से खोजें..." value="<?php echo e($search); ?>">
        </div>
        <div class="col-md-4">
            <select class="form-select form-select-sm" name="status">
                <option value="">सभी आवेदन स्थिति (All Status)</option>
                <?php foreach ($status_labels as $val => $lbl): ?>
                    <option value="<?php echo $val; ?>" <?php echo ($status_filter === $val) ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-grid">
            <button type="submit" class="btn btn-navy btn-sm fw-semibold">खोजें / फिल्टर</button>
        </div>
    </form>
</div>

<!-- Table -->
<div class="table-responsive bg-white rounded shadow-sm border font-hindi small">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th class="py-2">आईडी</th>
                <th>अधिवक्ता का नाम</th>
                <th>सदस्यता संख्या</th>
                <th>पंजीकरण संख्या</th>
                <th>आवेदन प्रकार</th>
                <th>स्थिति (Status)</th>
                <th class="text-end">कार्य</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($applications)): ?>
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">कोई आवेदन रिकॉर्ड नहीं मिला।</td>
                </tr>
            <?php else: ?>
                <?php foreach ($applications as $a): ?>
                    <tr>
                        <td class="py-2 english-text">#<?php echo $a['id']; ?></td>
                        <td>
                            <strong class="text-navy-custom d-block english-text"><?php echo e($a['full_name']); ?></strong>
                        </td>
                        <td class="english-text"><?php echo e($a['membership_no']); ?></td>
                        <td class="english-text"><?php echo e($a['enrollment_no']); ?></td>
                        <td>
                            <span class="badge bg-light text-navy-custom border"><?php echo e(ucfirst($a['application_type'])); ?></span>
                        </td>
                        <td>
                            <?php 
                            $st = $a['status'];
                            $b = ($st === 'submitted') ? 'bg-info' : (($st === 'approved') ? 'bg-success' : (($st === 'rejected') ? 'bg-danger' : 'bg-warning text-dark'));
                            ?>
                            <span class="badge <?php echo $b; ?> font-size-xs px-2 py-1">
                                <?php echo e($status_labels[$st] ?? $st); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="view.php?id=<?php echo $a['id']; ?>" class="btn btn-xs btn-outline-navy fw-semibold">
                                <i class="bi bi-eye-fill me-1"></i><?php echo $require_approval ? 'अनुमोदन समीक्षा' : 'विवरण देखें'; ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <nav class="mt-4 font-hindi small">
        <ul class="pagination pagination-sm justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="index.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">पिछला</a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="index.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="index.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">अगला</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
