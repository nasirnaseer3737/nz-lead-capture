<?php
/**
 * Lead storage: a dedicated custom table, accessed only through $wpdb
 * prepared statements. No raw user input ever reaches a query.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NZLC_Storage {

    public static function init() {
        // Storage is used on demand; no hooks are required to boot it.
    }

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . NZLC_TABLE;
    }

    public static function allowed_statuses() {
        return array( 'new', 'contacted', 'won', 'lost' );
    }

    public static function allowed_services() {
        return array(
            'web-development' => __( 'Web Development', 'nz-lead-capture' ),
            'wordpress'       => __( 'WordPress Development', 'nz-lead-capture' ),
            'ai-automation'   => __( 'AI Automation', 'nz-lead-capture' ),
            'ai-seo'          => __( 'AI SEO', 'nz-lead-capture' ),
            'lead-generation' => __( 'Lead Generation', 'nz-lead-capture' ),
            'other'           => __( 'Something else', 'nz-lead-capture' ),
        );
    }

    /**
     * Insert a validated lead. Returns the new row ID, or 0 on failure.
     *
     * @param array $data Sanitized lead fields.
     */
    public static function insert( array $data ) {
        global $wpdb;
        $inserted = $wpdb->insert(
            self::table(),
            array(
                'name'       => $data['name'],
                'email'      => $data['email'],
                'phone'      => isset( $data['phone'] ) ? $data['phone'] : '',
                'service'    => isset( $data['service'] ) ? $data['service'] : '',
                'message'    => $data['message'],
                'status'     => 'new',
                'source'     => isset( $data['source'] ) ? $data['source'] : '',
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id )
        );
    }

    /**
     * Paginated list, optionally filtered by status, newest first.
     */
    public static function list( $status = '', $page = 1, $per_page = 20 ) {
        global $wpdb;
        $page     = max( 1, (int) $page );
        $per_page = min( 100, max( 1, (int) $per_page ) );
        $offset   = ( $page - 1 ) * $per_page;
        if ( $status && in_array( $status, self::allowed_statuses(), true ) ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM ' . self::table() . ' WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d',
                    $status, $per_page, $offset
                )
            );
            $total = (int) $wpdb->get_var(
                $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE status = %s', $status )
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC LIMIT %d OFFSET %d',
                    $per_page, $offset
                )
            );
            $total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
        }
        return array( 'rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per_page );
    }

    public static function count_by_status() {
        global $wpdb;
        $counts = array_fill_keys( self::allowed_statuses(), 0 );
        $rows   = $wpdb->get_results(
            'SELECT status, COUNT(*) AS c FROM ' . self::table() . ' GROUP BY status'
        );
        foreach ( $rows as $row ) {
            if ( isset( $counts[ $row->status ] ) ) {
                $counts[ $row->status ] = (int) $row->c;
            }
        }
        $counts['all'] = array_sum( $counts );
        return $counts;
    }

    public static function update_status( $id, $status ) {
        global $wpdb;
        if ( ! in_array( $status, self::allowed_statuses(), true ) ) {
            return false;
        }
        return false !== $wpdb->update(
            self::table(),
            array( 'status' => $status ),
            array( 'id' => (int) $id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    public static function delete( $id ) {
        global $wpdb;
        return false !== $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
    }

    /**
     * Stream all leads as CSV. Called from the admin export action, after a
     * capability + nonce check in the admin class — never directly.
     */
    public static function export_csv() {
        global $wpdb;
        $rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC', ARRAY_A );
        $out  = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'ID', 'Name', 'Email', 'Phone', 'Service', 'Message', 'Status', 'Source', 'Created' ) );
        foreach ( $rows as $row ) {
            fputcsv( $out, $row );
        }
        fclose( $out );
    }
}
