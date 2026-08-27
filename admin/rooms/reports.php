<?php
/**
 * Room / Chamber Rent Operational Reports Dashboard
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

// Select Report Type
$report_type = sanitize($_GET['type'] ?? 'register');
$block_filter = sanitize($_GET['block'] ?? '');
$status_filter = sanitize($_GET['status'] ?? '');
$month_filter = sanitize($_GET['month'] ?? ''); // Format: YYYY-MM

// -----------------------------
// CSV EXPORT LOGIC (Phase 9)
// -----------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "chamber_" . $report_type . "_" . date('Ymd_His') . ".csv";
    
    // Set headers
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    
    $output = fopen("php://output", "w");
    // UTF-8 BOM for Excel Hindi character compatibility
    fwrite($output, "\xEF\xBB\xBF");

    if ($report_type === 'register') {
        fputcsv($output, ['Chamber No', 'Block Name', 'Floor', 'Type', 'Occupancy Type', 'Capacity', 'Monthly Rent', 'Security Deposit', 'Status']);
        
        $sql = "SELECT * FROM chambers WHERE 1=1";
        $p = [];
        if (!empty($block_filter)) { $sql .= " AND block_name = ?"; $p[] = $block_filter; }
        if (!empty($status_filter)) { $sql .= " AND status = ?"; $p[] = $status_filter; }
        $sql .= " ORDER BY chamber_no ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($p);
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['chamber_no'],
                $row['block_name'],
                $row['floor'],
                $row['chamber_type'],
                $row['occupancy_type'],
                $row['capacity'],
                $row['monthly_rent'],
                $row['security_deposit'],
                $row['status']
            ]);
        }
        
    } elseif ($report_type === 'allotment') {
        fputcsv($output, ['Allotment No', 'Chamber No', 'Block Name', 'Member Name', 'DBA Code', 'Allotment Date', 'Monthly Rent', 'Security Deposit', 'Status']);
        
        $sql = "
            SELECT a.*, c.chamber_no, c.block_name, m.full_name, m.membership_no 
            FROM chamber_allotments a
            JOIN chambers c ON c.id = a.chamber_id
            JOIN members m ON m.id = a.member_id
            WHERE 1=1
        ";
        $p = [];
        if (!empty($block_filter)) { $sql .= " AND c.block_name = ?"; $p[] = $block_filter; }
        if (!empty($status_filter)) { $sql .= " AND a.status = ?"; $p[] = $status_filter; }
        $sql .= " ORDER BY a.id DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($p);
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['allotment_no'],
                $row['chamber_no'],
                $row['block_name'],
                $row['full_name'],
                $row['membership_no'],
                $row['allotment_date'],
                $row['monthly_rent'],
                $row['security_deposit'],
                $row['status']
            ]);
        }
        
    } elseif ($report_type === 'outstanding') {
        fputcsv($output, ['Member Name', 'DBA Code', 'Chamber No', 'Rent Month', 'Due Date', 'Payable Rent', 'Paid Amount', 'Outstanding Amount', 'Status']);
        
        $sql = "
            SELECT d.*, a.allotment_no, c.chamber_no, m.full_name, m.membership_no 
            FROM chamber_rent_dues d
            JOIN chamber_allotments a ON a.id = d.allotment_id
            JOIN chambers c ON c.id = a.chamber_id
            JOIN members m ON m.id = a.member_id
            WHERE d.status NOT IN ('paid', 'waived', 'cancelled')
        ";
        $p = [];
        if (!empty($month_filter)) { $sql .= " AND d.rent_month = ?"; $p[] = $month_filter; }
        $sql .= " ORDER BY d.rent_month ASC, c.chamber_no ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($p);
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['full_name'],
                $row['membership_no'],
                $row['chamber_no'],
                $row['rent_month'],
                $row['due_date'],
                $row['payable_amount'],
                $row['paid_amount'],
                $row['outstanding_amount'],
                $row['status']
            ]);
        }
        
    } elseif ($report_type === 'collection') {
        fputcsv($output, ['Payment Date', 'Receipt No', 'Payment No', 'Member Name', 'DBA Code', 'Chamber No', 'Amount Paid', 'Payment Mode', 'Reference No', 'Remarks']);
        
        $sql = "
            SELECT p.*, c.chamber_no, m.full_name, m.membership_no 
            FROM chamber_rent_payments p
            JOIN chamber_allotments a ON a.id = p.allotment_id
            JOIN chambers c ON c.id = a.chamber_id
            JOIN members m ON m.id = p.member_id
            WHERE p.status = 'confirmed'
        ";
        $p = [];
        if (!empty($month_filter)) { $sql .= " AND DATE_FORMAT(p.payment_date, '%Y-%m') = ?"; $p[] = $month_filter; }
        $sql .= " ORDER BY p.payment_date DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($p);
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['payment_date'],
                $row['receipt_no'],
                $row['payment_no'],
                $row['full_name'],
                $row['membership_no'],
                $row['chamber_no'],
                $row['amount'],
                $row['payment_mode'],
                $row['reference_no'],
                $row['remarks']
            ]);
        }
        
    } elseif ($report_type === 'deposits') {
        fputcsv($output, ['Member Name', 'DBA Code', 'Chamber No', 'Allotment No', 'Deposit Amount', 'Payment Date', 'Payment Mode', 'Receipt No', 'Status']);
        
        $sql = "
            SELECT sd.*, c.chamber_no, m.full_name, m.membership_no, a.allotment_no
            FROM chamber_security_deposits sd
            JOIN chamber_allotments a ON a.id = sd.allotment_id
            JOIN chambers c ON c.id = a.chamber_id
            JOIN members m ON m.id = a.member_id
            WHERE 1=1
        ";
        $p = [];
        $stmt = $db->prepare($sql);
        $stmt->execute($p);
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['full_name'],
                $row['membership_no'],
                $row['chamber_no'],
                $row['allotment_no'],
                $row['amount'],
                $row['payment_date'],
                $row['payment_mode'],
                $row['receipt_no'],
                $row['status']
            ]);
        }
    }
    
    fclose($output);
    exit;
}

// -----------------------------
// HTML PAGE GENERATION (Phase 9)
// -----------------------------
$pageTitle = 'कक्ष किराया संचालन रिपोर्ट्स (Room Rent Reports)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Load lists based on active tab query
$data_rows = [];
try {
    if ($report_type === 'register') {
        $sql = "SELECT * FROM chambers WHERE 1=1";
        $p = [];
        if (!empty($block_filter)) { $sql .= " AND block_name = ?"; $p[] = $block_filter; }
        if (!empty($status_filter)) { $sql .= " AND status = ?"; $p[] = $status_filter; }
        $sql .= " ORDER BY chamber_no ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($p);
        $data_rows = $stmt->fetchAll();
        
    } elseif ($report_type === 'allotment') {
        $sql = "
            SELECT a.*, c.chamber_no, c.block_name, m.full_name, m.membership_no 
            FROM chamber_allotments a
            JOIN chambers c ON c.id = a.chamber_id
            JOIN members m ON m.id = a.member_id
            WHERE 1=1
        ";
        $p = [];
        if (!empty($block_filter)) { $sql .= " AND c.block_name = ?"; $p[] = $block_filter; }
        if (!empty($status_filter)) { $sql .= " AND a.status = ?"; $p[] = $status_filter; }
        $sql .= " ORDER BY a.id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($p);
        $data_rows = $stmt->fetchAll();
        
    } elseif ($report_type === 'outstanding') {
        $sql = "
            SELECT d.*, a.allotment_no, c.chamber_no, m.full_name, m.membership_no 
            FROM chamber_rent_dues d
            JOIN chamber_allotments a ON a.id = d.allotment_id
            JOIN chambers c ON c.id = a.chamber_id
            JOIN members m ON m.id = a.member_id
            WHERE d.status NOT IN ('paid', 'waived', 'cancelled')
        ";
        $p = [];
        if (!empty($month_filter)) { $sql .= " AND d.rent_month = ?"; $p[] = $month_filter; }
        $sql .= " ORDER BY d.rent_month ASC, c.chamber_no ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($p);
        $data_rows = $stmt->fetchAll();
        
    } elseif ($report_type === 'collection') {
        $sql = "
            SELECT p.*, c.chamber_no, m.full_name, m.membership_no 
            FROM chamber_rent_payments p
            JOIN chamber_allotments a ON a.id = p.allotment_id
            JOIN chambers c ON c.id = a.chamber_id
            JOIN members m ON m.id = p.member_id
            WHERE p.status = 'confirmed'
        ";
        $p = [];
        if (!empty($month_filter)) { $sql .= " AND DATE_FORMAT(p.payment_date, '%Y-%m') = ?"; $p[] = $month_filter; }
        $sql .= " ORDER BY p.payment_date DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($p);
        $data_rows = $stmt->fetchAll();
        
    } elseif ($report_type === 'deposits') {
        $sql = "
            SELECT sd.*, c.chamber_no, m.full_name, m.membership_no, a.allotment_no
            FROM chamber_security_deposits sd
            JOIN chamber_allotments a ON a.id = sd.allotment_id
            JOIN chambers c ON c.id = a.chamber_id
            JOIN members m ON m.id = a.member_id
            WHERE 1=1
        ";
        $p = [];
        $stmt = $db->prepare($sql);
        $stmt->execute($p);
        $data_rows = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Failed generating report rows: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi no-print">
    <h4 class="text-navy-custom fw-bold mb-0">कक्ष/चैंबर वित्तीय एवं परिचालन रिपोर्ट्स (Operational Reports)</h4>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-grid-fill me-1"></i>चैंबर मास्टर</a>
        <button onclick="window.print();" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-printer me-1"></i>A4 प्रिंट</button>
        <a href="?type=<?php echo $report_type; ?>&block=<?php echo urlencode($block_filter); ?>&status=<?php echo urlencode($status_filter); ?>&month=<?php echo urlencode($month_filter); ?>&export=csv" class="btn btn-xs btn-success fw-semibold"><i class="bi bi-file-earmark-excel me-1"></i>CSV निर्यात</a>
    </div>
</div>

<!-- Print Only Header -->
<div class="d-none d-print-block text-center mb-4 font-hindi">
    <h4 class="fw-bold mb-1 text-uppercase">जिला अधिवक्ता संघ, बांदा (उत्तर प्रदेश)</h4>
    <h6 class="text-secondary mb-3">कक्ष किराया रिपोर्ट: <?php echo ucfirst($report_type); ?> | दिनांक: <?php echo date('d-m-Y'); ?></h6>
    <hr class="border-navy-custom my-2">
</div>

<!-- Tabs Navigation -->
<div class="bg-white rounded border border-light p-1 shadow-xs mb-4 font-hindi small no-print">
    <ul class="nav nav-pills nav-fill" id="reportTabs">
        <li class="nav-item">
            <a class="nav-link py-2 <?php echo ($report_type === 'register') ? 'active bg-navy-custom' : 'text-navy-custom'; ?>" href="?type=register">चैंबर रजिस्टर (Register)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-2 <?php echo ($report_type === 'allotment') ? 'active bg-navy-custom' : 'text-navy-custom'; ?>" href="?type=allotment">सक्रिय आवंटन विवरणी (Allotments)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-2 <?php echo ($report_type === 'outstanding') ? 'active bg-navy-custom' : 'text-navy-custom'; ?>" href="?type=outstanding">किराया बकाया रिपोर्ट (Outstanding)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-2 <?php echo ($report_type === 'collection') ? 'active bg-navy-custom' : 'text-navy-custom'; ?>" href="?type=collection">किराया संग्रह बही (Collections)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-2 <?php echo ($report_type === 'deposits') ? 'active bg-navy-custom' : 'text-navy-custom'; ?>" href="?type=deposits">जमानत सुरक्षा जमा (Security Deposits)</a>
        </li>
    </ul>
</div>

<!-- Filters Panel (respects tab parameters) -->
<div class="card border-0 shadow-xs mb-4 font-hindi small no-print">
    <div class="card-body p-3 bg-light rounded border border-light">
        <form method="GET" action="" class="row g-2">
            <input type="hidden" name="type" value="<?php echo $report_type; ?>">
            
            <?php if ($report_type === 'register' || $report_type === 'allotment'): ?>
                <div class="col-md-5">
                    <label class="form-label fw-bold">परिसर ब्लॉक (Block)</label>
                    <select name="block" class="form-select form-select-sm">
                        <option value="">-- सभी ब्लॉक --</option>
                        <option value="Main Court Block" <?php echo ($block_filter === 'Main Court Block') ? 'selected' : ''; ?>>Main Court Block</option>
                        <option value="Library Block" <?php echo ($block_filter === 'Library Block') ? 'selected' : ''; ?>>Library Block</option>
                        <option value="New Annex Block" <?php echo ($block_filter === 'New Annex Block') ? 'selected' : ''; ?>>New Annex Block</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">स्थिति (Status)</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- सभी स्थितियां --</option>
                        <?php if ($report_type === 'register'): ?>
                            <option value="available" <?php echo ($status_filter === 'available') ? 'selected' : ''; ?>>Available</option>
                            <option value="occupied" <?php echo ($status_filter === 'occupied') ? 'selected' : ''; ?>>Occupied</option>
                            <option value="maintenance" <?php echo ($status_filter === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                        <?php else: ?>
                            <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active (सक्रिय)</option>
                            <option value="vacated" <?php echo ($status_filter === 'vacated') ? 'selected' : ''; ?>>Vacated (खाली)</option>
                        <?php endif; ?>
                    </select>
                </div>
            <?php elseif ($report_type === 'outstanding' || $report_type === 'collection'): ?>
                <div class="col-md-10">
                    <label class="form-label fw-bold">चयनित माह (Format: YYYY-MM)</label>
                    <input type="month" name="month" class="form-control form-control-sm" value="<?php echo e($month_filter); ?>">
                </div>
            <?php else: ?>
                <div class="col-10 text-muted py-2">इस रिपोर्ट श्रेणी हेतु कोई अतिरिक्त फ़िल्टर उपलब्ध नहीं है।</div>
            <?php endif; ?>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-navy btn-sm w-100"><i class="bi bi-filter-circle me-1"></i>खोजें</button>
            </div>
        </form>
    </div>
</div>

<!-- Table Output based on active tab -->
<div class="card border-0 shadow-sm font-hindi small">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <?php if ($report_type === 'register'): ?>
                    <thead class="table-light">
                        <tr>
                            <th>चैंबर नं</th>
                            <th>ब्लॉक परिसर</th>
                            <th>तल</th>
                            <th>श्रेणी</th>
                            <th>आवंटन</th>
                            <th>क्षमता</th>
                            <th class="text-end">मासिक किराया</th>
                            <th class="text-end">जमानत राशि</th>
                            <th>स्थिति</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data_rows as $row): ?>
                            <tr>
                                <td class="fw-bold text-navy-custom english-text"><?php echo e($row['chamber_no']); ?></td>
                                <td><?php echo e($row['block_name']); ?></td>
                                <td><?php echo e($row['floor']); ?></td>
                                <td><?php echo e($row['chamber_type']); ?></td>
                                <td><?php echo e($row['occupancy_type']); ?></td>
                                <td class="english-text"><?php echo e($row['capacity']); ?></td>
                                <td class="text-end text-navy-custom english-text">₹<?php echo number_format($row['monthly_rent'], 2); ?></td>
                                <td class="text-end text-muted english-text">₹<?php echo number_format($row['security_deposit'], 2); ?></td>
                                <td><span class="badge bg-secondary font-size-xs"><?php echo e($row['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php elseif ($report_type === 'allotment'): ?>
                    <thead class="table-light">
                        <tr>
                            <th>आवंटन सं.</th>
                            <th>चैंबर नं</th>
                            <th>ब्लॉक परिसर</th>
                            <th>अधिवक्ता सदस्य</th>
                            <th>DBA Code</th>
                            <th>आवंटन तिथि</th>
                            <th class="text-end">मासिक किराया</th>
                            <th class="text-end">सुरक्षा जमा</th>
                            <th>स्थिति</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data_rows as $row): ?>
                            <tr>
                                <td class="fw-bold english-text"><?php echo e($row['allotment_no']); ?></td>
                                <td class="english-text"><?php echo e($row['chamber_no']); ?></td>
                                <td><?php echo e($row['block_name']); ?></td>
                                <td><strong><?php echo e($row['full_name']); ?></strong></td>
                                <td class="english-text"><?php echo e($row['membership_no']); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($row['allotment_date'])); ?></td>
                                <td class="text-end text-danger english-text">₹<?php echo number_format($row['monthly_rent'], 2); ?></td>
                                <td class="text-end text-success english-text">₹<?php echo number_format($row['security_deposit'], 2); ?></td>
                                <td><span class="badge bg-primary font-size-xs"><?php echo e($row['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php elseif ($report_type === 'outstanding'): ?>
                    <thead class="table-light">
                        <tr>
                            <th>अधिवक्ता</th>
                            <th>DBA Code</th>
                            <th>चैंबर नं</th>
                            <th>किराया माह</th>
                            <th>देय तिथि</th>
                            <th class="text-end">कुल देय</th>
                            <th class="text-end">जमा राशि</th>
                            <th class="text-end">लंबित बकाया</th>
                            <th>स्थिति</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_out = 0;
                        foreach ($data_rows as $row): 
                            $total_out += floatval($row['outstanding_amount']);
                        ?>
                            <tr>
                                <td><strong><?php echo e($row['full_name']); ?></strong></td>
                                <td class="english-text"><?php echo e($row['membership_no']); ?></td>
                                <td class="english-text"><?php echo e($row['chamber_no']); ?></td>
                                <td class="english-text fw-bold text-navy-custom"><?php echo date('F Y', strtotime($row['rent_month'] . '-01')); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($row['due_date'])); ?></td>
                                <td class="text-end english-text">₹<?php echo number_format($row['payable_amount'], 2); ?></td>
                                <td class="text-end text-success english-text">₹<?php echo number_format($row['paid_amount'], 2); ?></td>
                                <td class="text-end text-danger fw-bold english-text">₹<?php echo number_format($row['outstanding_amount'], 2); ?></td>
                                <td><span class="badge bg-danger font-size-xs"><?php echo e($row['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold text-navy-custom">
                            <td colspan="7" class="text-end text-uppercase">कुल बकाया किराया (Total Outstanding)</td>
                            <td class="text-end text-danger fs-6 english-text">₹<?php echo number_format($total_out, 2); ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                <?php elseif ($report_type === 'collection'): ?>
                    <thead class="table-light">
                        <tr>
                            <th>भुगतान तिथि</th>
                            <th>रसीद संख्या</th>
                            <th>अधिवक्ता</th>
                            <th>DBA Code</th>
                            <th>चैंबर नं</th>
                            <th>भुगतान माध्यम</th>
                            <th>लेनदेन सन्दर्भ</th>
                            <th class="text-end">प्राप्त राशि</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_coll = 0;
                        foreach ($data_rows as $row): 
                            $total_coll += floatval($row['amount']);
                        ?>
                            <tr>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($row['payment_date'])); ?></td>
                                <td class="english-text fw-bold text-navy-custom"><?php echo e($row['receipt_no']); ?></td>
                                <td><strong><?php echo e($row['full_name']); ?></strong></td>
                                <td class="english-text"><?php echo e($row['membership_no']); ?></td>
                                <td class="english-text"><?php echo e($row['chamber_no']); ?></td>
                                <td><?php echo e($row['payment_mode']); ?></td>
                                <td class="english-text text-muted"><?php echo e($row['reference_no'] ?: '-'); ?></td>
                                <td class="text-end text-success fw-bold english-text">₹<?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold text-navy-custom">
                            <td colspan="7" class="text-end text-uppercase">कुल प्राप्त संग्रह (Total Collections)</td>
                            <td class="text-end text-success fs-6 english-text">₹<?php echo number_format($total_coll, 2); ?></td>
                        </tr>
                    </tbody>
                <?php elseif ($report_type === 'deposits'): ?>
                    <thead class="table-light">
                        <tr>
                            <th>अधिवक्ता</th>
                            <th>DBA Code</th>
                            <th>चैंबर नं</th>
                            <th>आवंटन सं.</th>
                            <th class="text-end">जमानत राशि</th>
                            <th>भुगतान तिथि</th>
                            <th>माध्यम</th>
                            <th>रसीद सं.</th>
                            <th>स्थिति</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data_rows as $row): ?>
                            <tr>
                                <td><strong><?php echo e($row['full_name']); ?></strong></td>
                                <td class="english-text"><?php echo e($row['membership_no']); ?></td>
                                <td class="english-text"><?php echo e($row['chamber_no']); ?></td>
                                <td class="english-text"><?php echo e($row['allotment_no']); ?></td>
                                <td class="text-end fw-bold text-navy-custom english-text">₹<?php echo number_format($row['amount'], 2); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($row['payment_date'])); ?></td>
                                <td><?php echo e($row['payment_mode']); ?></td>
                                <td class="english-text"><?php echo e($row['receipt_no']); ?></td>
                                <td>
                                    <?php 
                                    $c = ($row['status'] === 'paid') ? 'bg-success' : 'bg-warning text-dark';
                                    ?>
                                    <span class="badge <?php echo $c; ?> font-size-xs"><?php echo e(ucfirst($row['status'])); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
