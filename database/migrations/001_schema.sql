-- Database Schema for District Bar Association, Banda
-- Estd. 1937
-- Phase 8 Advocate Bar Fee Management

SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `banda_bar` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `banda_bar`;

-- Drop tables in order of dependencies if they exist
DROP TABLE IF EXISTS `election_history`;
DROP TABLE IF EXISTS `election_results`;
DROP TABLE IF EXISTS `election_documents`;
DROP TABLE IF EXISTS `election_candidates`;
DROP TABLE IF EXISTS `election_voters`;
DROP TABLE IF EXISTS `election_posts`;
DROP TABLE IF EXISTS `elections`;
DROP TABLE IF EXISTS `chamber_audit_logs`;
DROP TABLE IF EXISTS `chamber_vacate_requests`;
DROP TABLE IF EXISTS `chamber_rent_payment_allocations`;
DROP TABLE IF EXISTS `chamber_rent_payments`;
DROP TABLE IF EXISTS `chamber_rent_dues`;
DROP TABLE IF EXISTS `chamber_security_deposits`;
DROP TABLE IF EXISTS `chamber_allotments`;
DROP TABLE IF EXISTS `chamber_applications`;
DROP TABLE IF EXISTS `chambers`;
DROP TABLE IF EXISTS `bar_fee_payment_allocations`;
DROP TABLE IF EXISTS `bar_fee_payment_history`;
DROP TABLE IF EXISTS `bar_fee_due_history`;
DROP TABLE IF EXISTS `bar_fee_payments`;
DROP TABLE IF EXISTS `member_fee_dues`;
DROP TABLE IF EXISTS `bar_fee_types`;
DROP TABLE IF EXISTS `association_fund_history`;
DROP TABLE IF EXISTS `association_fund_transactions`;
DROP TABLE IF EXISTS `association_fund_categories`;
DROP TABLE IF EXISTS `association_fund_accounts`;
DROP TABLE IF EXISTS `association_financial_documents`;
DROP TABLE IF EXISTS `financial_years`;
DROP TABLE IF EXISTS `notice_views`;
DROP TABLE IF EXISTS `condolence_notices`;
DROP TABLE IF EXISTS `notice_history`;
DROP TABLE IF EXISTS `notices`;
DROP TABLE IF EXISTS `wakalatnama_history`;
DROP TABLE IF EXISTS `wakalatnama_downloads`;
DROP TABLE IF EXISTS `wakalatnamas`;
DROP TABLE IF EXISTS `id_card_history`;
DROP TABLE IF EXISTS `id_card_applications`;
DROP TABLE IF EXISTS `id_cards`;
DROP TABLE IF EXISTS `login_logs`;
DROP TABLE IF EXISTS `office_bearer_history`;
DROP TABLE IF EXISTS `office_bearers`;
DROP TABLE IF EXISTS `office_bearer_terms`;
DROP TABLE IF EXISTS `office_bearer_positions`;
DROP TABLE IF EXISTS `member_history`;
DROP TABLE IF EXISTS `member_documents`;
DROP TABLE IF EXISTS `member_profile_update_requests`;
DROP TABLE IF EXISTS `members`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `contact_messages`;

-- 1. Users Table (Core Auth)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NULL,
    `username` VARCHAR(50) UNIQUE NULL,
    `email` VARCHAR(100) UNIQUE NULL,
    `mobile` VARCHAR(20) UNIQUE NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'president', 'mahasachiv', 'member') NOT NULL,
    `status` ENUM('active', 'inactive', 'suspended', 'locked') DEFAULT 'active',
    `last_login_at` TIMESTAMP NULL,
    `last_login_ip` VARCHAR(45) NULL,
    `failed_login_attempts` INT DEFAULT 0,
    `locked_until` TIMESTAMP NULL,
    `password_changed_at` TIMESTAMP NULL,
    `must_change_password` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Members Table (Advocate Member Master)
CREATE TABLE IF NOT EXISTS `members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `membership_no` VARCHAR(50) UNIQUE NOT NULL,
    `enrollment_no` VARCHAR(100) UNIQUE NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `father_or_husband_name` VARCHAR(100) NULL,
    `gender` ENUM('Male', 'Female', 'Other') NULL,
    `date_of_birth` DATE NULL,
    `mobile` VARCHAR(20) UNIQUE NULL,
    `alternate_mobile` VARCHAR(20) NULL,
    `email` VARCHAR(100) UNIQUE NULL,
    `permanent_address` TEXT NULL,
    `local_address` TEXT NULL,
    `district` VARCHAR(50) NULL,
    `state` VARCHAR(50) NULL,
    `pincode` VARCHAR(10) NULL,
    `photo` VARCHAR(255) DEFAULT 'default_advocate.png',
    `signature` VARCHAR(255) NULL,
    `enrollment_date` DATE NULL,
    `member_since` DATE NOT NULL,
    `membership_category` ENUM('Regular Member', 'Life Member', 'Senior Member', 'Honorary Member', 'Other') DEFAULT 'Regular Member',
    `membership_status` ENUM('active', 'inactive', 'suspended', 'expired', 'deceased', 'pending', 'rejected') DEFAULT 'active',
    `chamber_no` VARCHAR(50) NULL,
    `practice_area` VARCHAR(255) NULL,
    `blood_group` VARCHAR(10) NULL,
    `emergency_contact_name` VARCHAR(100) NULL,
    `emergency_contact_mobile` VARCHAR(20) NULL,
    `is_office_bearer` TINYINT(1) DEFAULT 0,
    `is_public` TINYINT(1) DEFAULT 1,
    `show_mobile_publicly` TINYINT(1) DEFAULT 0,
    `show_email_publicly` TINYINT(1) DEFAULT 0,
    `status_reason` TEXT NULL,
    `created_by` INT NULL,
    `updated_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_member_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Complete circular dependency for user table pointing to member
ALTER TABLE `users` ADD CONSTRAINT `fk_user_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL;

-- Create Indexes on Member table
CREATE INDEX `idx_members_full_name` ON `members` (`full_name`);
CREATE INDEX `idx_members_membership_status` ON `members` (`membership_status`);
CREATE INDEX `idx_members_mobile` ON `members` (`mobile`);
CREATE INDEX `idx_members_member_since` ON `members` (`member_since`);

-- 3. Office Bearer Positions Table
CREATE TABLE IF NOT EXISTS `office_bearer_positions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `position_name` VARCHAR(100) UNIQUE NOT NULL,
    `position_name_hindi` VARCHAR(100) NULL,
    `code` VARCHAR(50) UNIQUE NOT NULL,
    `display_order` INT DEFAULT 0,
    `max_holders` INT DEFAULT 1,
    `is_executive` TINYINT(1) DEFAULT 1,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Office Bearer Terms Table
