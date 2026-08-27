<?php
/**
 * Unified Report Center
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'केंद्रीय रिपोर्ट केंद्र (Unified Report Center)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce permission
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';

// Reusable filters
$report_type = sanitize($_GET['report_type'] ?? '');
$start_date = sanitize($_GET['start_date'] ?? '');
$end_date = sanitize($_GET['end_date'] ?? '');
$filter_status = sanitize($_GET['status'] ?? '');
$filter_category = sanitize($_GET['category'] ?? '');
$filter_member = intval($_GET['member_id'] ?? 0);

$report_data = [];
$headers = [];

if ($db && !empty($report_type)) {
    try {
        $sql = "";
        $params = [];

        switch ($report_type) {
            case 'member_register':
                $headers = ['सदस्यता सं.', 'नाम', 'पिता/पति का नाम', 'मोबाइल', 'श्रेणी', 'स्थिति', 'नामांकन तिथि'];
                $sql = "SELECT membership_no, full_name, father_or_husband_name, mobile, membership_category, membership_status, enrollment_date FROM members WHERE 1=1";
                if (!empty($filter_status)) {
                    $sql .= " AND membership_status = ?";
                    $params[] = $filter_status;
                }
                if (!empty($filter_category)) {
                    $sql .= " AND membership_category = ?";
                    $params[] = $filter_category;
                }
                $sql .= " ORDER BY membership_no ASC";
                break;

            case 'id_cards':
                $headers = ['कार्ड नंबर', 'सदस्य नाम', 'सदस्यता सं.', 'जारी दिनांक', 'वैधता तिथि', 'स्थिति'];
                $sql = "
                    SELECT ic.card_number, m.full_name, m.membership_no, ic.issued_at, ic.valid_until, ic.status 
                    FROM id_cards ic
                    JOIN members m ON m.id = ic.member_id
                    WHERE 1=1
                ";
                if (!empty($filter_status)) {
                    $sql .= " AND ic.status = ?";
                    $params[] = $filter_status;
                }
                break;

            case 'wakalatnama':
                $headers = ['सदस्य नाम', 'सदस्यता सं.', 'शीर्षक', 'मूल्य', 'डाउनलोड तिथि', 'स्थिति'];
                $sql = "
                    SELECT m.full_name, m.membership_no, w.title, w.price, wd.downloaded_at, wd.status
                    FROM wakalatnama_downloads wd
                    JOIN members m ON m.id = wd.member_id
                    JOIN wakalatnamas w ON w.id = wd.wakalatnama_id
                    WHERE 1=1
                ";
                if (!empty($start_date)) {
                    $sql .= " AND wd.downloaded_at >= ?";
                    $params[] = $start_date . ' 00:00:00';
                }
                if (!empty($end_date)) {
                    $sql .= " AND wd.downloaded_at <= ?";
                    $params[] = $end_date . ' 23:59:59';
                }
                if ($filter_member > 0) {
                    $sql .= " AND wd.member_id = ?";
                    $params[] = $filter_member;
                }
                $sql .= " ORDER BY wd.id DESC";
                break;

            case 'fund_ledger':
                $headers = ['लेनदेन संख्या', 'रसीद/वाउचर सं.', 'प्रकार', 'विवरण (Category)', 'राशि', 'भुगतान माध्यम', 'दिनांक', 'स्थिति'];
                $sql = "
                    SELECT ft.transaction_no, COALESCE(ft.receipt_no, ft.voucher_no, '-') AS ref_no, ft.transaction_type, 
                           fc.name AS category_name, ft.amount, ft.payment_mode, ft.transaction_date, ft.status
                    FROM association_fund_transactions ft
                    JOIN association_fund_categories fc ON fc.id = ft.category_id
                    WHERE 1=1
                ";
                if (!empty($start_date)) {
                    $sql .= " AND ft.transaction_date >= ?";
                    $params[] = $start_date;
                }
                if (!empty($end_date)) {
                    $sql .= " AND ft.transaction_date <= ?";
                    $params[] = $end_date;
                }
                if (!empty($filter_status)) {
                    $sql .= " AND ft.status = ?";
                    $params[] = $filter_status;
                }
                $sql .= " ORDER BY ft.transaction_date DESC, ft.id DESC";
                break;

            case 'bar_fee':
                $headers = ['रसीद संख्या', 'सदस्य नाम', 'सदस्यता सं.', 'भुगतान विधि', 'जमा राशि', 'भुगतान दिनांक', 'स्थिति'];
                $sql = "
                    SELECT bp.receipt_no, m.full_name, m.membership_no, bp.payment_mode, bp.amount, bp.payment_date, bp.payment_status
                    FROM bar_fee_payments bp
                    JOIN members m ON m.id = bp.member_id
                    WHERE 1=1
                ";
                if (!empty($start_date)) {
                    $sql .= " AND bp.payment_date >= ?";
                    $params[] = $start_date;
                }
                if (!empty($end_date)) {
                    $sql .= " AND bp.payment_date <= ?";
                    $params[] = $end_date;
                }
                if ($filter_member > 0) {
                    $sql .= " AND bp.member_id = ?";
                    $params[] = $filter_member;
                }
                $sql .= " ORDER BY bp.payment_date DESC";
                break;

            case 'chambers':
                $headers = ['चैंबर नं.', 'सदस्य नाम', 'सदस्यता सं.', 'सिक्योरिटी डिपॉजिट', 'मासिक किराया', 'किराया स्थिति', 'आवंटन तिथि'];
                $sql = "
                    SELECT c.chamber_no, m.full_name, m.membership_no, c.security_deposit, c.monthly_rent, c.status, ca.allotment_date
                    FROM chambers c
                    LEFT JOIN chamber_allotments ca ON ca.chamber_id = c.id AND ca.status = 'active'
                    LEFT JOIN members m ON m.id = ca.member_id
                    WHERE 1=1
                ";
                if (!empty($filter_status)) {
                    $sql .= " AND c.status = ?";
                    $params[] = $filter_status;
                }
                $sql .= " ORDER BY c.chamber_no ASC";
                break;

            case 'election_voters':
                $headers = ['वोटर संख्या', 'सदस्य नाम', 'सदस्यता सं.', 'पंजीकरण संख्या', 'चैंबर नं.', 'पात्रता स्थिति'];
                $sql = "
                    SELECT ev.voter_no, ev.member_name, ev.membership_no, ev.enrollment_no, ev.chamber_no, ev.eligibility_status 
                    FROM election_voters ev
                    WHERE 1=1
                ";
                if (!empty($filter_status)) {
                    $sql .= " AND ev.eligibility_status = ?";
                    $params[] = $filter_status;
                }
                $sql .= " ORDER BY CAST(ev.voter_no AS UNSIGNED) ASC, ev.voter_no ASC";
                break;

            case 'audit_activity':
                $headers = ['दिनांक', 'उपयोगकर्ता', 'रोल', 'मॉड्यूल', 'क्रिया', 'आईपी पता'];
                $sql = "
                    SELECT a.created_at, COALESCE(u.username, 'System') AS username, a.role, a.module, a.action, a.ip_address
                    FROM audit_logs a
                    LEFT JOIN users u ON u.id = a.user_id
                    WHERE 1=1
                ";
                if (!empty($start_date)) {
                    $sql .= " AND a.created_at >= ?";
                    $params[] = $start_date . ' 00:00:00';
                }
                if (!empty($end_date)) {
                    $sql .= " AND a.created_at <= ?";
                    $params[] = $end_date . ' 23:59:59';
                }
                $sql .= " ORDER BY a.id DESC";
                break;

            default:
                throw new Exception('अमान्य रिपोर्ट प्रकार।');
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error = 'रिपोर्ट तैयार करने में विफलता: ' . $e->getMessage();
    }
}

// Fetch all members for dropdown
$members = [];
if ($db) {
    try {
        $members = $db->query("SELECT id, full_name, membership_no FROM members ORDER BY full_name ASC")->fetchAll();
    } catch (PDOException $e) {}
}

// Handle Export CSV (Requirement 15: UTF-8 BOM, Hindi support, exclude secrets)
if (isset($_GET['export_csv']) && $_GET['export_csv'] === '1' && !empty($report_type) && empty($error)) {
    $filename = "report_" . $report_type . "_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Output UTF-8 BOM for Excel
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    
    // Header
    fputcsv($output, $headers);

    foreach ($report_data as $row) {
        // Exclude passwords/hashes if any leakage exists
        fputcsv($output, array_values($row));
    }
    
    fclose($output);
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-file-earmark-bar-graph text-gold-custom me-2"></i>केंद्रीय रिपोर्ट केंद्र (Unified Report Center)</h4>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Filters Form -->
<div class="card border-0 shadow-sm mb-4 font-hindi small text-navy-custom">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-funnel me-1"></i>रिपोर्ट एवं फ़िल्टर चयन</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="" id="reportForm">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">रिपोर्ट का प्रकार (Report Type) *</label>
                    <select name="report_type" class="form-select form-select-sm" required onchange="document.getElementById('reportForm').submit();">
                        <option value="">-- रिपोर्ट प्रकार चुनें --</option>
                        <optgroup label="अधिवक्ता सदस्य रिपोर्ट (Members)">
                            <option value="member_register" <?php echo $report_type === 'member_register' ? 'selected' : ''; ?>>सदस्य रजिस्टर सूची (Member Register)</option>
                        </optgroup>
                        <optgroup label="आईडी कार्ड एवं विलेख (ID Cards & Wakalatnama)">
                            <option value="id_cards" <?php echo $report_type === 'id_cards' ? 'selected' : ''; ?>>आईडी कार्ड विवरणी (Issued ID Cards)</option>
                            <option value="wakalatnama" <?php echo $report_type === 'wakalatnama' ? 'selected' : ''; ?>>वकालतनामा डाउनलोड विवरणी (Wakalatnama Downloads)</option>
                        </optgroup>
                        <optgroup label="वित्तीय लेखा-जोखा (Finance)">
                            <option value="fund_ledger" <?php echo $report_type === 'fund_ledger' ? 'selected' : ''; ?>>संघीय कोष बहीखाता (Association Fund Ledger)</option>
                            <option value="bar_fee" <?php echo $report_type === 'bar_fee' ? 'selected' : ''; ?>>सदस्यता शुल्क संकलन (Bar Fee Collection)</option>
                            <option value="chambers" <?php echo $report_type === 'chambers' ? 'selected' : ''; ?>>चैंबर आवंटन एवं किराया स्थिति (Chambers Rent)</option>
                        </optgroup>
                        <optgroup label="निर्वाचन एवं प्रशासन (Elections & Admin)">
                            <option value="election_voters" <?php echo $report_type === 'election_voters' ? 'selected' : ''; ?>>चुनाव मतदाता सूची विवरणी (Voters register)</option>
                            <option value="audit_activity" <?php echo $report_type === 'audit_activity' ? 'selected' : ''; ?>>केंद्रीय सिस्टम ऑडिट लॉग (Central Audit Logs)</option>
                        </optgroup>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">प्रारंभ तिथि</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">समाप्ति तिथि</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">स्थिति (Status Filter)</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- सभी --</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active (सक्रिय)</option>
                        <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive (निष्क्रिय)</option>
                        <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved (स्वीकृत)</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending (विचाराधीन)</option>
                        <option value="eligible" <?php echo $filter_status === 'eligible' ? 'selected' : ''; ?>>Eligible (योग्य)</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">श्रेणी (Category)</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">-- सभी --</option>
                        <option value="Regular Member" <?php echo $filter_category === 'Regular Member' ? 'selected' : ''; ?>>Regular Member</option>
                        <option value="Life Member" <?php echo $filter_category === 'Life Member' ? 'selected' : ''; ?>>Life Member</option>
                        <option value="Senior Member" <?php echo $filter_category === 'Senior Member' ? 'selected' : ''; ?>>Senior Member</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">अधिवक्ता सदस्य</label>
                    <select name="member_id" class="form-select form-select-sm">
                        <option value="">-- सभी सदस्य --</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo $filter_member === intval($m['id']) ? 'selected' : ''; ?>><?php echo e($m['full_name']); ?> (<?php echo e($m['membership_no']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-navy btn-sm px-4"><i class="bi bi-filter me-1"></i>रिपोर्ट देखें (Apply)</button>
                <a href="index.php" class="btn btn-outline-secondary btn-sm px-4">रीसेट (Reset)</a>
                <?php if (!empty($report_type) && !empty($report_data)): ?>
                    <button type="submit" name="export_csv" value="1" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV डाउनलोड</button>
                    <button type="button" onclick="window.print();" class="btn btn-outline-dark btn-sm"><i class="bi bi-printer me-1"></i>प्रिंट रिपोर्ट</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Report Outputs Listing Table -->
<?php if (!empty($report_type)): ?>
    <div class="card border-0 shadow-sm font-hindi small text-navy-custom">
        <div class="card-header bg-light py-2 fw-bold d-flex justify-content-between align-items-center">
            <span>रिपोर्ट विवरणी तालिका</span>
            <span class="badge bg-navy-custom text-white english-text">Rows: <?php echo count($report_data); ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:0.75rem;">
                    <thead class="table-light text-center">
                        <tr>
                            <?php foreach ($headers as $h): ?>
                                <th><?php echo $h; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="<?php echo count($headers); ?>" class="text-center py-3 text-muted">कोई रिकॉर्ड नहीं मिला।</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $k => $v): ?>
                                        <td>
                                            <?php 
                                            // Handle special badges/currencies rendering
                                            if (in_array($k, ['amount', 'price', 'security_deposit', 'monthly_rent'])) {
                                                echo formatCurrency($v);
                                            } elseif (in_array($k, ['status', 'membership_status', 'payment_status', 'eligibility_status'])) {
                                                echo getStatusBadge($v);
                                            } else {
                                                echo e($v);
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
