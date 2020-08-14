window.addEventListener('DOMContentLoaded', function () {
    console.debug('[Coyote]', 'settings loaded');

    const hide = node => node.setAttribute('hidden', '');
    const show = node => node.removeAttribute('hidden');

    const disable = node => node.setAttribute('disabled', '');
    const enable  = node => node.removeAttribute('disabled');

    const byId = x => document.getElementById(x);
    const $ = x => document.querySelector(x);

    const processExistingPostsButton = byId('coyote_process_existing_posts');
    const cancelProcessingButton = byId('coyote_cancel_processing');

    const processingComplete = byId('coyote_processing_complete');
    const processingStatus = byId('coyote_processing_status');
    const processingContainer = byId('coyote_processing');

    const percentageSpan = $('#coyote_processing span');
    const statusSpan = $('#coyote_job_status span');

    const processorEndpoint = byId('coyote_processor_endpoint').value;

    const load = () => {
        if (processExistingPostsButton) {
            processExistingPostsButton.addEventListener('click', startProcessing);
            cancelProcessingButton.addEventListener('click', cancelProcessing);

            if (processExistingPostsButton.hasAttribute('disabled')) {
                cancelProcessingButton.removeAttribute('disabled');
                return updateProgress();
            }
        }
    }

    const startProcessing = function () {
        disable(processExistingPostsButton);
        enable(cancelProcessingButton);

        const data = {
            nonce: coyote_ajax_obj.nonce,
            host: coyote_ajax_obj.ajax_url,
            batchSize: byId('coyote_batch_size').value
        };

        fetch(`${processorEndpoint}/jobs/`, {
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
                disable(processExistingPostsButton);
                hide(processingComplete);
                show(processingStatus);
                show(processingContainer);
                updateProgress();
            }
        })
        .catch((error) => {
            console.debug("Error: ", error);
        });
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
                coyote_ajax_obj['job_id'] = data.id;
                return response.text();
            });
        } catch (e) {
            console.error(e);
            return Promise.reject("Invalid json");
        }
    }

    const cancelJob = function () {
        if (coyote_ajax_obj.job_id) {
            return fetch(`${processorEndpoint}/jobs/${coyote_ajax_obj.job_id}`, {
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
                enable(processExistingPostsButton);
                disable(cancelProcessingButton);
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
        const update = (url) => {
            fetch(url)
            .then(response => response.text())
            .then(data => {
                console.debug(data);

                if (data.length) {
                    data = JSON.parse(data);

                    statusSpan.textContent = data.status;
                    percentageSpan.textContent = data.progress;

                    if (data.progress < 100) {
                        setTimeout(() => update(url), 1000);
                        return;
                    }
                }

                clearBatchJob().then(() => {
                    percentageSpan.textContent = 100;
                    enable(processExistingPostsButton);
                    disable(cancelProcessingButton);
                    hide(processingContainer);
                    show(processingComplete);
                });
            });
        };

        // get this from the ajaxObj; job_id, job_type
        const url = `${processorEndpoint}/jobs/${coyote_ajax_obj.job_id}`;

        update(url);
    };

    load();
});
