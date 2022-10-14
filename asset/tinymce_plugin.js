(function () {
     /* Register the buttons */
     tinymce.create('tinymce.plugins.Coyote', {
            init : function (ed, url) {
                wp.media.events.on('editor:image-edit', function (arg) {
                    if (!('coyote' in window)) {
                        return;
                    }

                    var prefix = coyote.classic_editor.prefix;
                    var mapping = coyote.classic_editor.mapping;
                    var data = mapping[arg.metadata.attachment_id] || mapping[arg.image.src];

                    if (data === undefined) {
                        arg.metadata.coyoteManagementUrl = function () {
                            return 'Image descriptions are managed by Coyote.';
                        }
                        return;
                    }

                    arg.metadata.alt = data.alt;

                    arg.metadata.coyoteManagementUrl = function () {
                        url =
                        '<a target="_blank" href="' +
                        prefix + '/resources/' + data.coyoteId +
                        '">Manage image on Coyote website</a>';
                        return url;
                    };
                });
            },
            createControl : function (_n, _cm) {
                 return null;
            },
        });
     /* Start the buttons */
     tinymce.PluginManager.add('coyote', tinymce.plugins.Coyote);
})();
