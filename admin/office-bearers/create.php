<?php
/**
 * Create/Assign Office Bearer Form
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce admin/mahasachiv roles
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

// AJAX Search Endpoint
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    $q = sanitize($_GET['q'] ?? '');
    $res = [];
    if ($db && !empty($q)) {
        try {
            $stmt = $db->prepare("
                SELECT id, full_name, membership_no, enrollment_no, photo, membership_status 
                FROM members 
                WHERE (full_name LIKE ? OR membership_no LIKE ? OR enrollment_no LIKE ?)
                LIMIT 10
            ");
            $sp = "%$q%";
            $stmt->execute([$sp, $sp, $sp]);
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}
    }
    echo json_encode($res);
    exit;
}

$pageTitle = 'नया पदाधिकारी नियुक्त करें (Assign Bearer)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

$error = '';
$success = '';

// Check position holders and multiple position warnings (Requirement 9 & 10)
$warn_max_holders = false;
$warn_multiple_roles = false;
$warn_inactive_member = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $term_id = intval($_POST['term_id'] ?? 0);
        $position_id = intval($_POST['position_id'] ?? 0);
        $member_id = intval($_POST['member_id'] ?? 0);
        $start_date = sanitize($_POST['start_date'] ?? '');
        $end_date = !empty($_POST['end_date']) ? sanitize($_POST['end_date']) : null;
        $display_order = intval($_POST['display_order'] ?? 1);
        $message = sanitize(trim($_POST['message'] ?? ''));
        $status = sanitize($_POST['status'] ?? 'active');

        // Confirmations
        $confirm_inactive = isset($_POST['confirm_inactive']) ? 1 : 0;
        $confirm_max_holders = isset($_POST['confirm_max_holders']) ? 1 : 0;
        $confirm_multiple = isset($_POST['confirm_multiple']) ? 1 : 0;

        try {
            if ($term_id <= 0 || $position_id <= 0 || $member_id <= 0 || empty($start_date)) {
                throw new Exception('कृपया कार्यकाल, पद, अधिवक्ता सदस्य एवं प्रारंभ तिथि दर्ज करें।');
            }

            // Fetch member details
            $m_stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
            $m_stmt->execute([$member_id]);
            $member = $m_stmt->fetch();
            if (!$member) {
                throw new Exception('सदस्य मास्टर रिकॉर्ड नहीं मिला।');
            }

            // A. Warn if member status is not active (Requirement 6)
            if ($member['membership_status'] !== 'active' && !$confirm_inactive) {
                $warn_inactive_member = true;
                throw new Exception("चेतावनी: इस अधिवक्ता की सदस्यता की स्थिति '{$member['membership_status']}' है (सक्रिय नहीं है)।");
            }

            // B. Conflict Check: Max Holders (Requirement 9)
            $pos_stmt = $db->prepare("SELECT * FROM office_bearer_positions WHERE id = ?");
            $pos_stmt->execute([$position_id]);
            $pos = $pos_stmt->fetch();
            if ($pos) {
                // Count current active holders of this position in this term
                $hold_stmt = $db->prepare("SELECT COUNT(*) FROM office_bearers WHERE term_id = ? AND position_id = ? AND status = 'active'");
                $hold_stmt->execute([$term_id, $position_id]);
                $current_holders = $hold_stmt->fetchColumn();

                if ($current_holders >= $pos['max_holders'] && !$confirm_max_holders) {
                    $warn_max_holders = true;
                    throw new Exception("चेतावनी: इस पद ({$pos['position_name_hindi']}) हेतु पूर्व से ही अधिकतम स्वीकृत सीमा ({$pos['max_holders']}) के पदाधिकारी नियुक्त हैं।");
                }
            }

            // C. Conflict Check: Same member holding multiple positions in same term (Requirement 10)
            $mult_stmt = $db->prepare("SELECT COUNT(*) FROM office_bearers WHERE term_id = ? AND member_id = ? AND status = 'active'");
            $mult_stmt->execute([$term_id, $member_id]);
            $member_holdings = $mult_stmt->fetchColumn();

            if ($member_holdings > 0 && !$confirm_multiple) {
                $warn_multiple_roles = true;
                throw new Exception("चेतावनी: यह सदस्य इस कार्यकारिणी कार्यकाल में पहले से ही एक अन्य सक्रिय पद पर नियुक्त है।");
            }

            // Insert into Database
            $db->beginTransaction();
            $ins = $db->prepare("
                INSERT INTO office_bearers 
                (term_id, position_id, member_id, display_name_snapshot, photo_snapshot, 
                 membership_no_snapshot, enrollment_no_snapshot, designation_override, message, 
                 display_order, start_date, end_date, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $term_id, $position_id, $member_id, $member['full_name'], $member['photo'] ?: 'default_advocate.png',
                $member['membership_no'], $member['enrollment_no'], $message,
                $display_order, $start_date, $end_date, $status, $_SESSION['user_id']
            ]);
            $new_ob_id = $db->lastInsertId();

            // Save history
            $hist = $db->prepare("
                INSERT INTO office_bearer_history (office_bearer_id, action, new_status, remarks, performed_by)
                VALUES (?, 'Assigned', ?, 'Assigned office bearer position.', ?)
            ");
            $hist->execute([$new_ob_id, $status, $_SESSION['user_id']]);

            // Save Member History
            $m_hist = $db->prepare("
                INSERT INTO member_history (member_id, action, remarks, performed_by)
                VALUES (?, 'Assigned Office Bearer', ?, ?)
            ");
            $m_hist->execute([$member_id, "Assigned to position ID {$position_id} in Term ID {$term_id}.", $_SESSION['user_id']]);

            $db->commit();
            $_SESSION['flash_success'] = 'कार्यकारिणी पदाधिकारी सफलतापूर्वक नियुक्त किया गया।';
            header("Location: index.php");
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

// Fetch terms and positions for dropdowns
$terms = [];
$positions = [];
if ($db) {
    try {
        $terms = $db->query("SELECT id, title, status FROM office_bearer_terms ORDER BY id DESC")->fetchAll();
        $positions = $db->query("SELECT id, position_name, position_name_hindi FROM office_bearer_positions WHERE status = 'active' ORDER BY display_order ASC")->fetchAll();
    } catch (PDOException $e) {}
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">पदाधिकारी नियुक्ति प्रविष्टि (Assign Bearer Position)</h4>
    <a href="index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>पदाधिकारी सूची</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="row g-4 font-hindi small text-navy-custom">
    <div class="col-lg-8 mx-auto">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold">पदाधिकारी नियुक्ति फॉर्म (Executive Committee Assignment)</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="bearerForm">
                    <?php insertCSRF(); ?>

                    <!-- Override Confirmations -->
                    <?php if ($warn_inactive_member): ?>
                        <input type="hidden" name="confirm_inactive" value="1">
                    <?php endif; ?>
                    <?php if ($warn_max_holders): ?>
                        <input type="hidden" name="confirm_max_holders" value="1">
                    <?php endif; ?>
                    <?php if ($warn_multiple_roles): ?>
                        <input type="hidden" name="confirm_multiple" value="1">
                    <?php endif; ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">कार्यकाल (Term) *</label>
                            <select name="term_id" class="form-select form-select-sm" required>
                                <?php foreach ($terms as $t): ?>
                                    <option value="<?php echo $t['id']; ?>" <?php echo $t['status'] === 'active' ? 'selected' : ''; ?>>
                                        <?php echo e($t['title']); ?> (<?php echo e($t['status']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">संवैधानिक पद (Position) *</label>
                            <select name="position_id" class="form-select form-select-sm" required>
                                <option value="">-- पद चुनें --</option>
                                <?php foreach ($positions as $p): ?>
                                    <option value="<?php echo $p['id']; ?>">
                                        <?php echo e($p['position_name_hindi']); ?> (<?php echo e($p['position_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Member Master Search (Vanilla AJAX) -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">अधिवक्ता सदस्य खोजें (Search Advocate Member) *</label>
                        <div class="position-relative">
                            <input type="text" id="memberSearch" class="form-control form-control-sm" placeholder="अधिवक्ता का नाम, सदस्यता कोड (DBA No.) या पंजीकरण क्र. दर्ज करें..." autocomplete="off">
                            <div id="searchResults" class="list-group position-absolute w-100 shadow-sm z-3 d-none" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        <input type="hidden" name="member_id" id="memberId" required>
                    </div>

                    <!-- Selected Member Preview Panel -->
                    <div id="memberPreview" class="p-3 bg-light rounded border mb-3 d-none">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <img id="prevPhoto" src="" class="rounded border" style="width: 60px; height: 60px; object-fit: cover;">
                            </div>
                            <div class="col">
                                <h6 id="prevName" class="fw-bold mb-1"></h6>
                                <span class="d-block text-muted" style="font-size:0.75rem;">
                                    <strong>DBA Code:</strong> <span id="prevDba"></span> | 
                                    <strong>COP No:</strong> <span id="prevCop"></span>
                                </span>
                                <span class="badge bg-success font-size-xs mt-1" id="prevStatus"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">कार्यकाल प्रारंभ तिथि *</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">कार्यकाल समाप्ति तिथि (Optional)</label>
                            <input type="date" name="end_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">वरीयता क्रम (Display Order) *</label>
                            <input type="number" name="display_order" class="form-control form-control-sm" required value="1" min="1">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">स्थिति (Status) *</label>
                        <select name="status" class="form-select form-select-sm" required>
                            <option value="active">Active (सक्रिय)</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">अधिकारी का संदेश (Optional Message)</label>
                        <textarea name="message" class="form-control form-control-sm" rows="3" placeholder="उदा. अधिवक्ता हितों के संरक्षण हेतु अध्यक्ष की ओर से संदेश..."></textarea>
                    </div>

                    <?php if ($warn_inactive_member || $warn_max_holders || $warn_multiple_roles): ?>
                        <div class="alert alert-warning py-2 mb-3">
                            <strong>पुष्टिकरण की आवश्यकता:</strong> कृपया ऊपर दी गई चेतावनी की समीक्षा करें। यदि आप इस नियुक्ति को जारी रखना चाहते हैं, तो नीचे बटन पर पुनः क्लिक करें।
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>बाध्यकारी सहेजें (Force Save Assignment)</button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-navy btn-sm w-100 fw-bold"><i class="bi bi-check-circle-fill me-1"></i>नियुक्ति सहेजें</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const memberSearch = document.getElementById('memberSearch');
    const searchResults = document.getElementById('searchResults');
    const memberId = document.getElementById('memberId');
    
    const memberPreview = document.getElementById('memberPreview');
    const prevPhoto = document.getElementById('prevPhoto');
    const prevName = document.getElementById('prevName');
    const prevDba = document.getElementById('prevDba');
    const prevCop = document.getElementById('prevCop');
    const prevStatus = document.getElementById('prevStatus');

    let debounceTimer;

    memberSearch.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('d-none');
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`create.php?ajax=1&q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    searchResults.innerHTML = '';
                    if (data.length === 0) {
                        searchResults.innerHTML = '<div class="list-group-item text-muted">कोई सदस्य नहीं मिला।</div>';
                        searchResults.classList.remove('d-none');
                        return;
                    }

                    data.forEach(item => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action text-start';
                        btn.innerHTML = `<strong>${item.full_name}</strong> (${item.membership_no}) | COP: ${item.enrollment_no}`;
                        
                        btn.addEventListener('click', () => {
                            // Populate fields
                            memberId.value = item.id;
                            memberSearch.value = `${item.full_name} (${item.membership_no})`;
                            searchResults.classList.add('d-none');

                            // Update Preview (Requirement 8)
                            prevPhoto.src = `../../uploads/profile/${item.photo || 'default_advocate.png'}`;
                            prevName.textContent = item.full_name;
                            prevDba.textContent = item.membership_no;
                            prevCop.textContent = item.enrollment_no;
                            prevStatus.textContent = item.membership_status.toUpperCase();

                            if (item.membership_status === 'active') {
                                prevStatus.className = 'badge bg-success font-size-xs mt-1';
                            } else {
                                prevStatus.className = 'badge bg-danger font-size-xs mt-1';
                            }

                            memberPreview.classList.remove('d-none');
                        });

                        searchResults.appendChild(btn);
                    });

                    searchResults.classList.remove('d-none');
                });
        }, 300);
    });

    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== memberSearch && e.target !== searchResults) {
            searchResults.classList.add('d-none');
        }
    });
});
</script>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
