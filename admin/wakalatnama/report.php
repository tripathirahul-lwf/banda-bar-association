<?php
/**
 * Admin Member-wise Wakalatnama Download Reports with Filters
 * District Bar Association, Banda
 */

$pageTitle = 'वकालतनामा डाउनलोड रिपोर्ट (Download Reports)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$db = Database::getConnection();
$reports = [];
$total_records = 0;

if ($db) {
    try {
        $conditions = ["1=1"];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(m.full_name LIKE ? OR m.membership_no LIKE ? OR m.enrollment_no LIKE ? OR w.title LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($category_filter)) {
            $conditions[] = "w.category = ?";
            $params[] = $category_filter;
        }

        if (!empty($start_date)) {
            $conditions[] = "DATE(d.downloaded_at) >= ?";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $conditions[] = "DATE(d.downloaded_at) <= ?";
            $params[] = $end_date;
        }

        $where = implode(" AND ", $conditions);

        // Count total
        $count_stmt = $db->prepare("
            SELECT COUNT(*) FROM wakalatnama_downloads d
            JOIN members m ON m.id = d.member_id
            JOIN wakalatnamas w ON w.id = d.wakalatnama_id
            WHERE $where
        ");
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();

        // Fetch records
        $select_stmt = $db->prepare("
            SELECT d.*, m.full_name, m.membership_no, m.enrollment_no, w.title, w.category
            FROM wakalatnama_downloads d
            JOIN members m ON m.id = d.member_id
            JOIN wakalatnamas w ON w.id = d.wakalatnama_id
            WHERE $where
            ORDER BY d.id DESC
            LIMIT $limit OFFSET $offset
        ");
        $select_stmt->execute($params);
        $reports = $select_stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Failed loading reports list: " . $e->getMessage());
    }
}

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) $total_pages = 1;
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi no-print">
    <h4 class="text-navy-custom fw-bold mb-0">वकालतनामा डाउनलोड विस्तृत रिपोर्ट (Member Download Reports)</h4>
    <div class="d-flex gap-2">
        <a href="export.php?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-file-earmark-excel-fill me-1"></i>एक्सपोर्ट (CSV)</a>
        <a href="print.php?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" target="_blank" class="btn btn-xs btn-navy fw-semibold"><i class="bi bi-printer-fill me-1 text-gold-custom"></i>प्रिंट रिपोर्ट</a>
    </div>
</div>

<!-- Filters Panel -->
<div class="card p-3 mb-4 border-0 shadow-sm font-hindi small no-print">
    <form method="GET" action="report.php" class="row g-3">
        <div class="col-md-3">
            <label class="form-label text-secondary fw-semibold">खोजें</label>
            <input type="text" class="form-control form-control-sm" name="search" placeholder="नाम, सदस्य नं, शीर्षक..." value="<?php echo e($search); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary fw-semibold">श्रेणी</label>
            <select class="form-select form-select-sm" name="category">
                <option value="">सभी श्रेणियां</option>
                <option value="General" <?php echo ($category_filter === 'General') ? 'selected' : ''; ?>>General</option>
                <option value="Civil" <?php echo ($category_filter === 'Civil') ? 'selected' : ''; ?>>Civil</option>
                <option value="Criminal" <?php echo ($category_filter === 'Criminal') ? 'selected' : ''; ?>>Criminal</option>
                <option value="Family Court" <?php echo ($category_filter === 'Family Court') ? 'selected' : ''; ?>>Family Court</option>
                <option value="Revenue" <?php echo ($category_filter === 'Revenue') ? 'selected' : ''; ?>>Revenue</option>
                <option value="Other" <?php echo ($category_filter === 'Other') ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary fw-semibold">प्रारंभ तिथि</label>
            <input type="date" class="form-control form-control-sm english-text" name="start_date" value="<?php echo e($start_date); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary fw-semibold">अंतिम तिथि</label>
            <input type="date" class="form-control form-control-sm english-text" name="end_date" value="<?php echo e($end_date); ?>">
        </div>
        <div class="col-md-2 d-grid align-items-end">
            <button type="submit" class="btn btn-navy btn-sm fw-semibold py-2">फिल्टर लागू करें</button>
        </div>
    </form>
</div>

<!-- Table Output -->
<div class="table-responsive bg-white rounded shadow-sm border font-hindi small mb-4">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th class="py-2">सदस्य का नाम</th>
                <th>सदस्यता संख्या</th>
                <th>पंजीकरण संख्या</th>
                <th>वकालतनामा फ़ाइल</th>
                <th>श्रेणी</th>
                <th>डाउनलोड संस्करण</th>
                <th>दिनांक व समय</th>
                <th>आईपी पता</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">कोई डाउनलोड रिकॉर्ड नहीं मिले।</td>
                </tr>
            <?php else: ?>
                <?php foreach ($reports as $r): ?>
                    <tr>
                        <td class="py-2"><strong class="text-navy-custom english-text"><?php echo e($r['full_name']); ?></strong></td>
                        <td class="english-text"><?php echo e($r['membership_no']); ?></td>
                        <td class="english-text"><?php echo e($r['enrollment_no']); ?></td>
                        <td class="english-text"><?php echo e($r['title']); ?></td>
                        <td><span class="badge bg-light text-navy-custom border"><?php echo e($r['category']); ?></span></td>
                        <td class="english-text">v<?php echo e($r['version']); ?></td>
                        <td class="english-text"><?php echo date('d-m-Y H:i', strtotime($r['downloaded_at'])); ?></td>
                        <td class="english-text text-muted"><?php echo e($r['ip_address'] ?: 'N/A'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <nav class="no-print font-hindi small mb-5">
        <ul class="pagination pagination-sm justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="report.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">पिछला</a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="report.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="report.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">अगला</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
