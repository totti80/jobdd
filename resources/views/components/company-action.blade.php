@props(['href' => null])
@if($href)<a href="{{ $href }}" {{ $attributes->class(['inline-flex min-h-12 items-center justify-center rounded-lg border border-blue-700 bg-white px-5 py-3 font-semibold text-blue-800']) }}>{{ $slot }}</a>
@else<button {{ $attributes->class(['min-h-12 rounded-lg bg-blue-700 px-5 py-3 font-semibold text-white disabled:opacity-50']) }}>{{ $slot }}</button>@endif
