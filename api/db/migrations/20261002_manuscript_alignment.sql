-- One-time migration for an existing studio_mgmt database.
-- Back up your database before applying this file.
-- For a fresh database, import Phodio/studio_mgmt.sql instead.

ALTER TABLE `bookings`
  MODIFY `client_id` int(11) DEFAULT NULL,
  ADD COLUMN `service_type` varchar(80) DEFAULT NULL AFTER `title`,
  ADD COLUMN `package_key` varchar(80) DEFAULT NULL AFTER `service_type`,
  ADD COLUMN `slot_period` enum('AM','PM') DEFAULT NULL AFTER `start_time`,
  ADD COLUMN `attendee_count` tinyint(3) unsigned NOT NULL DEFAULT 1 AFTER `color_code`,
  ADD COLUMN `client_notes` text DEFAULT NULL AFTER `attendee_count`,
  ADD COLUMN `status` varchar(32) NOT NULL DEFAULT 'Confirmed' AFTER `client_notes`,
  ADD COLUMN `status_note` text DEFAULT NULL AFTER `status`,
  ADD COLUMN `status_updated_at` datetime DEFAULT NULL AFTER `status_note`,
  ADD COLUMN `created_at` timestamp NOT NULL DEFAULT current_timestamp() AFTER `status_updated_at`,
  ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`,
  ADD UNIQUE KEY `booking_day_slot` (`booking_date`,`slot_period`),
  ADD KEY `bookings_client_status` (`client_id`,`status`),
  ADD KEY `bookings_date_status` (`booking_date`,`status`);

-- Preserve the appointments already in the database while making them readable
-- in the client portal. Legacy slot_period stays NULL and is checked by time.
UPDATE `bookings`
SET `title` = CONCAT('Photography session #', `id`)
WHERE `title` IS NULL OR `title` = '';

UPDATE `bookings`
SET `service_type` = 'portrait'
WHERE `service_type` IS NULL OR `service_type` = '';

UPDATE `bookings`
SET `price` = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(`package_type`, '|', 2), '|', -1) AS DECIMAL(10,2))
WHERE (`price` IS NULL OR `price` = 0)
  AND `package_type` LIKE '%|%';

UPDATE `bookings`
SET `status_note` = 'Existing appointment migrated to the service progress tracker.',
    `status_updated_at` = COALESCE(`status_updated_at`, `created_at`)
WHERE `status_note` IS NULL OR `status_note` = '';

CREATE TABLE `booking_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `status` varchar(32) NOT NULL,
  `note` text NOT NULL,
  `actor_type` enum('client','admin','system') NOT NULL DEFAULT 'system',
  `actor_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_updates_booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `booking_updates` (`booking_id`, `status`, `note`, `actor_type`, `created_at`)
SELECT `id`, `status`, COALESCE(NULLIF(`status_note`, ''), 'Existing appointment imported.'), 'system', COALESCE(`status_updated_at`, `created_at`)
FROM `bookings`;
