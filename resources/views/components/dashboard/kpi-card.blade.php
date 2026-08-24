@props(['kpi'])

{{-- 旧 API。App\Support\Dashboard\Kpi をそのまま渡せる薄いラッパ。 --}}
<x-kpi-card :kpi="$kpi" {{ $attributes }} />
