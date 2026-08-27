<?php
/**
 * Searchable Public Members Directory - District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'अधिवक्ता सदस्य सूची (Members Directory)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
require_once 'config/database.php';

// Fetch filters from GET request and sanitize
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$search_membership = isset($_GET['search_membership']) ? trim($_GET['search_membership']) : '';
$search_enrollment = isset($_GET['search_enrollment']) ? trim($_GET['search_enrollment']) : '';

$members = [];
$db = Database::getConnection();

if ($db) {
    try {
        // Enforce membership_status = 'active' AND is_public = 1 for public search
        $sql = "SELECT * FROM members WHERE membership_status = 'active' AND is_public = 1";
        $params = [];
        
        if ($search_name !== '') {
            $sql .= " AND full_name LIKE :name";
            $params[':name'] = '%' . $search_name . '%';
        }
        if ($search_membership !== '') {
            $sql .= " AND membership_no LIKE :membership";
            $params[':membership'] = '%' . $search_membership . '%';
        }
        if ($search_enrollment !== '') {
            $sql .= " AND enrollment_no LIKE :enrollment";
            $params[':enrollment'] = '%' . $search_enrollment . '%';
        }
        
        $sql .= " ORDER BY membership_no ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in public members fetch: " . $e->getMessage());
    }
}
?>

<!-- Banner -->
<div class="bg-navy-custom text-white p-4 rounded-3 mb-4 shadow-sm border-bottom border-gold-custom">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="h3 mb-1 font-hindi fw-bold text-gold-custom">अधिवक्ता सदस्य सूची (Members Directory)</h1>
            <p class="mb-0 text-light-custom english-text text-uppercase tracking-wider small">जिला अधिवक्ता संघ, बांदा के पंजीकृत सदस्य अधिवक्ताओं की सार्वजनिक सूची</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="badge bg-gold-custom text-navy-custom font-hindi px-3 py-2 fs-6">कुल सदस्य: <?php echo count($members); ?></span>
        </div>
    </div>
</div>

<!-- Search Panel -->
<div class="bg-white p-4 rounded-3 shadow-sm border border-light mb-4">
    <h5 class="text-navy-custom font-hindi fw-bold mb-3"><i class="bi bi-search text-gold-custom me-2"></i>खोजें (Search Members)</h5>
    <form method="GET" action="members.php" class="row g-3">
        <div class="col-md-4 col-12">
            <label for="search_name" class="form-label font-hindi small text-secondary">अधिवक्ता का नाम (Name)</label>
            <input type="text" class="form-control" id="search_name" name="search_name" value="<?php echo e($search_name); ?>" placeholder="e.g. Rajesh Kumar">
        </div>
        <div class="col-md-4 col-12">
            <label for="search_membership" class="form-label font-hindi small text-secondary">सदस्यता संख्या (Membership No.)</label>
            <input type="text" class="form-control" id="search_membership" name="search_membership" value="<?php echo e($search_membership); ?>" placeholder="e.g. DBA-001">
        </div>
        <div class="col-md-4 col-12">
            <label for="search_enrollment" class="form-label font-hindi small text-secondary">पंजीकरण संख्या (Enrollment No.)</label>
            <input type="text" class="form-control" id="search_enrollment" name="search_enrollment" value="<?php echo e($search_enrollment); ?>" placeholder="e.g. UP/1234/2005">
        </div>
        
        <div class="col-12 d-flex gap-2 justify-content-end mt-4">
            <a href="members.php" class="btn btn-outline-secondary font-hindi px-4">
                <i class="bi bi-x-circle me-1"></i>साफ़ करें
            </a>
            <button type="submit" class="btn btn-navy font-hindi px-4">
                <i class="bi bi-funnel-fill me-1 text-gold-custom"></i>खोजें / Search
            </button>
        </div>
    </form>
</div>

<!-- Safe Data Disclaimer -->
<div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4 font-hindi small">
    <i class="bi bi-shield-fill-exclamation text-danger me-1"></i>
    <strong>सुरक्षा चेतावनी:</strong> अधिवक्ताओं की गोपनीयता सुनिश्चित करने के लिए व्यक्तिगत पते, जन्म तिथि, हस्ताक्षर, आधार विवरण, ईमेल या निजी फोन नंबर सार्वजनिक रूप से प्रदर्शित नहीं किए गए हैं।
</div>

<!-- Directory Results Table -->
<div class="bg-white p-3 rounded-3 shadow-sm border border-light">
    <?php if (empty($members)): ?>
        <div class="text-center py-5">
            <i class="bi bi-person-x text-muted display-4 mb-3 d-block"></i>
            <h5 class="text-navy-custom font-hindi fw-bold">कोई परिणाम नहीं मिला (No Members Found)</h5>
            <p class="text-muted small">दर्ज की गई खोज श्रेणियों से मेल खाता कोई सक्रिय सदस्य रिकॉर्ड उपलब्ध नहीं है।</p>
        </div>
    <?php else: ?>
        <div class="table-responsive table-responsive-custom">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-dark text-uppercase bg-navy-custom text-white" style="font-size: 0.85rem;">
                    <tr>
                        <th class="py-3 px-4" style="width: 30%;">नाम (Advocate Name)</th>
                        <th class="py-3 px-3">सदस्यता संख्या (Membership No.)</th>
                        <th class="py-3 px-3">पंजीकरण संख्या (Enrollment No.)</th>
                        <th class="py-3 px-3 text-center">स्थिति (Status)</th>
                        <th class="py-3 px-3">कक्ष (Chamber)</th>
                        <th class="py-3 px-4 text-end" style="width: 15%;">विवरण (Action)</th>
                    </tr>
                </thead>
                <tbody class="small font-hindi">
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td class="py-3 px-4 fw-bold text-navy-custom">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center border" style="width: 35px; height: 35px; overflow:hidden;">
                                        <?php if (!empty($member['photo']) && $member['photo'] !== 'default_advocate.png'): ?>
                                            <img src="uploads/photos/<?php echo e($member['photo']); ?>" alt="Photo" style="width:100%; height:100%; object-fit:cover;">
                                        <?php else: ?>
                                            <i class="bi bi-person text-muted"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="english-text"><?php echo e($member['full_name']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-3 fw-semibold text-secondary-custom english-text"><?php echo e($member['membership_no']); ?></td>
                            <td class="py-3 px-3 english-text"><?php echo e($member['enrollment_no']); ?></td>
                            <td class="py-3 px-3 text-center">
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1">सक्रिय (Active)</span>
                            </td>
                            <td class="py-3 px-3 text-secondary-custom fw-semibold font-hindi"><?php echo !empty($member['chamber_no']) ? 'Chamber ' . e($member['chamber_no']) : 'आवंटित नहीं'; ?></td>
                            <td class="py-3 px-4 text-end">
                                <a href="member-profile.php?id=<?php echo e($member['id']); ?>" class="btn btn-xs btn-outline-navy fw-semibold">
                                    प्रोफ़ाइल (Profile) <i class="bi bi-chevron-right small"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php 
require_once 'includes/footer.php';
?>
