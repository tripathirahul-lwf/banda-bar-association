<?php
/**
 * Single Notice Detail Viewer Page
 * District Bar Association, Banda
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

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
        error_log("Failed to fetch notice detail: " . $e->getMessage());
    }
}

// Staff checks and publication timeline validations (Requirement 39)
$user = currentUser();
$role = $user['role'] ?? '';
$is_staff = in_array($role, ['admin', 'mahasachiv', 'president']);

if ($notice && !$is_staff) {
    $now = date('Y-m-d H:i:s');
    $is_published = ($notice['status'] === 'published');
    $is_started = ($notice['publish_at'] === null || $notice['publish_at'] <= $now);
    $is_not_expired = ($notice['expire_at'] === null || $notice['expire_at'] >= $now);
    
    if (!$is_published || !$is_started || !$is_not_expired) {
        $notice = null;
    }
}

if (!$notice) {
    $pageTitle = 'त्रुटि - सूचना उपलब्ध नहीं है';
    require_once 'includes/header.php';
    require_once 'includes/navbar.php';
    echo '<div class="container py-5 text-center font-hindi"><div class="alert alert-danger">दस्तावेज़ या सूचना रिकॉर्ड नहीं मिला।</div><a href="notices.php" class="btn btn-navy">सूचना पट्ट पर वापस जाएं</a></div>';
    require_once 'includes/footer.php';
    exit();
}

// Authorization check for Members Only notices
$is_authorized = true;
$user = currentUser();
if ($notice['visibility'] === 'members_only') {
    if (!isset($_SESSION['auth'])) {
        $is_authorized = false;
    }
}

$pageTitle = $notice['title'] . ' | जिला अधिवक्ता संघ, बांदा';
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// Write View tracking if authorized & logged in member
if ($is_authorized && isset($_SESSION['auth']) && $_SESSION['auth']['role'] === 'member' && $db) {
    try {
        $member_id = $_SESSION['auth']['member_id'] ?? 0;
        if ($member_id > 0) {
            $chk = $db->prepare("SELECT COUNT(*) FROM notice_views WHERE notice_id = ? AND member_id = ?");
            $chk->execute([$notice['id'], $member_id]);
            if ($chk->fetchColumn() == 0) {
                $ins = $db->prepare("INSERT INTO notice_views (notice_id, member_id) VALUES (?, ?)");
                $ins->execute([$notice['id'], $member_id]);
            }
        }
    } catch (PDOException $e) {
        error_log("Failed notice view tracking: " . $e->getMessage());
    }
}

$isCondolence = ($notice['category'] === 'Condolence Notice');
?>

<!-- Print-friendly stylesheet inclusion -->
<style>
    @media print {
        body {
            font-size: 12px;
            color: #000000;
            background: #ffffff;
        }
        .no-print, nav, footer, header, .navbar, .btn {
            display: none !important;
        }
        .print-box {
            border: 2px solid #000000;
            padding: 20px;
            margin: 0;
        }
        .print-header {
            text-align: center;
            border-bottom: 2px solid #000000;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
    }
</style>

<div class="row justify-content-center py-4 font-hindi">
    <div class="col-lg-8 col-md-10">
        
        <div class="mb-3 no-print">
            <a href="javascript:history.back();" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>वापस जाएं</a>
        </div>

        <?php if (!$is_authorized): ?>
            <!-- BLOCKED GUEST VISITOR -->
            <div class="card p-5 text-center border-0 shadow-sm rounded-3">
                <i class="bi bi-shield-lock display-1 text-danger mb-3 d-block"></i>
                <h4 class="fw-bold text-navy-custom">सामग्री सुरक्षित (Protected Content)</h4>
                <p class="text-muted mb-4 small">यह सूचना केवल बार संघ के सक्रिय एवं पंजीकृत सदस्यों के लिए सुरक्षित (Members Only) है। कृपया अपने सदस्य खाते से लॉगिन करें।</p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="login.php" class="btn btn-navy fw-semibold px-4"><i class="bi bi-box-arrow-in-right text-gold-custom me-2"></i>लॉगिन पोर्टल</a>
                    <a href="notices.php" class="btn btn-outline-navy fw-semibold px-4">सार्वजनिक सूचनाएं</a>
                </div>
            </div>
        <?php else: ?>
            
            <!-- Detail Content -->
            <div class="card p-4 shadow-sm border bg-white print-box">
                
                <!-- Print and Share Header (visible on paper/PDF print) -->
                <div class="d-none d-print-block print-header">
                    <h3>जिला अधिवक्ता संघ, बांदा (स्थापित: 1937)</h3>
                    <p>कार्यालय पट्ट आधिकारिक अधिसूचना</p>
                </div>

                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <div>
                        <span class="badge bg-light text-navy-custom border px-2 py-1 mb-1">
                            <?php echo e($notice['category']); ?>
                        </span>
                        <?php if ($notice['notice_no']): ?>
                            <span class="small text-muted d-block english-text">संख्या: <?php echo e($notice['notice_no']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-end">
                        <small class="text-muted d-block english-text">प्रकाशित: <?php echo date('d-m-Y H:i', strtotime($notice['published_at'] ?: $notice['created_at'])); ?></small>
                        <span class="badge <?php echo ($notice['priority'] === 'urgent') ? 'bg-danger text-white' : (($notice['priority'] === 'important') ? 'bg-warning text-dark' : 'bg-secondary text-white'); ?> font-size-xs">
                            Priority: <?php echo e($notice['priority']); ?>
                        </span>
                    </div>
                </div>

                <?php if ($isCondolence): ?>
                    <!-- RESPECTFUL CONDOLENCE DETAIL LAYOUT -->
                    <div class="text-center my-4 font-hindi">
                        <div class="condolence-ribbon mx-auto mb-3" style="width: 40px; height: 3px; background: #000;"></div>
                        <h2 class="fw-bold text-dark-custom mb-3">|| शोक संदेश ||</h2>
                        
                        <div class="mx-auto rounded border border-dark p-1 bg-white mb-3" style="width: 120px; height: 150px; overflow: hidden; filter: grayscale(100%);">
                            <?php if (!empty($notice['advocate_photo']) && $notice['advocate_photo'] !== 'default_advocate.png'): ?>
                                <img src="uploads/photos/<?php echo e($notice['advocate_photo']); ?>" alt="Photo" style="width:100%; height:100%; object-fit:cover;">
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

                        <?php if (!empty($notice['family_message'])): ?>
                            <div class="p-3 border border-light rounded bg-light text-start mb-3">
                                <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-chat-left-text me-2"></i>परिवार का संदेश:</h6>
                                <p class="mb-0 text-muted small italic">"<?php echo e($notice['family_message']); ?>"</p>
                            </div>
                        <?php endif; ?>

                        <span class="d-block text-muted mt-4">ॐ शांति ॐ शांति ॐ</span>
                    </div>
                <?php else: ?>
                    <!-- STANDARD NOTICE DETAIL -->
                    <div class="my-4 font-hindi">
                        <h3 class="fw-bold text-navy-custom mb-3"><?php echo e($notice['title']); ?></h3>
                        <?php if (!empty($notice['title_hindi'])): ?>
                            <h5 class="text-secondary fw-semibold mb-4 border-bottom pb-2" style="font-size: 1.05rem;"><?php echo e($notice['title_hindi']); ?></h5>
                        <?php endif; ?>
                        
                        <div class="notice-description leading-relaxed mb-4 text-dark-custom" style="font-size: 0.95rem; text-align: justify; line-height: 1.8;">
                            <?php echo nl2br(e($notice['description'])); ?>
                        </div>

                        <?php if (!empty($notice['attachment_path'])): ?>
                            <div class="p-3 border rounded bg-light-custom my-4 d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="fw-bold mb-1 text-navy-custom"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>आधिकारिक संलग्नक दस्तावेज़</h6>
                                    <span class="small text-muted english-text"><?php echo e($notice['attachment_name']); ?> (<?php echo strtoupper($notice['attachment_type']); ?>)</span>
                                </div>
                                <a href="<?php echo ($notice['visibility'] === 'members_only') ? 'member/notices/download.php?id=' . $notice['id'] : 'uploads/notices/' . $notice['attachment_path']; ?>" target="_blank" class="btn btn-sm btn-navy px-4">
                                    <i class="bi bi-download me-2"></i>डाउनलोड PDF
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="border-top pt-3 d-flex justify-content-between align-items-center no-print mt-4">
                    <span class="small text-muted font-hindi">प्रकाशक: <strong>जिला अधिवक्ता संघ, बांदा</strong></span>
                    <div class="d-flex gap-2">
                        <button onclick="window.print();" class="btn btn-xs btn-outline-navy"><i class="bi bi-printer-fill me-1"></i>प्रिंट करें</button>
                        <a href="notices.php" class="btn btn-xs btn-navy">सूचना सूची</a>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
