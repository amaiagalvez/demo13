@props(['datetime', 'format' => 'datetime'])

@if ($datetime)
    @php
        $date = \Illuminate\Support\Carbon::parse($datetime);
        $locale = app()->getLocale();
        $fallback = $format === 'date'
            ? match ($locale) {
                'eu' => $date->format('Y-m-d'),
                'es' => $date->format('d-m-Y'),
                'fr' => $date->format('d/m/Y'),
                default => $date->format('M j, Y'),
            }
            : match ($locale) {
                'eu' => $date->format('Y-m-d H:i'),
                'es' => $date->format('d-m-Y H:i'),
                'fr' => $date->format('d/m/Y H:i'),
                default => $date->format('M j, Y H:i'),
            };
    @endphp

    <time datetime="{{ $datetime }}" data-local-datetime="{{ $format }}" {{ $attributes }}>
        {{ $fallback }}
    </time>
@endif
