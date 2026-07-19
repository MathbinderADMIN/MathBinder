<?php

require_once __DIR__ . '/lesson-schema.php';

if (!class_exists('MathBinder_WP_Lesson_Bridge')) {
    class MathBinder_WP_Lesson_Bridge {

        // Diagnostic source constants.
        const SOURCE_LESSON_FILE = 'lesson_file';
        const SOURCE_WORDPRESS = 'wordpress';
        const SOURCE_WORDPRESS_FALLBACK = 'wordpress_fallback';

        // Diagnostic reason-code constants.
        const LESSON_OWNED_STRING_USED = 'lesson_owned_string_used';
        const WP_BRIDGE_INACTIVE = 'wp_bridge_inactive';
        const WP_KEY_NOT_OWNED = 'wp_key_not_owned';
        const WP_FALLBACK_EMPTY_OR_INVALID_MAPPING = 'wp_fallback_empty_or_invalid_mapping';
        const WP_FALLBACK_MISSING_LESSON_KEY = 'wp_fallback_missing_lesson_key';
        const WP_FALLBACK_NON_STRING_VALUE = 'wp_fallback_non_string_value';
        const WP_FALLBACK_EMPTY_STRING_VALUE = 'wp_fallback_empty_string_value';

        // Request-scoped diagnostics storage (in-memory only).
        protected static $request_diagnostics = array();

        /**
         * Determines whether diagnostics are enabled for the current request.
         *
         * Diagnostics are enabled only when both conditions are true:
         *   - The current user can manage options.
         *   - Developer mode is explicitly enabled by constant or filter.
         *
         * This method fails safely when required WordPress functions are
         * unavailable and returns false.
         *
         * @return bool
         */
        public static function is_diagnostics_enabled_for_current_request() {
            if ( ! function_exists( 'current_user_can' ) ) {
                return false;
            }

            if ( ! current_user_can( 'manage_options' ) ) {
                return false;
            }

            $constant_enabled = defined( 'MATHBINDER_BRIDGE_DEBUG' ) && MATHBINDER_BRIDGE_DEBUG === true;
            $filter_enabled   = false;

            if ( function_exists( 'apply_filters' ) ) {
                $filter_enabled = apply_filters( 'mathbinder_bridge_debug_enabled', false ) === true;
            }

            return $constant_enabled || $filter_enabled;
        }

        /**
         * Records a diagnostics event for a bridge field lookup.
         *
         * The record is request-scoped and keyed by post ID and template key.
         * Repeated lookups for the same post ID and template key update the
         * stored record and increment lookup_count.
         *
         * @param int         $post_id
         * @param string      $template_key
         * @param string|null $lesson_key
         * @param string      $source
         * @param string      $reason
         * @return void
         */
        protected static function record_diagnostic( $post_id, $template_key, $lesson_key, $source, $reason ) {
            if ( ! self::is_diagnostics_enabled_for_current_request() ) {
                return;
            }

            $post_id = (int) $post_id;

            if ( ! isset( self::$request_diagnostics[ $post_id ] ) || ! is_array( self::$request_diagnostics[ $post_id ] ) ) {
                self::$request_diagnostics[ $post_id ] = array();
            }

            $existing_lookup_count = 0;
            if ( isset( self::$request_diagnostics[ $post_id ][ $template_key ]['lookup_count'] ) ) {
                $existing_lookup_count = (int) self::$request_diagnostics[ $post_id ][ $template_key ]['lookup_count'];
            }

            self::$request_diagnostics[ $post_id ][ $template_key ] = array(
                'post_id'      => $post_id,
                'template_key' => $template_key,
                'lesson_key'   => $lesson_key !== null ? (string) $lesson_key : null,
                'source'       => (string) $source,
                'reason'       => (string) $reason,
                'timestamp'    => microtime( true ),
                'lookup_count' => $existing_lookup_count + 1,
            );
        }

        /**
         * Returns request-scoped diagnostics for the current request.
         *
         * Returns an empty array when diagnostics are disabled.
         *
         * @return array
         */
        public static function get_diagnostics_for_request() {
            if ( ! self::is_diagnostics_enabled_for_current_request() ) {
                return array();
            }

            return self::$request_diagnostics;
        }

        /**
         * Clears request-scoped diagnostics for the current request.
         *
         * @return bool True when cleared, false when diagnostics are disabled.
         */
        public static function clear_diagnostics_for_request() {
            if ( ! self::is_diagnostics_enabled_for_current_request() ) {
                return false;
            }

            self::$request_diagnostics = array();
            return true;
        }

        /**
         * Returns the $meta callable for single-mb_binder_page.php.
         *
         * Only fields explicitly declared in the lesson file's
         * wordpress_bridge.owned_fields map are candidates for lesson-file
         * resolution. Every other field bypasses the lesson file entirely and
         * goes directly to get_post_meta().
         *
         * This method is read-only. It never writes to WordPress post meta.
         *
         * @param  int      $post_id  WordPress post ID.
         * @return callable  function( string $key ): mixed
         */
        public static function meta( $post_id ) {
            $load_result  = self::load( $post_id );
            $owned_fields = $load_result[0];
            $normalized   = $load_result[1];

            return function ( $key ) use ( $post_id, $owned_fields, $normalized ) {

                // Bridge is inactive. Go directly to WordPress post meta.
                if ( $owned_fields === null ) {
                    self::record_diagnostic(
                        $post_id,
                        $key,
                        null,
                        self::SOURCE_WORDPRESS,
                        self::WP_BRIDGE_INACTIVE
                    );
                    return get_post_meta( $post_id, '_mb_' . $key, true );
                }

                // Bridge is active, but key is not owned by the lesson file.
                if ( ! array_key_exists( $key, $owned_fields ) ) {
                    self::record_diagnostic(
                        $post_id,
                        $key,
                        null,
                        self::SOURCE_WORDPRESS,
                        self::WP_KEY_NOT_OWNED
                    );
                    return get_post_meta( $post_id, '_mb_' . $key, true );
                }

                // Key is owned. Resolve the corresponding lesson schema key.
                $lesson_key = $owned_fields[ $key ];
                if ( ! is_string( $lesson_key ) || $lesson_key === '' ) {
                    $diagnostic_lesson_key = null;
                    if ( is_string( $lesson_key ) ) {
                        $diagnostic_lesson_key = $lesson_key;
                    } elseif ( is_int( $lesson_key ) || is_float( $lesson_key ) || is_bool( $lesson_key ) ) {
                        $diagnostic_lesson_key = (string) $lesson_key;
                    }

                    self::record_diagnostic(
                        $post_id,
                        $key,
                        $diagnostic_lesson_key,
                        self::SOURCE_WORDPRESS_FALLBACK,
                        self::WP_FALLBACK_EMPTY_OR_INVALID_MAPPING
                    );
                    return get_post_meta( $post_id, '_mb_' . $key, true );
                }

                if ( ! array_key_exists( $lesson_key, $normalized ) ) {
                    self::record_diagnostic(
                        $post_id,
                        $key,
                        $lesson_key,
                        self::SOURCE_WORDPRESS_FALLBACK,
                        self::WP_FALLBACK_MISSING_LESSON_KEY
                    );
                    return get_post_meta( $post_id, '_mb_' . $key, true );
                }

                // Look up the value in the normalized lesson array.
                $value = $normalized[ $lesson_key ];

                if ( ! is_string( $value ) ) {
                    self::record_diagnostic(
                        $post_id,
                        $key,
                        $lesson_key,
                        self::SOURCE_WORDPRESS_FALLBACK,
                        self::WP_FALLBACK_NON_STRING_VALUE
                    );
                    return get_post_meta( $post_id, '_mb_' . $key, true );
                }

                // Only serve the lesson value when it is a non-empty,
                // non-whitespace string. Empty strings, whitespace-only strings,
                // missing keys, and non-string values (arrays, null) all fall
                // through to WordPress post meta.
                if ( trim( $value ) === '' ) {
                    self::record_diagnostic(
                        $post_id,
                        $key,
                        $lesson_key,
                        self::SOURCE_WORDPRESS_FALLBACK,
                        self::WP_FALLBACK_EMPTY_STRING_VALUE
                    );
                    return get_post_meta( $post_id, '_mb_' . $key, true );
                }

                self::record_diagnostic(
                    $post_id,
                    $key,
                    $lesson_key,
                    self::SOURCE_LESSON_FILE,
                    self::LESSON_OWNED_STRING_USED
                );

                return $value;

            };
        }

        /**
         * Loads and normalizes lesson data for the given post.
         *
         * Returns array( null, null ) whenever the bridge should be inactive,
         * which makes every subsequent $meta() call fall through transparently
         * to get_post_meta().
         *
         * The bridge is inactive when any of the following are true:
         *   - The content engine is not available.
         *   - The post does not exist or is not a WP_Post instance.
         *   - No lesson file matches the post slug.
         *   - The lesson file has no 'wordpress_bridge' key.
         *   - wordpress_bridge['enabled'] is absent or not truthy.
         *   - wordpress_bridge['owned_fields'] is absent, not an array, or empty.
         *
         * When the bridge is active, the raw lesson array is passed through
         * MathBinder_Lesson_Schema::from_array() exactly once and to_array()
         * is called exactly once. The resulting normalized array is returned for
         * all subsequent closure lookups. The schema object is not retained.
         *
         * @param  int    $post_id
         * @return array  Two-element array: array( owned_fields|null, normalized|null )
         */
        private static function load( $post_id ) {
            $inactive = array( null, null );

            if ( ! function_exists( 'mathbinder_content_engine' ) ) {
                return $inactive;
            }

            $post = get_post( $post_id );
            if ( ! ( $post instanceof WP_Post ) ) {
                return $inactive;
            }

            $lesson = mathbinder_content_engine()->get_lesson( $post->post_name );
            if ( $lesson === null || ! isset( $lesson['data'] ) || ! is_array( $lesson['data'] ) ) {
                return $inactive;
            }

            $raw = $lesson['data'];

            if ( empty( $raw['wordpress_bridge'] ) || ! is_array( $raw['wordpress_bridge'] ) ) {
                return $inactive;
            }

            $bridge_config = $raw['wordpress_bridge'];

            if ( empty( $bridge_config['enabled'] ) ) {
                return $inactive;
            }

            if ( empty( $bridge_config['owned_fields'] ) || ! is_array( $bridge_config['owned_fields'] ) ) {
                return $inactive;
            }

            $owned_fields = $bridge_config['owned_fields'];

            $schema     = MathBinder_Lesson_Schema::from_array( $raw );
            $normalized = $schema->to_array();

            return array( $owned_fields, $normalized );
        }
    }
}
