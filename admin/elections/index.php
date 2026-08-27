<?php
/**
 * Election Master Registry Index Workspace
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'चुनाव प्रबंधन डैशबोर्ड (Election Management)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';

// Handle Archive Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'archive') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $elec_id = intval($_POST['election_id'] ?? 0);
        if ($elec_id > 0) {
            try {
                $db->beginTransaction();
                
                $up = $db->prepare("UPDATE elections SET status = 'archived' WHERE id = ?");
                $up->execute([$elec_id]);
                
                // Audit log
                $audit = $db->prepare("INSERT INTO election_history (election_id, action, module, record_id, remarks, performed_by) VALUES (?, 'Election Archived', 'elections', ?, 'चुनाव पुरालेख (Archived) में सुरक्षित किया गया।', ?)");
                $audit->execute([$elec_id, $elec_id, $_SESSION['user_id']]);
                
                $db->commit();
                $success = 'चुनाव सफलतापूर्वक आर्काइव कर दिया गया।';
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'आर्काइव करने में विफलता: ' . $e->getMessage();
            }
        }
    }
}

// Retrieve elections
$elections = [];
if ($db) {
    try {
        $stmt = $db->query("
            SELECT e.*, 
                   (SELECT COUNT(*) FROM election_voters WHERE election_id = e.id) AS voters_count,
                   (SELECT COUNT(*) FROM election_candidates WHERE election_id = e.id) AS candidates_count
            FROM elections e
            ORDER BY e.election_year DESC, e.id DESC
        ");
        $elections = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed listing elections: " . $e->getMessage());
    }
}

// Stage labels mapping
$status_badges = [
    'draft' => 'bg-secondary',
    'announced' => 'bg-info text-dark',
    'nomination_open' => 'bg-primary text-white',
    'scrutiny' => 'bg-warning text-dark',
    'withdrawal' => 'bg-warning text-dark',
    'candidate_finalized' => 'bg-success',
    'voter_list_finalized' => 'bg-success',
    'polling_scheduled' => 'bg-info text-dark',
    'polling_completed' => 'bg-dark text-white',
    'counting' => 'bg-primary text-white',
    'result_declared' => 'bg-success text-white',
    'completed' => 'bg-success text-white',
    'archived' => 'bg-secondary',
    'cancelled' => 'bg-danger text-white'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">आम चुनाव प्रशासनिक नियंत्रण पटल (Election Registry)</h4>
    <a href="create.php" class="btn btn-xs btn-gold fw-semibold text-navy-custom"><i class="bi bi-plus-circle me-1"></i>नया चुनाव घोषित करें</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Grid Table of Elections -->
<div class="card border-0 shadow-sm font-hindi small text-navy-custom">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-grid-fill text-gold-custom me-2"></i>घोषित चुनावों की सूची (Elections Register)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2.5">चुनाव कोड (Code)</th>
                        <th>चुनाव शीर्षक (Title)</th>
                        <th>वर्ष</th>
                        <th>मतदान तिथि</th>
                        <th>सम्बद्ध मतदाता</th>
                        <th>नामांकित प्रत्याशी</th>
                        <th>वर्तमान चरण (Stage)</th>
                        <th>दृश्यता (Visibility)</th>
                        <th class="text-end">कार्यवाही (Actions)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($elections)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">कोई चुनाव घोषित नहीं किया गया है। नया चुनाव जोड़ने के लिए ऊपर दिए बटन का उपयोग करें।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($elections as $elec): ?>
                            <tr>
                                <td class="py-2 english-text fw-bold text-navy-custom"><?php echo e($elec['election_code']); ?></td>
                                <td>
                                    <strong><?php echo e($elec['title']); ?></strong>
                                    <?php if ($elec['venue']): ?>
                                        <br><small class="text-muted"><i class="bi bi-geo-alt me-1"></i>स्थान: <?php echo e($elec['venue']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="english-text"><?php echo e($elec['election_year']); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($elec['election_date'])); ?></td>
                                <td class="english-text fw-semibold text-center"><?php echo $elec['voters_count']; ?></td>
                                <td class="english-text fw-semibold text-center"><?php echo $elec['candidates_count']; ?></td>
                                <td>
                                    <span class="badge <?php echo $status_badges[$elec['status']] ?? 'bg-secondary'; ?> font-size-xs">
                                        <?php echo e(ucfirst(str_replace('_', ' ', $elec['status']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-size-xs"><?php echo e($elec['visibility']); ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="view.php?id=<?php echo $elec['id']; ?>" class="btn btn-xs btn-navy py-0.5"><i class="bi bi-gear-fill me-1"></i>प्रबंधन (Manage)</a>
                                        
                                        <?php if ($elec['status'] !== 'archived'): ?>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('क्या आप वास्तव में इस चुनाव को पुरालेख (Archive) करना चाहते हैं? इसके बाद कोई बदलाव सम्भव नहीं होगा।');">
                                                <?php insertCSRF(); ?>
                                                <input type="hidden" name="action" value="archive">
                                                <input type="hidden" name="election_id" value="<?php echo $elec['id']; ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-secondary py-0.5"><i class="bi bi-archive me-1"></i>आर्काइव</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
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
