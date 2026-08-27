<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_Migrations {
    const VERSION = '2.1.0';

    public static function run() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $audit = $wpdb->prefix . 'mb_audit_events';
        $external = $wpdb->prefix . 'mb_external_identities';
        $canvas = $wpdb->prefix . 'mb_canvas_mappings';
        $canvas_jobs = $wpdb->prefix . 'mb_canvas_sync_jobs';
        $profiles = $wpdb->prefix . 'mb_identity_profiles';
        $roles = $wpdb->prefix . 'mb_role_assignments';
        $guardians = $wpdb->prefix . 'mb_guardian_relationships';
        $verifications = $wpdb->prefix . 'mb_verifications';
        $duplicates = $wpdb->prefix . 'mb_duplicate_resolutions';
        $transfers = $wpdb->prefix . 'mb_identity_transfers';

        dbDelta("CREATE TABLE {$audit} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(100) NOT NULL,
            object_type varchar(80) NOT NULL,
            object_id varchar(191) NOT NULL DEFAULT '',
            scope_type varchar(40) NOT NULL DEFAULT 'site',
            scope_id bigint(20) unsigned NOT NULL DEFAULT 0,
            context_json longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY actor_user_id (actor_user_id),
            KEY object_lookup (object_type,object_id),
            KEY scope_lookup (scope_type,scope_id),
            KEY created_at (created_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$external} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            provider varchar(50) NOT NULL,
            issuer varchar(255) NOT NULL DEFAULT '',
            subject varchar(255) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY provider_identity (provider,issuer(100),subject(100)),
            KEY user_id (user_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$canvas} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            deployment_key varchar(191) NOT NULL,
            mapping_type varchar(50) NOT NULL,
            external_id varchar(255) NOT NULL,
            mathbinder_type varchar(50) NOT NULL,
            mathbinder_id bigint(20) unsigned NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'active',
            metadata_json longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY external_mapping (deployment_key(80),mapping_type,external_id(80)),
            KEY mathbinder_lookup (mathbinder_type,mathbinder_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$canvas_jobs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            deployment_key varchar(191) NOT NULL,
            job_type varchar(50) NOT NULL,
            direction varchar(20) NOT NULL DEFAULT 'outbound',
            status varchar(30) NOT NULL DEFAULT 'queued',
            mathbinder_type varchar(50) NOT NULL DEFAULT '',
            mathbinder_id varchar(191) NOT NULL DEFAULT '',
            external_id varchar(255) NOT NULL DEFAULT '',
            payload_json longtext NULL,
            result_json longtext NULL,
            attempts smallint(5) unsigned NOT NULL DEFAULT 0,
            last_error text NULL,
            next_attempt_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY deployment_status (deployment_key(80),status),
            KEY object_lookup (mathbinder_type,mathbinder_id(80)),
            KEY retry_lookup (status,next_attempt_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$profiles} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            permanent_key char(36) NOT NULL,
            account_status varchar(30) NOT NULL DEFAULT 'active',
            birth_year smallint(5) unsigned NULL,
            minor_status varchar(30) NOT NULL DEFAULT 'unknown',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id),
            UNIQUE KEY permanent_key (permanent_key),
            KEY account_status (account_status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$roles} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            role_key varchar(40) NOT NULL,
            scope_type varchar(40) NOT NULL DEFAULT 'site',
            scope_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(30) NOT NULL DEFAULT 'active',
            source varchar(40) NOT NULL DEFAULT 'administrator',
            approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY role_scope (user_id,role_key,scope_type,scope_id),
            KEY user_status (user_id,status),
            KEY scope_lookup (scope_type,scope_id,status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$guardians} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            student_user_id bigint(20) unsigned NOT NULL,
            guardian_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            guardian_email varchar(190) NOT NULL DEFAULT '',
            relationship_type varchar(40) NOT NULL DEFAULT 'guardian',
            approval_status varchar(30) NOT NULL DEFAULT 'pending',
            authorization_source varchar(40) NOT NULL DEFAULT 'parent',
            organization_id bigint(20) unsigned NOT NULL DEFAULT 0,
            consented_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY student_guardian (student_user_id,guardian_user_id,guardian_email(100)),
            KEY guardian_lookup (guardian_user_id,approval_status),
            KEY student_lookup (student_user_id,approval_status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$verifications} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            verification_type varchar(40) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'pending',
            verified_by bigint(20) unsigned NOT NULL DEFAULT 0,
            organization_id bigint(20) unsigned NOT NULL DEFAULT 0,
            evidence_json longtext NULL,
            verified_at datetime NULL,
            expires_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_verification (user_id,verification_type,organization_id),
            KEY status_lookup (verification_type,status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$duplicates} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            primary_user_id bigint(20) unsigned NOT NULL,
            candidate_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            candidate_email_hash char(64) NOT NULL DEFAULT '',
            resolution_status varchar(30) NOT NULL DEFAULT 'pending',
            resolution_note text NULL,
            resolved_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            resolved_at datetime NULL,
            PRIMARY KEY  (id),
            KEY primary_status (primary_user_id,resolution_status),
            KEY candidate_user_id (candidate_user_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$transfers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            from_scope_type varchar(40) NOT NULL DEFAULT 'site',
            from_scope_id bigint(20) unsigned NOT NULL DEFAULT 0,
            to_scope_type varchar(40) NOT NULL DEFAULT 'site',
            to_scope_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(30) NOT NULL DEFAULT 'pending',
            preserve_personal_records tinyint(1) NOT NULL DEFAULT 1,
            initiated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            completed_by bigint(20) unsigned NOT NULL DEFAULT 0,
            context_json longtext NULL,
            created_at datetime NOT NULL,
            completed_at datetime NULL,
            PRIMARY KEY  (id),
            KEY user_status (user_id,status),
            KEY from_scope (from_scope_type,from_scope_id),
            KEY to_scope (to_scope_type,to_scope_id)
        ) {$charset};");

        // Core 29.0.0 formatted scope_type as an integer while inserting the
        // seeded WordPress administrator assignment. Repair only that known
        // malformed site-level record, preserving every other assignment.
        $malformed = $wpdb->get_results($wpdb->prepare(
            "SELECT id, user_id, role_key FROM {$roles} WHERE scope_type = %s AND scope_id = 0 AND source = %s",
            '0',
            'wordpress'
        ), ARRAY_A);
        foreach ($malformed as $assignment) {
            $correct_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$roles} WHERE user_id = %d AND role_key = %s AND scope_type = %s AND scope_id = 0",
                (int) $assignment['user_id'],
                $assignment['role_key'],
                'site'
            ));
            if ($correct_id) {
                $wpdb->delete($roles, ['id' => (int) $assignment['id']], ['%d']);
            } else {
                $wpdb->update($roles, ['scope_type' => 'site'], ['id' => (int) $assignment['id']], ['%s'], ['%d']);
            }
        }

        update_option('mathbinder_schema_version', self::VERSION, false);
    }
}
