<?php
/**
 * President ID Card Application View & Approval Submission
 * District Bar Association, Banda
 */

$pageTitle = 'पहचान पत्र समीक्षा (ID Card Review)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce president role
requireRole('president');

$app_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$app = null;
$new_card = null;

$db = Database::getConnection();
$require_approval = defined('ID_CARD_REQUIRE_PRESIDENT_APPROVAL') ? ID_CARD_REQUIRE_PRESIDENT_APPROVAL : false;

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
            // Fetch newly generated card if exists
            $nc_stmt = $db->prepare("
                SELECT * FROM id_cards 
                WHERE member_id = ? AND application_type = ? AND status != 'cancelled'
                ORDER BY id DESC LIMIT 1
            ");
            $nc_stmt->execute([$app['member_id'], $app['application_type']]);
            $new_card = $nc_stmt->fetch();
        }
    } catch (PDOException $e) {
        error_log("President card fetch error: " . $e->getMessage());
    }
}

if (!$app) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: पहचान पत्र आवेदन नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit();
}

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $require_approval) {
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
                // Determined photo/signature
                $final_photo = $app['photo_path'] ?: $app['m_photo'];
                $final_signature = $app['signature_path'] ?: $app['m_signature'];
                
                // Generate Card & QR
                require_once __DIR__ . '/../../includes/functions.php';
                $card_number = generateIdCardNumber($db);
                $qr_token = bin2hex(random_bytes(24));
                
                $validity_months = defined('ID_CARD_VALIDITY_MONTHS') ? ID_CARD_VALIDITY_MONTHS : 24;
                $valid_from = date('Y-m-d');
                $valid_until = date('Y-m-d', strtotime("+$validity_months months"));

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

                $up_stmt = $db->prepare("UPDATE id_card_applications SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), approval_remarks = ? WHERE id = ?");
                $up_stmt->execute([$user_id, $remarks ?: 'अध्यक्ष द्वारा अनुमोदित।', $app_id]);

                // Log history
                $hist_stmt = $db->prepare("
                    INSERT INTO id_card_history (id_card_id, member_id, action, old_status, new_status, remarks, performed_by) 
                    VALUES (?, ?, 'President Approved', NULL, 'ready', ?, ?)
                ");
                $hist_stmt->execute([$card_id, $app['m_id'], "अध्यक्षीय अनुमोदन संपन्न।", $user_id]);

                $db->commit();
                setFlash('success', "पहचान पत्र सफलतापूर्वक अनुमोदित किया गया। कार्ड संख्या: $card_number");
                redirect("view.php?id=$app_id");

            } elseif ($action === 'reject') {
                $up_stmt = $db->prepare("UPDATE id_card_applications SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), approval_remarks = ? WHERE id = ?");
                $up_stmt->execute([$user_id, $remarks ?: 'अध्यक्ष द्वारा अस्वीकृत।', $app_id]);

                $db->commit();
                setFlash('success', 'आवेदन अध्यक्ष द्वारा अस्वीकृत किया गया।');
                redirect("view.php?id=$app_id");
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("President workflow error: " . $e->getMessage());
            setFlash('danger', 'त्रुटि: ' . $e->getMessage());
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">पहचान पत्र समीक्षा (President Detail View)</h4>
    <a href="index.php" class="btn btn-outline-navy btn-sm">वापस जाएं</a>
</div>

<div class="row g-4 font-hindi small">
    <!-- Left Column: Details -->
    <div class="col-md-6">
        <div class="card p-3 border-0 shadow-sm rounded-3 mb-4">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-file-earmark-text me-1"></i>विवरण विवरण</h6>
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
                    <td class="ps-0">आवेदन प्रकार:</td>
                    <td><strong class="text-primary-custom"><?php echo e(ucfirst($app['application_type'])); ?></strong></td>
                </tr>
                <tr>
                    <td class="ps-0">प्रस्तुत तिथि:</td>
                    <td class="english-text"><?php echo date('d-m-Y H:i', strtotime($app['submitted_at'])); ?></td>
                </tr>
                <?php if ($app['reason']): ?>
                    <tr>
                        <td class="ps-0">स्पष्टीकरण / कारण:</td>
                        <td class="text-dark-custom bg-light p-2 rounded" style="font-size: 0.72rem;"><?php echo e($app['reason']); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Master documents review -->
        <div class="card p-3 border-0 shadow-sm rounded-3 mb-4">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-image me-1"></i>दस्तावेज व फोटो विवरण</h6>
            <div class="row text-center">
                <div class="col-6 border-end">
                    <span class="text-muted d-block mb-1" style="font-size: 0.65rem;">सदस्य फोटो:</span>
                    <img src="../../uploads/photos/<?php echo e($app['photo_path'] ?: $app['m_photo']); ?>" class="border rounded mb-2" style="width: 80px; height: 95px; object-fit: cover;" onerror="this.src='../../uploads/photos/default_advocate.png'">
                </div>
                <div class="col-6">
                    <span class="text-muted d-block mb-1" style="font-size: 0.65rem;">सदस्य हस्ताक्षर:</span>
                    <?php if ($app['signature_path'] ?: $app['m_signature']): ?>
                        <img src="../../uploads/signatures/<?php echo e($app['signature_path'] ?: $app['m_signature']); ?>" class="border rounded p-1 mb-2" style="width: 110px; height: 35px; object-fit: contain;">
                    <?php else: ?>
                        <div class="text-muted border rounded p-1 mb-2 bg-light d-flex align-items-center justify-content-center" style="font-size:0.6rem; height:35px;">अपलोड नहीं</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Approval Box -->
        <?php if ($require_approval && in_array($app['status'], ['submitted', 'under_review'])): ?>
            <div class="card p-3 border-0 shadow-sm rounded-3 border-top border-success border-3">
                <h6 class="fw-bold text-success mb-3"><i class="bi bi-shield-check me-1"></i>अध्यक्षीय अनुमोदन कार्रवाई (Presidential Action)</h6>
                <form method="POST" action="view.php?id=<?php echo $app['id']; ?>">
                    <?php csrfField(); ?>
                    <div class="mb-3">
                        <label for="remarks" class="form-label text-secondary fw-semibold">अनुमोदन टिप्पणी (Remarks)</label>
                        <textarea class="form-control form-control-sm" id="remarks" name="remarks" rows="2" placeholder="टिप्पणी लिखें..."></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success flex-grow-1 fw-semibold" onclick="return confirm('क्या आप इस आवेदन को अनुमोदित कर नया कार्ड जनरेट करना चाहते हैं?');">
                            <i class="bi bi-check-circle-fill me-1"></i>अनुमोदित करें (Approve)
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger fw-semibold" onclick="return confirm('क्या आप इस आवेदन को अस्वीकृत करना चाहते हैं?');">
                            अस्वीकृत करें
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary border-0 mb-0 text-center">
                आवेदन स्थिति: <strong><?php echo ucfirst($app['status']); ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Card Preview -->
    <div class="col-md-6 d-flex flex-column align-items-center">
        <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3 w-100 text-center"><i class="bi bi-card-image me-1"></i>लाइव कार्ड विवरण</h6>
        
        <?php if ($new_card): ?>
            <!-- Card Front Preview -->
            <div class="id-card-wrapper mb-3">
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
                            <div class="bg-light w-100 h-100 d-flex align-items-center justify-content-center" style="font-size:0.5rem;">[QR Code]</div>
                        </div>
                        <div class="id-card-signature-box">
                            <div class="id-card-signature-placeholder"></div>
                            <span>MAHASACHIV SIGN</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info border-0 py-5 text-center w-100">
                <i class="bi bi-credit-card-2-front display-5 d-block mb-3 text-muted"></i>
                कार्ड स्वीकृत हो जाने के उपरांत ही लाइव प्रीव्यू विवरण उपलब्ध होगा।
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
