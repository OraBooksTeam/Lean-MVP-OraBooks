CREATE TABLE IF NOT EXISTS `{prefix}fiscal_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned NOT NULL,
  `period_type` varchar(20) NOT NULL DEFAULT 'MONTH',
  `period_name` varchar(100) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'OPEN',
  `closed_by` bigint(20) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `reopened_by` bigint(20) unsigned DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopen_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `org_type_period_start` (`org_id`, `period_type`, `period_start`),
  KEY `org_id` (`org_id`),
  KEY `org_status` (`org_id`, `status`),
  KEY `org_period_start_idx` (`org_id`, `period_start`),
  KEY `org_period_end_idx` (`org_id`, `period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{prefix}orabooks_ac_audit_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `old_value` longtext DEFAULT NULL,
  `new_value` longtext DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `org_event` (`org_id`, `event_type`),
  KEY `entity_lookup` (`entity_type`, `entity_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `{prefix}orabooks_db_sidebar`
  (`module`, `parent`, `menu_title`, `menu_slug`, `icon`, `sort_order`, `status`)
SELECT 'accounting', s.id, 'Fiscal Periods', 'fiscal-periods', 'fa-solid fa-lock', 8, 1
FROM `{prefix}orabooks_db_sidebar` s
WHERE s.module = 'accounting'
  AND s.menu_slug = 'setting'
  AND NOT EXISTS (
    SELECT 1 FROM `{prefix}orabooks_db_sidebar` fp
    WHERE fp.module = 'accounting' AND fp.menu_slug = 'fiscal-periods'
  );
