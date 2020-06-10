define([
    'jquery',
    'TYPO3/CMS/Backend/Modal',
    'TYPO3/CMS/NsGoogledocs/Main',
    'TYPO3/CMS/NsGoogledocs/Datatables',
    'TYPO3/CMS/Backend/jquery.clearable'
], function ($, Model) {
    $('.btn-import').click(function(e) {
        e.preventDefault();
        $('.import-doc-Name').html($(this).attr('data-fileName'));
        $('.import-page-Name').html($(this).attr('data-pageTitle'));
        $('#google-docs-name').val($(this).attr('data-fileName'));
        $('#google-docs-url').val($(this).attr('data-viewurl'));
        $('#google-docs-id').val($(this).attr('data-fileID'));
    });
    $('.field-info-trigger').click(function(e) {
        $(this).parents('.form-group').find('.field-info-text').slideToggle();
    });
    i = 0;
    var id;
    var elem = document.getElementById("custom-progress-bar");
    var width = 1;
    function frame() {
        if (width >= 100) {
            clearInterval(id);
            i = 0;
        } else {
            width++;
            elem.style.width = width + "%";
            elem.innerHTML = width + "%";
        }
    }
    $('#docsImport').on('submit',function(e){
        e.preventDefault();
        width = 1;
        $('.ns-googledocs-progressbar-wrap-main').removeClass('hide');
        $('.import-footer').addClass('hide');
        $('.ns-googledocs-progressbar-wrap-main').fadeIn(500);
        id = setInterval(frame, 1000);
        url = $(this).attr('action');
        $.ajax({
            url:url,
            method:'post',
            data:$(this).serializeArray(),
            success:function(result){
                clearInterval(id);
                if(result !== '') {
                    var obj = $.parseJSON(result);
                    if (obj.status == true) {
                        if (obj.affectedRows != '' && obj.affectedRows > 0) {
                            require(['TYPO3/CMS/Backend/Notification'], function(Notification) {
                                Notification.warning(obj.affectedRows + ' record(s) deleted before importing Google doc.');
                            });    
                        }
                        require(['TYPO3/CMS/Backend/Notification'], function(Notification) {
                            Notification.success('Well done', 'The document was imported successfully.');
                        });
                    } else {
                        require(['TYPO3/CMS/Backend/Notification'], function(Notification) {
                            Notification.error('Error', 'The document was not imported successfully.');
                        });
                    }
                } else {
                     require(['TYPO3/CMS/Backend/Notification'], function(Notification) {
                        Notification.warning('Warning', 'Something went wrong.');
                    });
                }
                var elem = document.getElementById("custom-progress-bar");
                elem.style.width = 100 + "%";
                elem.innerHTML = 100 + "%";
                setTimeout(function(){
                    $('.ns-googledocs-progressbar-wrap-main').fadeOut(500, function() {
                        elem.style.width = 1 + "%";
                        elem.innerHTML = 1;
                        $('.import-footer').removeClass('hide');
                        $('#importGooleDocsModal').modal('hide');
                    });
                }, 1000);


            }
        })
    });

    $('#update-user-info,#update-user-info-global').on('submit',function(e){
        e.preventDefault();
        url = $(this).attr('action');
        $.ajax({
            url:url,
            method:'post',
            data:$(this).serializeArray(),
            success:function(result){
                if(result !== '') {
                    var obj = $.parseJSON(result);
                    if (obj.status == true) {
                        require(['TYPO3/CMS/Backend/Notification'], function(Notification) {
                            Notification.success('Well done', 'Your Configuration was setup successfully.');
                        });
                    } else {
                        require(['TYPO3/CMS/Backend/Notification'], function(Notification) {
                            Notification.error('Error', 'Your Configuration was not setup successfully.');
                        });
                    }
                } else {
                    require(['TYPO3/CMS/Backend/Notification'], function(Notification) {
                        Notification.warning('Warning', 'Something went wrong.');
                    });
                }
                window.location.reload();
            }
        })
    });
    $('.ns-googledocs-datatable').DataTable({
        "language": {
            "lengthMenu": "Show _MENU_ entries",
            "zeroRecords": "No records found!",
            "info": "Showing page _PAGE_ of _PAGES_",
            "infoEmpty": "No records available",
            "infoFiltered": "(filtered from _MAX_ total records)",
            paginate: {
                previous: '<<',
                next:     '>>'
            }

        },
        "ordering": false,
    });
    $('.ns-googledocs-table-wrap .dataTables_length select,\
    .ns-googledocs-table-wrap .dataTables_filter input').addClass('form-control');
});