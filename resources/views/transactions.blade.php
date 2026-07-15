@extends('layouts.header')
<style>
    .content-area:has(.welcome-client) {
        margin-top: 90px !important;
    }
    
    .transaction-table th {
        text-align: center;
    }
    .btn-view {
        width: 100px;
        font-size: 14px;
    }
    .dashboard-stats {
        display: flex;
        justify-content: space-around;
    }
    .dashboard-stats div {
        text-align: center;
        padding: 20px;
        background-color: #f8f9fa;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        width: 30%;
    }
    .welcome {
        margin-top: auto !important;
    }
    .card-header {
        font-size: 1.25rem;
        font-weight: bold;
    }
    .card-body {
        padding: 20px;
    }
    .filter-container {
        margin-bottom: 20px;
    }
    .card {
      border-radius: 15px !important;
    }
    @media (max-width: 768px) {
        .modal-dialog {
            margin: 1rem;
            max-width: 100%;
        }

        .chosen-container {
            width: 100% !important;
        }

        .chosen-drop {
            max-height: 200px;
            overflow-y: auto;
        }

    }

    .search-name-responsive{
        width: 180px !important;
    }

    @media (max-width: 576px) {
        .search-name-responsive{
            font-size: 12px !important;
            width: 170px !important;
            height: 43px !important; 
        }
    }

.chosen-container .chosen-single {
  height: calc(2.25rem + 2px);
  padding: 0.375rem 0.75rem;
  font-size: 1rem;
  line-height: 1.5;
  color: #495057;
  background-color: #fff;
  border: 1px solid #ced4da;
  border-radius: 0.25rem;
  box-shadow: none;
}

