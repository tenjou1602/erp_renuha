-- Admin final confirmation step for purchase requests.
-- Flow: pending -> approved (procurement manager) -> confirmed (admin) -> PO
-- Run once against runeha_erp.

ALTER TABLE `purchase_requests`
  MODIFY COLUMN `status` ENUM(
    'draft',
    'pending',
    'approved',
    'confirmed',
    'rejected',
    'ordered',
    'received'
  ) NOT NULL DEFAULT 'pending';

-- Add confirmed_by / confirmed_at if missing (run manually if columns already exist):
-- ALTER TABLE `purchase_requests` ADD COLUMN `confirmed_by` INT NULL DEFAULT NULL AFTER `approved_at`;
-- ALTER TABLE `purchase_requests` ADD COLUMN `confirmed_at` DATETIME NULL DEFAULT NULL AFTER `confirmed_by`;