CREATE TABLE IF NOT EXISTS `office_bearer_terms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(100) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NULL,
    `status` ENUM('draft', 'active', 'completed', 'archived') DEFAULT 'draft',
    `description` TEXT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_term_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Office Bearers Table (New)
CREATE TABLE IF NOT EXISTS `office_bearers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `term_id` INT NOT NULL,
    `position_id` INT NOT NULL,
    `member_id` INT NOT NULL,
    `display_name_snapshot` VARCHAR(100) NOT NULL,
    `photo_snapshot` VARCHAR(255) NULL,
    `membership_no_snapshot` VARCHAR(50) NULL,
    `enrollment_no_snapshot` VARCHAR(100) NULL,
    `designation_override` VARCHAR(100) NULL,
    `message` TEXT NULL,
    `display_order` INT DEFAULT 0,
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `status` ENUM('active', 'inactive', 'resigned', 'completed') DEFAULT 'active',
    `appointed_by` INT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ob_term` FOREIGN KEY (`term_id`) REFERENCES `office_bearer_terms` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ob_position` FOREIGN KEY (`position_id`) REFERENCES `office_bearer_positions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ob_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ob_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Office Bearer History / Audit Logs
CREATE TABLE IF NOT EXISTS `office_bearer_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `office_bearer_id` INT NOT NULL,
    `action` VARCHAR(50) NOT NULL, -- 'Assigned', 'Position Changed', 'Term Started', 'Resigned', 'Tenure Ended', 'Replaced', 'Record Updated'
    `old_position_id` INT NULL,
    `new_position_id` INT NULL,
    `old_status` VARCHAR(20) NULL,
    `new_status` VARCHAR(20) NULL,
    `remarks` TEXT NULL,
    `performed_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_obh_bearer` FOREIGN KEY (`office_bearer_id`) REFERENCES `office_bearers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_obh_performer` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Member History Table (Audit Log)
CREATE TABLE IF NOT EXISTS `member_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `field_name` VARCHAR(50) NULL,
    `old_value` TEXT NULL,
    `new_value` TEXT NULL,
    `remarks` TEXT NULL,
    `performed_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_history_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Member Documents Table
CREATE TABLE IF NOT EXISTS `member_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NOT NULL,
    `document_type` ENUM('Enrollment Certificate', 'Certificate of Practice', 'Membership Form', 'Photo ID', 'Address Proof', 'Other') NOT NULL,
    `document_name` VARCHAR(150) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `verification_status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    `remarks` TEXT NULL,
    `uploaded_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_docs_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Member Profile Update Requests Table
CREATE TABLE IF NOT EXISTS `member_profile_update_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NOT NULL,
    `requested_changes` JSON NOT NULL,
    `remarks` TEXT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `reviewed_by` INT NULL,
    `reviewed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_req_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Notices Table
CREATE TABLE IF NOT EXISTS `notices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `notice_no` VARCHAR(50) UNIQUE NULL,
    `title` VARCHAR(255) NOT NULL,
    `title_hindi` VARCHAR(255) NULL,
    `slug` VARCHAR(255) UNIQUE NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `short_description` TEXT NULL,
    `description` TEXT NOT NULL,
    `priority` ENUM('normal', 'important', 'urgent') DEFAULT 'normal',
    `visibility` ENUM('public', 'members_only') DEFAULT 'public',
    `status` ENUM('draft', 'pending_approval', 'approved', 'scheduled', 'published', 'rejected', 'expired', 'archived') DEFAULT 'draft',
    `publish_at` DATETIME NULL,
    `expire_at` DATETIME NULL,
    `attachment_path` VARCHAR(255) NULL,
    `attachment_name` VARCHAR(255) NULL,
    `attachment_type` VARCHAR(50) NULL,
    `featured_image` VARCHAR(255) NULL,
    `created_by` INT NOT NULL,
    `submitted_by` INT NULL,
    `approved_by` INT NULL,
    `approved_at` DATETIME NULL,
    `rejection_reason` TEXT NULL,
    `published_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notice_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_notice_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_notices_status` ON `notices` (`status`);
CREATE INDEX `idx_notices_category` ON `notices` (`category`);
CREATE INDEX `idx_notices_publish_at` ON `notices` (`publish_at`);
CREATE INDEX `idx_notices_created_at` ON `notices` (`created_at`);

-- 8. Condolence Notices Table
CREATE TABLE IF NOT EXISTS `condolence_notices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `notice_id` INT NOT NULL,
    `member_id` INT NULL,
    `advocate_name` VARCHAR(100) NOT NULL,
    `advocate_photo` VARCHAR(255) NULL,
    `membership_no` VARCHAR(50) NULL,
    `enrollment_no` VARCHAR(100) NULL,
    `date_of_death` DATE NULL,
    `condolence_message` TEXT NOT NULL,
    `prayer_meeting_details` TEXT NULL,
    `family_message` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_condolence_notice` FOREIGN KEY (`notice_id`) REFERENCES `notices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_condolence_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Notice Views Table
CREATE TABLE IF NOT EXISTS `notice_views` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `notice_id` INT NOT NULL,
    `member_id` INT NULL,
    `session_hash` VARCHAR(64) NULL,
    `viewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_views_notice` FOREIGN KEY (`notice_id`) REFERENCES `notices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_views_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Notice History Table
CREATE TABLE IF NOT EXISTS `notice_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `notice_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `old_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NULL,
    `remarks` TEXT NULL,
    `performed_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_history_notice` FOREIGN KEY (`notice_id`) REFERENCES `notices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_history_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Contact Messages Table
CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Login Logs Table
CREATE TABLE IF NOT EXISTS `login_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `identifier` VARCHAR(100) NOT NULL,
    `role_attempted` VARCHAR(20) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NOT NULL,
    `status` ENUM('success', 'failed', 'locked') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. ID Cards Table
CREATE TABLE IF NOT EXISTS `id_cards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NOT NULL,
    `card_number` VARCHAR(50) UNIQUE NOT NULL,
    `card_type` ENUM('digital', 'physical', 'both') DEFAULT 'digital',
    `valid_from` DATE NOT NULL,
    `valid_until` DATE NOT NULL,
    `status` ENUM('draft', 'requested', 'under_review', 'approved', 'rejected', 'generated', 'printed', 'ready', 'issued', 'expired', 'cancelled') DEFAULT 'requested',
    `qr_token` VARCHAR(100) UNIQUE NOT NULL,
    `photo_path` VARCHAR(255) NULL,
    `signature_path` VARCHAR(255) NULL,
    `application_type` ENUM('new', 'renewal', 'duplicate', 'lost', 'replacement') DEFAULT 'new',
    `remarks` TEXT NULL,
    `requested_by` INT NOT NULL,
    `approved_by` INT NULL,
    `approved_at` TIMESTAMP NULL,
    `issued_by` INT NULL,
    `issued_at` TIMESTAMP NULL,
    `printed_at` TIMESTAMP NULL,
    `cancelled_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_id_cards_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. ID Card Applications Table
CREATE TABLE IF NOT EXISTS `id_card_applications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NOT NULL,
    `existing_card_id` INT NULL,
    `application_type` ENUM('new', 'renewal', 'duplicate', 'lost', 'replacement') DEFAULT 'new',
    `reason` TEXT NULL,
    `photo_path` VARCHAR(255) NULL,
    `signature_path` VARCHAR(255) NULL,
    `status` ENUM('submitted', 'under_review', 'clarification_required', 'approved', 'rejected', 'generated') DEFAULT 'submitted',
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `reviewed_by` INT NULL,
    `reviewed_at` TIMESTAMP NULL,
    `approval_remarks` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_id_app_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_id_app_card` FOREIGN KEY (`existing_card_id`) REFERENCES `id_cards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. ID Card History Table
