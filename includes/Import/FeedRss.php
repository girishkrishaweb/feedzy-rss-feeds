<?php


namespace Feedzy_Rss_Feeds\Import;


class FeedRss implements IFeedProtocolParser
{
    
    protected $feed;
    
    // TODO: It is probably better to pass SimplePie as a parameter but let it be this way for now
    public function __construct()
    {
    
    }
    
    private function createFeedEntity(\SimplePie $feed)
    {
    
    }
    
    // TODO: All RSS, XML or URL validators must be in this class
    
    // TODO: Make input params more reasonable
    public function fetch_feed($feed_url, $cache = '12_hours', $sc)
    {
        // Load SimplePie Instance
        $feed = $this->init_feed( $feed_url, $cache, $sc ); // Not used as log as #41304 is Opened.
    
        // Report error when is an error loading the feed
        if ( is_wp_error( $feed ) ) {
            // Fallback for different edge cases.
            if ( is_array( $feed_url ) ) {
                $feed_url = array_map( 'html_entity_decode', $feed_url );
            } else {
                $feed_url = html_entity_decode( $feed_url );
            }
        
            $feed_url = $this->get_valid_feed_urls( $feed_url, $cache );
        
            $feed = $this->init_feed( $feed_url, $cache, $sc ); // Not used as log as #41304 is Opened.
        }
        
        // TODO: Return RssFeedEntity instance
        
        return $feed;
    }
    
