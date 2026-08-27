<?php
/**
 * Centralized System Audit Logs Console
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'आडिट लॉग प्रबंधन (Central Audit Logs)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin permission (Requirement 27: Normal admins/super admins only. No delete action.)
requireRole(['admin']);

$db = Database::getConnection();

// Build query filters
$start_date = sanitize($_GET['start_date'] ?? '');
$end_date = sanitize($_GET['end_date'] ?? '');
$filter_user = intval($_GET['user_id'] ?? 0);
$filter_role = sanitize($_GET['role'] ?? '');
$filter_module = sanitize($_GET['module'] ?? '');
$filter_action = sanitize($_GET['action_query'] ?? '');

$sql = "
    SELECT a.*, u.username AS performer_name
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
    WHERE 1=1
";
$params = [];

if (!empty($start_date)) {
    $sql .= " AND a.created_at >= ?";
    $params[] = $start_date . ' 00:00:00';
}
if (!empty($end_date)) {
    $sql .= " AND a.created_at <= ?";
    $params[] = $end_date . ' 23:59:59';
}
if ($filter_user > 0) {
    $sql .= " AND a.user_id = ?";
    $params[] = $filter_user;
}
if (!empty($filter_role)) {
    $sql .= " AND a.role = ?";
    $params[] = $filter_role;
}
if (!empty($filter_module)) {
    $sql .= " AND a.module = ?";
    $params[] = $filter_module;
}
if (!empty($filter_action)) {
    $sql .= " AND a.action LIKE ?";
    $params[] = "%$filter_action%";
}

$sql .= " ORDER BY a.id DESC LIMIT 100";
$logs = [];

if ($db) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load audit logs: " . $e->getMessage());
    }
}

// Fetch all users for dropdown filter
$users = [];
if ($db) {
    try {
        $users = $db->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();
    } catch (PDOException $e) {}
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-shield-lock-fill text-gold-custom me-2"></i>केंद्रीय ऑडिट लॉग विवरणी (System Audit Logs)</h4>
    <a href="../security/login-activity.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-person-badge-fill me-1"></i>लॉगिन गतिविधि</a>
</div>

<!-- Reusable Filters -->
<div class="card border-0 shadow-xs p-3 bg-light border border-light mb-4 font-hindi small">
    <form method="GET" action="" class="row g-2">
        <div class="col-md-2 col-6">
            <label class="form-label fw-bold">प्रारंभ तिथि</label>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
        </div>
        <div class="col-md-2 col-6">
            <label class="form-label fw-bold">समाप्ति तिथि</label>
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label fw-bold">उपयोगकर्ता</label>
            <select name="user_id" class="form-select form-select-sm">
                <option value="">-- सभी --</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $filter_user === intval($u['id']) ? 'selected' : ''; ?>><?php echo e($u['username']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-bold">सुरक्षा रोल</label>
            <select name="role" class="form-select form-select-sm">
                <option value="">-- सभी --</option>
                <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="president" <?php echo $filter_role === 'president' ? 'selected' : ''; ?>>President</option>
                <option value="mahasachiv" <?php echo $filter_role === 'mahasachiv' ? 'selected' : ''; ?>>Mahasachiv</option>
                <option value="member" <?php echo $filter_role === 'member' ? 'selected' : ''; ?>>Member</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-bold">मॉड्यूल</label>
            <select name="module" class="form-select form-select-sm">
                <option value="">-- सभी --</option>
                <option value="members" <?php echo $filter_module === 'members' ? 'selected' : ''; ?>>Members Master</option>
                <option value="id_cards" <?php echo $filter_module === 'id_cards' ? 'selected' : ''; ?>>ID Cards</option>
                <option value="wakalatnama" <?php echo $filter_module === 'wakalatnama' ? 'selected' : ''; ?>>Wakalatnama</option>
                <option value="notices" <?php echo $filter_module === 'notices' ? 'selected' : ''; ?>>Notices</option>
                <option value="fund" <?php echo $filter_module === 'fund' ? 'selected' : ''; ?>>Association Fund</option>
                <option value="fee" <?php echo $filter_module === 'fee' ? 'selected' : ''; ?>>Advocate Fee</option>
                <option value="chambers" <?php echo $filter_module === 'chambers' ? 'selected' : ''; ?>>Chambers</option>
                <option value="elections" <?php echo $filter_module === 'elections' ? 'selected' : ''; ?>>Elections</option>
                <option value="settings" <?php echo $filter_module === 'settings' ? 'selected' : ''; ?>>Settings</option>
                <option value="content" <?php echo $filter_module === 'content' ? 'selected' : ''; ?>>Content CMS</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-bold">खोज कीवर्ड</label>
            <div class="input-group input-group-sm">
                <input type="text" name="action_query" class="form-control" value="<?php echo e($filter_action); ?>" placeholder="क्रिया विवरण...">
                <button type="submit" class="btn btn-navy"><i class="bi bi-search"></i></button>
            </div>
        </div>
    </form>
</div>

<!-- Logs List -->
<div class="card border-0 shadow-sm font-hindi small text-navy-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:0.75rem;">
                <thead class="table-light">
                    <tr>
                        <th>दिनांक एवं समय</th>
                        <th>उपयोगकर्ता (User)</th>
                        <th>रोल</th>
                        <th>मॉड्यूल</th>
                        <th>क्रिया (Action Executed)</th>
                        <th>आईपी पता (IP)</th>
                        <th class="text-end">विवरण</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-3 text-muted">कोई लॉग प्रविष्टि नहीं मिली।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="english-text"><?php echo date('d-m-Y h:i A', strtotime($l['created_at'])); ?></td>
                                <td><strong><?php echo e($l['performer_name'] ?: 'System'); ?></strong></td>
                                <td>
                                    <span class="badge bg-light text-navy-custom border"><?php echo e(ucfirst($l['role'])); ?></span>
                                </td>
                                <td class="fw-bold text-uppercase text-secondary"><?php echo e($l['module']); ?></td>
                                <td><span class="text-dark"><?php echo e($l['action']); ?></span></td>
                                <td class="english-text text-muted"><?php echo e($l['ip_address']); ?></td>
                                <td class="text-end">
                                    <a href="view.php?id=<?php echo $l['id']; ?>" class="btn btn-xs btn-outline-navy py-0.5">Details</a>
                                </td>
                            </tr>
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
