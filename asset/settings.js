window.addEventListener('DOMContentLoaded', function () {
    console.debug('[Coyote]', 'settings loaded');

    const hide = node => node.setAttribute('hidden', '');
    const show = node => node.removeAttribute('hidden');

    const disable = node => node.setAttribute('disabled', '');
    const enable  = node => node.removeAttribute('disabled');

    const byId = x => document.getElementById(x);
    const $ = x => document.querySelector(x);

    const coyoteOrganizationIdSelect = byId('coyote_api_organization_id');
    const coyoteOrganizationChangeAlert = byId('coyote_org_change_alert');

    const processExistingPostsButton = byId('coyote_process_existing_posts');
    const cancelProcessingButton = byId('coyote_cancel_processing');

    const processingComplete = byId('coyote_processing_complete');
    const processingStatus = byId('coyote_processing_status');
    const processingContainer = byId('coyote_processing');

    const percentageSpan = $('#coyote_processing span');
    const statusSpan = $('#coyote_job_status span');

    const verifyResourceGroupButton = $('#coyote_verify_resource_group');
    const verifyResourceGroupStatus = $('#coyote_verify_resource_group_status');

    const errorStatus = () => statusSpan.textContent = 'error';

    const processorEndpoint = byId('coyote_processor_endpoint') && byId('coyote_processor_endpoint').value;

    const load = () => {
        if (verifyResourceGroupButton) {
            verifyResourceGroupButton.addEventListener('click', verifyResourceGroup);
        }

        if (coyoteOrganizationIdSelect) {
            const organizationId = coyoteOrganizationIdSelect.value;
            coyoteOrganizationIdSelect.addEventListener('change', changeOrganization(organizationId).bind(coyoteOrganizationIdSelect));
        }

        if (processExistingPostsButton) {
            processExistingPostsButton.addEventListener('click', startProcessing);
            cancelProcessingButton.addEventListener('click', cancelProcessing);

            if (processExistingPostsButton.hasAttribute('disabled')) {
                return updateProgress()
                    .then(() => {
                        enable(cancelProcessingButton);
                    })
                    .catch(() => {
                        errorStatus();
                        enable(cancelProcessingButton);
                    });
            }
        }
    }

    const verifyResourceGroup = function () {
        disable(verifyResourceGroupButton);
        verifyResourceGroupStatus.textContent = "Verifying resource group...";

        const formData = new FormData();

        formData.append('_ajax_nonce', coyote_ajax_obj.nonce);
        formData.append('action', 'coyote_verify_resource_group');

        fetch(coyote_ajax_obj.ajax_url, {
            mode: 'cors',
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(reply => reply.trim())
        .then(reply => {
            if (!reply.length) {
                verifyResourceGroupStatus.textContent = "Error verifying resource group.";
            } else {
                verifyResourceGroupStatus.textContent = `Resource group verified with ID ${reply}.`;
            }
            enable(verifyResourceGroupButton);
        })
        .catch(error => {
            console.debug("Verification error:", error);
            verifyResourceGroupStatus.textContent = "Error verifying resource group.";
            enable(verifyResourceGroupButton);
        });
    };

    const changeOrganization = function (oldId) {
        const changeAlert = coyoteOrganizationChangeAlert;
        return function () {
            const newId = coyoteOrganizationIdSelect.value;
            if (oldId && (newId != oldId)) {
                changeAlert.textContent = '';
                changeAlert.textContent = changeAlert.dataset.message;
                return;
            }
            changeAlert.textContent = '';
        };
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
            if (reply.trim() == "1") {
                disable(processExistingPostsButton);
                hide(processingComplete);
                show(processingStatus);
                show(processingContainer);
                updateProgress();
            }
        })
        .catch((error) => {
            console.debug("Error: ", error);
            errorStatus();
            enable(cancelProcessingButton);
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
            console.error("Error: ", e);
            return Promise.reject("Invalid json");
        }
    }

    const cancelJob = function () {
        if (coyote_ajax_obj.job_id) {
            job_id = coyote_ajax_obj.job_id;
            delete coyote_ajax_obj.job_id;

            return fetch(`${processorEndpoint}/jobs/${job_id}`, {
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
                mode: 'cors',
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(reply => {
                statusSpan.innerText = "cancelled";
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
            mode: 'cors',
            method: 'POST',
            body: formData
        });
    }

    const updateProgress = function () {
        const update = (url) => {
            if (!coyote_ajax_obj.job_id) {
                return Promise.resolve();
            }

            return fetch(url)
            .then(response => response.text())
            .then(data => {
                if (data.length) {
                    try {
                        data = JSON.parse(data);
                    } catch (error) {
                        console.error("Error:", error);
                        errorStatus();
                    }

                    statusSpan.textContent = data.status;
                    percentageSpan.textContent = data.progress;

                    if (data.progress < 100 && data.status !== 'error') {
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
                }).catch(errorStatus);
            }).catch(() => {
                // job does not exist
                cancelProcessing()
            });
        };

        // get this from the ajaxObj; job_id, job_type
        const url = `${processorEndpoint}/jobs/${coyote_ajax_obj.job_id}`;

        return update(url);
    };

    load();
});
