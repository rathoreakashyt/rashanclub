@php
    $stepsList = [
        'welcome'     => ['route' => 'install.welcome',     'label' => '1. Welcome'],
        'environment' => ['route' => 'install.environment',  'label' => '2. Environment'],
        'purchase'    => ['route' => 'install.purchase',     'label' => '3. Verify Purchase'],
        'database'    => ['route' => 'install.database',    'label' => '4. Database'],
        'complete'    => ['route' => null,                  'label' => '5. Complete'],
    ];
    $stepKeys = array_keys($stepsList);
    $currentIndex = array_search($step ?? '', $stepKeys);
    if ($currentIndex === false) {
        $currentIndex = -1;
    }
@endphp
@foreach ($stepsList as $stepKey => $stepConfig)
    @php
        $index = array_search($stepKey, $stepKeys);
        $isCompleted = $index < $currentIndex;
        $isActive = $index === $currentIndex;
        $isUpcoming = $index > $currentIndex;
    @endphp
    @if ($isCompleted && $stepConfig['route'])
        <a href="{{ route($stepConfig['route']) }}" class="text-center px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-medium hover:bg-emerald-200">{{ $stepConfig['label'] }}</a>
    @elseif ($isCompleted && !$stepConfig['route'])
        <span class="text-center px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-medium">{{ $stepConfig['label'] }}</span>
    @elseif ($isActive)
        <span class="text-center px-3 py-1 rounded-full bg-indigo-600 text-white text-xs font-medium">{{ $stepConfig['label'] }}</span>
    @else
        <span class="text-center px-3 py-1 rounded-full bg-slate-200 text-slate-500 text-xs">{{ $stepConfig['label'] }}</span>
    @endif
    @if (!$loop->last)
        <span class="w-6 h-0.5 bg-slate-300"></span>
    @endif
@endforeach
