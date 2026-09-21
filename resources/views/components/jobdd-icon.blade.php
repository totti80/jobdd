@props(['name'])
<svg {{ $attributes->class(['jobdd-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('match')
            <circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" />
            @break
        @case('mismatch')
            <path d="M8 3v18M16 3v18M5 7h6M13 17h6" /><circle cx="8" cy="7" r="2" fill="currentColor" /><circle cx="16" cy="17" r="2" fill="currentColor" />
            @break
        @case('unknown')
            <circle cx="12" cy="12" r="9" /><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 16h.01" />
            @break
        @case('evidence')
            <path d="M14 3H5v18h14V8zM14 3v5h5M8 12h8M8 16h5" />
            @break
        @case('compare')
            <rect x="3" y="4" width="7" height="16" rx="2" /><rect x="14" y="4" width="7" height="16" rx="2" /><path d="M6 9h1M6 13h1M17 9h1M17 13h1" />
            @break
        @case('map')
            <path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3zM9 3v15M15 6v15" />
            @break
        @case('map-pin')
            <path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 0 1 14 0Z" /><circle cx="12" cy="10" r="2.5" />
            @break
        @case('difference')
            <path d="M4 8h15m-4-4 4 4-4 4M20 16H5m4-4-4 4 4 4" />
            @break
        @case('tools')
            <path d="m4 20 7-7M14 4a6 6 0 0 0-7 7l6 6a6 6 0 0 0 7-7l-4 4-6-6z" />
            @break
        @case('agent')
            <path d="M4 4h16v12H9l-5 4zM8 8h8M8 12h5" />
            @break
        @case('arrow')
            <path d="M4 12h16m-6-6 6 6-6 6" />
            @break
    @endswitch
</svg>
