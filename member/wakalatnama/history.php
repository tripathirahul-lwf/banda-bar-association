<?php
/**
 * Member Personal Download History List
 * District Bar Association, Banda
 */

$pageTitle = 'मेरा डाउनलोड इतिहास (My Downloads)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce member role
requireRole('member');

$user = currentUser();
$member_id = $user['member_id'] ?? 0;
$downloads = [];

$db = Database::getConnection();

if ($db && $member_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT d.*, w.title, w.title_hindi, w.category, w.status as current_status
            FROM wakalatnama_downloads d
            JOIN wakalatnamas w ON w.id = d.wakalatnama_id
            WHERE d.member_id = ?
            ORDER BY d.id DESC
        ");
        $stmt->execute([$member_id]);
        $downloads = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load personal download history: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>वकालतनामा डाउनलोड इतिहास (My History)</h4>
    <a href="index.php" class="btn btn-outline-navy btn-sm">दस्तावेज सूची</a>
</div>

<div class="table-responsive bg-white rounded shadow-sm border font-hindi small mb-5">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th class="py-2">शीर्षक (Title)</th>
                <th>श्रेणी (Category)</th>
                <th>डाउनलोड संस्करण</th>
                <th>दिनांक व समय</th>
                <th>सक्रिय फ़ाइल</th>
                <th class="text-end">कार्य</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($downloads)): ?>
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">आपने अभी तक कोई वकालतनामा डाउनलोड नहीं किया है।</td>
                </tr>
            <?php else: ?>
                <?php foreach ($downloads as $d): ?>
                    <tr>
                        <td class="py-2">
                            <strong class="text-navy-custom d-block english-text"><?php echo e($d['title']); ?></strong>
                            <?php if (!empty($d['title_hindi'])): ?>
                                <small class="text-muted"><?php echo e($d['title_hindi']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-navy-custom border"><?php echo e($d['category']); ?></span>
                        </td>
                        <td class="english-text">v<?php echo e($d['version']); ?></td>
                        <td class="english-text"><?php echo date('d-m-Y H:i', strtotime($d['downloaded_at'])); ?></td>
                        <td>
                            <?php if ($d['current_status'] === 'active'): ?>
                                <span class="badge bg-success font-size-xs px-2 py-0.5">सक्रिय (Available)</span>
                            <?php else: ?>
                                <span class="badge bg-secondary font-size-xs px-2 py-0.5">संग्रहीत (Archived)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($d['current_status'] === 'active'): ?>
                                <form method="POST" action="download.php" class="d-inline">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="id" value="<?php echo $d['wakalatnama_id']; ?>">
                                    <button type="submit" class="btn btn-xs btn-navy fw-semibold">
                                        <i class="bi bi-file-earmark-arrow-down-fill text-gold-custom me-1"></i>नवीनतम डाउनलोड
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-xs btn-light disabled font-size-xs" style="font-size: 0.65rem;">Archived Version</button>
                            <?php endif; ?>
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
