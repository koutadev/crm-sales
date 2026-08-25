@php
    use App\Enums\DealStatus;

    /** @var \App\Models\Deal $deal */
    $editing = $deal->exists;
    $action = $editing ? route('deals.update', $deal->id) : route('deals.store');

    // 画面に出す明細行。入力エラーで戻ってきたときは送信内容を優先する
    $rows = collect(old('items', $itemRows))
        ->map(fn ($row) => [
            'id' => $row['id'] ?? null,
            'product_id' => (string) ($row['product_id'] ?? ''),
            'quantity' => (int) ($row['quantity'] ?? 1),
            'unit_price' => (int) ($row['unit_price'] ?? 0),
        ])
        ->values()
        ->all();

    // Alpine に渡す初期値(表示補助の計算に使う)
    $formConfig = [
        'rows' => $rows,
        'products' => $productData,
        'contacts' => $contactsByCustomer,
        'partnerId' => (string) old('partner_id', $deal->partner_id),
        'contactId' => (string) old('partner_contact_id', $deal->partner_contact_id),
        'status' => old('status', $deal->status?->value ?? DealStatus::Prospect->value),
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                商談 &mdash; {{ $editing ? '編集' : '新規登録' }}
                @if ($editing)
                    <span class="ms-2 font-mono text-sm text-gray-500 dark:text-gray-400">{{ $deal->code }}</span>
                @endif
            </h2>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                金額はすべて税込。消費税は税率ごとの合計から逆算します（保存時にサーバ側で再計算）。
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <x-flash />

            <form method="POST" action="{{ $action }}"
                  x-data='dealForm(@json($formConfig))'>
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="space-y-6">
                    {{-- 商談情報 --}}
                    <div class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">商談情報</h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- 顧客・先方担当は入力で候補を絞れる(先方担当は顧客に連動) --}}
                            <x-form.combobox name="partner_id" label="顧客" :required="true"
                                             :options="$customerOptions"
                                             :selected="old('partner_id', $deal->partner_id)"
                                             model-expression="partnerId"
                                             on-select="contactId = ''"
                                             placeholder="顧客名・コードで検索" />

                            <x-form.combobox name="partner_contact_id" label="先方担当"
                                             help="選んだ顧客に登録されている担当者から選べます。"
                                             model-expression="contactId"
                                             options-expression="contactsForPartner()"
                                             placeholder="担当者名で検索"
                                             empty="この顧客に担当者が登録されていません" />

                            <x-form-field name="title" label="件名" :required="true">
                                <x-text-input id="title" name="title" type="text" class="mt-1 block w-full"
                                              :value="old('title', $deal->title)" required />
                            </x-form-field>

                            <x-form.combobox name="employee_id" label="営業担当" :required="true"
                                             :options="$employeeOptions"
                                             :selected="old('employee_id', $deal->employee_id)"
                                             placeholder="担当者名・コードで検索" />

                            <x-form-field name="status" label="ステータス" :required="true">
                                <select id="status" name="status" required x-model="status"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-form-field>

                            <x-form-field name="probability" label="確度(%)" :required="true" help="0〜100 の整数。">
                                <x-text-input id="probability" name="probability" type="number" step="1" min="0" max="100"
                                              class="mt-1 block w-full text-right"
                                              :value="old('probability', $deal->exists ? $deal->probability : 10)" required />
                            </x-form-field>

                            <x-form-field name="expected_close_date" label="予定クローズ日" :required="true">
                                <x-text-input id="expected_close_date" name="expected_close_date" type="date"
                                              class="mt-1 block w-full"
                                              :value="old('expected_close_date', $deal->expected_close_date?->format('Y-m-d'))" required />
                            </x-form-field>

                            <div x-show="status === '{{ DealStatus::Won->value }}'" x-cloak>
                                <x-form-field name="ordered_at" label="受注日" :required="true"
                                              help="ステータスが「受注」のときは必須です。受注以外に戻すと消えます。">
                                    <x-text-input id="ordered_at" name="ordered_at" type="date" class="mt-1 block w-full"
                                                  :value="old('ordered_at', $deal->ordered_at?->format('Y-m-d'))" />
                                </x-form-field>
                            </div>
                        </div>
                    </div>

                    {{-- 明細 --}}
                    <div class="space-y-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">明細</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                商品を選ぶと税込単価と税率が入ります。単価は案件ごとに変更できます。
                            </p>
                        </div>

                        <x-input-error :messages="$errors->get('items')" />

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">商品</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">数量</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">税込単価</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400">税率</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">税込金額</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <template x-for="(row, index) in rows" :key="index">
                                        <tr>
                                            <td class="px-3 py-2">
                                                <input type="hidden" :name="`items[${index}][id]`" :value="row.id ?? ''">

                                                {{-- 明細行の商品も入力で絞り込める(行ごとに独立した部品として動く) --}}
                                                <x-form.combobox name="product" size="sm" :required="true" unique-id
                                                                 :options="$productOptions"
                                                                 name-expression="`items[${index}][product_id]`"
                                                                 model-expression="row.product_id"
                                                                 on-select="applyProduct(index, $event.detail.value)"
                                                                 placeholder="商品名・コードで検索"
                                                                 class="min-w-56" />
                                            </td>

                                            <td class="px-3 py-2">
                                                <input type="number" min="1" step="1" required
                                                       :name="`items[${index}][quantity]`" x-model.number="row.quantity"
                                                       class="block w-24 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                            </td>

                                            <td class="px-3 py-2">
                                                <input type="number" min="0" step="1" required
                                                       :name="`items[${index}][unit_price]`" x-model.number="row.unit_price"
                                                       class="block w-32 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                            </td>

                                            <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">
                                                <span x-text="ratePercent(row) + '%'"></span>
                                            </td>

                                            <td class="px-3 py-2 text-right tabular-nums" x-text="format(lineTotal(row))"></td>

                                            <td class="px-3 py-2 text-right">
                                                <button type="button" x-on:click="removeRow(index)"
                                                        class="text-xs font-medium text-rose-600 hover:text-rose-500 dark:text-rose-400">
                                                    削除
                                                </button>
                                            </td>
                                        </tr>
                                    </template>

                                    <tr x-show="rows.length === 0">
                                        <td colspan="6" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">
                                            明細がありません。「明細を追加」から登録してください。
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <button type="button" x-on:click="addRow()"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            明細を追加
                        </button>

                        {{-- 金額(表示補助。保存される金額はサーバ側で計算し直す) --}}
                        <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                            <div class="ms-auto w-full max-w-sm space-y-1 text-sm">
                                <template x-for="group in rateGroups()" :key="group.rate">
                                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span x-text="`${group.rate}% 対象（税込）`"></span>
                                        <span class="tabular-nums" x-text="`${format(group.incl)}（うち消費税 ${format(group.tax)}）`"></span>
                                    </div>
                                </template>

                                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                    <span>税抜</span>
                                    <span class="tabular-nums" x-text="format(totalExcl())"></span>
                                </div>
                                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                    <span>消費税</span>
                                    <span class="tabular-nums" x-text="format(totalTax())"></span>
                                </div>
                                <div class="flex justify-between border-t border-gray-200 pt-1 text-base font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">
                                    <span>合計（税込）</span>
                                    <span class="tabular-nums" x-text="format(totalIncl())"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button type="submit">保存</x-primary-button>

                        <a href="{{ $deal->partner_id ? route('customers.show', ['id' => $deal->partner_id, 'tab' => 'deals']) : route('customers.index') }}"
                           class="text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                            キャンセル
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('head')
        <script>
            // 明細エディタ（表示補助）。保存される金額はサーバ側で計算し直される。
            function dealForm(config) {
                return {
                    rows: config.rows,
                    products: config.products,
                    contacts: config.contacts,
                    partnerId: config.partnerId,
                    contactId: config.contactId,
                    status: config.status,

                    contactsForPartner() {
                        return this.contacts[this.partnerId] ?? [];
                    },

                    addRow() {
                        this.rows.push({ id: null, product_id: '', quantity: 1, unit_price: 0 });
                    },

                    removeRow(index) {
                        this.rows.splice(index, 1);
                    },

                    applyProduct(index, productId = null) {
                        const row = this.rows[index];

                        if (productId !== null) {
                            row.product_id = productId;
                        }

                        const product = this.products[row.product_id];

                        if (product) {
                            row.unit_price = product.unit_price;
                        }
                    },

                    ratePercent(row) {
                        return this.products[row.product_id]?.tax_rate_percent ?? 0;
                    },

                    lineTotal(row) {
                        return (Number(row.unit_price) || 0) * (Number(row.quantity) || 0);
                    },

                    // 税率ごとにまとめ、合計に対して 1 回だけ消費税を逆算する（サーバ側と同じ規則）
                    rateGroups() {
                        const groups = {};

                        this.rows.forEach((row) => {
                            const rate = this.ratePercent(row);
                            groups[rate] = (groups[rate] ?? 0) + this.lineTotal(row);
                        });

                        return Object.keys(groups)
                            .map(Number)
                            .sort((a, b) => b - a)
                            .map((rate) => ({
                                rate,
                                incl: groups[rate],
                                tax: this.taxOf(groups[rate], rate),
                            }));
                    },

                    taxOf(amountInclTax, ratePercent) {
                        if (amountInclTax <= 0 || ratePercent <= 0) {
                            return 0;
                        }

                        const divisor = 100 + ratePercent;

                        return amountInclTax - Math.ceil((amountInclTax * 100) / divisor);
                    },

                    totalIncl() {
                        return this.rateGroups().reduce((total, group) => total + group.incl, 0);
                    },

                    totalTax() {
                        return this.rateGroups().reduce((total, group) => total + group.tax, 0);
                    },

                    totalExcl() {
                        return this.totalIncl() - this.totalTax();
                    },

                    format(value) {
                        return new Intl.NumberFormat('ja-JP').format(value ?? 0);
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
