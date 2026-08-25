<?php

namespace App\Http\Controllers\Crm;

use App\Enums\ActivityType;
use App\Enums\DealStatus;
use App\Http\Controllers\Masters\MasterController;
use App\Http\Requests\Crm\DealRequest;
use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\PartnerContact;
use App\Models\Product;
use App\Models\TaxRate;
use App\Support\Crm\AmountSummary;
use App\Support\Crm\DealListSummary;
use App\Support\Crm\TaxCalculator;
use App\Support\DataTable\Table;
use App\Support\DataTable\TableBuilder;
use App\Support\DataTable\TableDefinition;
use App\Support\DataTable\TableState;
use App\Tables\DealTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 商談の一覧・詳細と、商談・明細の登録・編集。
 *
 * 一覧・CSV・論理削除・復元は共通のマスタ基盤(MasterController)をそのまま使う。
 * 金額は画面から受け取らず、明細の「税込単価 × 数量」からサーバ側で必ず計算する。
 * 明細は商品を選んだ時点の税込単価と税率(%)をコピーして保持し、
 * 以後に商品マスタ・税率マスタが変わっても確定済みの金額は動かない。
 */
class DealController extends MasterController
{
    protected function definition(): TableDefinition
    {
        return new DealTable;
    }

    protected function viewPath(): string
    {
        return 'crm.deals';
    }

    protected function modelClass(): string
    {
        return Deal::class;
    }

    protected function resourceLabel(): string
    {
        return '商談';
    }

    /**
     * 一覧。上部に「絞り込み結果に連動した金額サマリ」を出す。
     */
    public function index(Request $request): View
    {
        $definition = $this->definition();
        $canManageDeleted = $this->canManageDeleted($request);

        $state = TableState::resolve($request, $definition, $canManageDeleted);
        $builder = new TableBuilder($definition, $state);

        $table = new Table($definition, $state, $builder->paginate(), $canManageDeleted);

        // 期間フィルタの入力欄に戻す値(相対プリセットはここでも現在日から計算される)
        $range = DealTable::dateRangeFrom($state);
        $basis = DealTable::basisColumn($state->extra('period_basis'));

        return view($this->viewPath().'.index', array_merge($this->sharedViewData(), [
            'table' => $table,
            'summary' => DealListSummary::for($builder),
            'period' => [
                'basis' => $basis,
                'basisLabel' => DealTable::BASIS_COLUMNS[$basis],
                'basisOptions' => DealTable::BASIS_COLUMNS,
                'preset' => $range->preset->value,
                'from' => $range->from?->toDateString(),
                'to' => $range->to?->toDateString(),
            ],
        ]));
    }

    /**
     * 詳細(商談情報 + 明細 + 金額内訳 + 活動履歴)。
     */
    public function show(Request $request, int $id): View
    {
        $deal = Deal::query()
            ->when($this->canManageDeleted($request), fn ($query) => $query->withTrashed())
            ->with(['partner', 'partnerContact', 'employee', 'items.product', 'items.taxRate'])
            ->findOrFail($id);

        return view($this->viewPath().'.show', array_merge($this->sharedViewData(), [
            'deal' => $deal,
            'summary' => $deal->amountSummary(),
            'activities' => $deal->activities()
                ->with('employee:id,name')
                ->orderByDesc('activity_at')
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
            'employeeOptions' => Employee::query()->active()->orderBy('code')->pluck('name', 'id')->all(),
            'defaultEmployeeId' => Employee::query()->where('user_id', $request->user()?->id)->value('id'),
            'activityTypeOptions' => ActivityType::options(),
        ]));
    }

    public function create(Request $request): View
    {
        $deal = new Deal([
            'partner_id' => $request->integer('customer') ?: null,
            'status' => DealStatus::Prospect->value,
            'probability' => 10,
            'expected_close_date' => now()->addMonth()->toDateString(),
        ]);

        return view('crm.deals.form', $this->dealFormData($deal, []));
    }

    public function store(DealRequest $request): RedirectResponse
    {
        $deal = DB::transaction(function () use ($request): Deal {
            $lines = $this->resolveLines($request->input('items', []), null);

            $deal = Deal::create($this->dealAttributes($request) + [
                'amount_total' => $this->summarize($lines)->totalInclTax(),
            ]);

            $this->syncItems($deal, $lines);

            // 保存される金額は必ずここを通す(明細から計算し直す)
            $deal->recalculateAmounts();

            return $deal;
        });

        return $this->backToDeal($deal, '商談 '.$deal->code.' を登録しました。');
    }

    public function edit(int $id): View
    {
        $deal = Deal::query()->with('items')->findOrFail($id);

        return view('crm.deals.form', $this->dealFormData($deal, $this->itemRows($deal)));
    }

    public function update(DealRequest $request, int $id): RedirectResponse
    {
        $deal = Deal::query()->findOrFail($id);

        DB::transaction(function () use ($request, $deal): void {
            $lines = $this->resolveLines($request->input('items', []), $deal);

            $deal->update($this->dealAttributes($request) + [
                'amount_total' => $this->summarize($lines)->totalInclTax(),
            ]);

            $this->syncItems($deal, $lines);

            $deal->recalculateAmounts();
        });

        return $this->backToDeal($deal, '商談 '.$deal->code.' を更新しました。');
    }

    /**
     * 商談本体の属性。受注日はステータスと矛盾しないように整える。
     *
     * @return array<string, mixed>
     */
    private function dealAttributes(DealRequest $request): array
    {
        $validated = $request->validated();

        $status = DealStatus::from((string) $validated['status']);

        return [
            'partner_id' => $validated['partner_id'],
            'partner_contact_id' => $validated['partner_contact_id'] ?? null,
            'employee_id' => $validated['employee_id'],
            'title' => $validated['title'],
            'status' => $status,
            'probability' => $validated['probability'],
            'expected_close_date' => $validated['expected_close_date'],
            // 受注以外(失注を含む)は受注日を持たせない
            'ordered_at' => $status->isWon() ? ($validated['ordered_at'] ?? null) : null,
        ];
    }