CREATE TABLE IF NOT EXISTS `id_card_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_card_id` INT NOT NULL,
    `member_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `old_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NULL,
    `remarks` TEXT NULL,
    `performed_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_id_hist_card` FOREIGN KEY (`id_card_id`) REFERENCES `id_cards` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_id_hist_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Wakalatnamas Table
CREATE TABLE IF NOT EXISTS `wakalatnamas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `title_hindi` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `category` VARCHAR(50) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `original_file_name` VARCHAR(255) NOT NULL,
    `file_type` VARCHAR(20) DEFAULT 'pdf',
    `file_size` INT NOT NULL,
    `version` VARCHAR(20) NOT NULL DEFAULT '1.0',
    `effective_from` DATE NOT NULL,
    `effective_until` DATE NULL,
    `status` ENUM('draft', 'pending_approval', 'active', 'inactive', 'archived') DEFAULT 'draft',
    `members_only` TINYINT(1) DEFAULT 1,
    `uploaded_by` INT NOT NULL,
    `approved_by` INT NULL,
    `approved_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_waka_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_waka_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Wakalatnama Downloads Table
CREATE TABLE IF NOT EXISTS `wakalatnama_downloads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `wakalatnama_id` INT NOT NULL,
    `member_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `downloaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `download_status` ENUM('success', 'failed') DEFAULT 'success',
    `remarks` TEXT NULL,
    CONSTRAINT `fk_down_waka` FOREIGN KEY (`wakalatnama_id`) REFERENCES `wakalatnamas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_down_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_down_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Wakalatnama History Table
CREATE TABLE IF NOT EXISTS `wakalatnama_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `wakalatnama_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `old_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NULL,
    `remarks` TEXT NULL,
    `performed_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_hist_waka` FOREIGN KEY (`wakalatnama_id`) REFERENCES `wakalatnamas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_hist_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Financial Years Table
CREATE TABLE IF NOT EXISTS `financial_years` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) UNIQUE NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `status` ENUM('active', 'closed', 'future') DEFAULT 'future',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Association Fund Accounts Table
CREATE TABLE IF NOT EXISTS `association_fund_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `financial_year_id` INT NOT NULL,
    `opening_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `opening_balance_date` DATE NOT NULL,
    `opening_balance_notes` TEXT NULL,
    `status` ENUM('open', 'closed') DEFAULT 'open',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_fund_acc_fy` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fund_acc_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. Association Fund Categories Table
CREATE TABLE IF NOT EXISTS `association_fund_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. Fund Transactions Table
CREATE TABLE IF NOT EXISTS `association_fund_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `financial_year_id` INT NOT NULL,
    `transaction_no` VARCHAR(50) UNIQUE NOT NULL,
    `transaction_date` DATE NOT NULL,
    `transaction_type` ENUM('income', 'expense') NOT NULL,
    `category_id` INT NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `payment_mode` VARCHAR(50) NOT NULL,
    `reference_no` VARCHAR(100) NULL,
    `party_name` VARCHAR(150) NULL,
    `description` TEXT NOT NULL,
    `voucher_no` VARCHAR(50) NULL,
    `receipt_no` VARCHAR(50) NULL,
    `attachment_path` VARCHAR(255) NULL,
    `status` ENUM('draft', 'pending_approval', 'approved', 'cancelled', 'rejected') DEFAULT 'draft',
    `created_by` INT NOT NULL,
    `approved_by` INT NULL,
    `approved_at` DATETIME NULL,
    `cancelled_by` INT NULL,
    `cancelled_at` DATETIME NULL,
    `cancellation_reason` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tx_fy` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_tx_cat` FOREIGN KEY (`category_id`) REFERENCES `association_fund_categories` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_tx_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_tx_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tx_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. Association Fund History Table
CREATE TABLE IF NOT EXISTS `association_fund_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT NULL,
    `financial_year_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `old_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NULL,
    `remarks` TEXT NULL,
    `performed_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_hist_tx` FOREIGN KEY (`transaction_id`) REFERENCES `association_fund_transactions` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_hist_fy` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_hist_perf` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 24. Association Financial Documents Table
CREATE TABLE IF NOT EXISTS `association_financial_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `financial_year_id` INT NOT NULL,
    `document_type` ENUM('Annual Account', 'Income & Expenditure Statement', 'Balance Summary', 'Audit Report', 'Other') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `visibility` ENUM('public', 'members_only', 'private') DEFAULT 'public',
    `status` ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    `uploaded_by` INT NOT NULL,
    `approved_by` INT NULL,
    `published_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_doc_fy` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_doc_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_doc_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 25. Bar Fee Types Table (New for Phase 8)
CREATE TABLE IF NOT EXISTS `bar_fee_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) UNIQUE NOT NULL,
    `description` TEXT NULL,
    `amount` DECIMAL(12,2) NULL,
    `frequency` ENUM('one_time', 'monthly', 'quarterly', 'half_yearly', 'yearly', 'custom') DEFAULT 'yearly',
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_fee_type_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 26. Member Fee Dues Table (New for Phase 8)
CREATE TABLE IF NOT EXISTS `member_fee_dues` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NOT NULL,
    `fee_type_id` INT NOT NULL,
    `financial_year` VARCHAR(20) NULL,
    `period_label` VARCHAR(50) NULL,
    `due_date` DATE NOT NULL,
    `original_amount` DECIMAL(12,2) NOT NULL,
    `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
    `penalty_amount` DECIMAL(12,2) DEFAULT 0.00,
    `payable_amount` DECIMAL(12,2) NOT NULL,
    `paid_amount` DECIMAL(12,2) DEFAULT 0.00,
    `outstanding_amount` DECIMAL(12,2) NOT NULL,
    `status` ENUM('pending', 'partially_paid', 'paid', 'overdue', 'waived', 'cancelled') DEFAULT 'pending',
    `remarks` TEXT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_dues_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dues_type` FOREIGN KEY (`fee_type_id`) REFERENCES `bar_fee_types` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_dues_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_dues_status` ON `member_fee_dues` (`status`);
CREATE INDEX `idx_dues_financial_year` ON `member_fee_dues` (`financial_year`);

-- 27. Fee Payments Table (New for Phase 8)
CREATE TABLE IF NOT EXISTS `bar_fee_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_no` VARCHAR(50) UNIQUE NOT NULL,
    `member_id` INT NOT NULL,
    `payment_date` DATE NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `payment_mode` VARCHAR(50) NOT NULL,
    `transaction_reference` VARCHAR(100) NULL,
    `receipt_no` VARCHAR(50) UNIQUE NOT NULL,
    `status` ENUM('pending', 'confirmed', 'cancelled', 'refunded') DEFAULT 'confirmed',
    `remarks` TEXT NULL,
    `collected_by` INT NULL,
    `created_by` INT NOT NULL,
    `approved_by` INT NULL,
    `approved_at` DATETIME NULL,
    `cancelled_by` INT NULL,
    `cancelled_at` DATETIME NULL,
    `cancellation_reason` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pay_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pay_collector` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pay_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pay_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pay_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 28. Payment Allocations Table (New for Phase 8)
