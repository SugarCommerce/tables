/**
 * Quick Edit and Bulk Edit, as core does them.
 *
 * Modelled on wp-admin/js/inline-edit-post.js, which is post-specific: it
 * reads `#inline_{id} .post_title`, submits `post[]`, and knows about
 * taxonomies and page parents. This is the same two editors against a table
 * whose rows are not posts.
 *
 * They do not work the same way, and the difference is core's, not an
 * inconsistency introduced here.
 *
 * Bulk Edit never saves from script. It reveals the row and lists what was
 * ticked; pressing Update submits the surrounding list form, selection
 * included, and the page reloads. That is why the bulk half has no ajax.
 *
 * Quick Edit does save from script. It reveals the row over a single row,
 * fills the inputs from the hidden `#inline_{id}` block, posts the fields,
 * and swaps in the row markup the server sends back. The response is the
 * rendered row rather than a success flag on purpose -- a status column, a
 * formatted amount and a row action set all change with the save, and a
 * script rebuilding them would be a second renderer drifting from the first.
 */
( function ( $, wp ) {
	'use strict';

	var settings = window.sugarCommerceInlineEdit || {};

	var InlineEdit = {

		/**
		 * The row being quick edited, or an empty string.
		 */
		what: '',

		/**
		 * Bind the bulk action selects, the row actions and the row buttons.
		 */
		init: function () {
			var self = this,
				$bulk = $( '#bulk-edit' ),
				$quick = $( '#inline-edit' );

			if ( ! $bulk.length && ! $quick.length ) {
				return;
			}

			// Both, because core has a set of bulk controls above the table
			// and another below it, and either can be the one used.
			$( '#doaction, #doaction2' ).on( 'click', function ( event ) {
				var action = $( this ).attr( 'id' ).substr( 2 );

				if ( 'edit' !== $( 'select[name="' + action + '"]' ).val() ) {
					return;
				}

				event.preventDefault();
				self.setBulk();
			} );

			/*
			 * Bound on each row's own elements, never delegated from the
			 * container holding them.
			 *
			 * edit() clones #inline-edit out into the list table, and
			 * clone(true) copies handlers bound directly to the row -- it
			 * cannot copy a delegated handler, which stays behind on the
			 * ancestor the clone just left. Delegating from #inlineedit left
			 * both Update and Cancel dead in the open editor, on a row that
			 * looked entirely normal.
			 *
			 * The bulk row is moved rather than cloned, so its handlers travel
			 * with it either way; it is bound the same way for consistency.
			 */
			$( '.cancel', $bulk ).on( 'click', function () {
				return self.revert();
			} );

			// A half-ticked box posts as unticked, which is what the server
			// expects of one -- its flag beside it is what keeps the rows'
			// own answers. Cleared on the way out, as core does, so nothing
			// is left drawn half-ticked behind the reloading page.
			$( 'input[name="bulk_edit"]', $bulk ).on( 'click', function () {
				$( 'ul[data-checklist] :checkbox', $bulk ).prop( 'indeterminate', false );
			} );

			$bulk.on( 'keyup', function ( event ) {
				if ( 27 === event.which ) {
					return self.revert();
				}
			} );

			// Removing one from the list unticks its row, so the form posts
			// what the list shows. Core does the same, and it is the only
			// thing keeping the two in step.
			$bulk.on( 'click', '.ntdelbutton', function () {
				var id = $( this ).attr( 'id' ).substr( 1 );

				$( 'table.widefat input[value="' + id + '"]' ).prop( 'checked', false );
				$( this ).parent().remove();

				if ( ! $( '#bulk-titles-list li' ).length ) {
					self.revert();
				}
			} );

			if ( ! $quick.length ) {
				return;
			}

			// This one is delegated on purpose: the row is replaced after
			// every save, and a handler bound to the old row would stop
			// firing. #the-list is never moved.
			$( '#the-list' ).on( 'click', '.editinline', function ( event ) {
				event.preventDefault();

				$( this ).attr( 'aria-expanded', 'true' );
				self.edit( this );
			} );

			$( '.save', $quick ).on( 'click', function () {
				return self.save();
			} );

			$( '.cancel', $quick ).on( 'click', function () {
				return self.revert();
			} );

			$( 'td', $quick ).on( 'keydown', function ( event ) {
				if ( 13 === event.which && ! $( event.target ).hasClass( 'cancel' ) ) {
					event.preventDefault();

					return self.save();
				}
			} );

			$quick.on( 'keyup', function ( event ) {
				if ( 27 === event.which ) {
					return self.revert();
				}
			} );
		},

		/**
		 * Open the quick editor over a row.
		 *
		 * @param {string|Element} id The row, or its id.
		 */
		edit: function ( id ) {
			var self = this,
				$editRow,
				$inlineData;

			this.revert();

			if ( 'object' === typeof id ) {
				id = this.getId( id );
			}

			this.what = id;

			$editRow = $( '#inline-edit' ).clone( true );

			$( 'td', $editRow ).attr(
				'colspan',
				$( 'th:visible, td:visible', '.widefat:first thead' ).length
			);

			// An empty row above it so the zebra striping survives, which is
			// what core does and why the row below is not suddenly the wrong
			// colour.
			//
			// The data row is hidden and nothing else. It used to be given
			// the editor's own class, inline-edit-row, which core styles
			// with `tr.inline-edit-row td { padding: 0 }` -- and Cancel
			// showed the row again with the class still on it, so every
			// cell came back with no padding at all. Core hides the row and
			// leaves its classes alone.
			$( '#item-' + id )
				.hide()
				.after( $editRow )
				.after( '<tr class="hidden"></tr>' );

			$editRow.attr( 'id', 'edit-' + id ).addClass( 'inline-editor' ).show();

			$inlineData = $( '#inline_' + id );

			$editRow.find( 'input[name="item_id"]' ).val( id );
			// Shown, not edited: the editor says which row it is for.
			$editRow.find( '[data-row-title]' ).text( this.readField( $inlineData, 'row_title' ) );

			// Every field takes its current value from the hidden block the
			// checkbox column printed. A field with no block entry is left at
			// its default rather than being blanked, so a control added by
			// the markup hook is not cleared by opening the editor.
			$editRow.find( '[name]' ).each( function () {
				var name = $( this ).attr( 'name' ),
					value;

				if ( 'item_id' === name || 'table_id' === name || 'row_title' === name ) {
					return;
				}

				// A checklist's boxes and its sentinel, all named `name[]`.
				// Left to fillChecklists() below: read as a single value here,
				// `[]` is not a class the data block can be searched for, and
				// a box would be ticked or not by whether the list read "1".
				if ( /\[\]$/.test( name ) ) {
					return;
				}

				value = self.readField( $inlineData, name );

				if ( null === value ) {
					return;
				}

				if ( 'checkbox' === this.type ) {
					$( this ).prop( 'checked', '1' === value || 'yes' === value );
					return;
				}

				// A select only takes a value it actually offers. Assigning
				// one it does not have leaves selectedIndex at -1, which
				// renders blank and drops the field out of serialize().
				//
				// Leaving it on its first option was worse. An order that is
				// Refunded, edited through a list offering Pending, Complete,
				// Failed and Cancelled, opened reading "Pending" -- and
				// pressing Update, having changed nothing, made it so.
				//
				// So the row's own value is put in the list for as long as
				// this editor is open: the control shows what the row is,
				// and submitting it untouched changes nothing, because the
				// server only accepts what the field declared. The clone is
				// thrown away on close, so the next row starts clean.
				if ( 'SELECT' === this.tagName && ! self.offers( this, value ) ) {
					$( this ).prepend(
						$( '<option>' ).val( value ).text( self.describe( value ) )
					);
				}

				$( this ).val( value );
			} );

			this.fillChecklists( $editRow, $inlineData );

			$editRow.find( ':input:visible' ).first().trigger( 'focus' );

			return false;
		},

		/**
		 * Tick what the row is in, in each checklist of the open editor.
		 *
		 * Core's way with a post's categories: the data block holds the ids
		 * as a comma list, and handing the boxes that list as an array
		 * ticks exactly the ones whose value is in it.
		 *
		 * @param {Object} $editRow    The open editor.
		 * @param {Object} $inlineData The row's data block.
		 */
		fillChecklists: function ( $editRow, $inlineData ) {
			var self = this;

			$editRow.find( 'ul[data-checklist]' ).each( function () {
				var ids = self.readField( $inlineData, $( this ).data( 'checklist' ) );

				$( ':checkbox', this ).val( ids ? ids.split( ',' ) : [] );
			} );
		},

		/**
		 * Open the bulk editor's checklists on what the ticked rows share.
		 *
		 * Core's setBulk(), for any checklist: a box every ticked row has is
		 * ticked, one only some have is half-ticked -- indeterminate, with a
		 * hidden `indeterminate_{name}[]` saying so to the server, which then
		 * leaves each row's own answer for it alone -- and one none have is
		 * clear. Touching a half-ticked box makes it a real answer for all
		 * of them, so the flag goes.
		 *
		 * @param {Object} $rows The ticked rows' checkboxes.
		 */
		setBulkChecklists: function ( $rows ) {
			var self = this;

			$( '#bulk-edit ul[data-checklist]' ).each( function () {
				var name = $( this ).data( 'checklist' ),
					counts = {};

				$rows.each( function () {
					var ids = self.readField( $( '#inline_' + $( this ).val() ), name );

					( ids ? ids.split( ',' ) : [] ).forEach( function ( id ) {
						counts[ id ] = ( counts[ id ] || 0 ) + 1;
					} );
				} );

				$( ':checkbox', this ).each( function () {
					var count = counts[ this.value ] || 0,
						label;

					$( this ).prop( 'checked', count > 0 && count === $rows.length );

					if ( count < 1 || count === $rows.length ) {
						return;
					}

					label = $.trim( $( this ).parent().text() );

					$( this )
						.prop( 'indeterminate', true )
						.attr( 'aria-label', label + ': ' + ( settings.someHaveThis || 'Some selected items have this' ) )
						.after(
							$( '<input type="hidden">' )
								.attr( 'name', 'indeterminate_' + name + '[]' )
								.val( this.value )
						)
						.one( 'change', function () {
							$( this )
								.removeAttr( 'aria-label' )
								.parent()
								.find( 'input[type="hidden"]' )
								.remove();
						} );
				} );
			} );
		},

		/**
		 * Put the bulk editor's checklists back to nothing ticked.
		 *
		 * The bulk row is moved, not cloned, so what the last selection
		 * left in it is still there when it opens again.
		 */
		resetBulkChecklists: function () {
			$( '#bulk-edit ul[data-checklist] :checkbox' )
				.off( 'change' )
				.prop( { checked: false, indeterminate: false } )
				.removeAttr( 'aria-label' )
				.parent()
				.find( 'input[type="hidden"]' )
				.remove();
		},

		/**
		 * Whether a select carries an option with this value.
		 *
		 * @param {Element} el    The select.
		 * @param {string}  value The value.
		 *
		 * @return {boolean} Whether it is on offer.
		 */
		offers: function ( el, value ) {
			var found = false;

			jQuery( el ).find( 'option' ).each( function () {
				if ( this.value === value ) {
					found = true;

					return false;
				}
			} );

			return found;
		},

		/**
		 * What to call a value the field does not offer.
		 *
		 * Only the stored value is known here, so it is the value made
		 * readable -- `partially_refunded` as "Partially refunded" -- and a
		 * dash for nothing at all.
		 *
		 * @param {string} value The stored value.
		 *
		 * @return {string} A label for it.
		 */
		describe: function ( value ) {
			var text = String( value ).replace( /[_-]+/g, ' ' ).trim();

			return text ? text.charAt( 0 ).toUpperCase() + text.slice( 1 ) : '\u2014';
		},

		/**
		 * A value out of the hidden data block, or null when it is absent.
		 *
		 * @param {Object} $data The block.
		 * @param {string} name  The field.
		 *
		 * @return {?string} The value.
		 */
		readField: function ( $data, name ) {
			var $field = $data.find( '.' + name );

			return $field.length ? $field.text() : null;
		},

		/**
		 * Post the open quick editor and swap in the row it returns.
		 */
		save: function () {
			var self = this,
				id = this.what,
				$row = $( '#edit-' + id ),
				params;

			if ( ! id ) {
				return false;
			}

			$( 'table.widefat .spinner' ).addClass( 'is-active' );

			params = $( ':input', $row ).serialize() +
				'&action=' + encodeURIComponent( settings.action || '' ) +
				'&item_id=' + encodeURIComponent( id );

			$.post( settings.ajaxUrl || window.ajaxurl, params )
				.done( function ( html, status, xhr ) {
					var $error = $row.find( '.error' );

					$( 'table.widefat .spinner' ).removeClass( 'is-active' );

					// A save that returns nothing did not save. Saying so is
					// the whole point -- the alternative is a row that
					// silently snaps back to what it was.
					if ( ! html || '-1' === $.trim( html ) ) {
						$error
							.html( settings.error || 'The change could not be saved.' )
							.parent()
							.removeClass( 'hidden' )
							.show();

						return;
					}

					// tr.hidden, not .hidden: the data block each row carries
					// is a div.hidden, and a bare class selector here removed
					// every one of them -- so the next Quick Edit opened with
					// empty fields, on rows that had never been touched.
					$( '#item-' + id ).siblings( 'tr.hidden' ).addBack().remove();
					$row.before( html ).remove();

					$( '#item-' + id )
						.hide()
						.fadeIn( 400, function () {
							$( this )
								.find( '.editinline' )
								.attr( 'aria-expanded', 'false' )
								.trigger( 'focus' );

							var said = self.notice( xhr );

							if ( wp && wp.a11y && wp.a11y.speak ) {
								wp.a11y.speak( said || settings.saved || 'Changes saved.' );
							}
						} );

					self.what = '';
				} )
				.fail( function () {
					$( 'table.widefat .spinner' ).removeClass( 'is-active' );

					$row.find( '.error' )
						.html( settings.error || 'The change could not be saved.' )
						.parent()
						.removeClass( 'hidden' )
						.show();
				} );

			return false;
		},

		/**
		 * Show what the save did, as the server worded it.
		 *
		 * It comes in a header beside the row, because the body is the row:
		 * "Order #115 is now complete.", in the notice area above the list
		 * where a bulk action's sentence appears, replacing the last one this
		 * put there. Dismissible the way core makes a notice added after the
		 * page loaded dismissible -- by saying one was added.
		 *
		 * @param {Object} xhr The response.
		 *
		 * @return {string} What it said, or an empty string.
		 */
		notice: function ( xhr ) {
			var raw = xhr && xhr.getResponseHeader ? xhr.getResponseHeader( 'X-SugarCommerce-Table-Notice' ) : '',
				notice,
				$notice;

			if ( ! raw ) {
				return '';
			}

			try {
				notice = JSON.parse( decodeURIComponent( raw ) );
			} catch ( e ) {
				return '';
			}

			if ( ! notice || ! notice.message ) {
				return '';
			}

			$notice = $( '<div class="notice is-dismissible sugarcommerce-table-quick-notice"><p></p></div>' )
				.addClass( 'notice-' + ( /^(success|warning|error|info)$/.test( notice.type ) ? notice.type : 'success' ) );

			// Text, never markup: a name is somebody's to choose.
			$notice.find( 'p' ).text( notice.message );

			$( '.sugarcommerce-table-quick-notice' ).remove();
			$( '.wrap hr.wp-header-end' ).first().after( $notice );
			$( document ).trigger( 'wp-updates-notice-added' );

			return notice.message;
		},

		/**
		 * The row id out of an element inside it.
		 *
		 * @param {Element} el Anything inside the row.
		 *
		 * @return {string} The id.
		 */
		getId: function ( el ) {
			var id = $( el ).closest( 'tr' ).attr( 'id' ) || '';

			return id.replace( 'item-', '' );
		},

		/**
		 * Move the bulk row into the table and list what was selected.
		 */
		setBulk: function () {
			var items = '',
				any = false;

			this.revert();

			$( '#bulk-edit td' ).attr(
				'colspan',
				$( 'th:visible, td:visible', '.widefat:first thead' ).length
			);

			$( 'table.widefat tbody' )
				.prepend( $( '#bulk-edit' ) )
				.prepend( '<tr class="hidden"></tr>' );

			$( '#bulk-edit' ).addClass( 'inline-editor' ).show();

			$( 'tbody .check-column input[type="checkbox"]' ).each( function () {
				if ( ! $( this ).prop( 'checked' ) ) {
					return;
				}

				any = true;

				var id = $( this ).val(),
					title = $( '#inline_' + id + ' .row_title' ).text() || '';

				items +=
					'<li class="ntdelitem"><button type="button" id="_' +
					id +
					'" class="button-link ntdelbutton"><span class="screen-reader-text">' +
					( settings.removeFromBulk || 'Remove from Bulk Edit' ) +
					'</span></button><span class="ntdeltitle" aria-hidden="true">' +
					$( '<div>' ).text( title ).html() +
					'</span></li>';
			} );

			// Nothing ticked is nothing to edit.
			if ( ! any ) {
				return this.revert();
			}

			$( '#bulk-titles' ).html(
				'<ul id="bulk-titles-list" role="list">' + items + '</ul>'
			);

			this.resetBulkChecklists();
			this.setBulkChecklists( $( 'tbody .check-column input[type="checkbox"]:checked' ) );

			$( '#bulk-edit' ).find( 'select' ).first().trigger( 'focus' );
		},

		/**
		 * Close whichever editor is open and put the table back.
		 */
		revert: function () {
			var id = $( 'table.widefat tr.inline-editor' ).attr( 'id' );

			if ( ! id ) {
				return false;
			}

			$( 'table.widefat .spinner' ).removeClass( 'is-active' );

			if ( 'bulk-edit' === id ) {
				$( '#bulk-edit' ).removeClass( 'inline-editor' ).hide().siblings( 'tr.hidden' ).remove();
				$( '#bulk-titles' ).empty();
				this.resetBulkChecklists();
				$( '#inlineedit' ).append( $( '#bulk-edit' ) );

				return false;
			}

			$( '#' + id ).siblings( 'tr.hidden' ).addBack().remove();

			id = id.replace( 'edit-', '' );
			this.what = '';

			$( '#item-' + id )
				.show()
				.find( '.editinline' )
				.attr( 'aria-expanded', 'false' )
				.trigger( 'focus' );

			return false;
		}
	};

	$( document ).ready( function () {
		InlineEdit.init();
	} );

	window.sugarCommerceInlineEditor = InlineEdit;
} )( jQuery, window.wp );
