/**
 * RockyJam Templates — Hooks Editor
 *
 * Requires: SortableJS (loaded via CDN or enqueued), jQuery
 */
( function () {
	'use strict';

	// ----------------------------------------------------------------
	// State
	// ----------------------------------------------------------------
	let config = [];          // Array of { hook, callbacks[] }
	let isDirty = false;

	const editor    = document.getElementById( 'rjt-hooks-editor' );
	if ( ! editor ) return;

	const slug      = editor.dataset.slug;
	const nonce     = editor.dataset.nonce;
	const ajaxUrl   = editor.dataset.ajaxurl;

	// Load initial config from embedded JSON.
	const configEl = document.getElementById( 'rjt-hooks-config' );
	if ( configEl ) {
		try {
			config = JSON.parse( configEl.textContent );
		} catch (e) {
			config = [];
		}
	}

	const statusEl  = document.getElementById( 'rjt-hooks-status' );
	const listEl    = document.getElementById( 'rjt-hooks-list' );

	// Addon hooks data (Этап 2).
	let addonHooks   = {};   // hook_name => [ { addon_id, addon_name, function, priority, label, source } ]
	let addonsActive = false;
	const addonHooksEl = document.getElementById( 'rjt-addon-hooks' );
	const addonsActiveEl = document.getElementById( 'rjt-addons-active' );
	if ( addonHooksEl ) {
		try { addonHooks = JSON.parse( addonHooksEl.textContent ); } catch(e) {}
	}
	if ( addonsActiveEl ) {
		addonsActive = addonsActiveEl.textContent.trim() === 'true';
	}

	// ----------------------------------------------------------------
	// Utility
	// ----------------------------------------------------------------
	function setStatus( msg, type ) {
		statusEl.textContent = msg;
		statusEl.className = 'rjt-hooks-status rjt-hooks-status--' + ( type || 'info' );
		if ( type === 'success' ) {
			setTimeout( () => { statusEl.textContent = ''; statusEl.className = 'rjt-hooks-status'; }, 3000 );
		}
	}

	function markDirty() {
		isDirty = true;
		setStatus( RjtHooks.i18n.unsaved, 'warning' );
	}

	/**
	 * Reads the current DOM state and rebuilds `config`.
	 */
	function syncConfigFromDom() {
		config = [];
		const groups = listEl.querySelectorAll( '.rjt-hook-group' );
		groups.forEach( group => {
			const hook = group.dataset.hook;
			const callbacks = [];
			group.querySelectorAll( '.rjt-callback' ).forEach( cbEl => {
				callbacks.push( {
					id:                cbEl.dataset.id,
					function:          cbEl.dataset.function,
					priority:          parseInt( cbEl.querySelector( '.rjt-priority-input' ).value, 10 ) || 10,
					original_priority: parseInt( cbEl.dataset.originalPriority, 10 ) || parseInt( cbEl.querySelector( '.rjt-priority-input' ).value, 10 ) || 10,
					enabled:           cbEl.querySelector( '.rjt-toggle-enabled' ).checked,
					custom:            cbEl.dataset.custom === '1',
					label:             cbEl.dataset.label,
					code:              cbEl.dataset.code || '',
				} );
			} );
			config.push( { hook, callbacks } );
		} );
	}

	// ----------------------------------------------------------------
	// Sortable (drag-and-drop within each hook group)
	// ----------------------------------------------------------------
	/**
	 * Reassign priority values by DOM order after a drag.
	 * Uses a fixed step of 10 starting from the lowest current priority value,
	 * so the relative ordering is always reflected in the numbers.
	 */
	function reprioritizeAfterDrag( list ) {
		const inputs = Array.from( list.querySelectorAll( '.rjt-callback .rjt-priority-input' ) );
		if ( inputs.length === 0 ) return;

		const values = inputs.map( i => parseInt( i.value, 10 ) || 10 );
		const start  = Math.max( 1, Math.min( ...values ) );
		const step   = 10;

		inputs.forEach( ( input, idx ) => {
			input.value = start + idx * step;
		} );
	}

	function initSortable() {
		const lists = listEl.querySelectorAll( '.sortable-list' );
		lists.forEach( list => {
			if ( typeof Sortable !== 'undefined' ) {
				Sortable.create( list, {
					animation:  150,
					// Accept both standard and addon drag handles.
					handle:     '.rjt-callback__drag',
					ghostClass: 'rjt-callback--ghost',
					onEnd( evt ) {
						reprioritizeAfterDrag( evt.to );
						markDirty();
					},
				} );
			}
		} );
	}

	// ----------------------------------------------------------------
	// Hook group collapse/expand
	// ----------------------------------------------------------------
	function initGroupToggles() {
		listEl.addEventListener( 'click', e => {
			const header = e.target.closest( '.rjt-hook-group__header' );
			if ( ! header ) return;
			// Don't collapse when clicking the "Add Function" button.
			if ( e.target.closest( '.rjt-add-callback' ) ) return;

			const group = header.closest( '.rjt-hook-group' );
			group.classList.toggle( 'rjt-hook-group--collapsed' );
			const icon = header.querySelector( '.rjt-hook-group__toggle' );
			if ( icon ) {
				icon.classList.toggle( 'dashicons-arrow-down' );
				icon.classList.toggle( 'dashicons-arrow-right' );
			}
		} );
	}

	// ----------------------------------------------------------------
	// Toggle enabled/disabled
	// ----------------------------------------------------------------
	function initToggleEnabled() {
		listEl.addEventListener( 'change', e => {
			if ( ! e.target.classList.contains( 'rjt-toggle-enabled' ) ) return;
			const cbEl = e.target.closest( '.rjt-callback' );
			if ( ! cbEl ) return;
			cbEl.classList.toggle( 'rjt-callback--disabled', ! e.target.checked );
			markDirty();
		} );
	}

	// ----------------------------------------------------------------
	// Priority input
	// ----------------------------------------------------------------
	function initPriorityInputs() {
		listEl.addEventListener( 'input', e => {
			if ( ! e.target.classList.contains( 'rjt-priority-input' ) ) return;
			markDirty();
		} );
	}

	// ----------------------------------------------------------------
	// Remove callback
	// ----------------------------------------------------------------
	function initRemoveCallbacks() {
		listEl.addEventListener( 'click', e => {
			const btn = e.target.closest( '.rjt-remove-callback' );
			if ( ! btn ) return;
			const cbEl = btn.closest( '.rjt-callback' );
			if ( ! cbEl ) return;
			if ( ! confirm( RjtHooks.i18n.confirmRemove ) ) return;
			cbEl.remove();

			// Show empty message if no callbacks left.
			const container = cbEl.closest( '.rjt-callbacks' );
			if ( container && ! container.querySelector( '.rjt-callback' ) ) {
				const empty = document.createElement( 'div' );
				empty.className = 'rjt-callbacks__empty';
				empty.textContent = RjtHooks.i18n.noCallbacks;
				container.appendChild( empty );
			}
			markDirty();
		} );
	}

	// ----------------------------------------------------------------
	// Modal for add/edit custom function
	// ----------------------------------------------------------------
	const modal      = document.getElementById( 'rjt-cb-modal' );
	const modalTitle = document.getElementById( 'rjt-cb-modal-title' );
	const hookSelect = document.getElementById( 'rjt-cb-hook-select' );
	const labelIn    = document.getElementById( 'rjt-cb-label' );
	const funcIn     = document.getElementById( 'rjt-cb-function' );
	const prioIn     = document.getElementById( 'rjt-cb-priority' );
	const codeIn     = document.getElementById( 'rjt-cb-code' );
	const editingId  = document.getElementById( 'rjt-cb-modal-editing-id' );

	let currentEditCbEl = null; // DOM element being edited

	function openModal( hook, existingCbEl ) {
		currentEditCbEl = existingCbEl || null;

		if ( existingCbEl ) {
			modalTitle.textContent = RjtHooks.i18n.editFunction;
			hookSelect.value       = existingCbEl.dataset.hook;
			hookSelect.disabled    = true;
			labelIn.value          = existingCbEl.dataset.label;
			funcIn.value           = existingCbEl.dataset.function;
			prioIn.value           = existingCbEl.querySelector( '.rjt-priority-input' ).value;
			codeIn.value           = existingCbEl.dataset.code || '';
			editingId.value        = existingCbEl.dataset.id;
		} else {
			modalTitle.textContent = RjtHooks.i18n.addFunction;
			hookSelect.value       = hook;
			hookSelect.disabled    = false;
			labelIn.value          = '';
			funcIn.value           = '';
			prioIn.value           = '10';
			codeIn.value           = '';
			editingId.value        = '';
		}

		modal.style.display = 'flex';
		labelIn.focus();
	}

	function closeModal() {
		modal.style.display = 'none';
		currentEditCbEl     = null;
	}

	function generateId() {
		return 'custom_' + Date.now() + '_' + Math.floor( Math.random() * 1000 );
	}

	function buildCallbackElement( data ) {
		const div = document.createElement( 'div' );
		div.className = 'rjt-callback rjt-callback--custom';
		div.dataset.id               = data.id;
		div.dataset.hook             = data.hook;
		div.dataset.function         = data.function;
		div.dataset.priority         = data.priority;
		div.dataset.originalPriority = data.priority; // custom: original = current (no WC registration)
		div.dataset.enabled          = '1';
		div.dataset.custom           = '1';
		div.dataset.label            = data.label;
		div.dataset.code             = data.code;

		div.innerHTML = `
			<span class="rjt-callback__drag dashicons dashicons-menu" title="${ RjtHooks.i18n.dragToReorder }"></span>
			<label class="rjt-callback__toggle">
				<input type="checkbox" class="rjt-toggle-enabled" checked>
				<span class="rjt-toggle-slider"></span>
			</label>
			<span class="rjt-callback__label">
				${ escHtml( data.label ) }
				<span class="rjt-badge rjt-badge--custom">${ RjtHooks.i18n.custom }</span>
			</span>
			<code class="rjt-callback__func">${ escHtml( data.function ) }</code>
			<div class="rjt-callback__priority-wrap">
				<input type="number" class="rjt-priority-input" value="${ data.priority }" min="1" max="999" step="1">
			</div>
			<div class="rjt-callback__actions">
				<button type="button" class="button button-small rjt-edit-callback" data-id="${ escAttr( data.id ) }">
					<span class="dashicons dashicons-edit"></span>
				</button>
				<button type="button" class="button button-small rjt-remove-callback" data-id="${ escAttr( data.id ) }">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>
		`;
		return div;
	}

	function escHtml( str ) {
		const d = document.createElement( 'div' );
		d.appendChild( document.createTextNode( String( str ) ) );
		return d.innerHTML;
	}
	function escAttr( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	function initModalButtons() {
		// "Add Function" buttons in each hook group header.
		listEl.addEventListener( 'click', e => {
			const addBtn = e.target.closest( '.rjt-add-callback' );
			if ( ! addBtn ) return;
			e.stopPropagation();
			openModal( addBtn.dataset.hook, null );
		} );

		// "Edit" button on existing custom callbacks.
		listEl.addEventListener( 'click', e => {
			const editBtn = e.target.closest( '.rjt-edit-callback' );
			if ( ! editBtn ) return;
			const cbEl = editBtn.closest( '.rjt-callback' );
			if ( ! cbEl ) return;
			openModal( cbEl.dataset.hook, cbEl );
		} );

		// Close modal.
		document.getElementById( 'rjt-cb-modal-close' ).addEventListener( 'click', closeModal );
		document.getElementById( 'rjt-cb-modal-cancel' ).addEventListener( 'click', closeModal );
		modal.querySelector( '.rjt-modal__backdrop' ).addEventListener( 'click', closeModal );

		// Save modal.
		document.getElementById( 'rjt-cb-modal-save' ).addEventListener( 'click', () => {
			const hook  = hookSelect.value;
			const label = labelIn.value.trim();
			const func  = funcIn.value.trim();
			const prio  = parseInt( prioIn.value, 10 ) || 10;
			const code  = codeIn.value;

			if ( ! label ) { labelIn.focus(); return; }
			if ( ! func  ) { funcIn.focus();  return; }
			if ( ! /^[a-zA-Z_][a-zA-Z0-9_]*$/.test( func ) ) {
				alert( RjtHooks.i18n.invalidFuncName );
				funcIn.focus();
				return;
			}

			if ( currentEditCbEl ) {
				// Update existing.
				currentEditCbEl.dataset.label    = label;
				currentEditCbEl.dataset.function = func;
				currentEditCbEl.dataset.code     = code;
				currentEditCbEl.querySelector( '.rjt-callback__label' ).innerHTML =
					escHtml( label ) + ' <span class="rjt-badge rjt-badge--custom">' + RjtHooks.i18n.custom + '</span>';
				currentEditCbEl.querySelector( '.rjt-callback__func' ).textContent = func;
				currentEditCbEl.querySelector( '.rjt-priority-input' ).value = prio;
			} else {
				// New callback.
				const id     = generateId();
				const cbEl   = buildCallbackElement( { id, hook, label, function: func, priority: prio, code } );

				// Find the sortable list for this hook.
				const container = listEl.querySelector( `.rjt-callbacks[data-hook="${ hook }"]` );
				if ( ! container ) { closeModal(); return; }

				// Remove "empty" placeholder if present.
				const emptyEl = container.querySelector( '.rjt-callbacks__empty' );
				if ( emptyEl ) emptyEl.remove();

				container.appendChild( cbEl );

				// Re-init sortable for this list (simplest approach).
				if ( typeof Sortable !== 'undefined' ) {
					Sortable.create( container, {
						animation:  150,
						handle:     '.rjt-callback__drag',
						ghostClass: 'rjt-callback--ghost',
						onEnd( evt ) {
							reprioritizeAfterDrag( evt.to );
							markDirty();
						},
					} );
				}
			}

			markDirty();
			closeModal();
		} );
	}

	// ----------------------------------------------------------------
	// Save (AJAX)
	// ----------------------------------------------------------------
	document.getElementById( 'rjt-hooks-save' ).addEventListener( 'click', () => {
		syncConfigFromDom();

		const btn = document.getElementById( 'rjt-hooks-save' );
		btn.disabled = true;
		setStatus( RjtHooks.i18n.saving, 'info' );

		const formData = new FormData();
		formData.append( 'action',  'rjt_save_hooks' );
		formData.append( 'nonce',   nonce );
		formData.append( 'slug',    slug );
		formData.append( 'config',  JSON.stringify( config ) );

		fetch( ajaxUrl, { method: 'POST', body: formData } )
			.then( r => r.json() )
			.then( res => {
				if ( res.success ) {
					isDirty = false;
					setStatus( res.data.message || RjtHooks.i18n.saved, 'success' );
				} else {
					setStatus( ( res.data && res.data.message ) || RjtHooks.i18n.error, 'error' );
				}
			} )
			.catch( () => setStatus( RjtHooks.i18n.error, 'error' ) )
			.finally( () => { btn.disabled = false; } );
	} );

	// ----------------------------------------------------------------
	// Reset to defaults
	// ----------------------------------------------------------------
	document.getElementById( 'rjt-hooks-reset' ).addEventListener( 'click', () => {
		if ( ! confirm( RjtHooks.i18n.confirmReset ) ) return;

		const formData = new FormData();
		formData.append( 'action',  'rjt_save_hooks' );
		formData.append( 'nonce',   nonce );
		formData.append( 'slug',    slug );
		formData.append( 'config',  JSON.stringify( [] ) ); // empty = regenerate from registry

		setStatus( RjtHooks.i18n.saving, 'info' );

		fetch( ajaxUrl, { method: 'POST', body: formData } )
			.then( r => r.json() )
			.then( res => {
				if ( res.success ) {
					setStatus( RjtHooks.i18n.saved, 'success' );
					setTimeout( () => location.reload(), 800 );
				} else {
					setStatus( ( res.data && res.data.message ) || RjtHooks.i18n.error, 'error' );
				}
			} )
			.catch( () => setStatus( RjtHooks.i18n.error, 'error' ) );
	} );

	// ----------------------------------------------------------------
	// Warn on unload if dirty
	// ----------------------------------------------------------------
	window.addEventListener( 'beforeunload', e => {
		if ( isDirty ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );

	// ----------------------------------------------------------------
	// Init
	// ----------------------------------------------------------------
	initSortable();
	initGroupToggles();
	initToggleEnabled();
	initPriorityInputs();
	initRemoveCallbacks();
	initModalButtons();
	injectAddonRows();

	// ----------------------------------------------------------------
	// Inject addon function rows into hook groups (Этап 2)
	// ----------------------------------------------------------------
	function injectAddonRows() {
		if ( ! addonsActive || ! Object.keys( addonHooks ).length ) {
			return;
		}

		for ( const [ hookName, callbacks ] of Object.entries( addonHooks ) ) {
			const container = listEl.querySelector( `.rjt-callbacks[data-hook="${ hookName }"]` );
			if ( ! container ) continue;

			// Remove "empty" placeholder if present.
			const emptyEl = container.querySelector( '.rjt-callbacks__empty' );

			for ( const cb of callbacks ) {
				// Skip if already shown (e.g. saved in hooks-config.json as custom).
				if ( container.querySelector( `[data-function="${ cb.function }"]` ) ) continue;

				if ( emptyEl ) emptyEl.remove();

				const row = buildAddonRow( hookName, cb );
				container.appendChild( row );
			}
		}
	}

	/**
	 * Builds a read-only (non-draggable, non-removable) addon callback row.
	 * Addon rows are always visible but cannot be reordered or deleted from here
	 * (they are managed by the addon itself). The user can only toggle them.
	 */
	function buildAddonRow( hookName, cb ) {
		const div = document.createElement( 'div' );
		div.className = 'rjt-callback rjt-callback--addon';
		div.dataset.id               = 'addon_' + cb.addon_id + '_' + cb.function;
		div.dataset.hook             = hookName;
		div.dataset.function         = cb.function;
		div.dataset.priority         = cb.priority;
		div.dataset.originalPriority = cb.priority; // addon: original = what addon registered
		div.dataset.enabled          = '1';
		div.dataset.custom           = '0';
		div.dataset.label            = cb.label || cb.function;
		div.dataset.code             = '';
		div.dataset.addonId          = cb.addon_id;

		const addonBadge = `<span class="rjt-badge rjt-badge--addon" title="${ escAttr( cb.addon_name || cb.addon_id ) }">${ escHtml( cb.addon_name || cb.addon_id ) }</span>`;
		const sourceBadge = cb.source === 'autodiscovered'
			? `<span class="rjt-badge rjt-badge--autodiscovered" title="${ escAttr( RjtHooks.i18n.autodiscovered ) }">${ RjtHooks.i18n.autodiscovered }</span>`
			: '';

		div.innerHTML = `
			<span class="rjt-callback__drag dashicons dashicons-menu" title="${ RjtHooks.i18n.dragToReorder }"></span>
			<label class="rjt-callback__toggle">
				<input type="checkbox" class="rjt-toggle-enabled" checked>
				<span class="rjt-toggle-slider"></span>
			</label>
			<span class="rjt-callback__label">
				${ escHtml( cb.label || cb.function ) }
				<span class="rjt-badge rjt-badge--addon-name">${ escHtml( cb.addon_name || cb.addon_id ) }</span>
				${ sourceBadge }
			</span>
			<code class="rjt-callback__func">${ escHtml( cb.function ) }</code>
			<div class="rjt-callback__priority-wrap">
				<input type="number" class="rjt-priority-input" value="${ cb.priority }" min="1" max="999" step="1">
			</div>
			<div class="rjt-callback__actions rjt-callback__actions--addon">
				<span class="rjt-addon-lock dashicons dashicons-lock" title="${ escAttr( RjtHooks.i18n.addonManaged ) }"></span>
			</div>
		`;
		return div;
	}

} )();
