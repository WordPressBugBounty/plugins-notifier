<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connection class for WANotifier plugin v3
 *
 * Manages per-integration connections between this WordPress site and the WANotifier SaaS.
 * Each integration (woocommerce, gravityforms, cf7, etc.) has its own connection stored
 * in WordPress options.
 *
 * Option keys used (per slug):
 *   notifier_conn_{slug}_auth_key
 *   notifier_conn_{slug}_webhook_key
 *   notifier_conn_{slug}_account_id
 *   notifier_conn_{slug}_connection_id
 *   notifier_conn_{slug}_saas_url
 *   notifier_conn_{slug}_connected_at
 *   notifier_conn_{slug}_token          (temporary, cleared after connect)
 *   notifier_conn_{slug}_token_created  (temporary)
 *
 * @package    Notifier
 */
class Notifier_Connection {

    // ========================================
    // 1. INITIALIZATION
    // ========================================

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_endpoints' ) );
        add_action( 'notifier_fire_trigger', array( __CLASS__, 'handle_fire_trigger_action' ), 10, 3 );
    }

    // ========================================
    // 2. CONNECTION MANAGEMENT
    // ========================================

    /**
     * Generate (or retrieve existing) connect token for a slug.
     *
     * @param string $slug Integration slug.
     * @return string Connect token.
     */
    public static function get_or_create_connect_token( $slug ) {
        $slug  = sanitize_key( $slug );
        $token = get_option( 'notifier_conn_' . $slug . '_token' );

        if ( ! $token ) {
            $token = 'ct_' . wp_generate_password( 48, false );
            update_option( 'notifier_conn_' . $slug . '_token', $token, false );
            update_option( 'notifier_conn_' . $slug . '_token_created', time(), false );
        }

        return $token;
    }

    /**
     * Complete the connection after SaaS calls back.
     *
     * @param string $slug Integration slug.
     * @param array  $data Connection data from SaaS (auth_key, webhook_key, account_id, etc.).
     * @return bool
     */
    public static function complete_connection( $slug, $data ) {
        $slug = sanitize_key( $slug );

        $stored_token = get_option( 'notifier_conn_' . $slug . '_token' );
        $connect_token = isset( $data['connect_token'] ) ? $data['connect_token'] : '';

        if ( empty( $stored_token ) || ! hash_equals( $stored_token, $connect_token ) ) {
            return false;
        }

        update_option( 'notifier_conn_' . $slug . '_auth_key',      sanitize_text_field( $data['auth_key'] ?? '' ), false );
        update_option( 'notifier_conn_' . $slug . '_webhook_key',   sanitize_text_field( $data['webhook_key'] ?? '' ), false );
        update_option( 'notifier_conn_' . $slug . '_account_id',    sanitize_text_field( $data['account_id'] ?? '' ), false );
        update_option( 'notifier_conn_' . $slug . '_connection_id', absint( $data['connection_id'] ?? 0 ), false );
        update_option( 'notifier_conn_' . $slug . '_saas_url',      esc_url_raw( $data['saas_url'] ?? ( NOTIFIER_APP_BASE_URL . '/' . NOTIFIER_APP_API_PATH . '/' ) ), false );
        update_option( 'notifier_conn_' . $slug . '_connected_at',  current_time( 'mysql' ), false );

        // Clear the temporary token.
        delete_option( 'notifier_conn_' . $slug . '_token' );
        delete_option( 'notifier_conn_' . $slug . '_token_created' );

        return true;
    }

    /**
     * Check if an integration is connected.
     *
     * @param string $slug Integration slug.
     * @return bool
     */
    public static function is_connected( $slug ) {
        $slug     = sanitize_key( $slug );
        $auth_key = get_option( 'notifier_conn_' . $slug . '_auth_key', '' );
        return ! empty( $auth_key );
    }

    /**
     * Get full connection data for a slug, including extra stored options like continuous_sync.
     *
     * @param string $slug Integration slug.
     * @return array|null Connection data or null if not connected.
     */
    public static function get_connection( $slug ) {
        $slug = sanitize_key( $slug );
        $info = self::get_connection_info( $slug );
        if ( ! $info ) {
            return null;
        }
        // Merge in any extra per-slug config stored as a single serialized option.
        $extra = get_option( 'notifier_conn_' . $slug . '_extra', array() );
        if ( is_array( $extra ) ) {
            $info = array_merge( $info, $extra );
        }
        return $info;
    }

    /**
     * Save extra connection config for a slug (e.g. continuous_sync).
     *
     * @param string $slug Integration slug.
     * @param array  $data Key-value pairs to store.
     */
    public static function save_connection( $slug, $data ) {
        $slug    = sanitize_key( $slug );
        $current = get_option( 'notifier_conn_' . $slug . '_extra', array() );
        if ( ! is_array( $current ) ) {
            $current = array();
        }
        // Only persist the extra keys (not auth_key, webhook_key etc. which have own options).
        $core_keys = array( 'auth_key', 'webhook_key', 'account_id', 'connection_id', 'saas_url', 'connected_at' );
        foreach ( $data as $key => $value ) {
            if ( ! in_array( $key, $core_keys, true ) ) {
                $current[ $key ] = $value;
            }
        }
        update_option( 'notifier_conn_' . $slug . '_extra', $current );
    }

    /**
     * Get connection info for a slug.
     *
     * @param string $slug Integration slug.
     * @return array|null Connection data or null if not connected.
     */
    public static function get_connection_info( $slug ) {
        $slug = sanitize_key( $slug );

        if ( ! self::is_connected( $slug ) ) {
            return null;
        }

        return array(
            'auth_key'      => get_option( 'notifier_conn_' . $slug . '_auth_key', '' ),
            'webhook_key'   => get_option( 'notifier_conn_' . $slug . '_webhook_key', '' ),
            'account_id'    => get_option( 'notifier_conn_' . $slug . '_account_id', '' ),
            'connection_id' => get_option( 'notifier_conn_' . $slug . '_connection_id', 0 ),
            'saas_url'      => get_option( 'notifier_conn_' . $slug . '_saas_url', NOTIFIER_APP_BASE_URL . '/' . NOTIFIER_APP_API_PATH . '/' ),
            'connected_at'  => get_option( 'notifier_conn_' . $slug . '_connected_at', '' ),
        );
    }

    /**
     * Disconnect an integration.
     *
     * @param string $slug Integration slug.
     * @param bool   $notify_saas Whether to notify the SaaS about the disconnect.
     * @return bool
     */
    public static function disconnect( $slug, $notify_saas = true ) {
        $slug = sanitize_key( $slug );
        $conn = self::get_connection_info( $slug );

        if ( $notify_saas && $conn ) {
            $saas_url = trailingslashit( $conn['saas_url'] );
            wp_remote_post(
                $saas_url . NOTIFIER_APP_API_PATH . '/integrations/' . $slug . '/disconnect',
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'X-Auth-Key'   => $conn['auth_key'],
                    ),
                    'body'      => '{}',
                    'timeout'   => 10,
                    'sslverify' => true,
                )
            );
        }

        delete_option( 'notifier_conn_' . $slug . '_auth_key' );
        delete_option( 'notifier_conn_' . $slug . '_webhook_key' );
        delete_option( 'notifier_conn_' . $slug . '_account_id' );
        delete_option( 'notifier_conn_' . $slug . '_connection_id' );
        delete_option( 'notifier_conn_' . $slug . '_saas_url' );
        delete_option( 'notifier_conn_' . $slug . '_connected_at' );
        delete_option( 'notifier_conn_' . $slug . '_token' );
        delete_option( 'notifier_conn_' . $slug . '_token_created' );

        return true;
    }

    /**
     * Build the SaaS connect URL for an integration.
     *
     * @param string $slug Integration slug.
     * @return string URL to redirect user to for connection.
     */
    public static function get_connect_url( $slug ) {
        $slug  = sanitize_key( $slug );
        $token = self::get_or_create_connect_token( $slug );

        $saas_base = NOTIFIER_APP_BASE_URL;

        $payload = base64_encode( wp_json_encode( array(
            'store_url'     => site_url(),
            'connect_token' => $token,
            'return_url'    => admin_url( 'admin.php?page=notifier' ),
        ) ) );

        return $saas_base . '/integrations/' . $slug . '/connect?payload=' . rawurlencode( $payload );
    }

    // ========================================
    // 3. TRIGGER DISPATCH (v3 — always via Action Scheduler)
    // ========================================

    /**
     * Dispatch a trigger event via Action Scheduler.
     *
     * Always async — AS provides built-in retry (3 attempts with backoff).
     *
     * @param string $slug         Integration slug.
     * @param string $trigger_slug Trigger slug (e.g. 'woo_order_new').
     * @param array  $payload      Trigger payload with merge_tags_data and recipient_fields.
     */
    public static function dispatch_trigger( $slug, $trigger_slug, $context_args ) {
        if ( ! self::is_connected( $slug ) ) {
            return;
        }

        $object_id = isset( $context_args['object_id'] ) ? (int) $context_args['object_id'] : 0;
        Notifier_Backend::insert_activity_log(
            'debug',
            sprintf( '[%s] Trigger scheduled: %s (object_id: %d)', $slug, $trigger_slug, $object_id )
        );

        $action_args = array(
            'slug'         => $slug,
            'trigger_slug' => $trigger_slug,
            'context_args' => $context_args,
        );

        // Prevent duplicate actions for the same trigger + context.
        $existing = as_get_scheduled_actions( array(
            'hook'   => 'notifier_fire_trigger',
            'args'   => $action_args,
            'group'  => 'notifier',
            'status' => ActionScheduler_Store::STATUS_PENDING,
        ), 'ids' );

        if ( ! empty( $existing ) ) {
            Notifier_Backend::insert_activity_log(
                'debug',
                sprintf( '[%s] Duplicate trigger skipped: %s (object_id: %d)', $slug, $trigger_slug, $object_id )
            );
            return;
        }

        as_enqueue_async_action(
            'notifier_fire_trigger',
            $action_args,
            'notifier'
        );
    }

    /**
     * Action Scheduler handler for notifier_fire_trigger.
     * Builds the payload here (async) so the main request is not slowed down.
     *
     * @param string $slug
     * @param string $trigger_slug
     * @param array  $context_args
     */
    public static function handle_fire_trigger_action( $slug, $trigger_slug, $context_args ) {
        $object_type = isset( $context_args['object_type'] ) ? $context_args['object_type'] : '';
        $object_id   = isset( $context_args['object_id'] ) ? (int) $context_args['object_id'] : 0;

        if ( 'user' === $object_type && $object_id ) {
            $payload = Notifier_Notification_Merge_Tags::build_user_payload( $object_id );
        } elseif ( 'comment' === $object_type && $object_id ) {
            $payload = Notifier_Notification_Merge_Tags::build_comment_payload( $object_id );
        } elseif ( $object_id && in_array( $object_type, array_merge( array( 'post', 'page', 'attachment' ), array_keys( get_post_types( array( 'public' => true ) ) ) ), true ) ) {
            $payload = Notifier_Notification_Merge_Tags::build_post_payload( $object_id, $object_type );
        } else {
            $payload = self::build_trigger_payload( $trigger_slug, $context_args );
        }

        Notifier_Backend::insert_activity_log(
            'debug',
            sprintf(
                '[%s] Trigger firing: %s (object_id: %d) | merge_tags: %s | recipient_fields: %s',
                $slug,
                $trigger_slug,
                $object_id,
                wp_json_encode( $payload['merge_tags_data'] ?? array() ),
                wp_json_encode( $payload['recipient_fields'] ?? array() )
            )
        );

        self::fire_trigger( $slug, $trigger_slug, $payload );
    }

    /**
     * Fire a trigger by POSTing to the SaaS event endpoint.
     *
     * @param string $slug
     * @param string $trigger_slug
     * @param array  $payload
     * @return bool
     */
    public static function fire_trigger( $slug, $trigger_slug, $payload ) {
        $conn = self::get_connection_info( $slug );

        if ( ! $conn ) {
            return false;
        }

        $saas_url    = trailingslashit( $conn['saas_url'] );
        $webhook_key = $conn['webhook_key'];
        $auth_key    = $conn['auth_key'];

        $recipient_fields = isset( $payload['recipient_fields'] ) ? $payload['recipient_fields'] : array();
        foreach ( $recipient_fields as $key => $value ) {
            $recipient_fields[ $key ] = notifier_maybe_add_default_country_code( notifier_sanitize_phone_number( (string) $value ) );
        }

        $body = wp_json_encode( array(
            'event_key'        => $trigger_slug,
            'merge_tags_data'  => isset( $payload['merge_tags_data'] ) ? $payload['merge_tags_data'] : array(),
            'recipient_fields' => $recipient_fields,
        ) );

        $response = wp_remote_post(
            $saas_url . NOTIFIER_APP_API_PATH . '/integrations/' . $slug . '/' . $webhook_key . '/event',
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-Auth-Key'   => $auth_key,
                ),
                'body'      => $body,
                'timeout'   => 30,
                'sslverify' => true,
            )
        );

        if ( is_wp_error( $response ) ) {
            $error_msg = $response->get_error_message();
            error_log( 'WANotifier: fire_trigger failed for ' . $slug . '/' . $trigger_slug . ': ' . $error_msg );
            Notifier_Backend::insert_activity_log(
                'error',
                sprintf( '[%s] Trigger failed: %s — %s', $slug, $trigger_slug, $error_msg )
            );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 400 ) {
            $body = wp_remote_retrieve_body( $response );
            error_log( 'WANotifier: fire_trigger HTTP ' . $code . ' for ' . $slug . '/' . $trigger_slug . ': ' . $body );
            Notifier_Backend::insert_activity_log(
                'error',
                sprintf( '[%s] Trigger error (HTTP %d): %s — %s', $slug, $code, $trigger_slug, $body )
            );
            return false;
        }

        Notifier_Backend::insert_activity_log(
            'success',
            sprintf( '[%s] Trigger sent successfully: %s', $slug, $trigger_slug )
        );

        return true;
    }

    // ========================================
    // 4. SAAS API HELPERS
    // ========================================

    /**
     * Send a request to the SaaS API for a specific integration.
     *
     * @param string $slug     Integration slug.
     * @param string $endpoint Endpoint path (relative to {NOTIFIER_APP_API_PATH}/integrations/{slug}/).
     * @param array  $body     Request body.
     * @param string $method   HTTP method.
     * @return array|false Decoded response body or false on failure.
     */
    public static function send_saas_request( $slug, $endpoint, $body = array(), $method = 'POST' ) {
        $conn = self::get_connection_info( $slug );

        if ( ! $conn ) {
            return false;
        }

        $saas_url = trailingslashit( $conn['saas_url'] );
        $url      = $saas_url . NOTIFIER_APP_API_PATH . '/integrations/' . $slug . '/' . ltrim( $endpoint, '/' );

        $response = wp_remote_request( $url, array(
            'method'  => $method,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Auth-Key'   => $conn['auth_key'],
            ),
            'body'      => wp_json_encode( $body ),
            'timeout'   => 30,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'WANotifier: SaaS request failed for ' . $slug . '/' . $endpoint . ': ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 400 ) {
            error_log( 'WANotifier: SaaS request HTTP ' . $code . ' for ' . $slug . '/' . $endpoint );
            return false;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    // ========================================
    // 5. REST API ENDPOINTS (plugin-side)
    // ========================================

    /**
     * Register REST endpoints for each integration.
     * These are called by the SaaS to complete the connection handshake.
     */
    public static function register_rest_endpoints() {
        // GET /wp-json/notifier/v1/legacy-triggers — SaaS fetches legacy CPT trigger definitions during migration.
        register_rest_route( 'notifier/v1', '/legacy-triggers', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'handle_rest_legacy_triggers' ),
            'permission_callback' => array( __CLASS__, 'verify_legacy_api_key' ),
        ) );

        $slugs = self::get_supported_slugs();

        foreach ( $slugs as $slug ) {
            // POST /wp-json/notifier/v1/{slug}/connect — SaaS delivers credentials.
            register_rest_route( 'notifier/v1', '/' . $slug . '/connect', array(
                'methods'             => 'POST',
                'callback'            => function( $request ) use ( $slug ) {
                    return Notifier_Connection::handle_rest_connect( $request, $slug );
                },
                'permission_callback' => '__return_true',
            ) );

            // POST /wp-json/notifier/v1/{slug}/disconnect — SaaS or admin disconnect.
            register_rest_route( 'notifier/v1', '/' . $slug . '/disconnect', array(
                'methods'             => 'POST',
                'callback'            => function( $request ) use ( $slug ) {
                    return Notifier_Connection::handle_rest_disconnect( $request, $slug );
                },
                'permission_callback' => function( $request ) use ( $slug ) {
                    return Notifier_Connection::verify_auth_key( $request, $slug );
                },
            ) );

            // GET /wp-json/notifier/v1/{slug}/status — SaaS health check.
            register_rest_route( 'notifier/v1', '/' . $slug . '/status', array(
                'methods'             => 'GET',
                'callback'            => function( $request ) use ( $slug ) {
                    return Notifier_Connection::handle_rest_status( $request, $slug );
                },
                'permission_callback' => function( $request ) use ( $slug ) {
                    return Notifier_Connection::verify_auth_key( $request, $slug );
                },
            ) );

            // POST /wp-json/notifier/v1/{slug}/sync — Sync button.
            register_rest_route( 'notifier/v1', '/' . $slug . '/sync', array(
                'methods'             => 'POST',
                'callback'            => function( $request ) use ( $slug ) {
                    return Notifier_Connection::handle_rest_sync_triggers( $request, $slug );
                },
                'permission_callback' => function( $request ) use ( $slug ) {
                    return Notifier_Connection::verify_auth_key( $request, $slug );
                },
            ) );
        }

    }

    /**
     * Handle SaaS callback to complete connection.
     *
     * @param WP_REST_Request $request
     * @param string          $slug
     * @return WP_REST_Response
     */
    public static function handle_rest_connect( $request, $slug ) {
        $data = $request->get_json_params();

        if ( empty( $data ) ) {
            return new WP_REST_Response( array( 'error' => true, 'message' => 'No data received.' ), 400 );
        }

        $success = self::complete_connection( $slug, $data );

        if ( ! $success ) {
            return new WP_REST_Response( array( 'error' => true, 'message' => 'Invalid connect token.' ), 401 );
        }

        // Build and return the trigger schema so SaaS can initialize trigger defaults.
        $trigger_schema = self::build_trigger_schema( $slug );

        return new WP_REST_Response( array(
            'error'    => false,
            'message'  => 'Connected successfully.',
            'triggers' => $trigger_schema,
        ), 200 );
    }

    /**
     * Handle disconnect request.
     *
     * @param WP_REST_Request $request
     * @param string          $slug
     * @return WP_REST_Response
     */
    public static function handle_rest_disconnect( $request, $slug ) {
        self::disconnect( $slug, false ); // Don't notify SaaS — it's calling us.

        return new WP_REST_Response( array(
            'error'   => false,
            'message' => 'Disconnected.',
        ), 200 );
    }

    /**
     * Handle status check.
     *
     * @param WP_REST_Request $request
     * @param string          $slug
     * @return WP_REST_Response
     */
    public static function handle_rest_status( $request, $slug ) {
        return new WP_REST_Response( array(
            'error'     => false,
            'connected' => self::is_connected( $slug ),
            'slug'      => $slug,
            'site_url'  => site_url(),
        ), 200 );
    }

    /**
     * Handle sync request — push current schema to SaaS.
     *
     * @param WP_REST_Request $request
     * @param string          $slug
     * @return WP_REST_Response
     */
    public static function handle_rest_sync_triggers( $request, $slug ) {
        $trigger_schema = self::build_trigger_schema( $slug );

        // Push to SaaS.
        self::send_saas_request( $slug, 'sync', array( 'triggers' => $trigger_schema ) );

        return new WP_REST_Response( array(
            'error'    => false,
            'message'  => 'Triggers synced.',
            'triggers' => $trigger_schema,
        ), 200 );
    }

    // ========================================
    // 6. TRIGGER PAYLOAD BUILDER
    // ========================================

    /**
     * Build the full trigger payload for v3 dispatch.
     *
     * Calls get_trigger_merge_tag_value() for ALL defined merge tags for the trigger
     * (not just the user-selected subset from CPT). This is the key difference from v2.
     *
     * @param string $trigger      Trigger ID (with or without site key prefix).
     * @param array  $context_args Context args (object_type, object_id, or form entry data).
     * @return array Payload with merge_tags_data and recipient_fields.
     */
    public static function build_trigger_payload( $trigger, $context_args ) {
        $all_triggers = Notifier_Notification_Triggers::get_notification_triggers();

        // Find the trigger definition.
        $trigger_def = null;
        foreach ( $all_triggers as $group => $triggers ) {
            foreach ( $triggers as $t ) {
                if ( $t['id'] === $trigger || Notifier_Notification_Triggers::get_trigger_id_without_site_key( $t['id'] ) === Notifier_Notification_Triggers::get_trigger_id_without_site_key( $trigger ) ) {
                    $trigger_def = $t;
                    break 2;
                }
            }
        }

        $merge_tags_data  = array();
        $recipient_fields = array();

        if ( $trigger_def ) {
            // Build merge tags — ALL defined tags, not just user-selected ones.
            if ( ! empty( $trigger_def['merge_tags'] ) ) {
                foreach ( $trigger_def['merge_tags'] as $group => $tags ) {
                    foreach ( $tags as $tag ) {
                        $merge_tags_data[ $tag['id'] ] = Notifier_Notification_Merge_Tags::get_trigger_merge_tag_value( $tag['id'], $context_args );
                    }
                }
            }

            // Build recipient fields — ALL defined fields.
            if ( ! empty( $trigger_def['recipient_fields'] ) ) {
                foreach ( $trigger_def['recipient_fields'] as $group => $fields ) {
                    foreach ( $fields as $field ) {
                        $recipient_fields[ $field['id'] ] = Notifier_Notification_Merge_Tags::get_trigger_recipient_field_value( $field['id'], $context_args );
                    }
                }
            }
        }

        $payload = array(
            'merge_tags_data'  => $merge_tags_data,
            'recipient_fields' => $recipient_fields,
        );

        /**
         * Allow integrations to override or enrich the trigger payload.
         *
         * @param array  $payload      Payload with merge_tags_data and recipient_fields.
         * @param string $trigger      Trigger ID.
         * @param array  $context_args Context args.
         */
        return apply_filters( 'notifier_v3_trigger_payload', $payload, $trigger, $context_args );
    }

    // ========================================
    // 7. TRIGGER SCHEMA BUILDER
    // ========================================

    /**
     * Build the trigger schema for a given integration slug.
     *
     * Serialises existing trigger definitions (from get_notification_triggers() and
     * integration-specific get_merge_tags()) into the format the SaaS expects.
     * No new field definitions are written — existing definitions are reused.
     *
     * @param string $slug Integration slug.
     * @return array Array of trigger schema objects.
     */
    public static function build_trigger_schema( $slug ) {
        $schema = array();

        switch ( $slug ) {
            case 'wordpress':
                $schema = self::build_wordpress_schema();
                break;

            case 'gravityforms':
            case 'cf7':
            case 'wpforms':
            case 'ninjaforms':
            case 'formidable':
            case 'fluentforms':
            case 'forminator':
            case 'sureforms':
            case 'wsform':
                $schema = self::build_form_schema( $slug );
                break;
        }

        return $schema;
    }

    /**
     * Build WordPress core trigger schema.
     *
     * @return array
     */
    private static function build_wordpress_schema() {
        $all_triggers = Notifier_Notification_Triggers::get_notification_triggers();
        $wp_triggers  = isset( $all_triggers['WordPress'] ) ? $all_triggers['WordPress'] : array();

        $enabled_merge_tags       = get_option( 'notifier_wp_enabled_merge_tags', array() );
        $enabled_recipient_fields = get_option( 'notifier_wp_enabled_recipient_fields', array() );
        $enabled_merge_tags       = is_array( $enabled_merge_tags ) ? $enabled_merge_tags : array();
        $enabled_recipient_fields = is_array( $enabled_recipient_fields ) ? $enabled_recipient_fields : array();

        $schema = array();
        foreach ( $wp_triggers as $trigger ) {
            $trigger_id = $trigger['id'];
            $base_slug  = Notifier_Notification_Triggers::get_trigger_id_without_site_key( $trigger_id );

            // Filter merge_tags: keep all predefined fields, only include enabled custom meta.
            $filtered_merge_tags = array();
            foreach ( ( $trigger['merge_tags'] ?? array() ) as $group => $tags ) {
                $filtered = array();
                foreach ( $tags as $tag ) {
                    if ( false !== strpos( $tag['id'], '_meta_' ) ) {
                        if ( ! in_array( $tag['id'], $enabled_merge_tags, true ) ) {
                            continue;
                        }
                    }
                    $filtered[] = $tag;
                }
                if ( ! empty( $filtered ) ) {
                    $filtered_merge_tags[ $group ] = $filtered;
                }
            }

            // Filter recipient_fields: only include enabled custom meta.
            $filtered_recipient_fields = array();
            foreach ( ( $trigger['recipient_fields'] ?? array() ) as $group => $fields ) {
                $filtered = array();
                foreach ( $fields as $field ) {
                    if ( false !== strpos( $field['id'], '_meta_' ) ) {
                        if ( ! in_array( $field['id'], $enabled_recipient_fields, true ) ) {
                            continue;
                        }
                    }
                    $filtered[] = $field;
                }
                if ( ! empty( $filtered ) ) {
                    $filtered_recipient_fields[ $group ] = $filtered;
                }
            }

            $schema[] = array(
                'slug'             => $base_slug,
                'label'            => $trigger['label'],
                'description'      => isset( $trigger['description'] ) ? $trigger['description'] : '',
                'category'         => 'automation',
                'event_key'        => $base_slug,
                'merge_tags'       => self::flatten_merge_tags( $filtered_merge_tags ),
                'recipient_fields' => self::flatten_recipient_fields( $filtered_recipient_fields ),
            );
        }

        return $schema;
    }

    /**
     * Build form plugin trigger schema (per-form triggers).
     *
     * @param string $slug Integration slug.
     * @return array
     */
    private static function build_form_schema( $slug ) {
        $all_triggers = Notifier_Notification_Triggers::get_notification_triggers();

        $group_map = array(
            'gravityforms' => 'Gravity Forms',
            'cf7'          => 'Contact Form 7',
            'wpforms'      => 'WPForms',
            'ninjaforms'   => 'Ninja Forms',
            'formidable'   => 'Formidable Forms',
            'fluentforms'  => 'Fluent Forms',
            'forminator'   => 'Forminator Forms',
            'sureforms'    => 'SureForms',
            'wsform'       => 'WS Form',
        );

        $group_key = isset( $group_map[ $slug ] ) ? $group_map[ $slug ] : '';
        $triggers  = ! empty( $group_key ) && isset( $all_triggers[ $group_key ] ) ? $all_triggers[ $group_key ] : array();

        $schema = array();
        foreach ( $triggers as $trigger ) {
            $trigger_id = $trigger['id'];
            $base_slug  = Notifier_Notification_Triggers::get_trigger_id_without_site_key( $trigger_id );

            // Extract just the form name from the label (e.g. 'Form "Contact Us" is submitted' → 'Contact Us').
            // The SaaS builds the full display label dynamically from form_name.
            $schema_entry = array(
                'slug'             => $base_slug,
                'label'            => $trigger['label'],
                'description'      => isset( $trigger['description'] ) ? $trigger['description'] : '',
                'category'         => 'automation',
                'event_key'        => $base_slug,
                'merge_tags'       => self::flatten_merge_tags( $trigger['merge_tags'] ?? array() ),
                'recipient_fields' => self::flatten_recipient_fields( $trigger['recipient_fields'] ?? array() ),
            );
            $form_name = self::extract_form_name( $trigger['label'] );
            if ( ! empty( $form_name ) ) {
                $schema_entry['form_name'] = $form_name;
            }

            $schema[] = $schema_entry;
        }

        return $schema;
    }

    /**
     * Flatten grouped merge tags array to a flat list for SaaS.
     *
     * @param array $grouped_tags Grouped merge tags (group => [tag, ...]).
     * @return array Flat list of {id, label, return_type}.
     */
    /**
     * Extract just the form name from a trigger label like 'Form "Contact Us" is submitted'.
     * Returns empty string for non-form triggers.
     *
     * @param string $label
     * @return string
     */
    private static function extract_form_name( $label ) {
        if ( preg_match( '/^Form ["\'](.+)["\'] is submitted$/i', $label, $m ) ) {
            return $m[1];
        }
        return '';
    }

    private static function flatten_merge_tags( $grouped_tags ) {
        $flat = array();
        foreach ( $grouped_tags as $group => $tags ) {
            foreach ( $tags as $tag ) {
                $flat[] = array(
                    'id'          => $tag['id'],
                    'label'       => $tag['label'],
                    'return_type' => isset( $tag['return_type'] ) ? $tag['return_type'] : 'text',
                    'group'       => $group,
                );
            }
        }
        return $flat;
    }

    /**
     * Flatten grouped recipient fields to a flat list for SaaS.
     *
     * @param array $grouped_fields Grouped recipient fields.
     * @return array Flat list of {id, label}.
     */
    private static function flatten_recipient_fields( $grouped_fields ) {
        $flat = array();
        foreach ( $grouped_fields as $group => $fields ) {
            foreach ( $fields as $field ) {
                $flat[] = array(
                    'id'    => $field['id'],
                    'label' => $field['label'],
                    'group' => $group,
                );
            }
        }
        return $flat;
    }

    // ========================================
    // 7. AUTHENTICATION HELPERS
    // ========================================

    /**
     * Verify auth key from request header.
     *
     * @param WP_REST_Request $request
     * @param string          $slug
     * @return bool
     */
    public static function verify_auth_key( $request, $slug ) {
        $provided_key = $request->get_header( 'X-Auth-Key' );
        if ( empty( $provided_key ) ) {
            return false;
        }

        $stored_key = get_option( 'notifier_conn_' . sanitize_key( $slug ) . '_auth_key', '' );
        if ( empty( $stored_key ) ) {
            return false;
        }

        return hash_equals( $stored_key, $provided_key );
    }

    /**
     * Verify the legacy API key for the /legacy-triggers endpoint.
     *
     * Accepts the old notifier_api_key via X-Auth-Key header or ?key= query param.
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public static function verify_legacy_api_key( $request ) {
        $provided_key = $request->get_header( 'X-Auth-Key' );
        if ( empty( $provided_key ) ) {
            $provided_key = $request->get_param( 'key' );
        }

        if ( empty( $provided_key ) ) {
            return false;
        }

        $stored_key = trim( get_option( 'notifier_api_key', '' ) );
        if ( empty( $stored_key ) ) {
            return false;
        }

        return hash_equals( $stored_key, $provided_key );
    }

    /**
     * Handle GET /wp-json/notifier/v1/legacy-triggers
     *
     * Returns legacy CPT trigger definitions so the SaaS can run trigger migration
     * during the batch connect flow. Optionally filtered by ?slug=.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_rest_legacy_triggers( $request ) {
        $filter_slug = $request->get_param( 'slug' );
        if ( $filter_slug ) {
            $filter_slug = sanitize_key( $filter_slug );
        }

        $posts = get_posts( array(
            'post_type'   => 'wa_notifier_trigger',
            'post_status' => 'publish',
            'numberposts' => -1,
        ) );

        $triggers = array();

        foreach ( $posts as $post ) {
            $trigger_id   = get_post_meta( $post->ID, NOTIFIER_PREFIX . 'trigger', true );
            $base_trigger = Notifier_Notification_Triggers::get_trigger_id_without_site_key( $trigger_id );
            $slug         = Notifier_Notification_Triggers::get_integration_slug_for_trigger( $base_trigger );

            if ( ! $slug ) {
                continue;
            }

            if ( $filter_slug && $slug !== $filter_slug ) {
                continue;
            }

            $triggers[] = array(
                'post_id'          => $post->ID,
                'old_trigger_id'   => $trigger_id,
                'new_trigger_slug' => $base_trigger,
                'slug'             => $slug,
            );
        }

        return new WP_REST_Response( array(
            'error'    => false,
            'triggers' => $triggers,
        ), 200 );
    }

    // ========================================
    // 9. SUPPORTED SLUGS
    // ========================================

    /**
     * Get list of supported integration slugs.
     *
     * @return string[]
     */
    public static function get_supported_slugs() {
        return array(
            'wordpress',
            'gravityforms',
            'cf7',
            'wpforms',
            'ninjaforms',
            'formidable',
            'fluentforms',
            'forminator',
            'sureforms',
            'wsform',
        );
    }

}
