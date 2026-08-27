<?php
/**
 * Admin ID Card Application Detail, Preview & Status Workflow Actions
 * District Bar Association, Banda
 */

$pageTitle = 'पहचान पत्र समीक्षा (ID Card Review)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin/mahasachiv role
requireRole(['admin', 'mahasachiv']);

// Include local QR Code library
require_once __DIR__ . '/../../libraries/phpqrcode/qrlib.php';

$app_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$app = null;
$member = null;
$existing_card = null;
$new_card = null;

$db = Database::getConnection();

if ($db && $app_id > 0) {
    try {
        // Fetch application details
        $stmt = $db->prepare("
            SELECT a.*, m.id as m_id, m.full_name, m.membership_no, m.enrollment_no, m.membership_status, 
                   m.father_or_husband_name, m.blood_group, m.member_since, m.membership_category, m.photo as m_photo, m.signature as m_signature
            FROM id_card_applications a
            JOIN members m ON m.id = a.member_id
            WHERE a.id = ?
        ");
        $stmt->execute([$app_id]);
        $app = $stmt->fetch();
        
        if ($app) {
            // Fetch connected existing card if any
            if ($app['existing_card_id']) {
                $c_stmt = $db->prepare("SELECT * FROM id_cards WHERE id = ?");
                $c_stmt->execute([$app['existing_card_id']]);
                $existing_card = $c_stmt->fetch();
            }

            // Fetch newly generated card if already approved/generated
            $nc_stmt = $db->prepare("
                SELECT * FROM id_cards 
                WHERE member_id = ? AND application_type = ? AND status != 'cancelled'
                ORDER BY id DESC LIMIT 1
            ");
            $nc_stmt->execute([$app['member_id'], $app['application_type']]);
            $new_card = $nc_stmt->fetch();
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch application review: " . $e->getMessage());
    }
}

if (!$app) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: पहचान पत्र आवेदन नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit();
}

// Handle workflow actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect("view.php?id=$app_id");
    }

    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    
    if ($db) {
        try {
            $db->beginTransaction();
            $user_id = $_SESSION['auth']['user_id'];
            
            if ($action === 'approve') {
                // Determine photo and signature to use
                $final_photo = $app['photo_path'] ?: $app['m_photo'];
                $final_signature = $app['signature_path'] ?: $app['m_signature'];
                
                // 1. Generate unique Card Number & QR Token
                $card_number = generateIdCardNumber($db);
                $qr_token = bin2hex(random_bytes(24));
                
                // Validity
                $validity_months = defined('ID_CARD_VALIDITY_MONTHS') ? ID_CARD_VALIDITY_MONTHS : 24;
                $valid_from = date('Y-m-d');
                $valid_until = date('Y-m-d', strtotime("+$validity_months months"));
                
                // 2. Insert into id_cards table
                $ins_stmt = $db->prepare("
                    INSERT INTO id_cards (member_id, card_number, card_type, valid_from, valid_until, status, qr_token, photo_path, signature_path, application_type, requested_by, approved_by, approved_at) 
                    VALUES (?, ?, 'digital', ?, ?, 'ready', ?, ?, ?, ?, ?, ?, NOW())
                ");
                $ins_stmt->execute([
                    $app['m_id'],
                    $card_number,
                    $valid_from,
                    $valid_until,
                    $qr_token,
                    $final_photo,
                    $final_signature,
                    $app['application_type'],
                    $app['member_id'],
                    $user_id
                ]);
                $card_id = $db->lastInsertId();
                
                // 3. Update application status
                $up_stmt = $db->prepare("UPDATE id_card_applications SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), approval_remarks = ? WHERE id = ?");
                $up_stmt->execute([$user_id, $remarks ?: 'आवेदन स्वीकृत किया गया और कार्ड तैयार है।', $app_id]);
                
                // 4. Log in ID Card History
                $hist_stmt = $db->prepare("
                    INSERT INTO id_card_history (id_card_id, member_id, action, old_status, new_status, remarks, performed_by) 
                    VALUES (?, ?, 'Approved & Generated', NULL, 'ready', ?, ?)
                ");
                $hist_stmt->execute([$card_id, $app['m_id'], "कार्ड जनरेट किया गया: $card_number", $user_id]);
                
                $db->commit();
                setFlash('success', "पहचान पत्र सफलतापूर्वक स्वीकृत किया गया। कार्ड संख्या: $card_number");
                redirect("view.php?id=$app_id");
                
            } elseif ($action === 'reject') {
                $up_stmt = $db->prepare("UPDATE id_card_applications SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), approval_remarks = ? WHERE id = ?");
                $up_stmt->execute([$user_id, $remarks ?: 'आवेदन अस्वीकृत कर दिया गया।', $app_id]);
                
                $db->commit();
                setFlash('success', 'आवेदन अस्वीकृत किया गया।');
                redirect("view.php?id=$app_id");
                
            } elseif ($action === 'clarify') {
                $up_stmt = $db->prepare("UPDATE id_card_applications SET status = 'clarification_required', reviewed_by = ?, reviewed_at = NOW(), approval_remarks = ? WHERE id = ?");
                $up_stmt->execute([$user_id, $remarks ?: 'स्पष्टीकरण आवश्यक है।', $app_id]);
                
                $db->commit();
                setFlash('success', 'स्पष्टीकरण अनुरोध दर्ज किया गया।');
                redirect("view.php?id=$app_id");
                
            } elseif ($action === 'issue' && $new_card) {
                // Mark card as issued
                $up_card = $db->prepare("UPDATE id_cards SET status = 'issued', issued_by = ?, issued_at = NOW() WHERE id = ?");
                $up_card->execute([$user_id, $new_card['id']]);
                
                // Log history
                $hist_stmt = $db->prepare("
                    INSERT INTO id_card_history (id_card_id, member_id, action, old_status, new_status, remarks, performed_by) 
                    VALUES (?, ?, 'Issued', 'ready', 'issued', ?, ?)
                ");
                $hist_stmt->execute([$new_card['id'], $app['m_id'], "कार्ड धारक को भौतिक/डिजिटल रूप से प्रदान किया गया।", $user_id]);
                
                $db->commit();
                setFlash('success', 'पहचान पत्र जारी कर दिया गया है।');
                redirect("view.php?id=$app_id");
                
            } elseif ($action === 'print' && $new_card) {
                // Mark card as printed
                $up_card = $db->prepare("UPDATE id_cards SET status = 'printed', printed_at = NOW() WHERE id = ?");
                $up_card->execute([$new_card['id']]);
                
                // Log history
                $hist_stmt = $db->prepare("
                    INSERT INTO id_card_history (id_card_id, member_id, action, old_status, new_status, remarks, performed_by) 
                    VALUES (?, ?, 'Printed', 'ready', 'printed', ?, ?)
                ");
                $hist_stmt->execute([$new_card['id'], $app['m_id'], "कार्ड प्रिंटर रजिस्टर के लिए मुद्रित।", $user_id]);
                
                $db->commit();
                setFlash('success', 'कार्ड मुद्रित चिह्नित किया गया।');
                redirect("view.php?id=$app_id");
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed executing card workflow action: " . $e->getMessage());
            setFlash('danger', 'त्रुटि: ' . $e->getMessage());
        }
    }
}

// Generate QR Code cached image for live preview (if approved card exists)
$qr_webpath = '';
if ($new_card) {
    $qr_filename = 'qr_' . $new_card['qr_token'] . '.png';
    $qr_dir = __DIR__ . '/../../uploads/qrcodes/';
    $qr_filepath = $qr_dir . $qr_filename;
    $qr_webpath = SITE_URL . '/uploads/qrcodes/' . $qr_filename;

    if (!is_dir($qr_dir)) mkdir($qr_dir, 0755, true);
    if (!file_exists($qr_filepath)) {
        $verify_url = SITE_URL . '/verify-id-card.php?token=' . $new_card['qr_token'];
        QRcode::png($verify_url, $qr_filepath, QR_ECLEVEL_L, 3, 1);
    }
}
?>

<!-- Load ID Card CSS -->
<link rel="stylesheet" href="../../assets/css/id-card.css">

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi no-print">
    <h4 class="text-navy-custom fw-bold mb-0">पहचान पत्र समीक्षा पटल (ID Card Review Panel)</h4>
    <a href="index.php" class="btn btn-outline-navy btn-sm">कतार पर वापस जाएं</a>
</div>

<div class="row g-4 font-hindi small">
    <!-- Left Column: Application Details and Actions -->
    <div class="col-md-6 no-print">
        <div class="card p-3 border-0 shadow-sm rounded-3 mb-4">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-file-earmark-text me-1"></i>आवेदन पत्र विवरण</h6>
            
            <table class="table table-sm table-borderless text-muted mb-0">
                <tr>
                    <td class="ps-0" style="width: 130px;">अधिवक्ता का नाम:</td>
                    <td><strong class="text-navy-custom english-text"><?php echo e($app['full_name']); ?></strong></td>
                </tr>
                <tr>
                    <td class="ps-0">पिता का नाम:</td>
                    <td class="text-dark-custom"><?php echo e($app['father_or_husband_name']); ?></td>
                </tr>
                <tr>
                    <td class="ps-0">सदस्यता संख्या:</td>
                    <td><strong class="text-navy-custom english-text"><?php echo e($app['membership_no']); ?></strong></td>
                </tr>
                <tr>
                    <td class="ps-0">पंजीकरण संख्या:</td>
                    <td class="english-text text-dark-custom"><?php echo e($app['enrollment_no']); ?></td>
                </tr>
                <tr>
                    <td class="ps-0">सदस्यता श्रेणी:</td>
                    <td><span class="badge bg-light text-navy-custom border"><?php echo e($app['membership_category']); ?></span></td>
                </tr>
                <tr>
                    <td class="ps-0">आवेदन प्रकार:</td>
                    <td><strong class="text-primary-custom"><?php echo e(ucfirst($app['application_type'])); ?></strong></td>
                </tr>
                <tr>
                    <td class="ps-0">प्रस्तुत तिथि:</td>
                    <td class="english-text"><?php echo date('d-m-Y H:i', strtotime($app['submitted_at'])); ?></td>
                </tr>
                <?php if ($app['reason']): ?>
                    <tr>
                        <td class="ps-0">आवेदन का कारण:</td>
                        <td class="text-dark-custom bg-light p-2 rounded" style="font-size: 0.72rem;"><?php echo e($app['reason']); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Document Preview Uploaded in Application vs Master Profile -->
        <div class="card p-3 border-0 shadow-sm rounded-3 mb-4">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-image me-1"></i>दस्तावेज व फोटो सत्यापन</h6>
            <div class="row text-center">
                <div class="col-6 border-end">
                    <span class="text-muted d-block mb-1" style="font-size: 0.65rem;">मास्टर प्रोफाइल फ़ोटो:</span>
                    <img src="../../uploads/photos/<?php echo e($app['m_photo']); ?>" class="border rounded mb-2" style="width: 80px; height: 95px; object-fit: cover;" onerror="this.src='../../uploads/photos/default_advocate.png'">
                    
                    <?php if ($app['photo_path']): ?>
                        <span class="text-muted d-block mb-1" style="font-size: 0.65rem; color: #ffc107 !important;">आवेदन हेतु नई फ़ोटो:</span>
                        <img src="../../uploads/photos/<?php echo e($app['photo_path']); ?>" class="border border-warning rounded" style="width: 80px; height: 95px; object-fit: cover;">
                    <?php endif; ?>
                </div>
                <div class="col-6">
                    <span class="text-muted d-block mb-1" style="font-size: 0.65rem;">मास्टर हस्ताक्षर:</span>
                    <?php if ($app['m_signature']): ?>
                        <img src="../../uploads/signatures/<?php echo e($app['m_signature']); ?>" class="border rounded p-1 mb-2" style="width: 110px; height: 35px; object-fit: contain;">
                    <?php else: ?>
                        <div class="text-muted border rounded p-1 mb-2 bg-light" style="font-size:0.6rem; height:35px;">अपलोड नहीं</div>
                    <?php endif; ?>

                    <?php if ($app['signature_path']): ?>
                        <span class="text-muted d-block mb-1" style="font-size: 0.65rem; color: #ffc107 !important;">आवेदन नया हस्ताक्षर:</span>
                        <img src="../../uploads/signatures/<?php echo e($app['signature_path']); ?>" class="border border-warning rounded p-1" style="width: 110px; height: 35px; object-fit: contain;">
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- WORKFLOW ACTIONS CARD -->
        <div class="card p-3 border-0 shadow-sm rounded-3 border-top border-navy border-3">
            <h6 class="fw-bold text-navy-custom mb-3"><i class="bi bi-shield-check me-1"></i>कार्यप्रवाह कार्रवाई (Workflow Action)</h6>
            
            <?php if (in_array($app['status'], ['submitted', 'under_review'])): ?>
                <!-- Pending Approvals Form -->
                <form method="POST" action="view.php?id=<?php echo $app['id']; ?>">
                    <?php csrfField(); ?>
                    <div class="mb-3">
                        <label for="remarks" class="form-label text-secondary fw-semibold">टिप्पणी / रिमार्क (Remarks for applicant)</label>
                        <textarea class="form-control form-control-sm" id="remarks" name="remarks" rows="2" placeholder="समीक्षा टिप्पणी लिखें..."></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success flex-grow-1 fw-semibold" onclick="return confirm('क्या आप इस आवेदन को स्वीकृत कर नया कार्ड जनरेट करना चाहते हैं?');">
                            <i class="bi bi-check-circle-fill me-1"></i>स्वीकृत व जनरेट
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger fw-semibold" onclick="return confirm('क्या आप इस आवेदन को अस्वीकृत करना चाहते हैं?');">
                            अस्वीकृत
                        </button>
                        <button type="submit" name="action" value="clarify" class="btn btn-sm btn-warning text-dark fw-semibold">
                            स्पष्टीकरण मांगें
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <!-- Already Processed States -->
                <div class="alert alert-secondary border-0 mb-3 text-center">
                    इस आवेदन को <strong><?php echo ucfirst($app['status']); ?></strong> के रूप में निस्तारित किया जा चुका है।
                </div>
                
                <?php if ($new_card && in_array($new_card['status'], ['ready', 'printed'])): ?>
                    <form method="POST" action="view.php?id=<?php echo $app['id']; ?>" class="d-flex gap-2 justify-content-center">
                        <?php csrfField(); ?>
                        <button type="button" class="btn btn-sm btn-outline-navy fw-semibold" onclick="window.print();">
                            <i class="bi bi-printer-fill me-1"></i>कार्ड प्रिंट करें
                        </button>
                        <button type="submit" name="action" value="print" class="btn btn-sm btn-warning text-dark fw-semibold" onclick="return confirm('क्या आप कार्ड को मुद्रित चिह्नित करना चाहते हैं?');">
                            मुद्रित चिह्नित करें
                        </button>
                        <button type="submit" name="action" value="issue" class="btn btn-sm btn-success fw-semibold" onclick="return confirm('क्या आप इस कार्ड को सक्रिय/जारी करना चाहते हैं?');">
                            <i class="bi bi-patch-check-fill me-1"></i>कार्ड जारी (Issue Card)
                        </button>
                    </form>
                <?php elseif ($new_card && $new_card['status'] === 'issued'): ?>
                    <div class="alert alert-success border-0 text-center mb-0">
                        <i class="bi bi-shield-fill-check me-1"></i>पहचान पत्र सक्रिय है और सदस्य को जारी किया जा चुका है।
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Live Card Preview -->
    <div class="col-md-6 d-flex flex-column align-items-center">
        <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3 w-100 no-print text-center"><i class="bi bi-card-image me-1"></i>आईडी कार्ड प्रीव्यू (Live Card Preview)</h6>
        
        <?php if ($new_card): ?>
            <!-- Renders dynamically the front side -->
            <div class="id-card-wrapper id-card-print-target mb-3">
                <div class="id-card-container">
                    <div class="id-card-header">
                        <img src="../../assets/images/logo.png" alt="Logo" class="id-card-logo" onerror="this.src='../../includes/dashboard/logo-placeholder.png'">
                        <div>
                            <h5 class="id-card-title-hi">जिला अधिवक्ता संघ, बांदा</h5>
                            <h6 class="id-card-title-en">DISTRICT BAR ASSOCIATION, BANDA (U.P.)</h6>
                            <span class="id-card-estd">Estd. 1937 | Registered Advocate Credential</span>
                        </div>
                    </div>
                    
                    <div class="id-card-body">
                        <div class="id-card-left">
                            <img src="../../uploads/photos/<?php echo e($new_card['photo_path'] ?: $app['m_photo']); ?>" alt="Photo" class="id-card-photo" onerror="this.src='../../uploads/photos/default_advocate.png'">
                            <span class="id-card-category-badge"><?php echo e($app['membership_category']); ?></span>
                        </div>
                        
                        <div class="id-card-right">
                            <div class="id-card-row">
                                <span class="id-card-label">NAME:</span>
                                <span class="id-card-value d-block english-text"><?php echo e($app['full_name']); ?></span>
                            </div>
                            <div class="id-card-row">
                                <span class="id-card-label">MEMB NO:</span>
                                <span class="id-card-value english-text"><?php echo e($app['membership_no']); ?></span>
                            </div>
                            <div class="id-card-row">
                                <span class="id-card-label">ENROLL NO:</span>
                                <span class="id-card-value english-text"><?php echo e($app['enrollment_no']); ?></span>
                            </div>
                            <div class="id-card-row">
                                <span class="id-card-label">VALIDITY:</span>
                                <span class="id-card-value text-success font-size-xs english-text">
                                    <?php echo date('M Y', strtotime($new_card['valid_from'])); ?> - <?php echo date('M Y', strtotime($new_card['valid_until'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="id-card-footer">
                        <div class="id-card-qr-box">
                            <img src="<?php echo $qr_webpath; ?>" alt="QR Verification">
                        </div>
                        <div class="id-card-signature-box">
                            <div class="id-card-signature-placeholder"></div>
                            <span>MAHASACHIV SIGN</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back Side Preview -->
            <div class="id-card-wrapper id-card-print-target">
                <div class="id-card-container back-side">
                    <div class="id-card-back-title font-hindi">सदस्यता नियम व शर्तें (DBA Rules)</div>
                    <div class="id-card-back-terms font-hindi">
                        1. यह कार्ड जिला अधिवक्ता संघ, बांदा की संपत्ति है।<br>
                        2. न्यायालय परिसर एवं आधिकारिक कार्यक्रमों में इसे धारण करना अनिवार्य है।<br>
                        3. कार्ड खो जाने या चोरी होने पर तुरंत बार कार्यालय को सूचित करें।<br>
                        4. इस कार्ड का दुरुपयोग दंडनीय विधिक अनुशासनात्मक कार्रवाई के अधीन है।
                    </div>
                    
                    <div class="id-card-back-meta font-hindi small text-navy-custom border-top pt-2 mb-2">
                        <div class="row">
                            <div class="col-6">
                                <span class="text-muted" style="font-size: 0.6rem;">रक्त समूह (Blood):</span>
                                <strong class="d-block"><?php echo e($app['blood_group'] ?: 'N/A'); ?></strong>
                            </div>
                            <div class="col-6 text-end">
                                <span class="text-muted" style="font-size: 0.6rem;">सदस्य सिंस (Since):</span>
                                <strong class="d-block english-text"><?php echo date('Y', strtotime($app['member_since'])); ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <div class="id-card-back-address font-hindi">
                        <strong>कार्यालय:</strong> जिला अधिवक्ता संघ भवन, कलेक्ट्रेट परिसर, बांदा (U.P.) - 210001
                        <span class="d-block english-text" style="font-size: 0.5rem; margin-top:2px;">Verify at: <?php echo SITE_URL; ?>/verify-id-card.php</span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info border-0 font-hindi py-5 text-center w-100 no-print">
                <i class="bi bi-credit-card-2-front display-5 d-block mb-3 text-muted"></i>
                आवेदन स्वीकृत होने के उपरांत ही लाइव कार्ड प्रीव्यू प्रदर्शित होगा।
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
