/**
 * RockyJam Templates — Overrides Editor
 * Manages the WC template overrides tab: list, CodeMirror editor, add-modal.
 */
( function () {
	'use strict';

	if ( typeof RjtOverrides === 'undefined' ) return;

	const cfg      = RjtOverrides;
	const ajaxUrl  = cfg.ajaxUrl;
	const nonce    = cfg.nonce;
	const slug     = cfg.slug;
	const i18n     = cfg.i18n;

	let cmEditor      = null; // CodeMirror instance
	let currentPath   = null; // path open in editor
	let isDirty       = false;

	// ── DOM refs ────────────────────────────────────────────────────────────
	const wrap        = document.querySelector( '.rjt-overrides' );
	if ( ! wrap ) return;

	const list        = document.getElementById( 'rjt-overrides-list' );
	const editorWrap  = document.getElementById( 'rjt-override-editor-wrap' );
	const editorTitle = document.getElementById( 'rjt-override-editor-title' );
	const saveStatus  = document.getElementById( 'rjt-override-save-status' );
	const saveBtn     = document.getElementById( 'rjt-override-save-btn' );
	const deleteBtn   = document.getElementById( 'rjt-override-delete-btn' );
	const closeBtn    = document.getElementById( 'rjt-override-close-btn' );
	const addBtn      = document.querySelector( '.rjt-overrides__add-btn' );
	const modal       = document.getElementById( 'rjt-add-override-modal' );
	const addConfirm  = document.getElementById( 'rjt-add-overrides-confirm' );
	const searchInput = document.getElementById( 'rjt-add-overrides-search' );

	// ── Init CodeMirror ──────────────────────────────────────────────────────
	function initCodeMirror() {
		if ( cmEditor ) return;

		const host = document.getElementById( 'rjt-codemirror-host' );
		if ( ! host || typeof CodeMirror === 'undefined' ) return;

		cmEditor = CodeMirror( host, {
			mode:           'application/x-httpd-php',
			theme:          'material-darker',
			lineNumbers:    true,
			matchBrackets:  true,
			autoCloseBrackets: true,
			indentUnit:     4,
			tabSize:        4,
			indentWithTabs: true,
			lineWrapping:   false,
			extraKeys: {
				'Ctrl-S':     () => saveOverride(),
				'Cmd-S':      () => saveOverride(),
				'Ctrl-Space': 'autocomplete',
			},
		} );

		cmEditor.on( 'change', () => {
			isDirty = true;
			saveStatus.textContent = '';
		} );
	}

	// ── Open editor ─────────────────────────────────────────────────────────
	function openEditor( path, label ) {
		if ( isDirty && currentPath && currentPath !== path ) {
			if ( ! confirm( 'You have unsaved changes. Discard them?' ) ) return;
		}

		currentPath = path;
		isDirty     = false;
		saveStatus.textContent = '';
		editorTitle.textContent = label + '  (' + path + ')';
		editorWrap.style.display = '';

		initCodeMirror();

		// Load content via AJAX
		const fd = new FormData();
		fd.append( 'action',   'rjt_get_override_content' );
		fd.append( '_nonce',   nonce );
		fd.append( 'slug',     slug );
		fd.append( 'tpl_path', path );

		fetch( ajaxUrl, { method: 'POST', body: fd } )
			.then( r => r.json() )
			.then( res => {
				if ( ! res.success ) {
					alert( i18n.errorLoad );
					return;
				}
				cmEditor.setValue( res.data.content );
				cmEditor.clearHistory();
				isDirty = false;

				// Show hint if this is the WC default (not yet saved as override)
				if ( ! res.data.exists ) {
					saveStatus.textContent = i18n.wcDefault;
					saveStatus.className = 'rjt-overrides__save-status rjt-overrides__save-status--hint';
				}

				// Scroll to top
				cmEditor.scrollTo( 0, 0 );
				cmEditor.refresh();
			} );
	}

	// ── Save ────────────────────────────────────────────────────────────────
	function saveOverride() {
		if ( ! currentPath ) return;

		saveStatus.textContent = i18n.saving;
		saveStatus.className   = 'rjt-overrides__save-status';

		const fd = new FormData();
		fd.append( 'action',   'rjt_save_override' );
		fd.append( '_nonce',   nonce );
		fd.append( 'slug',     slug );
		fd.append( 'tpl_path', currentPath );
		fd.append( 'content',  cmEditor.getValue() );

		fetch( ajaxUrl, { method: 'POST', body: fd } )
			.then( r => r.json() )
			.then( res => {
				if ( ! res.success ) {
					saveStatus.textContent = '⚠ ' + ( res.data?.message || i18n.errorSave );
					saveStatus.className   = 'rjt-overrides__save-status rjt-overrides__save-status--error';
					return;
				}
				isDirty = false;
				saveStatus.textContent = '✓ ' + i18n.saved;
				saveStatus.className   = 'rjt-overrides__save-status rjt-overrides__save-status--ok';

				// Ensure row appears in list (first save)
				ensureRowInList( currentPath );
			} );
	}

	// ── Delete ───────────────────────────────────────────────────────────────
	function deleteOverride() {
		if ( ! currentPath ) return;
		if ( ! confirm( i18n.confirmDelete ) ) return;

		const fd = new FormData();
		fd.append( 'action',   'rjt_delete_override' );
		fd.append( '_nonce',   nonce );
		fd.append( 'slug',     slug );
		fd.append( 'tpl_path', currentPath );

		fetch( ajaxUrl, { method: 'POST', body: fd } )
			.then( r => r.json() )
			.then( res => {
				if ( ! res.success ) {
					alert( res.data?.message || 'Error' );
					return;
				}
				// Remove row from list
				const row = list.querySelector( `.rjt-override-row[data-path="${ CSS.escape( currentPath ) }"]` );
				if ( row ) row.remove();

				// Close editor
				closeEditor();

				// Show empty state if no rows left
				if ( ! list.querySelector( '.rjt-override-row' ) ) {
					list.innerHTML = '<p class="rjt-overrides__empty">' +
						'No overrides yet. Click &ldquo;Add Override&rdquo; to start.</p>';
				}
			} );
	}

	// ── Close editor ────────────────────────────────────────────────────────
	function closeEditor() {
		if ( isDirty ) {
			if ( ! confirm( 'You have unsaved changes. Discard them?' ) ) return;
		}
		editorWrap.style.display = 'none';
		currentPath = null;
		isDirty     = false;
	}

	// ── Ensure row exists in list ────────────────────────────────────────────
	function ensureRowInList( path ) {
		if ( list.querySelector( `.rjt-override-row[data-path="${ CSS.escape( path ) }"]` ) ) return;

		// Remove empty state
		const empty = list.querySelector( '.rjt-overrides__empty' );
		if ( empty ) empty.remove();

		const row = buildRow( path, path );
		list.appendChild( row );
	}

	// ── Build a row element ──────────────────────────────────────────────────
	function buildRow( path, label ) {
		const div = document.createElement( 'div' );
		div.className = 'rjt-override-row';
		div.dataset.path = path;
		div.innerHTML = `
			<span class="rjt-override-row__icon dashicons dashicons-media-code"></span>
			<span class="rjt-override-row__label">${ escHtml( label ) }</span>
			<code class="rjt-override-row__path">${ escHtml( path ) }</code>
			<div class="rjt-override-row__actions">
				<button type="button" class="button rjt-override-row__edit-btn"
					data-path="${ escHtml( path ) }" data-label="${ escHtml( label ) }">Edit</button>
				<button type="button" class="button rjt-btn--danger rjt-override-row__delete-btn"
					data-path="${ escHtml( path ) }">Delete</button>
			</div>`;
		return div;
	}

	// ── Add-override modal ───────────────────────────────────────────────────
	function openModal() {
		modal.style.display = 'flex';
		if ( searchInput ) searchInput.value = '';
		filterModalItems( '' );
	}

	function closeModal() {
		modal.style.display = 'none';
	}

	function filterModalItems( q ) {
		const rows = modal.querySelectorAll( '.rjt-add-overrides__item' );
		const lq   = q.toLowerCase();
		rows.forEach( row => {
			const text = row.textContent.toLowerCase();
			row.style.display = ( ! lq || text.includes( lq ) ) ? '' : 'none';
		} );

		// Hide section titles if all items in the section are hidden
		modal.querySelectorAll( '.rjt-add-overrides__section' ).forEach( section => {
			const visible = section.querySelectorAll( '.rjt-add-overrides__item:not([style*="display: none"])' );
			section.style.display = visible.length ? '' : 'none';
		} );
	}

	function confirmAddOverrides() {
		const checked = Array.from( modal.querySelectorAll( 'input[name="rjt_override_paths[]"]:checked' ) );
		const paths   = checked.map( cb => cb.value );

		if ( ! paths.length ) {
			alert( i18n.noneSelected );
			return;
		}

		const fd = new FormData();
		fd.append( 'action',  'rjt_add_overrides' );
		fd.append( '_nonce',  nonce );
		fd.append( 'slug',    slug );
		paths.forEach( p => fd.append( 'paths[]', p ) );

		fetch( ajaxUrl, { method: 'POST', body: fd } )
			.then( r => r.json() )
			.then( res => {
				if ( ! res.success ) {
					alert( res.data?.message || 'Error' );
					return;
				}
				// Add rows to list and remove from modal checkboxes
				const added = res.data.added || [];
				const empty = list.querySelector( '.rjt-overrides__empty' );
				if ( empty && added.length ) empty.remove();

				added.forEach( path => {
					// Find label from checkbox
					const cb = modal.querySelector( `input[value="${ CSS.escape( path ) }"]` );
					const label = cb ? cb.closest( '.rjt-add-overrides__item' )
						?.querySelector( '.rjt-add-overrides__label' )?.textContent || path : path;

					ensureRowInListWithLabel( path, label );

					// Remove from modal
					if ( cb ) cb.closest( '.rjt-add-overrides__item' ).remove();
				} );

				closeModal();
			} );
	}

	function ensureRowInListWithLabel( path, label ) {
		if ( list.querySelector( `.rjt-override-row[data-path="${ CSS.escape( path ) }"]` ) ) return;
		const row = buildRow( path, label );
		list.appendChild( row );
	}

	// ── Event delegation ─────────────────────────────────────────────────────
	list.addEventListener( 'click', e => {
		const editBtn   = e.target.closest( '.rjt-override-row__edit-btn' );
		const deleteBtn = e.target.closest( '.rjt-override-row__delete-btn' );

		if ( editBtn ) {
			openEditor( editBtn.dataset.path, editBtn.dataset.label || editBtn.dataset.path );
		}
		if ( deleteBtn ) {
			const row = deleteBtn.closest( '.rjt-override-row' );
			const path = deleteBtn.dataset.path;
			// Quick delete without opening editor
			if ( ! confirm( i18n.confirmDelete ) ) return;

			const fd = new FormData();
			fd.append( 'action',   'rjt_delete_override' );
			fd.append( '_nonce',   nonce );
			fd.append( 'slug',     slug );
			fd.append( 'tpl_path', path );

			fetch( ajaxUrl, { method: 'POST', body: fd } )
				.then( r => r.json() )
				.then( res => {
					if ( ! res.success ) { alert( res.data?.message || 'Error' ); return; }
					if ( row ) row.remove();
					if ( currentPath === path ) closeEditor();
					if ( ! list.querySelector( '.rjt-override-row' ) ) {
						list.innerHTML = '<p class="rjt-overrides__empty">No overrides yet. Click &ldquo;Add Override&rdquo; to start.</p>';
					}
				} );
		}
	} );

	saveBtn.addEventListener(   'click', saveOverride );
	deleteBtn.addEventListener( 'click', deleteOverride );
	closeBtn.addEventListener(  'click', closeEditor );
	addBtn.addEventListener(    'click', openModal );
	addConfirm.addEventListener( 'click', confirmAddOverrides );

	modal.querySelector( '.rjt-modal__backdrop' )?.addEventListener( 'click', closeModal );
	modal.querySelectorAll( '.rjt-modal__close' ).forEach( b => b.addEventListener( 'click', closeModal ) );

	if ( searchInput ) {
		searchInput.addEventListener( 'input', () => filterModalItems( searchInput.value ) );
	}

	// ── Utility ──────────────────────────────────────────────────────────────
	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

} )();
