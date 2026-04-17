-- Fix disbursements table to match application code

-- Add missing columns
ALTER TABLE `disbursements`
  ADD COLUMN IF NOT EXISTS `application_id` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `transaction_reference` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `created_by` INT DEFAULT NULL;

-- Fix status ENUM: 'processed' → 'processing'
ALTER TABLE `disbursements`
  MODIFY COLUMN `status` ENUM('pending','processing','completed','failed') DEFAULT 'pending';

-- Fix payment_method: must have a default so inserts without it don't fail
ALTER TABLE `disbursements`
  MODIFY COLUMN `payment_method` VARCHAR(100) NOT NULL DEFAULT 'Cash';

-- Migrate any existing 'processed' rows to 'processing'
UPDATE `disbursements` SET `status` = 'processing' WHERE `status` = 'processed';
