<?php
/**
 * Public Office Bearers Directory
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'कार्यकारिणी पदाधिकारी (Executive Committee)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
require_once 'config/database.php';

$db = Database::getConnection();

// Retrieve Active Term
$active_term = null;
$bearers = [];

if ($db) {
    try {
        $t_stmt = $db->query("SELECT * FROM office_bearer_terms WHERE status = 'active' LIMIT 1");
        $active_term = $t_stmt->fetch();

        if ($active_term) {
            $b_stmt = $db->prepare("
                SELECT ob.*, p.position_name, p.position_name_hindi 
                FROM office_bearers ob
                JOIN office_bearer_positions p ON p.id = ob.position_id
                WHERE ob.term_id = ? AND ob.status = 'active'
                ORDER BY p.display_order ASC, ob.display_order ASC, ob.id ASC
            ");
            $b_stmt->execute([$active_term['id']]);
            $bearers = $b_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Failed to load public bearers list: " . $e->getMessage());
    }
}
?>

<!-- Office Bearers Page Banner -->
<div class="bg-navy-custom text-white p-4 rounded-3 mb-4 shadow-sm border-bottom border-gold-custom font-hindi">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="h3 mb-1 font-hindi fw-bold text-gold-custom">कार्यकारिणी पदाधिकारी (Executive Committee)</h1>
            <p class="mb-0 text-light-custom english-text text-uppercase tracking-wider small">District Bar Association, Banda</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="badge bg-gold-custom text-navy-custom font-hindi px-3 py-2 fs-6">
                <?php echo $active_term ? e($active_term['title']) : 'सत्र विवरण अनुपलब्ध'; ?>
            </span>
        </div>
    </div>
</div>

<div class="container py-2 text-navy-custom font-hindi small">
    
    <!-- Institutional description ticker -->
    <div class="alert alert-info border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-info text-info fs-5"></i>
        <div>
            <strong>आधिकारिक सूचना:</strong> जिला अधिवक्ता संघ बांदा के पदाधिकारियों की सूची एवं उनके कार्यकाल का आधिकारिक विवरण यहाँ दर्शित है। किसी भी प्रशासनिक अथवा विधिक कार्य हेतु संबंधित पदाधिकारी से संपर्क करें।
        </div>
    </div>

    <!-- Empty States Check (Requirement 42) -->
    <?php if (empty($bearers)): ?>
        <div class="text-center py-5 bg-white border rounded shadow-sm text-muted">
            <i class="fa-solid fa-users display-4 d-block mb-3 text-secondary"></i>
            <h5 class="fw-bold mb-2">वर्तमान कार्यकारिणी विवरण शीघ्र उपलब्ध होगा।</h5>
            <p class="text-muted">पूर्व कार्यकाल की समितियों को देखने के लिए पुरालेख अनुभाग में जाएं।</p>
            <a href="office-bearers/history.php" class="btn btn-navy btn-sm mt-3"><i class="fa-solid fa-box-archive me-1"></i>विगत कार्यकारिणी इतिहास</a>
        </div>
    <?php else: ?>
        <!-- Bearers Grid -->
        <div class="row g-4 mb-5">
            <?php foreach ($bearers as $bearer): ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card-custom h-100 card-bearer p-3 d-flex flex-column text-center bg-white border rounded shadow-xs">
                        
                        <!-- Photo Container -->
                        <div class="mb-3 mx-auto rounded-circle overflow-hidden bg-light border border-gold-custom border-2 d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; position: relative;">
                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $bearer['photo_snapshot'] ?: 'profile/default_advocate.png'; ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                            <span class="position-absolute bottom-0 end-0 bg-navy-custom text-white rounded-circle p-1 border border-white" style="line-height: 1; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-circle-check text-gold-custom" style="font-size: 0.85rem;"></i>
                            </span>
                        </div>
                        
                        <!-- Information -->
                        <h5 class="fw-bold mb-1 text-navy-custom english-text fs-6"><?php echo e($bearer['display_name_snapshot']); ?></h5>
                        <div class="mb-2">
                            <span class="badge bg-navy-custom text-white font-hindi px-3 py-1 font-size-xs" style="font-size: 0.72rem; border-left: 3px solid var(--gold-accent);">
                                <?php echo e($bearer['designation_override'] ?: $bearer['position_name_hindi']); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($bearer['message'])): ?>
                            <p class="small text-muted font-hindi px-2 mb-3 border rounded p-1 bg-light bg-opacity-50" style="font-size:0.72rem; line-height:1.4;">
                                "<?php echo e($bearer['message']); ?>"
                            </p>
                        <?php endif; ?>

                        <!-- Detailed metadata (Hide private phone/email) -->
                        <div class="border-top border-light pt-2 mt-auto text-start font-hindi small text-muted" style="font-size:0.75rem;">
                            <div class="d-flex justify-content-between mb-1">
                                <span><i class="fa-solid fa-id-card me-1 text-gold-dark" style="width: 16px;"></i>सदस्यता सं (DBA):</span>
                                <span class="fw-medium text-dark-custom"><?php echo e($bearer['membership_no_snapshot'] ?: 'N/A'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span><i class="fa-solid fa-certificate me-1 text-gold-dark" style="width: 16px;"></i>पंजीकरण (COP):</span>
                                <span class="fw-medium text-dark-custom"><?php echo e($bearer['enrollment_no_snapshot'] ?: 'N/A'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><i class="fa-solid fa-calendar-days me-1 text-gold-dark" style="width: 16px;"></i>सत्र (Term):</span>
                                <span class="fw-medium text-dark-custom"><?php echo e($active_term['title']); ?></span>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mb-5">
            <a href="executive-committee.php" class="btn btn-gold text-navy-custom fw-bold px-4 me-2"><i class="fa-solid fa-users me-1"></i>पूर्ण कार्यकारिणी विवरण (Full Committee)</a>
            <a href="office-bearers/history.php" class="btn btn-outline-navy px-4"><i class="fa-solid fa-clock-rotate-left me-1"></i>विगत समितियां (Historical Committees)</a>
        </div>
    <?php endif; ?>
</div>

<?php 
require_once 'includes/footer.php';
?>
