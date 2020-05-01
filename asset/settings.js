window.addEventListener('DOMContentLoaded', function () {
    console.debug('[Coyote]', 'settings loaded');

    const button = document.getElementById('coyote_process_existing_posts');
    if (!button.hasAttribute('disabled')) {
        return;
    }

    const progress = document.querySelector('#coyote_process_existing_posts_progress span');

    const updateProgress = (url) => {
        fetch(url)
            .then(response => response.text())
            .then(data => {
                if (data.length) {
                    progress.textContent = data;
                    setTimeout(() => updateProgress(url), 1000);
                    return;
                }

                progress.textContent = 100;
                button.removeAttribute('disabled');
                document.querySelector('#coyote_process_existing_posts_progress').setAttribute('hidden', '');
                document.querySelector('#coyote_processing_complete').removeAttribute('hidden');

            });
    };

    const url = new URL(coyote_ajax_obj.ajax_url);
    const params = {
        _ajax_nonce: coyote_ajax_obj.nonce,
        action: 'coyote_get_processing_progress'
    };
    Object.keys(params).forEach(key => url.searchParams.append(key, params[key]))

    updateProgress(url);
});
