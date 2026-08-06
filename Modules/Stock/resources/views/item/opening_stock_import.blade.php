@extends('backend.backend_layout')
@section('page-title', __('Opening Stock Import'))
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Opening Stock Import') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => '#'],
                ['label' => __('Items'), 'link' => '#'],
                ['label' => __('Opening Stock Import'), 'active' => true]
            ]
        ])
    </div>

    <div class="row">
        {{-- Card 1: General Product & Installment Product --}}
        <div class="col-12 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('General Product & Installment Product') }}</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">{{ __('Export items where Item Type is General Product or Installment Product. Excel columns: Name, Code, Item Type, Stock. Fill stock quantity (leave empty to skip row) and import.') }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('opening-stock-export-general') }}" class="btn btn-outline-primary">
                            <i class="ti tabler-download me-1"></i> {{ __('Export Template') }}
                        </a>
                    </div>
                </div>
                <div class="card-footer">
                    <form action="{{ route('opening-stock-import-general') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="file_general">{{ __('Import File') }} <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" name="file" id="file_general" accept=".xlsx,.xls,.csv" required />
                            <small class="text-muted">{{ __('Name, Code, Item Type, Stock (.xlsx, .xls, .csv)') }}</small>
                        </div>
                        @if ($errors->has('file') && !$errors->has('file_imei') && !$errors->has('file_medicine'))
                            <div class="alert alert-danger py-2">{{ $errors->first('file') }}</div>
                        @endif
                        <button type="submit" class="btn btn-primary">
                            <i class="ti tabler-upload me-1"></i> {{ __('Import') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Card 2: IMEI Product & Serial Product --}}
        <div class="col-12 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('IMEI Product & Serial Product') }}</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">{{ __('Export items where Item Type is IMEI Product or Serial Product. Excel columns: Name, Code, Item Type, Stock. Fill Stock with IMEI/Serial comma separated (e.g. 123, 234) Make sure after comma you have give an space.') }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('opening-stock-export-imei-serial') }}" class="btn btn-outline-primary">
                            <i class="ti tabler-download me-1"></i> {{ __('Export Template') }}
                        </a>
                    </div>
                </div>
                <div class="card-footer">
                    <form action="{{ route('opening-stock-import-imei-serial') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="file_imei">{{ __('Import File') }} <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" name="file" id="file_imei" accept=".xlsx,.xls,.csv" required />
                            <small class="text-muted">{{ __('Name, Code, Item Type, Stock (IMEI/Serial comma separated e.g. 123, 345)') }}</small>
                        </div>
                        @if ($errors->has('file_imei'))
                            <div class="alert alert-danger py-2">{{ $errors->first('file_imei') }}</div>
                        @endif
                        <button type="submit" class="btn btn-primary">
                            <i class="ti tabler-upload me-1"></i> {{ __('Import') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Card 3: Medicine Product --}}
        <div class="col-12 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Medicine Product') }}</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">{{ __('Export items where Item Type is Medicine Product. Excel columns: Name, Code, Item Type, Expiry Date Maintain, Stock. If Expiry Date Maintain is Yes, fill Stock as quantity/expiry_date comma separated (e.g. 12/2026-12-30, 12/2026-11-30). If No, fill Stock with quantity only.') }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('opening-stock-export-medicine') }}" class="btn btn-outline-primary">
                            <i class="ti tabler-download me-1"></i> {{ __('Export Template') }}
                        </a>
                    </div>
                </div>
                <div class="card-footer">
                    <form action="{{ route('opening-stock-import-medicine') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="file_medicine">{{ __('Import File') }} <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" name="file" id="file_medicine" accept=".xlsx,.xls,.csv" required />
                            <small class="text-muted">{{ __('Name, Code, Item Type, Expiry Date Maintain, Stock (.xlsx, .xls, .csv)') }}</small>
                        </div>
                        @if ($errors->has('file_medicine'))
                            <div class="alert alert-danger py-2">{{ $errors->first('file_medicine') }}</div>
                        @endif
                        <button type="submit" class="btn btn-primary">
                            <i class="ti tabler-upload me-1"></i> {{ __('Import') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Success & summary for Card 1 --}}
    @if (session('success_general'))
        <div class="alert alert-success">{{ session('success_general') }}</div>
    @endif
    @php $summaryGeneral = session('import_summary_general'); $failuresGeneral = session('import_failures_general') ?? []; @endphp
    @if ($summaryGeneral)
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">{{ __('General / Installment Import Summary') }}</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-4"><div class="border rounded p-2 text-center"><span class="text-muted small">{{ __('Processed') }}</span><div class="fw-bold">{{ $summaryGeneral['processed'] ?? 0 }}</div></div></div>
                    <div class="col-4"><div class="border rounded p-2 text-center"><span class="text-muted small">{{ __('Created') }}</span><div class="fw-bold text-success">{{ $summaryGeneral['created'] ?? 0 }}</div></div></div>
                    <div class="col-4"><div class="border rounded p-2 text-center"><span class="text-muted small">{{ __('Skipped') }}</span><div class="fw-bold text-warning">{{ $summaryGeneral['skipped'] ?? 0 }}</div></div></div>
                </div>
            </div>
        </div>
    @endif
    @if (count($failuresGeneral) > 0)
        <div class="card mb-4 border-danger">
            <div class="card-header bg-light"><h6 class="mb-0 text-danger">{{ __('General / Installment Import Errors') }}</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>{{ __('Row') }}</th><th>{{ __('Error') }}</th></tr></thead>
                        <tbody>
                            @foreach ($failuresGeneral as $f)
                                <tr>
                                    <td>{{ $f['row'] ?? '-' }}</td>
                                    <td><ul class="mb-0 ps-3">@foreach ($f['errors'] ?? [] as $err)<li>{{ $err }}</li>@endforeach</ul></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Success & summary for Card 2 --}}
    @if (session('success_imei'))
        <div class="alert alert-success">{{ session('success_imei') }}</div>
    @endif
    @php $summaryImei = session('import_summary_imei'); $failuresImei = session('import_failures_imei') ?? []; @endphp
    @if ($summaryImei)
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">{{ __('IMEI / Serial Import Summary') }}</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-4"><div class="border rounded p-2 text-center"><span class="text-muted small">{{ __('Processed') }}</span><div class="fw-bold">{{ $summaryImei['processed'] ?? 0 }}</div></div></div>
                    <div class="col-4"><div class="border rounded p-2 text-center"><span class="text-muted small">{{ __('Created') }}</span><div class="fw-bold text-success">{{ $summaryImei['created'] ?? 0 }}</div></div></div>
                    <div class="col-4"><div class="border rounded p-2 text-center"><span class="text-muted small">{{ __('Skipped') }}</span><div class="fw-bold text-warning">{{ $summaryImei['skipped'] ?? 0 }}</div></div></div>
                </div>
            </div>
        </div>
    @endif
    @if (count($failuresImei) > 0)
        <div class="card mb-4 border-danger">
            <div class="card-header bg-light"><h6 class="mb-0 text-danger">{{ __('IMEI / Serial Import Errors') }}</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>{{ __('Row') }}</th><th>{{ __('Error') }}</th></tr></thead>
                        <tbody>
                            @foreach ($failuresImei as $f)
                                <tr>
                                    <td>{{ $f['row'] ?? '-' }}</td>
                                    <td><ul class="mb-0 ps-3">@foreach ($f['errors'] ?? [] as $err)<li>{{ $err }}</li>@endforeach</ul></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Success & summary for Card 3 (Medicine) --}}
    @if (session('success_medicine'))
        <div class="alert alert-success">{{ session('success_medicine') }}</div>
    @endif
    @php $summaryMedicine = session('import_summary_medicine'); $failuresMedicine = session('import_failures_medicine') ?? []; @endphp
    @if ($summaryMedicine)
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">{{ __('Medicine Import Summary') }}</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-4"><div class="border rounded p-2 text-center"><span class="text-muted small">{{ __('Processed') }}</span><div class="fw-bold">{{ $summaryMedicine['processed'] ?? 0 }}</div></div></div>
                    <div class="col-4"><div class="border rounded p-2 text-center"><span class="text-muted small">{{ __('Created') }}</span><div class="fw-bold text-success">{{ $summaryMedicine['created'] ?? 0 }}</div></div></div>
                    <div class="col-4"><div class="border rounded p-2 text-center"><span class="text-muted small">{{ __('Skipped') }}</span><div class="fw-bold text-warning">{{ $summaryMedicine['skipped'] ?? 0 }}</div></div></div>
                </div>
            </div>
        </div>
    @endif
    @if (count($failuresMedicine) > 0)
        <div class="card mb-4 border-danger">
            <div class="card-header bg-light"><h6 class="mb-0 text-danger">{{ __('Medicine Import Errors') }}</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>{{ __('Row') }}</th><th>{{ __('Error') }}</th></tr></thead>
                        <tbody>
                            @foreach ($failuresMedicine as $f)
                                <tr>
                                    <td>{{ $f['row'] ?? '-' }}</td>
                                    <td><ul class="mb-0 ps-3">@foreach ($f['errors'] ?? [] as $err)<li>{{ $err }}</li>@endforeach</ul></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
