window.addEventListener('DOMContentLoaded', function () {
    console.debug('[Coyote]', 'settings loaded');

    const STATUS_CANCELED = 'canceled';

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

    let currentJob = undefined;

    if ('job_id' in coyote_ajax_obj && !!coyote_ajax_obj.job_id) {
        currentJob = {
            id: coyote_ajax_obj.job_id,
            status: 'running',
            progress: coyote_ajax_obj.job_progress
        }

        percentageSpan.textContent = currentJob.progress;
        statusSpan.textContent = currentJob.status;
    }


    const formDataForAction = action => {
        const formData = new FormData();

        formData.append('_ajax_nonce', coyote_ajax_obj.nonce);
        formData.append('action', action);

        return formData;
    }

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

            if (currentJob !== undefined) {
                return runJob()
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

        const formData = formDataForAction('coyote_verify_resource_group');

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
            if (oldId && (newId !== oldId)) {
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

        const formData = formDataForAction('coyote_start_batch_job');
        formData.append('size', byId('coyote_batch_size').value);

        fetch(coyote_ajax_obj.ajax_url, {
            mode: 'cors',
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(getJobId)
            .then(jobId => {
                if (jobId !== undefined) {
                    currentJob = {
                        id: jobId,
                        status: 'created',
                        progress: 0
                    };

                    percentageSpan.textContent = currentJob.progress;
                    statusSpan.textContent = currentJob.status;

                    cancelProcessingButton.addEventListener('click', cancelProcessing);
                    disable(processExistingPostsButton);
                    hide(processingComplete);
                    show(processingStatus);
                    show(processingContainer);

                    runJob();
                } else {
                    console.debug("Unable to obtain job id!")
                    errorStatus();
                    enable(cancelProcessingButton);
                }
            })
            .catch((error) => {
                console.debug("Error: ", error);
                errorStatus();
                enable(cancelProcessingButton);
            });
    };

    const getJobId = function (responseText) {
        const response = responseText.trim();

        if (response === "0" || response === "") {
            return undefined;
        }

        return response;
    }

    const cancelProcessing = function () {
        currentJob.status = STATUS_CANCELED;

        const formData = formDataForAction('coyote_cancel_batch_job');
        formData.append('id', currentJob.id);

        fetch(coyote_ajax_obj.ajax_url, {
            mode: 'cors',
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(reply => {
                statusSpan.innerText = "canceled";
                enable(processExistingPostsButton);
                disable(cancelProcessingButton);
            });
    }

    const decreaseBatchSize = function () {
        const formData = formDataForAction('coyote_resize_batch_job')
        formData.append('id', currentJob.id);

        return fetch(coyote_ajax_obj.ajax_url, {
            mode: 'cors',
            method: 'POST',
            body: formData
        });
    }

    const runJob = function () {
        if (currentJob.status === STATUS_CANCELED) {
            return cancelProcessing();
        }

        const formData = formDataForAction('coyote_run_batch_job')
        formData.append('id', currentJob.id);

        return fetch(coyote_ajax_obj.ajax_url, {
            mode: 'cors',
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(data => {
                if (currentJob.status === STATUS_CANCELED) {
                    return cancelProcessing();
                }

                data = data.trim();

                if (data === "0") {
                    errorStatus();
                    return;
                }

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
                        setTimeout(runJob, 500);
                        return;
                    }
                }

                percentageSpan.textContent = 100;
                enable(processExistingPostsButton);
                disable(cancelProcessingButton);
                hide(processingContainer);
                show(processingComplete);
            }).catch(() => {
                // error during run, resize and try again
                decreaseBatchSize().then(() => setTimeout(runJob, 500));
                return;
            });
    };

    load();
});
