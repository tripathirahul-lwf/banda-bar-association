<?php
/**
 * President Notice Approvals Workspace
 * District Bar Association, Banda
 */

$pageTitle = 'अधिसूचना अनुमोदन पटल (President Approvals)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce President role
requireRole('president');

$db = Database::getConnection();
$pending_notices = [];
$approved_notices = [];
$published_notices = [];

if ($db) {
    try {
        // Pending
        $stmt = $db->query("SELECT * FROM notices WHERE status = 'pending_approval' ORDER BY id DESC");
        $pending_notices = $stmt->fetchAll();

        // Recently Approved
        $stmt = $db->query("SELECT * FROM notices WHERE status = 'approved' ORDER BY id DESC LIMIT 5");
        $approved_notices = $stmt->fetchAll();

        // Published
        $stmt = $db->query("SELECT * FROM notices WHERE status = 'published' ORDER BY id DESC LIMIT 5");
        $published_notices = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading President approvals dashboard: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-shield-check me-2"></i>अधिसूचना अनुमोदन पटल (President Desk)</h4>
</div>

<!-- Pending Approvals -->
<div class="card mb-4 border-0 shadow-sm font-hindi small">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-gold-custom me-2"></i>अनुमोदन हेतु लंबित अधिसूचनाएं (Pending Approvals)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2" style="width: 15%;">अधिसूचना सं.</th>
                        <th style="width: 45%;">शीर्षक</th>
                        <th style="width: 15%;">श्रेणी</th>
                        <th style="width: 10%;">प्राथमिकता</th>
                        <th class="text-end" style="width: 15%;">कार्रवाई</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_notices)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-secondary">कोई सूचना अनुमोदन के लिए लंबित नहीं है।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pending_notices as $n): ?>
                            <tr>
                                <td class="py-2 english-text"><?php echo e($n['notice_no'] ?: 'Auto-Generate'); ?></td>
                                <td>
                                    <strong class="text-navy-custom"><?php echo e($n['title']); ?></strong>
                                    <?php if ($n['title_hindi']): ?>
                                        <br><small class="text-muted"><?php echo e($n['title_hindi']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($n['category']); ?></td>
                                <td>
                                    <span class="badge <?php echo ($n['priority'] === 'urgent') ? 'bg-danger text-white' : (($n['priority'] === 'important') ? 'bg-warning text-dark' : 'bg-light text-navy-custom border'); ?> font-size-xs px-2 py-0.5">
                                        <?php echo e($n['priority']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="<?php echo SITE_URL; ?>/admin/notices/preview.php?slug=<?php echo urlencode($n['slug']); ?>" target="_blank" class="btn btn-xs btn-outline-navy"><i class="bi bi-eye-fill"></i></a>
                                        <form method="POST" action="action.php" style="display:inline;">
                                            <?php csrfField(); ?>
                                            <input type="hidden" name="notice_id" value="<?php echo $n['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-xs btn-success fw-semibold" onclick="return confirm('क्या आप अनुमोदित करना चाहते हैं?');">अनुमोदन</button>
                                        </form>
                                        <a href="#" class="btn btn-xs btn-danger fw-semibold" onclick="var r = prompt('अस्वीकृति का कारण:'); if(r){ var f = document.createElement('form'); f.method='POST'; f.action='action.php'; var c = document.createElement('input'); c.type='hidden'; c.name='csrf_token'; c.value='<?php echo $_SESSION['csrf_token']; ?>'; f.appendChild(c); var i = document.createElement('input'); i.type='hidden'; i.name='notice_id'; i.value='<?php echo $n['id']; ?>'; f.appendChild(i); var a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='reject'; f.appendChild(a); var rm = document.createElement('input'); rm.type='hidden'; rm.name='remarks'; rm.value=r; f.appendChild(rm); document.body.appendChild(f); f.submit(); } return false;">अस्वीकार</a>
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

<!-- Published & Approved Lists -->
<div class="row g-4 font-hindi small">
    <div class="col-md-6 col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light py-2">
                <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-check-circle-fill text-success me-2"></i>हाल ही में स्वीकृत (Recently Approved)</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($approved_notices)): ?>
                        <li class="list-group-item text-center py-3 text-muted">कोई रिकॉर्ड नहीं।</li>
                    <?php else: ?>
                        <?php foreach ($approved_notices as $n): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-navy-custom"><?php echo e($n['title']); ?></strong>
                                    <small class="text-muted d-block font-size-xs"><?php echo e($n['category']); ?> | No: <?php echo e($n['notice_no']); ?></small>
                                </div>
                                <span class="badge bg-success font-size-xs">Approved</span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light py-2">
                <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-globe2 text-info me-2"></i>सक्रिय रूप से प्रकाशित (Published Notices)</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($published_notices)): ?>
                        <li class="list-group-item text-center py-3 text-muted">कोई सक्रिय रिकॉर्ड नहीं।</li>
                    <?php else: ?>
                        <?php foreach ($published_notices as $n): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-navy-custom"><?php echo e($n['title']); ?></strong>
                                    <small class="text-muted d-block font-size-xs"><?php echo e($n['category']); ?> | Date: <?php echo date('d-m-Y', strtotime($n['publish_at'] ?: $n['created_at'])); ?></small>
                                </div>
                                <span class="badge bg-primary font-size-xs">Published</span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
