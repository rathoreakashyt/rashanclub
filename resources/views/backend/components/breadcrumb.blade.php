<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        @foreach($breadcrumbs as $breadcrumb)
            @if(!$loop->last)
                <li class="breadcrumb-item">
                    <a href="{{ $breadcrumb['link'] }}">{!! $breadcrumb['label'] !!}</a>
                </li>
            @else
                <li class="breadcrumb-item active" aria-current="page">
                    {!! $breadcrumb['label'] !!}
                </li>
            @endif
        @endforeach
    </ol>
</nav>
