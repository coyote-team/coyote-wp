const patchAttachmentEditorAltField = () => {
    const field = document.getElementById('attachment_alt');
    const description = document.getElementById('alt-text-description');

    if (!field || !description) {
        return;
    }

    if (!window.coyote || !window.coyote.hasOwnProperty('post_data')) {
        return; 
    }

    field.setAttribute('disabled', '');
    field.setAttribute('value', 'alt ' + window.coyote.post_data.alt);

    // change the field name so we don't store the alt we're patching here
    field.name = "coyote.managed_media_alt";

    while (description.firstChild) {
        description.removeChild(description.firstChild);
    }

    anchor = document.createElement('a');
    anchor.setAttribute('href', window.coyote.post_data.management_link);
    anchor.textContent = 'Manage image on Coyote website';
    anchor.setAttribute('target', '_blank');

    description.appendChild(anchor);
}

window.addEventListener('load', function () {
    patchAttachmentEditorAltField();
});
