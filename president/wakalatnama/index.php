<?php
/**
 * President Wakalatnama Approvals & Active Documents Monitoring
 * District Bar Association, Banda
 */

$pageTitle = 'वकालतनामा समीक्षा (Wakalatnama Review)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce president role
requireRole('president');

$db = Database::getConnection();
$pending_docs = [];
$active_docs = [];

$require_approval = defined('WAKALATNAMA_REQUIRE_APPROVAL') ? WAKALATNAMA_REQUIRE_APPROVAL : false;

if ($db) {
    try {
        // Fetch Pending Approvals
        $stmt = $db->query("
            SELECT w.*, u.username as uploader_name 
            FROM wakalatnamas w
            LEFT JOIN users u ON u.id = w.uploaded_by
            WHERE w.status = 'pending_approval'
            ORDER BY w.id DESC
        ");
        $pending_docs = $stmt->fetchAll();

        // Fetch Active Available Documents
        $stmt = $db->query("
            SELECT w.*, u.username as uploader_name,
                   (SELECT COUNT(*) FROM wakalatnama_downloads WHERE wakalatnama_id = w.id) as total_downloads
            FROM wakalatnamas w
            LEFT JOIN users u ON u.id = w.uploaded_by
            WHERE w.status = 'active'
            ORDER BY w.id DESC
        ");
        $active_docs = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("President failed to load documents: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">वकालतनामा अनुमोदन एवं नियंत्रण (President Desk)</h4>
    <span class="badge <?php echo $require_approval ? 'bg-success' : 'bg-secondary'; ?>">
        <?php echo $require_approval ? 'अनुमोदन प्रणाली: सक्रिय' : 'अनुमोदन प्रणाली: निष्क्रिय'; ?>
    </span>
</div>

<!-- Pending Approvals section -->
<?php if ($require_approval): ?>
    <h5 class="text-navy-custom font-hindi fw-bold mb-3"><i class="bi bi-clock-history text-gold-custom me-2"></i>स्वीकृति हेतु लंबित वकालतनामा (Pending Approvals)</h5>
    
    <div class="table-responsive bg-white rounded shadow-sm border font-hindi small mb-4">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="py-2">शीर्षक (Title)</th>
                    <th>श्रेणी</th>
                    <th>संस्करण</th>
                    <th>लागू तिथि</th>
                    <th>अपलोड कर्ता</th>
                    <th class="text-end">त्वरित कार्रवाई</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pending_docs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">स्वीकृति हेतु कोई दस्तावेज लंबित नहीं है।</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pending_docs as $p): ?>
                        <tr>
                            <td class="py-2">
                                <strong class="text-navy-custom d-block english-text"><?php echo e($p['title']); ?></strong>
                                <?php if (!empty($p['title_hindi'])): ?>
                                    <small class="text-muted"><?php echo e($p['title_hindi']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($p['category']); ?></td>
                            <td class="english-text">v<?php echo e($p['version']); ?></td>
                            <td class="english-text"><?php echo date('d-m-Y', strtotime($p['effective_from'])); ?></td>
                            <td class="english-text text-muted"><?php echo e($p['uploader_name'] ?: 'System'); ?></td>
                            <td class="text-end">
                                <form method="POST" action="action.php" class="d-inline-flex gap-1">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="doc_id" value="<?php echo $p['id']; ?>">
                                    <input type="text" name="remarks" class="form-control form-control-xs" placeholder="टिप्पणी (रिमार्क)" style="width:140px; font-size:0.7rem; padding: 2px 5px; height: auto;">
                                    <button type="submit" name="action" value="approve" class="btn btn-xs btn-success fw-semibold"><i class="bi bi-check-circle me-1"></i>स्वीकृत</button>
                                    <button type="submit" name="action" value="reject" class="btn btn-xs btn-danger fw-semibold">अस्वीकृत</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Active documents monitoring -->
<h5 class="text-navy-custom font-hindi fw-bold mb-3"><i class="bi bi-shield-check text-gold-custom me-2"></i>वर्तमान सक्रिय वकालतनामा (Active & Available Documents)</h5>

<div class="table-responsive bg-white rounded shadow-sm border font-hindi small mb-5">
    <table class="table table-hover align-middle mb-0 text-muted">
        <thead class="table-light">
            <tr>
                <th class="py-2">शीर्षक (Title)</th>
                <th>श्रेणी</th>
                <th>संस्करण</th>
                <th>प्रभावी तिथि</th>
                <th>कुल डाउनलोड</th>
                <th>सुरक्षा स्थिति</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($active_docs)): ?>
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">कोई सक्रिय वकालतनामा नहीं मिला।</td>
                </tr>
            <?php else: ?>
                <?php foreach ($active_docs as $a): ?>
                    <tr>
                        <td class="py-2"><strong class="text-navy-custom english-text"><?php echo e($a['title']); ?></strong></td>
                        <td><?php echo e($a['category']); ?></td>
                        <td class="english-text">v<?php echo e($a['version']); ?></td>
                        <td class="english-text"><?php echo date('d-m-Y', strtotime($a['effective_from'])); ?></td>
                        <td class="english-text text-navy-custom fw-semibold"><?php echo $a['total_downloads']; ?> times</td>
                        <td>
                            <span class="badge bg-success font-size-xs px-2 py-0.5"><i class="bi bi-shield-lock-fill me-1"></i>सक्रिय व सुरक्षित</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
