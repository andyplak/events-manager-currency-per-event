<?php
/**
 * Plugin Name: Events Manager - Currency Per Event
 * Plugin URI: http://www.andyplace.co.uk
 * Description: Plugin for Events Manager that allows the ticket currency to be configered per event.
 * Version: 1.3
 * Author: Andy Place
 * Author URI: http://www.andyplace.co.uk
 * License: GPL2
 */

if( !function_exists('em_get_currencies') ) {
	add_action( 'admin_notices', 'em_not_activated_currency_error_notice' );
}

function em_not_activated_currency_error_notice() {
	$message = __('Please ensure both Events Manager and Events Manager Pro are enabled for the Currencies per Event plugin to work.', 'em-pro');
	echo '<div class="error"> <p>'.$message.'</p></div>';
}

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

	$currencies = em_get_currencies();

	if( get_option('dbem_multiple_bookings', 0) ) {
		_e('Currencies cannot be set per event when multiple bookings mode is enabled.');
		return;
	}

	$curr_value = get_post_meta( $post->ID, '_event_currency', true );

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
		<?php foreach( $currencies->names as $key => $currency) : ?>
		<option value="<?php echo $key ?>" <?php echo ($curr_value == $key ? 'selected=selected':'') ?>>
			<?php echo $currency ?>
		</option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Hook into front end event submission form and add currency fields
 */
function em_curr_front_event_form_footer() {

	$currencies = em_get_currencies();

	if( get_option('dbem_multiple_bookings', 0) ) {
		return;
	}

	$curr_value = get_post_meta( $post->ID, '_event_currency', true );

	$default_currency = esc_html(get_option('dbem_bookings_currency','USD'));
	?>
	<h3><?php _e( 'Event Currency' ); ?></h3>
	<select name="dbem_bookings_currency">
		<option><?php echo $default_currency ?> - <?php echo $currencies->names[ $default_currency ] ?></option>
		<option disabled>------------------</option>
		<?php foreach( $currencies->names as $key => $currency) : ?>
		<option value="<?php echo $key ?>">
			<?php echo $key ?> - <?php echo $currency ?>
		</option>
		<?php endforeach; ?>
	</select>
	<?php

}
add_action('em_front_event_form_footer', 'em_curr_front_event_form_footer');


/**
 * Save currency option setting
 */
function em_curr_save_post($post_id, $post) {

	// verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times
	if ( !wp_verify_nonce( $_POST['_emnonce'], 'edit_event' ) &&
		   !wp_verify_nonce( $_POST['_wpnonce'], 'wpnonce_event_save' ) ) {
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


/************** Modify currency in booking admin ********************/

/**
 * Add our custom column for the total with the correct currency to the column template
 */
function em_curr_bookings_table_cols_template( $cols_template ) {
	if( is_admin() ) {

		if( isset( $cols_template['booking_price'] ) ) {
			unset( $cols_template['booking_price'] );
		}
		$cols_template['booking_currency_price'] = 'Total';
	}
	return $cols_template;
}
add_filter('em_bookings_table_cols_template', 'em_curr_bookings_table_cols_template', 10, 1);


/**
 * Ensure that our custom column is actually included where required
 */
function em_curr_bookings_table( $EM_Bookings_Table ) {
	if( !in_array('booking_currency_price', $EM_Bookings_Table->cols) ) {
		$EM_Bookings_Table->cols[] = 'booking_currency_price';

		// reorder array so actions at the end
		if(($key = array_search('actions', $EM_Bookings_Table->cols)) !== false) {
			unset($EM_Bookings_Table->cols[$key]);
			$EM_Bookings_Table->cols[] = 'actions';
		}
	}
}
add_action('em_bookings_table', 'em_curr_bookings_table', 10, 1);


/**
 * Deal with displaying the output of the total with correct currency in our custom column
 */
function em_curr_bookings_table_rows_col_booking_currency_price($val, $EM_Booking, $EM_Bookings_Table, $csv) {

	$EM_Event = $EM_Booking->get_event();

	if( get_post_meta( $EM_Event->post_id, '_event_currency', true ) ) {
		$price = $EM_Booking->get_price(false);
		$currency = get_post_meta( $EM_Event->post_id, '_event_currency', true );
		$format = get_option('dbem_bookings_currency_format','@#');
		$formatted_price = str_replace('@', em_get_currency_symbol(true,$currency), $format);
		$formatted_price = str_replace('#', number_format( $price, 2, get_option('dbem_bookings_currency_decimal_point','.'), get_option('dbem_bookings_currency_thousands_sep',',') ), $formatted_price);
	}else{
		$formatted_price = $EM_Booking->get_price(true);
	}
	return $formatted_price;
}
add_filter('em_bookings_table_rows_col_booking_currency_price', 'em_curr_bookings_table_rows_col_booking_currency_price', 10, 5);



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


/**
 * Hook into PayPal Chained payments and set currency if configured for the booking event
 */
function em_curr_gateway_paypal_chained_paypal_request_data( $pay_pal_request_data, $EM_Booking ) {

	// Skip if multi bookings is enabled
	if( get_option('dbem_multiple_bookings') == 1 ) {
		return $paypal_vars;
	}

	$EM_Event = $EM_Booking->get_event();
	if( get_post_meta( $EM_Event->post_id, '_event_currency', true ) ) {
		$pay_pal_request_data['PayRequestFields']['CurrencyCode']
			= get_post_meta( $EM_Event->post_id, '_event_currency', true );
	}

	return $pay_pal_request_data;
}
add_filter('em_gateway_paypal_chained_paypal_request_data', 'em_curr_gateway_paypal_chained_paypal_request_data', 10, 2);