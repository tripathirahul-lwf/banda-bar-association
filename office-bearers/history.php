<?php
/**
 * Public Historical Committees Archive Directory
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$historical_terms = [];

if ($db) {
    try {
        // Fetch completed/archived terms
        $historical_terms = $db->query("
            SELECT * FROM office_bearer_terms 
            WHERE status IN ('completed', 'archived')
            ORDER BY start_date DESC, id DESC
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed query historical terms: " . $e->getMessage());
    }
}
?>

<div class="container py-5 text-navy-custom font-hindi small">
    
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
        <h4 class="text-navy-custom fw-bold mb-0">विगत कार्यकारिणी इतिहास पुरालेख (Historical Committees)</h4>
        <a href="<?php echo SITE_URL; ?>/office-bearers.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>सक्रिय कार्यकारिणी</a>
    </div>

    <!-- Empty States check (Requirement 42) -->
    <?php if (empty($historical_terms)): ?>
        <div class="text-center py-5 bg-white border rounded shadow-sm text-muted">
            <i class="bi bi-archive display-4 d-block mb-3 text-secondary"></i>
            <h5 class="fw-bold">पिछली कार्यकारिणी का रिकॉर्ड उपलब्ध नहीं है।</h5>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($historical_terms as $term): 
                // Fetch bearers snapshot of this historical term
                $term_bearers = [];
                try {
                    $b_stmt = $db->prepare("
                        SELECT ob.*, p.position_name, p.position_name_hindi 
                        FROM office_bearers ob
                        JOIN office_bearer_positions p ON p.id = ob.position_id
                        WHERE ob.term_id = ?
                        ORDER BY p.display_order ASC, ob.display_order ASC, ob.id ASC
                    ");
                    $b_stmt->execute([$term['id']]);
                    $term_bearers = $b_stmt->fetchAll();
                } catch (PDOException $e) {}
            ?>
                <div class="col-md-6 col-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-navy-custom text-white py-2">
                            <h6 class="mb-0 fw-bold"><?php echo e($term['title']); ?> (अवधि: <?php echo date('Y', strtotime($term['start_date'])); ?>-<?php echo $term['end_date'] ? date('Y', strtotime($term['end_date'])) : 'Completed'; ?>)</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted leading-relaxed font-size-xs border-bottom pb-2 mb-3" style="font-size:0.75rem;"><?php echo e($term['description'] ?: 'कोई अतिरिक्त कार्यकाल टिप्पणी दर्ज नहीं है।'); ?></p>
                            
                            <ul class="list-unstyled mb-0 ps-1" style="font-size:0.72rem; line-height:1.6;">
                                <?php if (empty($term_bearers)): ?>
                                    <li class="text-muted italic">इस सत्र की कार्यकारिणी पदाधिकारियों की सूची उपलब्ध नहीं है।</li>
                                <?php else: ?>
                                    <?php foreach ($term_bearers as $tb): ?>
                                        <li class="mb-2 d-flex align-items-center gap-2">
                                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $tb['photo_snapshot'] ?: 'profile/default_advocate.png'; ?>" class="rounded border" style="width: 28px; height: 28px; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                                            <div>
                                                <strong><?php echo e($tb['position_name_hindi']); ?>:</strong> 
                                                <span class="fw-semibold text-dark-custom"><?php echo e($tb['display_name_snapshot']); ?></span>
                                                <span class="text-muted" style="font-size:0.65rem;">(Code: <?php echo e($tb['membership_no_snapshot']); ?>)</span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php';
?>