    /**
     * 画面から来た明細を、保存できる形(スナップショット込み)に組み立てる。
     *
     * 既存明細で商品が変わっていなければ、税率は登録当時のものを引き継ぐ。
     * 新しい行・商品を変えた行は、その時点の商品マスタの税込単価と税率を写し取る。
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function resolveLines(array $rows, ?Deal $deal): array
    {
        $products = Product::query()
            ->whereIn('id', array_filter(array_map(static fn (array $row): mixed => $row['product_id'] ?? null, $rows)))
            ->get()
            ->keyBy('id');

        /** @var array<int, int> $taxRatePercents */
        $taxRatePercents = TaxRate::query()->pluck('rate_percent', 'id')->all();

        $existingItems = $deal?->items()->get()->keyBy('id');

        $lines = [];

        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            $product = $products->get($productId);

            if ($product === null) {
                continue;
            }

            $itemId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $existing = $itemId !== null ? $existingItems?->get($itemId) : null;

            if ($existing !== null && $existing->product_id === $productId) {
                // 確定済みのスナップショットは動かさない
                $taxRateId = $existing->tax_rate_id;
                $taxRatePercent = $existing->tax_rate_percent;
            } else {
                $taxRateId = $product->tax_rate_id;

                if ($taxRateId === null || ! array_key_exists($taxRateId, $taxRatePercents)) {
                    throw ValidationException::withMessages([
                        'items' => '商品「'.$product->name.'」に税率が設定されていません。商品マスタで税率を設定してください。',
                    ]);
                }

                $taxRatePercent = $taxRatePercents[$taxRateId];
            }

            $quantity = (int) $row['quantity'];
            $unitPrice = (int) $row['unit_price'];

            $lines[] = [
                'id' => $existing?->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate_id' => $taxRateId,
                'tax_rate_percent' => $taxRatePercent,
                'amount_incl_tax' => $unitPrice * $quantity,
            ];
        }

        return $lines;
    }

    /**
     * 明細を入れ替える。画面から消えた行は論理削除する。
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncItems(Deal $deal, array $lines): void
    {
        // 1 行ごとに商談の金額を計算し直さないよう、入れ替えのあいだは止めておく
        Deal::withoutAmountRecalculation(function () use ($deal, $lines): void {
            $keptIds = [];

            foreach ($lines as $line) {
                $item = $line['id'] !== null
                    ? $deal->items()->find($line['id'])
                    : null;

                $item ??= new DealItem(['deal_id' => $deal->id]);

                $item->fill([
                    'deal_id' => $deal->id,
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate_id' => $line['tax_rate_id'],
                    'tax_rate_percent' => $line['tax_rate_percent'],
                    'amount_incl_tax' => $line['amount_incl_tax'],
                ])->save();

                $keptIds[] = $item->id;
            }

            $deal->items()->whereNotIn('id', $keptIds)->get()->each->delete();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function summarize(array $lines): AmountSummary
    {
        return TaxCalculator::summarize(array_map(static fn (array $line): array => [
            'amount_incl_tax' => (int) $line['amount_incl_tax'],
            'tax_rate_percent' => (int) $line['tax_rate_percent'],
        ], $lines));
    }

    /**
     * 画面に渡す選択肢など。
     *
     * @param  list<array<string, mixed>>  $itemRows
     * @return array<string, mixed>
     */
    private function dealFormData(Deal $deal, array $itemRows): array
    {
        $products = Product::query()->active()->orderBy('code')->get();

        /** @var array<int, int> $taxRatePercents */
        $taxRatePercents = TaxRate::query()->pluck('rate_percent', 'id')->all();

        return [
            'deal' => $deal,
            'itemRows' => $itemRows,
            'customerOptions' => Partner::query()->customers()->active()->orderBy('code')
                ->pluck('name', 'id')->all(),
            'employeeOptions' => Employee::query()->active()->orderBy('code')
                ->pluck('name', 'id')->all(),
            'statusOptions' => DealStatus::options(),
            'productOptions' => $products->mapWithKeys(
                static fn (Product $product): array => [$product->id => $product->code.' '.$product->name]
            )->all(),
            // 商品を選んだときに単価と税率を引き当てるための一覧(画面表示用)
            'productData' => $products->mapWithKeys(static fn (Product $product): array => [
                $product->id => [
                    'unit_price' => $product->unit_price,
                    'tax_rate_percent' => $taxRatePercents[(int) $product->tax_rate_id] ?? 0,
                ],
            ])->all(),
            'contactsByCustomer' => PartnerContact::query()->active()->orderBy('name')->get()
                ->groupBy('partner_id')
                ->map(static fn ($contacts) => $contacts->map(static fn (PartnerContact $contact): array => [
                    'id' => $contact->id,
                    'name' => trim($contact->name.' '.($contact->department ?? '')),
                ])->values())
                ->all(),
        ];
    }

    /**
     * 編集画面に渡す明細行。
     *
     * @return list<array<string, mixed>>
     */
    private function itemRows(Deal $deal): array
    {
        return $deal->items->map(static fn (DealItem $item): array => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'tax_rate_percent' => $item->tax_rate_percent,
        ])->values()->all();
    }

    /**
     * 保存後は商談詳細へ。
     */
    private function backToDeal(Deal $deal, string $message): RedirectResponse
    {
        return redirect()
            ->route('deals.show', $deal->id)
            ->with('status', $message);
    }
}
