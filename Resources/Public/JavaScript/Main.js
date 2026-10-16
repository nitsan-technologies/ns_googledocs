import Notification from '@typo3/backend/notification.js';

function getJquery() {
    if (typeof window !== 'undefined' && window.jQuery) {
        return window.jQuery;
    }
    return null;
}

function getBootstrapModalApi() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        return bootstrap;
    }
    if (typeof window !== 'undefined' && window.bootstrap && window.bootstrap.Modal) {
        return window.bootstrap;
    }
    return null;
}

function showImportModal(modalElement) {
    if (!modalElement) {
        return;
    }
    // Keep overlay on <body> so position:fixed centers in the viewport
    // (module wrappers / scroll containers would otherwise pin it incorrectly).
    if (modalElement.parentElement !== document.body) {
        document.body.appendChild(modalElement);
    }

    const bs = getBootstrapModalApi();
    if (bs) {
        bs.Modal.getOrCreateInstance(modalElement, {
            backdrop: false,
            keyboard: true,
            focus: true
        }).show();
        return;
    }

    modalElement.classList.add('show');
    modalElement.style.display = 'flex';
    modalElement.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
}

function closeModalElement(modalElement) {
    if (!modalElement) {
        return;
    }

    const bs = getBootstrapModalApi();
    if (bs) {
        bs.Modal.getOrCreateInstance(modalElement).hide();
        return;
    }

    modalElement.classList.remove('show');
    modalElement.style.display = 'none';
    modalElement.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
}

function parseJsonPayload(result) {
    if (result && typeof result === 'object') {
        return result;
    }
    if (typeof result !== 'string' || result === '') {
        return null;
    }
    try {
        return JSON.parse(result);
    } catch (e) {
        return null;
    }
}

function initNsGoogledocsUi() {
    const $ = getJquery();
    if (!$) {
        return;
    }

    $(document).off('click.nsGdImportOpen', '[data-bs-target="#importGooleDocsModal"]')
        .on('click.nsGdImportOpen', '[data-bs-target="#importGooleDocsModal"]', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            $('.import-doc-Name').html($btn.attr('data-fileName') || '');
            $('.import-page-Name').html($btn.attr('data-pageTitle') || '');
            $('#google-docs-name').val($btn.attr('data-fileName') || '');
            $('#google-docs-url').val($btn.attr('data-viewurl') || '');
            $('#google-docs-id').val($btn.attr('data-fileID') || '');

            showImportModal(document.getElementById('importGooleDocsModal'));
        });

    $(document).off('click.nsGdImportClose', '.modal .close, .modal [data-bs-dismiss="modal"]')
        .on('click.nsGdImportClose', '.modal .close, .modal [data-bs-dismiss="modal"]', function (e) {
            e.preventDefault();
            closeModalElement(this.closest('.modal'));
        });

    $(document).off('click.nsGdImportBackdrop', '.google-docs-wizard-modal.modal')
        .on('click.nsGdImportBackdrop', '.google-docs-wizard-modal.modal', function (e) {
            if (e.target === this) {
                closeModalElement(this);
            }
        });

    $('.field-info-trigger').off('click.nsGdInfo').on('click.nsGdInfo', function () {
        $(this).parents('.form-group').find('.field-info-text').slideToggle();
    });

    let progressIntervalId;
    let width = 1;

    function frame() {
        const progressElem = document.getElementById('custom-progress-bar');
        if (!progressElem) {
            return;
        }
        if (width >= 100) {
            clearInterval(progressIntervalId);
        } else {
            width++;
            progressElem.style.width = width + '%';
            progressElem.innerHTML = width + '%';
        }
    }

    $(document).off('submit.nsGdDocsImport', '#docsImport')
        .on('submit.nsGdDocsImport', '#docsImport', function (e) {
            e.preventDefault();

            const $form = $(this);
            const googleDocsId = String($form.find('#google-docs-id').val() || '').trim();
            if (!googleDocsId) {
                Notification.error('Error', 'Missing Google Docs file id. Please click Import Now again.');
                return;
            }

            width = 1;
            $('.ns-googledocs-progressbar-wrap-main').removeClass('hide').fadeIn(500);
            $('.import-footer').addClass('hide');
            progressIntervalId = setInterval(frame, 1000);

            $.ajax({
                url: $form.attr('action'),
                method: 'post',
                data: $form.serialize(),
                dataType: 'json',
                success: function (result) {
                    clearInterval(progressIntervalId);
                    const obj = parseJsonPayload(result);
                    const progressElem = document.getElementById('custom-progress-bar');
                    if (obj && obj.status === true) {
                        if (obj.affectedRows && obj.affectedRows > 0) {
                            Notification.warning(obj.affectedRows + ' record(s) deleted before importing Google doc.');
                        }
                        Notification.success('Well done', 'The document was imported successfully.');
                    } else {
                        Notification.error('Error', 'The document was not imported successfully.');
                    }
                    if (progressElem) {
                        progressElem.style.width = '100%';
                        progressElem.innerHTML = '100%';
                    }
                    setTimeout(function () {
                        $('.ns-googledocs-progressbar-wrap-main').fadeOut(500, function () {
                            if (progressElem) {
                                progressElem.style.width = '1%';
                                progressElem.innerHTML = '1';
                            }
                            $('.import-footer').removeClass('hide');
                            closeModalElement(document.getElementById('importGooleDocsModal'));
                        });
                    }, 1000);
                },
                error: function (xhr) {
                    clearInterval(progressIntervalId);
                    let message = 'The document was not imported successfully.';
                    try {
                        const obj = parseJsonPayload(xhr.responseText);
                        if (obj && obj.error) {
                            message = obj.error;
                        }
                    } catch (err) {
                        // keep default
                    }
                    Notification.error('Error', message);
                    $('.import-footer').removeClass('hide');
                    $('.ns-googledocs-progressbar-wrap-main').addClass('hide');
                }
            });
        });

    $(document).off('submit.nsGdUpdate', '#update-user-info, #update-user-info-global')
        .on('submit.nsGdUpdate', '#update-user-info, #update-user-info-global', function (e) {
            e.preventDefault();
            const $form = $(this);
            $.ajax({
                url: $form.attr('action'),
                method: 'post',
                data: $form.serialize(),
                dataType: 'json',
                success: function (result) {
                    const obj = parseJsonPayload(result);
                    if (obj && obj.status === true) {
                        Notification.success('Well done', 'Your Configuration was setup successfully.');
                    } else {
                        Notification.error('Error', 'Your Configuration was not setup successfully.');
                    }
                    window.location.reload();
                },
                error: function () {
                    Notification.error('Error', 'Your Configuration was not setup successfully.');
                }
            });
        });

    if ($.fn.DataTable && $('.ns-googledocs-datatable').length && !$.fn.DataTable.isDataTable('.ns-googledocs-datatable')) {
        $('.ns-googledocs-datatable').DataTable({
            language: {
                lengthMenu: 'Show _MENU_ entries',
                zeroRecords: 'No records found!',
                info: 'Showing page _PAGE_ of _PAGES_',
                infoEmpty: 'No records available',
                infoFiltered: '(filtered from _MAX_ total records)',
                paginate: {
                    previous: '<<',
                    next: '>>'
                }
            },
            ordering: false
        });
        $('.ns-googledocs-table-wrap .dataTables_length select, .ns-googledocs-table-wrap .dataTables_filter input')
            .addClass('form-control');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNsGoogledocsUi);
} else {
    initNsGoogledocsUi();
}
