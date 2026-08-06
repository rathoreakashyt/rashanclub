$(function() {
    "use strict";
    var base_url = $('#base_url').val();
    var language_name = $('#language_name').val();
    var language_path = '/resources/lang/' + language_name + '.json';

    var table = $('.datatables-basic-price-lists').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: base_url + '/price-list',
            type: 'GET',
            data: function(d) {
                d.draw = d.draw || 1;
            }
        },
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'description', name: 'description', defaultContent: '-' },
            { data: 'customer_type', name: 'customer_type', defaultContent: '-' },
            { data: 'item_count', name: 'item_count', searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        columnDefs: [
            {
                targets: 5,
                render: function(data, type, row) {
                    var editUrl = base_url + '/price-list/' + row.encrypted_id + '/edit';
                    var delUrl = base_url + '/price-list/' + row.encrypted_id;
                    return '<a href="' + editUrl + '" class="btn btn-icon btn-sm btn-label-primary"><i class="ti tabler-edit"></i></a> ' +
                        '<button class="btn btn-icon btn-sm btn-label-danger delete-btn" data-url="' + delUrl + '"><i class="ti tabler-trash"></i></button>';
                }
            }
        ],
        order: [[0, 'desc']],
        drawCallback: function() {
            $('.delete-btn').on('click', function() {
                var url = $(this).data('url');
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!',
                }).then(function(result) {
                    if (result.value) {
                        $.ajax({
                            url: url,
                            type: 'DELETE',
                            data: { _token: $('meta[name="csrf-token"]').attr('content') },
                            success: function() {
                                table.ajax.reload();
                                Swal.fire('Deleted!', 'Price list has been deleted.', 'success');
                            }
                        });
                    }
                });
            });
        }
    });
});
