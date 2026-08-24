{{-- 概要タブ: 金額サマリ(税込) + 会社情報 --}}
<div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($kpis as $kpi)
            <x-dashboard.kpi-card :kpi="$kpi" />
        @endforeach
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">会社情報</h3>

        <dl class="mt-4 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
            @foreach ([
                '顧客コード' => $customer->code,
                '顧客名' => $customer->name,
                '取引先区分' => $customer->partner_type->label(),
                '法人 / 個人' => $customer->entity_type->label(),
                'メールアドレス' => $customer->email,
                '電話番号' => $customer->phone,
                '郵便番号' => $customer->postal_code,
                '住所' => $customer->address,
            ] as $label => $value)
                <div>
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $value ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>

        @can(\App\Enums\PermissionName::MasterManage->value)
            <div class="mt-6 border-t border-gray-100 pt-4 dark:border-gray-700">
                <a href="{{ route('masters.partners.edit', $customer->id) }}"
                   class="text-sm text-primary-text underline hover:text-primary-hover">
                    会社情報を編集する（取引先マスタ）
                </a>
            </div>
        @endcan
    </div>
</div>