.chosen-container-active.chosen-with-drop .chosen-single {
  border-color: #80bdff;
  box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.chosen-container .chosen-drop {
  border: 1px solid #ced4da;
  border-top: none;
  border-radius: 0 0 0.25rem 0.25rem;
  box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
}

.chosen-container .chosen-results {
  max-height: 200px;
  overflow-y: auto;
}

.chosen-container .chosen-search input {
  height: calc(1.5em + 0.75rem + 2px);
  padding: 0.375rem 0.75rem;
  font-size: 1rem;
  border: 1px solid #ced4da;
  border-radius: 0.25rem;
}

.dataTables_length {
  float: left;
  margin-top: 15px;
  margin-bottom: 5px;
}

.dataTables_filter {
  float: right;
  margin-top: 15px;
  margin-bottom: 5px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:focus {
    box-shadow: none;
    outline: none;
}

.export-btn-custom {
    width: 130px;
    height: 38px;
    font-size: 14px;
    padding: 6px 12px;
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
    margin-bottom: 0 !important;
}

table.dataTable {
    margin-top: 5px !important;
}

.card-body > .dataTables_wrapper {
    margin-bottom: 0 !important;
}

.dataTables_wrapper .row:first-child {
    margin-bottom: 0 !important;
}


</style>
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

@section('head')
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection
@section('content')
<div class="welcome @if(auth()->user()->role === 'Dealer') welcome-client @endif">
    <div class="row">
        <!-- Cards Section - All 4 cards in one row -->
        <div class="col-12">
            <div class="row mb-0 cards">
                <!-- Total Sales -->
                  <div class="col-sm-6 col-lg-4 col-xl-2">
                    <div class="card warning-card overflow-hidden text-bg-primary w-100">
                        <div class="card-body p-4">
                          <div class="mb-7">
                            <i class="ti ti-brand-producthunt fs-8 fw-lighter"></i>
                          </div>
                          <h5 class="text-white fw-bold fs-14 text-nowrap">
                          {{ $transactions->sum(function($transaction) {
                                return $transaction->price * $transaction->qty;
                            }) }}
                          </h5>
                          <p class="opacity-50 mb-0 ">TOTAL SALES</p>
                        </div>
                    </div>
                </div>
                  <div class="col-sm-6 col-lg-4 col-xl-2">
                    <div class="card danger-card overflow-hidden text-bg-primary w-100">
                        <div class="card-body p-4">
                          <div class="mb-7">
                            <i class="ti ti-brand-producthunt fs-8 fw-lighter"></i>
                          </div>
                          <h5 class="text-white fw-bold fs-14 text-nowrap">
                            {{$transactions->count()}}
                          </h5>
                          <p class="opacity-50 mb-0 ">TRANSACTIONS</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4 col-xl-2">
                    <div class="card info-card overflow-hidden text-bg-primary w-100">
                        <div class="card-body p-4">
                          <div class="mb-7">
                            <i class="ti ti-brand-producthunt fs-8 fw-lighter"></i>
                          </div>
                          <h5 class="text-white fw-bold fs-14 text-nowrap">
                            {{$transactions->sum('qty')}}
                          </h5>
                          <p class="opacity-50 mb-0 ">QTY SOLD</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4 col-xl-2">
                    <div class="card info-card overflow-hidden text-bg-primary w-100">
                        <div class="card-body p-4">
                          <div class="mb-7">
                            <i class="ti ti-brand-producthunt fs-8 fw-lighter"></i>
                          </div>
                          <h5 class="text-white fw-bold fs-14 text-nowrap">
                            {{$transactions->sum('points_dealer') + $transactions->sum('points_client')}}
                          </h5>
                          <p class="opacity-50 mb-0 ">TOTAL POINTS</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12">
            <div class="card w-100">  
                <div class="card-body">
                  <div class="d-flex justify-content-start align-items-center gap-2 mb-3">
                      @if(auth()->user()->role == "Admin")
                          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModalAdmin">
                              <i class="bi bi-plus-lg"></i> Search Name
                          </button>
                          <div id="exportExcelContainer"></div>
                          <button type="button" class="btn btn-danger btn-sm" id="deleteSelectedBtn" title="Delete Selected" style="display: none; height: 38px;">
                              <i class="bi bi-trash"></i> Delete All
                          </button>
                      @else
                          <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#qrScannerModal">
                              Scan QR
                          </button>
                          <button type="button" class="btn btn-primary search-name-responsive" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                              <i class="bi bi-plus-lg"></i> Search Name
                          </button>
                      @endif
                  </div>

                  <div class="table-responsive">
                      <table class="table table-bordered table-striped transaction-table" id="example" style="width:100%">
                          <thead>
                              <tr>
                                  @if(auth()->user()->role == "Admin" && auth()->user()->can_delete === "on")
                                      <th scope="col" style="width: 50px; text-align: center;">
                                          <div class="d-flex align-items-center justify-content-center">
                                              <input type="checkbox" id="selectAll" title="Select All" style="cursor: pointer;">
                                          </div>
                                      </th>
                                  @endif
                                  <th scope="col">ID</th>
                                  <th scope="col">Date</th>
                                  <th scope="col">Quantity</th>
                                  <th scope="col">Amount</th>
                                  <th scope="col">Dealer</th>
                                  <th scope="col">Customer</th>
                                  <th scope="col">Dealer Points</th>
                                  <th scope="col">Customer Points</th>
                                  <th scope="col">Item</th>
                                  @if(auth()->user()->role == "Admin" && auth()->user()->can_delete === "on")
                                      <th scope="col" style="width: 80px; text-align: center;">Actions</th>
                                  @endif
                              </tr>
                          </thead>
                          <tbody id="transactionBody">
                              @foreach($transactions as $transaction)
                                  <tr id="transaction-row-{{$transaction->id}}">
                                      @if(auth()->user()->role == "Admin" && auth()->user()->can_delete === "on")
                                          <td style="text-align: center;">
                                              <input type="checkbox" class="checkbox-item" data-id="{{$transaction->id}}" style="cursor: pointer;">
                                          </td>
                                      @endif
                                      <td>{{$transaction->id}}</td>
                                      <td>{{ date('M d, Y', strtotime($transaction->date)) }}</td>
                                      <td>{{ number_format($transaction->qty, 2) }}</td>
                                      <td>{{ number_format($transaction->qty * $transaction->price, 2) }}</td>
                                      <td>{{ $transaction->dealer->name ?? '' }}</td>
                                      <td>{{ $transaction->is_guest ? 'Guest' : ($transaction->customer->name ?? '') }}</td>
                                      <td><span class='text-success'>{{ $transaction->points_dealer }}</span></td>
                                      <td><span class='text-success'>{{ $transaction->points_client }}</span></td>
                                      <td>{{ strtoupper($transaction->item) }}</td>
                                      @if(auth()->user()->role == "Admin" && auth()->user()->can_delete === "on")
                                          <td style="text-align: center;">
                                              <button type="button" class="btn btn-danger btn-sm delete-single" 
                                                      data-id="{{ $transaction->id }}" 
                                                      title="Delete"
                                                      style="cursor: pointer;">
                                                  <i class="bi bi-trash"></i>
                                              </button>
                                          </td>
                                      @endif
                                  </tr>
                              @endforeach
                          </tbody>
                      </table>
                  </div>
                </div>
            </div>
        </div>
    </div>
</section>

@if(auth()->user()->role == "Admin")
  @include('new_transaction_admin')
@else
  @include('new_transaction')
@endif
@include('qr_scanner')

@endsection

@section('javascript')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

{{-- <script>
 $(document).ready(function() {
  $('#customerSelect').select2({
    dropdownParent: $('#addTransactionModal') // ✅ replace with your modal's ID
  });
  $('#customerSelect123').select2({
    dropdownParent: $('#addTransactionModalAdmin') // ✅ replace with your modal's ID
  });
  $('#dealer').select2({
    dropdownParent: $('#addTransactionModalAdmin') // ✅ replace with your modal's ID
  });
});
</script> --}}
<script>
  document.addEventListener('DOMContentLoaded', function () {
    new TomSelect('#customerSelect', {
      create: false,
      allowEmptyOption: true,
      placeholder: "Search Customer"
    });
    new TomSelect('#dealer', {
      create: false,
      allowEmptyOption: true,
      placeholder: "Search Dealer"
    });
  });
</script>
<script>
$(document).ready(function() {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    });

    const table = $('#example').DataTable({
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: 'Export Excel',
                className: 'btn btn-sm btn-success export-btn-custom',
                title: 'Transactions'
            }
        ],
        columnDefs: [
            { 
                orderable: false, 
                targets: [0, -1] // Disable sorting on first column (checkbox) and last column (actions)
            },
            {
                className: 'text-center', // Center align the checkbox column
                targets: [0]
            }
        ],
        destroy: true,
        order: [[1, 'desc']] // Default sort by ID column (index 1) in descending order
    });

    table.buttons().container().appendTo('#exportExcelContainer');

    $(document).on('change', '.checkbox-item', function() {
        updateUI();
    });

    $(document).on('change', '#selectAll', function() {
        const isChecked = $(this).prop('checked');
        $('.checkbox-item').prop('checked', isChecked);
        updateUI();
    });

    $(document).on('click', '#deleteSelectedBtn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        performBulkDelete();
    });

    $(document).on('click', '.delete-single', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const transactionId = $(this).data('id');
        performSingleDelete(transactionId);
    });

    function updateUI() {
        const $checkboxes = $('.checkbox-item');
        const $checked = $('.checkbox-item:checked');
        const checkedCount = $checked.length;
        const totalCount = $checkboxes.length;
        
        if (checkedCount > 0) {
            $('#deleteSelectedBtn').show();
        } else {
            $('#deleteSelectedBtn').hide();
        }
        
        const $selectAll = $('#selectAll');
        if (checkedCount === totalCount && totalCount > 0) {
            $selectAll.prop('checked', true);
            $selectAll.prop('indeterminate', false);
        } else if (checkedCount > 0) {
            $selectAll.prop('checked', false);
            $selectAll.prop('indeterminate', true);
        } else {
            $selectAll.prop('checked', false);
            $selectAll.prop('indeterminate', false);
        }
    }

    function performSingleDelete(transactionId) {
        if (!transactionId || isNaN(transactionId)) {
            Swal.fire('Error!', 'Invalid transaction ID', 'error');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: 'This transaction will be permanently deleted!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Deleting...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const deleteUrl = `{{ url('/transactions') }}/${transactionId}`;

                $.ajax({
                    url: deleteUrl,
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        _method: 'DELETE'
                    },
                    success: function(response) {
                        Swal.fire('Deleted!', response.success || 'Transaction deleted successfully', 'success').then(() => {
                            window.location.reload();
                        });
                    },
                    error: function(xhr, status, error) {
                        let message = 'An error occurred while deleting the transaction';
                        
                        if (xhr.status === 404) {
                            message = 'Route not found. Please check your routes configuration.';
                        } else if (xhr.status === 405) {
                            message = 'Method not allowed. Check if the route accepts DELETE method.';
                        } else if (xhr.status === 403) {
                            message = 'You are not authorized to delete this transaction.';
                        } else if (xhr.status === 500) {
                            message = 'Server error. Please check the server logs.';
                        }
                        
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.error) {
                                message = response.error;
                            } else if (response.message) {
                                message = response.message;
                            }
                        } catch (e) {
                            // Use default message
                        }
                        
                        Swal.fire('Error!', message, 'error');
                    }
                });
            }
        });
    }

    function performBulkDelete() {
        const selectedIds = $('.checkbox-item:checked').map(function() {
            return parseInt($(this).data('id'));
        }).get();

        if (selectedIds.length === 0) {
            Swal.fire('No Selection', 'Please select at least one transaction to delete.', 'warning');
            return;
        }

        const invalidIds = selectedIds.filter(id => isNaN(id) || id <= 0);
        if (invalidIds.length > 0) {
            Swal.fire('Error!', 'Some transaction IDs are invalid', 'error');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${selectedIds.length} transaction(s). This cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete them!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Deleting...',
                    text: 'Please wait while we delete the selected transactions.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const bulkDeleteUrl = '{{ url("/transactions/bulk-delete") }}';

                $.ajax({
                    url: bulkDeleteUrl,
                    type: 'POST',
                    data: {
                        ids: selectedIds,
                        _token: csrfToken
                    },
                    success: function(response) {
                        Swal.fire('Deleted!', response.success || 'Transactions deleted successfully', 'success').then(() => {
                            window.location.reload();
                        });
                    },
                    error: function(xhr, status, error) {
                        let message = 'An error occurred while deleting the transactions';
                        
                        if (xhr.status === 404) {
                            message = 'Route not found. Please check your routes configuration.';
                        } else if (xhr.status === 405) {
                            message = 'Method not allowed. Check if the route accepts POST method.';
                        } else if (xhr.status === 403) {
                            message = 'You are not authorized to delete these transactions.';
                        } else if (xhr.status === 500) {
                            message = 'Server error. Please check the server logs.';
                        }
                        
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.error) {
                                message = response.error;
                            } else if (response.message) {
                                message = response.message;
                            }
                        } catch (e) {
                            // Use default message
                        }
                        
                        Swal.fire('Error!', message, 'error');
                    }
                });
            }
        });
    }

    setTimeout(function() {
        updateUI();
    }, 100);
});
</script>

