<?php
/**
 * Admin Executive Committee & Office Bearers Registry
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'कार्यकारिणी पदाधिकारी प्रबंधन (Office Bearers)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin/mahasachiv roles
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';

if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// 1. Retrieve Current Active Term
$active_term = null;
if ($db) {
    try {
        $stmt = $db->query("SELECT * FROM office_bearer_terms WHERE status = 'active' LIMIT 1");
        $active_term = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to load active term: " . $e->getMessage());
    }
}

// 2. Load Position filters
$positions_list = [];
if ($db) {
    try {
        $positions_list = $db->query("SELECT id, position_name, position_name_hindi FROM office_bearer_positions WHERE status = 'active' ORDER BY display_order ASC")->fetchAll();
    } catch (PDOException $e) {}
}

// 3. Load office bearers for the active term with filters
$bearers = [];
$filter_pos = isset($_GET['position_id']) ? intval($_GET['position_id']) : 0;
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

if ($db && $active_term) {
    try {
        $sql = "
            SELECT ob.*, p.position_name, p.position_name_hindi, p.code AS position_code, m.membership_status, u.role AS user_role, u.id AS user_uid
            FROM office_bearers ob
            JOIN office_bearer_positions p ON p.id = ob.position_id
            JOIN members m ON m.id = ob.member_id
            LEFT JOIN users u ON u.member_id = m.id
            WHERE ob.term_id = ?
        ";
        $params = [$active_term['id']];
        
        if ($filter_pos > 0) {
            $sql .= " AND ob.position_id = ?";
            $params[] = $filter_pos;
        }
        if (!empty($filter_status)) {
            $sql .= " AND ob.status = ?";
            $params[] = $filter_status;
        }
        
        $sql .= " ORDER BY p.display_order ASC, ob.display_order ASC, ob.id ASC";
        $b_stmt = $db->prepare($sql);
        $b_stmt->execute($params);
        $bearers = $b_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading office bearers: " . $e->getMessage());
    }
}

// 4. Role mismatch checks (Requirements 25 & 26)
$mismatches = [];
if ($db && $active_term) {
    try {
        // Query users table to find mismatch:
        // A. Users who are currently active President/Mahasachiv but have a different user role
        $stmtA = $db->prepare("
            SELECT ob.id AS bearer_id, m.full_name, ob.membership_no_snapshot, p.position_name, p.code AS position_code, u.role AS current_role, u.id AS user_uid
            FROM office_bearers ob
            JOIN office_bearer_positions p ON p.id = ob.position_id
            JOIN members m ON m.id = ob.member_id
            JOIN users u ON u.member_id = m.id
            WHERE ob.term_id = ? AND ob.status = 'active'
              AND p.code IN ('PRESIDENT', 'MAHASACHIV')
              AND ((p.code = 'PRESIDENT' AND u.role != 'president') OR (p.code = 'MAHASACHIV' AND u.role != 'mahasachiv'))
        ");
        $stmtA->execute([$active_term['id']]);
        while ($row = $stmtA->fetch()) {
            $mismatches[] = [
                'type' => 'upgrade',
                'name' => $row['full_name'],
                'code' => $row['membership_no_snapshot'],
                'bearer_id' => $row['bearer_id'],
                'user_uid' => $row['user_uid'],
                'position' => $row['position_name'],
                'target_role' => strtolower($row['position_code']),
                'current_role' => $row['current_role'],
                'message' => "चेतावनी: {$row['full_name']} ({$row['position_name']}) का लॉगिन रोल वर्तमान में '{$row['current_role']}' है, जो कि '{$row['position_code']}' होना चाहिए।"
            ];
        }

        // B. Users who have role president/mahasachiv in users table, but DO NOT hold an active matching position in the current term
        $stmtB = $db->query("
            SELECT u.id AS user_uid, u.role AS current_role, m.full_name, m.membership_no
            FROM users u
            JOIN members m ON m.id = u.member_id
            WHERE u.role IN ('president', 'mahasachiv')
              AND u.id NOT IN (
                  SELECT DISTINCT u2.id
                  FROM office_bearers ob2
                  JOIN office_bearer_positions p2 ON p2.id = ob2.position_id
                  JOIN users u2 ON u2.member_id = ob2.member_id
                  WHERE ob2.term_id = {$active_term['id']} AND ob2.status = 'active'
                    AND ((p2.code = 'PRESIDENT' AND u2.role = 'president') OR (p2.code = 'MAHASACHIV' AND u2.role = 'mahasachiv'))
              )
        ");
        while ($row = $stmtB->fetch()) {
            $mismatches[] = [
                'type' => 'downgrade',
                'name' => $row['full_name'],
                'code' => $row['membership_no'],
                'bearer_id' => 0,
                'user_uid' => $row['user_uid'],
                'position' => ucfirst($row['current_role']),
                'target_role' => 'member',
                'current_role' => $row['current_role'],
                'message' => "चेतावनी: {$row['full_name']} के पास '{$row['current_role']}' के उच्च अधिकार हैं, लेकिन वे वर्तमान कार्यकारिणी पद पर सक्रिय नहीं हैं।"
            ];
        }
    } catch (PDOException $e) {
        error_log("Failed loading role mismatches: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">कार्यकारिणी पदाधिकारी प्रबंधन (Executive Committee Desk)</h4>
    <div class="d-flex gap-2">
        <a href="terms.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-calendar3 me-1"></i>कार्यकाल (Terms)</a>
        <a href="positions.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-briefcase me-1"></i>संवैधानिक पद (Positions)</a>
        <?php if ($active_term): ?>
            <a href="create.php" class="btn btn-xs btn-gold text-navy-custom fw-bold"><i class="bi bi-plus-circle me-1"></i>नया पदाधिकारी जोड़ें</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<!-- 1. Active Term Header Summary -->
<div class="card border-0 shadow-sm mb-4 font-hindi small bg-light">
    <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <span class="text-muted d-block font-size-xs">सक्रिय कार्यकारिणी कार्यकाल (Current Active Term)</span>
            <?php if ($active_term): ?>
                <h5 class="fw-bold text-navy-custom mb-0 mt-0.5"><?php echo e($active_term['title']); ?></h5>
                <span class="text-secondary" style="font-size:0.75rem;"><i class="bi bi-calendar-event me-1"></i>अवधि: <?php echo date('d-m-Y', strtotime($active_term['start_date'])); ?> से <?php echo $active_term['end_date'] ? date('d-m-Y', strtotime($active_term['end_date'])) : 'निरंतर'; ?></span>
            <?php else: ?>
                <h5 class="fw-bold text-danger mb-0 mt-0.5">कोई सक्रिय कार्यकारिणी घोषित नहीं है।</h5>
            <?php endif; ?>
        </div>
        
        <?php if ($active_term): ?>
            <div class="d-flex gap-1.5 mt-2 mt-md-0">
                <a href="print.php?term_id=<?php echo $active_term['id']; ?>" target="_blank" class="btn btn-xs btn-outline-navy"><i class="bi bi-printer me-1"></i>A4 प्रिंट</a>
                <a href="export-csv.php?term_id=<?php echo $active_term['id']; ?><?php echo $filter_pos ? '&position_id='.$filter_pos : ''; ?><?php echo $filter_status ? '&status='.$filter_status : ''; ?>" class="btn btn-xs btn-outline-navy"><i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV निर्यात</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 2. Role Sync / Access Mismatches Alerts (Requirements 25 & 26) -->
<?php if (!empty($mismatches)): ?>
    <div class="card border-0 shadow-sm border-start border-3 border-danger mb-4 font-hindi small text-navy-custom">
        <div class="card-header bg-danger bg-opacity-10 text-danger py-2">
            <h6 class="mb-0 fw-bold"><i class="bi bi-shield-exclamation me-2"></i>लॉगिन रोल सत्यापन त्रुटियाँ (Role Sync Alerts)</h6>
        </div>
        <div class="card-body py-2 px-3">
            <ul class="list-unstyled mb-0">
                <?php foreach ($mismatches as $m): ?>
                    <li class="py-2 border-bottom border-light d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                            <span><?php echo e($m['message']); ?></span>
                        </div>
                        <form method="POST" action="role-sync.php" class="d-inline" onsubmit="return confirm('क्या आप वास्तव में इस उपयोगकर्ता रोल को सिंक करना चाहते हैं?');">
                            <?php insertCSRF(); ?>
                            <input type="hidden" name="user_id" value="<?php echo $m['user_uid']; ?>">
                            <input type="hidden" name="target_role" value="<?php echo $m['target_role']; ?>">
                            <button type="submit" class="btn btn-xs <?php echo $m['type'] === 'upgrade' ? 'btn-success' : 'btn-outline-danger'; ?> py-0.5">
                                <?php echo $m['type'] === 'upgrade' ? 'Assign Position Role' : 'Revert to Member Role'; ?>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<!-- 3. Bearers Filter & List -->
<?php if ($active_term): ?>
    <div class="card border-0 shadow-xs p-3 bg-light border border-light mb-4 font-hindi small no-print">
        <form method="GET" action="" class="row g-2">
            <div class="col-md-5">
                <select name="position_id" class="form-select form-select-sm">
                    <option value="">-- सभी पद --</option>
                    <?php foreach ($positions_list as $pos): ?>
                        <option value="<?php echo $pos['id']; ?>" <?php echo ($filter_pos === intval($pos['id'])) ? 'selected' : ''; ?>><?php echo e($pos['position_name_hindi']); ?> (<?php echo e($pos['position_name']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- सभी स्थिति --</option>
                    <option value="active" <?php echo ($filter_status === 'active') ? 'selected' : ''; ?>>Active (सक्रिय)</option>
                    <option value="inactive" <?php echo ($filter_status === 'inactive') ? 'selected' : ''; ?>>Inactive (निष्क्रिय)</option>
                    <option value="resigned" <?php echo ($filter_status === 'resigned') ? 'selected' : ''; ?>>Resigned (इस्तीफा)</option>
                    <option value="completed" <?php echo ($filter_status === 'completed') ? 'selected' : ''; ?>>Completed (पूर्ण)</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-navy btn-sm w-100"><i class="bi bi-filter me-1"></i>फिल्टर लागू करें</button>
            </div>
        </form>
    </div>

    <!-- Table Registry Grid -->
    <div class="card border-0 shadow-sm font-hindi small text-navy-custom">
        <div class="card-header bg-navy-custom text-white py-2">
            <h6 class="mb-0 fw-bold">कार्यकाल पदाधिकारी विवरणी (Office Bearers list)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;" class="text-center">वरीयता</th>
                            <th>फोटो</th>
                            <th>संवैधानिक पद (Designation)</th>
                            <th>अधिवक्ता का नाम</th>
                            <th>सदस्य कोड (DBA Code)</th>
                            <th>पंजीकरण क्र.</th>
                            <th>स्थिति</th>
                            <th class="text-end">कार्यवाही</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bearers)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted font-hindi">इस कार्यकाल में अभी कोई पदाधिकारी जोड़ा नहीं गया है।</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bearers as $b): ?>
                                <tr>
                                    <td class="english-text text-center fw-bold">#<?php echo $b['display_order']; ?></td>
                                    <td>
                                        <img src="<?php echo SITE_URL; ?>/uploads/profile/<?php echo $b['photo_snapshot'] ?: 'default_advocate.png'; ?>" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                                    </td>
                                    <td>
                                        <strong><?php echo e($b['position_name_hindi']); ?></strong><br>
                                        <span class="text-muted font-size-xs english-text" style="font-size:0.7rem;"><?php echo e($b['position_name']); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo e($b['display_name_snapshot']); ?></strong>
                                        <?php if ($b['membership_status'] !== 'active'): ?>
                                            <span class="badge bg-danger ms-1 font-size-xs" style="font-size:0.65rem;">Membership: <?php echo e($b['membership_status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="english-text"><?php echo e($b['membership_no_snapshot'] ?: 'N/A'); ?></td>
                                    <td class="english-text"><?php echo e($b['enrollment_no_snapshot'] ?: 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        $c = ($b['status'] === 'active') ? 'bg-success' : (($b['status'] === 'resigned') ? 'bg-danger' : 'bg-secondary');
                                        ?>
                                        <span class="badge <?php echo $c; ?> font-size-xs"><?php echo e(ucfirst($b['status'])); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="edit.php?id=<?php echo $b['id']; ?>" class="btn btn-xs btn-outline-navy py-0.5">बदलें (Edit)</a>
                                            
                                            <?php if ($b['status'] === 'active'): ?>
                                                <button class="btn btn-xs btn-outline-danger py-0.5" data-bs-toggle="modal" data-bs-target="#tenureModal<?php echo $b['id']; ?>">End Tenure</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <!-- End Tenure Modal (Requirement 20) -->
                                <?php if ($b['status'] === 'active'): ?>
                                    <div class="modal fade" id="tenureModal<?php echo $b['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content text-start">
                                                <div class="modal-header bg-danger text-white py-2">
                                                    <h6 class="modal-title fw-bold">कार्यकाल समाप्त करें (End Tenure)</h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body text-navy-custom">
                                                    <form method="POST" action="role-sync.php">
                                                        <?php insertCSRF(); ?>
                                                        <input type="hidden" name="action" value="end_tenure">
                                                        <input type="hidden" name="bearer_id" value="<?php echo $b['id']; ?>">

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">समाप्ति तिथि (End Date) *</label>
                                                            <input type="date" name="end_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">समाप्ति स्थिति (Reason/Status) *</label>
                                                            <select name="status" class="form-select form-select-sm" required>
                                                                <option value="completed">Completed (पूर्ण कार्यकाल)</option>
                                                                <option value="resigned">Resigned (इस्तीफा)</option>
                                                                <option value="inactive">Inactive (निष्क्रिय)</option>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">समाप्ति टिप्पणी / कारण</label>
                                                            <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="उदा. त्यागपत्र स्वीकृत, पदोन्नति आदि..."></textarea>
                                                        </div>

                                                        <div class="text-end">
                                                            <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-dismiss="modal">बंद करें</button>
                                                            <button type="submit" class="btn btn-xs btn-danger px-3">End Tenure Now</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
