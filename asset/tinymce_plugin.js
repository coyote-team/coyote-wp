(function() {
     /* Register the buttons */
     tinymce.create('tinymce.plugins.Coyote', {
          init : function(ed, url) {
            wp.media.events.on('editor:image-edit', function (arg) {
                var getManagementUrl = function (prefix) {
                    if (arg.image.dataset.coyoteId) {
                        url = 
                            '<a target="_blank" href="' +
                            prefix + '/resources/' + arg.image.dataset.coyoteId +
                            '">Manage image on Coyote website</a>';
                        return url;
                    }
                };

                arg.metadata.coyoteManagementUrl = getManagementUrl;
            });
          },
          createControl : function(n, cm) {
               return null;
          },
     });
     /* Start the buttons */
     tinymce.PluginManager.add( 'coyote', tinymce.plugins.Coyote );
})();
