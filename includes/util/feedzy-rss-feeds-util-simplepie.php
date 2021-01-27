<?php
/**
 * The class that contains a custom implementation of SimplePie.
 *
 * @link       http://themeisle.com
 *
 * @package    feedzy-rss-feeds
 * @subpackage feedzy-rss-feeds/includes/util
 */

if ( ! class_exists( 'SimplePie' ) ) {
	require_once( ABSPATH . WPINC . '/class-simplepie.php' );
	require_once( ABSPATH . WPINC . '/class-wp-feed-cache-transient.php' );
	require_once( ABSPATH . WPINC . '/class-wp-simplepie-file.php' );
}

/**
 * The class that contains a custom implementation of SimplePie.
 *
 * Class that contains a custom implementation of SimplePie.
 *
 * @package    feedzy-rss-feeds
 * @subpackage feedzy-rss-feeds/includes/util
 * @author     Themeisle <friends@themeisle.com>
 */
class Feedzy_Rss_Feeds_Util_SimplePie extends SimplePie {

	/**
	 * The shortcode attributes.
	 *
	 * @access   private
	 * @var      array $sc The shortcode attributes.
	 */
	private static $sc;

	/**
	 * Whether custom sorting is enabled.
	 *
	 * @access   private
	 * @var      bool $custom_sorting Whether custom sorting is enabled.
	 */
	private static $custom_sorting = false;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @access  public
	 *
	 * @param   array $sc The shortcode attributes.
	 */
	public function __construct( $sc ) {
		self::$sc = $sc;
		if ( array_key_exists( 'sort', self::$sc ) && ! empty( self::$sc['sort'] ) ) {
			if ( 'date_desc' === self::$sc['sort'] ) {
				$this->enable_order_by_date( true );
			} else {
				self::$custom_sorting = true;
			}
		}
		parent::__construct();
	}

	/**
	 * Sorting callback for items
	 *
	 * @access public
	 * @param SimplePie $a The SimplePieItem.
	 * @param SimplePie $b The SimplePieItem.
	 * @return boolean
	 */
	public static function sort_items( $a, $b ) {
		if ( self::$custom_sorting ) {
			switch ( self::$sc['sort'] ) {
				case 'title_desc':
					return $a->get_title() <= $b->get_title();
				case 'title_asc':
					return $a->get_title() > $b->get_title();
				case 'date_asc':
					return $a->get_date( 'U' ) > $b->get_date( 'U' );
			}
		}
		return parent::sort_items( $a, $b );
	}

	/**
	 * Coping method for JSON to XML mapping
	 *
	 * @access public
	 * @param $json_items The JSON Items which has to be copied
	 * @return NONE VOID Method
	 */
	public function copy( $json_items ) {
		// Perform Manual Mapping first
		$this->feed_url = !empty( $json_items[ "feed_url" ] ) ? $json_items[ "feed_url" ] : "";
		$this->permanent_url = !empty( $json_items[ "feed_url" ] ) ? $json_items[ "feed_url" ] : "";

		// Perform Auto Mapping on the Channel
		if ( !empty( $json_items ) ) {
			foreach ( $json_items as $item_key => $item_value ) {
				$this->data[ "child" ][ "" ][ "rss" ][ 0 ][ "child" ][ "" ][ "channel" ][ 0 ][ $item_key ] = $item_value;
			}
		}
	}
}
