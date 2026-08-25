<?php

namespace App\Http\Controllers\Crm;

use App\Enums\OrganizationType;
use App\Enums\TargetScope;
use App\Http\Controllers\Masters\MasterController;
use App\Http\Requests\Crm\SalesTargetRequest;
use App\Models\BaseModel;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\SalesTarget;
use App\Support\Crm\TargetLabels;
use App\Support\DataTable\Table;
use App\Support\DataTable\TableDefinition;
use App\Support\Ui\Toast;
use App\Support\Ui\Tone;
use App\Tables\SalesTargetTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * 売上目標マスタ。
 *
 * 実績（商談の受注金額）には手を入れず、目標だけをここで管理する。
 * 毎月ゼロから入力しなくて済むよう、前の期間からの複製を用意している。
 */
class SalesTargetController extends MasterController
{
    protected function definition(): TableDefinition
    {
        return new SalesTargetTable;
    }

    protected function viewPath(): string
    {
        return 'crm.sales-targets';
    }

    protected function modelClass(): string
    {
        return SalesTarget::class;
    }

    protected function resourceLabel(): string
    {
        return '売上目標';
    }

    public function index(Request $request): View
    {
        $view = parent::index($request);

        /** @var Table $table */
        $table = $view->getData()['table'];

        $now = Carbon::now();

        return $view->with([
            // 対象名(組織 / 社員)をまとめて解決する
            'labels' => TargetLabels::for($table->items()->all()),
            // 複製フォームの初期値: 先月 → 今月
            'copyFrom' => $now->copy()->subMonthNoOverflow(),
            'copyTo' => $now,
        ]);
    }

    public function create(): View
    {
        $now = Carbon::now();

        return view($this->viewPath().'.form', $this->formData(new SalesTarget([
            'scope' => TargetScope::Company,
            'year' => $now->year,
            'month' => $now->month,
        ])));
    }

    public function store(SalesTargetRequest $request): RedirectResponse
    {
        SalesTarget::create($this->attributes($request));

        return $this->redirectToIndex('売上目標を登録しました。');
    }

    public function edit(int $id): View
    {
        return view($this->viewPath().'.form', $this->formData(SalesTarget::query()->findOrFail($id)));
    }

    public function update(SalesTargetRequest $request, int $id): RedirectResponse
    {
        SalesTarget::query()->findOrFail($id)->update($this->attributes($request));

        return $this->redirectToIndex('売上目標を更新しました。');
    }

    /**
     * ある年月の目標を、別の年月へまとめて複製する。
     *
     * すでに同じ対象の目標がある年月には作らない（上書きしない）。
     */
    public function duplicate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'from_month' => ['required', 'integer', 'min:1', 'max:12'],
            'to_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'to_month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $sources = SalesTarget::query()
            ->forMonth((int) $validated['from_year'], (int) $validated['from_month'])
            ->get();

        if ($sources->isEmpty()) {
            return $this->backToIndex()
                ->with(Toast::SESSION_KEY, Toast::make(Tone::Warning, '複製もとの年月に目標がありません。'));
        }

        // すでにある対象は飛ばす(1 クエリで既存を引く)
        $existing = SalesTarget::query()
            ->forMonth((int) $validated['to_year'], (int) $validated['to_month'])
            ->get()
            ->map(static fn (SalesTarget $target): string => $target->scope->value.':'.($target->target_id ?? '-'))
            ->all();

        $created = 0;

        foreach ($sources as $source) {
            $key = $source->scope->value.':'.($source->target_id ?? '-');

            if (in_array($key, $existing, true)) {
                continue;
            }

            SalesTarget::create([
                'scope' => $source->scope,
                'target_id' => $source->target_id,
                'year' => (int) $validated['to_year'],
                'month' => (int) $validated['to_month'],
                'amount' => $source->amount,
                'is_active' => true,
            ]);

            $created++;
        }

        $skipped = $sources->count() - $created;

        return $this->backToIndex()->with(
            Toast::SESSION_KEY,
            Toast::success(sprintf(
                '%d年%d月の目標を %d件 複製しました。%s',
                (int) $validated['to_year'],
                (int) $validated['to_month'],
                $created,
                $skipped > 0 ? "（すでにある {$skipped}件 はそのままです）" : '',
            )),
        );
    }

    /**
     * @return array<string, string|null>
     */
    protected function detailRows(BaseModel $record): array
    {
        /** @var SalesTarget $record */
        return [
            '目標コード' => $record->code,
            '対象期間' => $record->periodLabel(),
            '粒度' => $record->scope->label(),
            '対象' => TargetLabels::for([$record])->of($record),
            '目標金額(税込)' => number_format($record->amount).' 円',
            '年度' => $record->fiscalYear().'年度',
            '状態' => $record->activeLabel(),
            '登録日時' => $record->created_at?->format('Y/m/d H:i'),
            '最終更新' => $record->updated_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(SalesTargetRequest $request): array
    {
        $validated = $request->validated();
        $scope = TargetScope::from((string) $validated['scope']);

        return [
            'scope' => $scope,
            'target_id' => $scope->needsTarget() ? ($validated['target_id'] ?? null) : null,
            'year' => (int) $validated['year'],
            'month' => (int) $validated['month'],
            'amount' => (int) $validated['amount'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(BaseModel $target): array
    {
        /** @var SalesTarget $target */
        $organizations = Organization::query()
            ->active()
            ->with('parent.parent:id,name')
            ->orderBy('code')
            ->get();

        return array_merge($this->sharedViewData(), [
            'target' => $target,
            'scopeOptions' => TargetScope::options(),
            // 粒度ごとの対象候補(画面側で切り替える)
            'targetOptions' => [
                TargetScope::Region->value => $this->optionsFor($organizations, OrganizationType::Region),
                TargetScope::Area->value => $this->optionsFor($organizations, OrganizationType::Area),
                TargetScope::Store->value => $this->optionsFor($organizations, OrganizationType::Store),
                TargetScope::Employee->value => Employee::query()->active()->orderBy('code')->pluck('name', 'id')->all(),
            ],
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    /**
     * @param  Collection<int, Organization>  $organizations
     * @return array<int, string>
     */
    private function optionsFor(Collection $organizations, OrganizationType $type): array
    {
        return $organizations
            ->filter(static fn (Organization $node): bool => $node->type === $type)
            ->mapWithKeys(static fn (Organization $node): array => [$node->id => $node->path()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function yearOptions(): array
    {
        $current = (int) Carbon::now()->year;
        $years = [];

        foreach (range($current - 2, $current + 2) as $year) {
            $years[$year] = $year.'年';
        }

        return $years;
    }

    private function backToIndex(): RedirectResponse
    {
        return redirect()->route($this->definition()->routeName().'.index');
    }
}
