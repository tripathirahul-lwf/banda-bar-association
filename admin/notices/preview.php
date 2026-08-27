<?php
/**
 * Admin Preview Notice Draft Page
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce staff roles
if (!isset($_SESSION['auth']) || !in_array($_SESSION['auth']['role'], ['admin', 'mahasachiv', 'president'])) {
    http_response_code(403);
    exit("Direct access forbidden. Unauthorized access.");
}

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$db = Database::getConnection();
$notice = null;

if ($db && !empty($slug)) {
    try {
        $stmt = $db->prepare("
            SELECT n.*, c.advocate_name, c.advocate_photo, c.date_of_death, c.prayer_meeting_details, c.family_message, c.condolence_message,
                   m.membership_no as linked_memb_no, m.enrollment_no as linked_enrol_no
            FROM notices n
            LEFT JOIN condolence_notices c ON c.notice_id = n.id
            LEFT JOIN members m ON m.id = c.member_id
            WHERE n.slug = ?
        ");
        $stmt->execute([$slug]);
        $notice = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed notice preview: " . $e->getMessage());
    }
}

if (!$notice) {
    exit("Notice slug not found.");
}

$pageTitle = '[PREVIEW] ' . $notice['title'];
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Noindex SEO Safeguard -->
<meta name="robots" content="noindex, nofollow">

<style>
    .watermark {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 8rem;
        color: rgba(220, 53, 69, 0.15);
        font-weight: bold;
        z-index: 9999;
        pointer-events: none;
        user-select: none;
        white-space: nowrap;
    }
</style>

<div class="watermark font-hindi">पूर्वावलोकन (PREVIEW)</div>

<nav class="navbar navbar-dark bg-danger py-2 no-print">
    <div class="container justify-content-center">
        <span class="navbar-text text-white fw-bold font-hindi">
            <i class="bi bi-eye-fill me-2"></i>यह अधिसूचना का अस्थायी पूर्वावलोकन (PREVIEW) मोड है। यह अभी तक प्रकाशित नहीं हुआ है।
        </span>
    </div>
</nav>

<div class="container py-5 font-hindi text-dark-custom">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            
            <div class="card p-4 shadow-sm border bg-white">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <div>
                        <span class="badge bg-light text-navy-custom border px-2 py-1 mb-1">
                            <?php echo e($notice['category']); ?>
                        </span>
                        <span class="small text-muted d-block english-text">संख्या: <?php echo e($notice['notice_no'] ?: 'Auto-Generate'); ?></span>
                    </div>
                    
                    <div class="text-end">
                        <small class="text-muted d-block english-text">प्रकाशन तिथि: <?php echo date('d-m-Y H:i', strtotime($notice['publish_at'] ?: $notice['created_at'])); ?></small>
                        <span class="badge bg-danger font-size-xs">स्थिति: <?php echo e($notice['status']); ?></span>
                    </div>
                </div>

                <?php if ($notice['category'] === 'Condolence Notice'): ?>
                    <!-- CONDOLENCE PREVIEW -->
                    <div class="text-center my-4">
                        <div class="condolence-ribbon mx-auto mb-3" style="width: 40px; height: 3px; background: #000;"></div>
                        <h2 class="fw-bold text-dark-custom mb-3">|| शोक संदेश ||</h2>
                        
                        <div class="mx-auto rounded border border-dark p-1 bg-white mb-3" style="width: 120px; height: 150px; overflow: hidden; filter: grayscale(100%);">
                            <?php if (!empty($notice['advocate_photo']) && $notice['advocate_photo'] !== 'default_advocate.png'): ?>
                                <img src="../../uploads/photos/<?php echo e($notice['advocate_photo']); ?>" alt="Photo" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <div class="d-flex flex-column align-items-center justify-content-center h-100 bg-light text-muted">
                                    <i class="bi bi-person-fill fs-1"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h4 class="fw-bold text-dark-custom text-decoration-underline mb-1"><?php echo e($notice['advocate_name']); ?></h4>
                        <p class="text-muted small mb-3">पंजीकरण संख्या: <?php echo e($notice['linked_enrol_no'] ?: ($notice['enrollment_no'] ?: 'N/A')); ?> | सदस्यता संख्या: <?php echo e($notice['linked_memb_no'] ?: ($notice['membership_no'] ?: 'N/A')); ?></p>
                        
                        <div class="p-3 border rounded bg-light-custom my-4" style="font-style: italic; font-size: 1.05rem; line-height: 1.8; text-align: justify;">
                            "<?php echo e($notice['description']); ?>"
                        </div>

                        <?php if (!empty($notice['prayer_meeting_details'])): ?>
                            <div class="p-3 border border-dark rounded bg-white text-start mb-3">
                                <h6 class="fw-bold text-navy-custom mb-2"><i class="bi bi-clock text-gold-dark me-2"></i>प्रार्थना सभा / श्रद्धांजलि विवरण:</h6>
                                <p class="mb-0 text-muted small"><?php echo e($notice['prayer_meeting_details']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- REGULAR NOTICE PREVIEW -->
                    <div class="my-4">
                        <h3 class="fw-bold text-navy-custom mb-3"><?php echo e($notice['title']); ?></h3>
                        <?php if (!empty($notice['title_hindi'])): ?>
                            <h5 class="text-secondary fw-semibold mb-4 border-bottom pb-2" style="font-size: 1.05rem;"><?php echo e($notice['title_hindi']); ?></h5>
                        <?php endif; ?>
                        
                        <div class="notice-description leading-relaxed mb-4 text-dark-custom" style="font-size: 0.95rem; text-align: justify; line-height: 1.8;">
                            <?php echo nl2br(e($notice['description'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="border-top pt-3 text-center no-print">
                    <button onclick="window.close();" class="btn btn-sm btn-navy px-4">पूर्वावलोकन बंद करें (Close Preview)</button>
                </div>
            </div>

        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/footer.php';
?>
