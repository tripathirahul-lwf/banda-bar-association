<?php
/**
 * Member Chamber Applications Status
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'कक्ष आवंटन आवेदन स्थिति (My Chamber Applications)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Ensure user has Member role
requireRole('member');

$db = Database::getConnection();
$member_id = $_SESSION['member_id'];
$applications = [];

if ($db && $member_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT a.*, c.chamber_no, c.block_name 
            FROM chamber_applications a
            LEFT JOIN chambers c ON c.id = a.preferred_chamber_id
            WHERE a.member_id = ?
            ORDER BY a.id DESC
        ");
        $stmt->execute([$member_id]);
        $applications = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading applications list: " . $e->getMessage());
    }
}

// Helper to determine status badges
$status_badges = [
    'submitted' => 'bg-info text-dark',
    'under_review' => 'bg-warning text-dark',
    'waiting_list' => 'bg-primary text-white',
    'approved' => 'bg-success text-white',
    'rejected' => 'bg-danger text-white',
    'withdrawn' => 'bg-secondary text-white',
    'allotted' => 'bg-success text-white'
];

$status_labels_hindi = [
    'submitted' => 'आवेदन प्राप्त (Submitted)',
    'under_review' => 'समीक्षा के अधीन (Under Review)',
    'waiting_list' => 'प्रतीक्षा सूची (Waiting List)',
    'approved' => 'स्वीकृत (Approved)',
    'rejected' => 'अस्वीकृत (Rejected)',
    'withdrawn' => 'वापस लिया गया (Withdrawn)',
    'allotted' => 'कक्ष आवंटित (Allotted)'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">आवेदन स्थिति एवं इतिहास (Application History)</h4>
    <a href="apply.php" class="btn btn-xs btn-navy"><i class="bi bi-file-earmark-plus me-1"></i>नया आवेदन दर्ज करें</a>
</div>

<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success py-2 font-hindi small">
        <?php 
        echo $_SESSION['flash_success']; 
        unset($_SESSION['flash_success']);
        ?>
    </div>
<?php endif; ?>

<div class="row g-4 font-hindi small text-navy-custom">
    <?php if (empty($applications)): ?>
        <div class="col-12">
            <div class="text-center py-5 bg-white border border-light rounded shadow-sm text-muted">
                <i class="bi bi-file-earmark-x fs-2 d-block mb-2 text-secondary"></i>
                कोई आवेदन पत्र प्राप्त नहीं हुआ है।
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($applications as $app): ?>
            <div class="col-12">
                <div class="card border-light shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2.5">
                        <strong class="english-text text-navy-custom fs-6">आवेदन संख्या: <?php echo e($app['application_no']); ?></strong>
                        <span class="badge <?php echo $status_badges[$app['status']] ?? 'bg-secondary'; ?>">
                            <?php echo $status_labels_hindi[$app['status']] ?? $app['status']; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-3">
                                <span class="text-muted d-block">आवेदन दिनांक:</span>
                                <strong class="english-text"><?php echo date('d-m-Y', strtotime($app['application_date'])); ?></strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted d-block">वरीयता (Preference):</span>
                                <strong class="english-text"><?php echo e($app['chamber_preference']); ?></strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted d-block">प्राथमिकता कमरा:</span>
                                <strong class="english-text">
                                    <?php echo $app['chamber_no'] ? 'Chamber ' . e($app['chamber_no']) . ' (' . e($app['block_name']) . ')' : 'सामान्य उपलब्धता'; ?>
                                </strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted d-block">प्रतीक्षा सूची क्रमांक:</span>
                                <strong class="english-text text-danger"><?php echo $app['priority_no'] ? '#' . $app['priority_no'] : 'लागू नहीं'; ?></strong>
                            </div>
                        </div>

                        <?php if (!empty($app['remarks'])): ?>
                            <div class="bg-light p-2 rounded mb-3 text-secondary" style="font-size:0.75rem;">
                                <strong>प्रशासकीय टिप्पणी (Remarks):</strong> <?php echo e($app['remarks']); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Simple Progress Timeline Visualizer -->
                        <h6 class="fw-bold border-bottom pb-1 mb-3 text-secondary" style="font-size:0.75rem;"><i class="bi bi-activity me-1"></i>समीक्षा प्रगति यात्रा (Application Timeline)</h6>
                        
                        <?php 
                        $status_steps = ['submitted', 'under_review', 'waiting_list', 'approved', 'allotted'];
                        $current_status = $app['status'];
                        
                        if ($current_status === 'rejected' || $current_status === 'withdrawn') {
                            $status_steps = ['submitted', 'under_review', $current_status];
                        }
                        
                        $active_idx = array_search($current_status, $status_steps);
                        if ($active_idx === false) $active_idx = 0;
                        ?>
                        
                        <div class="position-relative d-flex justify-content-between align-items-center my-4 ps-4 pe-4">
                            <div class="position-absolute top-50 start-0 translate-middle-y w-100 bg-secondary" style="height: 2px; z-index: 1; opacity:0.2;"></div>
                            
                            <?php foreach ($status_steps as $idx => $step): 
                                $is_done = ($idx <= $active_idx);
                                $color = $is_done ? 'bg-success text-white' : 'bg-light text-muted border';
                                $lbl = $status_labels_hindi[$step] ?? ucfirst($step);
                            ?>
                                <div class="text-center" style="z-index: 2; width: 120px;">
                                    <div class="rounded-circle <?php echo $color; ?> d-inline-flex align-items-center justify-content-center mb-1 shadow-xs" style="width: 28px; height: 28px;">
                                        <?php if ($is_done): ?>
                                            <i class="bi bi-check-lg" style="font-size:0.8rem;"></i>
                                        <?php else: ?>
                                            <span style="font-size:0.7rem;"><?php echo $idx + 1; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="d-block text-secondary-custom fw-semibold" style="font-size: 0.65rem; line-height: 1.2;"><?php echo $lbl; ?></span>
                                </div>
                            <?php endforeach; ?>
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
