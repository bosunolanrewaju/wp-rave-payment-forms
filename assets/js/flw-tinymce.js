'use strict';

( function () {
	tinymce.create( 'tinymce.plugins.flw_plugin', {
		init( editor, url ) {
			const assetsUrl = url.replace( '/js', '/' );
			editor.addCommand( 'flw_insert_shortcode', function () {
				const selected = tinymce.activeEditor.selection.getContent();
				const content = selected
					? '[flw-pay-button]' + selected + '[/flw-pay-button]'
					: '[flw-pay-button]';
				tinymce.execCommand( 'mceInsertContent', false, content );
			} );

			editor.addButton( 'flw_button', {
				title: 'Insert Flutterwave payment shortcode',
				cmd: 'flw_insert_shortcode',
				image: assetsUrl + 'images/rave-icon.png',
			} );
		},
	} );

	tinymce.PluginManager.add( 'flw_button', tinymce.plugins.flw_plugin );
} )();
