<?php
/**
 * Detailed Member View (Dashboard Tabs Panel)
 * District Bar Association, Banda
 */

$pageTitle = 'सदस्य प्रोफाइल मास्टर कार्ड';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv']);
requirePermission('members.view');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$member = null;
$db = Database::getConnection();

if ($db && $id > 0) {
    try {
        $stmt = $db->prepare("SELECT m.*, u.username, u.status AS user_status, u.last_login_at FROM members m LEFT JOIN users u ON u.member_id = m.id WHERE m.id = ?");
        $stmt->execute([$id]);
        $member = $stmt->fetch();
        
        if ($member) {
            $fee_summary = getMemberFeeSummary($id, $db);
            
            $dues_stmt = $db->prepare("
                SELECT d.*, t.name as fee_name 
                FROM member_fee_dues d
                JOIN bar_fee_types t ON t.id = d.fee_type_id
                WHERE d.member_id = ? AND d.status != 'cancelled'
                ORDER BY d.due_date ASC, d.id ASC
            ");
            $dues_stmt->execute([$id]);
            $member_dues = $dues_stmt->fetchAll();

            $pay_stmt = $db->prepare("
                SELECT p.*
                FROM bar_fee_payments p
                WHERE p.member_id = ? AND p.status = 'confirmed'
                ORDER BY p.payment_date DESC, p.id DESC
            ");
            $member_payments = $pay_stmt->fetchAll();
            
            // Fetch Chamber Summary (Phase 9)
            $chamber_summary = getMemberChamberSummary($id, $db);

            // Fetch Election Voter Status (Phase 10)
            $voter_summary = null;
            if ($db) {
                $voter_stmt = $db->prepare("
                    SELECT ev.*, e.title AS election_title, e.election_code 
                    FROM election_voters ev
                    JOIN elections e ON e.id = ev.election_id
                    WHERE ev.member_id = ?
                    ORDER BY e.election_year DESC, e.id DESC LIMIT 1
                ");
                $voter_stmt->execute([$id]);
                $voter_summary = $voter_stmt->fetch();
            }

            // Fetch Association Roles History (Phase 11)
            $roles_history = [];
            if ($db) {
                $role_h_stmt = $db->prepare("
                    SELECT ob.*, p.position_name, p.position_name_hindi, t.title AS term_title, t.status AS term_status
                    FROM office_bearers ob
                    JOIN office_bearer_positions p ON p.id = ob.position_id
                    JOIN office_bearer_terms t ON t.id = ob.term_id
                    WHERE ob.member_id = ?
                    ORDER BY t.start_date DESC, ob.id DESC
                ");
                $role_h_stmt->execute([$id]);
                $roles_history = $role_h_stmt->fetchAll();
            }
        }
    } catch (PDOException $e) {
        error_log("Failed to load detailed member card: " . $e->getMessage());
    }
}

if (!$member) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: सदस्य रिकॉर्ड नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit();
}

// Fetch member history logs
$history = [];
if ($db) {
    try {
        $hist_stmt = $db->prepare("SELECT h.*, u.username AS performed_by_username FROM member_history h LEFT JOIN users u ON u.id = h.performed_by WHERE h.member_id = ? ORDER BY h.created_at DESC");
        $hist_stmt->execute([$id]);
        $history = $hist_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch member logs: " . $e->getMessage());
    }
}

// Fetch member documents
$documents = [];
if ($db) {
    try {
        $doc_stmt = $db->prepare("SELECT * FROM member_documents WHERE member_id = ? ORDER BY created_at DESC");
        $doc_stmt->execute([$id]);
        $documents = $doc_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch documents: " . $e->getMessage());
    }
}

// Status Hindi labels mapping
$status_labels = [
    'active' => 'सक्रिय (Active)',
    'inactive' => 'निष्क्रिय (Inactive)',
    'suspended' => 'निलंबित (Suspended)',
    'expired' => 'समाप्त (Expired)',
    'deceased' => 'दिवंगत (Deceased)',
    'pending' => 'लंबित (Pending)',
    'rejected' => 'अस्वीकृत (Rejected)'
];

// Document Types Hindi labels mapping
$doc_labels = [
    'Enrollment Certificate' => 'नामांकन प्रमाण पत्र',
    'Certificate of Practice' => 'विधिक अभ्यास प्रमाण पत्र (COP)',
    'Membership Form' => 'बार सदस्यता आवेदन पत्र',
    'Photo ID' => 'पहचान पत्र (Aadhar/Voter)',
    'Address Proof' => 'निवास प्रमाण पत्र',
    'Other' => 'अन्य विलेख'
];

$active_tab = trim($_GET['tab'] ?? 'overview');
?>

<div class="mb-3 font-hindi">
    <a href="index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>सदस्य सूची पर वापस जाएं</a>
</div>

<!-- Header overview card -->
<div class="card-custom bg-white p-4 mb-4 border border-light shadow-sm font-hindi">
    <div class="row align-items-center g-3">
        <!-- Photo -->
        <div class="col-md-2 col-12 text-center">
            <div class="mx-auto rounded-circle overflow-hidden bg-light border border-gold-custom border-2 d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                <?php if (!empty($member['photo']) && $member['photo'] !== 'default_advocate.png'): ?>
                    <img src="../../uploads/photos/<?php echo e($member['photo']); ?>" alt="Photo" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <i class="bi bi-person-fill text-muted" style="font-size: 5rem;"></i>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick details -->
        <div class="col-md-7 col-12 text-center text-md-start">
            <h4 class="fw-bold text-navy-custom mb-1 english-text"><?php echo e($member['full_name']); ?></h4>
            <p class="text-muted small mb-2"><i class="bi bi-hash text-gold-dark me-1"></i>सदस्यता संख्या: <?php echo e($member['membership_no']); ?> | पंजीकरण संख्या: <?php echo e($member['enrollment_no']); ?></p>
            
            <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-2">
                <span class="badge bg-light text-navy-custom border py-1 px-2">चैंबर: <?php echo !empty($member['chamber_no']) ? e($member['chamber_no']) : 'N/A'; ?></span>
                <span class="badge bg-light text-navy-custom border py-1 px-2">श्रेणी: <?php echo e($member['membership_category']); ?></span>
                <?php 
                $st = $member['membership_status'];
                $badge_c = 'bg-secondary';
                if ($st === 'active') $badge_c = 'bg-success';
                elseif ($st === 'suspended') $badge_c = 'bg-danger';
                elseif ($st === 'deceased') $badge_c = 'bg-dark text-white';
                elseif ($st === 'pending') $badge_c = 'bg-warning text-dark';
                ?>
                <span class="badge <?php echo $badge_c; ?> py-1 px-2">स्थिति: <?php echo e($status_labels[$st] ?? $st); ?></span>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="col-md-3 col-12 text-center text-md-end">
            <?php if (hasPermission('members.manage')): ?>
                <div class="d-grid gap-2">
                    <a href="edit.php?id=<?php echo e($member['id']); ?>" class="btn btn-xs btn-navy py-1.5"><i class="bi bi-pencil-square me-1 text-gold-custom"></i>विवरण संशोधित करें</a>
                    <a href="#status" onclick="document.getElementById('status-tab-btn').click();" class="btn btn-xs btn-outline-danger py-1.5"><i class="bi bi-shield-exclamation me-1"></i>स्थिति बदलें</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="bg-white rounded border border-light p-1 shadow-xs mb-4 font-hindi small">
    <ul class="nav nav-pills nav-fill" id="profileTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link py-2 <?php echo ($active_tab === 'overview') ? 'active' : ''; ?>" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button">सामान्य विवरण</button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-2 <?php echo ($active_tab === 'membership') ? 'active' : ''; ?>" id="membership-tab" data-bs-toggle="tab" data-bs-target="#membership" type="button">सदस्यता विवरण</button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-2 <?php echo ($active_tab === 'contact') ? 'active' : ''; ?>" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button">संपर्क व पता</button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-2 <?php echo ($active_tab === 'documents') ? 'active' : ''; ?>" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button">दस्तावेज (<?php echo count($documents); ?>)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-2 <?php echo ($active_tab === 'barfee') ? 'active' : ''; ?>" id="barfee-tab" data-bs-toggle="tab" data-bs-target="#barfee" type="button">बार संघ शुल्क (Bar Fee)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-2 <?php echo ($active_tab === 'account') ? 'active' : ''; ?>" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button">लॉगिन खाता</button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-2 <?php echo ($active_tab === 'history') ? 'active' : ''; ?>" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button">संशोधन इतिहास Logs</button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-2 <?php echo ($active_tab === 'status') ? 'active bg-danger text-white' : ''; ?>" id="status-tab-btn" data-bs-toggle="tab" data-bs-target="#status" type="button">स्थिति परिवर्तन</button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-2 text-muted" id="future-tab" data-bs-toggle="tab" data-bs-target="#future" type="button">भविष्य सेवाएं</button>
        </li>
    </ul>
</div>

<!-- Tabs Content Panels -->
<div class="tab-content" id="profileTabsContent">
    
    <!-- Tab 1: Overview -->
    <div class="tab-pane fade <?php echo ($active_tab === 'overview') ? 'show active' : ''; ?>" id="overview" role="tabpanel">
        <div class="bg-white p-4 border rounded shadow-sm font-hindi small text-dark-custom">
            <h5 class="fw-bold text-navy-custom mb-3 border-bottom pb-2">सामान्य अवलोकन (General Overview)</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="text-muted d-block">अधिवक्ता का नाम:</span>
                    <span class="fw-semibold text-navy-custom fs-6 english-text"><?php echo e($member['full_name']); ?></span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">पिता/पति का नाम:</span>
                    <span class="fw-semibold text-navy-custom fs-6"><?php echo e($member['father_or_husband_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">लिंग (Gender):</span>
                    <span class="fw-semibold text-navy-custom"><?php echo e($member['gender'] ?? 'N/A'); ?></span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">जन्म तिथि (DOB):</span>
                    <span class="fw-semibold text-navy-custom english-text"><?php echo !empty($member['date_of_birth']) ? date('d-m-Y', strtotime($member['date_of_birth'])) : 'N/A'; ?></span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">रक्त समूह (Blood):</span>
                    <span class="fw-semibold text-navy-custom"><?php echo e($member['blood_group'] ?? 'N/A'); ?></span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">चैंबर नंबर (Chamber No):</span>
                    <span class="fw-semibold text-navy-custom"><?php echo e($member['chamber_no'] ?? 'N/A'); ?></span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">मुख्य कार्य क्षेत्र (Practice Area):</span>
                    <span class="fw-semibold text-navy-custom"><?php echo e($member['practice_area'] ?? 'N/A'); ?></span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">कार्यकारिणी पदाधिकारी:</span>
                    <span class="fw-semibold"><?php echo $member['is_office_bearer'] == 1 ? '<span class="text-success">हां (Active Bearer)</span>' : '<span class="text-secondary">नहीं (General Member)</span>'; ?></span>
                </div>
            </div>
            
            <?php if (!empty($member['signature'])): ?>
                <div class="mt-4 border-top pt-3">
                    <span class="text-muted d-block mb-1">आधिकारिक डिजिटल हस्ताक्षर (Signature):</span>
                    <div class="bg-light p-2 d-inline-block rounded border" style="height: 60px;">
                        <img src="../../uploads/signatures/<?php echo e($member['signature']); ?>" alt="Signature" style="max-height: 100%; object-fit: contain;">
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab 2: Membership -->
    <div class="tab-pane fade <?php echo ($active_tab === 'membership') ? 'show active' : ''; ?>" id="membership" role="tabpanel">
        <div class="bg-white p-4 border rounded shadow-sm font-hindi small">
            <h5 class="fw-bold text-navy-custom mb-3 border-bottom pb-2">सदस्यता एवं नामांकन (Membership Details)</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="text-muted d-block">बार एसोसिएशन कोड (DBA Code):</span>
                    <span class="fw-bold text-navy-custom english-text"><?php echo e($member['membership_no']); ?></span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">बार काउंसिल पंजीकरण संख्या (Bar Council No):</span>
                    <span class="fw-bold text-navy-custom english-text"><?php echo e($member['enrollment_no']); ?></span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">नामांकन तिथि (Bar Council Enrollment Date):</span>
                    <span class="fw-semibold english-text"><?php echo !empty($member['enrollment_date']) ? date('d-m-Y', strtotime($member['enrollment_date'])) : 'N/A'; ?></span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">संघ सदस्यता तिथि (Member Since):</span>
                    <span class="fw-semibold english-text"><?php echo date('d-m-Y', strtotime($member['member_since'])); ?></span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">सदस्यता श्रेणी:</span>
                    <span class="fw-semibold text-navy-custom"><?php echo e($member['membership_category']); ?></span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">पब्लिक डायरेक्टरी विजिबिलिटी:</span>
                    <span class="fw-semibold"><?php echo $member['is_public'] == 1 ? '<span class="text-success">हां (सार्वजनिक रूप से दर्शनीय)</span>' : '<span class="text-danger">नहीं (गोपनीय / निजी)</span>'; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 3: Contact & Address -->
    <div class="tab-pane fade <?php echo ($active_tab === 'contact') ? 'show active' : ''; ?>" id="contact" role="tabpanel">
        <div class="bg-white p-4 border rounded shadow-sm font-hindi small">
            <h5 class="fw-bold text-navy-custom mb-3 border-bottom pb-2">संपर्क विवरण एवं पते (Contact & Addresses)</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="text-muted d-block">मोबाइल (Primary Mobile) *:</span>
                    <span class="fw-bold text-navy-custom english-text"><?php echo e($member['mobile'] ?? 'N/A'); ?></span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">वैकल्पिक मोबाइल:</span>
                    <span class="fw-semibold english-text"><?php echo e($member['alternate_mobile'] ?? 'N/A'); ?></span>
                </div>
                <div class="col-md-12">
                    <span class="text-muted d-block">ईमेल पता (Email):</span>
                    <span class="fw-semibold text-navy-custom english-text"><?php echo e($member['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">पब्लिक विजिबिलिटी - मोबाइल नंबर:</span>
                    <span class="fw-semibold"><?php echo $member['show_mobile_publicly'] == 1 ? '<span class="text-success">सार्वजनिक (Yes)</span>' : '<span class="text-danger">गोपनीय (Hidden)</span>'; ?></span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">पब्लिक विजिबिलिटी - ईमेल:</span>
                    <span class="fw-semibold"><?php echo $member['show_email_publicly'] == 1 ? '<span class="text-success">सार्वजनिक (Yes)</span>' : '<span class="text-danger">गोपनीय (Hidden)</span>'; ?></span>
                </div>
                
                <div class="col-md-12 border-top pt-2">
                    <span class="text-muted d-block">स्थानीय पता (Local Address):</span>
                    <span class="fw-semibold text-navy-custom"><?php echo !empty($member['local_address']) ? e($member['local_address']) : 'N/A'; ?></span>
                </div>
                <div class="col-md-12">
                    <span class="text-muted d-block">स्थायी पता (Permanent Address):</span>
                    <span class="fw-semibold text-navy-custom"><?php echo !empty($member['permanent_address']) ? e($member['permanent_address']) : 'N/A'; ?></span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">जिला / राज्य (District/State):</span>
                    <span class="fw-semibold"><?php echo e($member['district']); ?>, <?php echo e($member['state']); ?></span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">पिन कोड (Pincode):</span>
                    <span class="fw-semibold english-text"><?php echo e($member['pincode'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="col-md-12 border-top pt-3 text-warning-custom">
                    <h6 class="fw-bold text-navy-custom mb-1"><i class="bi bi-telephone-outbound-fill me-1"></i>आपातकालीन संपर्क विवरण (Emergency Contact)</h6>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <span class="text-muted">व्यक्ति का नाम:</span>
                            <span class="fw-semibold text-navy-custom d-block"><?php echo e($member['emergency_contact_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted">मोबाइल नंबर:</span>
                            <span class="fw-semibold text-navy-custom d-block english-text"><?php echo e($member['emergency_contact_mobile'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 4: Documents -->
    <div class="tab-pane fade <?php echo ($active_tab === 'documents') ? 'show active' : ''; ?>" id="documents" role="tabpanel">
        <div class="bg-white p-4 border rounded shadow-sm font-hindi small">
            <h5 class="fw-bold text-navy-custom mb-3 border-bottom pb-2 d-flex justify-content-between align-items-center">
                <span>दस्तावेज सूची (Verification Documents)</span>
                <?php if (hasPermission('members.manage')): ?>
                    <a href="documents.php?member_id=<?php echo e($member['id']); ?>" class="btn btn-xs btn-navy py-1.5"><i class="bi bi-upload me-1"></i>नया दस्तावेज जोड़ें</a>
                <?php endif; ?>
            </h5>
            
            <?php if (empty($documents)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-file-earmark-lock display-6 d-block mb-1"></i>
                    कोई दस्तावेज अभी तक अपलोड नहीं किया गया है।
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>प्रकार (Doc Type)</th>
                                <th>दस्तावेज नाम</th>
                                <th>अपलोड तिथि</th>
                                <th>सत्यापन</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td class="fw-semibold text-navy-custom"><?php echo e($doc_labels[$doc['document_type']] ?? $doc['document_type']); ?></td>
                                    <td class="english-text"><?php echo e($doc['document_name']); ?></td>
                                    <td class="english-text"><?php echo date('d-m-Y H:i', strtotime($doc['created_at'])); ?></td>
                                    <td>
                                        <?php 
                                        $v_status = $doc['verification_status'];
                                        if ($v_status === 'verified') echo '<span class="badge bg-success font-size-xs px-2 py-0.5">सत्यापित</span>';
                                        elseif ($v_status === 'rejected') echo '<span class="badge bg-danger font-size-xs px-2 py-0.5">अस्वीकृत</span>';
                                        else echo '<span class="badge bg-warning text-dark font-size-xs px-2 py-0.5">लंबित</span>';
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="download-doc.php?id=<?php echo e($doc['id']); ?>" class="btn btn-xs btn-outline-navy py-0.5 px-2" target="_blank"><i class="bi bi-download"></i></a>
                                        <?php if (hasPermission('members.manage')): ?>
                                            <a href="documents.php?member_id=<?php echo e($member['id']); ?>" class="btn btn-xs btn-outline-secondary py-0.5 px-2"><i class="bi bi-pencil"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab 5: Account -->
    <div class="tab-pane fade <?php echo ($active_tab === 'account') ? 'show active' : ''; ?>" id="account" role="tabpanel">
        <div class="bg-white p-4 border rounded shadow-sm font-hindi small">
            <h5 class="fw-bold text-navy-custom mb-3 border-bottom pb-2">लॉगिन खाता प्रबंधन (Linked Login Account)</h5>
            
            <?php if (empty($member['username'])): ?>
                <div class="alert alert-warning border-0 mb-3">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> इस सदस्य के पास वर्तमान में कोई संबद्ध लॉगिन खाता नहीं है।
                </div>
                
                <?php if (hasPermission('members.manage')): ?>
                    <form method="POST" action="account-action.php" class="border p-3 rounded bg-light-custom">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="create_account">
                        <input type="hidden" name="member_id" value="<?php echo e($member['id']); ?>">
                        
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label text-secondary fw-semibold">लॉगिन यूजरनेम *</label>
                                <input type="text" class="form-control form-control-sm english-text" name="username" value="<?php echo e('adv_' . strtolower(explode(' ', str_replace('.', '', $member['full_name'] ?? ''))[1] ?? 'adv')); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary fw-semibold">अस्थायी पासवर्ड *</label>
                                <input type="text" class="form-control form-control-sm english-text" name="password" value="<?php echo e(bin2hex(random_bytes(4))); ?>" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-sm btn-navy w-100 py-1.5"><i class="bi bi-person-lock me-1 text-gold-custom"></i>खाता बनाएं व लिंक करें</button>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">नोट: यह खाता बार सदस्य की भूमिका (Bar Member) के रूप में काम करेगा।</small>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-4 border-bottom pb-2">
                        <span class="text-muted d-block">यूजरनेम:</span>
                        <strong class="text-navy-custom english-text"><?php echo e($member['username']); ?></strong>
                    </div>
                    <div class="col-md-4 border-bottom pb-2">
                        <span class="text-muted d-block">लॉगिन स्थिति (Status):</span>
                        <span class="badge <?php echo ($member['user_status'] === 'active') ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo e(ucfirst($member['user_status'] ?? '')); ?>
                        </span>
                    </div>
                    <div class="col-md-4 border-bottom pb-2">
                        <span class="text-muted d-block">अंतिम लॉगिन तिथि:</span>
                        <strong class="text-secondary-custom english-text"><?php echo !empty($member['last_login_at']) ? date('d-m-Y H:i', strtotime($member['last_login_at'])) : 'Never'; ?></strong>
                    </div>
                </div>

                <?php if (hasPermission('members.manage')): ?>
                    <div class="row g-3 border-top pt-3">
                        <!-- Reset password Form -->
                        <div class="col-md-6 border-end">
                            <h6 class="fw-bold text-navy-custom mb-3"><i class="bi bi-key-fill text-gold-custom me-1"></i>पासवर्ड रीसेट करें (Reset Password)</h6>
                            <form method="POST" action="account-action.php">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="member_id" value="<?php echo e($member['id']); ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label text-secondary fw-semibold">नया अस्थायी पासवर्ड *</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control english-text" name="new_password" id="new_password_field" value="<?php echo e(bin2hex(random_bytes(4))); ?>" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('new_password_field').value = Math.random().toString(36).slice(-8);">जेनरेट</button>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-xs btn-navy" onclick="return confirm('क्या आप वाकई सदस्य पासवर्ड रीसेट करना चाहते हैं?');">अस्थायी पासवर्ड अपडेट करें</button>
                            </form>
                        </div>

                        <!-- Account Status Form -->
                        <div class="col-md-6 ps-md-4">
                            <h6 class="fw-bold text-navy-custom mb-3"><i class="bi bi-shield-slash-fill text-danger me-1"></i>लॉगिन अक्षम / सक्षम करें (Lock/Disable)</h6>
                            <form method="POST" action="account-action.php">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="member_id" value="<?php echo e($member['id']); ?>">
                                <input type="hidden" name="current_status" value="<?php echo e($member['user_status']); ?>">
                                
                                <p class="text-muted font-size-xs">यदि सदस्य निलंबन पर है या भुगतान बकाया है, तो आप उनका वेब लॉगिन ब्लॉक कर सकते हैं।</p>
                                
                                <?php if ($member['user_status'] === 'active'): ?>
                                    <button type="submit" class="btn btn-xs btn-danger"><i class="bi bi-lock-fill me-1"></i>लॉगिन खाता बंद करें (Deactivate Login)</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-xs btn-success"><i class="bi bi-unlock-fill me-1"></i>लॉगिन खाता सक्रिय करें (Activate Login)</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab 6: History Logs -->
    <div class="tab-pane fade <?php echo ($active_tab === 'history') ? 'show active' : ''; ?>" id="history" role="tabpanel">
        <div class="bg-white p-4 border rounded shadow-sm font-hindi small">
            <h5 class="fw-bold text-navy-custom mb-3 border-bottom pb-2">सदस्य संशोधन इतिहास लॉग्स (Audit Trail Logs)</h5>
            
            <?php if (empty($history)): ?>
                <div class="text-center py-4 text-muted">इस सदस्य के विरूद्ध कोई इतिहास लॉग्स उपलब्ध नहीं हैं।</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>तारीख और समय</th>
                                <th>संशोधन क्रिया</th>
                                <th>फ़ील्ड</th>
                                <th>पुरानी वैल्यू</th>
                                <th>नई वैल्यू</th>
                                <th>कर्ता (Admin)</th>
                            </tr>
                        </thead>
                        <tbody class="english-text" style="font-size: 0.8rem;">
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y H:i:s', strtotime($h['created_at'])); ?></td>
                                    <td class="font-hindi text-navy-custom fw-semibold"><?php echo e($h['action']); ?></td>
                                    <td><code><?php echo e($h['field_name'] ?? 'General'); ?></code></td>
                                    <td class="text-truncate" style="max-width: 120px;"><?php echo e($h['old_value'] ?? 'N/A'); ?></td>
                                    <td class="text-truncate" style="max-width: 120px;"><?php echo e($h['new_value'] ?? 'N/A'); ?></td>
                                    <td class="font-hindi"><?php echo e($h['performed_by_username'] ?? 'System'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab 7: Status change -->
    <div class="tab-pane fade <?php echo ($active_tab === 'status') ? 'show active' : ''; ?>" id="status" role="tabpanel">
        <div class="bg-white p-4 border rounded shadow-sm font-hindi small">
            <h5 class="fw-bold text-danger mb-3 border-bottom pb-2"><i class="bi bi-shield-exclamation text-danger me-2"></i>सदस्यता स्थिति परिवर्तन (Membership Status Transition)</h5>
            
            <form method="POST" action="status-action.php" class="bg-light-custom p-3 rounded border">
                <?php csrfField(); ?>
                <input type="hidden" name="member_id" value="<?php echo e($member['id']); ?>">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-semibold">वर्तमान स्थिति:</label>
                        <input type="text" class="form-control form-control-sm text-navy-custom fw-bold" readonly value="<?php echo e($status_labels[$member['membership_status']] ?? $member['membership_status']); ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label for="new_status" class="form-label text-secondary fw-semibold">नवीनतम स्थिति चुनें *</label>
                        <select class="form-select form-select-sm" id="new_status" name="new_status" required>
                            <option value="">चुनें...</option>
                            <option value="active" <?php echo ($member['membership_status'] === 'active') ? 'disabled' : ''; ?>>सक्रिय (Active)</option>
                            <option value="inactive" <?php echo ($member['membership_status'] === 'inactive') ? 'disabled' : ''; ?>>निष्क्रिय (Inactive)</option>
                            <option value="suspended" <?php echo ($member['membership_status'] === 'suspended') ? 'disabled' : ''; ?>>निलंबित (Suspended)</option>
                            <option value="expired" <?php echo ($member['membership_status'] === 'expired') ? 'disabled' : ''; ?>>समाप्त (Expired)</option>
                            <option value="deceased" <?php echo ($member['membership_status'] === 'deceased') ? 'disabled' : ''; ?>>दिवंगत (Deceased)</option>
                            <option value="pending" <?php echo ($member['membership_status'] === 'pending') ? 'disabled' : ''; ?>>लंबित (Pending)</option>
                            <option value="rejected" <?php echo ($member['membership_status'] === 'rejected') ? 'disabled' : ''; ?>>अस्वीकृत (Rejected)</option>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label for="status_reason" class="form-label text-secondary fw-semibold">परिवर्तन का वैध कारण / टिप्पणी (Remarks) *</label>
                        <textarea class="form-control" id="status_reason" name="status_reason" rows="2" placeholder="Deceased, Suspended या Rejected होने पर विवरण लिखना अनिवार्य है।" required></textarea>
                    </div>
                    
                    <div class="col-12 mt-3 text-end">
                        <button type="submit" class="btn btn-sm btn-danger px-4" onclick="return confirm('क्या आप वास्तव में इस सदस्य की स्थिति परिवर्तित करना चाहते हैं?');">
                            <i class="bi bi-check-circle me-1"></i> स्थिति अपडेट करें
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab 7.5: Bar Fee Details -->
    <div class="tab-pane fade <?php echo ($active_tab === 'barfee') ? 'show active' : ''; ?>" id="barfee" role="tabpanel">
        <div class="bg-white p-4 border rounded shadow-sm font-hindi small">
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                <h5 class="fw-bold text-navy-custom mb-0"><i class="bi bi-currency-rupee text-gold-dark me-2"></i>अधिवक्ता शुल्क विवरणी (Bar Fee Registry)</h5>
                <div>
                    <a href="../bar-fee/member-ledger.php?member_id=<?php echo $member['id']; ?>" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-file-earmark-spreadsheet me-1"></i>शुल्क खाता बही खोलें (Open Ledger)</a>
                    <a href="../bar-fee/payments/create.php?member_id=<?php echo $member['id']; ?>" class="btn btn-xs btn-success fw-semibold"><i class="bi bi-cash-stack me-1"></i>भुगतान प्राप्त करें</a>
                </div>
            </div>

            <!-- Dues Summary Indicators -->
            <div class="row g-2 text-center text-navy-custom mb-4 font-hindi small">
                <div class="col-4 border-end">
                    <span class="text-secondary d-block font-size-xs">कुल शुल्क देयता</span>
                    <strong class="fs-6 text-navy-custom">₹<?php echo number_format($fee_summary['total_due'], 2); ?></strong>
                </div>
                <div class="col-4 border-end">
                    <span class="text-secondary d-block font-size-xs">कुल प्राप्त जमा</span>
                    <strong class="fs-6 text-success">₹<?php echo number_format($fee_summary['total_paid'], 2); ?></strong>
                </div>
                <div class="col-4">
                    <span class="text-secondary d-block font-size-xs">शेष बकाया</span>
                    <strong class="fs-6 text-danger">₹<?php echo number_format($fee_summary['total_outstanding'], 2); ?></strong>
                </div>
            </div>

            <!-- Current Dues Table -->
            <h6 class="fw-bold mb-2">बकाया शुल्क देयताएं (Current Dues):</h6>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>शुल्क मद</th>
                            <th>अवधि</th>
                            <th>देय तिथि</th>
                            <th class="text-end">कुल देय</th>
                            <th class="text-end">प्राप्त जमा</th>
                            <th class="text-end">बकाया</th>
                            <th>स्थिति</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($member_dues)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-2 text-muted">कोई निर्धारित शुल्क देय उपलब्ध नहीं है।</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($member_dues as $d): ?>
                                <tr>
                                    <td><?php echo e($d['fee_name']); ?></td>
                                    <td class="english-text"><?php echo e($d['period_label'] ?: $d['financial_year']); ?></td>
                                    <td class="english-text"><?php echo date('d-m-Y', strtotime($d['due_date'])); ?></td>
                                    <td class="text-end english-text">₹<?php echo number_format($d['payable_amount'], 2); ?></td>
                                    <td class="text-end text-success english-text">₹<?php echo number_format($d['paid_amount'], 2); ?></td>
                                    <td class="text-end text-danger fw-bold english-text">₹<?php echo number_format($d['outstanding_amount'], 2); ?></td>
                                    <td>
                                        <?php 
                                        $st = $d['status'];
                                        $c = ($st === 'paid') ? 'bg-success' : (($st === 'pending') ? 'bg-danger' : 'bg-warning text-dark');
                                        ?>
                                        <span class="badge <?php echo $c; ?> font-size-xs"><?php echo e(ucfirst($st)); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Confirmed Payments -->
            <h6 class="fw-bold mb-2">हालिया प्राप्त शुल्क रसीदें (Fee Payments):</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>रसीद संख्या</th>
                            <th>भुगतान तिथि</th>
                            <th>माध्यम</th>
                            <th>लेनदेन सन्दर्भ</th>
                            <th class="text-end">प्राप्त राशि</th>
                            <th class="text-end">एक्शन</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($member_payments)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-2 text-muted">कोई भुगतान रसीद उपलब्ध नहीं है।</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($member_payments as $mp): ?>
                                <tr>
                                    <td class="english-text fw-bold text-navy-custom"><?php echo e($mp['receipt_no']); ?></td>
                                    <td class="english-text"><?php echo date('d-m-Y', strtotime($mp['payment_date'])); ?></td>
                                    <td><?php echo e($mp['payment_mode']); ?></td>
                                    <td class="english-text"><?php echo e($mp['transaction_reference'] ?: '-'); ?></td>
                                    <td class="text-end text-success fw-bold english-text">₹<?php echo number_format($mp['amount'], 2); ?></td>
                                    <td class="text-end">
                                        <a href="../bar-fee/receipt.php?id=<?php echo $mp['id']; ?>" target="_blank" class="btn btn-xs btn-outline-navy py-0.5">प्रिंट रसीद</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 8: Future Services Placeholders & Live Integrations -->
    <div class="tab-pane fade" id="future" role="tabpanel">
        <div class="bg-white p-4 border rounded shadow-sm font-hindi small">
            <h5 class="fw-bold text-navy-custom mb-3 border-bottom pb-2">एकीकृत डिजिटल सेवाएं (Digital Services Status)</h5>
            
            <div class="row g-3">
                <!-- Wakalatnama Downloads Live Card -->
                <div class="col-md-6 col-12">
                    <div class="p-3 border rounded bg-white shadow-xs">
                        <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-2">
                            <i class="bi bi-file-earmark-text text-gold-dark me-2"></i>वकालतनामा डाउनलोड सेवाएं (Wakalatnama)
                        </h6>
                        <?php
                        $w_total = 0;
                        $w_last = 'कभी नहीं (Never)';
                        $w_recent = [];
                        if ($db) {
                            try {
                                $w_total_stmt = $db->prepare("SELECT COUNT(*) FROM wakalatnama_downloads WHERE member_id = ? AND download_status = 'success'");
                                $w_total_stmt->execute([$member['id']]);
                                $w_total = $w_total_stmt->fetchColumn();

                                $w_last_stmt = $db->prepare("SELECT downloaded_at FROM wakalatnama_downloads WHERE member_id = ? AND download_status = 'success' ORDER BY id DESC LIMIT 1");
                                $w_last_stmt->execute([$member['id']]);
                                $last_date = $w_last_stmt->fetchColumn();
                                if ($last_date) {
                                    $w_last = date('d-m-Y H:i', strtotime($last_date));
                                }

                                $w_recent_stmt = $db->prepare("
                                    SELECT d.*, w.title 
                                    FROM wakalatnama_downloads d 
                                    JOIN wakalatnamas w ON w.id = d.wakalatnama_id 
                                    WHERE d.member_id = ? AND d.download_status = 'success' 
                                    ORDER BY d.id DESC LIMIT 5
                                ");
                                $w_recent_stmt->execute([$member['id']]);
                                $w_recent = $w_recent_stmt->fetchAll();
                            } catch (PDOException $e) {
                                error_log("Failed to query member stats: " . $e->getMessage());
                            }
                        }
                        ?>
                        
                        <div class="row text-muted g-2 mb-3" style="font-size:0.75rem;">
                            <div class="col-6">
                                <span>कुल डाउनलोड्स:</span>
                                <strong class="text-navy-custom d-block english-text"><?php echo $w_total; ?> बार</strong>
                            </div>
                            <div class="col-6">
                                <span>अंतिम डाउनलोड:</span>
                                <strong class="text-navy-custom d-block english-text"><?php echo $w_last; ?></strong>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-1" style="font-size:0.72rem;">हाल ही के डाउनलोड्स:</h6>
                        <?php if (empty($w_recent)): ?>
                            <p class="text-muted italic" style="font-size:0.68rem;">कोई डाउनलोड इतिहास नहीं मिला।</p>
                        <?php else: ?>
                            <ul class="list-unstyled mb-3 text-muted ps-2" style="font-size:0.68rem; line-height:1.4;">
                                <?php foreach ($w_recent as $wr): ?>
                                    <li><i class="bi bi-caret-right-fill text-gold-dark me-1"></i><?php echo e($wr['title']); ?> (v<?php echo e($wr['version']); ?>) - <span class="english-text"><?php echo date('d-m-Y', strtotime($wr['downloaded_at'])); ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <div class="text-end">
                            <a href="../wakalatnama/report.php?search=<?php echo urlencode($member['membership_no']); ?>" class="btn btn-xs btn-outline-navy fw-semibold">
                                <i class="bi bi-clock-history me-1"></i>पूर्ण डाउनलोड इतिहास (View Complete History)
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Digital ID Card Live Card -->
                <div class="col-md-6 col-12">
                    <div class="p-3 border rounded bg-white shadow-xs">
                        <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-2">
                            <i class="bi bi-card-image text-gold-dark me-2"></i>डिजिटल आईडी कार्ड (Digital ID Card)
                        </h6>
                        <?php
                        $card = null;
                        if ($db) {
                            try {
                                $c_stmt = $db->prepare("SELECT * FROM id_cards WHERE member_id = ? ORDER BY id DESC LIMIT 1");
                                $c_stmt->execute([$member['id']]);
                                $card = $c_stmt->fetch();
                            } catch (PDOException $e) {
                                error_log("Failed loading card: " . $e->getMessage());
                            }
                        }
                        ?>
                        
                        <?php if ($card): ?>
                            <div class="row text-muted g-2 mb-3" style="font-size:0.75rem;">
                                <div class="col-6">
                                    <span>कार्ड नंबर:</span>
                                    <strong class="text-navy-custom d-block english-text"><?php echo e($card['card_number']); ?></strong>
                                </div>
                                <div class="col-6">
                                    <span>स्थिति:</span>
                                    <strong class="text-success d-block"><?php echo e($card['status']); ?></strong>
                                </div>
                            </div>
                            <div class="text-end">
                                <a href="../id-cards/view.php?id=<?php echo $card['id']; ?>" class="btn btn-xs btn-navy fw-semibold">कार्ड विवरण देखें</a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted py-2" style="font-size: 0.75rem;">इस सदस्य के लिए कोई सक्रिय डिजिटल आईडी कार्ड जारी नहीं किया गया है।</p>
                            <div class="text-end">
                                <a href="../id-cards/index.php" class="btn btn-xs btn-outline-navy fw-semibold">आईडी कार्ड सूची</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Roadmaps placeholders for remaining items -->
                <div class="col-md-4 col-12">
                    <a href="#barfee" onclick="document.getElementById('barfee-tab').click();" class="text-decoration-none">
                        <div class="p-3 border rounded text-center bg-white shadow-xs">
                            <i class="bi bi-currency-rupee text-success fs-3 mb-2 d-block"></i>
                            <h6 class="fw-bold text-navy-custom">वार्षिक संघ शुल्क (Bar Fee)</h6>
                            <span class="badge bg-success font-size-xs">सक्रिय (Live)</span>
                        </div>
                    </a>
                </div>
                <div class="col-md-4 col-12">
                    <?php if ($chamber_summary['has_chamber']): ?>
                        <a href="../rooms/view.php?id=<?php echo $chamber_summary['allotment_id']; ?>" class="text-decoration-none text-navy-custom">
                            <div class="p-3 border rounded text-center bg-white shadow-xs">
                                <i class="bi bi-door-closed text-danger fs-3 mb-2 d-block"></i>
                                <h6 class="fw-bold text-navy-custom">चैंबर: <?php echo e($chamber_summary['chamber_no']); ?></h6>
                                <span class="badge bg-danger font-size-xs mb-1">किराया बकाया: ₹<?php echo number_format($chamber_summary['outstanding_rent'], 2); ?></span>
                                <small class="d-block text-muted" style="font-size: 0.68rem;">क्लिक करें: चैंबर विवरण</small>
                            </div>
                        </a>
                    <?php else: ?>
                        <a href="../rooms/applications.php" class="text-decoration-none text-muted">
                            <div class="p-3 border rounded text-center bg-light">
                                <i class="bi bi-door-closed text-muted fs-3 mb-2 d-block"></i>
                                <h6 class="fw-bold">कक्ष/चैंबर (None Allotted)</h6>
                                <span class="badge bg-secondary font-size-xs">कोई आवंटन नहीं</span>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 col-12">
                    <?php if ($voter_summary): ?>
                        <a href="../elections/view.php?id=<?php echo $voter_summary['election_id']; ?>&tab=voters" class="text-decoration-none text-navy-custom">
                            <div class="p-3 border rounded text-center bg-white shadow-xs">
                                <i class="bi bi-check2-square text-success fs-3 mb-2 d-block"></i>
                                <h6 class="fw-bold text-navy-custom">वोटर नं: <?php echo e($voter_summary['voter_no'] ?: '-'); ?></h6>
                                <span class="badge bg-success font-size-xs mb-1">पात्रता: <?php echo e($voter_summary['eligibility_status']); ?></span>
                                <small class="d-block text-muted" style="font-size: 0.68rem;"><?php echo e($voter_summary['election_code']); ?></small>
                            </div>
                        </a>
                    <?php else: ?>
                        <a href="../elections/index.php" class="text-decoration-none text-muted">
                            <div class="p-3 border rounded text-center bg-light">
                                <i class="bi bi-check2-square text-muted fs-3 mb-2 d-block"></i>
                                <h6 class="fw-bold">चुनाव एवं वोटर सूची</h6>
                                <span class="badge bg-secondary font-size-xs">कोई प्रविष्टि नहीं</span>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Association Roles History (Phase 11 Requirement 23) -->
            <div class="card border-0 shadow-sm mt-4 font-hindi small text-navy-custom">
                <div class="card-header bg-navy-custom text-white py-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-award-fill text-gold-custom me-2"></i>संघीय दायित्व एवं इतिहास (Association Roles History)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size:0.75rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>सत्र कार्यकाल (Term)</th>
                                    <th>संवैधानिक पद (Designation)</th>
                                    <th>अवधि (Tenure Dates)</th>
                                    <th>दायित्व स्थिति (Status)</th>
                                    <th>पदाधिकारी संदेश (Message)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($roles_history)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3 text-muted">इस सदस्य के लिए कोई ऐतिहासिक संघ दायित्व दर्ज नहीं है।</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($roles_history as $rh): ?>
                                        <tr>
                                            <td><strong><?php echo e($rh['term_title']); ?></strong></td>
                                            <td>
                                                <strong><?php echo e($rh['designation_override'] ?: $rh['position_name_hindi']); ?></strong><br>
                                                <span class="text-muted text-uppercase" style="font-size:0.65rem;"><?php echo e($rh['position_name']); ?></span>
                                            </td>
                                            <td class="english-text">
                                                <?php echo $rh['start_date'] ? date('d-m-Y', strtotime($rh['start_date'])) : '-'; ?> से 
                                                <?php echo $rh['end_date'] ? date('d-m-Y', strtotime($rh['end_date'])) : (($rh['status'] === 'active') ? 'सक्रिय' : '-'); ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $col = ($rh['status'] === 'active') ? 'bg-success' : 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $col; ?> font-size-xs"><?php echo e(ucfirst($rh['status'])); ?></span>
                                            </td>
                                            <td><span class="text-muted text-truncate d-block" style="max-width:200px;"><?php echo e($rh['message'] ?: '-'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
