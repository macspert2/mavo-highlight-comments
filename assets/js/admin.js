/**
 * Mavo Highlight Comments — admin metabox behaviour.
 * Dependency-free. Handles: add/remove highlight rows, copy a scored candidate
 * into a new row, and reorder rows via drag handle (keeps [order] in sync).
 */
( function () {
	'use strict';

	var i18n = ( window.mvhcAdmin && window.mvhcAdmin.i18n ) || {};
	var uid = Date.now();

	function qs( sel, ctx ) {
		return ( ctx || document ).querySelector( sel );
	}
	function qsa( sel, ctx ) {
		return Array.prototype.slice.call( ( ctx || document ).querySelectorAll( sel ) );
	}

	var metabox = qs( '.mvhc-metabox' );
	if ( ! metabox ) {
		return;
	}

	var rowsContainer = qs( '[data-mvhc-rows]', metabox );
	var template = qs( '#mvhc-row-template' );

	/**
	 * Re-number every row: sets the visible label and the hidden [order] field
	 * to the row's current DOM position (1-based).
	 */
	function renumber() {
		qsa( '[data-mvhc-row]', rowsContainer ).forEach( function ( row, index ) {
			var num = index + 1;
			var label = qs( '.mvhc-row__num', row );
			if ( label ) {
				label.textContent = num;
			}
			var orderField = qs( '[data-mvhc-field="order"]', row );
			if ( orderField ) {
				orderField.value = num;
			}
		} );
	}

	/**
	 * Create a new highlight row from the template with a unique field index.
	 *
	 * @return {Element} The appended row element.
	 */
	function addRow() {
		if ( ! template ) {
			return null;
		}
		uid += 1;
		var html = template.innerHTML.replace( /__INDEX__/g, 'new_' + uid );
		var wrapper = document.createElement( 'div' );
		wrapper.innerHTML = html.trim();
		var row = wrapper.firstElementChild;
		rowsContainer.appendChild( row );
		bindRow( row );
		renumber();
		return row;
	}

	function bindRow( row ) {
		var remove = qs( '[data-mvhc-remove]', row );
		if ( remove ) {
			remove.addEventListener( 'click', function () {
				if ( window.confirm( i18n.confirmRemove || 'Remove this highlight?' ) ) {
					row.parentNode.removeChild( row );
					renumber();
				}
			} );
		}
		makeDraggable( row );
	}

	/* --- Drag-and-drop reordering via the handle ------------------------ */
	function makeDraggable( row ) {
		var handle = qs( '.mvhc-row__handle', row );
		if ( ! handle ) {
			return;
		}
		handle.setAttribute( 'draggable', 'true' );
		handle.style.cursor = 'grab';

		handle.addEventListener( 'dragstart', function ( e ) {
			row.classList.add( 'is-dragging' );
			if ( e.dataTransfer ) {
				e.dataTransfer.effectAllowed = 'move';
				// Firefox requires data to be set for drag to start.
				e.dataTransfer.setData( 'text/plain', '' );
			}
		} );
		handle.addEventListener( 'dragend', function () {
			row.classList.remove( 'is-dragging' );
			renumber();
		} );
	}

	if ( rowsContainer ) {
		rowsContainer.addEventListener( 'dragover', function ( e ) {
			e.preventDefault();
			var dragging = qs( '.is-dragging', rowsContainer );
			if ( ! dragging ) {
				return;
			}
			var after = getRowAfter( rowsContainer, e.clientY );
			if ( null === after ) {
				rowsContainer.appendChild( dragging );
			} else if ( after !== dragging ) {
				rowsContainer.insertBefore( dragging, after );
			}
		} );
	}

	function getRowAfter( container, y ) {
		var rows = qsa( '[data-mvhc-row]:not(.is-dragging)', container );
		var closest = null;
		var closestOffset = Number.NEGATIVE_INFINITY;
		rows.forEach( function ( row ) {
			var box = row.getBoundingClientRect();
			var offset = y - box.top - box.height / 2;
			if ( offset < 0 && offset > closestOffset ) {
				closestOffset = offset;
				closest = row;
			}
		} );
		return closest;
	}

	/* --- Copy a scored candidate into a new highlight row --------------- */
	function addCandidate( btn ) {
		var row = addRow();
		if ( ! row ) {
			return;
		}
		var text = btn.getAttribute( 'data-text' ) || '';
		var author = btn.getAttribute( 'data-author' ) || '';
		var commentId = btn.getAttribute( 'data-comment-id' ) || '';
		var sourcePost = btn.getAttribute( 'data-source-post' ) || '';

		// Seed the translated-text field with the original French so the editor
		// translates in place; author with the first name.
		var textField = qs( '.mvhc-field__text', row );
		if ( textField ) {
			textField.value = text;
		}
		var authorField = qs( 'input[name*="[display_author]"]', row );
		if ( authorField ) {
			authorField.value = author;
		}
		setHidden( row, 'source_comment_id', commentId );
		setHidden( row, 'source_post_id', sourcePost );
		setHidden( row, 'source_lang', 'fr' );

		row.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		if ( textField ) {
			textField.focus();
		}
	}

	function setHidden( row, field, value ) {
		var el = qs( '[data-mvhc-field="' + field + '"]', row );
		if ( el ) {
			el.value = value;
		}
	}

	/* --- Wire up --------------------------------------------------------- */
	qsa( '[data-mvhc-row]', rowsContainer ).forEach( bindRow );
	renumber();

	var addBtn = qs( '[data-mvhc-add]', metabox );
	if ( addBtn ) {
		addBtn.addEventListener( 'click', function () {
			var row = addRow();
			if ( row ) {
				row.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}
		} );
	}

	qsa( '[data-mvhc-add-candidate]', metabox ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			addCandidate( btn );
		} );
	} );
} )();
