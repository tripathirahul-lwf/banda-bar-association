<?php
/**
 * Secure Document Download proxy
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce login and access permissions
requireRole(['admin', 'mahasachiv']);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("Error: Invalid ID.");
}

$db = Database::getConnection();
if ($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM member_documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            $filepath = __DIR__ . '/../../uploads/documents/' . $doc['file_path'];
            
            if (file_exists($filepath)) {
                // Determine content type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $content_type = finfo_file($finfo, $filepath);
                finfo_close($finfo);
                
                // Clear buffers
                if (ob_get_level()) ob_end_clean();
                
                header('Content-Description: File Transfer');
                header('Content-Type: ' . $content_type);
                header('Content-Disposition: attachment; filename="' . basename($doc['document_name'] . '.' . pathinfo($filepath, PATHINFO_EXTENSION)) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filepath));
                
                readfile($filepath);
                exit();
            } else {
                die("Error: File not found on the server storage.");
            }
        } else {
            die("Error: Document not registered in database.");
        }
    } catch (PDOException $e) {
        error_log("Failed downloading document proxy: " . $e->getMessage());
        die("Error: System failure.");
    }
}
