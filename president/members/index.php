<?php
/**
 * President - View Only Members Directory
 * District Bar Association, Banda
 */

$pageTitle = 'अधिवक्ता सदस्य सूची (Members Directory)';

// Require custom dashboard headers (which handles requireLogin)
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce President Role
requireRole('president');

// Search and filter parameters
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$search_membership = isset($_GET['search_membership']) ? trim($_GET['search_membership']) : '';
$search_status = isset($_GET['search_status']) ? trim($_GET['search_status']) : '';

$members = [];
$db = Database::getConnection();

if ($db) {
    try {
        // President can view all members regardless of public toggle
        $sql = "SELECT * FROM members WHERE 1=1";
        $params = [];
        
        if ($search_name !== '') {
            $sql .= " AND full_name LIKE :name";
            $params[':name'] = '%' . $search_name . '%';
        }
        if ($search_membership !== '') {
            $sql .= " AND membership_no LIKE :membership";
            $params[':membership'] = '%' . $search_membership . '%';
        }
        if ($search_status !== '') {
            $sql .= " AND membership_status = :status";
            $params[':status'] = $search_status;
        }
        
        $sql .= " ORDER BY membership_no ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("President members query failure: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
    <h4 class="text-navy-custom font-hindi fw-bold mb-0">सदस्य डायरेक्टरी निरीक्षण (Member Records Review)</h4>
    <span class="badge bg-warning text-navy-custom px-3 py-1 font-hindi fw-bold">अध्यक्ष पटल (Read-Only)</span>
</div>

<!-- Search Panel -->
<div class="bg-light-custom p-3 rounded border mb-4 font-hindi small">
    <form method="GET" action="index.php" class="row g-3">
        <div class="col-md-4">
            <label for="search_name" class="form-label text-secondary">अधिवक्ता का नाम (Name)</label>
            <input type="text" class="form-control form-control-sm" id="search_name" name="search_name" value="<?php echo e($search_name); ?>" placeholder="खोजें...">
        </div>
        <div class="col-md-4">
            <label for="search_membership" class="form-label text-secondary">सदस्यता संख्या (Membership No)</label>
            <input type="text" class="form-control form-control-sm" id="search_membership" name="search_membership" value="<?php echo e($search_membership); ?>" placeholder="e.g. DBA-001">
        </div>
        <div class="col-md-4">
            <label for="search_status" class="form-label text-secondary">सदस्यता स्थिति (Status)</label>
            <select class="form-select form-select-sm" id="search_status" name="search_status">
                <option value="">सभी (All)</option>
                <option value="active" <?php echo ($search_status === 'active') ? 'selected' : ''; ?>>सक्रिय (Active)</option>
                <option value="inactive" <?php echo ($search_status === 'inactive') ? 'selected' : ''; ?>>निष्क्रिय (Inactive)</option>
                <option value="suspended" <?php echo ($search_status === 'suspended') ? 'selected' : ''; ?>>निलंबित (Suspended)</option>
                <option value="deceased" <?php echo ($search_status === 'deceased') ? 'selected' : ''; ?>>दिवंगत (Deceased)</option>
            </select>
        </div>
        <div class="col-12 text-end mt-3">
            <a href="index.php" class="btn btn-xs btn-outline-secondary px-3 py-1.5">साफ़ करें</a>
            <button type="submit" class="btn btn-xs btn-navy px-3 py-1.5 ms-1"><i class="bi bi-search me-1"></i>खोजें / Filter</button>
        </div>
    </form>
</div>

<!-- Results Table -->
<div class="table-responsive table-responsive-custom">
    <table class="table table-hover table-striped mb-0 align-middle">
        <thead class="table-dark bg-navy-custom text-white" style="font-size: 0.85rem;">
            <tr>
                <th class="py-2 px-3">Advocate Name</th>
                <th class="py-2 px-2">Membership No.</th>
                <th class="py-2 px-2">Enrollment No.</th>
                <th class="py-2 px-2">Mobile</th>
                <th class="py-2 px-2 text-center">Status</th>
                <th class="py-2 px-3 text-end" style="width: 12%;">Action</th>
            </tr>
        </thead>
        <tbody class="small font-hindi">
            <?php if (empty($members)): ?>
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">कोई सदस्य रिकॉर्ड नहीं मिला।</td>
                </tr>
            <?php else: ?>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td class="py-2 px-3 fw-bold text-navy-custom">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center border" style="width: 32px; height: 32px; overflow:hidden;">
                                    <?php if (!empty($m['photo']) && $m['photo'] !== 'default_advocate.png'): ?>
                                        <img src="../../uploads/photos/<?php echo e($m['photo']); ?>" alt="Photo" style="width:100%; height:100%; object-fit:cover;">
                                    <?php else: ?>
                                        <i class="bi bi-person text-muted"></i>
                                    <?php endif; ?>
                                </div>
                                <span class="english-text"><?php echo e($m['full_name']); ?></span>
                            </div>
                        </td>
                        <td class="py-2 px-2 english-text fw-semibold text-secondary-custom"><?php echo e($m['membership_no']); ?></td>
                        <td class="py-2 px-2 english-text"><?php echo e($m['enrollment_no']); ?></td>
                        <td class="py-2 px-2 english-text"><?php echo e($m['mobile'] ?? 'N/A'); ?></td>
                        <td class="py-2 px-2 text-center">
                            <?php 
                            $status = $m['membership_status'];
                            if ($status === 'active') echo '<span class="badge bg-success font-size-xs px-2 py-0.5">सक्रिय</span>';
                            elseif ($status === 'inactive') echo '<span class="badge bg-secondary font-size-xs px-2 py-0.5">निष्क्रिय</span>';
                            elseif ($status === 'suspended') echo '<span class="badge bg-danger font-size-xs px-2 py-0.5">निलंबित</span>';
                            elseif ($status === 'deceased') echo '<span class="badge bg-dark text-white font-size-xs px-2 py-0.5">दिवंगत</span>';
                            else echo '<span class="badge bg-warning text-dark font-size-xs px-2 py-0.5">' . e($status) . '</span>';
                            ?>
                        </td>
                        <td class="py-2 px-3 text-end">
                            <a href="view.php?id=<?php echo e($m['id']); ?>" class="btn btn-xs btn-outline-navy fw-semibold py-1">
                                देखें (Review) <i class="bi bi-chevron-right small"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
