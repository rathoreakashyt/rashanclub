$(function() {
    "use strict";
    var base_url = $('#base_url').val();

    var table = $('.datatables-basic-wallets').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: base_url + '/business-club/wallets',
            type: 'GET',
        },
        columns: [
            { data: 'sn', name: 'sn' },
            { data: 'customer_name', name: 'customer_name' },
            { data: 'customer_phone', name: 'customer_phone' },
            { data: 'total_earned', name: 'total_earned', className: 'text-end' },
            { data: 'balance', name: 'balance', className: 'text-end' },
            { data: 'total_redeemed', name: 'total_redeemed', className: 'text-end' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        columnDefs: [
            {
                targets: 6,
                render: function(data, type, row) {
                    var btns = '<button class="btn btn-sm btn-label-info view-transactions me-1" data-wallet-id="' + row.id + '"><i class="ti tabler-eye"></i> Transactions</button>';
                    btns += '<a href="' + base_url + '/business-club/partner/' + row.customer_id + '/profile" class="btn btn-sm btn-label-primary"><i class="ti tabler-user"></i> Profile</a>';
                    return btns;
                }
            }
        ],
        order: [[4, 'desc']],
        drawCallback: function() {
            $('.view-transactions').on('click', function() {
                var walletId = $(this).data('wallet-id');
                $.get(base_url + '/business-club/wallets/' + walletId + '/transactions', function(res) {
                    var html = '';
                    $.each(res.data, function(i, tx) {
                        html += '<tr>' +
                            '<td>' + tx.date + '</td>' +
                            '<td><span class="badge bg-label-' + (tx.type === 'Credit' ? 'success' : 'danger') + '">' + tx.type + '</span></td>' +
'<td class="text-end">₹' + tx.amount + '</td>' +
'<td class="text-end">₹' + tx.balance_before + '</td>' +
'<td class="text-end">₹' + tx.balance_after + '</td>' +
                            '<td>' + tx.description + '</td>' +
                            '</tr>';
                    });
                    if (!html) {
                        html = '<tr><td colspan="6" class="text-center">No transactions found</td></tr>';
                    }
                    $('#transactionsBody').html(html);
                    $('#transactionsModal').modal('show');
                });
            });
        }
    });
});
