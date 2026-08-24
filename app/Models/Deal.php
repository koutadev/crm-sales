<?php

namespace App\Models;

use App\Enums\DealStatus;
use App\Models\Concerns\HasSequentialCode;
use App\Support\Code\CodeGenerator;
use App\Support\Crm\AmountSummary;
use App\Support\Crm\TaxCalculator;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 商談(案件)。
 *
 * コードは年度別に採番する(採番系列 deals:2026 → DEAL-2026-0001)。
 * amount_total は明細の税込金額の合算を非正規化して持つ(再計算は STEP 4 で実装)。
 *
 * @property int $id
 * @property string $code
 * @property int $partner_id
 * @property int|null $partner_contact_id
 * @property int $employee_id
 * @property string $title
 * @property DealStatus $status
 * @property int $probability
 * @property int $amount_total
 * @property Carbon $expected_close_date
 * @property Carbon|null $ordered_at
 * @property-read Partner|null $partner
 * @property-read PartnerContact|null $partnerContact
 * @property-read Employee|null $employee
 */
class Deal extends BaseModel
{
    /** @use HasFactory<DealFactory> */
    use HasFactory;

    use HasSequentialCode;

    /** 明細の一括入れ替え中など、金額の再計算を止めるか */
    protected static bool $amountRecalculationSuspended = false;

    protected $fillable = [
        'partner_id',
        'partner_contact_id',
        'employee_id',
        'title',
        'status',
        'probability',
        'amount_total',
        'expected_close_date',
        'ordered_at',
        'is_active',
    ];

    public static function codePrefix(): string
    {
        return 'DEAL';
    }

    /**
     * 採番系列のキー。年度ごとに連番を切り直す。
     */
    public static function codeSequenceKey(): string
    {
        return static::codeSequenceKeyFor(static::codeYear());
    }

    /**
     * 指定年の採番系列キー(例: deals:2026)。
     */
    public static function codeSequenceKeyFor(int $year): string
    {
        return 'deals:'.$year;
    }

    /**
     * 年度別のコードを採番する(例: DEAL-2026-0001)。
     */
    public static function generateCode(): string
    {
        $year = static::codeYear();

        return app(CodeGenerator::class)->next(
            key: static::codeSequenceKeyFor($year),
            prefix: static::codePrefix().'-'.$year,
            padding: static::$codePadding,
        );
    }

    /**
     * 採番に使う年。現状は暦年(登録日の年)。
     * 会計年度で切りたくなったときは、このメソッドだけを変更すればよい。
     */
    protected static function codeYear(): int
    {
        return (int) now()->format('Y');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DealStatus::class,
            'probability' => 'integer',
            'amount_total' => 'integer',
            'expected_close_date' => 'date',
            'ordered_at' => 'date',
        ];
    }

    /**
     * 顧客(会社)。
     *
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * 先方担当(任意)。
     *
     * @return BelongsTo<PartnerContact, $this>
     */
    public function partnerContact(): BelongsTo
    {
        return $this->belongsTo(PartnerContact::class);
    }

    /**
     * 自社の営業担当。
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * 商談明細。
     *
     * @return HasMany<DealItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DealItem::class);
    }

    /**
     * この商談に紐づく活動履歴。
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * 進行中(受注も失注もしていない)の商談。
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', DealStatus::openValues());
    }

    /**
     * 受注済みの商談(売上の集計対象)。
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWon(Builder $query): Builder
    {
        return $query->where('status', DealStatus::Won->value);
    }

    /**
     * 明細から金額を計算し直して保存する(税込が正・税率ごとに 1 回だけ切り捨て)。
     *
     * 明細の追加・更新・削除のたびに呼ばれる(DealItem のモデルイベント)。
     * 画面側の計算は表示補助で、保存される金額は必ずここを通る。
     */
    public function recalculateAmounts(): AmountSummary
    {
        $items = $this->items()->orderBy('id')->get()->values();

        $summary = TaxCalculator::summarize($items->map(static fn (DealItem $item): array => [
            'amount_incl_tax' => $item->unit_price * $item->quantity,
            'tax_rate_percent' => $item->tax_rate_percent,
        ])->all());

        // 明細ごとの内訳を保存する。再計算がループしないようイベントは起こさない
        foreach ($items as $position => $item) {
            $line = $summary->lineAmounts[$position];

            $item->forceFill([
                'amount_incl_tax' => $line->amountInclTax,
                'tax_amount' => $line->taxAmount,
                'amount_excl_tax' => $line->amountExclTax,
            ])->saveQuietly();
        }

        // 金額が変わったときだけ保存される(操作ログにも金額の変化として残る)
        $this->forceFill(['amount_total' => $summary->totalInclTax()])->save();

        return $summary;
    }

    /**
     * 保存済みの明細から金額サマリを組み立てる(保存はしない・表示用)。
     */
    public function amountSummary(): AmountSummary
    {
        return TaxCalculator::summarize($this->items->map(static fn (DealItem $item): array => [
            'amount_incl_tax' => $item->amount_incl_tax,
            'tax_rate_percent' => $item->tax_rate_percent,
        ])->all());
    }

    /**
     * 明細をまとめて入れ替えるあいだ、1 行ごとの再計算を止める。
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutAmountRecalculation(callable $callback): mixed
    {
        static::$amountRecalculationSuspended = true;

        try {
            return $callback();
        } finally {
            static::$amountRecalculationSuspended = false;
        }
    }

    public static function amountRecalculationSuspended(): bool
    {
        return static::$amountRecalculationSuspended;
    }

    public function activityLogLabel(): ?string
    {
        return $this->code.' '.$this->title;
    }
}
