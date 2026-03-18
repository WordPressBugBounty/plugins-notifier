<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backend class for WANotifier plugin v3
 *
 * Consolidates admin menu registration, settings management, REST API endpoints,
 * and the React shell view. Replaces the former Dashboard, Settings, Tools, and
 * WooCommerce Configure classes.
 *
 * @package Notifier
 */
class Notifier_Backend {

    // ========================================
    // 1. INITIALIZATION
    // ========================================

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
        add_action( 'admin_menu', array( __CLASS__, 'manage_menu_visibility' ), 999 );
        add_action( 'admin_init', array( __CLASS__, 'handle_disconnect_action' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
        add_filter( 'plugin_action_links_notifier/notifier.php', array( __CLASS__, 'add_plugin_action_links' ) );
    }

    public static function add_plugin_action_links( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=notifier' ) ) . '">' . __( 'Settings', 'notifier' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    // ========================================
    // 2. MENU REGISTRATION
    // ========================================

    public static function register_menus() {
        add_menu_page(
            'WANotifier',
            'WANotifier',
            'manage_options',
            NOTIFIER_NAME,
            array( __CLASS__, 'render_react_app' ),
            'data:image/svg+xml;base64,' . base64_encode( file_get_contents( NOTIFIER_PATH . 'assets/images/menu-icon.svg' ) ),
            '51'
        );

        add_submenu_page(
            NOTIFIER_NAME,
            __( 'Integrations', 'notifier' ),
            __( 'Integrations', 'notifier' ),
            'manage_options',
            NOTIFIER_NAME,
            array( __CLASS__, 'render_react_app' )
        );

        add_submenu_page(
            NOTIFIER_NAME,
            __( 'WhatsApp Chat Button', 'notifier' ),
            __( 'Chat Button', 'notifier' ),
            'manage_options',
            'notifier-chat-button',
            array( __CLASS__, 'render_react_app' )
        );

        add_submenu_page(
            NOTIFIER_NAME,
            __( 'Settings', 'notifier' ),
            __( 'Settings', 'notifier' ),
            'manage_options',
            'notifier-settings',
            array( __CLASS__, 'render_react_app' )
        );

        add_submenu_page(
            NOTIFIER_NAME,
            __( 'Logs', 'notifier' ),
            __( 'Logs', 'notifier' ),
            'manage_options',
            'notifier-logs',
            array( __CLASS__, 'render_react_app' )
        );

        add_submenu_page(
            NOTIFIER_NAME,
            __( 'Migrate to v3', 'notifier' ),
            __( 'Migrate to v3', 'notifier' ),
            'manage_options',
            'notifier-migration',
            array( __CLASS__, 'render_react_app' )
        );
    }

    /**
     * Manage submenu visibility based on application state.
     */
    public static function manage_menu_visibility() {
        global $submenu;

        if ( empty( $submenu[ NOTIFIER_NAME ] ) ) {
            return;
        }

        $migration_complete = Notifier_Migration::is_migration_complete();
        $has_legacy         = Notifier_Migration::has_legacy_triggers();

        if ( ! $migration_complete && $has_legacy ) {
            // Migration pending: show only "Migrate to v3".
            foreach ( $submenu[ NOTIFIER_NAME ] as $key => $item ) {
                if ( isset( $item[2] ) && 'notifier-migration' !== $item[2] ) {
                    unset( $submenu[ NOTIFIER_NAME ][ $key ] );
                }
            }
        } else {
            // Migration complete or no legacy triggers: hide migration page.
            foreach ( $submenu[ NOTIFIER_NAME ] as $key => $item ) {
                if ( isset( $item[2] ) && 'notifier-migration' === $item[2] ) {
                    unset( $submenu[ NOTIFIER_NAME ][ $key ] );
                }
            }
        }

        // Always hide legacy trigger CPT menu items.
        foreach ( $submenu[ NOTIFIER_NAME ] as $key => $item ) {
            if ( isset( $item[2] ) && (
                'edit.php?post_type=wa_notifier_trigger' === $item[2] ||
                'post-new.php?post_type=wa_notifier_trigger' === $item[2]
            ) ) {
                unset( $submenu[ NOTIFIER_NAME ][ $key ] );
            }
        }
    }

    // ========================================
    // 3. REACT SHELL VIEW
    // ========================================

    public static function render_react_app() {
        include_once NOTIFIER_PATH . 'views/admin.php';
    }

    // ========================================
    // 4. DISCONNECT HANDLER
    // ========================================

    public static function handle_disconnect_action() {
        if ( ! isset( $_GET['notifier_disconnect'] ) || ! isset( $_GET['notifier_slug'] ) ) {
            return;
        }

        if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'notifier_disconnect_' . sanitize_key( $_GET['notifier_slug'] ) ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $slug = sanitize_key( $_GET['notifier_slug'] );
        Notifier_Connection::disconnect( $slug );

        wp_safe_redirect( admin_url( 'admin.php?page=notifier&disconnected=' . $slug ) );
        exit;
    }

    // ========================================
    // 5. REST API ROUTES
    // ========================================

    public static function register_rest_routes() {
        $namespace = 'notifier/v1';

        register_rest_route( $namespace, '/admin/integrations', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'rest_get_integrations' ),
            'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
        ) );

        register_rest_route( $namespace, '/admin/settings', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'rest_get_settings' ),
                'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'rest_save_settings' ),
                'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
            ),
        ) );

        register_rest_route( $namespace, '/admin/sync-triggers', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'rest_sync_triggers' ),
            'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
        ) );

        register_rest_route( $namespace, '/admin/preview-btn-style', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'rest_preview_btn_style' ),
            'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
        ) );

        register_rest_route( $namespace, '/admin/logs/dates', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'rest_get_log_dates' ),
            'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
        ) );

        register_rest_route( $namespace, '/admin/logs', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'rest_get_logs' ),
                'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( __CLASS__, 'rest_delete_logs' ),
                'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
            ),
        ) );

        register_rest_route( $namespace, '/admin/migration-status', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'rest_get_migration_status' ),
            'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
        ) );

        register_rest_route( $namespace, '/admin/finalize-migration', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'rest_finalize_migration' ),
            'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
        ) );

        register_rest_route( $namespace, '/admin/disconnect', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'rest_disconnect' ),
            'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
        ) );

        register_rest_route( $namespace, '/admin/wp-available-meta-fields', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'rest_get_wp_available_meta_fields' ),
            'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
        ) );
        register_rest_route( $namespace, '/admin/sync-meta-fields', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'rest_sync_meta_fields' ),
            'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
        ) );
    }

    public static function rest_permission_check() {
        return current_user_can( 'manage_options' );
    }

    // ========================================
    // 6. REST HANDLERS — INTEGRATIONS
    // ========================================

    public static function rest_get_integrations() {
        $integrations = self::get_integrations();
        $result       = array();

        foreach ( $integrations as $slug => $integration ) {
            $no_connect   = ! empty( $integration['no_connect'] );
            $is_connected = ! $no_connect && Notifier_Connection::is_connected( $slug );
            $connect_url  = ! $no_connect ? Notifier_Connection::get_connect_url( $slug ) : '';

            $disconnect_url = wp_nonce_url(
                admin_url( 'admin.php?page=notifier&notifier_disconnect=1&notifier_slug=' . $slug ),
                'notifier_disconnect_' . $slug
            );

            $conn          = $is_connected ? Notifier_Connection::get_connection_info( $slug ) : array();
            $connected_at  = ! empty( $conn['connected_at'] ) ? $conn['connected_at'] : '';

            $result[ $slug ] = array_merge( $integration, array(
                'slug'            => $slug,
                'is_connected'    => $is_connected,
                'connect_url'     => $connect_url,
                'disconnect_url'  => $disconnect_url,
                'connected_at'    => $connected_at,
            ) );
        }

        $result['_meta'] = array(
            'has_woo_legacy_triggers' => ! Notifier_Migration::is_migration_complete() && in_array( Notifier_Migration::get_trigger_scenario(), array( 'woo_only', 'mixed' ), true ),
            'is_woo_active'           => class_exists( 'WooCommerce' ),
            'is_wanowc_active' => Notifier::is_woo_handled_by_standalone_plugin(),
        );

        return new WP_REST_Response( $result, 200 );
    }

    // ========================================
    // 7. REST HANDLERS — SETTINGS
    // ========================================

    public static function rest_get_settings() {
        return new WP_REST_Response( self::get_all_settings(), 200 );
    }

    public static function rest_save_settings( WP_REST_Request $request ) {
        $data    = $request->get_json_params();
        $errors  = array();
        $allowed = self::get_settings_field_definitions();

        foreach ( $data as $key => $value ) {
            if ( ! isset( $allowed[ $key ] ) ) {
                continue;
            }

            $def         = $allowed[ $key ];
            $option_name = NOTIFIER_PREFIX . $key;

            switch ( $def['type'] ) {
                case 'checkbox':
                    $value = ( 'yes' === $value || '1' === $value || true === $value ) ? 'yes' : 'no';
                    break;
                case 'number':
                    $value = absint( $value );
                    if ( isset( $def['min'] ) ) {
                        $value = max( $def['min'], $value );
                    }
                    break;
                case 'select':
                    if ( ! empty( $def['options'] ) && ! array_key_exists( $value, $def['options'] ) ) {
                        $value = $def['default'] ?? '';
                    }
                    break;
                case 'textarea':
                    $value = wp_kses_post( trim( $value ) );
                    break;
                case 'array':
                    $value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
                    break;
                default:
                    $value = sanitize_text_field( $value );
                    break;
            }

            if ( 'ctc_whatsapp_number' === $key && ! empty( $value ) && function_exists( 'notifier_validate_phone_number' ) && ! notifier_validate_phone_number( $value ) ) {
                $errors[] = __( 'Please enter a valid WhatsApp phone number with country code.', 'notifier' );
                continue;
            }

            update_option( $option_name, $value, 'yes' );
        }

        if ( ! empty( $errors ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'errors'  => $errors,
            ), 200 );
        }

        return new WP_REST_Response( array(
            'success'  => true,
            'message'  => __( 'Settings saved.', 'notifier' ),
            'settings' => self::get_all_settings(),
        ), 200 );
    }

    // ========================================
    // 8. REST HANDLERS — SYNC TRIGGERS
    // ========================================

    public static function rest_sync_triggers( WP_REST_Request $request ) {
        $slug = sanitize_key( $request->get_param( 'slug' ) );

        if ( empty( $slug ) || ! Notifier_Connection::is_connected( $slug ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Integration not connected.', 'notifier' ),
            ), 400 );
        }

        $trigger_schema = Notifier_Connection::build_trigger_schema( $slug );
        $result = Notifier_Connection::send_saas_request( $slug, 'sync', array( 'triggers' => $trigger_schema ) );

        return new WP_REST_Response( array(
            'success' => ( false !== $result ),
            'message' => ( false !== $result ) ? __( 'Triggers synced successfully.', 'notifier' ) : __( 'Failed to sync with SaaS.', 'notifier' ),
        ), 200 );
    }

    public static function rest_disconnect( WP_REST_Request $request ) {
        $data = $request->get_json_params();
        $slug = isset( $data['slug'] ) ? sanitize_key( $data['slug'] ) : '';

        if ( empty( $slug ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Invalid slug.', 'notifier' ) ), 400 );
        }

        Notifier_Connection::disconnect( $slug );

        return new WP_REST_Response( array( 'success' => true, 'message' => __( 'Disconnected successfully.', 'notifier' ) ), 200 );
    }

    // ========================================
    // 9. REST HANDLERS — CHAT BUTTON PREVIEW
    // ========================================

    public static function rest_preview_btn_style( WP_REST_Request $request ) {
        $btn_style    = sanitize_text_field( $request->get_param( 'btn_style' ) );
        $valid_styles = array_keys( self::get_button_styles() );

        if ( ! in_array( $btn_style, $valid_styles, true ) || 'default' === $btn_style ) {
            return new WP_REST_Response( array( 'preview' => '' ), 200 );
        }

        $template = NOTIFIER_PATH . 'templates/buttons/' . $btn_style . '.php';
        if ( ! file_exists( $template ) ) {
            return new WP_REST_Response( array( 'preview' => '' ), 200 );
        }

        ob_start();
        include $template;
        $html = ob_get_clean();

        return new WP_REST_Response( array( 'preview' => $html ), 200 );
    }

    // ========================================
    // 10. REST HANDLERS — LOGS
    // ========================================

    public static function rest_get_log_dates() {
        global $wpdb;
        $table_name = $wpdb->prefix . NOTIFIER_ACTIVITY_TABLE_NAME;

        $dates = $wpdb->get_col( "SELECT DISTINCT DATE(timestamp) as log_date FROM `$table_name` ORDER BY log_date DESC" );

        $formatted = array();
        foreach ( $dates as $date ) {
            $formatted[] = function_exists( 'notifier_convert_date_from_utc' )
                ? notifier_convert_date_from_utc( $date, 'Y-m-d' )
                : $date;
        }

        return new WP_REST_Response( $formatted, 200 );
    }

    public static function rest_get_logs( WP_REST_Request $request ) {
        $date = sanitize_text_field( $request->get_param( 'date' ) );

        if ( empty( $date ) ) {
            return new WP_REST_Response( array(), 200 );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . NOTIFIER_ACTIVITY_TABLE_NAME;

        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT log_id, timestamp, message, type FROM `$table_name` WHERE DATE(timestamp) = %s ORDER BY timestamp DESC",
            $date
        ) );

        return new WP_REST_Response( $logs ?: array(), 200 );
    }

    public static function rest_delete_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . NOTIFIER_ACTIVITY_TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; $table_name from trusted constant and $wpdb->prefix only.
        $wpdb->query( "TRUNCATE TABLE `{$table_name}`" );

        return new WP_REST_Response( array(
            'success' => true,
            'message' => __( 'All logs have been deleted.', 'notifier' ),
        ), 200 );
    }

    // ========================================
    // 11. REST HANDLERS — MIGRATION
    // ========================================

    public static function rest_get_migration_status() {
        $is_complete      = Notifier_Migration::is_migration_complete();
        $has_legacy       = Notifier_Migration::has_legacy_triggers();
        $slugs            = Notifier_Migration::get_non_woo_slugs_with_legacy_triggers();
        $all_connected    = Notifier_Migration::all_non_woo_slugs_connected();
        $migrate_url      = Notifier_Migration::get_migrate_connect_url();
        $trigger_scenario = Notifier_Migration::get_trigger_scenario();

        $trigger_posts = array();
        if ( $has_legacy ) {
            $integrations = self::get_integrations();

            $posts = get_posts( array(
                'post_type'   => 'wa_notifier_trigger',
                'post_status' => 'publish',
                'numberposts' => -1,
            ) );

            $all_triggers = Notifier_Notification_Triggers::get_notification_triggers();
            $trigger_label_map = array();
            foreach ( $all_triggers as $group => $triggers ) {
                foreach ( $triggers as $t ) {
                    $base = Notifier_Notification_Triggers::get_trigger_id_without_site_key( $t['id'] );
                    $trigger_label_map[ $base ] = $t['label'];
                }
            }

            foreach ( $posts as $post ) {
                $trigger_id   = get_post_meta( $post->ID, NOTIFIER_PREFIX . 'trigger', true );
                $base_trigger = Notifier_Notification_Triggers::get_trigger_id_without_site_key( $trigger_id );
                $slug         = Notifier_Notification_Triggers::get_integration_slug_for_trigger( $base_trigger );
                $is_woo       = in_array( $slug, Notifier_Migration::WOO_SLUGS, true );
                $connected    = $slug ? Notifier_Connection::is_connected( $slug ) : false;
                $label        = isset( $integrations[ $slug ]['label'] ) ? $integrations[ $slug ]['label'] : ucfirst( $slug ?: 'Unknown' );
                $trigger_label = isset( $trigger_label_map[ $base_trigger ] ) ? $trigger_label_map[ $base_trigger ] : $base_trigger;

                $trigger_posts[] = array(
                    'id'           => $post->ID,
                    'title'        => $trigger_label,
                    'slug'         => $slug ?: 'unknown',
                    'label'        => $label,
                    'is_connected' => $connected,
                    'is_woo'       => $is_woo,
                );
            }
        }

        return new WP_REST_Response( array(
            'is_complete'      => $is_complete,
            'has_legacy'       => $has_legacy,
            'slugs'            => $slugs,
            'all_connected'    => $all_connected,
            'migrate_url'      => $migrate_url,
            'trigger_posts'    => $trigger_posts,
            'trigger_scenario' => $trigger_scenario,
            'integrations_url' => admin_url( 'admin.php?page=notifier' ),
        ), 200 );
    }

    public static function rest_finalize_migration() {
        if ( ! Notifier_Migration::all_non_woo_slugs_connected() ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Not all integrations are connected yet.', 'notifier' ),
            ), 400 );
        }

        $api_key = trim( get_option( 'notifier_api_key', '' ) );
        if ( $api_key ) {
            Notifier_Migration::disconnect_legacy_on_saas( $api_key );
        }

        Notifier_Migration::delete_legacy_trigger_posts();

        update_option( Notifier_Migration::STATUS_OPTION, 'complete' );

        return new WP_REST_Response( array(
            'success'  => true,
            'message'  => __( 'Migration complete!', 'notifier' ),
            'redirect' => admin_url( 'admin.php?page=notifier' ),
        ), 200 );
    }


    // ========================================
    // 12b. REST HANDLER — WORDPRESS META FIELDS
    // ========================================

    public static function rest_get_wp_available_meta_fields( $request ) {
        $show_hidden = rest_sanitize_boolean( $request->get_param( 'show_hidden' ) );
        $groups     = array();

        // Single "Post Meta" group: all custom meta keys from all public post types (option stores all; filter by show_hidden).
        $combined = Notifier_Notification_Merge_Tags::get_custom_meta_keys( null );
        if ( ! empty( $combined ) ) {
            $post_meta = array();
            foreach ( $combined as $item ) {
                if ( ! $show_hidden && isset( $item['key'] ) && substr( (string) $item['key'], 0, 1 ) === '_' ) {
                    continue;
                }
                $post_meta[] = array(
                    'id'          => $item['post_type'] . '_meta_' . $item['key'],
                    'label'       => $item['key'],
                    'return_type' => 'all',
                );
            }
            if ( ! empty( $post_meta ) ) {
                $groups['Post Meta'] = $post_meta;
            }
        }

        // User meta group (option stores all; filter by show_hidden).
        $user_keys = Notifier_Notification_Merge_Tags::get_user_meta_keys();
        if ( ! empty( $user_keys ) && is_array( $user_keys ) ) {
            $user_meta = array();
            foreach ( $user_keys as $key ) {
                if ( ! $show_hidden && substr( (string) $key, 0, 1 ) === '_' ) {
                    continue;
                }
                $user_meta[] = array(
                    'id'          => 'user_meta_' . $key,
                    'label'       => $key,
                    'return_type' => 'all',
                );
            }
            if ( ! empty( $user_meta ) ) {
                $groups['User Meta'] = $user_meta;
            }
        }

        // Hook-added fields: exclude groups that are builtin (from merge tags / trigger definitions).
        $hook_fields = self::get_hook_added_merge_tag_fields();
        foreach ( $hook_fields as $field ) {
            $group = isset( $field['group'] ) ? $field['group'] : 'Custom Fields';
            if ( ! isset( $groups[ $group ] ) ) {
                $groups[ $group ] = array();
            }
            $groups[ $group ][] = array(
                'id'          => $field['id'],
                'label'       => isset( $field['label'] ) ? $field['label'] : $field['id'],
                'return_type' => isset( $field['return_type'] ) ? $field['return_type'] : 'text',
            );
        }

        return new WP_REST_Response( array( 'groups' => $groups ), 200 );
    }

    /**
     * POST /admin/sync-meta-fields — Sync all post and user meta keys from DB into options (normal + hidden).
     * Call this to refresh the meta field list when new meta is added on the site.
     */
    public static function rest_sync_meta_fields() {
        delete_option( 'notifier_custom_meta_keys' );
        delete_option( 'notifier_user_meta_keys' );
        Notifier_Notification_Merge_Tags::get_custom_meta_keys( null );
        Notifier_Notification_Merge_Tags::get_user_meta_keys();
        return new WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Meta fields synced. The list now includes all current post and user meta keys.', 'notifier' ),
        ), 200 );
    }

    /**
     * Return merge tag fields added via the notifier_notification_merge_tags filter
     * that are not part of the built-in groups.
     *
     * @return array[] Array of { group, id, label } objects.
     */
    private static function get_hook_added_merge_tag_fields() {
        // Builtin groups from merge tags / trigger definitions so we exclude them from the Data Fields dropdown.
        $all_tags       = apply_filters( 'notifier_notification_merge_tags', array() );
        $builtin_groups = array_merge( array( 'WordPress' ), array_keys( $all_tags ) );
        $fields         = array();

        foreach ( $all_tags as $group => $tags ) {
            if ( in_array( $group, $builtin_groups, true ) ) {
                continue;
            }
            foreach ( (array) $tags as $tag ) {
                if ( empty( $tag['id'] ) ) {
                    continue;
                }
                $fields[] = array(
                    'group' => $group,
                    'id'    => $tag['id'],
                    'label' => isset( $tag['label'] ) ? $tag['label'] : $tag['id'],
                );
            }
        }

        return $fields;
    }

    // ========================================
    // 13. INTEGRATION DEFINITIONS
    // ========================================

    public static function get_integrations() {
        $img          = NOTIFIER_URL . 'assets/images/integrations/';
        $woo_handled  = Notifier::is_woo_handled_by_standalone_plugin();

        return array(
            'woocommerce' => array(
                'label'                 => 'WooCommerce',
                'description'           => __( 'Order notifications and customer sync', 'notifier' ),
                'icon'                  => $img . 'woocommerce-logo.png',
                'active'                => class_exists( 'WooCommerce' ),
                'has_config'            => $woo_handled ? false : true,
                'config_component'      => $woo_handled ? '' : 'WooCommerceConfig',
                'handled_by_standalone' => $woo_handled,
            ),
            'wcar' => array(
                'label'                 => 'Cart Abandonment Recovery for WooCommerce',
                'description'           => __( 'Send notifications when a cart is abandoned', 'notifier' ),
                'icon'                  => $img . 'wcar-logo.svg',
                'active'                => class_exists( 'CARTFLOWS_CA_Loader' ) && defined( 'CARTFLOWS_CA_VER' ) && version_compare( CARTFLOWS_CA_VER, '1.2.25', '>=' ),
                'has_config'            => false,
                'handled_by_standalone' => $woo_handled,
            ),
            'wordpress' => array(
                'label'            => 'WordPress',
                'description'      => __( 'Posts, users, comments and more', 'notifier' ),
                'icon'             => $img . 'wordpress-logo.png',
                'active'           => true,
                'has_config'       => true,
                'config_component' => 'WordPressConfig',
            ),
            'gravityforms' => array(
                'label'       => 'Gravity Forms',
                'description' => __( 'Form submission notifications', 'notifier' ),
                'icon'        => $img . 'gravity-forms-logo.png',
                'active'      => class_exists( 'GFCommon' ),
                'has_config'  => false,
            ),
            'cf7' => array(
                'label'       => 'Contact Form 7',
                'description' => __( 'Form submission notifications', 'notifier' ),
                'icon'        => $img . 'contact-form-7-logo.png',
                'active'      => class_exists( 'WPCF7' ),
                'has_config'  => false,
            ),
            'wpforms' => array(
                'label'       => 'WPForms',
                'description' => __( 'Form submission notifications', 'notifier' ),
                'icon'        => $img . 'wpforms-logo.png',
                'active'      => class_exists( 'WPForms' ),
                'has_config'  => false,
            ),
            'ninjaforms' => array(
                'label'       => 'Ninja Forms',
                'description' => __( 'Form submission notifications', 'notifier' ),
                'icon'        => $img . 'nf-logo.png',
                'active'      => class_exists( 'Ninja_Forms' ),
                'has_config'  => false,
            ),
            'formidable' => array(
                'label'       => 'Formidable Forms',
                'description' => __( 'Form submission notifications', 'notifier' ),
                'icon'        => $img . 'formidable-logo.png',
                'active'      => class_exists( 'FrmForm' ),
                'has_config'  => false,
            ),
            'fluentforms' => array(
                'label'       => 'Fluent Forms',
                'description' => __( 'Form submission notifications', 'notifier' ),
                'icon'        => $img . 'fluent-forms-logo.webp',
                'active'      => function_exists( 'fluentFormApi' ),
                'has_config'  => false,
            ),
            'forminator' => array(
                'label'       => 'Forminator Forms',
                'description' => __( 'Form submission notifications', 'notifier' ),
                'icon'        => $img . 'forminator-form-logo.svg',
                'active'      => class_exists( 'Forminator' ),
                'has_config'  => false,
            ),
            'sureforms' => array(
                'label'       => 'SureForms',
                'description' => __( 'Form submission notifications', 'notifier' ),
                'icon'        => $img . 'sureform-logo.svg',
                'active'      => class_exists( 'SRFM\Plugin_Loader' ),
                'has_config'  => false,
            ),
            'wsform' => array(
                'label'       => 'WS Forms',
                'description' => __( 'Form submission notifications', 'notifier' ),
                'icon'        => $img . 'ws-form-logo.svg',
                'active'      => class_exists( 'WS_Form' ),
                'has_config'  => false,
            ),
        );
    }

    // ========================================
    // 14. SETTINGS DATA
    // ========================================

    public static function get_all_settings() {
        return array(
            'default_country_code'              => get_option( 'notifier_default_country_code', '' ),
            'ctc_enable'                        => get_option( 'notifier_ctc_enable', 'no' ),
            'ctc_whatsapp_number'               => get_option( 'notifier_ctc_whatsapp_number', '' ),
            'ctc_greeting_text'                 => get_option( 'notifier_ctc_greeting_text', '' ),
            'ctc_button_style'                  => get_option( 'notifier_ctc_button_style', 'default' ),
            'ctc_custom_button_image_url'       => get_option( 'notifier_ctc_custom_button_image_url', '' ),
            'enable_activity_log'               => get_option( 'notifier_enable_activity_log', 'no' ),
            'wp_enabled_merge_tags'             => get_option( 'notifier_wp_enabled_merge_tags', array() ),
            'wp_enabled_recipient_fields'       => get_option( 'notifier_wp_enabled_recipient_fields', array() ),
            'button_styles'                     => self::get_button_styles(),
        );
    }

    private static function get_settings_field_definitions() {
        return array(
            'default_country_code'            => array( 'type' => 'text' ),
            'ctc_enable'                      => array( 'type' => 'checkbox' ),
            'ctc_whatsapp_number'             => array( 'type' => 'text' ),
            'ctc_greeting_text'               => array( 'type' => 'text' ),
            'ctc_button_style'                => array( 'type' => 'select', 'options' => self::get_button_styles(), 'default' => 'default' ),
            'ctc_custom_button_image_url'     => array( 'type' => 'text' ),
            'enable_activity_log'             => array( 'type' => 'checkbox' ),
            'wp_enabled_merge_tags'           => array( 'type' => 'array' ),
            'wp_enabled_recipient_fields'     => array( 'type' => 'array' ),
        );
    }

    public static function get_button_styles() {
        return array(
            'default'          => 'Select button style',
            'btn-style-1'     => 'Style 1',
            'btn-style-2'     => 'Style 2',
            'btn-style-3'     => 'Style 3',
            'btn-style-4'     => 'Style 4',
            'btn-custom-image' => 'Add your own image',
        );
    }

    // ========================================
    // 15. ACTIVITY LOG
    // ========================================

    public static function insert_activity_log( $type = 'debug', $message = '' ) {
        if ( 'yes' !== get_option( 'notifier_enable_activity_log' ) ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . NOTIFIER_ACTIVITY_TABLE_NAME;

        $wpdb->insert( $table_name, array(
            'timestamp' => current_time( 'mysql', true ),
            'message'   => $message,
            'type'      => $type,
        ), array( '%s', '%s', '%s' ) );
    }
}
