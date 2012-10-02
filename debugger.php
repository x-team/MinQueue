<?php


function mph_minify_debugger_style() {

	?>

	<style>
		#mph-minify-debugger { position: fixed; top: 50px; right: 30px; width: 160px; border-radius: 10px; background: rgba(0,0,0,0.8); border: 1px solid rgba(0,0,0,0.5); color: #FFF; padding: 15px 30px; margin-bottom: 30px; }
		#mph-minify-debugger h2 { font-family: sans-serif; font-size: 18px; line-height: normal; letter-spacing: normal; color: #FFF; font-size: 12px; font-family: verdana, sans-serif; }
		#mph-minify-debugger ul { margin-bottom: 20px; }
		#mph-minify-debugger ul,
		#mph-minify-debugger li { list-style-position: outside; padding: 0; margin-left: 0; margin-right: 0; font-size: 10px; font-family: verdana, sans-serif; }
		#mph-minify-debugger li.header { color: orange;}
		#mph-minify-debugger li.footer { color: yellow;}

	</style>

	<?php

}


function mph_minify_debugger( $minify_scripts, $minify_styles ) {
	
	global $wp_scripts, $wp_styles;

	$script_queue = $minify_scripts->get_asset_queue();
	$style_queue = $minify_styles->get_asset_queue();

	echo '<div id="mph-minify-debugger">';

	echo '<h2>Enqueued Scripts</h2>';
	echo '<ul>';

	foreach( $wp_scripts->queue as $handle )
		if ( 0 === strpos( $handle, 'mph-minify' ) )
			continue;
		elseif ( ! empty( $script_queue[0] ) && array_key_exists( $handle, $script_queue[0] ) )
			echo '<li class="header">' . $handle . '</li>';
		elseif ( ! empty( $script_queue[1] ) && array_key_exists( $handle, $script_queue[1] ) )
			echo '<li class="footer">' . $handle . '</li>';
		else
			echo '<li>' . $handle . '</li>';
	
	echo '</ul>';
	
	echo '<h2>Enqueued Styles</h2>';
	echo '<ul>';
	
	foreach( $wp_styles->queue as $handle )
		if ( 0 === strpos( $handle, 'mph-minify' ) )
			continue;
		elseif ( ! empty( $style_queue[0] ) && array_key_exists( $handle, $style_queue[0] ) )
			echo '<li class="header">' . $handle . '</li>';
		elseif ( ! empty( $style_queue[1] ) && array_key_exists( $handle, $style_queue[1] ) )
			echo '<li class="footer">' . $handle . '</li>';
		else
			echo '<li>' . $handle . '</li>';

	echo '</ul>';

	echo '<h2>Key</h2><ul><li class="header">Orange: Minified in header</li><li class="footer">Yellow: Minified in footer</li><li>White: not minified.</li></ul>';

	echo '</div>';

}