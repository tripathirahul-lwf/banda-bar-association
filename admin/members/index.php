<?php
/**
 * Admin/Mahasachiv Searchable Member Directory - Full Management
 * District Bar Association, Banda
 */

$pageTitle = 'अधिवक्ता सदस्य प्रबंधन (Member Directory)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce permissions: Admin and Mahasachiv have access
requireRole(['admin', 'mahasachiv']);
requirePermission('members.view');

$db = Database::getConnection();

// --- 1. SEARCH, FILTERS & SORTING PARAMS ---
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$category = trim($_GET['category'] ?? '');
$gender = trim($_GET['gender'] ?? '');
$bearer = trim($_GET['bearer'] ?? '');
$chamber = trim($_GET['chamber'] ?? '');
$visibility = trim($_GET['visibility'] ?? '');
$enroll_year = trim($_GET['enroll_year'] ?? '');

// Sorting whitelist
$sort_by = trim($_GET['sort_by'] ?? 'membership_no');
$sort_order = strtoupper(trim($_GET['sort_order'] ?? 'ASC'));

$allowed_sorts = [
    'full_name' => 'full_name',
    'membership_no' => 'membership_no',
    'enrollment_no' => 'enrollment_no',
    'member_since' => 'member_since',
    'membership_status' => 'membership_status'
];

if (!array_key_exists($sort_by, $allowed_sorts)) {
    $sort_by = 'membership_no';
}
if ($sort_order !== 'ASC' && $sort_order !== 'DESC') {
    $sort_order = 'ASC';
}

// Pagination
$limit = intval($_GET['limit'] ?? 20);
if (!in_array($limit, [20, 50, 100])) {
    $limit = 20;
}
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// --- 2. BUILD DYNAMIC SQL QUERY ---
$sql_where = " WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql_where .= " AND (full_name LIKE :search OR membership_no LIKE :search OR enrollment_no LIKE :search OR mobile LIKE :search OR chamber_no LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status !== '') {
    $sql_where .= " AND membership_status = :status";
    $params[':status'] = $status;
}

if ($category !== '') {
    $sql_where .= " AND membership_category = :category";
    $params[':category'] = $category;
}

if ($gender !== '') {
    $sql_where .= " AND gender = :gender";
    $params[':gender'] = $gender;
}

if ($bearer !== '') {
    $sql_where .= " AND is_office_bearer = :bearer";
    $params[':bearer'] = ($bearer === 'yes') ? 1 : 0;
}

if ($chamber !== '') {
    if ($chamber === 'assigned') {
        $sql_where .= " AND chamber_no IS NOT NULL AND chamber_no != '' AND chamber_no != 'N/A'";
    } else {
        $sql_where .= " AND (chamber_no IS NULL OR chamber_no = '' OR chamber_no = 'N/A')";
    }
}

if ($visibility !== '') {
    $sql_where .= " AND is_public = :visibility";
    $params[':visibility'] = ($visibility === 'public') ? 1 : 0;
}

if ($enroll_year !== '' && is_numeric($enroll_year)) {
    $sql_where .= " AND YEAR(enrollment_date) = :enroll_year";
    $params[':enroll_year'] = $enroll_year;
}

// --- 3. FETCH DATA & ROW COUNT ---
$total_records = 0;
$members = [];

if ($db) {
    try {
        // Count total matching records for pagination
        $count_stmt = $db->prepare("SELECT COUNT(*) FROM members" . $sql_where);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();
        
        // Fetch matching records
        $sql_query = "SELECT * FROM members" . $sql_where . " ORDER BY " . $allowed_sorts[$sort_by] . " " . $sort_order . " LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql_query);
        
        // Bind integer limit/offset parameters separately for safety in PDO
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $members = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Admin members query failed: " . $e->getMessage());
    }
}

$total_pages = ceil($total_records / $limit);

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

