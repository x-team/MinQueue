<?php

class MPH_Minify {

	// Useful stuff.
	public $plugin_url;
	public $minify_url;
	public $cache_url;

	// Do not minify these assets - array of handles.
	public $ignore_list = array();
	public $force_list = array();

	// The class. Must be a sub class of WP_Dependencies. Either WP_Scripts or WP_Styles.
	private $class;

	// If true, minified files are cached. Else minify done on the fly. 
	private $cache = true;

	// Internal queue of assets to be minified. By group. Stores handle & version - used for creating filename.
	private $asset_queue = array();
	
	// Array of script Localization data.
	private $script_localization = array();

	/**
	 * Set up class.
	 * @param string $class Minify assets for this class.
	 */
	function __construct( $class ) {

		// Set up which WP_Dependencies sub-class to use. 
		if ( 'WP_Scripts' ==  $class ) {
			
			global $wp_scripts;
			$this->class = $wp_scripts;

		} elseif ( 'WP_Styles' ==  $class ) {
		
			global $wp_styles;
			$this->class = $wp_styles;
		
		}

		if ( empty( $this->class ) )
			return;

		// Error?
		if ( ! is_subclass_of( $this->class, 'WP_Dependencies' ) )
			die( get_class( $this->class ) . ' does not extend WP_Dependencies' );

		$this->plugin_url    = plugins_url( basename( __DIR__ ) );
		$this->minify_url    = trailingslashit( $this->plugin_url ) . 'php-minify/min/';
		
		$this->cache_dirname = trailingslashit( 'mph_minify_cache' );
		$this->cache_url     = trailingslashit( WP_CONTENT_URL ) . $this->cache_dirname;
		$this->cache_dir     = trailingslashit( WP_CONTENT_DIR ) . $this->cache_dirname;

	}


	/**
	 * Action! Run the minifier.
	 * 
	 * @return null
	 */
	function minify() {

		if ( empty( $this->class ) )
			return;
		
		foreach ( (array) $this->get_asset_queue() as $group => $assets  )
			$this->enqueue_minified_assets( $group );	

		// Add the localization data to the head. Do it as early as possible.
		if ( ! empty( $this->script_localization ) )
			add_action( 'wp_head', array( $this, 'script_localization' ), 2 );

	}

	/**
	 * Get the queue of assets for a given class.
	 * 
	 * @param  class $class  type of asset (wp_scripts of wp_styles)
	 * @return array asset queue. An array of classes. Contains array of groups. contains array of asset handles.
	 */
	function get_asset_queue() {

		// Do this to set up the todos - in the correct order.	
		$this->class->all_deps( array_merge( $this->class->queue, $this->force_list ) );
		
  		foreach ( $this->class->to_do as $key => $handle ) {

			// If this script is ignored, skip it.
			if ( in_array( $handle, $this->ignore_list ) ) {
				continue;
			}

			// Add this asset to the queue.
			$this->asset_queue[ $this->class->groups[$handle] ][$handle] = array( 
				'handle' => $handle,
				'version' => $this->class->registered[$handle]->ver
			);

			if ( ! empty( $this->class->registered[$handle]->extra['data'] ) )
				$this->script_localization[ $handle ] = $this->class->registered[$handle]->extra['data'];

		}

		return $this->asset_queue;

	}

	/**
	 * Process Assets. 
	 *
	 * Enqueue cached minified file or create one and enqueue that.
	 * 
	 * @param  int $group Group identifier
	 * @return null
	 */
	function enqueue_minified_assets( $group ) {

		// Filename is a crc32 hash of the current group asset queue (contains version numbers)
		$filename = crc32( serialize( $this->asset_queue[$group] ) ) . ( ( 'WP_Styles' === get_class( $this->class ) ) ? '.css' : '.js' );		
		$src = trailingslashit( $this->cache_url ) . $filename;
		$minify_handle = 'mph-minify-' . get_class( $this->class ) . '-' . $group;

		// If no cached file - generate minified asset src.
		if ( ! file_exists( $this->cache_dir . $filename ) ) {

			$_srcs = array();
			foreach ( $this->asset_queue[$group] as $asset )
				$_srcs[] = $this->get_asset_path( $asset['handle'] );

			// On the fly minify url - used to generate the cache.
			$src = $this->minify_url . '/?f=' . implode( ',', array_filter( $_srcs ) );

			if ( $this->cache )
				$src = $this->cache_file( $filename, $src );

		}

		// Mark the minified assets as done.
		foreach ( $this->asset_queue[$group] as $asset )
			$this->class->done[] = $asset['handle'];

		// If any of the assets in this file are dependencies of any other registered files, we need to add the minified file as a dependancy.
		// Array keys = asset handles in this file.
		foreach ( $this->class->registered as $asset )
			if ( ! empty( $asset->deps ) )
				if ( array_intersect( $asset->deps, array_keys( $this->asset_queue[$group] ) ) ) 
					$asset->deps[] = $minify_handle;

		// Enqueue the minified file
		$this->class->add( $minify_handle, $src, null, null );
		$this->class->add_data( $minify_handle, 'group', $group );
		$this->class->enqueue( $minify_handle );

	}

	/**
	 * Localize the minified scripts. Echo script tags in the head.
	 *
	 * @return null
	 * @todo - look into properly adding this using the wp_scripts class. Can we actually localize the minified script? or use print_inline_style.
	 */
	function script_localization () {

		foreach ( $this->script_localization as $handle => $data )
			echo '<script>' . $data . '</script>';

	}

	/**
	 * Return the path to an asset relative to the site root, Uses $wp_scripts->registered.
	 * 
	 * @param  string $handle handle of the asset
	 * @return string         string, path of the asset, relative to site root.
	 */
	function get_asset_path( $handle ) {

		if ( empty( $this->class->registered[$handle] ) )
			return;

		$src = $this->class->registered[$handle]->src;

		if ( ! preg_match('|^(https?:)?//|', $src) && ! ( $this->class->content_url && 0 === strpos( $src, $this->class->content_url ) ) )
			$src = $this->class->base_url . $src;

		// Don't handle remote urls. For now...
		if ( 0 !== strpos( $src, home_url() ) )
			return;

		return str_replace( home_url(), '', $src );

	}

	/**
	 * Create Cache file. 
	 * 
	 * @param  string $filename name used to create file. A hash of args.
	 * @param  array  $srcs     srcs of assets.
	 * @return string           src of cache file.
	 */
	function cache_file( $filename, $minify_src ) {

		// Create Directory.
		if ( ! is_dir( $this->cache_dir ) )
			mkdir( $this->cache_dir );

		$data = file_get_contents( $minify_src );

		if ( $data ) {
			
			file_put_contents( $this->cache_dir . $filename, $minify_src . "/n" . $data );	
		
			return $this->cache_url . $filename;

		}
		
	}

	/**
	 * Delete all cached files.
	 * 
	 * @return null
	 */
	function delete_cache() { 

		// @todo

	}

}