    /**
     *
     * Method to avoid using core implementation in order
     * order to fix issues reported here: https://core.trac.wordpress.org/ticket/41304
     * Bug: #41304 with WP wp_kses sanitizer used by WP SimplePie implementation.
     *
     * NOTE: This is temporary should be removed as soon as #41304 is patched.
     *
     * @since   3.1.7
     * @access  private
     *
     * @param   string $feed_url The feed URL.
     * @param   string $cache The cache string (eg. 1_hour, 30_min etc.).
     * @param   array  $sc The shortcode attributes.
     *
     * @return \SimplePie
     */
    private function init_feed( $feed_url, $cache, $sc, $allow_https = FEEDZY_ALLOW_HTTPS ) {
        // TODO: Refactor Method
        
        $unit_defaults = array(
            'mins'  => MINUTE_IN_SECONDS,
            'hours' => HOUR_IN_SECONDS,
            'days'  => DAY_IN_SECONDS,
        );
        $cache_time    = 12 * HOUR_IN_SECONDS;
        if ( isset( $cache ) && $cache !== '' ) {
            list( $value, $unit ) = explode( '_', $cache );
            if ( isset( $value ) && is_numeric( $value ) && $value >= 1 && $value <= 100 ) {
                if ( isset( $unit ) && in_array( strtolower( $unit ), array( 'mins', 'hours', 'days' ), true ) ) {
                    $cache_time = $value * $unit_defaults[ $unit ];
                }
            }
        }
        
        $feed = new \Feedzy_Rss_Feeds_Util_SimplePie( $sc );
        if ( ! $allow_https && method_exists( $feed, 'set_curl_options' ) ) {
            $feed->set_curl_options(
                array(
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_SSL_VERIFYPEER => false,
                )
            );
        }
        require_once( ABSPATH . WPINC . '/class-wp-feed-cache-transient.php' );
        require_once( ABSPATH . WPINC . '/class-wp-simplepie-file.php' );
        
        $feed->set_file_class( 'WP_SimplePie_File' );
        $default_agent = $this->get_default_user_agent( $feed_url );
        $feed->set_useragent( apply_filters( 'http_headers_useragent', $default_agent ) );
        if ( false === apply_filters( 'feedzy_disable_db_cache', false, $feed_url ) ) {
            \SimplePie_Cache::register( 'wp_transient', 'WP_Feed_Cache_Transient' );
            $feed->set_cache_location( 'wp_transient' );
            add_filter(
                'wp_feed_cache_transient_lifetime', function( $time ) use ( $cache_time ) {
                return $cache_time;
            }, 10, 1
            );
        } else {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            WP_Filesystem();
            global $wp_filesystem;
            
            $dir    = $wp_filesystem->wp_content_dir() . 'uploads/simplepie';
            if ( ! $wp_filesystem->exists( $dir ) ) {
                if ( ( $done = $wp_filesystem->mkdir( $dir ) ) === false ) {
                    do_action( 'themeisle_log_event', FEEDZY_NAME, sprintf( 'Unable to create directory %s', $dir ), 'error', __FILE__, __LINE__ );
                }
            }
            $feed->set_cache_location( $dir );
        }
        
        // Do not use force_feed for multiple URLs.
        $feed->force_feed( apply_filters( 'feedzy_force_feed', ( is_string( $feed_url ) || ( is_array( $feed_url ) && 1 === count( $feed_url ) ) ) ) );
        
        do_action( 'feedzy_modify_feed_config', $feed );
        
        $cloned_feed = clone $feed;
        
        // set the url as the last step, because we need to be able to clone this feed without the url being set
        // so that we can fall back to raw data in case of an error
        $feed->set_feed_url( $feed_url );
        
        $feedzy_current_error_reporting = error_reporting();
        
        // to avoid the Warning! Non-numeric value encountered. This can be removed once SimplePie in core is fixed.
        if ( version_compare( phpversion(), '7.1', '>=' ) ) {
            error_reporting( E_ALL ^ E_WARNING );
            // reset the error_reporting back to its original value.
            add_action(
                'shutdown', function() use ($feedzy_current_error_reporting)  {
                error_reporting( $feedzy_current_error_reporting );
            }
            );
        }
        
        $feed->init();
        
        $error = $feed->error();
        // error could be an array, so let's join the different errors.
        if ( is_array( $error ) ) {
            $error = implode( '|', $error );
        }
        
        if ( ! empty( $error ) ) {
            do_action( 'themeisle_log_event', FEEDZY_NAME, sprintf( 'Error while parsing feed: %s', $error ), 'error', __FILE__, __LINE__ );
            
            // curl: (60) SSL certificate problem: unable to get local issuer certificate
            if ( strpos( $error, 'SSL certificate' ) !== false ) {
                do_action( 'themeisle_log_event', FEEDZY_NAME, sprintf( 'Got an SSL Error (%s), retrying by ignoring SSL', $error ), 'debug', __FILE__, __LINE__ );
                $feed = $this->init_feed( $feed_url, $cache, $sc, false );
            } elseif ( is_string( $feed_url ) || ( is_array( $feed_url ) && 1 === count( $feed_url ) ) ) {
                do_action( 'themeisle_log_event', FEEDZY_NAME, 'Trying to use raw data', 'debug', __FILE__, __LINE__ );
                $data   = wp_remote_retrieve_body( wp_remote_get( $feed_url, array( 'user-agent' => $default_agent ) ) );
                $cloned_feed->set_raw_data( $data );
                $cloned_feed->init();
                $error_raw = $cloned_feed->error();
                if ( empty( $error_raw ) ) {
                    // only if using the raw url produces no errors, will we consider the new feed as good to go.
                    // otherwise we will use the old feed
                    $feed = $cloned_feed;
                }
            } else {
                do_action( 'themeisle_log_event', FEEDZY_NAME, 'Cannot use raw data as this is a multifeed URL', 'debug', __FILE__, __LINE__ );
            }
        }
        return $feed;
    }
    
    /**
     * Change the default user agent based on the feed url.
     *
     * @param string|array $urls Feed urls.
     *
     * @return string Optimal User Agent
     */
    private function get_default_user_agent( $urls ) {
        
        $set = array();
        if ( ! is_array( $urls ) ) {
            $set[] = $urls;
        }
        foreach ( $set as $url ) {
            if ( strpos( $url, 'medium.com' ) !== false ) {
                return FEEDZY_USER_AGENT;
            }
        }
        
        return SIMPLEPIE_USERAGENT;
    }
}