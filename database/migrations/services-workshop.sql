-- Migration: feature/auth-security
-- Date: 2026-09-16
-- Run this after schema.sql on a fresh install.

-- [1] plants: soft-delete flag
ALTER TABLE `plants`
    ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1
    AFTER `is_featured`;

-- [2] workshop_registrations: allow guest applicants (no user account)
ALTER TABLE `workshop_registrations`
    MODIFY COLUMN `user_id` INT NULL DEFAULT NULL;

ALTER TABLE `workshop_registrations`
    DROP FOREIGN KEY IF EXISTS `workshop_registrations_ibfk_2`;

ALTER TABLE `workshop_registrations`
    ADD CONSTRAINT `fk_ws_reg_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- [3] workshop_registrations: staff accept/reject workflow
ALTER TABLE `workshop_registrations`
    ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'pending'
    AFTER `registration_date`;
