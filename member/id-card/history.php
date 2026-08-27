<?php
/**
 * Member ID Card Applications & Cards History
 * District Bar Association, Banda
 */

$pageTitle = 'पहचान पत्र इतिहास (ID Card History)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce member role
requireRole('member');

$user = currentUser();
$member_id = $user['member_id'] ?? 0;

$applications = [];
$cards = [];

$db = Database::getConnection();
if ($db && $member_id > 0) {
    try {
        // Fetch applications
        $app_stmt = $db->prepare("SELECT * FROM id_card_applications WHERE member_id = ? ORDER BY id DESC");
        $app_stmt->execute([$member_id]);
        $applications = $app_stmt->fetchAll();

        // Fetch cards
        $card_stmt = $db->prepare("SELECT * FROM id_cards WHERE member_id = ? ORDER BY id DESC");
        $card_stmt->execute([$member_id]);
        $cards = $card_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch member card history: " . $e->getMessage());
    }
}

$status_labels = [
    'submitted' => 'प्रस्तुत किया गया (Submitted)',
    'under_review' => 'समीक्षा में (Under Review)',
    'clarification_required' => 'स्पष्टीकरण अपेक्षित (Clarification Required)',
    'approved' => 'स्वीकृत (Approved)',
    'rejected' => 'अस्वीकृत (Rejected)',
    'generated' => 'जनरेटेड (Generated)'
];

$card_status_labels = [
    'draft' => 'ड्राफ्ट',
    'requested' => 'अनुरोधित',
    'under_review' => 'समीक्षा में',
    'approved' => 'स्वीकृत',
    'rejected' => 'अस्वीकृत',
    'generated' => 'जनरेटेड',
    'printed' => 'मुद्रित',
    'ready' => 'तैयार',
    'issued' => 'सक्रिय / जारी (Issued)',
    'expired' => 'समाप्त (Expired)',
    'cancelled' => 'निरस्त (Cancelled)'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>पहचान पत्र आवेदन एवं कार्ड इतिहास (My ID History)</h4>
    <a href="../id-card.php" class="btn btn-outline-navy btn-sm">वापस आईडी पटल पर जाएं</a>
</div>

<div class="row g-4 font-hindi small">
    <!-- Section 1: Issued Cards -->
    <div class="col-md-6">
        <div class="border rounded p-3 bg-white shadow-sm h-100">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-card-image text-gold-dark me-2"></i>जारी किए गए कार्ड (Issued Cards)</h6>
            
            <?php if (empty($cards)): ?>
                <div class="text-center py-4 text-muted">कोई कार्ड जारी नहीं मिला।</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($cards as $c): ?>
                        <div class="p-2 border rounded bg-light-custom position-relative">
                            <span class="fw-bold text-navy-custom d-block english-text mb-1"><?php echo e($c['card_number']); ?></span>
                            <div class="row text-muted" style="font-size: 0.72rem;">
                                <div class="col-6">
                                    <span>वैधता प्रारंभ:</span>
                                    <strong class="d-block english-text"><?php echo date('d-m-Y', strtotime($c['valid_from'])); ?></strong>
                                </div>
                                <div class="col-6">
                                    <span>वैधता समाप्ति:</span>
                                    <strong class="d-block english-text"><?php echo date('d-m-Y', strtotime($c['valid_until'])); ?></strong>
                                </div>
                            </div>
                            
                            <!-- Badges -->
                            <div class="d-flex gap-2 align-items-center justify-content-between mt-2 pt-2 border-top border-light">
                                <span>प्रकार: <strong class="english-text"><?php echo e(ucfirst($c['card_type'])); ?></strong></span>
                                <?php 
                                $status = $c['status'];
                                $badge = ($status === 'issued') ? 'bg-success' : (($status === 'cancelled') ? 'bg-danger' : 'bg-secondary');
                                ?>
                                <span class="badge <?php echo $badge; ?> font-size-xs px-2 py-0.5">
                                    <?php echo e($card_status_labels[$status] ?? $status); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section 2: Application Submissions -->
    <div class="col-md-6">
        <div class="border rounded p-3 bg-white shadow-sm h-100">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-file-earmark-arrow-up-fill text-gold-dark me-2"></i>आवेदन अनुरोध इतिहास (Applications)</h6>
            
            <?php if (empty($applications)): ?>
                <div class="text-center py-4 text-muted">कोई आवेदन अनुरोध नहीं मिला।</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($applications as $a): ?>
                        <div class="p-2 border rounded bg-light-custom">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div>
                                    <span class="badge bg-navy-custom text-white font-size-xs px-2 py-0.5 mb-1"><?php echo e(ucfirst($a['application_type'])); ?></span>
                                    <span class="text-muted d-block" style="font-size: 0.65rem;">प्रस्तुत तिथि: <?php echo date('d-m-Y H:i', strtotime($a['submitted_at'])); ?></span>
                                </div>
                                <div>
                                    <?php 
                                    $st = $a['status'];
                                    $b = ($st === 'submitted') ? 'bg-info' : (($st === 'approved') ? 'bg-success' : (($st === 'rejected') ? 'bg-danger' : 'bg-warning text-dark'));
                                    ?>
                                    <span class="badge <?php echo $b; ?> font-size-xs px-2 py-0.5">
                                        <?php echo e($status_labels[$st] ?? $st); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if (!empty($a['reason'])): ?>
                                <div class="text-muted mt-1" style="font-size: 0.72rem; line-height: 1.3;">
                                    <strong>कारण:</strong> <?php echo e($a['reason']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($a['approval_remarks'])): ?>
                                <div class="p-1 bg-white border rounded text-danger mt-1" style="font-size: 0.65rem;">
                                    <strong>रिमार्क:</strong> <?php echo e($a['approval_remarks']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
