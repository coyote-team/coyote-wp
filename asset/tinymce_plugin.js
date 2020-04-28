(function() {
     /* Register the buttons */
     tinymce.create('tinymce.plugins.Coyote', {
          init : function(ed, url) {
            wp.media.events.on('editor:image-edit', function (arg) {
                var getManagementUrl = function () {
                    var prefix = coyote.classic_editor.prefix;
                    var coyoteId = coyote.classic_editor.mapping[arg.image.src];

                    if (coyoteId !== undefined) {
                        url = 
                            '<a target="_blank" href="' +
                            prefix + '/resources/' + coyoteId +
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
