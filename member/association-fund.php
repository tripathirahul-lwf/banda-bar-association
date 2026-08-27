<?php
/**
 * Member Association Fund Viewer Page
 * District Bar Association, Banda
 */

$pageTitle = 'संघीय कोष विवरण (Association Fund Details)';
require_once __DIR__ . '/../includes/dashboard/header.php';

// Enforce Member Role
requireRole('member');

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

            // Fetch published documents visible to members or public
            $stmt = $db->prepare("
                SELECT * FROM association_financial_documents 
                WHERE status = 'published' AND visibility IN ('public', 'members_only')
                ORDER BY published_at DESC
            ");
            $stmt->execute();
            $published_docs = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Member association fund page error: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">संघीय कोष पटल (Association Fund Viewer)</h4>
    <span class="badge bg-primary text-white px-3 py-1 fw-bold">सदस्य ज़ोन (Secure)</span>
</div>

<div class="row g-4 font-hindi small">
    <div class="col-lg-8 col-12">
        <!-- Balance stats -->
        <div class="card border-0 shadow-sm p-4 mb-4 bg-white text-navy-custom">
            <h5 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-wallet2 text-gold-custom me-2"></i>कोष विवरण (Financial Summary Balance)</h5>
            <p class="text-muted">सक्रिय वित्तीय वर्ष <strong><?php echo e($active_fy['name'] ?? '2026-27'); ?></strong> के आधिकारिक आंकड़े निम्नवत हैं:</p>
            
            <div class="row g-3 text-center my-2 text-uppercase">
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
        </div>

        <!-- Documents lists -->
        <div class="card border-0 shadow-sm p-4 bg-white text-navy-custom">
            <h5 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>अंकेक्षित रिपोर्ट एवं वित्तीय लेखा-जोखा (Audit Documents)</h5>
            
            <?php if (empty($published_docs)): ?>
                <p class="text-muted text-center py-4 mb-0">कोई दस्तावेज उपलब्ध नहीं है।</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($published_docs as $doc): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-1">
                            <div>
                                <strong class="text-navy-custom"><?php echo e($doc['title']); ?></strong>
                                <small class="text-muted d-block mt-0.5">प्रकार: <?php echo e($doc['document_type']); ?> | दिनांक: <?php echo date('d-m-Y', strtotime($doc['published_at'] ?: $doc['created_at'])); ?></small>
                            </div>
                            <a href="../uploads/financials/<?php echo e($doc['file_path']); ?>" target="_blank" class="btn btn-outline-danger btn-sm fw-semibold"><i class="bi bi-download me-1"></i> डाउनलोड PDF</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Sidebar info -->
    <div class="col-lg-4 col-12">
        <div class="border rounded-3 p-3 bg-light-custom">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-info-circle-fill text-gold-custom me-2"></i>सहायक विवरण</h6>
            <p class="text-muted small" style="line-height: 1.6;">
                यह पोर्टल जिला अधिवक्ता संघ, बांदा के सम्मानित सदस्यों को वित्तीय सूचनाएं पारदर्शी रूप से प्रस्तुत करने का सुरक्षित मंच है।
            </p>
            <div class="alert alert-info border-0 p-2 font-size-xs mb-0">
                यदि आपको संघ के वित्तीय व्यवहारों या प्रकाशित खातों में किसी प्रकार की विसंगति दृष्टिगत होती है, तो आप लिखित आपत्ति महासचिव कार्यालय को प्रेषित कर सकते हैं।
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/dashboard/footer.php';
?>
