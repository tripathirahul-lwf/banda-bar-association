<?php
/**
 * Global Admin Search Engine
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'वैश्विक खोज पटल (Global Admin Search)';
require_once __DIR__ . '/../includes/dashboard/header.php';

// Enforce admin permission
requireRole(['admin', 'mahasachiv', 'president']);

$db = Database::getConnection();
$q = sanitize($_GET['q'] ?? '');

$results = [
    'members' => [],
    'id_cards' => [],
    'notices' => [],
    'payments' => [],
    'fund' => [],
    'chambers' => [],
    'elections' => []
];

if ($db && !empty($q)) {
    $search_pattern = "%$q%";

    try {
        // 1. Search Members
        $stmt = $db->prepare("
            SELECT id, full_name, membership_no, enrollment_no, membership_status 
            FROM members 
            WHERE (full_name LIKE ? OR membership_no LIKE ? OR enrollment_no LIKE ? OR mobile LIKE ?)
            LIMIT 5
        ");
        $stmt->execute([$search_pattern, $search_pattern, $search_pattern, $search_pattern]);
        $results['members'] = $stmt->fetchAll();

        // 2. Search ID Cards
        $stmt = $db->prepare("
            SELECT ic.id, ic.card_number, ic.status, m.full_name 
            FROM id_cards ic
            JOIN members m ON m.id = ic.member_id
            WHERE ic.card_number LIKE ?
            LIMIT 5
        ");
        $stmt->execute([$search_pattern]);
        $results['id_cards'] = $stmt->fetchAll();

        // 3. Search Notices
        $stmt = $db->prepare("
            SELECT id, notice_no, title, status, category 
            FROM notices 
            WHERE (notice_no LIKE ? OR title LIKE ?)
            LIMIT 5
        ");
        $stmt->execute([$search_pattern, $search_pattern]);
        $results['notices'] = $stmt->fetchAll();

        // 4. Search Payments / Bar Fee
        $stmt = $db->prepare("
            SELECT bp.id, bp.receipt_no, bp.amount, bp.payment_status, m.full_name 
            FROM bar_fee_payments bp
            JOIN members m ON m.id = bp.member_id
            WHERE bp.receipt_no LIKE ?
            LIMIT 5
        ");
        $stmt->execute([$search_pattern]);
        $results['payments'] = $stmt->fetchAll();

        // 5. Search Fund Transactions
        $stmt = $db->prepare("
            SELECT id, transaction_no, receipt_no, voucher_no, amount, transaction_type, status 
            FROM association_fund_transactions 
            WHERE (transaction_no LIKE ? OR receipt_no LIKE ? OR voucher_no LIKE ?)
            LIMIT 5
        ");
        $stmt->execute([$search_pattern, $search_pattern, $search_pattern]);
        $results['fund'] = $stmt->fetchAll();

        // 6. Search Chambers
        $stmt = $db->prepare("
            SELECT c.id, c.chamber_no, c.status, m.full_name
            FROM chambers c
            LEFT JOIN chamber_allotments ca ON ca.chamber_id = c.id AND ca.status = 'active'
            LEFT JOIN members m ON m.id = ca.member_id
            WHERE c.chamber_no LIKE ?
            LIMIT 5
        ");
        $stmt->execute([$search_pattern]);
        $results['chambers'] = $stmt->fetchAll();

        // 7. Search Elections
        $stmt = $db->prepare("
            SELECT id, election_code, title, status 
            FROM elections 
            WHERE (election_code LIKE ? OR title LIKE ?)
            LIMIT 5
        ");
        $stmt->execute([$search_pattern, $search_pattern]);
        $results['elections'] = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Failed global search: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-search text-gold-custom me-2"></i>वैश्विक खोज परिणाम (Global Search Results)</h4>
    <span class="text-muted">खोज शब्द: <strong>"<?php echo e($q); ?>"</strong></span>
</div>

<div class="font-hindi small text-navy-custom">
    
    <div class="mb-4">
        <form method="GET" action="search.php" class="row g-2">
            <div class="col-md-9 col-8">
                <input type="text" name="q" class="form-control form-control-sm" value="<?php echo e($q); ?>" required placeholder="अधिवक्ता नाम, सदस्य क्रमांक, रसीद नंबर, नोटिस नंबर दर्ज करें...">
            </div>
            <div class="col-md-3 col-4">
                <button type="submit" class="btn btn-navy btn-sm w-100"><i class="bi bi-search me-1"></i>खोजें (Search)</button>
            </div>
        </form>
    </div>

    <?php 
    $total_found = array_sum(array_map('count', $results));
    if ($total_found === 0 && !empty($q)): 
    ?>
        <div class="text-center py-5 bg-white border rounded shadow-xs text-muted">
            <i class="bi bi-search-heart display-5 d-block mb-2"></i>
            <p class="mb-0">कोई परिणाम नहीं मिला। कृपया अन्य खोज शब्द का प्रयास करें।</p>
        </div>
    <?php elseif (!empty($q)): ?>
        
        <!-- Results Grouped -->
        <div class="row g-4">
            
            <!-- 1. Members -->
            <?php if (!empty($results['members'])): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-xs">
                        <div class="card-header bg-light py-2 fw-bold"><i class="bi bi-people-fill text-gold-dark me-2"></i>अधिवक्ता सदस्य (Advocate Members)</div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($results['members'] as $m): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo e($m['full_name']); ?></strong> (DBA Code: <?php echo e($m['membership_no']); ?>) | COP: <?php echo e($m['enrollment_no']); ?>
                                        </div>
                                        <div>
                                            <?php echo getStatusBadge($m['membership_status']); ?>
                                            <a href="members/view.php?id=<?php echo $m['id']; ?>" class="btn btn-xs btn-navy ms-2">View Profile</a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 2. ID Cards -->
            <?php if (!empty($results['id_cards'])): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-xs">
                        <div class="card-header bg-light py-2 fw-bold"><i class="bi bi-card-image text-gold-dark me-2"></i>डिजिटल पहचान पत्र (ID Cards)</div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($results['id_cards'] as $ic): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo e($ic['card_number']); ?></strong> (नाम: <?php echo e($ic['full_name']); ?>)
                                        </div>
                                        <div>
                                            <?php echo getStatusBadge($ic['status']); ?>
                                            <a href="id-cards/view.php?id=<?php echo $ic['id']; ?>" class="btn btn-xs btn-navy ms-2">View Card</a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 3. Notices -->
            <?php if (!empty($results['notices'])): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-xs">
                        <div class="card-header bg-light py-2 fw-bold"><i class="bi bi-bell-fill text-gold-dark me-2"></i>सूचना पट्ट (Notices)</div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($results['notices'] as $n): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo e($n['notice_no']); ?></strong>: <?php echo e($n['title']); ?> (श्रेणी: <?php echo e($n['category']); ?>)
                                        </div>
                                        <div>
                                            <?php echo getStatusBadge($n['status']); ?>
                                            <a href="notices/index.php" class="btn btn-xs btn-navy ms-2">Notice Master</a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 4. Bar Fee Payments -->
            <?php if (!empty($results['payments'])): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-xs">
                        <div class="card-header bg-light py-2 fw-bold"><i class="bi bi-currency-rupee text-gold-dark me-2"></i>अधिवक्ता शुल्क रसीदें (Bar Fee Receipts)</div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($results['payments'] as $p): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            रसीद संख्या: <strong><?php echo e($p['receipt_no']); ?></strong> | प्रेषक: <?php echo e($p['full_name']); ?> | राशि: <?php echo formatCurrency($p['amount']); ?>
                                        </div>
                                        <div>
                                            <?php echo getStatusBadge($p['payment_status']); ?>
                                            <a href="advocate-fee/payments.php" class="btn btn-xs btn-navy ms-2">Fee Registry</a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 5. Fund Transactions -->
            <?php if (!empty($results['fund'])): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-xs">
                        <div class="card-header bg-light py-2 fw-bold"><i class="bi bi-bank text-gold-dark me-2"></i>संघीय कोष लेनदेन (Fund Transactions)</div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($results['fund'] as $f): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            ट्रांजैक्शन: <strong><?php echo e($f['transaction_no']); ?></strong> (रसीद/वाउचर: <?php echo e($f['receipt_no'] ?: $f['voucher_no'] ?: '-'); ?>) | प्रकार: <span class="badge bg-light text-navy-custom"><?php echo ucfirst($f['transaction_type']); ?></span> | राशि: <?php echo formatCurrency($f['amount']); ?>
                                        </div>
                                        <div>
                                            <?php echo getStatusBadge($f['status']); ?>
                                            <a href="association-fund/index.php" class="btn btn-xs btn-navy ms-2">Fund Registry</a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 6. Chambers -->
            <?php if (!empty($results['chambers'])): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-xs">
                        <div class="card-header bg-light py-2 fw-bold"><i class="bi bi-door-closed text-gold-dark me-2"></i>चैंबर आवंटन (Chambers Occupancy)</div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($results['chambers'] as $c): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            चैंबर क्रमांक: <strong><?php echo e($c['chamber_no']); ?></strong> | वर्तमान आवंटी: <?php echo e($c['full_name'] ?: 'None (खाली)'); ?>
                                        </div>
                                        <div>
                                            <?php echo getStatusBadge($c['status']); ?>
                                            <a href="rooms/index.php" class="btn btn-xs btn-navy ms-2">Chambers Registry</a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 7. Elections -->
            <?php if (!empty($results['elections'])): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-xs">
                        <div class="card-header bg-light py-2 fw-bold"><i class="bi bi-check2-square text-gold-dark me-2"></i>चुनाव एवं वोटर सूची (Elections)</div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($results['elections'] as $el): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            कोड: <strong><?php echo e($el['election_code']); ?></strong> | शीर्षक: <?php echo e($el['title']); ?>
                                        </div>
                                        <div>
                                            <?php echo getStatusBadge($el['status']); ?>
                                            <a href="elections/view.php?id=<?php echo $el['id']; ?>" class="btn btn-xs btn-navy ms-2">View Election</a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    <?php endif; ?>
</div>

<?php 
require_once __DIR__ . '/../includes/dashboard/footer.php';
?>
