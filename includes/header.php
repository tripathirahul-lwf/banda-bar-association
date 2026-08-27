<?php
/**
 * Global header file for District Bar Association, Banda
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden.");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Prepare dynamic page title
$association_name_hi = getSetting('association_name_hi', 'जिला अधिवक्ता संघ, बांदा');
$association_name_en = getSetting('association_name_en', 'District Bar Association, Banda');

// Maintenance Mode intercept (Requirement 50)
$is_maintenance = getSetting('maintenance_mode', false);
$current_script = basename($_SERVER['SCRIPT_NAME']);
$role = currentRole();

if ($is_maintenance && $current_script !== 'login.php' && !in_array($role, ['admin', 'president', 'mahasachiv'])) {
    ?>
    <!DOCTYPE html>
    <html lang="hi" class="h-100">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>रख-रखाव जारी | Website Under Maintenance</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <style>
            body { background-color: #0b2545; color: #fff; text-align: center; }
            .container { max-width: 600px; margin-top: 10%; }
            .logo-icon { font-size: 5rem; color: #eeb902; }
            .text-navy-custom { color: #0b2545; }
        </style>
    </head>
    <body class="d-flex align-items-center justify-content-center h-100">
        <div class="container bg-white text-dark p-5 rounded shadow-lg border border-warning border-3">
            <i class="bi bi-gear-fill logo-icon d-block mb-4"></i>
            <h3 class="fw-bold text-navy-custom mb-3">वेबसाइट का रख-रखाव जारी है</h3>
            <h5 class="text-danger mb-4">Website maintenance is currently in progress.</h5>
            <p class="text-muted mb-4">अधिवक्ता पोर्टल की सुरक्षा और सुगमता को बेहतर बनाने के लिए तकनीकी रख-रखाव कार्य किया जा रहा है। असुविधा के लिए खेद है।</p>
            <div class="border-top pt-3">
                <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-outline-dark">विभागीय लॉगिन (Admin Login)</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$fullTitle = (isset($pageTitle) ? $pageTitle . " | " : "") . $association_name_hi . " - " . $association_name_en;
?>
<!DOCTYPE html>
<html lang="hi" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="जिला अधिवक्ता संघ, बांदा - स्थापना वर्ष 1937। District Bar Association, Banda official web portal.">
    <title><?php echo sanitize($fullTitle); ?></title>
    
    <!-- Google Fonts: Poppins, Noto Sans Devanagari & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- FontAwesome 6 CDN (Requirement 54 - High quality icons replacement) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
</head>
<body class="d-flex flex-column h-100 bg-light-custom text-dark-custom">
