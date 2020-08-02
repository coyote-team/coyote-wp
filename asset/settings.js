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

    const setProcessingJob = function (responseText) {
        try {
            const data = JSON.parse(responseText);

            const formData = new FormData();
            formData.append('action', 'coyote_set_batch_job');
            formData.append('job_id', data.id);
            formData.append('job_type', 'process');
            formData.append('_ajax_nonce', coyote_ajax_obj.nonce);

            return fetch(coyote_ajax_obj.ajax_url, {
                method: 'POST',
                body: formData
            }).then(response => {
                console.debug(response);
                coyote_ajax_obj['job_id'] = data.id;
                coyote_ajax_obj['job_type'] = 'process';
                return response.text();
            });
        } catch (e) {
            console.error(e);
            return Promise.reject("Invalid json");
        }
    }

    const startProcessing = function () {
        disable('#coyote_process_existing_posts');
        enable('#coyote_cancel_processing');

        const data = {
            nonce: coyote_ajax_obj.nonce,
            action: 'process',
            host: coyote_ajax_obj.ajax_url,
            batchSize: document.getElementById('batch_size').value
        }

        fetch(`${coyote_ajax_obj.endpoint}/jobs/`, {
            mode: 'cors',
            method: 'POST',
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json',
            },
        })
        .then(response => response.text())
        .then(setProcessingJob)
        .then(reply => {
            if (reply == "1") {
                processExistingPostsButton.setAttribute('disabled', '');
                hide('#coyote_processing_complete');
                show('#coyote_processing_status');
                show('#coyote_processing');
                updateProgress();
            }
        })
        .catch((error) => {
            console.debug("Error: ", error);
        });
    };

    const cancelJob = function () {
        if (coyote_ajax_obj.job_id) {
            return fetch(`${coyote_ajax_obj.endpoint}/jobs/${coyote_ajax_obj.job_id}`, {
                mode: 'cors',
                method: 'DELETE',
            });
        }
        return Promise.resolve();
    }

    const cancelProcessing = function () {
        const formData = new FormData();

        formData.append('_ajax_nonce', coyote_ajax_obj.nonce);
        formData.append('action', 'coyote_cancel_batch_job');

        cancelJob().then(() => {
            fetch(coyote_ajax_obj.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(reply => {
                enable('#coyote_process_existing_posts');
                disable('#coyote_cancel_processing');
            });
        });
    }

    const clearBatchJob = function () {
        const formData = new FormData();

        formData.append('_ajax_nonce', coyote_ajax_obj.nonce);
        formData.append('action', 'coyote_clear_batch_job');

        return fetch(coyote_ajax_obj.ajax_url, {
            method: 'POST',
            body: formData
        });
    }

    const updateProgress = function () {
        const percentage = document.querySelector('#coyote_processing span');
        const status = document.querySelector('#coyote_job_status span');

        const update = (url) => {
            fetch(url)
            .then(response => response.text())
            .then(data => {
                console.debug(data);

                if (data.length) {
                    data = JSON.parse(data);
                    status.textContent = data.status;
                    percentage.textContent = data.progress;
                    if (data.progress < 100) {
                        setTimeout(() => update(url), 1000);
                        return;
                    }
                }

                clearBatchJob().then(() => {
                    percentage.textContent = 100;
                    processExistingPostsButton.removeAttribute('disabled');
                    hide('#coyote_processing');
                    show('#coyote_processing_complete');
                });
            });
        };

        // get this from the ajaxObj; job_id, job_type
        const url = `${coyote_ajax_obj.endpoint}/jobs/${coyote_ajax_obj.job_id}`;
        update(url);
    };

    const processExistingPostsButton = document.getElementById('coyote_process_existing_posts');
    const cancelProcessingButton = document.getElementById('coyote_cancel_processing');

    if (processExistingPostsButton) {
        processExistingPostsButton.addEventListener('click', startProcessing);
        cancelProcessingButton.addEventListener('click', cancelProcessing);

        if (processExistingPostsButton.hasAttribute('disabled')) {
            cancelProcessingButton.removeAttribute('disabled');
            return updateProgress();
        }
    }

});
