<?php
/**
 * Plugin Name: Events Manager - Currency Per Event
 * Plugin URI: http://www.andyplace.co.uk
 * Description: Plugin for Events Manager that allows the ticket currency to be configered per event.
 * Version: 1.0
 * Author: Andy Place
 * Author URI: http://www.andyplace.co.uk
 * License: GPL2
 */


/**
 * Add metabox to revents page editor that allows us to configure the currency
 */
function em_curr_adding_custom_meta_boxes( $post ) {

	add_meta_box(
		'em-event-currency',
		__( 'Currency' ),
		'render_curency_meta_box',
		'event',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_event', 'em_curr_adding_custom_meta_boxes', 10, 2 );



function render_curency_meta_box() {
	global $post;

	$curr_value = get_post_meta( $post->ID, '_event_currency', true );

	$currencies = array(
		"EUR" => "Euros",
		"USD" => "U.S. Dollars",
		"GBP" => "British Pounds",
		"CAD" => "Canadian Dollars",
		"AUD" => "Australian Dollars",
		"BRL" => "Brazilian Reais",
		"CZK" => "Czech Koruny",
		"DKK" => "Danish Kroner",
		"HKD" => "Hong Kong Dollars",
		"HUF" => "Hungarian Forints",
		"ILS" => "Israeli New Shekels",
		"JPY" => "Japanese Yen",
		"MYR" => "Malaysian Ringgit",
		"MXN" => "Mexican Pesos",
		"TWD" => "New Taiwan Dollars",
		"NZD" => "New Zealand Dollars",
		"NOK" => "Norwegian Kroner",
		"PHP" => "Philippine Pesos",
		"PLN" => "Polish Zlotys",
		"SGD" => "Singapore Dollars",
		"SEK" => "Swedish Kronor",
		"CHF" => "Swiss Francs",
		"THB" => "Thai Baht",
		"TRY" => "Turkish Liras",
	);

	?>
	<p><strong>
		<?php _e('Default Currency') ?>: <?php echo esc_html(get_option('dbem_bookings_currency','USD')); ?>
	</strong></p>
	<p>
		<?php _e('The currency for all events is configured under Events -> Settings -> Bookings -> Pricing Options.') ?>
		<?php _e('If you want this event to use a different currency to the above, select from the list below.') ?>
	</p>

	<select name="dbem_bookings_currency">
		<option value="">Use Default</option>
		<?php foreach( $currencies as $key => $currency) : ?>
		<option value="<?php echo $key ?>" <?php echo ($curr_value == $key ? 'selected=selected':'') ?>>
			<?php echo $key ?> - <?php echo $currency ?>
		</option>
		<?php endforeach; ?>
	</select>
	<?php
}


/**
 * Save currency option setting
 */
function em_curr_save_post($post_id, $post) {

	// verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times
	if ( !wp_verify_nonce( $_POST['_emnonce'], 'edit_event' ) ) {
		return $post->ID;
	}

	// Is the user allowed to edit the post or page?
	if ( !current_user_can( 'edit_post', $post->ID ))
		return $post->ID;

	if( $post->post_type == 'revision' )
		return $post->ID; // Don't store custom data twice

	if( isset( $_POST['dbem_bookings_currency'] ) ) {
		update_post_meta( $post->ID, '_event_currency', $_POST['dbem_bookings_currency'] );
	}else{
		delete_post_meta( $post->ID, '_event_currency' );
	}

}
add_action('save_post', 'em_curr_save_post', 1, 2);