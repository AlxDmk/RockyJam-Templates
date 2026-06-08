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

	// ---- CodeMirror editor ----
	$( document ).ready( function () {
		var $textarea = $( '#template_content' );

		if ( ! $textarea.length ) {
			return;
		}

		if (
			window.wp &&
			window.wp.codeEditor &&
			window.RjtAdmin.cmSettings
		) {
			window.wp.codeEditor.initialize( $textarea[0], window.RjtAdmin.cmSettings );
		}
	} );

} )( jQuery );
