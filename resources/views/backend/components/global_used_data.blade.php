@php
    $language = app()->getLocale();
@endphp
<input type="hidden" id="base_url" value="{{ url('/') }}">
<input type="hidden" id="company_data" value="{{ json_encode(session('company', [])) }}">
<input type="hidden" id="language_name" value="{{ $language }}">

