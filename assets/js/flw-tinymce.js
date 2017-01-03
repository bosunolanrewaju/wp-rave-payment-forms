'use strict';

(function() {
  tinymce.create('tinymce.plugins.flw_plugin', {
    init: function( editor, url ) {
      var assetsUrl = url.replace( '/js', '/' );
      editor.addCommand('flw_insert_shortcode', function() {

        var selected  = tinymce.activeEditor.selection.getContent();
        var content   = ( selected ) ? '[flw-pay-button]' + selected + '[/flw-pay-button]' : '[flw-pay-button]';
        tinymce.execCommand( 'mceInsertContent', false, content );

      });

      editor.addButton( 'flw_button', {
        title : 'Insert Rave payment shortcode',
        cmd   : 'flw_insert_shortcode',
        image : assetsUrl +'images/rave-icon.png',
      } );
    },
  });

  tinymce.PluginManager.add('flw_button', tinymce.plugins.flw_plugin);

})();
