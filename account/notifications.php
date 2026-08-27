<?php
/**
 * Personalized System Notifications Center
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'सूचनाएं एवं अलर्ट (System Notifications)';
require_once __DIR__ . '/../includes/dashboard/header.php';

$db = Database::getConnection();
$error = '';
$success = '';

$user_id = $_SESSION['user_id'] ?? 0;
$member_id = $_SESSION['member_id'] ?? 0;
$role = $_SESSION['role'] ?? '';

// Handle mark as read actions (Requirement 37)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $action = sanitize($_POST['action'] ?? '');
        
        try {
            if ($action === 'mark_all') {
                $stmt = $db->prepare("
                    UPDATE `notifications` 
                    SET `is_read` = 1 
                    WHERE (`user_id` = ? OR (`member_id` = ? AND ? > 0) OR `role_target` = ?)
                ");
                $stmt->execute([$user_id, $member_id, $member_id, $role]);
                $success = 'सभी सूचनाओं को पठित चिह्नित किया गया।';
            } elseif ($action === 'mark_single') {
                $notif_id = intval($_POST['notification_id'] ?? 0);
                if ($notif_id > 0) {
                    $stmt = $db->prepare("
                        UPDATE `notifications` 
                        SET `is_read` = 1 
                        WHERE `id` = ? AND (`user_id` = ? OR (`member_id` = ? AND ? > 0) OR `role_target` = ?)
                    ");
                    $stmt->execute([$notif_id, $user_id, $member_id, $member_id, $role]);
                    $success = 'सूचना को पठित चिह्नित किया गया।';
                }
            }
        } catch (PDOException $e) {
            $error = 'त्रुटि: ' . $e->getMessage();
        }
    }
}

// Fetch user notifications
$notifications_list = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM `notifications` 
            WHERE `user_id` = ? OR (`member_id` = ? AND ? > 0) OR `role_target` = ?
            ORDER BY `created_at` DESC LIMIT 100
        ");
        $stmt->execute([$user_id, $member_id, $member_id, $role]);
        $notifications_list = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch user notifications: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-bell-fill text-gold-custom me-2"></i>मेरी सूचनाएं एवं अलर्ट (My Notifications)</h4>
    <?php if (!empty($notifications_list)): ?>
        <form method="POST" action="" class="d-inline">
            <?php insertCSRF(); ?>
            <input type="hidden" name="action" value="mark_all">
            <button type="submit" class="btn btn-xs btn-outline-navy"><i class="bi bi-check2-all me-1"></i>सभी को पठित मार्क करें</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm font-hindi small text-navy-custom">
    <div class="card-body p-0">
        <?php if (empty($notifications_list)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-bell-slash display-5 d-block mb-2"></i>
                <p class="mb-0">आपके पास कोई नई सूचना या अलर्ट नहीं है।</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications_list as $notif): 
                    $border_class = $notif['is_read'] ? 'border-light' : 'border-start border-3 border-gold-custom bg-light bg-opacity-50';
                ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start gap-3 <?php echo $border_class; ?>">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <?php 
                                $icon = 'bi-info-circle-fill text-primary';
                                if ($notif['type'] === 'success') $icon = 'bi-check-circle-fill text-success';
                                elseif ($notif['type'] === 'warning') $icon = 'bi-exclamation-triangle-fill text-warning';
                                elseif ($notif['type'] === 'danger') $icon = 'bi-x-circle-fill text-danger';
                                ?>
                                <i class="bi <?php echo $icon; ?>"></i>
                                <span class="fw-bold"><?php echo e($notif['title']); ?></span>
                                <small class="text-muted english-text ms-auto" style="font-size:0.68rem;"><?php echo date('d-m-Y h:i A', strtotime($notif['created_at'])); ?></small>
                            </div>
                            <p class="text-muted mb-2" style="font-size:0.75rem;"><?php echo e($notif['message']); ?></p>
                            
                            <?php if (!empty($notif['link'])): ?>
                                <a href="<?php echo SITE_URL . '/' . ltrim($notif['link'], '/'); ?>" class="btn btn-link btn-xs text-decoration-none p-0 fw-bold text-navy-custom">
                                    विवरण देखें (View Details) <i class="bi bi-chevron-right small"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if (!$notif['is_read']): ?>
                            <form method="POST" action="" class="d-inline">
                                <?php insertCSRF(); ?>
                                <input type="hidden" name="action" value="mark_single">
                                <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                <button type="submit" class="btn btn-xs btn-light text-navy-custom border py-0.5" title="पठित मार्क करें">
                                    <i class="bi bi-check2"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/dashboard/footer.php';
?>
