jQuery( function ( $ ) {
	var widget = $( '#when_last_login_top_users' );

	if ( ! widget.length || typeof wllWidget === 'undefined' ) {
		return;
	}

	function showError( rows ) {
		rows.empty().append(
			$( '<tr>' ).append(
				$( '<td>', {
					colspan: 4,
					text: wllWidget.errorMessage
				} )
			)
		);
	}

	widget.on( 'click', '.wll-widget-sort-btn', function () {
		var button = $( this );
		var sort = button.data( 'sort' ) === 'time' ? 'time' : 'count';
		var blogId = parseInt( button.data( 'blog-id' ), 10 ) || 0;
		var group = button.closest( '.wll-widget-sort' );
		var rows = blogId ? $( '#wll-widget-rows-' + blogId ) : widget.find( '#wll-widget-rows' );
		var spinner = group.find( '.spinner' );

		group.find( '.wll-widget-sort-btn' )
			.removeClass( 'button-primary' )
			.attr( 'aria-pressed', 'false' );
		button.addClass( 'button-primary' ).attr( 'aria-pressed', 'true' );
		spinner.addClass( 'is-active' );

		$.post( wllWidget.ajaxUrl, {
			action: 'wll_fetch_widget_data',
			nonce: wllWidget.nonce,
			sort_order: sort,
			blog_id: blogId
		} ).done( function ( response ) {
			if ( response && response.success && response.data && typeof response.data.html === 'string' ) {
				rows.html( response.data.html );
			} else {
				showError( rows );
			}
		} ).fail( function () {
			showError( rows );
		} ).always( function () {
			spinner.removeClass( 'is-active' );
		} );
	} );
} );
