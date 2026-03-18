<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration class for WANotifier plugin v3
 *
 * Migration flow (batch — all slugs in one redirect):
 *
 * 1. Migration page shows a list of legacy triggers grouped by integration slug.
 * 2. A single "Connect & Migrate" button redirects the user's browser to the SaaS
 *    batch migration page at /integrations/migrate/, passing all slugs, their connect
 *    tokens, the old API key, and a return_url.
 * 3. SaaS: shows a single authorization page listing all integrations. The WA account
 *    is resolved automatically from the old API key — no account selector needed.
 * 4. User clicks "Connect & Migrate All". SaaS loops over each slug:
 *    - Creates a connection row (save_connection).
 *    - Calls the plugin's /wp-json/notifier/v1/{slug}/connect to deliver credentials.
 *    - Fetches legacy trigger definitions from /wp-json/notifier/v1/legacy-triggers?slug={slug}.
 *    - Runs process_migrate_trigger() for each trigger, which saves the new trigger config
 *      and updates the corresponding Notifications' trigger references from the old format
 *      (e.g. wp_abc12_woo_order_new) to the new format ({connection_id}:{trigger_slug}).
 * 5. SaaS redirects back to the plugin's migration page (return_url).
 * 6. Plugin detects all slugs are connected and shows "Finalize Migration" button.
 * 7. User clicks "Finalize Migration" (AJAX):
 *    - Calls /api/v1/disconnect (old API key) to remove legacy trigger schema from SaaS.
 *    - Permanently deletes all wa_notifier_trigger CPT posts.
 *    - Marks migration complete.
 *
 * Migration state is tracked via the `notifier_migration_status` option:
 *   - '' / not set : migration not started
 *   - 'complete'   : migration done
 *
 * @package    Notifier
 */
class Notifier_Migration {

    const STATUS_OPTION = 'notifier_migration_status';

    /**
     * Integration slugs that belong to the standalone WANotifier for WooCommerce plugin.
     */
    const WOO_SLUGS = array( 'woocommerce', 'wcar' );

    // ========================================
    // 1. INITIALIZATION
    // ========================================

    public static function init() {
        add_action( 'admin_notices', array( __CLASS__, 'show_migration_notice' ) );
        add_action( 'wp_ajax_notifier_dismiss_migration_notice', array( __CLASS__, 'ajax_dismiss_notice' ) );
        add_action( 'current_screen', array( __CLASS__, 'maybe_capture_used_meta' ) );
    }

    // ========================================
    // 2. MIGRATION STATUS HELPERS
    // ========================================

    public static function is_migration_complete() {
        return 'complete' === get_option( self::STATUS_OPTION, '' );
    }

    public static function is_migration_dismissed() {
        return 'dismissed' === get_option( 'notifier_migration_notice_dismissed', '' );
    }

    /**
     * Check whether there are any legacy CPT triggers to migrate.
     *
     * @return bool
     */
    public static function has_legacy_triggers() {
        $count = wp_count_posts( 'wa_notifier_trigger' );
        return ! empty( $count->publish ) && (int) $count->publish > 0;
    }

