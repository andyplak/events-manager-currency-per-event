<?php
/**
 * Plugin Name: Events Manager - Currency Per Event
 * Plugin URI: http://www.andyplace.co.uk
 * Description: Plugin for Events Manager that allows the ticket currency to be configered per event.
 * Version: 1.1
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


/**
 * Render metabox with currency options. The list was the same as those included with
 * Events Manager at the time of writing.
 * Note, this option is disabled when in Multiple Bookings mode
 */
function render_curency_meta_box() {
	global $post;

	if( get_option('dbem_multiple_bookings', 0) ) {
		_e('Currencies cannot be set per event when multiple bookings mode is enabled.');
		return;
	}

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



/************ Modify Ticket price display ************/

/*
 * We can't access ticket info in the format price hook, so we need to store
 * globally the currency that is to be converted from an earlier hook where we can
 * see the event details
 */
$modify_currency = false; // Dubious global var that we use to get round filters limitation


/**
 * Hook into EM_Ticket->get_price() and detect if the event currency is non standard
 * Store the currency value into our global var if requried.
 */
function em_curr_ticket_get_price( $ticket_price, $EM_Ticket ) {
	global $modify_currency;

	$EM_Event = $EM_Ticket->get_event();

	// Does this event have a custom currency?
	if( get_post_meta( $EM_Event->post_id, '_event_currency', true ) ) {
		// If so we set this to our global $modify_currency var for use later on
		$modify_currency = get_post_meta( $EM_Event->post_id, '_event_currency', true );
	}
	return $ticket_price;
}
add_filter('em_ticket_get_price','em_curr_ticket_get_price', 10, 2);


/**
 * Hook into Events Manager's em_get_currency_formatted function
 * Modify currency symbol if determined previously that this needs changing
 */
function em_curr_get_currency_formatted($formatted_price, $price, $currency, $format) {
	global $modify_currency;

	if( $modify_currency ){
		$formatted_price = str_replace('@', em_get_currency_symbol(true,$modify_currency), $format);
		$formatted_price = str_replace('#', number_format( $price, 2, get_option('dbem_bookings_currency_decimal_point','.'), get_option('dbem_bookings_currency_thousands_sep',',') ), $formatted_price);
	}

	return $formatted_price;
}
add_filter('em_get_currency_formatted', 'em_curr_get_currency_formatted', 10, 4);


/************** Modify Currency for Payment Gateways ****************/

/**
 * Hook into Sage Pay gateway and modify the currency if set on the event
 */
function em_curr_gateway_sage_get_currency( $currency, $EM_Booking ) {

	// Skip if multi bookings is enabled
	if( get_option('dbem_multiple_bookings') == 1 ) {
		return $currency;
	}

	$EM_Event = $EM_Booking->get_event();
	if( get_post_meta( $EM_Event->post_id, '_event_currency', true ) ) {
		$currency = get_post_meta( $EM_Event->post_id, '_event_currency', true );
	}

	return $currency;
}
add_filter('em_gateway_sage_get_currency', 'em_curr_gateway_sage_get_currency', 10, 2);


/**
 * Hook into PayPal vars and modify curreny if set on the event
 */
function em_curr_gateway_paypal_get_paypal_vars($paypal_vars, $EM_Booking, $EM_PayPal_Gateway) {

	// Skip if multi bookings is enabled
	if( get_option('dbem_multiple_bookings') == 1 ) {
		return $paypal_vars;
	}

	$EM_Event = $EM_Booking->get_event();
	if( get_post_meta( $EM_Event->post_id, '_event_currency', true ) ) {
		$paypal_vars['currency_code'] = get_post_meta( $EM_Event->post_id, '_event_currency', true );
	}

	return $paypal_vars;
}
add_filter('em_gateway_paypal_get_paypal_vars', 'em_curr_gateway_paypal_get_paypal_vars', 10, 3);