CREATE TABLE IF NOT EXISTS `bar_fee_payment_allocations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT NOT NULL,
    `due_id` INT NOT NULL,
    `allocated_amount` DECIMAL(12,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_alloc_pay` FOREIGN KEY (`payment_id`) REFERENCES `bar_fee_payments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_alloc_due` FOREIGN KEY (`due_id`) REFERENCES `member_fee_dues` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 29. Payment History Table (New for Phase 8)
CREATE TABLE IF NOT EXISTS `bar_fee_payment_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `old_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NULL,
    `remarks` TEXT NULL,
    `performed_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_p_hist_pay` FOREIGN KEY (`payment_id`) REFERENCES `bar_fee_payments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_p_hist_perf` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 30. Due History Table (New for Phase 8)
CREATE TABLE IF NOT EXISTS `bar_fee_due_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `due_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `old_amount` DECIMAL(12,2) NULL,
    `new_amount` DECIMAL(12,2) NULL,
    `remarks` TEXT NULL,
    `performed_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_d_hist_due` FOREIGN KEY (`due_id`) REFERENCES `member_fee_dues` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_d_hist_perf` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- DEV SEED DATA - Realistic Banda Values
-- ==========================================

-- Insert Members
INSERT INTO `members` (`id`, `membership_no`, `enrollment_no`, `full_name`, `father_or_husband_name`, `gender`, `date_of_birth`, `mobile`, `alternate_mobile`, `email`, `permanent_address`, `local_address`, `district`, `state`, `pincode`, `photo`, `enrollment_date`, `member_since`, `membership_category`, `membership_status`, `chamber_no`, `practice_area`, `blood_group`, `is_office_bearer`, `is_public`, `show_mobile_publicly`, `show_email_publicly`) VALUES
(1, 'DBA-001', 'UP/1234/2005', 'Adv. Rajesh Kumar Singh', 'Late R. P. Singh', 'Male', '1978-04-10', '9999999999', '9999999998', 'rajesh.singh@bandadistrictbar.org', 'Civil Lines, Banda', 'Chamber 12, Court Campus, Banda', 'Banda', 'Uttar Pradesh', '210001', 'default_advocate.png', '2005-03-12', '2005-06-15', 'Life Member', 'active', '12', 'Civil & Constitutional', 'O+', 1, 1, 1, 1),
(2, 'DBA-002', 'UP/2458/2012', 'Adv. Neha Srivastava', 'Shri V. K. Srivastava', 'Female', '1987-11-23', '7777777777', '7777777776', 'neha.sri@bandadistrictbar.org', 'Bhuragarh, Banda', 'Chamber 45, Court Campus, Banda', 'Banda', 'Uttar Pradesh', '210001', 'default_advocate.png', '2012-05-18', '2012-08-20', 'Regular Member', 'active', '45', 'Criminal & Revenue', 'B+', 1, 1, 0, 1),
(3, 'DBA-003', 'UP/3921/2010', 'Adv. Amit Kumar Mishra', 'Shri R. N. Mishra', 'Male', '1984-07-15', '9876543210', '9876543211', 'amit.mishra@bandadistrictbar.org', 'Katra, Banda', 'Chamber 28, Court Campus, Banda', 'Banda', 'Uttar Pradesh', '210001', 'default_advocate.png', '2010-09-02', '2010-11-05', 'Regular Member', 'active', '28', 'Criminal & Civil', 'A+', 0, 1, 1, 0),
(4, 'DBA-004', 'UP/8762/2018', 'Adv. Vikramaditya Bundela', 'Shri S. S. Bundela', 'Male', '1992-01-30', '8888888888', NULL, 'vikram.bundela@gmail.com', 'Mawai, Banda', 'Chamber 15, Block C, Banda', 'Banda', 'Uttar Pradesh', '210001', 'default_advocate.png', '2018-02-14', '2018-04-12', 'Regular Member', 'active', '15', 'Revenue', 'O-', 0, 1, 0, 0),
(5, 'DBA-005', 'UP/4122/2015', 'Adv. Sarita Yadav', 'Shri Ram Lakhan Yadav', 'Female', '1990-09-05', '9450123456', NULL, 'sarita.yadav@gmail.com', 'Tindwari, Banda', 'N/A', 'Banda', 'Uttar Pradesh', '210128', 'default_advocate.png', '2015-06-10', '2015-09-01', 'Regular Member', 'inactive', 'N/A', 'Matrimonial', 'AB+', 0, 1, 0, 0);

-- Insert Users
INSERT INTO `users` (`id`, `member_id`, `username`, `email`, `mobile`, `password_hash`, `role`, `status`) VALUES
(1, NULL, 'admin', 'admin@bandadistrictbar.org', '9999999999', '$2y$10$Ed8FltAKgFY0Wx/x8cWqo.JlfYOsVMEE7FiMEw3hVak4g/dV79/Nq', 'admin', 'active'),
(2, 1, 'president', 'president@bandadistrictbar.org', '8888888888', '$2y$10$9D1LUTUcDvKF8L3YgxWsoeYUuoUxd5SZfp1LLIUuZs2xuVTViNN.a', 'president', 'active'),
(3, 2, 'mahasachiv', 'secretary@bandadistrictbar.org', '7777777777', '$2y$10$WG8JtpoGZuk5Ne3h04GIjePFxl.0.VXz.FKIqqRpvpVCNu1BGJ.wy', 'mahasachiv', 'active'),
(4, 3, 'member', 'member@bandadistrictbar.org', '9876543210', '$2y$10$Zypm4drSrYDxNPJPmNJIj.B4Iz2TTXOi20Bg9wcdfFPbVAbMo49Oy', 'member', 'active');

-- Link user records back
UPDATE `members` SET `user_id` = 2 WHERE `id` = 1;
UPDATE `members` SET `user_id` = 3 WHERE `id` = 2;
UPDATE `members` SET `user_id` = 4 WHERE `id` = 3;

-- Insert Bearers positions seeds
INSERT INTO `office_bearer_positions` (`id`, `position_name`, `position_name_hindi`, `code`, `display_order`, `max_holders`, `is_executive`, `status`) VALUES
(1, 'President', 'अध्यक्ष', 'PRESIDENT', 1, 1, 1, 'active'),
(2, 'Vice President', 'उपाध्यक्ष', 'VICE_PRESIDENT', 2, 2, 1, 'active'),
(3, 'Mahasachiv', 'महासचिव', 'MAHASACHIV', 3, 1, 1, 'active'),
(4, 'Treasurer', 'कोषाध्यक्ष', 'TREASURER', 4, 1, 1, 'active'),
(5, 'Joint Secretary', 'सह सचिव', 'JOINT_SECRETARY', 5, 2, 1, 'active'),
(6, 'Executive Member', 'कार्यकारिणी सदस्य', 'EXECUTIVE_MEMBER', 6, 10, 1, 'active');

-- Insert Bearers terms seeds
INSERT INTO `office_bearer_terms` (`id`, `title`, `start_date`, `end_date`, `status`, `description`, `created_by`) VALUES
(1, 'Executive Committee 2025-26', '2025-04-01', '2026-03-31', 'completed', 'विगत वर्ष की निर्वाचित बार कार्यकारिणी समिति।', 1),
(2, 'Executive Committee 2026-27', '2026-04-01', NULL, 'active', 'सक्रिय वर्ष की निर्वाचित बार कार्यकारिणी समिति। (Demo / Seed)', 1);

-- Insert Office Bearers Seeds
INSERT INTO `office_bearers` (`id`, `term_id`, `position_id`, `member_id`, `display_name_snapshot`, `photo_snapshot`, `membership_no_snapshot`, `enrollment_no_snapshot`, `designation_override`, `message`, `display_order`, `status`, `created_by`) VALUES
(1, 2, 1, 1, 'Adv. Rajesh Kumar Singh', 'default_advocate.png', 'DBA-001', 'UP/1234/2005', NULL, 'अधिवक्ता हितों की रक्षा और संघ की गरिमा बनाए रखना ही मेरा मुख्य संकल्प है। अध्यक्ष की कलम से नववर्ष की शुभकामनाएं।', 1, 'active', 1),
(2, 2, 3, 3, 'Adv. Amit Kumar Mishra', 'default_advocate.png', 'DBA-003', 'UP/3921/2010', NULL, 'संघ के कुशल प्रशासनिक संचालन और विधिक सहयोग हेतु सदैव उपलब्ध रहने के लिए प्रतिबद्ध।', 3, 'active', 1),
(3, 2, 2, 2, 'Adv. Neha Srivastava', 'default_advocate.png', 'DBA-002', 'UP/2458/2012', NULL, NULL, 2, 'active', 1),
(4, 2, 4, 4, 'Adv. Vikramaditya Bundela', 'default_advocate.png', 'DBA-004', 'UP/8762/2018', NULL, NULL, 4, 'active', 1);

-- Insert Realistic Notice Seeds
INSERT INTO `notices` (`id`, `notice_no`, `title`, `title_hindi`, `slug`, `category`, `short_description`, `description`, `priority`, `visibility`, `status`, `publish_at`, `created_by`, `approved_by`, `approved_at`, `published_at`) VALUES
(1, 'DBA/NOTICE/2026/0001', 'General Meeting regarding Executive Budget 2026', 'सामान्य सभा की बैठक संबंधी सूचना', 'general-meeting-budget-2026', 'Meeting Notice', 'संघ की कार्यकारिणी बजट आवंटन बैठक संबंधी आवश्यक सूचना।', 'संघ के सभी सम्मानित कार्यकारिणी सदस्यों को सूचित किया जाता है कि दिनांक २५ अगस्त २०२६ को दोपहर १:०० बजे संघ सभागार में एक आवश्यक कार्यकारिणी बैठक आयोजित की जाएगी। बैठक में वर्ष २०२६-२७ के बजट आवंटन, पुस्तकालय सुधार एवं कक्ष किराया समीक्षा आदि विषयों पर विचार-विमर्श किया जाएगा।', 'important', 'public', 'published', '2026-08-20 00:00:00', 1, 1, NOW(), NOW()),
(2, 'DBA/NOTICE/2026/0002', 'Member Profile Verification COP compliance', 'सदस्य अभिलेख अद्यतन करने संबंधी सूचना', 'member-profile-cop-compliance', 'General Notice', 'बार काउंसिल नियमों के अंतर्गत सदस्यता सत्यापन व COP अद्यतन जानकारी।', 'उत्तर प्रदेश बार काउंसिल के आदेशानुसार, संघ के सभी पंजीकृत सदस्यों को अपनी वकालत की वैधता (COP Card) का सत्यापन कराना आवश्यक है। समस्त सदस्यगण अपने सीओपी प्रमाणपत्र तथा दो नवीन पासपोर्ट साइज फोटो दिनांक ३१ अगस्त २०२६ तक कार्यालय में जमा कराना सुनिश्चित करें।', 'normal', 'members_only', 'published', '2026-08-21 00:00:00', 1, 1, NOW(), NOW()),
(3, 'DBA/NOTICE/2026/0003', 'Condolence Notice - Late Shri Ram Gupta', 'शोक सभा सूचना - स्व० श्री राम गुप्ता जी', 'condolence-shri-ram-gupta', 'Condolence Notice', 'वरिष्ठ अधिवक्ता स्व० श्री राम गुप्ता जी के दुःखद निधन पर बार संघ की शोक सभा।', 'हमारे बार संघ के अत्यंत वरिष्ठ सदस्य श्री राम गुप्ता जी का स्वर्गवास दिनांक १५ अगस्त को हो गया है। उनके विधिक सेवा काल एवं संघ के प्रति योगदान को याद करते हुए शोक सभा का आयोजन किया जाएगा।', 'urgent', 'public', 'published', '2026-08-15 00:00:00', 1, 1, NOW(), NOW());

-- Insert Condolence Detail Seed
INSERT INTO `condolence_notices` (`id`, `notice_id`, `member_id`, `advocate_name`, `advocate_photo`, `membership_no`, `enrollment_no`, `date_of_death`, `condolence_message`, `prayer_meeting_details`) VALUES
(1, 3, 5, 'Adv. Sarita Yadav', 'default_advocate.png', 'DBA-005', 'UP/4122/2015', '2026-08-14', 'दिवंगत आत्मा की शांति हेतु संघ द्वारा शोक संदेश।', 'प्रार्थना सभा दिनांक १६ अगस्त को दोपहर १२ बजे संघ सभागार में।');

-- Insert Notice History Logs
INSERT INTO `notice_history` (`notice_id`, `action`, `old_status`, `new_status`, `remarks`, `performed_by`) VALUES
(1, 'Created & Published', NULL, 'published', 'सामान्य सभा बैठक सूचना निर्मित की गई।', 1),
(2, 'Created & Published', NULL, 'published', 'सदस्य सत्यापन सूचना निर्मित की गई।', 1),
(3, 'Created & Published', NULL, 'published', 'शोक संदेश सूचना निर्मित की गई।', 1);

-- Insert Demo ID Card
INSERT INTO `id_cards` (`id`, `member_id`, `card_number`, `card_type`, `valid_from`, `valid_until`, `status`, `qr_token`, `application_type`, `requested_by`, `approved_by`, `approved_at`, `issued_by`, `issued_at`) VALUES
(1, 1, 'DBA-ID-2026-0001', 'digital', '2026-01-01', '2028-01-01', 'issued', 'e4a2b9f1d8c76b5a4a3f2e1d0c9b8a7f6e5d4c3b2a1f0e9d', 'new', 2, 1, '2026-01-01 10:00:00', 1, '2026-01-01 10:05:00');

-- Insert ID Card History
INSERT INTO `id_card_history` (`id_card_id`, `member_id`, `action`, `old_status`, `new_status`, `remarks`, `performed_by`) VALUES
(1, 1, 'Application Submitted', NULL, 'requested', 'नया आईडी कार्ड आवेदन प्रस्तुत किया गया।', 2),
(1, 1, 'Approved', 'requested', 'approved', 'सत्यापन उपरांत कार्ड स्वीकृत किया गया।', 1),
(1, 1, 'Issued', 'approved', 'issued', 'डिजिटल कार्ड जारी किया गया।', 1);

-- Insert Demo Wakalatnama
INSERT INTO `wakalatnamas` (`id`, `title`, `title_hindi`, `description`, `category`, `file_path`, `original_file_name`, `file_size`, `version`, `effective_from`, `status`, `members_only`, `uploaded_by`, `approved_by`, `approved_at`) VALUES
(1, 'General Wakalatnama', 'सामान्य वकालतनामा', 'सभी न्यायालयों हेतु सामान्य वकालतनामा फॉर्म।', 'General', 'demo_waka_v1.pdf', 'General_Wakalatnama_v1.pdf', 120450, '1.0', '2026-01-01', 'active', 1, 1, 1, NOW());

-- Insert Wakalatnama History Logs
INSERT INTO `wakalatnama_history` (`wakalatnama_id`, `action`, `old_status`, `new_status`, `remarks`, `performed_by`) VALUES
(1, 'Created & Uploaded', NULL, 'draft', 'प्रारूप वकालतनामा अपलोडेड।', 1),
(1, 'Approved & Activated', 'draft', 'active', 'सक्रिय घोषित कर सदस्यों हेतु उपलब्ध कराया गया।', 1);

-- Insert Financial Years Seeds
INSERT INTO `financial_years` (`id`, `name`, `start_date`, `end_date`, `status`) VALUES
(1, '2025-26', '2025-04-01', '2026-03-31', 'closed'),
(2, '2026-27', '2026-04-01', '2027-03-31', 'active');

-- Insert Association Fund Accounts Seeds
INSERT INTO `association_fund_accounts` (`id`, `financial_year_id`, `opening_balance`, `opening_balance_date`, `opening_balance_notes`, `status`, `created_by`) VALUES
(1, 1, 50000.00, '2025-04-01', 'प्रारंभिक कोष पूंजी वर्ष २०२५-२६', 'closed', 1),
(2, 2, 60000.00, '2026-04-01', 'वर्ष २०२६-२७ हेतु कैरी फॉरवर्डेड प्रारंभिक शेष', 'open', 1);

-- Insert Association Fund Categories Seeds
INSERT INTO `association_fund_categories` (`id`, `name`, `type`, `description`) VALUES
(1, 'Donation', 'income', 'संघ में प्राप्त स्वैच्छिक दान राशि।'),
(2, 'Association Contribution', 'income', 'संघ में प्राप्त सामान्य योगदान।'),
(3, 'Interest Income', 'income', 'बैंक ब्याज से प्राप्त आय।'),
(4, 'Miscellaneous Income', 'income', 'विविध आय स्रोत।'),
(5, 'Office Expense', 'expense', 'कार्यालय रखरखाव व प्रशासन व्यय।'),
(6, 'Maintenance', 'expense', 'भवन व परिसर मरम्मत व्यय।'),
(7, 'Events', 'expense', 'संघ के विधिक व संस्कृतिक समारोहों का व्यय।'),
(8, 'Printing & Stationery', 'expense', 'प्रकाशन, फॉर्म छपाई व स्टेशनरी व्यय।'),
(9, 'Other', 'expense', 'अन्य विविध व्यय।');

-- Insert Fund Transactions Seeds
INSERT INTO `association_fund_transactions` (`id`, `financial_year_id`, `transaction_no`, `transaction_date`, `transaction_type`, `category_id`, `amount`, `payment_mode`, `party_name`, `description`, `receipt_no`, `voucher_no`, `status`, `created_by`, `approved_by`, `approved_at`) VALUES
(1, 2, 'DBA/FUND/2026-27/0001', '2026-05-10', 'income', 1, 10000.00, 'UPI', 'Adv. Shiv Shankar', 'विशेष विधिक विकास हेतु दान', 'REC-2026-01', NULL, 'approved', 1, 1, NOW()),
(2, 2, 'DBA/FUND/2026-27/0002', '2026-06-15', 'expense', 8, 2500.00, 'Cash', 'Banda Stationery Mart', 'कार्यालय रजिस्टर व फाइल छपाई', NULL, 'VOU-2026-01', 'approved', 1, 1, NOW());

-- Insert Association Fund History Logs
INSERT INTO `association_fund_history` (`transaction_id`, `financial_year_id`, `action`, `old_status`, `new_status`, `remarks`, `performed_by`) VALUES
(1, 2, 'Created & Approved', NULL, 'approved', 'प्रारंभिक दान आय स्वीकृत की गई।', 1),
(2, 2, 'Created & Approved', NULL, 'approved', 'स्टेशनरी व्यय स्वीकृत किया गया।', 1);

-- Insert Bar Fee Types Seeds (New for Phase 8)
INSERT INTO `bar_fee_types` (`id`, `name`, `code`, `description`, `amount`, `frequency`, `status`, `created_by`) VALUES
(1, 'Annual Bar Fee', 'BAR_FEE', 'वार्षिक संघ सदस्यता शुल्क (General Subscription)', 1000.00, 'yearly', 'active', 1),
(2, 'Membership Renewal Fee', 'RENEWAL_FEE', 'सदस्यता नवीनीकरण शुल्क', 500.00, 'yearly', 'active', 1),
(3, 'Other Association Member Fee', 'OTHER_FEE', 'अन्य संघ शुल्क (विविध योगदान)', NULL, 'custom', 'active', 1);

-- Insert Member Fee Dues Seeds (New for Phase 8)
INSERT INTO `member_fee_dues` (`id`, `member_id`, `fee_type_id`, `financial_year`, `period_label`, `due_date`, `original_amount`, `discount_amount`, `penalty_amount`, `payable_amount`, `paid_amount`, `outstanding_amount`, `status`, `remarks`, `created_by`) VALUES
(1, 1, 1, '2026-27', 'Annual Bar Fee 2026-27', '2026-09-30', 1000.00, 0.00, 0.00, 1000.00, 1000.00, 0.00, 'paid', 'प्रारंभिक वार्षिक शुल्क पूर्ण भुगतान', 1),
(2, 1, 2, '2026-27', 'Membership Renewal Fee 2026-27', '2026-09-30', 500.00, 0.00, 0.00, 500.00, 0.00, 500.00, 'pending', 'नवीनीकरण शुल्क बकाया', 1),
(3, 2, 1, '2026-27', 'Annual Bar Fee 2026-27', '2026-09-30', 1000.00, 0.00, 0.00, 1000.00, 0.00, 1000.00, 'pending', 'वार्षिक शुल्क', 1);

-- Insert Fee Payments Seeds (New for Phase 8)
INSERT INTO `bar_fee_payments` (`id`, `payment_no`, `member_id`, `payment_date`, `amount`, `payment_mode`, `transaction_reference`, `receipt_no`, `status`, `remarks`, `collected_by`, `created_by`, `approved_by`, `approved_at`) VALUES
(1, 'DBA/BF/2026-27/00001', 1, '2026-05-12', 1000.00, 'UPI', 'UTR7721839', 'REC-BF-2026-0001', 'confirmed', 'वार्षिक सदस्यता शुल्क भुगतान', 1, 1, 1, NOW());

-- Insert Payment Allocations Seeds (New for Phase 8)
INSERT INTO `bar_fee_payment_allocations` (`id`, `payment_id`, `due_id`, `allocated_amount`) VALUES
(1, 1, 1, 1000.00);

-- Insert Fee Payment History Logs (New for Phase 8)
INSERT INTO `bar_fee_payment_history` (`payment_id`, `action`, `old_status`, `new_status`, `remarks`, `performed_by`) VALUES
(1, 'Payment Confirmed', NULL, 'confirmed', 'ऑनलाइन भुगतान दर्ज व पुष्टि', 1);

-- Insert Due History Logs (New for Phase 8)
INSERT INTO `bar_fee_due_history` (`due_id`, `action`, `old_amount`, `new_amount`, `remarks`, `performed_by`) VALUES
(1, 'Payment Allocated', 1000.00, 0.00, 'पेमेंट संख्या १ आवंटित', 1);

-- Phase 9: Room Rent & Chamber Management Tables

CREATE TABLE IF NOT EXISTS `chambers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `chamber_no` VARCHAR(50) UNIQUE NOT NULL,
  `block_name` VARCHAR(100) NULL,
  `floor` VARCHAR(50) NULL,
  `chamber_type` VARCHAR(50) NULL,
  `monthly_rent` DECIMAL(12,2) NOT NULL,
  `security_deposit` DECIMAL(12,2) NOT NULL,
  `occupancy_type` ENUM('single', 'shared') NOT NULL,
  `capacity` INT DEFAULT 1,
  `status` ENUM('available', 'occupied', 'partially_occupied', 'reserved', 'maintenance', 'inactive') DEFAULT 'available',
  `description` TEXT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamber_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_no` VARCHAR(50) UNIQUE NOT NULL,
  `member_id` INT NOT NULL,
  `preferred_chamber_id` INT NULL,
  `chamber_preference` VARCHAR(255) NULL,
  `application_date` DATE NOT NULL,
  `reason` TEXT NULL,
  `current_chamber_id` INT NULL,
  `status` ENUM('submitted', 'under_review', 'waiting_list', 'approved', 'rejected', 'withdrawn', 'allotted') DEFAULT 'submitted',
  `priority_no` INT NULL,
  `remarks` TEXT NULL,
  `reviewed_by` INT NULL,
  `reviewed_at` TIMESTAMP NULL,
  `approved_by` INT NULL,
  `approved_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamber_allotments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `chamber_id` INT NOT NULL,
  `member_id` INT NOT NULL,
  `application_id` INT NULL,
  `allotment_no` VARCHAR(50) UNIQUE NOT NULL,
  `allotment_date` DATE NOT NULL,
  `effective_from` DATE NOT NULL,
  `monthly_rent` DECIMAL(12,2) NOT NULL,
  `security_deposit` DECIMAL(12,2) NOT NULL,
  `security_deposit_paid` DECIMAL(12,2) DEFAULT 0.00,
  `status` ENUM('active', 'vacated', 'transferred', 'cancelled') DEFAULT 'active',
  `vacated_at` DATE NULL,
  `vacate_reason` TEXT NULL,
  `allotted_by` INT NOT NULL,
  `approved_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamber_security_deposits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `allotment_id` INT NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_mode` VARCHAR(50) NOT NULL,
  `reference_no` VARCHAR(100) NULL,
  `receipt_no` VARCHAR(50) UNIQUE NOT NULL,
  `status` ENUM('pending', 'paid', 'refunded', 'adjusted', 'forfeited') DEFAULT 'paid',
  `remarks` TEXT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamber_rent_dues` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `allotment_id` INT NOT NULL,
  `rent_month` VARCHAR(20) NOT NULL, -- Format: YYYY-MM
  `due_date` DATE NOT NULL,
  `rent_amount` DECIMAL(12,2) NOT NULL,
  `penalty_amount` DECIMAL(12,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
  `payable_amount` DECIMAL(12,2) NOT NULL,
  `paid_amount` DECIMAL(12,2) DEFAULT 0.00,
  `outstanding_amount` DECIMAL(12,2) NOT NULL,
  `status` ENUM('pending', 'partially_paid', 'paid', 'overdue', 'waived', 'cancelled') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `uq_rent_allot_month` UNIQUE (`allotment_id`, `rent_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamber_rent_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payment_no` VARCHAR(50) UNIQUE NOT NULL,
  `member_id` INT NOT NULL,
  `allotment_id` INT NOT NULL,
  `payment_date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_mode` VARCHAR(50) NOT NULL,
  `reference_no` VARCHAR(100) NULL,
  `receipt_no` VARCHAR(50) UNIQUE NOT NULL,
  `status` ENUM('confirmed', 'cancelled') DEFAULT 'confirmed',
  `remarks` TEXT NULL,
  `collected_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamber_rent_payment_allocations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payment_id` INT NOT NULL,
  `rent_due_id` INT NOT NULL,
  `allocated_amount` DECIMAL(12,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamber_vacate_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_id` INT NOT NULL,
  `allotment_id` INT NOT NULL,
  `requested_date` DATE NOT NULL,
  `reason` TEXT NULL,
  `status` ENUM('submitted', 'approved', 'rejected', 'withdrawn') DEFAULT 'submitted',
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamber_audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `action` VARCHAR(100) NOT NULL,
  `old_value` TEXT NULL,
  `new_value` TEXT NULL,
  `remarks` TEXT NULL,
  `performed_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeds for Phase 9 Chamber Management
INSERT INTO `chambers` (`id`, `chamber_no`, `block_name`, `floor`, `chamber_type`, `monthly_rent`, `security_deposit`, `occupancy_type`, `capacity`, `status`, `description`, `created_by`) VALUES
(1, 'A-12', 'Main Court Block', 'Ground Floor', 'General Room', 1000.00, 5000.00, 'single', 1, 'occupied', 'मुख्य ब्लॉक के समीप भूतल पर स्थित सिंगल केबिन चैम्बर', 1),
(2, 'B-04', 'Library Block', 'First Floor', 'Shared Cabin', 600.00, 3000.00, 'shared', 2, 'available', 'साझा चैम्बर लाइब्रेरी हॉल के पास', 1),
(3, 'C-08', 'New Annex Block', 'First Floor', 'General Room', 1200.00, 6000.00, 'single', 1, 'available', 'नवीन एनेक्सी ब्लॉक प्रथम तल', 1);

INSERT INTO `chamber_allotments` (`id`, `chamber_id`, `member_id`, `allotment_no`, `allotment_date`, `effective_from`, `monthly_rent`, `security_deposit`, `security_deposit_paid`, `status`, `allotted_by`) VALUES
(1, 1, 1, 'DBA/ALLOT/2026/0001', '2026-04-01', '2026-04-01', 1000.00, 5000.00, 5000.00, 'active', 1);

INSERT INTO `chamber_security_deposits` (`id`, `allotment_id`, `amount`, `payment_date`, `payment_mode`, `receipt_no`, `status`, `created_by`) VALUES
(1, 1, 5000.00, '2026-04-01', 'Cash', 'REC-SD-2026-0001', 'paid', 1);

INSERT INTO `chamber_rent_dues` (`id`, `allotment_id`, `rent_month`, `due_date`, `rent_amount`, `payable_amount`, `paid_amount`, `outstanding_amount`, `status`) VALUES
(1, 1, '2026-04', '2026-04-10', 1000.00, 1000.00, 1000.00, 0.00, 'paid'),
(2, 1, '2026-05', '2026-05-10', 1000.00, 1000.00, 0.00, 1000.00, 'overdue');

INSERT INTO `chamber_rent_payments` (`id`, `payment_no`, `member_id`, `allotment_id`, `payment_date`, `amount`, `payment_mode`, `receipt_no`, `status`, `collected_by`) VALUES
(1, 'DBA/RP/2026/0001', 1, 1, '2026-04-05', 1000.00, 'Cash', 'REC-RENT-2026-0001', 'confirmed', 1);

-- ==========================================
-- Phase 10: Election Management System Tables
-- ==========================================

-- Alter notices table to support election link
ALTER TABLE `notices` ADD COLUMN `election_id` INT NULL AFTER `notice_no`;

CREATE TABLE IF NOT EXISTS `elections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `election_code` VARCHAR(50) UNIQUE NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `election_year` INT NOT NULL,
  `election_date` DATE NOT NULL,
  `nomination_start` DATETIME NULL,
  `nomination_end` DATETIME NULL,
  `scrutiny_date` DATE NULL,
  `withdrawal_deadline` DATETIME NULL,
  `final_candidate_date` DATE NULL,
  `voting_start_time` TIME NULL,
  `voting_end_time` TIME NULL,
  `counting_date` DATE NULL,
  `result_date` DATE NULL,
  `venue` VARCHAR(255) NULL,
  `status` ENUM('draft', 'announced', 'nomination_open', 'scrutiny', 'withdrawal', 'candidate_finalized', 'voter_list_finalized', 'polling_scheduled', 'polling_completed', 'counting', 'result_declared', 'completed', 'archived', 'cancelled') DEFAULT 'draft',
  `visibility` ENUM('public', 'members_only', 'private') DEFAULT 'private',
  `created_by` INT NOT NULL,
  `approved_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `election_posts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `election_id` INT NOT NULL,
  `post_name` VARCHAR(100) NOT NULL,
  `post_name_hindi` VARCHAR(100) NULL,
  `number_of_seats` INT DEFAULT 1,
  `display_order` INT DEFAULT 0,
  `description` TEXT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_post_elec` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `election_voters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `election_id` INT NOT NULL,
  `member_id` INT NOT NULL,
  `voter_no` VARCHAR(50) NULL,
  `eligibility_status` ENUM('eligible', 'not_eligible', 'pending_review') DEFAULT 'pending_review',
  `eligibility_reason` TEXT NULL,
  `finalized` TINYINT DEFAULT 0,
  `added_by` INT NOT NULL,
  `member_name` VARCHAR(150) NULL,
  `membership_no` VARCHAR(50) NULL,
  `enrollment_no` VARCHAR(100) NULL,
  `chamber_no` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_voter_elec` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_voter_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_voter_creator` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_elec_voter` (`election_id`, `member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `election_candidates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `election_id` INT NOT NULL,
  `post_id` INT NOT NULL,
  `member_id` INT NOT NULL,
  `nomination_no` VARCHAR(50) NULL,
  `nomination_date` DATE NULL,
  `status` ENUM('nominated', 'under_scrutiny', 'accepted', 'rejected', 'withdrawn', 'final_candidate') DEFAULT 'nominated',
  `ballot_name` VARCHAR(150) NULL,
  `ballot_order` INT NULL,
  `photo_snapshot` VARCHAR(255) NULL,
  `name_snapshot` VARCHAR(150) NULL,
  `membership_no_snapshot` VARCHAR(50) NULL,
  `enrollment_no_snapshot` VARCHAR(100) NULL,
  `proposer_member_id` INT NULL,
  `seconder_member_id` INT NULL,
  `remarks` TEXT NULL,
  `reviewed_by` INT NULL,
  `approved_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cand_elec` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cand_post` FOREIGN KEY (`post_id`) REFERENCES `election_posts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cand_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cand_proposer` FOREIGN KEY (`proposer_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cand_seconder` FOREIGN KEY (`seconder_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cand_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cand_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  UNIQUE KEY `uq_elec_cand_post` (`election_id`, `post_id`, `member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `election_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `election_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `document_type` ENUM('Election Programme', 'Nomination Form', 'Election Rules', 'Preliminary Voter List PDF', 'Final Voter List PDF', 'Final Candidate List PDF', 'Result Sheet', 'Other') NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `visibility` ENUM('public', 'members_only', 'private') DEFAULT 'public',
  `status` ENUM('draft', 'published', 'archived') DEFAULT 'draft',
  `uploaded_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_doc_elec` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_doc_up` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `election_results` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `election_id` INT NOT NULL,
  `post_id` INT NOT NULL,
  `candidate_id` INT NOT NULL,
  `votes_received` INT NULL,
  `result_status` ENUM('elected', 'runner_up', 'not_elected', 'unopposed', 'tied', 'pending') DEFAULT 'pending',
  `position` INT NULL,
  `remarks` TEXT NULL,
  `declared_by` INT NULL,
  `declared_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_res_elec` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_res_post` FOREIGN KEY (`post_id`) REFERENCES `election_posts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_res_cand` FOREIGN KEY (`candidate_id`) REFERENCES `election_candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_res_decl` FOREIGN KEY (`declared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  UNIQUE KEY `uq_elec_res_post_cand` (`election_id`, `post_id`, `candidate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `election_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `election_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `record_id` INT NULL,
  `old_value` TEXT NULL,
  `new_value` TEXT NULL,
  `remarks` TEXT NULL,
  `performed_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_hist_elec` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_perf_by` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeds for Phase 10: Election Management
INSERT INTO `elections` (`id`, `election_code`, `title`, `description`, `election_year`, `election_date`, `nomination_start`, `nomination_end`, `scrutiny_date`, `withdrawal_deadline`, `final_candidate_date`, `voting_start_time`, `voting_end_time`, `counting_date`, `result_date`, `venue`, `status`, `visibility`, `created_by`) VALUES
(1, 'DBA-ELECTION-2026', 'वार्षिक कार्यकारिणी चुनाव २०२६-२७', 'जिला अधिवक्ता संघ, बांदा के वार्षिक पदाधिकारियों के निर्वाचन हेतु आधिकारिक चुनाव प्रक्रिया।', 2026, '2026-09-15', '2026-09-01 10:00:00', '2026-09-05 16:00:00', '2026-09-06', '2026-09-08 15:00:00', '2026-09-09', '09:00:00', '16:00:00', '2026-09-15', '2026-09-15', 'बार संघ सभागार भवन, बांदा', 'announced', 'public', 1);

INSERT INTO `election_posts` (`id`, `election_id`, `post_name`, `post_name_hindi`, `number_of_seats`, `display_order`, `description`, `status`) VALUES
(1, 1, 'President', 'अध्यक्ष', 1, 1, 'संघ के सर्वोच्च कार्यकारी अधिकारी', 'active'),
(2, 1, 'Vice President', 'उपाध्यक्ष', 1, 2, 'अध्यक्ष की अनुपस्थिति में कार्यभार संभालने वाले अधिकारी', 'active'),
(3, 1, 'Mahasachiv', 'महासचिव', 1, 3, 'संघ के मुख्य प्रशासनिक सचिव', 'active');

SET FOREIGN_KEY_CHECKS = 1;
