<?php
/**
 * Public Executive Committee Members Directory
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'कार्यकारिणी समिति (Executive Committee)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
require_once 'config/database.php';

$db = Database::getConnection();

$active_term = null;
$officers = [];
$executives = [];

if ($db) {
    try {
        $t_stmt = $db->query("SELECT * FROM office_bearer_terms WHERE status = 'active' LIMIT 1");
        $active_term = $t_stmt->fetch();

        if ($active_term) {
            // Group 1: Core Office Bearers
            $o_stmt = $db->prepare("
                SELECT ob.*, p.position_name, p.position_name_hindi, p.code AS position_code
                FROM office_bearers ob
                JOIN office_bearer_positions p ON p.id = ob.position_id
                WHERE ob.term_id = ? AND ob.status = 'active' AND p.code != 'EXECUTIVE_MEMBER'
                ORDER BY p.display_order ASC, ob.display_order ASC, ob.id ASC
            ");
            $o_stmt->execute([$active_term['id']]);
            $officers = $o_stmt->fetchAll();

            // Group 2: Executive Members
            $e_stmt = $db->prepare("
                SELECT ob.*, p.position_name, p.position_name_hindi, p.code AS position_code
                FROM office_bearers ob
                JOIN office_bearer_positions p ON p.id = ob.position_id
                WHERE ob.term_id = ? AND ob.status = 'active' AND p.code = 'EXECUTIVE_MEMBER'
                ORDER BY ob.display_order ASC, ob.id ASC
            ");
            $e_stmt->execute([$active_term['id']]);
            $executives = $e_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Failed to load public executive committee list: " . $e->getMessage());
    }
}
?>

<div class="container py-5 text-navy-custom font-hindi small">
    
    <!-- Title Banner -->
    <div class="bg-navy-custom text-white p-4 rounded-3 mb-4 shadow-sm text-center border-bottom border-gold-custom">
        <span class="badge bg-gold-custom text-navy-custom mb-2 px-3 py-1 fw-bold text-uppercase">जिला अधिवक्ता संघ, बांदा</span>
        <h2 class="fw-bold font-hindi text-white mb-1">प्रबंधकारिणी समिति (Executive Committee Board)</h2>
        <p class="mb-0 text-light-custom english-text text-uppercase tracking-wider small">
            <?php echo $active_term ? e($active_term['title']) : 'सत्र विवरण अनुपलब्ध'; ?>
        </p>
    </div>

    <?php if (!$active_term): ?>
        <div class="text-center py-5 bg-white border rounded shadow-sm text-muted">
            <h5 class="fw-bold mb-0">वर्तमान कार्यकारिणी विवरण शीघ्र उपलब्ध होगा।</h5>
        </div>
    <?php else: ?>
        
        <!-- Section 1: Core Officers -->
        <h4 class="fw-bold text-navy-custom border-bottom pb-2 mb-4 font-hindi"><i class="bi bi-award-fill text-gold-dark me-2"></i>मुख्य पदाधिकारी (Office Bearers)</h4>
        
        <div class="row g-3 mb-5">
            <?php if (empty($officers)): ?>
                <div class="col-12 text-center text-muted">मुख्य पदाधिकारियों की सूची प्रक्रियाधीन है।</div>
            <?php else: ?>
                <?php foreach ($officers as $ob): ?>
                    <div class="col-md-4 col-sm-6 col-12">
                        <div class="p-3 bg-white border rounded shadow-xs d-flex align-items-center gap-3">
                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $ob['photo_snapshot'] ?: 'profile/default_advocate.png'; ?>" class="rounded border" style="width: 50px; height: 50px; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                            <div>
                                <h6 class="fw-bold mb-0 text-navy-custom"><?php echo e($ob['display_name_snapshot']); ?></h6>
                                <span class="badge bg-gold-custom text-navy-custom py-0.5 px-2 my-1" style="font-size:0.65rem;">
                                    <?php echo e($ob['designation_override'] ?: $ob['position_name_hindi']); ?>
                                </span>
                                <small class="d-block text-muted" style="font-size:0.65rem;">Code: <?php echo e($ob['membership_no_snapshot']); ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Section 2: Executive Committee Members -->
        <h4 class="fw-bold text-navy-custom border-bottom pb-2 mb-4 font-hindi"><i class="bi bi-people-fill text-gold-dark me-2"></i>कार्यकारिणी सदस्य (Executive Members)</h4>
        
        <div class="row g-3 mb-4">
            <?php if (empty($executives)): ?>
                <div class="col-12 text-center text-muted py-3 bg-light rounded">इस कार्यकाल में कोई विशिष्ट कार्यकारिणी सदस्य नामांकित नहीं है।</div>
            <?php else: ?>
                <?php foreach ($executives as $ex): ?>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="p-3 bg-light bg-opacity-50 border rounded shadow-xs d-flex align-items-center gap-3">
                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $ex['photo_snapshot'] ?: 'profile/default_advocate.png'; ?>" class="rounded border" style="width: 45px; height: 45px; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                            <div>
                                <h6 class="fw-bold mb-0 text-navy-custom" style="font-size:0.75rem;"><?php echo e($ex['display_name_snapshot']); ?></h6>
                                <small class="text-muted d-block" style="font-size:0.65rem;">कार्यकारिणी सदस्य</small>
                                <small class="text-muted d-block" style="font-size:0.65rem;">DBA Code: <?php echo e($ex['membership_no_snapshot']); ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="text-center mt-5 mb-4">
            <a href="office-bearers.php" class="btn btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>मुख्य पदाधिकारी प्रोफाइल</a>
            <a href="office-bearers/history.php" class="btn btn-outline-secondary ms-2"><i class="bi bi-clock-history me-1"></i>विगत समितियां</a>
        </div>
    <?php endif; ?>
</div>

<?php 
require_once 'includes/footer.php';
?>
