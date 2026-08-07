<?php
if (!defined('ABSPATH')) exit;

/**
 * Phase 1 services are booted separately from the legacy lesson engine so the
 * existing Binder Page behavior can be preserved while Core becomes modular.
 */
function mathbinder_foundation_bootstrap() {
    MathBinder_Capabilities::register();
    MathBinder_Student_Dashboard::register();
    MathBinder_Teacher_Dashboard::register();
    MathBinder_Identity_Service::register();
    MathBinder_Verification_Service::register();
    MathBinder_Account_Workspace::register();
    MathBinder_Family_Account::register();
    MathBinder_Frontend_Auth::register();
    MathBinder_Identity_Admin::register();
    MathBinder_Organization_Admin::register();
    MathBinder_Stripe_Settings::register();
    MathBinder_Family_Checkout::register();
    MathBinder_Canvas_Integration::register();
    MathBinder_REST_Controller::register();
}
add_action('plugins_loaded', 'mathbinder_foundation_bootstrap');

function mathbinder_foundation_activate() {
    MathBinder_Migrations::run();
    MathBinder_Capabilities::install();
    MathBinder_Identity_Service::install();
    MathBinder_Student_Dashboard::ensure_page();
    MathBinder_Teacher_Dashboard::ensure_page();
    MathBinder_Account_Workspace::ensure_page();
    MathBinder_Frontend_Auth::ensure_page();
    MathBinder_Family_Checkout::ensure_page();
    MathBinder_Organization_Migrations::run();
}
register_activation_hook(dirname(__DIR__) . '/mathbinder-core.php', 'mathbinder_foundation_activate');

function mathbinder_foundation_upgrade() {
    MathBinder_Student_Dashboard::ensure_page();
    MathBinder_Teacher_Dashboard::ensure_page();
    MathBinder_Account_Workspace::ensure_page();
    MathBinder_Frontend_Auth::ensure_page();
    MathBinder_Family_Checkout::ensure_page();
    if (version_compare((string) get_option('mathbinder_schema_version', '0'), MathBinder_Migrations::VERSION, '<')) {
        MathBinder_Migrations::run();
        MathBinder_Capabilities::install();
        MathBinder_Identity_Service::install();
        MathBinder_Account_Workspace::ensure_page();
    }
    if (version_compare((string) get_option('mathbinder_organization_schema_version', '0'), MathBinder_Organization_Migrations::VERSION, '<')) {
        MathBinder_Organization_Migrations::run();
        MathBinder_Capabilities::install();
    }
}
add_action('admin_init', 'mathbinder_foundation_upgrade');
