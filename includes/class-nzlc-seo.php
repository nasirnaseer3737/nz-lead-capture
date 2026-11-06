<?php
/**
 * Lightweight on-page SEO: only fills gaps, never overwrites an SEO
 * plugin. If a description or OG tags already exist, this stays quiet.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NZLC_SEO {

    public static function init() {
        add_action( 'wp_head', array( __CLASS__, 'output' ), 5 );
    }

    public static function enabled() {
        return '1' === get_option( 'nzlc_seo_enabled', '1' );
    }

    /**
     * Build the fallback description: the page excerpt, then the site
     * tagline. Capped at 160 characters, as search snippets expect.
     */
    public static function description() {
        if ( is_singular() ) {
            $text = get_the_excerpt();
            if ( $text ) {
                return mb_substr( wp_strip_all_tags( $text ), 0, 160 );
            }
        }
        return mb_substr( get_bloginfo( 'description' ), 0, 160 );
    }

    public static function output() {
        if ( ! self::enabled() || is_admin() ) {
            return;
        }
        // Respect dedicated SEO plugins when they are active.
        if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) {
            return;
        }
        $desc = self::description();
        if ( $desc ) {
            echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
        }
        if ( is_singular() ) {
            echo '<meta property="og:title" content="' . esc_attr( get_the_title() ) . '" />' . "\n";
            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( get_permalink() ) . '" />' . "\n";
            if ( $desc ) {
                echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
            }
        }
        $business = get_option( 'nzlc_business_name', get_bloginfo( 'name' ) );
        if ( $business && is_front_page() ) {
            $schema = array(
                '@context'    => 'https://schema.org',
                '@type'       => 'LocalBusiness',
                'name'        => $business,
                'url'         => home_url( '/' ),
                'description' => $desc,
            );
            echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
        }
    }
}
