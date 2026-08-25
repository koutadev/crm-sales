@php
    /** @var \App\Support\Crm\OrganizationSales $organizationSales */
    /** @var \App\Support\Crm\SalesTargetLookup $targets */
    use App\Support\Ui\Achievement;

    $fiscal = Achievement::of($organizationSales->fiscalAmount, $targets->fiscalTotal());
@endphp

{{-- 予実(目標対実績)。目標は売上目標マスタ、実績は受注集計をそのまま使っている --}}
<x-card title="予実（目標と実績）"
        subtitle="実績は受注日ベースの税込金額です。目標は売上目標マスタの全社目標を使っています。">
    <x-slot name="actions">
        <a href="{{ route('masters.sales-targets.index') }}"
           class="text-xs text-primary-text underline hover:text-primary-hover">目標を編集</a>
    </x-slot>

    <div class="grid grid-cols-1 gap-8 sm:grid-cols-2">
        <x-gauge label="当月（{{ now()->format('Y年n月') }}）"
                 :actual="$organizationSales->monthAmount"
                 :target="$organizationSales->monthTarget"
                 unit="円"
                 size="lg" />

        <x-gauge label="{{ $fiscalLabel }}（累計）"
                 :actual="$organizationSales->fiscalAmount"
                 :target="$targets->fiscalTotal()"
                 unit="円"
                 size="lg"
                 note="目標が登録されている月ぶんの累計と比べています。" />
    </div>
</x-card>
