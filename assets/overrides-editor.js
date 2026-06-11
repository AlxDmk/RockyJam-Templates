/**
 * RockyJam Templates — Overrides Editor (textarea-based)
 */
( function () {
	'use strict';

	if ( typeof RjtOverrides === 'undefined' ) return;

	const cfg     = RjtOverrides;
	const ajaxUrl = cfg.ajaxUrl;
	const nonce   = cfg.nonce;
	const slug    = cfg.slug;
	const i18n    = cfg.i18n;

	let currentPath = null;
	let isDirty     = false;

	// ── DOM refs ─────────────────────────────────────────────────────────────
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

	// Create textarea dynamically and append to host
	const host     = document.getElementById( 'rjt-codemirror-host' );
	const textarea = document.createElement( 'textarea' );
	textarea.id        = 'rjt-override-textarea';
	textarea.className = 'rjt-override-textarea';
	textarea.spellcheck = false;
	host.appendChild( textarea );

	// Auto-resize textarea as content changes
	function autoResize() {
		textarea.style.height = 'auto';
		textarea.style.height = Math.max( 400, textarea.scrollHeight ) + 'px';
	}
	textarea.addEventListener( 'input', () => {
		isDirty = true;
		saveStatus.textContent = '';
		autoResize();
	} );

	// Tab key inserts 4 spaces instead of moving focus
	textarea.addEventListener( 'keydown', e => {
		if ( e.key === 'Tab' ) {
			e.preventDefault();
			const start = textarea.selectionStart;
			const end   = textarea.selectionEnd;
			const val   = textarea.value;
			textarea.value = val.slice( 0, start ) + '\t' + val.slice( end );
			textarea.selectionStart = textarea.selectionEnd = start + 1;
			autoResize();
		}
		// Ctrl/Cmd+S → save
		if ( ( e.ctrlKey || e.metaKey ) && e.key === 's' ) {
			e.preventDefault();
			saveOverride();
		}
	} );

	// ── Open editor ──────────────────────────────────────────────────────────
	function openEditor( path, label ) {
		if ( isDirty && currentPath && currentPath !== path ) {
			if ( ! confirm( 'You have unsaved changes. Discard them?' ) ) return;
		}

		currentPath = path;
		isDirty     = false;
		saveStatus.textContent = '';
		editorTitle.textContent = label + '  (' + path + ')';
		editorWrap.style.display = '';
		textarea.value = '';
		autoResize();

		editorWrap.scrollIntoView( { behavior: 'smooth', block: 'start' } );

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
				textarea.value = res.data.content;
				isDirty = false;
				autoResize();

				if ( ! res.data.exists ) {
					saveStatus.textContent = i18n.wcDefault;
					saveStatus.className = 'rjt-overrides__save-status rjt-overrides__save-status--hint';
				}

				textarea.focus();
				textarea.setSelectionRange( 0, 0 );
				textarea.scrollTop = 0;
			} );
	}

	// ── Save ─────────────────────────────────────────────────────────────────
	function saveOverride() {
		if ( ! currentPath ) return;

		saveStatus.textContent = i18n.saving;
		saveStatus.className   = 'rjt-overrides__save-status';

		const fd = new FormData();
		fd.append( 'action',   'rjt_save_override' );
		fd.append( '_nonce',   nonce );
		fd.append( 'slug',     slug );
		fd.append( 'tpl_path', currentPath );
		fd.append( 'content',  textarea.value );

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
				ensureRowInList( currentPath );
			} );
	}

	// ── Delete (from editor) ─────────────────────────────────────────────────
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
				if ( ! res.success ) { alert( res.data?.message || 'Error' ); return; }
				removeRowFromList( currentPath );
				closeEditor();
			} );
	}

	// ── Close editor ─────────────────────────────────────────────────────────
	function closeEditor() {
		if ( isDirty ) {
			if ( ! confirm( 'You have unsaved changes. Discard them?' ) ) return;
		}
		editorWrap.style.display = 'none';
		currentPath = null;
		isDirty     = false;
		textarea.value = '';
	}

	// ── List helpers ─────────────────────────────────────────────────────────
	function ensureRowInList( path ) {
		if ( list.querySelector( '[data-path="' + CSS.escape( path ) + '"]' ) ) return;
		const empty = list.querySelector( '.rjt-overrides__empty' );
		if ( empty ) empty.remove();
		list.appendChild( buildRow( path, path ) );
	}

	function removeRowFromList( path ) {
		const row = list.querySelector( '.rjt-override-row[data-path="' + CSS.escape( path ) + '"]' );
		if ( row ) row.remove();
		if ( ! list.querySelector( '.rjt-override-row' ) ) {
			list.innerHTML = '<p class="rjt-overrides__empty">No overrides yet. Click \u201cAdd Override\u201d to start.</p>';
		}
	}

	function buildRow( path, label ) {
		const div = document.createElement( 'div' );
		div.className   = 'rjt-override-row';
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

	// ── Add-override modal ────────────────────────────────────────────────────
	function openModal() {
		modal.style.display = 'flex';
		if ( searchInput ) { searchInput.value = ''; filterModalItems( '' ); }
	}

	function closeModal() { modal.style.display = 'none'; }

	function filterModalItems( q ) {
		const lq = q.toLowerCase();
		modal.querySelectorAll( '.rjt-add-overrides__item' ).forEach( row => {
			row.style.display = ( ! lq || row.textContent.toLowerCase().includes( lq ) ) ? '' : 'none';
		} );
		modal.querySelectorAll( '.rjt-add-overrides__section' ).forEach( sec => {
			const vis = sec.querySelectorAll( '.rjt-add-overrides__item:not([style*="display: none"])' );
			sec.style.display = vis.length ? '' : 'none';
		} );
	}

	function confirmAddOverrides() {
		const paths = Array.from( modal.querySelectorAll( 'input[name="rjt_override_paths[]"]:checked' ) )
			.map( cb => cb.value );

		if ( ! paths.length ) { alert( i18n.noneSelected ); return; }

		const fd = new FormData();
		fd.append( 'action', 'rjt_add_overrides' );
		fd.append( '_nonce', nonce );
		fd.append( 'slug',   slug );
		paths.forEach( p => fd.append( 'paths[]', p ) );

		fetch( ajaxUrl, { method: 'POST', body: fd } )
			.then( r => r.json() )
			.then( res => {
				if ( ! res.success ) { alert( res.data?.message || 'Error' ); return; }

				const empty = list.querySelector( '.rjt-overrides__empty' );
				( res.data.added || [] ).forEach( path => {
					if ( empty ) empty.remove();
					const cb    = modal.querySelector( `input[value="${ CSS.escape( path ) }"]` );
					const label = cb?.closest( '.rjt-add-overrides__item' )
						?.querySelector( '.rjt-add-overrides__label' )?.textContent || path;
					if ( ! list.querySelector( '[data-path="' + CSS.escape( path ) + '"]' ) ) {
						list.appendChild( buildRow( path, label ) );
					}
					cb?.closest( '.rjt-add-overrides__item' )?.remove();
				} );

				closeModal();
			} );
	}

	// ── Event delegation ──────────────────────────────────────────────────────
	list.addEventListener( 'click', e => {
		const editBtn   = e.target.closest( '.rjt-override-row__edit-btn' );
		const delBtn    = e.target.closest( '.rjt-override-row__delete-btn' );

		if ( editBtn ) {
			openEditor( editBtn.dataset.path, editBtn.dataset.label || editBtn.dataset.path );
		}
		if ( delBtn ) {
			const path = delBtn.dataset.path;
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
					removeRowFromList( path );
					if ( currentPath === path ) closeEditor();
				} );
		}
	} );

	saveBtn.addEventListener(    'click', saveOverride );
	deleteBtn.addEventListener(  'click', deleteOverride );
	closeBtn.addEventListener(   'click', closeEditor );
	addBtn.addEventListener(     'click', openModal );
	addConfirm.addEventListener( 'click', confirmAddOverrides );
	modal.querySelector( '.rjt-modal__backdrop' )?.addEventListener( 'click', closeModal );
	modal.querySelectorAll( '.rjt-modal__close' ).forEach( b => b.addEventListener( 'click', closeModal ) );
	if ( searchInput ) {
		searchInput.addEventListener( 'input', () => filterModalItems( searchInput.value ) );
	}

	// ── Utility ───────────────────────────────────────────────────────────────
	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

} )();
