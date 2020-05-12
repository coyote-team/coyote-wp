window.addEventListener('DOMContentLoaded', function () {
    console.debug('[Coyote]', 'settings loaded');

    const hide = (selector) => document.querySelector(selector).setAttribute('hidden', '');
    const show = (selector) => document.querySelector(selector).removeAttribute('hidden');
    const disable = (selector) => document.querySelector(selector).setAttribute('disabled', '');
    const enable = (selector) => document.querySelector(selector).removeAttribute('disabled');

    const ajaxUrl = (action) => {
        const url = new URL(coyote_ajax_obj.ajax_url);
        const params = {
            _ajax_nonce: coyote_ajax_obj.nonce,
            action: action
        };
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]))
        return url;
    };

    const startProcessing = function () {
        const formData = new FormData();

        formData.append('_ajax_nonce', coyote_ajax_obj.nonce);
        formData.append('action', 'coyote_process_existing_posts');
        formData.append('batchSize', document.getElementById('batch_size').value);

        fetch(coyote_ajax_obj.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(reply => {
            if (reply == "1") {
                processExistingPostsButton.setAttribute('disabled', '');
                hide('#coyote_processing_complete');
                show('#coyote_processing_status');
                show('#coyote_processing');
                updateProgress();
            }
        });
    };

    const updateProgress = function () {
        const percentage = document.querySelector('#coyote_processing_status span');

        const update = (url) => {
            fetch(url)
            .then(response => response.text())
            .then(data => {
                if (data.length) {
                    percentage.textContent = data;
                    setTimeout(() => update(url), 1000);
                    return;
                }

                percentage.textContent = 100;
                processExistingPostsButton.removeAttribute('disabled');
                hide('#coyote_processing');
                show('#coyote_processing_complete');
            });
        };

        const url = ajaxUrl('coyote_get_processing_progress');
        update(url);
    };

    const processExistingPostsButton = document.getElementById('coyote_process_existing_posts');

    if (processExistingPostsButton) {
        processExistingPostsButton.addEventListener('click', startProcessing);

        if (processExistingPostsButton.hasAttribute('disabled')) {
            return updateProgress();
        }
    }

});
