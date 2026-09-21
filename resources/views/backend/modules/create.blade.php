@extends('backend.backend_layout')
@section('page-title', 'Install Module')
@push('page-css')
<style>
    .drop-zone {
        border: 2px dashed #ccc;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        cursor: pointer;
        transition: border-color 0.3s, background-color 0.3s;
    }
    .drop-zone:hover,
    .drop-zone.dragover {
        border-color: #696cff;
        background-color: #f0f0ff;
    }
    .drop-zone .drop-icon {
        font-size: 3rem;
        color: #696cff;
        opacity: 0.6;
    }
    .drop-zone.has-file {
        border-color: #28a745;
        background-color: #f0fff4;
    }
</style>
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ __('Install Module') }}</h4>
        <a href="{{ route('modules.index') }}" class="btn btn-outline-secondary">
            <i class="ti tabler-arrow-left me-1"></i> {{ __('Back to Modules') }}
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('modules.store') }}" method="POST" enctype="multipart/form-data" id="moduleForm">
                @csrf

                <div class="drop-zone" id="dropZone">
                    <div class="drop-icon mb-3">
                        <i class="ti tabler-cloud-upload"></i>
                    </div>
                    <h5>{{ __('Drag & Drop your module ZIP file here') }}</h5>
                    <p class="text-muted mb-2">{{ __('or click to browse') }}</p>
                    <p class="text-muted small">{{ __('Accepted: .zip files up to 50MB') }}</p>
                    <p class="file-name text-success fw-bold mt-2" id="fileName"></p>
                </div>

                <input type="file" name="module_zip" id="moduleZipInput" class="d-none" accept=".zip">

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                        <i class="ti tabler-upload me-1"></i> {{ __('Install Module') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <h6 class="card-title">{{ __('Module ZIP Structure') }}</h6>
            <p class="text-muted">{{ __('Your module ZIP file can optionally contain a') }} <code>module.json</code> {{ __('file at the root level with metadata:') }}</p>
            <pre class="bg-light p-3 rounded"><code>{
    "name": "My Module",
    "description": "A brief description of the module",
    "version": "1.0.0",
    "author": "Your Name"
}</code></pre>
        </div>
    </div>
</div>

@endsection

@push('page-js')
<script>
(function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('moduleZipInput');
    const fileName = document.getElementById('fileName');
    const submitBtn = document.getElementById('submitBtn');

    // Click to browse
    dropZone.addEventListener('click', () => fileInput.click());

    // Drag events
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0 && files[0].name.endsWith('.zip')) {
            fileInput.files = files;
            updateFileName(files[0].name);
        } else {
            alert('Please drop a valid .zip file.');
        }
    });

    // File input change
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            updateFileName(this.files[0].name);
        }
    });

    function updateFileName(name) {
        fileName.textContent = name;
        dropZone.classList.add('has-file');
        submitBtn.disabled = false;
    }
})();
</script>
@endpush