// Helper to persist query parameters in URLs
function getQueryString($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return http_build_query($params);
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="fa-solid fa-users me-2"></i>अधिवक्ता सदस्य मास्टर फ़ाइल</h4>
    <div class="d-flex gap-2 mt-2 mt-md-0">
        <a href="print.php?<?php echo e(http_build_query($_GET)); ?>" target="_blank" class="btn btn-sm btn-outline-primary fw-semibold"><i class="fa-solid fa-print me-1"></i>रजिस्टर प्रिंट (Print A4)</a>
        <a href="export.php?<?php echo e(http_build_query($_GET)); ?>" class="btn btn-sm btn-outline-success fw-semibold"><i class="fa-solid fa-file-excel me-1"></i>एक्सेल निर्यात (CSV)</a>
        <?php if (hasPermission('members.manage')): ?>
            <a href="create.php" class="btn btn-sm btn-warning text-dark fw-bold"><i class="fa-solid fa-user-plus me-1"></i>नया सदस्य जोड़ें</a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Panel -->
<div class="bg-light-custom p-4 rounded border mb-4 font-hindi small">
    <form method="GET" action="index.php" class="row g-3">
        <!-- Text Search -->
        <div class="col-lg-3 col-md-6 col-12">
            <label for="search" class="form-label text-secondary fw-semibold">खोजें (Name, Memb#, Enroll#, Chamber, Mobile)</label>
            <input type="text" class="form-control form-control-sm" id="search" name="search" value="<?php echo e($search); ?>" placeholder="खोजें...">
        </div>

        <!-- Status -->
        <div class="col-lg-3 col-md-6 col-12">
            <label for="status" class="form-label text-secondary fw-semibold">सदस्यता स्थिति (Status)</label>
            <select class="form-select form-select-sm" id="status" name="status">
                <option value="">सभी (All)</option>
                <?php foreach ($status_labels as $key => $label): ?>
                    <option value="<?php echo e($key); ?>" <?php echo ($status === $key) ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Category -->
        <div class="col-lg-3 col-md-6 col-12">
            <label for="category" class="form-label text-secondary fw-semibold">सदस्यता श्रेणी (Category)</label>
            <select class="form-select form-select-sm" id="category" name="category">
                <option value="">सभी (All)</option>
                <option value="Regular Member" <?php echo ($category === 'Regular Member') ? 'selected' : ''; ?>>Regular Member (सामान्य)</option>
                <option value="Life Member" <?php echo ($category === 'Life Member') ? 'selected' : ''; ?>>Life Member (आजीवन)</option>
                <option value="Senior Member" <?php echo ($category === 'Senior Member') ? 'selected' : ''; ?>>Senior Member (वरिष्ठ)</option>
                <option value="Honorary Member" <?php echo ($category === 'Honorary Member') ? 'selected' : ''; ?>>Honorary Member (मानद)</option>
                <option value="Other" <?php echo ($category === 'Other') ? 'selected' : ''; ?>>Other (अन्य)</option>
            </select>
        </div>

        <!-- Gender -->
        <div class="col-lg-3 col-md-6 col-12">
            <label for="gender" class="form-label text-secondary fw-semibold">लिंग (Gender)</label>
            <select class="form-select form-select-sm" id="gender" name="gender">
                <option value="">सभी (All)</option>
                <option value="Male" <?php echo ($gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo ($gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?php echo ($gender === 'Other') ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>

        <!-- Office Bearer -->
        <div class="col-lg-3 col-md-6 col-12">
            <label for="bearer" class="form-label text-secondary fw-semibold">कार्यकारिणी पदाधिकारी</label>
            <select class="form-select form-select-sm" id="bearer" name="bearer">
                <option value="">सभी (All)</option>
                <option value="yes" <?php echo ($bearer === 'yes') ? 'selected' : ''; ?>>हाँ (Yes)</option>
                <option value="no" <?php echo ($bearer === 'no') ? 'selected' : ''; ?>>नहीं (No)</option>
            </select>
        </div>

        <!-- Chamber status -->
        <div class="col-lg-3 col-md-6 col-12">
            <label for="chamber" class="form-label text-secondary fw-semibold">चैंबर आवंटन</label>
            <select class="form-select form-select-sm" id="chamber" name="chamber">
                <option value="">सभी (All)</option>
                <option value="assigned" <?php echo ($chamber === 'assigned') ? 'selected' : ''; ?>>आवंटित (Assigned)</option>
                <option value="unassigned" <?php echo ($chamber === 'unassigned') ? 'selected' : ''; ?>>आवंटित नहीं (Unassigned)</option>
            </select>
        </div>

        <!-- Public Visibility -->
        <div class="col-lg-3 col-md-6 col-12">
            <label for="visibility" class="form-label text-secondary fw-semibold">पब्लिक विजिबिलिटी</label>
            <select class="form-select form-select-sm" id="visibility" name="visibility">
                <option value="">सभी (All)</option>
                <option value="public" <?php echo ($visibility === 'public') ? 'selected' : ''; ?>>सार्वजनिक (Public)</option>
                <option value="private" <?php echo ($visibility === 'private') ? 'selected' : ''; ?>>गोपनीय (Private)</option>
            </select>
        </div>

        <!-- Limit per page -->
        <div class="col-lg-3 col-md-6 col-12">
            <label for="limit" class="form-label text-secondary fw-semibold">प्रति पृष्ठ सदस्य संख्या</label>
            <select class="form-select form-select-sm" id="limit" name="limit">
                <option value="20" <?php echo ($limit == 20) ? 'selected' : ''; ?>>20</option>
                <option value="50" <?php echo ($limit == 50) ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo ($limit == 100) ? 'selected' : ''; ?>>100</option>
            </select>
        </div>

        <div class="col-12 d-flex justify-content-between align-items-center pt-2 border-top mt-3">
            <span class="text-muted font-size-xs">कुल परिणाम: <?php echo $total_records; ?> सदस्य रिकॉर्ड</span>
            <div>
                <a href="index.php" class="btn btn-sm btn-outline-secondary px-3">Reset Filters</a>
                <button type="submit" class="btn btn-sm btn-primary px-4 ms-1"><i class="fa-solid fa-filter text-gold-custom me-1"></i>खोजें / Apply Filters</button>
            </div>
        </div>
    </form>
</div>

<!-- Search Results Table -->
<div class="bg-white p-2 rounded border shadow-xs mb-4">
    <div class="table-responsive table-responsive-custom">
        <table class="table table-hover table-striped mb-0 align-middle">
            <thead class="table-dark bg-navy-custom text-white" style="font-size: 0.85rem;">
                <tr>
                    <th class="py-2 px-3">Photo</th>
                    <th class="py-2 px-2"><a href="index.php?<?php echo getQueryString(['sort_by' => 'full_name', 'sort_order' => ($sort_by === 'full_name' && $sort_order === 'ASC') ? 'DESC' : 'ASC']); ?>" class="text-white text-decoration-none">Advocate Name <i class="fa-solid fa-sort font-size-xs ms-0.5"></i></a></th>
                    <th class="py-2 px-2"><a href="index.php?<?php echo getQueryString(['sort_by' => 'membership_no', 'sort_order' => ($sort_by === 'membership_no' && $sort_order === 'ASC') ? 'DESC' : 'ASC']); ?>" class="text-white text-decoration-none">Memb. No <i class="fa-solid fa-sort font-size-xs ms-0.5"></i></a></th>
                    <th class="py-2 px-2">Enrollment No</th>
                    <th class="py-2 px-2">Mobile</th>
                    <th class="py-2 px-2">Chamber</th>
                    <th class="py-2 px-2"><a href="index.php?<?php echo getQueryString(['sort_by' => 'membership_status', 'sort_order' => ($sort_by === 'membership_status' && $sort_order === 'ASC') ? 'DESC' : 'ASC']); ?>" class="text-white text-decoration-none">Status <i class="bi bi-arrow-down-up font-size-xs ms-0.5"></i></a></th>
                    <th class="py-2 px-3 text-end" style="width: 18%;">Actions</th>
                </tr>
            </thead>
            <tbody class="small font-hindi text-dark-custom">
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <i class="bi bi-person-exclamation text-muted display-6 d-block mb-2"></i>
                            <span class="fw-bold d-block text-navy-custom mb-1">कोई सदस्य रिकॉर्ड नहीं मिला। (No Members Match.)</span>
                            <a href="index.php" class="btn btn-xs btn-outline-navy mt-2">फ़िल्टर साफ़ करें</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td class="py-2 px-3">
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center border" style="width: 32px; height: 32px; overflow:hidden; flex-shrink:0;">
                                    <?php if (!empty($m['photo']) && $m['photo'] !== 'default_advocate.png'): ?>
                                        <img src="../../uploads/photos/<?php echo e($m['photo']); ?>" alt="Photo" style="width:100%; height:100%; object-fit:cover;">
                                    <?php else: ?>
                                        <i class="bi bi-person text-muted"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-2 px-2 fw-bold text-navy-custom english-text"><?php echo e($m['full_name']); ?></td>
                            <td class="py-2 px-2 fw-semibold text-secondary-custom english-text"><?php echo e($m['membership_no']); ?></td>
                            <td class="py-2 px-2 english-text"><?php echo e($m['enrollment_no']); ?></td>
                            <td class="py-2 px-2 english-text"><?php echo e($m['mobile'] ?? 'N/A'); ?></td>
                            <td class="py-2 px-2 text-secondary-custom fw-semibold"><?php echo !empty($m['chamber_no']) ? 'Chamber ' . e($m['chamber_no']) : 'N/A'; ?></td>
                            <td class="py-2 px-2">
                                <?php 
                                $st = $m['membership_status'];
                                $badge_class = 'bg-secondary';
                                if ($st === 'active') $badge_class = 'bg-success';
                                elseif ($st === 'suspended') $badge_class = 'bg-danger';
                                elseif ($st === 'deceased') $badge_class = 'bg-dark text-white';
                                elseif ($st === 'pending') $badge_class = 'bg-warning text-dark';
                                ?>
                                <span class="badge <?php echo $badge_class; ?> font-size-xs px-2 py-1">
                                    <?php echo e($status_labels[$st] ?? $st); ?>
                                </span>
                            </td>
                            <td class="py-2 px-3 text-end">
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        प्रबंधित करें
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border border-light small font-hindi">
                                        <li><a class="dropdown-item py-1.5" href="view.php?id=<?php echo e($m['id']); ?>"><i class="fa-solid fa-eye text-navy-custom me-2"></i>विवरण देखें (View)</a></li>
                                        <?php if (hasPermission('members.manage')): ?>
                                            <li><a class="dropdown-item py-1.5" href="edit.php?id=<?php echo e($m['id']); ?>"><i class="fa-solid fa-pen-to-square text-secondary-custom me-2"></i>संशोधन (Edit)</a></li>
                                            <li><a class="dropdown-item py-1.5 text-danger" href="view.php?id=<?php echo e($m['id']); ?>#status"><i class="fa-solid fa-triangle-exclamation me-2"></i>स्थिति बदलें (Status)</a></li>
                                            <li><a class="dropdown-item py-1.5" href="documents.php?member_id=<?php echo e($m['id']); ?>"><i class="fa-solid fa-file-arrow-up me-2"></i>दस्तावेज (Docs)</a></li>
                                            <li><a class="dropdown-item py-1.5" href="view.php?id=<?php echo e($m['id']); ?>#account"><i class="fa-solid fa-user-lock me-2"></i>लॉगिन खाता (Account)</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Pagination Links -->
<?php if ($total_pages > 1): ?>
    <nav class="d-flex justify-content-center" aria-label="Page navigation">
        <ul class="pagination pagination-sm font-hindi">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="index.php?<?php echo getQueryString(['page' => $page - 1]); ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo; पिछला</span>
                </a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="index.php?<?php echo getQueryString(['page' => $i]); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="index.php?<?php echo getQueryString(['page' => $page + 1]); ?>" aria-label="Next">
                    <span aria-hidden="true">अगला &raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
