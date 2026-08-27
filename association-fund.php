<?php
/**
 * Public Association Fund Overview & Documents Download Page
 * District Bar Association, Banda
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$db = Database::getConnection();

$active_fy = null;
$summary = [
    'opening_balance' => 0.00,
    'total_income' => 0.00,
    'total_expense' => 0.00,
    'current_balance' => 0.00
];
$published_docs = [];

if ($db) {
    try {
        // Resolve active year
        $active_fy = $db->query("SELECT * FROM financial_years WHERE status = 'active' LIMIT 1")->fetch();

        if ($active_fy) {
            $summary = getAssociationFundSummary($active_fy['id'], $db);

            // Fetch published documents visible publicly
            $stmt = $db->prepare("
                SELECT * FROM association_financial_documents 
                WHERE status = 'published' AND visibility = 'public'
                ORDER BY published_at DESC
            ");
            $stmt->execute();
            $published_docs = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Public association fund loading failed: " . $e->getMessage());
    }
}

// Layout header
$pageTitle = 'जिला अधिवक्ता संघ कोष (Association Fund)';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Banner Section -->
<div class="bg-navy-custom text-white py-5 mb-5 font-hindi position-relative">
    <div class="container text-center">
        <h2 class="fw-bold mb-1">एसोसिएशन फंड पटल (Association Fund Portal)</h2>
        <p class="lead small text-light-custom">पारदर्शिता एवं वित्तीय उत्तरदायित्व - जिला अधिवक्ता संघ, बांदा</p>
    </div>
</div>

<div class="container mb-5 font-hindi small">
    <div class="row g-4">
        <!-- Balance widgets -->
        <div class="col-lg-8 col-12">
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h5 class="fw-bold text-navy-custom border-bottom pb-2 mb-3">
                    <i class="bi bi-wallet2 text-gold-custom me-2"></i>कोष विवरण (Fund Summary Balance)
                </h5>
                
                <?php if (!SHOW_ASSOCIATION_FUND_SUMMARY_PUBLICLY): ?>
                    <div class="alert alert-info border-0 rounded-3 py-3 mb-0">
                        <i class="bi bi-shield-lock-fill me-2"></i>संघीय कोष के आंतरिक आंकड़े सार्वजनिक दृश्यता हेतु प्रतिबंधित हैं। केवल अधिकृत सदस्य ही लॉगिन उपरांत इसे देख सकते हैं।
                    </div>
                <?php else: ?>
                    <p class="text-muted">वित्तीय वर्ष <strong><?php echo e($active_fy['name'] ?? '2026-27'); ?></strong> के स्वीकृत आधिकारिक कोष आंकड़े निम्नलिखित हैं:</p>
                    
                    <div class="row g-3 text-center my-2">
                        <div class="col-md-3 col-6">
                            <div class="p-3 border rounded bg-light">
                                <span class="text-muted font-size-xs d-block">प्रारंभिक शेष</span>
                                <h5 class="fw-bold mb-0 text-navy-custom">₹<?php echo number_format($summary['opening_balance'], 2); ?></h5>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="p-3 border rounded bg-success bg-opacity-10 text-success">
                                <span class="text-muted font-size-xs d-block">कुल आय</span>
                                <h5 class="fw-bold mb-0">₹<?php echo number_format($summary['total_income'], 2); ?></h5>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="p-3 border rounded bg-danger bg-opacity-10 text-danger">
                                <span class="text-muted font-size-xs d-block">कुल व्यय</span>
                                <h5 class="fw-bold mb-0">₹<?php echo number_format($summary['total_expense'], 2); ?></h5>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="p-3 border rounded bg-navy-custom text-white">
                                <span class="text-light-custom font-size-xs d-block">वर्तमान शेष</span>
                                <h5 class="fw-bold mb-0 text-gold-custom">₹<?php echo number_format($summary['current_balance'], 2); ?></h5>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Published Documents Section -->
            <div class="card border-0 shadow-sm p-4">
                <h5 class="fw-bold text-navy-custom border-bottom pb-2 mb-3">
                    <i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>प्रकाशित वार्षिक खाते व ऑडिट रिपोर्ट (Published Financial Reports)
                </h5>
                
                <?php if (empty($published_docs)): ?>
                    <p class="text-muted text-center py-4 mb-0"><i class="bi bi-folder-x display-6 d-block mb-1 text-secondary"></i>कोई प्रकाशित वित्तीय रिपोर्ट उपलब्ध नहीं है।</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($published_docs as $doc): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-1">
                                <div>
                                    <strong class="text-navy-custom"><?php echo e($doc['title']); ?></strong>
                                    <small class="text-muted d-block mt-0.5">प्रकार: <?php echo e($doc['document_type']); ?> | दिनांक: <?php echo date('d-m-Y', strtotime($doc['published_at'] ?: $doc['created_at'])); ?></small>
                                </div>
                                <!-- Directly downloadable since they are public files under storage financials -->
                                <a href="uploads/financials/<?php echo e($doc['file_path']); ?>" target="_blank" class="btn btn-outline-danger btn-sm fw-semibold"><i class="bi bi-download me-1"></i> डाउनलोड PDF</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar context -->
        <div class="col-lg-4 col-12">
            <div class="border rounded-3 p-3 bg-light-custom h-100">
                <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-info-circle-fill text-gold-custom me-2"></i>अधिसूचना व नियम</h6>
                <p class="text-muted small" style="line-height: 1.6;">
                    संघ के नियमों के अंतर्गत, प्रत्येक वित्तीय वर्ष की समाप्ति पर कार्यकारिणी द्वारा नियुक्त चार्टर्ड एकाउंटेंट द्वारा खातों का अंकेक्षण (Audit) किया जाता है। अंकेक्षित विवरणों को सदस्यों की आम सभा के सम्मुख प्रस्तुत कर पारित कराया जाता है।
                </p>
                <div class="alert alert-warning border-0 p-2 font-size-xs mb-0">
                    <strong>नोट:</strong> विस्तृत बही खाता (Ledger details) देखने के लिए संघ के सदस्य अपने क्रेडेंशियल के साथ लॉगिन कर सदस्य सेवा पटल पर जा सकते हैं।
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/includes/footer.php';
?>
