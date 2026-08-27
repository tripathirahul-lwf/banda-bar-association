<?php
/**
 * Admin Portal Accounts & User Management Console
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'पोर्टल खाता प्रबंधन (User Management)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin permission
requireRole(['admin']);

$db = Database::getConnection();
$error = '';
$success = '';

// Handle User Actions (Requirement 30)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $action = sanitize($_POST['action'] ?? '');
        $user_id = intval($_POST['user_id'] ?? 0);

        try {
            if ($user_id <= 0 && $action !== 'create_user') {
                throw new Exception('अमान्य उपयोगकर्ता।');
            }

            $db->beginTransaction();

            if ($action === 'status_toggle') {
                $status = sanitize($_POST['status'] ?? 'active');
                if (!in_array($status, ['active', 'inactive'])) {
                    throw new Exception('अमान्य खाता स्थिति।');
                }

                // Prevent admin disabling themselves
                if ($user_id === intval($_SESSION['user_id'])) {
                    throw new Exception('आप अपने स्वयं के खाते को निष्क्रिय नहीं कर सकते।');
                }

                // Get old value
                $old = $db->query("SELECT status FROM users WHERE id = $user_id")->fetchColumn();

                $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$status, $user_id]);

                logAudit('users', 'Toggled User Status', 'users', $user_id, "Status changed from $old to $status.", ['status' => $old], ['status' => $status]);
                $success = 'उपयोगकर्ता खाते की स्थिति सफलतापूर्वक बदली गई।';

            } elseif ($action === 'unlock') {
                $stmt = $db->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, status = 'active' WHERE id = ?");
                $stmt->execute([$user_id]);

                logAudit('users', 'Unlocked User Account', 'users', $user_id, "User account manually unlocked.");
                $success = 'उपयोगकर्ता खाता सफलतापूर्वक अनलॉक कर दिया गया।';

            } elseif ($action === 'reset_password') {
                $new_password = $_POST['new_password'] ?? '';
                if (strlen($new_password) < 6) {
                    throw new Exception('पासवर्ड की लंबाई कम से कम 6 अक्षर होनी चाहिए।');
                }
                $hash = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?");
                $stmt->execute([$hash, $user_id]);

                logAudit('users', 'Reset User Password', 'users', $user_id, "Manual password reset by Admin.");
                $success = 'पासवर्ड सफलतापूर्वक बदल दिया गया।';

            } elseif ($action === 'force_password_change') {
                $stmt = $db->prepare("UPDATE users SET must_change_password = 1 WHERE id = ?");
                $stmt->execute([$user_id]);

                logAudit('users', 'Forced Password Change', 'users', $user_id, "Flagged user to change password on next login.");
                $success = 'अगले लॉगिन पर पासवर्ड परिवर्तन हेतु सफलतापूर्वक बाध्य किया गया।';

            } elseif ($action === 'link_member') {
                $member_id = intval($_POST['member_id'] ?? 0);
                if ($member_id <= 0) {
                    throw new Exception('अमान्य अधिवक्ता सदस्य क्रमांक।');
                }

                // Check if member already linked to another user
                $chk = $db->prepare("SELECT COUNT(*) FROM users WHERE member_id = ? AND id != ?");
                $chk->execute([$member_id, $user_id]);
                if ($chk->fetchColumn() > 0) {
                    throw new Exception('यह सदस्य पूर्व से ही किसी अन्य उपयोगकर्ता खाते से सम्बद्ध है।');
                }

                // Fetch current linked member
                $old = $db->query("SELECT member_id FROM users WHERE id = $user_id")->fetchColumn();

                // Update user
                $stmt = $db->prepare("UPDATE users SET member_id = ? WHERE id = ?");
                $stmt->execute([$member_id, $user_id]);

                // Also update member's user_id link
                $m_stmt = $db->prepare("UPDATE members SET user_id = ? WHERE id = ?");
                $m_stmt->execute([$user_id, $member_id]);

                logAudit('users', 'Linked User to Member', 'users', $user_id, "Linked member ID changed from $old to $member_id.", ['member_id' => $old], ['member_id' => $member_id]);
                $success = 'उपयोगकर्ता खाते को अधिवक्ता सदस्य से सफलतापूर्वक लिंक किया गया।';

            } elseif ($action === 'role_change') { // Requirement 31
                $new_role = sanitize($_POST['new_role'] ?? '');
                if (!in_array($new_role, ['admin', 'president', 'mahasachiv', 'member'])) {
                    throw new Exception('अमान्य रोल।');
                }

                // Prevent admin changing their own role to prevent lockout
                if ($user_id === intval($_SESSION['user_id'])) {
                    throw new Exception('सुरक्षा चेतावनी: सुरक्षा कारणों से आप स्वयं का रोल नहीं बदल सकते (लॉगआउट लॉकआउट से बचने हेतु)।');
                }

                $u_stmt = $db->prepare("SELECT role, member_id FROM users WHERE id = ?");
                $u_stmt->execute([$user_id]);
                $u = $u_stmt->fetch();
                $old_role = $u['role'];

                $stmt = $db->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);

                logAudit('users', 'Changed User Role', 'users', $user_id, "User role modified from $old_role to $new_role.", ['role' => $old_role], ['role' => $new_role]);
                $success = 'उपयोगकर्ता सुरक्षा रोल सफलतापूर्वक परिवर्तित किया गया।';
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

// Fetch all portal users
$filter_role = sanitize($_GET['role'] ?? '');
$filter_status = sanitize($_GET['status'] ?? '');
$filter_query = sanitize($_GET['query'] ?? '');

$sql = "
    SELECT u.*, m.full_name AS member_name, m.membership_no
    FROM users u
    LEFT JOIN members m ON m.id = u.member_id
    WHERE 1=1
";
$params = [];

if (!empty($filter_role)) {
    $sql .= " AND u.role = ?";
    $params[] = $filter_role;
}
if (!empty($filter_status)) {
    $sql .= " AND u.status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_query)) {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR m.full_name LIKE ?)";
    $sp = "%$filter_query%";
    $params[] = $sp;
    $params[] = $sp;
    $params[] = $sp;
}

$sql .= " ORDER BY u.id DESC";
$users_list = [];

if ($db) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users_list = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch users: " . $e->getMessage());
    }
}

// Fetch members who don't have linked users to display in dropdowns
$available_members = [];
if ($db) {
    try {
        $available_members = $db->query("
            SELECT id, full_name, membership_no 
            FROM members 
            WHERE user_id IS NULL AND membership_status = 'active'
            ORDER BY full_name ASC
        ")->fetchAll();
    } catch (PDOException $e) {}
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-people-fill text-gold-custom me-2"></i>पोर्टल खाता प्रबंधन (User Accounts Console)</h4>
    <a href="permissions.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-shield-check me-1"></i>परमिशन रिव्यू</a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-xs p-3 bg-light border border-light mb-4 font-hindi small">
    <form method="GET" action="" class="row g-2">
        <div class="col-md-3">
            <select name="role" class="form-select form-select-sm">
                <option value="">-- सभी रोल --</option>
                <option value="admin" <?php echo ($filter_role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                <option value="president" <?php echo ($filter_role === 'president') ? 'selected' : ''; ?>>President</option>
                <option value="mahasachiv" <?php echo ($filter_role === 'mahasachiv') ? 'selected' : ''; ?>>Mahasachiv</option>
                <option value="member" <?php echo ($filter_role === 'member') ? 'selected' : ''; ?>>Member</option>
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">-- सभी स्थितियां --</option>
                <option value="active" <?php echo ($filter_status === 'active') ? 'selected' : ''; ?>>Active (सक्रिय)</option>
                <option value="inactive" <?php echo ($filter_status === 'inactive') ? 'selected' : ''; ?>>Inactive (निष्क्रिय)</option>
                <option value="suspended" <?php echo ($filter_status === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                <option value="locked" <?php echo ($filter_status === 'locked') ? 'selected' : ''; ?>>Locked (अवरुद्ध)</option>
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" name="query" class="form-control form-control-sm" value="<?php echo e($filter_query); ?>" placeholder="यूज़रनेम, नाम या ईमेल से खोजें...">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-navy btn-sm w-100"><i class="bi bi-search me-1"></i>खोजें</button>
        </div>
    </form>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Users table -->
<div class="card border-0 shadow-sm font-hindi small text-navy-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:0.75rem;">
                <thead class="table-light">
                    <tr>
                        <th>यूज़रनेम</th>
                        <th>सम्बद्ध अधिवक्ता</th>
                        <th>सुरक्षा रोल (Role)</th>
                        <th>खाता स्थिति</th>
                        <th>अंतिम लॉगिन तिथि</th>
                        <th class="text-end">कार्यवाही</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users_list)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-3 text-muted">कोई खाता नहीं मिला।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users_list as $u): ?>
                            <tr>
                                <td>
                                    <strong class="english-text"><?php echo e($u['username']); ?></strong><br>
                                    <span class="text-muted english-text" style="font-size: 0.65rem;"><?php echo e($u['email'] ?: 'No Email'); ?></span>
                                </td>
                                <td>
                                    <?php if ($u['member_id']): ?>
                                        <strong><?php echo e($u['member_name']); ?></strong><br>
                                        <span class="text-muted english-text" style="font-size: 0.65rem;">Code: <?php echo e($u['membership_no']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted italic">असम्बद्ध (Unlinked)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-navy-custom border text-uppercase" style="font-size: 0.65rem;"><?php echo e($u['role']); ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $c = ($u['status'] === 'active') ? 'bg-success' : (($u['status'] === 'locked') ? 'bg-danger' : 'bg-secondary');
                                    ?>
                                    <span class="badge <?php echo $c; ?> font-size-xs"><?php echo e(ucfirst($u['status'])); ?></span>
                                </td>
                                <td class="english-text">
                                    <?php echo $u['last_login_at'] ? date('d-m-Y h:i A', strtotime($u['last_login_at'])) : '-'; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <!-- Actions Dropdown -->
                                        <div class="dropdown">
                                            <button class="btn btn-xs btn-outline-navy dropdown-toggle py-0.5" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                विकल्प
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.75rem;">
                                                <!-- Reset Password Trigger -->
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#resetModal<?php echo $u['id']; ?>"><i class="bi bi-key me-1"></i>पासवर्ड बदलें</a></li>
                                                
                                                <!-- Force Password Change -->
                                                <li>
                                                    <form method="POST" action="" onsubmit="return confirm('क्या आप वास्तव में इस उपयोगकर्ता को पासवर्ड बदलने के लिए मजबूर करना चाहते हैं?');">
                                                        <?php insertCSRF(); ?>
                                                        <input type="hidden" name="action" value="force_password_change">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button type="submit" class="dropdown-item"><i class="bi bi-arrow-repeat me-1"></i>पासवर्ड बदलें बाध्य करें</button>
                                                    </form>
                                                </li>

                                                <!-- Status Toggle -->
                                                <li>
                                                    <form method="POST" action="" onsubmit="return confirm('क्या आप खाता स्थिति बदलना चाहते हैं?');">
                                                        <?php insertCSRF(); ?>
                                                        <input type="hidden" name="action" value="status_toggle">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <input type="hidden" name="status" value="<?php echo ($u['status'] === 'active') ? 'inactive' : 'active'; ?>">
                                                        <button type="submit" class="dropdown-item text-warning">
                                                            <i class="bi bi-power me-1"></i><?php echo ($u['status'] === 'active') ? 'Deactivate' : 'Activate'; ?>
                                                        </button>
                                                    </form>
                                                </li>

                                                <!-- Unlock Account if locked -->
                                                <?php if ($u['status'] === 'locked' || $u['failed_login_attempts'] > 0): ?>
                                                    <li>
                                                        <form method="POST" action="" onsubmit="return confirm('क्या आप खाता अनलॉक करना चाहते हैं?');">
                                                            <?php insertCSRF(); ?>
                                                            <input type="hidden" name="action" value="unlock">
                                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                            <button type="submit" class="dropdown-item text-success"><i class="bi bi-unlock me-1"></i>खाता अनलॉक करें</button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>

                                                <!-- Role Change Trigger (Requirement 31) -->
                                                <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#roleModal<?php echo $u['id']; ?>"><i class="bi bi-shield-exclamation me-1"></i>रोल परिवर्तित करें</a></li>

                                                <!-- Link Member Trigger -->
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#linkModal<?php echo $u['id']; ?>"><i class="bi bi-link-45deg me-1"></i>अधिवक्ता सम्बद्ध करें</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- Reset Password Modal -->
                            <div class="modal fade" id="resetModal<?php echo $u['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content text-start">
                                        <div class="modal-header bg-navy-custom text-white py-2">
                                            <h6 class="modal-title fw-bold">पासवर्ड बदलें - <?php echo e($u['username']); ?></h6>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body text-navy-custom">
                                            <form method="POST" action="">
                                                <?php insertCSRF(); ?>
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">नया पासवर्ड दर्ज करें (Min 6 Chars) *</label>
                                                    <input type="password" name="new_password" class="form-control form-control-sm" required minlength="6">
                                                </div>

                                                <div class="text-end">
                                                    <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-dismiss="modal">रद्द करें</button>
                                                    <button type="submit" class="btn btn-xs btn-navy px-3">पासवर्ड रीसेट करें</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Role Change Modal (Requirement 31: Safety checks, current vs new role) -->
                            <div class="modal fade" id="roleModal<?php echo $u['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content text-start">
                                        <div class="modal-header bg-danger text-white py-2">
                                            <h6 class="modal-title fw-bold"><i class="bi bi-shield-exclamation me-1"></i>रोल परिवर्तन पुष्टि - <?php echo e($u['username']); ?></h6>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body text-navy-custom">
                                            <form method="POST" action="">
                                                <?php insertCSRF(); ?>
                                                <input type="hidden" name="action" value="role_change">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                
                                                <div class="p-3 bg-light rounded border mb-3">
                                                    <p class="mb-1"><strong>वर्तमान रोल:</strong> <span class="badge bg-secondary"><?php echo e(ucfirst($u['role'])); ?></span></p>
                                                    <p class="mb-0"><strong>सम्बद्ध अधिवक्ता:</strong> <?php echo e($u['member_name'] ?: 'No linked advocate member'); ?></p>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">नया सुरक्षा रोल चुनें *</label>
                                                    <select name="new_role" class="form-select form-select-sm" required>
                                                        <option value="member" <?php echo $u['role'] === 'member' ? 'selected' : ''; ?>>Member (अधिवक्ता सदस्य)</option>
                                                        <option value="mahasachiv" <?php echo $u['role'] === 'mahasachiv' ? 'selected' : ''; ?>>Mahasachiv (महासचिव)</option>
                                                        <option value="president" <?php echo $u['role'] === 'president' ? 'selected' : ''; ?>>President (अध्यक्ष)</option>
                                                        <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin (प्रशासक)</option>
                                                    </select>
                                                </div>

                                                <div class="alert alert-warning text-dark py-2" style="font-size:0.7rem;">
                                                    <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> **चेतावनी:** सुरक्षा रोल बदलने से इस उपयोगकर्ता के सिस्टम अधिकार तुरंत बदल जाएंगे।
                                                </div>

                                                <div class="text-end">
                                                    <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-dismiss="modal">रद्द करें</button>
                                                    <button type="submit" class="btn btn-xs btn-danger px-3">रोल बदलें (Change Role Now)</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Link Member Modal -->
                            <div class="modal fade" id="linkModal<?php echo $u['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content text-start">
                                        <div class="modal-header bg-navy-custom text-white py-2">
                                            <h6 class="modal-title fw-bold">अधिवक्ता सदस्य से लिंक करें - <?php echo e($u['username']); ?></h6>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body text-navy-custom">
                                            <form method="POST" action="">
                                                <?php insertCSRF(); ?>
                                                <input type="hidden" name="action" value="link_member">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">सम्बद्ध करने हेतु अधिवक्ता चुनें *</label>
                                                    <select name="member_id" class="form-select form-select-sm" required>
                                                        <option value="">-- सदस्य चुनें --</option>
                                                        <?php foreach ($available_members as $am): ?>
                                                            <option value="<?php echo $am['id']; ?>"><?php echo e($am['full_name']); ?> (<?php echo e($am['membership_no']); ?>)</option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="text-end">
                                                    <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-dismiss="modal">रद्द करें</button>
                                                    <button type="submit" class="btn btn-xs btn-navy px-3">लिंक सहेजें</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
