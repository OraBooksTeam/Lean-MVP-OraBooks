<?php
/**
 * RBAC / ABAC access-control facade.
 *
 * Centralizes org-scoped permission evaluation, permission-denied audit logs,
 * and the public permission matrix used by UI filtering.
 */

if (!defined('ABSPATH')) {
 exit;
}

class OBN_Access_Control {
 const ROLES = ['owner', 'admin', 'approver', 'staff', 'viewer'];

 private static $permission_aliases = [
 'manage_employees' => 'invite_user',
 'manage_settings' => 'manage_org_settings',
 'manage_roles' => 'change_role',
 ];

 private static $accounting_permissions = [
 'view_reports',
 'view_financial_reports',
 'view_operational_reports',
 'submit_transaction',
 'approve_journal',
 'reverse_journal',
 'view_coa',
 'manage_coa',
 'manage_fiscal_periods',
 'create_invoice',
 'view_invoices',
 'manage_billing',
 'manage_expenses',
 'approve_expense',
 'override_tax',
 'manage_inventory',
 'export_reports',
 'sign_report',
 ];

 public static function init() {
 if (class_exists('OraBooks_RBAC')) {
 OraBooks_RBAC::init;
 }
 }

 public static function normalize_permission($permission) {
 $permission = sanitize_key((string) $permission);
 return self::$permission_aliases[$permission] ?? $permission;
 }

 public static function public_permission_name($permission) {
 $permission = self::normalize_permission($permission);
 $reverse = array_flip(self::$permission_aliases);
 return $reverse[$permission] ?? $permission;
 }

 public static function get_roles() {
 return self::ROLES;
 }

 public static function get_permission_matrix($public_names = false) {
 $matrix = class_exists('OraBooks_RBAC') ? OraBooks_RBAC::get_all_permissions: [];
 if (!$public_names) {
 return $matrix;
 }

 $public = [];
 foreach ($matrix as $permission => $roles) {
 $public[self::public_permission_name($permission)] = $roles;
 }
 foreach (self::$permission_aliases as $alias => $canonical) {
 if (isset($matrix[$canonical])) {
 $public[$alias] = $matrix[$canonical];
 }
 }
 ksort($public);
 return $public;
 }

 public static function get_effective_permissions($role, $org_id = null) {
 $permissions = class_exists('OraBooks_RBAC')
 ? OraBooks_RBAC::get_effective_permissions($role, $org_id)
: [];

 foreach (self::$permission_aliases as $alias => $canonical) {
 if (in_array($canonical, $permissions, true) && !in_array($alias, $permissions, true)) {
 $permissions[] = $alias;
 }
 }

 return array_values(array_unique($permissions));
 }

 public static function require_permission($user_id, $org_id, $permission, $options = []) {
 $permission = self::normalize_permission($permission);
 $user_id = (int) $user_id;
 $org_id = (int) $org_id;
 $target_org_id = isset($options['target_org_id']) ? (int) $options['target_org_id']: $org_id;

 if ($user_id <= 0 || $org_id <= 0) {
 self::log_denied($user_id, $org_id, $permission, '', 'missing_context');
 return false;
 }

 if ($target_org_id > 0 && $target_org_id !== $org_id) {
 self::log_denied($user_id, $org_id, $permission, '', 'cross_tenant', [
 'target_org_id' => $target_org_id,
 ]);
 return false;
 }

 $org = class_exists('OraBooks_Organization') ? OraBooks_Organization::get($org_id): null;
 if (!$org) {
 self::log_denied($user_id, $org_id, $permission, '', 'missing_org');
 return false;
 }

 if (($org->status ?? '') !== 'active') {
 self::log_denied($user_id, $org_id, $permission, '', 'inactive_org', [
 'org_status' => $org->status ?? '',
 ]);
 return false;
 }

 $role = function_exists('orabooks_get_user_role')
 ? orabooks_get_user_role($user_id, $org_id)
: '';

 if (!$role) {
 self::log_denied($user_id, $org_id, $permission, '', 'missing_role');
 return false;
 }

 if (($org->organization_type ?? '') === 'partner' && in_array($permission, self::$accounting_permissions, true)) {
 self::log_denied($user_id, $org_id, $permission, $role, 'partner_accounting_blocked', [
 'organization_type' => 'partner',
 ]);
 return false;
 }

 $allowed = class_exists('OraBooks_RBAC')
 ? OraBooks_RBAC::check_permission($role, $permission, $org_id)
: false;

 if (!$allowed) {
 self::log_denied($user_id, $org_id, $permission, $role, 'permission_denied');
 return false;
 }

 return true;
 }

 public static function log_denied($user_id, $org_id, $permission, $role = '', $reason = 'permission_denied', $metadata = []) {
 global $wpdb;

 $permission = self::normalize_permission($permission);
 $metadata = array_merge([
 'permission' => $permission,
 'role' => $role,
 'reason' => $reason,
 'ip_address' => function_exists('orabooks_get_client_ip()') ? orabooks_get_client_ip(): '',
 'user_agent' => function_exists('orabooks_get_user_agent()') ? orabooks_get_user_agent(): '',
 ], is_array($metadata) ? $metadata: []);

 if (class_exists('OraBooks_Database')) {
 $table = OraBooks_Database::table('permission_audit_log');
 $wpdb->insert($table, [
 'org_id' => (int) $org_id,
 'user_id' => (int) $user_id,
 'event_type' => 'permission_denied',
 'permission' => $permission,
 'role' => (string) $role,
 'reason' => $reason,
 'ip_address' => $metadata['ip_address'],
 'user_agent' => $metadata['user_agent'],
 'metadata' => wp_json_encode($metadata),
 'created_at' => current_time('mysql', true),
 ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
 }

 if (function_exists('orabooks_log_event')) {
 orabooks_log_event('permission_denied', "Permission denied: {$permission}", 'warning', $metadata, $user_id ?: null, $org_id ?: null);
 }
 }

 public static function log_role_change($org_id, $target_user_id, $changed_by, $old_role, $new_role) {
 if (function_exists('orabooks_log_event')) {
 orabooks_log_event('user_role_changed', "User {$target_user_id} role changed from {$old_role} to {$new_role}", 'info', [
 'old_role' => $old_role,
 'new_role' => $new_role,
 'target_user_id' => (int) $target_user_id,
 ], $changed_by ?: null, $org_id ?: null);
 }
 }
}
