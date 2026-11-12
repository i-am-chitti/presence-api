/**
 * Order room client.
 *
 * Joins the open order's presence room on every Heartbeat tick, for the order
 * read from `window.presenceWooOrdersRoom`, and lists who else has it open.
 *
 * @param {jQuery} $ The jQuery instance.
 * @package Presence_Woo_Orders
 */

( function ( $ ) {
	'use strict';

	const config = window.presenceWooOrdersRoom;

	if ( ! config || ! config.orderId ) {
		return;
	}

	const { __, sprintf } = wp.i18n;

	const notice = $(
		'<div class="notice notice-info inline presence-woo-orders-viewers" role="status" hidden><p></p></div>'
	);
	let shown = '';

	$( function () {
		notice.insertAfter( $( '.wp-header-end' ).first() );
	} );

	$( document )
		.on( 'heartbeat-send', function ( event, data ) {
			data[ 'presence-woo-orders' ] = config.orderId;
		} )
		.on( 'heartbeat-tick', function ( event, data ) {
			const names = data[ 'presence-woo-orders' ];
			if ( ! Array.isArray( names ) ) {
				return;
			}

			const text = names.length
				? sprintf(
						/* translators: %s: Comma-separated names of the people who also have the order open. */
						__( 'Also on this order: %s', 'presence-woo-orders' ),
						names.join( ', ' )
					)
				: '';

			// Only a change is announced, so the status region doesn't repeat itself every tick.
			if ( text === shown ) {
				return;
			}
			shown = text;
			notice.find( 'p' ).text( text );
			notice.prop( 'hidden', ! text );
		} );
} )( jQuery );
