<?php
/**
 * Member Wakalatnama Listing & Downloads
 * District Bar Association, Banda
 */

$pageTitle = 'वकालतनामा (Wakalatnama)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce member role
requireRole('member');

$user = currentUser();
$member_id = $user['member_id'] ?? 0;
$member = null;
$eligible = false;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';

$db = Database::getConnection();

if ($db && $member_id > 0) {
    try {
        // Fetch member master info
        $m_stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $m_stmt->execute([$member_id]);
        $member = $m_stmt->fetch();
        
        if ($member && $member['membership_status'] === 'active' && $user['status'] === 'active') {
            $eligible = true;
        }
    } catch (PDOException $e) {
        error_log("Failed to check member status: " . $e->getMessage());
    }
}

// Fetch active available documents if eligible
$documents = [];
if ($eligible && $db) {
    try {
        $conditions = [
            "status = 'active'",
            "effective_from <= CURRENT_DATE()",
            "(effective_until IS NULL OR effective_until >= CURRENT_DATE())"
        ];
        $params = [];
        
        if (!empty($search)) {
            $conditions[] = "(title LIKE ? OR title_hindi LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($category_filter)) {
            $conditions[] = "category = ?";
            $params[] = $category_filter;
        }
        
        $where = implode(" AND ", $conditions);
        $stmt = $db->prepare("SELECT * FROM wakalatnamas WHERE $where ORDER BY id DESC");
        $stmt->execute($params);
        $documents = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch available wakalatnamas: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">वकालतनामा डाउनलोड पटल (Wakalatnama)</h4>
    <div class="d-flex gap-2">
        <a href="history.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-clock-history me-1"></i>मेरा डाउनलोड इतिहास (History)</a>
    </div>
</div>

<p class="text-muted font-hindi small mb-4"><strong>केवल सक्रिय एवं पंजीकृत सदस्यों के लिए (For Active & Registered Members Only)</strong></p>

<?php if (!$eligible): ?>
    <!-- INELIGIBLE MEMBER WARNING -->
    <div class="alert alert-danger border-0 font-hindi shadow-xs p-4 mb-4">
        <i class="bi bi-x-octagon-fill display-5 text-danger d-block mb-2"></i>
        <h5 class="fw-bold">डाउनलोड प्रतिबंधित (Download Blocked)</h5>
        <p class="mb-0 text-dark-custom">
            आपकी वर्तमान सदस्यता स्थिति के कारण Wakalatnama डाउनलोड उपलब्ध नहीं है। कृपया संघ कार्यालय से संपर्क करें।
        </p>
    </div>
<?php else: ?>
    
    <!-- Search / Filter Card -->
    <div class="card p-3 mb-4 border-0 shadow-sm font-hindi small">
        <form method="GET" action="index.php" class="row g-3">
            <div class="col-md-6">
                <input type="text" class="form-control form-control-sm" name="search" placeholder="खोजें: शीर्षक या नाम..." value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-4">
                <select class="form-select form-select-sm" name="category">
                    <option value="">सभी श्रेणियां (All Categories)</option>
                    <option value="General" <?php echo ($category_filter === 'General') ? 'selected' : ''; ?>>General</option>
                    <option value="Civil" <?php echo ($category_filter === 'Civil') ? 'selected' : ''; ?>>Civil</option>
                    <option value="Criminal" <?php echo ($category_filter === 'Criminal') ? 'selected' : ''; ?>>Criminal</option>
                    <option value="Family Court" <?php echo ($category_filter === 'Family Court') ? 'selected' : ''; ?>>Family Court</option>
                    <option value="Revenue" <?php echo ($category_filter === 'Revenue') ? 'selected' : ''; ?>>Revenue</option>
                    <option value="Other" <?php echo ($category_filter === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-navy btn-sm fw-semibold">खोजें</button>
            </div>
        </form>
    </div>

    <!-- Documents Cards Grid -->
    <?php if (empty($documents)): ?>
        <div class="card p-5 text-center border-0 shadow-sm font-hindi rounded-3">
            <i class="bi bi-file-earmark-pdf display-3 text-muted mb-3 d-block"></i>
            <h5 class="fw-bold text-navy-custom">वर्तमान में कोई Wakalatnama डाउनलोड के लिए उपलब्ध नहीं है।</h5>
            <p class="text-muted small mb-0">कृपया बाद में जांचें या संघ व्यवस्थापक से संपर्क करें।</p>
        </div>
    <?php else: ?>
        <div class="row g-3 font-hindi small mb-5">
            <?php foreach ($documents as $doc): ?>
                <div class="col-lg-6 col-12">
                    <div class="card p-3 border border-light shadow-xs h-100 bg-white">
                        <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                            <div>
                                <h6 class="fw-bold text-navy-custom mb-1 english-text"><?php echo e($doc['title']); ?></h6>
                                <?php if (!empty($doc['title_hindi'])): ?>
                                    <span class="text-secondary fw-semibold d-block" style="font-size:0.75rem;"><?php echo e($doc['title_hindi']); ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-light text-navy-custom border px-2 py-1"><?php echo e($doc['category']); ?></span>
                        </div>
                        
                        <p class="text-muted mb-3" style="font-size: 0.72rem; line-height: 1.4; flex: 1;">
                            <?php echo e($doc['description'] ?: 'कोई अतिरिक्त विवरण नहीं।'); ?>
                        </p>
                        
                        <div class="row text-muted border-top pt-2 g-2" style="font-size: 0.68rem;">
                            <div class="col-6">
                                <span>संस्करण (Version):</span>
                                <strong class="text-navy-custom d-block english-text"><?php echo e($doc['version']); ?></strong>
                            </div>
                            <div class="col-6">
                                <span>लागू तिथि:</span>
                                <strong class="text-navy-custom d-block english-text"><?php echo date('d-m-Y', strtotime($doc['effective_from'])); ?></strong>
                            </div>
                            <div class="col-6">
                                <span>फ़ाइल का प्रकार:</span>
                                <strong class="text-navy-custom d-block english-text"><?php echo strtoupper($doc['file_type']); ?> (<?php echo round($doc['file_size']/1024, 1); ?> KB)</strong>
                            </div>
                            <div class="col-6">
                                <span>अपडेट तिथि:</span>
                                <strong class="text-navy-custom d-block english-text"><?php echo date('d-m-Y', strtotime($doc['updated_at'])); ?></strong>
                            </div>
                        </div>

                        <!-- Secure Download Trigger via POST -->
                        <form method="POST" action="download.php" class="d-grid mt-3">
                            <?php csrfField(); ?>
                            <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                            <button type="submit" class="btn btn-navy py-2 fw-semibold">
                                <i class="bi bi-file-earmark-arrow-down-fill text-gold-custom me-2"></i>वकालतनामा डाउनलोड (Download PDF)
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
