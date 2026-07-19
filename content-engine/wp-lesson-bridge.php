<?php

require_once __DIR__ . '/lesson-schema.php';

if (!class_exists('MathBinder_WP_Lesson_Bridge')) {
    class MathBinder_WP_Lesson_Bridge {

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

                // Bridge is inactive or key is not in the ownership list.
                // Go directly to WordPress post meta without touching the lesson file.
                if ( $owned_fields === null || ! array_key_exists( $key, $owned_fields ) ) {
                    return get_post_meta( $post_id, '_mb_' . $key, true );
                }

                // Key is owned. Resolve the corresponding lesson schema key.
                $lesson_key = $owned_fields[ $key ];
                if ( ! is_string( $lesson_key ) || $lesson_key === '' ) {
                    return get_post_meta( $post_id, '_mb_' . $key, true );
                }

                // Look up the value in the normalized lesson array.
                $value = array_key_exists( $lesson_key, $normalized )
                    ? $normalized[ $lesson_key ]
                    : null;

                // Only serve the lesson value when it is a non-empty,
                // non-whitespace string. Empty strings, whitespace-only strings,
                // missing keys, and non-string values (arrays, null) all fall
                // through to WordPress post meta.
                if ( is_string( $value ) && trim( $value ) !== '' ) {
                    return $value;
                }

                return get_post_meta( $post_id, '_mb_' . $key, true );
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
