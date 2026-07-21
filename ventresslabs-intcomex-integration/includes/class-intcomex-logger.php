<?php
/**
 * Logger for IWS API calls.
 *
 * Records every request made to Intcomex Web Services in a custom table
 * so TI Intcomex can validate logs during the TEST go-live (Sección 6
 * of the IWS guide). Also exposes an admin viewer.
 *
 * @link       https://ventresslabs.com/
 * @since      1.1.0
 *
 * @package    VentressLabs_Intcomex
 * @subpackage VentressLabs_Intcomex/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VentressLabs_Intcomex_Logger {

    /**
     * Option key where the latest N entries are stored.
     * Using wp_options to avoid creating DB tables for now; entries are
     * capped and rotated automatically.
     */
    const OPTION_KEY = 'ventresslabs_intcomex_logs';

    /**
     * Maximum number of entries to retain.
     */
    const MAX_ENTRIES = 500;

    /**
     * Log a single API call.
     *
     * @since 1.1.0
     * @param array $data {
     *     @type string $environment
     *     @type string $method
     *     @type string $url
     *     @type mixed  $request_body
     *     @type int    $response_code
     *     @type mixed  $response_body
     *     @type string $wp_error
     *     @type float  $elapsed_ms
     * }
     */
    public function log_call( array $data ) {
        $entries = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $entries ) ) {
            $entries = array();
        }

        $entry = wp_parse_args( array(
            'timestamp'     => current_time( 'mysql' ),
            'timestamp_utc' => gmdate( 'Y-m-d\TH:i:s\Z' ),
        ), $data );

        // Normalize bodies to strings truncating if too long.
        foreach ( array( 'request_body', 'response_body' ) as $key ) {
            if ( is_array( $entry[ $key ] ) || is_object( $entry[ $key ] ) ) {
                $entry[ $key ] = wp_json_encode( $entry[ $key ] );
            }
            if ( is_string( $entry[ $key ] ) && mb_strlen( $entry[ $key ] ) > 4096 ) {
                $entry[ $key ] = mb_substr( $entry[ $key ], 0, 4096 ) . '…[truncated]';
            }
        }

        $entries[] = $entry;

        // Rotate oldest entries beyond the cap.
        if ( count( $entries ) > self::MAX_ENTRIES ) {
            $entries = array_slice( $entries, -self::MAX_ENTRIES );
        }

        update_option( self::OPTION_KEY, $entries, false );
    }

    /**
     * Get all stored log entries (newest last).
     *
     * @since 1.1.0
     * @return array
     */
    public function get_entries() {
        $entries = get_option( self::OPTION_KEY, array() );
        return is_array( $entries ) ? $entries : array();
    }

    /**
     * Get the most recent N entries (newest first).
     *
     * @since 1.1.0
     * @param int $limit Number of entries.
     * @return array
     */
    public function get_recent( $limit = 100 ) {
        $entries = $this->get_entries();
        return array_slice( array_reverse( $entries ), 0, absint( $limit ) );
    }

    /**
     * Clear all stored log entries.
     *
     * @since 1.1.0
     */
    public function clear() {
        delete_option( self::OPTION_KEY );
    }
}