<script>
 let html5QrcodeScanner = null;

function startScanner() {
    if (!html5QrcodeScanner) {
        document.getElementById("reader").innerHTML = "";
        html5QrcodeScanner = new Html5Qrcode("reader");
    }

    const config = { fps: 10, qrbox: 250 };

    html5QrcodeScanner.start(
        { facingMode: "environment" }, 
        config,
        qrCodeMessage => {
            document.getElementById('result').innerText = qrCodeMessage;
            fetchUserInfo(qrCodeMessage);
            html5QrcodeScanner.stop();
        },
        errorMessage => {
            // optional: handle scanning errors
        }
    ).catch(err => {
        console.error("Unable to start scanning.", err);
    });
}

function stopScanner() {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.stop().then(() => {
            html5QrcodeScanner.clear();
            html5QrcodeScanner = null;
        }).catch(err => {
            console.warn("Failed to stop scanner", err);
            html5QrcodeScanner = null;
        });
    }
}

function fetchUserInfo(userId) {
      fetch(`{{ url('get-user') }}/${userId}`, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('userId').value = data.user.id;
            document.getElementById('userName').value = data.user.name;

            var qrModal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
            qrModal.hide();
            var transactionModal = new bootstrap.Modal(document.getElementById('addTransactionModaldd'));
            transactionModal.show();
        } else {
            alert("User not found");
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error fetching user info:', error);
        alert("Error fetching user info");
    });
}

document.getElementById('qrScannerModal').addEventListener('shown.bs.modal', startScanner);
document.getElementById('qrScannerModal').addEventListener('hidden.bs.modal', stopScanner);
</script>

@endsection
