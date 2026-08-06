@php
    $pwa = getPwaSettings();
    $themeColor = $pwa->theme_color ?? '#0f172a';
@endphp
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ $themeColor }}">
