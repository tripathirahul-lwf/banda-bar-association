<?php
/**
 * Admin Contact Form Submissions Registry
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'सम्पर्क सन्देश प्रबंधन (Contact Messages)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin permission
requireRole(['admin']);

$db = Database::getConnection();
$error = '';
$success = '';

// Handle actions (Requirement 23)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $action = sanitize($_POST['action'] ?? '');
        $msg_id = intval($_POST['message_id'] ?? 0);

        if ($msg_id > 0) {
            try {
                $db->beginTransaction();

                if ($action === 'mark_read') {
                    $up = $db->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
                    $up->execute([$msg_id]);
                    $success = 'सन्देश को पठित (Read) चिह्नित किया गया।';
                } elseif ($action === 'mark_resolved') {
                    $up = $db->prepare("UPDATE contact_messages SET status = 'resolved' WHERE id = ?");
                    $up->execute([$msg_id]);
                    $success = 'सन्देश को निस्तारित (Resolved) चिह्नित किया गया।';
                } elseif ($action === 'archive') {
                    $up = $db->prepare("UPDATE contact_messages SET status = 'archived' WHERE id = ?");
                    $up->execute([$msg_id]);
                    $success = 'सन्देश को पुरालेख (Archived) में स्थानांतरित किया गया।';
                }

                logAudit('messages', 'Contact Message Action', 'contact_messages', $msg_id, "Message action: $action.");

                $db->commit();
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'त्रुटि: ' . $e->getMessage();
            }
        }
    }
}

// Fetch messages with status filter
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$sql = "SELECT * FROM contact_messages";
$params = [];

if (!empty($filter_status)) {
    $sql .= " WHERE status = ?";
    $params[] = $filter_status;
} else {
    $sql .= " WHERE status != 'archived'"; // Default hide archived
}

$sql .= " ORDER BY created_at DESC";
$messages = [];

if ($db) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load contact messages: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">सम्पर्क सन्देश प्रबंधन (Contact Messages Desk)</h4>
</div>

<!-- Filters -->
<div class="card border-0 shadow-xs p-3 bg-light border border-light mb-4 font-hindi small">
    <form method="GET" action="" class="row g-2 align-items-center">
        <div class="col-md-8">
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-xs <?php echo empty($filter_status) ? 'btn-navy' : 'btn-outline-navy'; ?>">सक्रिय सन्देश (All Active)</a>
                <a href="index.php?status=new" class="btn btn-xs <?php echo $filter_status === 'new' ? 'btn-navy' : 'btn-outline-navy'; ?>">नए (New)</a>
                <a href="index.php?status=read" class="btn btn-xs <?php echo $filter_status === 'read' ? 'btn-navy' : 'btn-outline-navy'; ?>">पठित (Read)</a>
                <a href="index.php?status=resolved" class="btn btn-xs <?php echo $filter_status === 'resolved' ? 'btn-navy' : 'btn-outline-navy'; ?>">निस्तारित (Resolved)</a>
                <a href="index.php?status=archived" class="btn btn-xs <?php echo $filter_status === 'archived' ? 'btn-navy' : 'btn-outline-navy'; ?>">पुरालेख (Archived)</a>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <span class="text-muted" style="font-size:0.75rem;">कुल प्राप्त सन्देश: <strong><?php echo count($messages); ?></strong></span>
        </div>
    </form>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Submissions Listing -->
<div class="card border-0 shadow-sm font-hindi small text-navy-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:0.78rem;">
                <thead class="table-light">
                    <tr>
                        <th>प्राप्त तिथि</th>
                        <th>प्रेषक नाम</th>
                        <th>सम्पर्क सूत्र (Mobile/Email)</th>
                        <th>विषय (Subject)</th>
                        <th>स्थिति</th>
                        <th class="text-end">कार्यवाही</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">कोई सन्देश नहीं मिला।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <tr>
                                <td class="english-text"><?php echo date('d-m-Y h:i A', strtotime($msg['created_at'])); ?></td>
                                <td class="fw-bold"><?php echo e($msg['name']); ?></td>
                                <td>
                                    <span class="d-block english-text"><i class="bi bi-telephone me-1"></i><?php echo e($msg['mobile']); ?></span>
                                    <span class="d-block text-muted english-text" style="font-size:0.7rem;"><i class="bi bi-envelope me-1"></i><?php echo e($msg['email']); ?></span>
                                </td>
                                <td><strong><?php echo e($msg['subject']); ?></strong></td>
                                <td>
                                    <?php 
                                    $badge = 'bg-warning text-dark';
                                    if ($msg['status'] === 'read') $badge = 'bg-info text-white';
                                    elseif ($msg['status'] === 'resolved') $badge = 'bg-success';
                                    elseif ($msg['status'] === 'archived') $badge = 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $badge; ?> font-size-xs"><?php echo e(ucfirst($msg['status'])); ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button class="btn btn-xs btn-outline-navy py-0.5" data-bs-toggle="modal" data-bs-target="#msgModal<?php echo $msg['id']; ?>">पढ़ें</button>
                                        
                                        <?php if ($msg['status'] === 'new'): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <?php insertCSRF(); ?>
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                <button type="submit" class="btn btn-xs btn-light text-navy-custom border py-0.5" title="Mark Read"><i class="bi bi-envelope-open"></i></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($msg['status'] !== 'resolved' && $msg['status'] !== 'archived'): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <?php insertCSRF(); ?>
                                                <input type="hidden" name="action" value="mark_resolved">
                                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-success py-0.5" title="Mark Resolved"><i class="bi bi-check2-circle"></i></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($msg['status'] !== 'archived'): ?>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('क्या आप इस सन्देश को पुरालेख में भेजना चाहते हैं?');">
                                                <?php insertCSRF(); ?>
                                                <input type="hidden" name="action" value="archive">
                                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-danger py-0.5" title="Archive"><i class="bi bi-archive"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <!-- Message Body Modal -->
                            <div class="modal fade" id="msgModal<?php echo $msg['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content text-start">
                                        <div class="modal-header bg-navy-custom text-white py-2">
                                            <h6 class="modal-title fw-bold">सम्पर्क सन्देश - <?php echo e($msg['name']); ?></h6>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body text-navy-custom">
                                            <div class="p-2 bg-light rounded mb-3 border">
                                                <strong>विषय:</strong> <?php echo e($msg['subject']); ?><br>
                                                <strong>भेजा गया:</strong> <span class="english-text"><?php echo date('d-m-Y h:i A', strtotime($msg['created_at'])); ?></span>
                                            </div>
                                            <p class="leading-relaxed border p-2 bg-white rounded" style="min-height: 100px; white-space: pre-wrap;"><?php echo e($msg['message']); ?></p>
                                            
                                            <div class="text-end">
                                                <button type="button" class="btn btn-xs btn-navy" data-bs-dismiss="modal">बंद करें</button>
                                            </div>
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
