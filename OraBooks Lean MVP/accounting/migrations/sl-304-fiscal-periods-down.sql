DELETE FROM `{prefix}orabooks_db_sidebar`
WHERE `module` = 'accounting'
  AND `menu_slug` = 'fiscal-periods';

DROP TABLE IF EXISTS `{prefix}fiscal_periods`;

-- Keep `{prefix}orabooks_ac_audit_events` by default because audit logs are immutable.
-- If a destructive rollback is explicitly approved, run:
-- DROP TABLE IF EXISTS `{prefix}orabooks_ac_audit_events`;
