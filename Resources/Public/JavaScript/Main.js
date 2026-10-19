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
    if (bs && typeof bs.Modal.getOrCreateInstance === 'function') {
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
    if (bs && typeof bs.Modal.getOrCreateInstance === 'function') {
        try {
            bs.Modal.getOrCreateInstance(modalElement).hide();
        } catch (err) {
            // fall through to the manual cleanup below
        }
    }

    // Always force the closed state: the modal may have been opened by the manual
    // fallback in showImportModal() (no Bootstrap instance to hide) or by another
    // Bootstrap build, so Modal#hide() alone can be a no-op.
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

    /**
     * Extbase plugin field prefix, e.g. tx_nsgoogledocs_…[…] —
     * must match hidden fields so PHP does not turn googleDocsID into an array
     * when section fields are posted (string value would be lost).
     */
    function getPluginFieldPrefix() {
        const name = String(
            $('#google-docs-page-id').attr('name')
            || $('#google-docs-id').attr('name')
            || $('#docsImport').find('input[name*="[pageId]"]').attr('name')
            || ''
        );
        const stripped = name.replace(/\[[^\]]+]$/, '');
        return stripped !== '' ? stripped : name;
    }

    function resolveGoogleDocsFileId($btn) {
        if (!$btn || !$btn.length) {
            return '';
        }
        return String(
            $btn.attr('data-file-id')
            || $btn.attr('data-fileid')
            || $btn.attr('data-fileID')
            || $btn.data('fileId')
            || $btn.data('fileid')
            || $btn.closest('tr').attr('data-id')
            || ''
        ).trim();
    }

    function getStoredGoogleDocsFileId($form) {
        const $f = $form && $form.length ? $form : $('#docsImport');
        return String(
            $f.find('#google-docs-id').val()
            || $f.attr('data-ns-gd-file-id')
            || $f.data('nsGdFileId')
            || ''
        ).trim();
    }

    function setGoogleDocsFileId(fileId) {
        const id = String(fileId || '').trim();
        const $form = $('#docsImport');
        $form.find('#google-docs-id').val(id);
        $form.attr('data-ns-gd-file-id', id);
        $form.data('nsGdFileId', id);
        return id;
    }

    function getElementTypeOptions() {
        const el = document.getElementById('ns-gd-element-type-options');
        if (!el) {
            return { header: 'Header', text: 'Text Element', textpic: 'Text & Image', image: 'Image Only' };
        }
        try {
            return JSON.parse(el.textContent || '{}') || {};
        } catch (err) {
            return { header: 'Header', text: 'Text Element', textpic: 'Text & Image', image: 'Image Only' };
        }
    }

    function resetSectionsUi() {
        $('#ns-gd-sections-body').empty();
        $('.ns-gd-sections-wrap').addClass('hide');
        $('.ns-gd-sections-error').addClass('hide').text('');
        $('.ns-gd-sections-loading').addClass('hide');
        $('.btn-import-now').prop('disabled', true);
    }

    function renderSections(sections, defaultType) {
        const $tbody = $('#ns-gd-sections-body');
        $tbody.empty();
        const options = getElementTypeOptions();
        const prefix = getPluginFieldPrefix();
        const type = defaultType || $('#docsImport').attr('data-default-type') || 'textpic';

        (sections || []).forEach(function (section) {
            const index = section.index;
            const selected = section.defaultType || type;
            let typeOptionsHtml = '';
            Object.keys(options).forEach(function (value) {
                typeOptionsHtml += '<option value="' + value + '"'
                    + (value === selected ? ' selected' : '') + '>'
                    + $('<div>').text(options[value]).html()
                    + '</option>';
            });

            const title = section.title || '';
            // Sections without a real heading show their body excerpt as placeholder
            // instead of a blank title cell; the header stays empty unless edited.
            const fallbackTitle = title === '' ? String(section.fallbackTitle || '') : '';
            const escapeAttr = function (value) {
                return $('<div>').text(value).html().replace(/"/g, '&quot;');
            };
            const row = ''
                + '<tr data-section-index="' + index + '">'
                + '<td class="ns-gd-section-enabled-cell"><input type="checkbox" class="ns-gd-section-enabled" name="' + prefix + '[sections][' + index + '][enabled]" value="1" checked="checked" /></td>'
                + '<td><select class="form-select form-control" name="' + prefix + '[sections][' + index + '][CType]">' + typeOptionsHtml + '</select></td>'
                + '<td><input type="text" class="form-control" name="' + prefix + '[sections][' + index + '][header]" value="'
                + escapeAttr(title) + '"'
                + (fallbackTitle !== '' ? ' placeholder="' + escapeAttr(fallbackTitle) + '" title="' + escapeAttr(fallbackTitle) + '"' : '')
                + ' /></td>'
                + '</tr>';
            $tbody.append(row);
        });

        $('.ns-gd-sections-wrap').removeClass('hide');
        $('.btn-import-now').prop('disabled', false);
    }

    function loadDocSections() {
        const $form = $('#docsImport');
        const previewUrl = String($form.attr('data-preview-action') || '').trim();
        const googleDocsId = getStoredGoogleDocsFileId($form);
        if (!previewUrl || !googleDocsId) {
            $('.ns-gd-sections-error').removeClass('hide').text('Missing preview URL or Google Docs id.');
            return;
        }

        setGoogleDocsFileId(googleDocsId);
        resetSectionsUi();
        // Keep file id after resetSectionsUi (it does not clear hidden fields).
        setGoogleDocsFileId(googleDocsId);
        $('.ns-gd-sections-loading').removeClass('hide');

        $.ajax({
            url: previewUrl,
            method: 'post',
            data: $form.serialize(),
            dataType: 'json',
            success: function (result) {
                $('.ns-gd-sections-loading').addClass('hide');
                const obj = parseJsonPayload(result);
                if (obj && obj.status === true && Array.isArray(obj.sections)) {
                    renderSections(obj.sections, obj.defaultElementType);
                } else {
                    $('.ns-gd-sections-error').removeClass('hide').text(
                        (obj && obj.error) ? obj.error : 'Could not read document sections.'
                    );
                }
            },
            error: function (xhr) {
                $('.ns-gd-sections-loading').addClass('hide');
                let message = 'Could not read document sections.';
                try {
                    const obj = parseJsonPayload(xhr.responseText);
                    if (obj && obj.error) {
                        message = obj.error;
                    }
                } catch (err) {
                    // keep default
                }
                $('.ns-gd-sections-error').removeClass('hide').text(message);
            }
        });
    }

    $(document).off('click.nsGdImportOpen', '[data-bs-target="#importGooleDocsModal"]')
        .on('click.nsGdImportOpen', '[data-bs-target="#importGooleDocsModal"]', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            const fileId = resolveGoogleDocsFileId($btn);
            const fileName = $btn.attr('data-file-name') || $btn.attr('data-fileName') || $btn.attr('data-filename') || '';
            const pageTitle = $btn.attr('data-page-title') || $btn.attr('data-pageTitle') || $btn.attr('data-pagetitle') || '';
            $('.import-doc-Name').html(fileName);
            $('.import-page-Name').html(pageTitle);
            $('#google-docs-name').val(fileName);
            $('#google-docs-url').val($btn.attr('data-viewurl') || '');
            setGoogleDocsFileId(fileId);

            resetSectionsUi();
            setGoogleDocsFileId(fileId);
            showImportModal(document.getElementById('importGooleDocsModal'));
            loadDocSections();
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
            const googleDocsId = setGoogleDocsFileId(getStoredGoogleDocsFileId($form));
            if (!googleDocsId) {
                Notification.error('Error', 'Missing Google Docs file id. Please click Import Now again.');
                return;
            }

            width = 1;
            $('.ns-gd-sections-error').addClass('hide').text('');
            $('.ns-googledocs-progressbar-wrap-main').stop(true, true).removeClass('hide').show();
            $('.import-footer').addClass('hide');
            progressIntervalId = setInterval(frame, 1000);

            // Build POST data explicitly so googleDocsID stays a string even if
            // a bad section field name would otherwise collide in PHP.
            const prefix = getPluginFieldPrefix();
            const postData = $form.serializeArray().filter(function (item) {
                return !/\[googleDocsID]$/i.test(String(item.name || ''));
            });
            postData.push({
                name: prefix ? prefix + '[googleDocsID]' : 'googleDocsID',
                value: googleDocsId
            });

            $.ajax({
                url: $form.attr('action'),
                method: 'post',
                data: $.param(postData),
                dataType: 'json',
                success: function (result) {
                    clearInterval(progressIntervalId);
                    const obj = parseJsonPayload(result);
                    if (obj && obj.status === true) {
                        const importedCount = parseInt(obj.imported || obj.affectedRows || 0, 10);
                        const successDetail = importedCount > 0
                            ? 'The document was imported successfully (' + importedCount + ' content element(s)).'
                            : 'The document was imported successfully.';
                        Notification.success('Well done', successDetail);
                        setProgress(100);
                        // Short pause so the editor sees 100%, then close (toast stays visible).
                        setTimeout(function () {
                            resetImportProgress();
                            closeModalElement(document.getElementById('importGooleDocsModal'));
                        }, 400);
                        return;
                    }
                    showImportError((obj && obj.error) ? obj.error : 'The document was not imported successfully.');
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
                    showImportError(message);
                }
            });
        });

    function setProgress(value) {
        const progressElem = document.getElementById('custom-progress-bar');
        if (progressElem) {
            progressElem.style.width = value + '%';
            progressElem.innerHTML = value + '%';
        }
    }

    /**
     * Hide the progress bar and restore the footer without relying on jQuery
     * animations (their completion callback is what used to close the modal).
     */
    function resetImportProgress() {
        clearInterval(progressIntervalId);
        width = 1;
        $('.ns-googledocs-progressbar-wrap-main').stop(true, true).addClass('hide').hide();
        setProgress(1);
        $('.import-footer').removeClass('hide');
    }

    /** Keep the modal open on failure and show the reason inside it as well as in a toast. */
    function showImportError(message) {
        Notification.error('Error', message);
        resetImportProgress();
        $('.ns-gd-sections-error').removeClass('hide').text(message);
    }

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
