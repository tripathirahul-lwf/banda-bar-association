<?php
/**
 * Admin Document-wise Wakalatnama Download Statistics & History
 * District Bar Association, Banda
 */

$pageTitle = 'दस्तावेज डाउनलोड सांख्यिकी (Document Stats)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$doc = null;
$downloads_today = 0;
$downloads_month = 0;
$unique_members = 0;
$recent_downloads = [];
$other_versions = [];

$db = Database::getConnection();

if ($db && $doc_id > 0) {
    try {
        // Fetch document info
        $stmt = $db->prepare("SELECT * FROM wakalatnamas WHERE id = ?");
        $stmt->execute([$doc_id]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            // Count downloads today
            $stmt = $db->prepare("SELECT COUNT(*) FROM wakalatnama_downloads WHERE wakalatnama_id = ? AND DATE(downloaded_at) = CURRENT_DATE()");
            $stmt->execute([$doc_id]);
            $downloads_today = $stmt->fetchColumn();

            // Count downloads this month
            $stmt = $db->prepare("SELECT COUNT(*) FROM wakalatnama_downloads WHERE wakalatnama_id = ? AND MONTH(downloaded_at) = MONTH(CURRENT_DATE()) AND YEAR(downloaded_at) = YEAR(CURRENT_DATE())");
            $stmt->execute([$doc_id]);
            $downloads_month = $stmt->fetchColumn();

            // Unique members count
            $stmt = $db->prepare("SELECT COUNT(DISTINCT member_id) FROM wakalatnama_downloads WHERE wakalatnama_id = ?");
            $stmt->execute([$doc_id]);
            $unique_members = $stmt->fetchColumn();

            // Recent downloads
            $stmt = $db->prepare("
                SELECT d.*, m.full_name, m.membership_no, m.enrollment_no 
                FROM wakalatnama_downloads d
                JOIN members m ON m.id = d.member_id
                WHERE d.wakalatnama_id = ?
                ORDER BY d.id DESC LIMIT 10
            ");
            $stmt->execute([$doc_id]);
            $recent_downloads = $stmt->fetchAll();

            // Fetch other versions of this same document title
            $stmt = $db->prepare("
                SELECT id, version, status, effective_from, 
                       (SELECT COUNT(*) FROM wakalatnama_downloads WHERE wakalatnama_id = w.id) as version_downloads
                FROM wakalatnamas w 
                WHERE title = ? AND id != ?
                ORDER BY id DESC
            ");
            $stmt->execute([$doc['title'], $doc_id]);
            $other_versions = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Failed to load document statistics: " . $e->getMessage());
    }
}

if (!$doc) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: वकालतनामा रिकॉर्ड नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">दस्तावेज सांख्यिकी रिपोर्ट (Document Statistics)</h4>
    <a href="index.php" class="btn btn-outline-navy btn-sm">वापस सूची पर जाएं</a>
</div>

<!-- Metadata and Stats Cards -->
<div class="row g-3 font-hindi mb-4">
    <!-- Document Info Card -->
    <div class="col-md-6 col-12">
        <div class="card p-3 border-0 bg-light-custom h-100 small text-muted">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-2"><i class="bi bi-file-earmark-pdf me-1"></i>दस्तावेज विवरण</h6>
            <span>शीर्षक: <strong class="text-navy-custom english-text"><?php echo e($doc['title']); ?></strong></span>
            <span class="d-block">श्रेणी: <strong class="text-navy-custom"><?php echo e($doc['category']); ?></strong></span>
            <span>संस्करण: <strong class="text-navy-custom english-text">v<?php echo e($doc['version']); ?></strong> | स्थिति: <span class="badge bg-navy-custom"><?php echo e($doc['status']); ?></span></span>
            <span class="d-block mt-1">लागू तिथि: <strong class="english-text"><?php echo date('d-m-Y', strtotime($doc['effective_from'])); ?></strong> से <?php echo $doc['effective_until'] ? date('d-m-Y', strtotime($doc['effective_until'])) : 'अनंतकाल'; ?></span>
            <span class="d-block mt-1">मूल फ़ाइल नाम: <strong class="english-text" style="font-size: 0.65rem;"><?php echo e($doc['original_file_name']); ?></strong> (<?php echo round($doc['file_size']/1024, 1); ?> KB)</span>
        </div>
    </div>
    
    <!-- Stats widgets -->
    <div class="col-md-6 col-12">
        <div class="row g-2 h-100">
            <div class="col-6">
                <div class="card p-3 border-0 bg-navy-custom text-white text-center h-100 justify-content-center shadow-xs">
                    <span class="small d-block mb-1">कुल अनूठे सदस्य</span>
                    <h3 class="fw-bold mb-0 english-text"><?php echo $unique_members; ?></h3>
                </div>
            </div>
            <div class="col-6">
                <div class="row g-2 h-100">
                    <div class="col-12">
                        <div class="card p-2 border-0 bg-success text-white text-center h-100 justify-content-center shadow-xs">
                            <span class="d-block" style="font-size:0.68rem;">आज के डाउनलोड्स</span>
                            <h5 class="fw-bold mb-0 english-text"><?php echo $downloads_today; ?></h5>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card p-2 border-0 bg-warning text-navy-custom text-center h-100 justify-content-center shadow-xs">
                            <span class="d-block" style="font-size:0.68rem;">इस माह के डाउनलोड्स</span>
                            <h5 class="fw-bold mb-0 english-text"><?php echo $downloads_month; ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 font-hindi small mb-5">
    <!-- Left Column: Recent Downloads list -->
    <div class="col-lg-8 col-12">
        <div class="border rounded-3 p-3 bg-white shadow-sm h-100">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-clock-history text-gold-dark me-2"></i>हाल ही के डाउनलोड्स (Recent downloads)</h6>
            
            <?php if (empty($recent_downloads)): ?>
                <div class="text-center py-4 text-muted">इस संस्करण के लिए कोई डाउनलोड्स रिकॉर्ड नहीं हैं।</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 text-muted">
                        <thead>
                            <tr class="table-light">
                                <th>सदस्य का नाम</th>
                                <th>सदस्य संख्या</th>
                                <th>पंजीकरण संख्या</th>
                                <th>आईपी पता</th>
                                <th class="text-end">डाउनलोड समय</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_downloads as $r): ?>
                                <tr>
                                    <td><strong class="text-navy-custom english-text"><?php echo e($r['full_name']); ?></strong></td>
                                    <td class="english-text"><?php echo e($r['membership_no']); ?></td>
                                    <td class="english-text"><?php echo e($r['enrollment_no']); ?></td>
                                    <td class="english-text"><?php echo e($r['ip_address'] ?: 'N/A'); ?></td>
                                    <td class="text-end english-text"><?php echo date('d-m-Y H:i', strtotime($r['downloaded_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Document versions history mapping -->
    <div class="col-lg-4 col-12">
        <div class="border rounded-3 p-3 bg-white shadow-sm h-100">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-layers-fill text-gold-dark me-2"></i>अन्य संस्करण विवरण (Versions list)</h6>
            
            <?php if (empty($other_versions)): ?>
                <div class="text-center py-4 text-muted">कोई अन्य संस्करण उपलब्ध नहीं हैं।</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($other_versions as $ov): ?>
                        <div class="p-2 border rounded bg-light-custom position-relative">
                            <span class="fw-bold text-navy-custom d-block mb-1">Version v<?php echo e($ov['version']); ?></span>
                            <span class="d-block" style="font-size:0.65rem;">लागू: <?php echo date('d-m-Y', strtotime($ov['effective_from'])); ?> | डाउनलोड्स: <strong class="text-navy-custom"><?php echo $ov['version_downloads']; ?></strong></span>
                            
                            <div class="d-flex gap-2 align-items-center justify-content-between mt-2 pt-2 border-top border-light">
                                <span class="badge bg-secondary font-size-xs px-2 py-0.5"><?php echo e($ov['status']); ?></span>
                                <a href="history.php?id=<?php echo $ov['id']; ?>" class="btn btn-xs btn-outline-navy py-0 px-2 fw-semibold" style="font-size:0.6rem;">विवरण देखें</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
