'use strict';

(function() {
  tinymce.create('tinymce.plugins.flw_plugin', {
    init: function( editor, url ) {

      editor.addCommand('flw_insert_shortcode', function() {

        var selected  = tinymce.activeEditor.selection.getContent();
        var content   = ( selected ) ? '[flw-pay-button]' + selected + '[/flw-pay-button]' : '[flw-pay-button]';
        tinymce.execCommand( 'mceInsertContent', false, content );

      });

      editor.addButton( 'flw_button', {
        title : 'Insert shortcode',
        cmd   : 'flw_insert_shortcode',
        image : 'http://sample.net/haute/wp-content/plugins/flutterwave-pay/assets/images/flutterwave-icon.png',
      } );
    },
  });

  tinymce.PluginManager.add('flw_button', tinymce.plugins.flw_plugin);

})();
