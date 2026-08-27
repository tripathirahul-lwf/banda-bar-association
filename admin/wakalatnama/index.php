<?php
/**
 * Admin / Mahasachiv Wakalatnama List & Document Management Queue
 * District Bar Association, Banda
 */

$pageTitle = 'वकालतनामा प्रबंधन (Wakalatnama Management)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';

$db = Database::getConnection();
$documents = [];

// Handle Quick State Actions (Activate / Deactivate / Archive)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect('index.php');
    }
    
    $doc_id = intval($_POST['doc_id'] ?? 0);
    $action = trim($_POST['action']);
    
    $valid_statuses = [
        'activate' => 'active',
        'deactivate' => 'inactive',
        'archive' => 'archived',
        'submit' => 'pending_approval'
    ];
    
    if ($doc_id > 0 && array_key_exists($action, $valid_statuses)) {
        $new_status = $valid_statuses[$action];
        try {
            $db->beginTransaction();
            $user_id = $_SESSION['auth']['user_id'];
            
            // Get current status
            $c_stmt = $db->prepare("SELECT status FROM wakalatnamas WHERE id = ?");
            $c_stmt->execute([$doc_id]);
            $old_status = $c_stmt->fetchColumn();
            
            // Update Status
            $up_stmt = $db->prepare("UPDATE wakalatnamas SET status = ? WHERE id = ?");
            $up_stmt->execute([$new_status, $doc_id]);
            
            // Log history
            $hist = $db->prepare("
                INSERT INTO wakalatnama_history (wakalatnama_id, action, old_status, new_status, remarks, performed_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $hist->execute([
                $doc_id, 
                ucfirst($action) . ' Action', 
                $old_status, 
                $new_status, 
                "कार्रवाई: " . ucfirst($action) . " संपन्न किया गया।", 
                $user_id
            ]);
            
            // Update Audit Log
            $log_desc = "Wakalatnama ID $doc_id status changed from $old_status to $new_status";
            $audit = $db->prepare("
                INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                VALUES (0, 'Wakalatnama Status Update', 'status', ?, ?, ?, ?)
            ");
            $audit->execute([$old_status, $new_status, $log_desc, $user_id]);
            
            $db->commit();
            setFlash('success', 'दस्तावेज की स्थिति सफलतापूर्वक अपडेट की गई।');
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed updating wakalatnama status: " . $e->getMessage());
            setFlash('danger', 'अपडेट करने में विफलता: ' . $e->getMessage());
        }
        redirect('index.php');
    }
}

if ($db) {
    try {
        $conditions = ["1=1"];
        $params = [];
        
        if (!empty($search)) {
            $conditions[] = "(title LIKE ? OR title_hindi LIKE ? OR version LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if (!empty($status_filter)) {
            $conditions[] = "status = ?";
            $params[] = $status_filter;
        }
        if (!empty($category_filter)) {
            $conditions[] = "category = ?";
            $params[] = $category_filter;
        }
        
        $where = implode(" AND ", $conditions);
        
        // Fetch matching documents and count downloads count
        $stmt = $db->prepare("
            SELECT w.*, u.username as uploader_name,
                   (SELECT COUNT(*) FROM wakalatnama_downloads WHERE wakalatnama_id = w.id AND download_status = 'success') as total_downloads
            FROM wakalatnamas w
            LEFT JOIN users u ON u.id = w.uploaded_by
            WHERE $where
            ORDER BY w.id DESC
        ");
        $stmt->execute($params);
        $documents = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch wakalatnamas: " . $e->getMessage());
    }
}

$status_badges = [
    'draft' => 'bg-secondary',
    'pending_approval' => 'bg-warning text-dark',
    'active' => 'bg-success',
    'inactive' => 'bg-danger',
    'archived' => 'bg-dark'
];

$status_labels = [
    'draft' => 'ड्राफ्ट (Draft)',
    'pending_approval' => 'स्वीकृति लंबित',
    'active' => 'सक्रिय (Active)',
    'inactive' => 'निष्क्रिय',
    'archived' => 'संग्रहीत'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="fa-solid fa-file-signature text-gold-dark me-2"></i>वकालतनामा मास्टर सूची (Wakalatnama Management)</h4>
    <div class="d-flex gap-2">
        <a href="report.php" class="btn btn-sm btn-outline-secondary fw-semibold"><i class="fa-solid fa-chart-line me-1"></i>डाउनलोड रिपोर्ट</a>
        <button type="button" class="btn btn-sm btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#uploadWakaModal">
            <i class="fa-solid fa-circle-plus me-1 text-gold-custom"></i>नया अपलोड करें
        </button>
    </div>
</div>

<!-- Search & Filter Board -->
<div class="card p-3 mb-4 border-0 shadow-sm bg-light-custom font-hindi small" style="border-radius: 8px;">
    <form method="GET" action="index.php" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass" style="font-size: 0.8rem;"></i></span>
                <input type="text" class="form-control border-start-0 ps-1" name="search" placeholder="शीर्षक, संस्करण से खोजें..." value="<?php echo e($search); ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select class="form-select form-select-sm" name="status">
                <option value="">सभी स्थिति (All Status)</option>
                <?php foreach ($status_labels as $val => $lbl): ?>
                    <option value="<?php echo $val; ?>" <?php echo ($status_filter === $val) ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
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
            <button type="submit" class="btn btn-primary btn-sm fw-semibold"><i class="fa-solid fa-filter me-1"></i>फिल्टर लागू करें</button>
        </div>
    </form>
</div>

<!-- List Table -->
<div class="table-responsive bg-white rounded shadow-sm border font-hindi small mb-5">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th class="py-3 px-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">शीर्षक (Title)</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">श्रेणी</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">संस्करण</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">लागू तिथि</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">अपलोड कर्ता</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">डाउनलोड्स</th>
                <th class="py-3 text-navy-custom fw-bold" style="font-size: 0.8rem;">स्थिति (Status)</th>
                <th class="py-3 px-3 text-end text-navy-custom fw-bold" style="font-size: 0.8rem;">कार्य</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($documents)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">कोई वकालतनामा रिकॉर्ड नहीं मिला।</td>
                </tr>
            <?php else: ?>
                <?php foreach ($documents as $d): ?>
                    <tr>
                        <td class="py-3 px-3">
                            <strong class="text-navy-custom d-block english-text mb-0.5" style="font-size: 0.85rem;"><?php echo e($d['title']); ?></strong>
                            <?php if (!empty($d['title_hindi'])): ?>
                                <small class="text-muted d-block font-hindi" style="font-size: 0.73rem;"><?php echo e($d['title_hindi']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 text-secondary" style="font-size: 0.78rem;"><?php echo e($d['category']); ?></td>
                        <td class="py-3">
                            <span class="badge bg-light text-navy-custom border fw-bold english-text" style="font-size: 0.72rem;">
                                v<?php echo e($d['version']); ?>
                            </span>
                        </td>
                        <td class="py-3 english-text text-secondary" style="font-size: 0.75rem;"><?php echo date('d-m-Y', strtotime($d['effective_from'])); ?></td>
                        <td class="py-3 english-text text-secondary" style="font-size: 0.78rem;"><?php echo e($d['uploader_name'] ?: 'System'); ?></td>
                        <td class="py-3">
                            <a href="history.php?id=<?php echo $d['id']; ?>" class="badge bg-light text-navy-custom border fw-bold text-decoration-none px-2.5 py-1" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-download me-1 text-gold-dark"></i><?php echo $d['total_downloads']; ?>
                            </a>
                        </td>
                        <td class="py-3">
                            <?php 
                            $st = $d['status'];
                            $lbl = $status_labels[$st] ?? $st;
                            if ($st === 'active') {
                                echo '<span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-success me-1" style="font-size: 0.45rem;"></i>' . $lbl . '</span>';
                            } elseif ($st === 'draft') {
                                echo '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-secondary me-1" style="font-size: 0.45rem;"></i>' . $lbl . '</span>';
                            } elseif ($st === 'pending_approval') {
                                echo '<span class="badge bg-warning bg-opacity-15 text-warning-dark border border-warning-subtle px-2 py-1" style="color: #a36200; background-color: rgba(255, 193, 7, 0.15); font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-warning-dark me-1" style="font-size: 0.45rem;"></i>' . $lbl . '</span>';
                            } elseif ($st === 'inactive') {
                                echo '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-danger me-1" style="font-size: 0.45rem;"></i>' . $lbl . '</span>';
                            } else {
                                echo '<span class="badge bg-dark bg-opacity-10 text-dark border border-dark-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 600;"><i class="fa-solid fa-circle text-dark me-1" style="font-size: 0.45rem;"></i>' . $lbl . '</span>';
                            }
                            ?>
                        </td>
                        <td class="py-3 px-3 text-end">
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle fw-semibold py-1 px-2" type="button" data-bs-toggle="dropdown" style="font-size: 0.73rem;">
                                    <i class="fa-solid fa-gears me-1"></i>कार्रवाई
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm font-hindi small">
                                    <li><a class="dropdown-item" href="history.php?id=<?php echo $d['id']; ?>"><i class="fa-solid fa-eye me-2 text-navy-custom"></i>विवरण व इतिहास</a></li>
                                    <li><a class="dropdown-item" href="edit.php?id=<?php echo $d['id']; ?>"><i class="fa-solid fa-pen-to-square me-2 text-warning"></i>विवरण सुधार (Edit)</a></li>
                                    <li><a class="dropdown-item" href="version.php?id=<?php echo $d['id']; ?>"><i class="fa-solid fa-code-branch me-2 text-success"></i>नया संस्करण अपलोड (New Ver)</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    
                                    <!-- Status changes with CSRF POST protection -->
                                    <?php if ($d['status'] === 'draft'): ?>
                                        <li>
                                            <form method="POST" action="index.php" style="display:inline;">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="doc_id" value="<?php echo $d['id']; ?>">
                                                <button type="submit" name="action" value="submit" class="dropdown-item text-info"><i class="fa-solid fa-share me-2"></i>समीक्षा हेतु भेजें</button>
                                            </form>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($d['status'], ['draft', 'inactive', 'pending_approval'])): ?>
                                        <li>
                                            <form method="POST" action="index.php" style="display:inline;">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="doc_id" value="<?php echo $d['id']; ?>">
                                                <button type="submit" name="action" value="activate" class="dropdown-item text-success" onclick="return confirm('क्या आप इसे सक्रिय (Active) करना चाहते हैं?');"><i class="fa-solid fa-circle-check me-2"></i>सक्रिय करें (Activate)</button>
                                            </form>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($d['status'] === 'active'): ?>
                                        <li>
                                            <form method="POST" action="index.php" style="display:inline;">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="doc_id" value="<?php echo $d['id']; ?>">
                                                <button type="submit" name="action" value="deactivate" class="dropdown-item text-warning" onclick="return confirm('क्या आप इसे निष्क्रिय करना चाहते हैं?');"><i class="fa-solid fa-circle-minus me-2"></i>निष्क्रिय करें (Deactivate)</button>
                                            </form>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($d['status'] !== 'archived'): ?>
                                        <li>
                                            <form method="POST" action="index.php" style="display:inline;">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="doc_id" value="<?php echo $d['id']; ?>">
                                                <button type="submit" name="action" value="archive" class="dropdown-item text-danger" onclick="return confirm('क्या आप इस दस्तावेज को सुरक्षित रूप से संग्रहित (Archive) करना चाहते हैं? इतिहास सुरक्षित रहेगा पर सदस्य डाउनलोड नहीं कर पाएंगे।');"><i class="fa-solid fa-box-archive me-2"></i>संग्रहित करें (Archive)</button>
                                            </form>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Upload Modal Popup -->
<div class="modal fade font-hindi" id="uploadWakaModal" tabindex="-1" aria-labelledby="uploadWakaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-navy-custom text-white py-3">
                <h5 class="modal-title fw-bold" id="uploadWakaModalLabel"><i class="fa-solid fa-file-signature text-gold-custom me-2"></i>नया वकालतनामा अपलोड करें</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="create.php" enctype="multipart/form-data">
                <?php csrfField(); ?>
                <div class="modal-body p-4 small">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="title" class="form-label text-secondary fw-semibold small">दस्तावेज का अंग्रेजी शीर्षक <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm english-text" id="title" name="title" placeholder="e.g. Civil Wakalatnama" required>
                        </div>
                        <div class="col-md-6">
                            <label for="title_hindi" class="form-label text-secondary fw-semibold small">दस्तावेज का हिंदी शीर्षक</label>
                            <input type="text" class="form-control form-control-sm" id="title_hindi" name="title_hindi" placeholder="e.g. दीवानी वकालतनामा">
                        </div>
                        <div class="col-md-6">
                            <label for="category" class="form-label text-secondary fw-semibold small">श्रेणी / प्रभाग <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="category" name="category" required>
                                <option value="">श्रेणी चुनें...</option>
                                <option value="General">General</option>
                                <option value="Civil">Civil</option>
                                <option value="Criminal">Criminal</option>
                                <option value="Family Court">Family Court</option>
                                <option value="Revenue">Revenue</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="version" class="form-label text-secondary fw-semibold small">संस्करण / Version</label>
                            <input type="text" class="form-control form-control-sm english-text" id="version" name="version" value="1.0" required>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check mt-4 pt-2">
                                <input class="form-check-input" type="checkbox" id="members_only" name="members_only" value="1" checked>
                                <label class="form-check-label fw-semibold text-navy-custom" for="members_only">केवल सदस्य</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="effective_from" class="form-label text-secondary fw-semibold small">प्रभावी तिथि <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm english-text" id="effective_from" name="effective_from" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="effective_until" class="form-label text-secondary fw-semibold small">प्रभावी अंतिम तिथि (Optional)</label>
                            <input type="date" class="form-control form-control-sm english-text" id="effective_until" name="effective_until">
                        </div>
                        <div class="col-md-6">
                            <label for="pdf_file" class="form-label text-secondary fw-semibold small">वकालतनामा पीडीएफ फाइल <span class="text-danger">*</span></label>
                            <input type="file" class="form-control form-control-sm" id="pdf_file" name="pdf_file" accept=".pdf" required>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label text-secondary fw-semibold small">कार्यप्रवाह स्थिति</label>
                            <select class="form-select form-select-sm" id="status" name="status">
                                <option value="draft">ड्राफ्ट सुरक्षित करें (Draft)</option>
                                <option value="pending_approval">अनुमोदन हेतु भेजें (Pending Approval)</option>
                                <option value="active">सीधे सक्रिय करें (Active)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label text-secondary fw-semibold small">संक्षिप्त टिप्पणी / विवरण</label>
                            <textarea class="form-control form-control-sm" id="description" name="description" rows="2" placeholder="दस्तावेज के बारे में संक्षिप्त जानकारी..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1.5" data-bs-dismiss="modal">रद्द करें</button>
                    <button type="submit" class="btn btn-sm btn-primary px-4 py-1.5 fw-semibold"><i class="fa-solid fa-cloud-arrow-up me-2"></i>दस्तावेज अपलोड करें</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
