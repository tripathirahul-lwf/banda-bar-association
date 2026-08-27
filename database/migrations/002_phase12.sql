-- Phase 12 Database Updates (Settings, Audit Logs, Notifications)

-- 1. Alter contact_messages if status doesn't exist
-- Note: In MySQL, we can check if it exists or run it. We'll wrap in helper or run directly if migration hasn't executed.
ALTER TABLE `contact_messages` ADD COLUMN `status` ENUM('new', 'read', 'resolved', 'archived') DEFAULT 'new';

-- 2. Create system_settings table
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_group` VARCHAR(50) NOT NULL,
    `setting_key` VARCHAR(50) NOT NULL,
    `setting_value` TEXT NULL,
    `value_type` VARCHAR(20) DEFAULT 'string',
    `is_public` TINYINT(1) DEFAULT 1,
    `updated_by` INT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_group_key` (`setting_group`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create audit_logs table
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `role` VARCHAR(50) NULL,
    `module` VARCHAR(50) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `record_type` VARCHAR(50) NULL,
    `record_id` INT NULL,
    `old_values` TEXT NULL,
    `new_values` TEXT NULL,
    `remarks` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `member_id` INT NULL,
    `role_target` VARCHAR(50) NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Seed default system settings
INSERT INTO `system_settings` (`setting_group`, `setting_key`, `setting_value`, `value_type`, `is_public`) VALUES
('general', 'association_name_en', 'District Bar Association, Banda', 'string', 1),
('general', 'association_name_hi', 'जिला अधिवक्ता संघ, बांदा', 'string', 1),
('general', 'established_year', '1937', 'string', 1),
('general', 'address', 'Civil Court Compound, Banda, Uttar Pradesh', 'string', 1),
('general', 'district', 'Banda', 'string', 1),
('general', 'state', 'Uttar Pradesh', 'string', 1),
('general', 'pincode', '210001', 'string', 1),
('general', 'contact_number', '05192-220000', 'string', 1),
('general', 'alternate_number', '+91-9999999999', 'string', 1),
('general', 'official_email', 'info@dbabanda.in', 'string', 1),
('general', 'office_timing', '10:00 AM - 05:00 PM', 'string', 1),
('general', 'website_url', 'http://localhost/banda-bar', 'string', 1),
('general', 'footer_text', '© 2026 जिला अधिवक्ता संघ, बांदा. सर्वाधिकार सुरक्षित।', 'string', 1),
('general', 'maintenance_mode', '0', 'boolean', 1),
('branding', 'logo', 'assets/images/logo.png', 'string', 1),
('branding', 'favicon', 'favicon.ico', 'string', 1),
('branding', 'emblem', '', 'string', 1),
('branding', 'primary_color', '#0B2545', 'string', 1),
('branding', 'secondary_color', '#134074', 'string', 1),
('branding', 'accent_color', '#EEB902', 'string', 1),
('homepage', 'hero_heading', 'जिला अधिवक्ता संघ, बांदा में आपका स्वागत है', 'string', 1),
('homepage', 'hero_description', '1937 से न्याय, सत्य और अधिवक्ता एकता का प्रतीक।', 'string', 1),
('homepage', 'primary_cta', 'सदस्यता खोजें', 'string', 1),
('homepage', 'secondary_cta', 'महत्वपूर्ण सूचनाएं', 'string', 1),
('homepage', 'about_preview', 'जिला अधिवक्ता संघ बांदा एक ऐतिहासिक बार एसोसिएशन है जो अधिवक्ताओं के कल्याण के लिए काम करती है।', 'string', 1),
('homepage', 'president_message_visibility', '1', 'boolean', 1),
('homepage', 'office_bearers_visibility', '1', 'boolean', 1),
('homepage', 'important_notices_visibility', '1', 'boolean', 1),
('homepage', 'election_section_visibility', '1', 'boolean', 1),
('homepage', 'association_statistics_visibility', '1', 'boolean', 1),
('content', 'about_association', 'जिला अधिवक्ता संघ, बांदा उत्तर प्रदेश का एक प्रमुख विधिक संघ है।', 'text', 1),
('content', 'history', 'जिला अधिवक्ता संघ बांदा की स्थापना वर्ष 1937 में हुई थी।', 'text', 1),
('content', 'mission', 'हमारा mission अधिवक्ताओं की एकता और विधिक सहायता प्रदान करना है।', 'text', 1)
ON DUPLICATE KEY UPDATE `value_type` = VALUES(`value_type`), `is_public` = VALUES(`is_public`);
