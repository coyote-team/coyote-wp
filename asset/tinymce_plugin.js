(function() {
     /* Register the buttons */
     tinymce.create('tinymce.plugins.Coyote', {
          init : function(ed, url) {
            wp.media.events.on('editor:image-edit', function (arg) {
                arg.metadata.coyoteManagementUrl = function () {
                    var prefix = coyote.classic_editor.prefix;
                    var mapping = coyote.classic_editor.mapping;
                    var coyoteId = mapping[arg.metadata.attachment_id] || mapping[arg.image.src];

                    if (coyoteId !== undefined) {
                        url =
                            '<a target="_blank" href="' +
                            prefix + '/resources/' + coyoteId +
                            '">Manage image on Coyote website</a>';
                        return url;
                    }
                };
            });
          },
          createControl : function(_n, _cm) {
               return null;
          },
     });
     /* Start the buttons */
     tinymce.PluginManager.add( 'coyote', tinymce.plugins.Coyote );
})();
