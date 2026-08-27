<?php
/**
 * Member-only Notice Center Dashboard list
 * District Bar Association, Banda
 */

$pageTitle = 'संघीय सूचनाएं (Notices)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce member role
requireRole('member');

$user = currentUser();
$member_id = $user['member_id'] ?? 0;

$selected_category = isset($_GET['category']) ? trim($_GET['category']) : '';
$selected_priority = isset($_GET['priority']) ? trim($_GET['priority']) : '';

$db = Database::getConnection();
$notices = [];

if ($db) {
    try {
        $conditions = [
            "status = 'published'",
            "publish_at <= CURRENT_TIMESTAMP()",
            "(expire_at IS NULL OR expire_at >= CURRENT_TIMESTAMP())"
        ];
        $params = [];

        if (!empty($selected_category)) {
            if ($selected_category === 'Important') {
                $conditions[] = "priority IN ('important', 'urgent')";
            } else {
                $conditions[] = "category = ?";
                $params[] = $selected_category . ' Notice';
            }
        }

        if (!empty($selected_priority)) {
            $conditions[] = "priority = ?";
            $params[] = $selected_priority;
        }

        $where = implode(" AND ", $conditions);

        // Fetch notices and track member read/unread status
        $stmt = $db->prepare("
            SELECT n.*, 
                   (SELECT COUNT(*) FROM notice_views WHERE notice_id = n.id AND member_id = ?) as view_count
            FROM notices n
            WHERE $where
            ORDER BY 
                CASE priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'important' THEN 2 
                    ELSE 3 
                END, 
                n.published_at DESC, 
                n.id DESC
        ");
        $stmt->execute(array_merge([$member_id], $params));
        $notices = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading member notices center: " . $e->getMessage());
    }
}

$categories_labels = [
    'All' => 'सभी सूचनाएं',
    'General' => 'सामान्य सूचना',
    'Meeting' => 'बैठक सूचना',
    'Court' => 'न्यायालय आदेश',
    'Election' => 'चुनाव सूचना',
    'Important' => 'महत्वपूर्ण सूचना',
    'Condolence' => 'शोक संदेश'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-bell-fill me-2"></i>संघीय सूचना पट्ट (Notice Board)</h4>
</div>

<!-- Category Filters strip -->
<div class="bg-white p-2 rounded border mb-4 font-hindi small">
    <div class="d-flex flex-wrap gap-1 align-items-center">
        <span class="text-secondary fw-semibold me-2"><i class="bi bi-funnel"></i> फ़िल्टर:</span>
        <?php foreach ($categories_labels as $key => $label): ?>
            <a href="index.php?category=<?php echo urlencode($key); ?>" class="btn btn-xs <?php echo (($selected_category ?: 'All') === $key) ? 'btn-navy' : 'btn-outline-navy'; ?>">
                <?php echo sanitize($label); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Listing -->
<div class="row g-3 font-hindi small mb-5">
    <?php if (empty($notices)): ?>
        <div class="col-12 text-center py-5 bg-white border rounded shadow-xs">
            <i class="bi bi-bell-slash text-muted display-4 mb-2 d-block"></i>
            <h5 class="fw-bold text-navy-custom">कोई नई सूचना उपलब्ध नहीं है।</h5>
        </div>
    <?php else: ?>
        <?php foreach ($notices as $n): ?>
            <?php 
            $is_unread = ($n['view_count'] == 0);
            $priority_class = ($n['priority'] === 'urgent') ? 'bg-danger text-white' : (($n['priority'] === 'important') ? 'bg-warning text-dark' : 'bg-secondary text-white');
            $border_class = ($n['priority'] === 'urgent') ? 'border-danger border-2' : (($n['priority'] === 'important') ? 'border-warning' : 'border-light');
            ?>
            <div class="col-md-6 col-12">
                <div class="card p-3 bg-white h-100 shadow-xs border <?php echo $border_class; ?>">
                    
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($is_unread): ?>
                                <span class="badge bg-danger animate-pulse font-size-xs px-2 py-0.5"><i class="bi bi-star-fill me-1"></i>New</span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border font-size-xs px-2 py-0.5"><i class="bi bi-eye-fill me-1"></i>Read</span>
                            <?php endif; ?>
                            <span class="badge <?php echo $priority_class; ?> font-size-xs"><?php echo e($n['priority']); ?></span>
                        </div>
                        <small class="text-muted english-text"><?php echo date('d-m-Y', strtotime($n['published_at'] ?: $n['created_at'])); ?></small>
                    </div>

                    <h6 class="fw-bold text-navy-custom mb-1">
                        <a href="<?php echo SITE_URL; ?>/notice.php?slug=<?php echo urlencode($n['slug']); ?>" class="text-navy-custom text-decoration-none">
                            <?php echo e($n['title']); ?>
                        </a>
                    </h6>
                    <span class="badge bg-light text-navy-custom border align-self-start font-size-xs px-2 py-0.5 mb-2"><?php echo e($n['category']); ?></span>
                    
                    <p class="text-muted mb-3" style="font-size:0.75rem; line-height:1.5; flex:1;">
                        <?php echo e($n['short_description'] ?: trim_words($n['description'], 120)); ?>
                    </p>

                    <div class="d-flex justify-content-between align-items-center border-top pt-2">
                        <?php if ($n['visibility'] === 'members_only'): ?>
                            <span class="badge bg-dark text-white font-size-xs"><i class="bi bi-lock-fill me-1"></i>Members Only</span>
                        <?php else: ?>
                            <span class="badge bg-light text-muted border font-size-xs"><i class="bi bi-globe2 me-1"></i>Public</span>
                        <?php endif; ?>

                        <div class="d-flex gap-1">
                            <?php if (!empty($n['attachment_path'])): ?>
                                <a href="<?php echo ($n['visibility'] === 'members_only') ? 'download.php?id=' . $n['id'] : SITE_URL . '/uploads/notices/' . $n['attachment_path']; ?>" target="_blank" class="btn btn-xs btn-outline-navy fw-semibold">
                                    <i class="bi bi-file-earmark-pdf-fill me-1 text-danger"></i>संलग्नक
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo SITE_URL; ?>/notice.php?slug=<?php echo urlencode($n['slug']); ?>" class="btn btn-xs btn-navy fw-semibold">पढ़ें (Read)</a>
                        </div>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
