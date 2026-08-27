<?php
/**
 * Admin / Secretary Notice Boards Management Panel
 * District Bar Association, Banda
 */

$pageTitle = 'अधिसूचना प्रबंधन (Notices Management)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$priority_filter = isset($_GET['priority']) ? trim($_GET['priority']) : '';
$visibility_filter = isset($_GET['visibility']) ? trim($_GET['visibility']) : '';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$db = Database::getConnection();
$notices = [];
$total_records = 0;

// Handle quick actions (Submit / Approve / Reject / Archive)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect('index.php');
    }

    $notice_id = intval($_POST['notice_id'] ?? 0);
    $action = trim($_POST['action']);
    $remarks = trim($_POST['remarks'] ?? '');

    $valid_statuses = [
        'submit' => 'pending_approval',
        'approve' => 'published',
        'reject' => 'rejected',
        'archive' => 'archived'
    ];

    if ($notice_id > 0 && array_key_exists($action, $valid_statuses)) {
        $new_status = $valid_statuses[$action];
        
        // President approvals checks where necessary
        if ($action === 'approve' && defined('NOTICE_REQUIRE_APPROVAL') && NOTICE_REQUIRE_APPROVAL) {
            if ($_SESSION['auth']['role'] !== 'president' && $_SESSION['auth']['role'] !== 'admin') {
                setFlash('danger', 'आपको इस अधिसूचना को अनुमोदित करने की अनुमति नहीं है।');
                redirect('index.php');
            }
        }

        try {
            $db->beginTransaction();
            $user_id = $_SESSION['auth']['user_id'];

            // Fetch old status
            $old_stmt = $db->prepare("SELECT status, title FROM notices WHERE id = ?");
            $old_stmt->execute([$notice_id]);
            $notice_row = $old_stmt->fetch();
            $old_status = $notice_row['status'] ?? 'draft';

            // If action is approve, we set published_at = NOW() if not already set, or if publish_at is scheduled in future we keep scheduled status!
            if ($action === 'approve') {
                $chk = $db->prepare("SELECT publish_at FROM notices WHERE id = ?");
                $chk->execute([$notice_id]);
                $pub_at = $chk->fetchColumn();
                
                if ($pub_at && strtotime($pub_at) > time()) {
                    $new_status = 'scheduled';
                }
            }

            // Update notices status
            if ($action === 'approve') {
                $up = $db->prepare("UPDATE notices SET status = ?, approved_by = ?, approved_at = NOW(), published_at = CASE WHEN ? = 'published' THEN NOW() ELSE published_at END WHERE id = ?");
                $up->execute([$new_status, $user_id, $new_status, $notice_id]);
            } elseif ($action === 'reject') {
                $up = $db->prepare("UPDATE notices SET status = ?, rejection_reason = ? WHERE id = ?");
                $up->execute([$new_status, $remarks ?: 'अस्वीकृत विवरण नहीं दिया गया।', $notice_id]);
            } else {
                $up = $db->prepare("UPDATE notices SET status = ? WHERE id = ?");
                $up->execute([$new_status, $notice_id]);
            }

            // Log notice history
            $hist = $db->prepare("
                INSERT INTO notice_history (notice_id, action, old_status, new_status, remarks, performed_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $hist->execute([
                $notice_id,
                ucfirst($action) . ' Action',
                $old_status,
                $new_status,
                $remarks ?: "स्थिति संक्रमण $old_status -> $new_status",
                $user_id
            ]);

            // Audit Trail
            $log_desc = "Notice ID $notice_id status transitioned from $old_status to $new_status";
            $audit = $db->prepare("
                INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                VALUES (0, 'Notice Status Update', 'status', ?, ?, ?, ?)
            ");
            $audit->execute([$old_status, $new_status, $log_desc, $user_id]);

            $db->commit();
            setFlash('success', "अधिसूचना स्थिति सफलतापूर्वक अपडेट की गई।");
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Notice action transition failed: " . $e->getMessage());
            setFlash('danger', 'कार्रवाई करने में विफलता: ' . $e->getMessage());
        }
        redirect('index.php');
    }
}

if ($db) {
    try {
        $conditions = ["1=1"];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(notice_no LIKE ? OR title LIKE ? OR title_hindi LIKE ? OR description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($status_filter)) {
            $conditions[] = "status = ?";
            $params[] = $status_filter;
        }

        if (!empty($category_filter)) {
            $conditions[] = "category = ?";
            $params[] = $category_filter;
        }

        if (!empty($priority_filter)) {
            $conditions[] = "priority = ?";
            $params[] = $priority_filter;
        }

        if (!empty($visibility_filter)) {
            $conditions[] = "visibility = ?";
            $params[] = $visibility_filter;
        }

        $where = implode(" AND ", $conditions);

        // Count matching records
        $cnt = $db->prepare("SELECT COUNT(*) FROM notices WHERE $where");
        $cnt->execute($params);
        $total_records = $cnt->fetchColumn();

        // Fetch notices records
        $stmt = $db->prepare("
            SELECT n.*, u.username as creator_name 
            FROM notices n
            LEFT JOIN users u ON u.id = n.created_by
            WHERE $where
            ORDER BY n.id DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $notices = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Failed to fetch notices list: " . $e->getMessage());
    }
}

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) $total_pages = 1;

$status_badges = [
    'draft' => 'bg-secondary',
    'pending_approval' => 'bg-warning text-dark',
    'approved' => 'bg-info text-dark',
    'scheduled' => 'bg-primary',
    'published' => 'bg-success',
    'rejected' => 'bg-danger',
    'expired' => 'bg-dark',
    'archived' => 'bg-dark text-white'
];

$categories = [
    'General Notice' => 'General Notice',
    'Important Notice' => 'Important Notice',
    'Court Notice' => 'Court Notice',
    'Meeting Notice' => 'Meeting Notice',
    'Election Notice' => 'Election Notice',
    'Holiday Notice' => 'Holiday Notice',
    'Association Notice' => 'Association Notice',
    'Condolence Notice' => 'Condolence Notice',
    'Other' => 'Other'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="fa-solid fa-bullhorn text-gold-dark me-2"></i>सूचना पट्ट प्रबंधन (Notices Management)</h4>
    <div class="d-flex gap-2">
        <a href="print-register.php" target="_blank" class="btn btn-sm btn-outline-secondary fw-semibold"><i class="fa-solid fa-print me-1"></i>रजिस्टर प्रिंट</a>
        <a href="export.php?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>" class="btn btn-sm btn-outline-success fw-semibold"><i class="fa-solid fa-file-excel me-1"></i>एक्सपोर्ट CSV</a>
        <a href="create.php" class="btn btn-sm btn-primary fw-semibold"><i class="fa-solid fa-circle-plus me-1 text-gold-custom"></i>नई सूचना निर्मित करें</a>
    </div>
</div>

<!-- Filters Panel -->
<div class="card p-3 mb-4 border-0 shadow-sm bg-light-custom font-hindi small" style="border-radius: 8px;">
    <form method="GET" action="index.php" class="row g-2 align-items-center">
        <div class="col-md-3">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass" style="font-size: 0.8rem;"></i></span>
                <input type="text" class="form-control border-start-0 ps-1" name="search" placeholder="खोजें: शीर्षक, संख्या..." value="<?php echo e($search); ?>">
            </div>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="status">
                <option value="">सभी स्थिति (All)</option>
                <option value="draft" <?php echo ($status_filter === 'draft') ? 'selected' : ''; ?>>ड्राफ्ट</option>
                <option value="pending_approval" <?php echo ($status_filter === 'pending_approval') ? 'selected' : ''; ?>>लंबित समीक्षा</option>
                <option value="published" <?php echo ($status_filter === 'published') ? 'selected' : ''; ?>>प्रकाशित</option>
                <option value="scheduled" <?php echo ($status_filter === 'scheduled') ? 'selected' : ''; ?>>अनुसूचित</option>
                <option value="rejected" <?php echo ($status_filter === 'rejected') ? 'selected' : ''; ?>>अस्वीकृत</option>
                <option value="archived" <?php echo ($status_filter === 'archived') ? 'selected' : ''; ?>>आर्काइव</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select form-select-sm" name="category">
                <option value="">सभी श्रेणियां (Category)</option>
                <?php foreach ($categories as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php echo ($category_filter === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="priority">
                <option value="">सभी प्राथमिकता (Priority)</option>
                <option value="normal" <?php echo ($priority_filter === 'normal') ? 'selected' : ''; ?>>सामान्य</option>
                <option value="important" <?php echo ($priority_filter === 'important') ? 'selected' : ''; ?>>महत्वपूर्ण</option>
                <option value="urgent" <?php echo ($priority_filter === 'urgent') ? 'selected' : ''; ?>>अति आवश्यक</option>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-primary btn-sm fw-semibold"><i class="fa-solid fa-filter me-1"></i>फिल्टर करें</button>
        </div>
    </form>
</div>

<!-- Table list -->
<div class="table-responsive bg-white rounded shadow-sm border font-hindi small mb-4">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th class="py-3 px-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">सूचना संख्या</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">शीर्षक (Title)</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">श्रेणी</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">प्राथमिकता</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">दृश्यता</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">प्रकाशन तिथि</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">स्थिति (Status)</th>
                <th class="py-3 px-3 text-end text-navy-custom fw-bold" style="font-size: 0.8rem;">कार्य</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($notices)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">कोई अधिसूचना रिकॉर्ड नहीं मिला।</td>
                </tr>
            <?php else: ?>
                <?php foreach ($notices as $n): ?>
                    <tr>
                        <td class="py-3 px-3">
                            <span class="badge bg-light text-navy-custom border fw-bold english-text" style="font-size: 0.72rem; letter-spacing: 0.3px;">
                                <?php echo e($n['notice_no'] ?: 'Auto-Generate'); ?>
                            </span>
                        </td>
                        <td class="py-3">
                            <strong class="text-navy-custom d-block english-text mb-0.5" style="font-size: 0.85rem;"><?php echo e($n['title']); ?></strong>
                            <?php if (!empty($n['title_hindi'])): ?>
                                <small class="text-muted d-block font-hindi" style="font-size: 0.73rem;"><?php echo e($n['title_hindi']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 text-secondary" style="font-size: 0.78rem;"><?php echo e($n['category']); ?></td>
                        <td class="py-3">
                            <?php if ($n['priority'] === 'urgent'): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;">अति आवश्यक</span>
                            <?php elseif ($n['priority'] === 'important'): ?>
                                <span class="badge bg-warning bg-opacity-15 text-warning-dark border border-warning-subtle px-2 py-1" style="color: #a36200; background-color: rgba(255, 193, 7, 0.15); font-size: 0.7rem; font-weight: 600;">महत्वपूर्ण</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;">सामान्य</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 text-secondary english-text" style="font-size: 0.78rem;">
                            <?php if ($n['visibility'] === 'public'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-2" style="font-size: 0.68rem; font-weight: 600;">Public</span>
                            <?php else: ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-2" style="font-size: 0.68rem; font-weight: 600;">Members Only</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 english-text text-secondary" style="font-size: 0.75rem;">
                            <?php echo $n['publish_at'] ? date('d-m-Y H:i', strtotime($n['publish_at'])) : 'तुरंत'; ?>
                        </td>
                        <td class="py-3">
                            <?php 
                            $st = $n['status'];
                            if ($st === 'published') {
                                echo '<span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-success me-1" style="font-size: 0.45rem;"></i>Published</span>';
                            } elseif ($st === 'draft') {
                                echo '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-secondary me-1" style="font-size: 0.45rem;"></i>Draft</span>';
                            } elseif ($st === 'pending_approval') {
                                echo '<span class="badge bg-warning bg-opacity-15 text-warning-dark border border-warning-subtle px-2 py-1" style="color: #a36200; background-color: rgba(255, 193, 7, 0.15); font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-warning-dark me-1" style="font-size: 0.45rem;"></i>Pending</span>';
                            } elseif ($st === 'scheduled') {
                                echo '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-primary me-1" style="font-size: 0.45rem;"></i>Scheduled</span>';
                            } elseif ($st === 'rejected') {
                                echo '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-danger me-1" style="font-size: 0.45rem;"></i>Rejected</span>';
                            } else {
                                echo '<span class="badge bg-dark bg-opacity-10 text-dark border border-dark-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-dark me-1" style="font-size: 0.45rem;"></i>' . ucfirst($st) . '</span>';
                            }
                            ?>
                        </td>
                        <td class="py-3 px-3 text-end">
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle fw-semibold py-1 px-2" type="button" data-bs-toggle="dropdown" style="font-size: 0.73rem;">
                                    <i class="fa-solid fa-gears me-1"></i>कार्रवाई
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm font-hindi small">
                                    <li><a class="dropdown-item" href="preview.php?slug=<?php echo urlencode($n['slug']); ?>" target="_blank"><i class="fa-solid fa-eye me-2 text-navy-custom"></i>पूर्वावलोकन (Preview)</a></li>
                                    <li><a class="dropdown-item" href="edit.php?id=<?php echo $n['id']; ?>"><i class="fa-solid fa-pen-to-square me-2 text-warning"></i>संपादित करें (Edit)</a></li>
                                    
                                    <!-- Dynamic status workflow triggers -->
                                    <?php if ($n['status'] === 'draft'): ?>
                                        <li>
                                            <form method="POST" action="index.php" style="display:inline;">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="notice_id" value="<?php echo $n['id']; ?>">
                                                <button type="submit" name="action" value="submit" class="dropdown-item text-info"><i class="fa-solid fa-share me-2"></i>अनुमोदन हेतु भेजें</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" action="index.php" style="display:inline;">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="notice_id" value="<?php echo $n['id']; ?>">
                                                <button type="submit" name="action" value="approve" class="dropdown-item text-success" onclick="return confirm('क्या आप इसे सीधे प्रकाशित करना चाहते हैं?');"><i class="fa-solid fa-globe me-2"></i>सीधे प्रकाशित करें</button>
                                            </form>
                                        </li>
                                    <?php endif; ?>

                                    <?php if ($n['status'] === 'pending_approval'): ?>
                                        <li>
                                            <form method="POST" action="index.php" style="display:inline;">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="notice_id" value="<?php echo $n['id']; ?>">
                                                <button type="submit" name="action" value="approve" class="dropdown-item text-success" onclick="return confirm('क्या आप इस अधिसूचना को अनुमोदित करना चाहते हैं?');"><i class="fa-solid fa-circle-check me-2"></i>अनुमोदित करें (Approve)</button>
                                            </form>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" onclick="var r = prompt('अस्वीकृति का कारण दर्ज करें:'); if(r){ var f = document.createElement('form'); f.method='POST'; f.action='index.php'; var c = document.createElement('input'); c.type='hidden'; c.name='csrf_token'; c.value='<?php echo $_SESSION['csrf_token']; ?>'; f.appendChild(c); var i = document.createElement('input'); i.type='hidden'; i.name='notice_id'; i.value='<?php echo $n['id']; ?>'; f.appendChild(i); var a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='reject'; f.appendChild(a); var rm = document.createElement('input'); rm.type='hidden'; rm.name='remarks'; rm.value=r; f.appendChild(rm); document.body.appendChild(f); f.submit(); } return false;"><i class="fa-solid fa-circle-xmark me-2"></i>अस्वीकार करें (Reject)</a>
                                        </li>
                                    <?php endif; ?>

                                    <?php if ($n['status'] !== 'archived'): ?>
                                        <li>
                                            <form method="POST" action="index.php" style="display:inline;">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="notice_id" value="<?php echo $n['id']; ?>">
                                                <button type="submit" name="action" value="archive" class="dropdown-item text-danger" onclick="return confirm('क्या आप वास्तव में इस अधिसूचना को संग्रहित (Archive) करना चाहते हैं?');"><i class="fa-solid fa-box-archive me-2"></i>संग्रहित करें (Archive)</button>
                                            </form>
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
                <a class="page-link" href="index.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>">पिछला</a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="index.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="index.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>">अगला</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
