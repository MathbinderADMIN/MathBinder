<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_Organization_Migrations {
    const VERSION = '3.1.0';

    public static function run() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;

        dbDelta("CREATE TABLE {$p}mb_organizations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            organization_type varchar(40) NOT NULL DEFAULT 'school',
            status varchar(30) NOT NULL DEFAULT 'pending',
            verification_status varchar(30) NOT NULL DEFAULT 'pending',
            owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            settings_json longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id), KEY owner_status (owner_user_id,status)
        ) {$c};");
        dbDelta("CREATE TABLE {$p}mb_terms (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) unsigned NOT NULL,
            name varchar(120) NOT NULL,
            starts_on date NULL, ends_on date NULL,
            status varchar(30) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL, updated_at datetime NOT NULL,
            PRIMARY KEY  (id), KEY organization_status (organization_id,status)
        ) {$c};");
        dbDelta("CREATE TABLE {$p}mb_classes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) unsigned NOT NULL,
            term_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(190) NOT NULL,
            section_name varchar(120) NOT NULL DEFAULT '',
            teacher_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            class_code varchar(24) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL, updated_at datetime NOT NULL,
            PRIMARY KEY  (id), UNIQUE KEY class_code (class_code),
            KEY organization_status (organization_id,status), KEY teacher_user_id (teacher_user_id)
        ) {$c};");
        dbDelta("CREATE TABLE {$p}mb_enrollments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            class_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            invited_email varchar(190) NOT NULL DEFAULT '',
            role_key varchar(30) NOT NULL DEFAULT 'student',
            status varchar(30) NOT NULL DEFAULT 'pending',
            source varchar(40) NOT NULL DEFAULT 'administrator',
            approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL, updated_at datetime NOT NULL,
            PRIMARY KEY  (id), UNIQUE KEY class_user (class_id,user_id,role_key),
            KEY class_status (class_id,status), KEY invited_email (invited_email)
        ) {$c};");
        dbDelta("CREATE TABLE {$p}mb_licenses (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) unsigned NOT NULL,
            plan_key varchar(60) NOT NULL DEFAULT 'school_premium',
            status varchar(30) NOT NULL DEFAULT 'trial',
            seat_limit int(10) unsigned NOT NULL DEFAULT 0,
            trial_ends_at datetime NULL, grace_ends_at datetime NULL,
            renews_at datetime NULL, canceled_at datetime NULL,
            provider varchar(40) NOT NULL DEFAULT 'manual',
            external_reference varchar(190) NOT NULL DEFAULT '',
            created_at datetime NOT NULL, updated_at datetime NOT NULL,
            PRIMARY KEY  (id), KEY organization_status (organization_id,status)
        ) {$c};");
        dbDelta("CREATE TABLE {$p}mb_seat_allocations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            license_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            account_email varchar(190) NOT NULL DEFAULT '',
            coverage_priority tinyint(3) unsigned NOT NULL DEFAULT 100,
            status varchar(30) NOT NULL DEFAULT 'active',
            allocated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL, updated_at datetime NOT NULL,
            PRIMARY KEY  (id), UNIQUE KEY license_email (license_id,account_email),
            KEY user_status (user_id,status)
        ) {$c};");
        $legacy_index=$wpdb->get_var("SHOW INDEX FROM {$p}mb_seat_allocations WHERE Key_name='license_user'");
        if($legacy_index){$wpdb->query("ALTER TABLE {$p}mb_seat_allocations DROP INDEX license_user");}
        $wpdb->query("UPDATE {$p}mb_seat_allocations s LEFT JOIN {$p}users u ON u.ID=s.user_id SET s.account_email=LOWER(u.user_email) WHERE s.account_email='' AND u.user_email IS NOT NULL");
        $seat_email_index=$wpdb->get_var("SHOW INDEX FROM {$p}mb_seat_allocations WHERE Key_name='license_email'");
        if(!$seat_email_index){$wpdb->query("ALTER TABLE {$p}mb_seat_allocations ADD UNIQUE KEY license_email (license_id,account_email)");}
        dbDelta("CREATE TABLE {$p}mb_invites (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            class_id bigint(20) unsigned NOT NULL,
            token_hash char(64) NOT NULL,
            invite_type varchar(30) NOT NULL DEFAULT 'link',
            role_key varchar(30) NOT NULL DEFAULT 'student',
            max_uses int(10) unsigned NOT NULL DEFAULT 0,
            use_count int(10) unsigned NOT NULL DEFAULT 0,
            expires_at datetime NULL, status varchar(30) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id), UNIQUE KEY token_hash (token_hash), KEY class_status (class_id,status)
        ) {$c};");
        update_option('mathbinder_organization_schema_version', self::VERSION, false);
    }
}