    /**
     * Get all unique integration slugs that have legacy triggers.
     *
     * @return string[]
     */
    public static function get_slugs_with_legacy_triggers() {
        $posts = get_posts( array(
            'post_type'   => 'wa_notifier_trigger',
            'post_status' => 'publish',
            'numberposts' => -1,
        ) );

        $slugs = array();
        foreach ( $posts as $post ) {
            $trigger_id   = get_post_meta( $post->ID, NOTIFIER_PREFIX . 'trigger', true );
            $base_trigger = Notifier_Notification_Triggers::get_trigger_id_without_site_key( $trigger_id );
            $slug         = Notifier_Notification_Triggers::get_integration_slug_for_trigger( $base_trigger );
            if ( $slug && ! in_array( $slug, $slugs, true ) ) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * Get only non-Woo/WCAR slugs that have legacy triggers.
     *
     * @return string[]
     */
    public static function get_non_woo_slugs_with_legacy_triggers() {
        return array_values( array_diff( self::get_slugs_with_legacy_triggers(), self::WOO_SLUGS ) );
    }

    /**
     * Determine the trigger scenario for migration UI.
     *
     * @return string 'woo_only' | 'mixed' | 'non_woo_only'
     */
    public static function get_trigger_scenario() {
        $all_slugs     = self::get_slugs_with_legacy_triggers();
        $non_woo_slugs = array_diff( $all_slugs, self::WOO_SLUGS );
        $woo_slugs     = array_intersect( $all_slugs, self::WOO_SLUGS );

        if ( ! empty( $woo_slugs ) && empty( $non_woo_slugs ) ) {
            return 'woo_only';
        }

        if ( ! empty( $woo_slugs ) && ! empty( $non_woo_slugs ) ) {
            return 'mixed';
        }

        return 'non_woo_only';
    }

    /**
     * Check if all non-Woo slugs with legacy triggers are now connected.
     *
     * @return bool
     */
    public static function all_non_woo_slugs_connected() {
        $slugs = self::get_non_woo_slugs_with_legacy_triggers();
        if ( empty( $slugs ) ) {
            return true;
        }
        foreach ( $slugs as $slug ) {
            if ( ! Notifier_Connection::is_connected( $slug ) ) {
                return false;
            }
        }
        return true;
    }

    // ========================================
    // 3. CONNECT URL FOR MIGRATION
    // ========================================

    /**
     * Build the SaaS batch migration URL for all slugs with legacy triggers.
     *
     * Redirects to /integrations/migrate/ with all slugs and a base64-encoded
     * payload containing the connect tokens, old API key, and return URL.
     * Base64 encoding keeps the URL clean and avoids query-string escaping issues
     * with JSON and special characters in URLs.
     *
     * @return string
     */
    public static function get_migrate_connect_url() {
        $slugs   = self::get_non_woo_slugs_with_legacy_triggers();
        $api_key = trim( get_option( 'notifier_api_key', '' ) );

        $connect_tokens = array();
        foreach ( $slugs as $slug ) {
            $connect_tokens[ $slug ] = Notifier_Connection::get_or_create_connect_token( $slug );
        }

        $return_url = admin_url( 'admin.php?page=notifier-migration' );
        $saas_base  = untrailingslashit( NOTIFIER_APP_BASE_URL );

        $payload = base64_encode( wp_json_encode( array(
            'store_url'      => site_url(),
            'slugs'          => $slugs,
            'connect_tokens' => $connect_tokens,
            'return_url'     => $return_url,
            'api_key'        => $api_key,
        ) ) );

        return $saas_base . '/integrations/migrate/?payload=' . rawurlencode( $payload );
    }

    // ========================================
    // 4. ADMIN NOTICE
    // ========================================

    public static function show_migration_notice() {
        if ( self::is_migration_complete() ) {
            return;
        }

        if ( self::is_migration_dismissed() ) {
            return;
        }

        if ( ! self::has_legacy_triggers() ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Don't show on the migration page itself.
        $screen = get_current_screen();
        if ( $screen && false !== strpos( $screen->id, 'notifier-migration' ) ) {
            return;
        }

        $migration_url = admin_url( 'admin.php?page=notifier-migration' );
        ?>
        <div class="notice notice-warning is-dismissible" id="notifier-migration-notice">
            <p>
                <strong><?php esc_html_e( 'WANotifier v3: Action Required', 'notifier' ); ?></strong>
                &mdash;
                <?php esc_html_e( 'Your existing triggers need to be migrated to the new integration system. They will continue to work until you migrate.', 'notifier' ); ?>
                <a href="<?php echo esc_url( $migration_url ); ?>" class="button button-primary" style="margin-left:8px;">
                    <?php esc_html_e( 'Migrate Now', 'notifier' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public static function ajax_dismiss_notice() {
        check_ajax_referer( 'notifier_dismiss_migration', 'nonce' );
        update_option( 'notifier_migration_notice_dismissed', 'dismissed' );
        wp_send_json_success();
    }

    // ========================================
    // 5. POST-MIGRATION CLEANUP
    // ========================================

    /**
     * Delete legacy trigger CPT posts, skipping Woo/WCAR triggers
     * (those are handled by the standalone plugin's own migration).
     */
    public static function delete_legacy_trigger_posts() {
        $posts = get_posts( array(
            'post_type'   => 'wa_notifier_trigger',
            'post_status' => array( 'publish', 'draft', 'trash' ),
            'numberposts' => -1,
        ) );

        foreach ( $posts as $post ) {
            $trigger_id   = get_post_meta( $post->ID, NOTIFIER_PREFIX . 'trigger', true );
            $base_trigger = Notifier_Notification_Triggers::get_trigger_id_without_site_key( $trigger_id );
            $slug         = Notifier_Notification_Triggers::get_integration_slug_for_trigger( $base_trigger );

            if ( in_array( $slug, self::WOO_SLUGS, true ) ) {
                continue;
            }

            wp_delete_post( $post->ID, true );
        }
    }

    /**
     * Remove the site's legacy trigger schema from the SaaS.
     *
     * The legacy system stored all triggers for a site under source='wp', so one
     * call removes the entire site entry from wa_notifier_api_sources_and_triggers_*
     * user meta.
     *
     * @param string $api_key Old WANotifier API key.
     */
    public static function disconnect_legacy_on_saas( $api_key ) {
        $saas_url = untrailingslashit( NOTIFIER_APP_BASE_URL );

        $response = wp_remote_post( $saas_url . '/' . NOTIFIER_APP_API_PATH . '/disconnect?key=' . rawurlencode( $api_key ), array(
            'timeout'   => 15,
            'headers'   => array( 'Content-Type' => 'application/json' ),
            'body'      => wp_json_encode( array(
                'site_url' => site_url(),
                'source'   => 'wp',
            ) ),
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'WANotifier migration: disconnect_legacy_on_saas failed: ' . $response->get_error_message() );
        }
    }

    // ========================================
    // 6. META FIELD SELECTION CAPTURE
    // ========================================

    /**
     * On page load, check for ?used_meta= query param and store the decoded
     * merge tags and recipient fields in options for the WordPress meta field selection.
     *
     * The SaaS redirects back with a base64-encoded JSON payload in the used_meta
     * query parameter containing the merge tags and recipient fields used in Notifications.
     */
    public static function maybe_capture_used_meta() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || false === strpos( $screen->id, 'notifier' ) ) {
            return;
        }

        if ( ! isset( $_GET['used_meta'] ) ) {
            return;
        }

        $raw     = wp_unslash( $_GET['used_meta'] );
        $decoded = json_decode( base64_decode( $raw ), true );

        if ( ! is_array( $decoded ) ) {
            return;
        }

        // Include WP meta (post_meta_*, user_meta_*) and hook-added fields; exclude Woo meta.
        $wp_pattern   = '/^((?!woo_).+_meta_|user_meta_)/';
        $is_wp_field  = function( $id ) use ( $wp_pattern ) {
            $id = is_string( $id ) ? $id : '';
            return $id !== '' && ( preg_match( $wp_pattern, $id ) || strpos( $id, 'woo_' ) !== 0 );
        };

        if ( isset( $decoded['merge_tags'] ) && is_array( $decoded['merge_tags'] ) ) {
            $wp_tags = array_values( array_filter( $decoded['merge_tags'], $is_wp_field ) );
            if ( ! empty( $wp_tags ) ) {
                update_option( 'notifier_wp_enabled_merge_tags', array_map( 'sanitize_text_field', $wp_tags ) );
            }
        }

        if ( isset( $decoded['recipient_fields'] ) && is_array( $decoded['recipient_fields'] ) ) {
            $wp_fields = array_values( array_filter( $decoded['recipient_fields'], $is_wp_field ) );
            if ( ! empty( $wp_fields ) ) {
                update_option( 'notifier_wp_enabled_recipient_fields', array_map( 'sanitize_text_field', $wp_fields ) );
            }
        }
    }

}
