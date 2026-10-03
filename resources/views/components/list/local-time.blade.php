@props(['datetime', 'format' => 'datetime'])

@if ($datetime)
    @php
        $date = \Illuminate\Support\Carbon::parse($datetime);
        $fallback = $format === 'date'
            ? $date->format('Y-m-d')
            : $date->format('Y-m-d H:i');
    @endphp

    <time datetime="{{ $datetime }}" data-local-datetime="{{ $format }}" {{ $attributes }}>
        {{ $fallback }}
    </time>
@endif
