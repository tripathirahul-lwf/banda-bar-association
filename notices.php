<?php
/**
 * Public Notices Board
 * District Bar Association, Banda
 */

$pageTitle = 'सूचना पट्ट (Notice Board)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
require_once 'config/database.php';

$selected_category = isset($_GET['category']) ? trim($_GET['category']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Server-side pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$db = Database::getConnection();
$notices = [];
$total_records = 0;

if ($db) {
    try {
        $conditions = [
            "visibility = 'public'",
            "status = 'published'",
            "publish_at <= CURRENT_TIMESTAMP()",
            "(expire_at IS NULL OR expire_at >= CURRENT_TIMESTAMP())"
        ];
        $params = [];

        if (!empty($selected_category)) {
            $conditions[] = "category = ?";
            $params[] = $selected_category;
        }

        if (!empty($search)) {
            $conditions[] = "(notice_no LIKE ? OR title LIKE ? OR title_hindi LIKE ? OR description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $where = implode(" AND ", $conditions);

        // Count total matching
        $count_stmt = $db->prepare("SELECT COUNT(*) FROM notices WHERE $where");
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();

        // Query notices with priority order: urgent first, then important, then normal
        $stmt = $db->prepare("
            SELECT n.*, c.advocate_name, c.advocate_photo, c.date_of_death 
            FROM notices n
            LEFT JOIN condolence_notices c ON c.notice_id = n.id
            WHERE $where
            ORDER BY 
                CASE priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'important' THEN 2 
                    ELSE 3 
                END, 
                n.published_at DESC, 
                n.id DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $notices = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error fetching notices: " . $e->getMessage());
    }
}

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) $total_pages = 1;

$categories = [
    'General Notice' => 'सामान्य सूचना',
    'Important Notice' => 'महत्वपूर्ण सूचना',
    'Court Notice' => 'न्यायालय आदेश',
    'Meeting Notice' => 'बैठक सूचना',
    'Election Notice' => 'चुनाव सूचना',
    'Holiday Notice' => 'अवकाश सूचना',
    'Association Notice' => 'संघीय सूचना',
    'Condolence Notice' => 'शोक संदेश',
    'Other' => 'अन्य विवरण'
];
?>

<!-- Banner -->
<div class="bg-navy-custom text-white p-4 rounded-3 mb-4 shadow-sm border-bottom border-gold-custom">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="h3 mb-1 font-hindi fw-bold text-gold-custom">सूचना पट्ट (Notice Board)</h1>
            <p class="mb-0 text-light-custom english-text text-uppercase tracking-wider small">जिला अधिवक्ता संघ, बांदा के विधिक, संगठनात्मक और प्रशासनिक आदेश</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="badge bg-gold-custom text-navy-custom font-hindi px-3 py-2 fs-6">सक्रिय सूचनाएं: <?php echo $total_records; ?></span>
        </div>
    </div>
</div>

<!-- Filters & Search Panel -->
<div class="bg-white p-3 rounded-3 shadow-sm border border-light mb-4 font-hindi small">
    <form method="GET" action="notices.php" class="row g-3 align-items-center">
        <div class="col-md-3">
            <input type="text" class="form-control form-control-sm" name="search" placeholder="खोजें: विषय, संख्या..." value="<?php echo e($search); ?>">
        </div>
        <div class="col-md-7 d-flex flex-wrap gap-1 align-items-center">
            <a href="notices.php?search=<?php echo urlencode($search); ?>" class="btn btn-xs <?php echo ($selected_category === '') ? 'btn-navy' : 'btn-outline-navy'; ?>">
                सभी श्रेणियां
            </a>
            <?php foreach ($categories as $key => $label): ?>
                <a href="notices.php?category=<?php echo urlencode($key); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-xs <?php echo ($selected_category === $key) ? 'btn-navy' : 'btn-outline-navy'; ?>">
                    <?php echo sanitize($label); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-navy btn-sm fw-semibold">खोजें (Search)</button>
        </div>
    </form>
</div>

<!-- Notices List Grid -->
<div class="row g-4 mb-4">
    <?php if (empty($notices)): ?>
        <div class="col-12 text-center py-5 bg-white rounded-3 shadow-sm border border-light">
            <i class="bi bi-bell-slash text-muted display-4 mb-3 d-block"></i>
            <h5 class="text-navy-custom font-hindi fw-bold">वर्तमान में कोई सूचना उपलब्ध नहीं है।</h5>
            <p class="text-muted small">कृपया बाद में पुनः जाँच करें।</p>
        </div>
    <?php else: ?>
        <?php foreach ($notices as $notice): ?>
            <?php 
            $isCondolence = ($notice['category'] === 'Condolence Notice');
            
            // Priority styling rules
            $cardBorder = 'border-light';
            $priorityBadge = 'bg-secondary text-white';
            if ($notice['priority'] === 'urgent') {
                $cardBorder = 'border-danger border-2';
                $priorityBadge = 'bg-danger text-white';
            } elseif ($notice['priority'] === 'important') {
                $cardBorder = 'border-warning';
                $priorityBadge = 'bg-warning text-dark';
            }
            ?>
            
            <div class="col-lg-6 col-12">
                <?php if ($isCondolence): ?>
                    <!-- Respectful condolence card -->
                    <div class="card-condolence p-4 shadow-xs h-100 d-flex flex-column border">
                        <div class="condolence-ribbon"></div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-dark text-white font-hindi font-size-xs px-3 py-1 border border-secondary">
                                <i class="bi bi-flower1 me-1 text-muted"></i>शोक संदेश (Condolence)
                            </span>
                            <small class="text-muted font-hindi fw-bold">
                                <i class="bi bi-calendar3 me-1"></i><?php echo date('d-m-Y', strtotime($notice['published_at'] ?: $notice['created_at'])); ?>
                            </small>
                        </div>
                        
                        <div class="row align-items-center g-3 my-2 font-hindi">
                            <div class="col-sm-3 col-12 text-center">
                                <div class="mx-auto rounded border border-dark p-1 bg-white" style="width: 80px; height: 100px; overflow: hidden; filter: grayscale(100%);">
                                    <?php if (!empty($notice['advocate_photo']) && $notice['advocate_photo'] !== 'default_advocate.png'): ?>
                                        <img src="uploads/photos/<?php echo e($notice['advocate_photo']); ?>" alt="Advocate Photo" style="width:100%; height:100%; object-fit:cover;">
                                    <?php else: ?>
                                        <div class="d-flex flex-column align-items-center justify-content-center h-100 bg-light text-muted">
                                            <i class="bi bi-person-fill" style="font-size: 2.5rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-sm-9 col-12 text-center text-sm-start">
                                <h5 class="fw-bold text-dark-custom mb-1 text-decoration-underline">
                                    <?php echo e($notice['advocate_name']); ?>
                                </h5>
                                <p class="small text-muted mb-0">जिला अधिवक्ता संघ, बांदा</p>
                            </div>
                        </div>

                        <hr class="border-secondary opacity-50 my-2">

                        <p class="font-hindi text-secondary mb-3 small" style="line-height: 1.6; text-align: justify; font-style: italic;">
                            "<?php echo e($notice['short_description'] ?: trim_words($notice['description'], 150)); ?>"
                        </p>
                        
                        <div class="mt-auto pt-2 border-top border-light d-flex justify-content-between align-items-center">
                            <span class="small font-hindi text-muted">ॐ शांति ॐ</span>
                            <div class="d-flex gap-1">
                                <?php if (!empty($notice['attachment_path'])): ?>
                                    <a href="<?php echo SITE_URL; ?>/uploads/notices/<?php echo e($notice['attachment_path']); ?>" target="_blank" class="btn btn-xs btn-outline-dark font-hindi py-1">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>संलग्नक PDF
                                    </a>
                                <?php endif; ?>
                                <a href="notice.php?slug=<?php echo urlencode($notice['slug']); ?>" class="btn btn-xs btn-dark font-hindi py-1">विवरण देखें</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Standard Notice Card -->
                    <div class="card-custom p-4 h-100 d-flex flex-column border <?php echo $cardBorder; ?>">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge <?php echo $priorityBadge; ?> font-hindi font-size-xs px-3 py-1">
                                <?php echo e($notice['priority']); ?>
                            </span>
                            <small class="text-muted font-hindi"><i class="bi bi-calendar3 me-1"></i><?php echo date('d-m-Y', strtotime($notice['published_at'] ?: $notice['created_at'])); ?></small>
                        </div>
                        
                        <h5 class="fw-bold text-navy-custom font-hindi mb-2">
                            <?php if ($notice['priority'] === 'urgent'): ?>
                                <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                            <?php else: ?>
                                <i class="bi bi-bookmark-fill text-gold-custom me-1"></i>
                            <?php endif; ?>
                            <?php echo e($notice['title']); ?>
                        </h5>
                        
                        <span class="badge bg-light-custom text-navy-custom align-self-start font-hindi font-size-xs border border-light py-1 px-2 mb-3">
                            <?php echo e($categories[$notice['category']] ?? $notice['category']); ?>
                        </span>

                        <p class="font-hindi text-muted mb-4 small" style="line-height: 1.6;">
                            <?php echo e($notice['short_description'] ?: trim_words($notice['description'], 150)); ?>
                        </p>
                        
                        <div class="mt-auto pt-2 border-top border-light d-flex justify-content-between align-items-center">
                            <?php if (!empty($notice['attachment_path'])): ?>
                                <a href="<?php echo SITE_URL; ?>/uploads/notices/<?php echo e($notice['attachment_path']); ?>" target="_blank" class="btn btn-xs btn-outline-navy font-hindi d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-file-earmark-arrow-down-fill text-gold-dark"></i>
                                    <span>संलग्नक (PDF)</span>
                                </a>
                            <?php else: ?>
                                <span class="text-muted small font-hindi font-size-xs">कोई फ़ाइल नहीं</span>
                            <?php endif; ?>
                            
                            <a href="notice.php?slug=<?php echo urlencode($notice['slug']); ?>" class="btn btn-xs btn-navy font-hindi">पूर्ण विवरण देखें</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination navigation -->
<?php if ($total_pages > 1): ?>
    <nav class="font-hindi small mb-5">
        <ul class="pagination pagination-sm justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="notices.php?page=<?php echo $page - 1; ?>&category=<?php echo urlencode($selected_category); ?>&search=<?php echo urlencode($search); ?>">पिछला</a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="notices.php?page=<?php echo $i; ?>&category=<?php echo urlencode($selected_category); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="notices.php?page=<?php echo $page + 1; ?>&category=<?php echo urlencode($selected_category); ?>&search=<?php echo urlencode($search); ?>">अगला</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php 
require_once 'includes/footer.php';
?>
