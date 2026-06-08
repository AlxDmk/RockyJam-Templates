/**
 * RockyJam Templates — Admin Scripts
 */
( function ( $ ) {
	'use strict';

	// ---- Delete confirmation ----
	$( document ).on( 'submit', '.rjt-delete-form', function ( e ) {
		if ( ! window.confirm( window.RjtAdmin.confirmDelete ) ) {
			e.preventDefault();
		}
	} );

	// ---- Auto-fill slug from name (create form) ----
	$( '#template_name' ).on( 'input', function () {
		var $slug = $( '#template_slug' );
		if ( $slug.length && ! $slug.data( 'manual' ) ) {
			var slug = $( this ).val()
				.toLowerCase().trim()
				.replace( /[^a-z0-9]+/g, '-' )
				.replace( /^-+|-+$/g, '' );
			$slug.val( slug );
		}
	} );
	$( '#template_slug' ).on( 'input', function () {
		$( this ).data( 'manual', $( this ).val().length > 0 );
	} );

	// ---- File tabs ----
	$( document ).ready( function () {
		var $tabs = $( '#rjt-tabs' );
		if ( ! $tabs.length ) {
			return;
		}

		// Activate first tab or the one referenced in URL hash.
		var hash   = window.location.hash.replace( '#', '' );
		var $btns  = $tabs.find( '.rjt-tab-btn' );
		var $panels = $tabs.find( '.rjt-tab-panel' );

		function activateTab( tabId ) {
			$btns.removeClass( 'rjt-tab-btn--active' );
			$panels.hide();

			var $btn   = $btns.filter( '[data-tab="' + tabId + '"]' );
			var $panel = $( '#' + tabId );

			if ( $btn.length ) {
				$btn.addClass( 'rjt-tab-btn--active' );
			} else {
				$btns.first().addClass( 'rjt-tab-btn--active' );
			}

			if ( $panel.length ) {
				$panel.show();
			} else {
				$panels.first().show();
			}
		}

		$btns.on( 'click', function () {
			var tabId = $( this ).data( 'tab' );
			activateTab( tabId );
			history.replaceState( null, '', '#' + tabId );
		} );

		// Init.
		activateTab( hash || $btns.first().data( 'tab' ) );

		// ---- CodeMirror per tab ----
		if ( window.wp && window.wp.codeEditor ) {
			$panels.each( function () {
				var $textarea = $( this ).find( 'textarea.rjt-code-editor' );
				if ( ! $textarea.length ) {
					return;
				}
				var lang     = $textarea.data( 'lang' ) || 'text/html';
				var settings = $.extend( true, {}, window.RjtAdmin.cmSettings || {}, {
					codemirror: { mode: lang, lineNumbers: true, lineWrapping: false }
				} );
				window.wp.codeEditor.initialize( $textarea[0], settings );
			} );
		}
	} );

} )( jQuery );
