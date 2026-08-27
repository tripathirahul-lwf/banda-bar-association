<?php
/**
 * Admin/Mahasachiv ID Card Applications List
 * District Bar Association, Banda
 */

$pageTitle = 'पहचान पत्र प्रबंधन (ID Card Management)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin/mahasachiv role
requireRole(['admin', 'mahasachiv']);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$db = Database::getConnection();
$applications = [];
$total_records = 0;

if ($db) {
    try {
        // Build query conditions
        $conditions = [];
        $params = [];
        
        if (!empty($search)) {
            $conditions[] = "(m.full_name LIKE ? OR m.membership_no LIKE ? OR m.enrollment_no LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($status_filter)) {
            $conditions[] = "a.status = ?";
            $params[] = $status_filter;
        }
        
        if (!empty($type_filter)) {
            $conditions[] = "a.application_type = ?";
            $params[] = $type_filter;
        }
        
        $where_clause = '';
        if (!empty($conditions)) {
            $where_clause = "WHERE " . implode(" AND ", $conditions);
        }
        
        // Count total records
        $count_query = "
            SELECT COUNT(*) FROM id_card_applications a
            JOIN members m ON m.id = a.member_id
            $where_clause
        ";
        $c_stmt = $db->prepare($count_query);
        $c_stmt->execute($params);
        $total_records = $c_stmt->fetchColumn();
        
        // Fetch records
        $select_query = "
            SELECT a.*, m.full_name, m.membership_no, m.enrollment_no, m.membership_status 
            FROM id_card_applications a
            JOIN members m ON m.id = a.member_id
            $where_clause
            ORDER BY a.id DESC
            LIMIT $limit OFFSET $offset
        ";
        $s_stmt = $db->prepare($select_query);
        $s_stmt->execute($params);
        $applications = $s_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load card applications: " . $e->getMessage());
        setFlash('danger', 'डेटाबेस लोड विफलता।');
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
    <h4 class="text-navy-custom fw-bold mb-0"><i class="fa-solid fa-id-card text-gold-dark me-2"></i>पहचान पत्र आवेदन कतार (ID Card Applications)</h4>
    <div class="d-flex gap-2">
        <a href="print-register.php" target="_blank" class="btn btn-sm btn-outline-secondary fw-semibold"><i class="fa-solid fa-print me-1"></i>रजिस्टर प्रिंट</a>
        <a href="export.php?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>" class="btn btn-sm btn-outline-success fw-semibold"><i class="fa-solid fa-file-excel me-1"></i>एक्सपोर्ट (CSV)</a>
    </div>
</div>

<!-- Filters -->
<div class="card p-3 mb-4 border-0 shadow-sm bg-light-custom font-hindi small" style="border-radius: 8px;">
    <form method="GET" action="index.php" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass" style="font-size: 0.8rem;"></i></span>
                <input type="text" class="form-control border-start-0 ps-1" name="search" placeholder="खोजें: नाम, सदस्य संख्या, पंजीकरण..." value="<?php echo e($search); ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select class="form-select form-select-sm" name="status">
                <option value="">सभी स्थिति (All Status)</option>
                <?php foreach ($status_labels as $val => $lbl): ?>
                    <option value="<?php echo $val; ?>" <?php echo ($status_filter === $val) ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select form-select-sm" name="type">
                <option value="">सभी प्रकार (All Types)</option>
                <option value="new" <?php echo ($type_filter === 'new') ? 'selected' : ''; ?>>New ID</option>
                <option value="renewal" <?php echo ($type_filter === 'renewal') ? 'selected' : ''; ?>>Renewal</option>
                <option value="duplicate" <?php echo ($type_filter === 'duplicate') ? 'selected' : ''; ?>>Duplicate</option>
                <option value="lost" <?php echo ($type_filter === 'lost') ? 'selected' : ''; ?>>Lost</option>
                <option value="replacement" <?php echo ($type_filter === 'replacement') ? 'selected' : ''; ?>>Replacement</option>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-primary btn-sm fw-semibold"><i class="fa-solid fa-filter me-1"></i>फिल्टर लागू करें</button>
        </div>
    </form>
</div>

<!-- Applications Table -->
<div class="table-responsive bg-white rounded shadow-sm border font-hindi small mb-4">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th class="py-3 px-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">आईडी</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">अधिवक्ता का नाम</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">सदस्य संख्या</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">पंजीकरण संख्या</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">आवेदन प्रकार</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">प्रस्तुत तिथि</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">स्थिति (Status)</th>
                <th class="py-3 px-3 text-end text-navy-custom fw-bold" style="font-size: 0.8rem;">कार्य</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($applications)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">कोई लंबित आवेदन नहीं मिले।</td>
                </tr>
            <?php else: ?>
                <?php foreach ($applications as $a): ?>
                    <tr>
                        <td class="py-3 px-3">
                            <span class="badge bg-light text-navy-custom border fw-bold english-text" style="font-size: 0.72rem; letter-spacing: 0.3px;">
                                #<?php echo $a['id']; ?>
                            </span>
                        </td>
                        <td class="py-3">
                            <strong class="text-navy-custom d-block english-text mb-0.5" style="font-size: 0.85rem;"><?php echo e($a['full_name']); ?></strong>
                        </td>
                        <td class="py-3 english-text text-secondary" style="font-size: 0.78rem;"><?php echo e($a['membership_no']); ?></td>
                        <td class="py-3 english-text text-secondary" style="font-size: 0.78rem;"><?php echo e($a['enrollment_no']); ?></td>
                        <td class="py-3">
                            <?php 
                            $type = strtolower($a['application_type']);
                            if ($type === 'new'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;">New ID</span>
                            <?php elseif ($type === 'renewal'): ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;">Renewal</span>
                            <?php else: ?>
                                <span class="badge bg-warning bg-opacity-15 text-warning-dark border border-warning-subtle px-2 py-1" style="color: #a36200; background-color: rgba(255, 193, 7, 0.15); font-size: 0.7rem; font-weight: 600;"><?php echo e(ucfirst($type)); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 english-text text-secondary" style="font-size: 0.75rem;">
                            <?php echo date('d-m-Y h:i A', strtotime($a['submitted_at'])); ?>
                        </td>
                        <td class="py-3">
                            <?php 
                            $st = $a['status'];
                            $lbl = $status_labels[$st] ?? $st;
                            if ($st === 'approved') {
                                echo '<span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-success me-1" style="font-size: 0.45rem;"></i>' . $lbl . '</span>';
                            } elseif ($st === 'submitted' || $st === 'under_review') {
                                echo '<span class="badge bg-warning bg-opacity-15 text-warning-dark border border-warning-subtle px-2 py-1" style="color: #a36200; background-color: rgba(255, 193, 7, 0.15); font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-warning-dark me-1" style="font-size: 0.45rem;"></i>' . $lbl . '</span>';
                            } elseif ($st === 'rejected') {
                                echo '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-danger me-1" style="font-size: 0.45rem;"></i>' . $lbl . '</span>';
                            } else {
                                echo '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-secondary me-1" style="font-size: 0.45rem;"></i>' . $lbl . '</span>';
                            }
                            ?>
                        </td>
                        <td class="py-3 px-3 text-end">
                            <a href="view.php?id=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-secondary fw-semibold py-1 px-2.5" style="font-size: 0.73rem;">
                                <i class="fa-solid fa-gears me-1"></i>समीक्षा / विवरण
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
    <nav class="mt-4 no-print font-hindi small">
        <ul class="pagination pagination-sm justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="index.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">पिछला</a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="index.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="index.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">अगला</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
