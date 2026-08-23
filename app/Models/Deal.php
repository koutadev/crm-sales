<?php

namespace App\Models;

use App\Enums\DealStatus;
use App\Models\Concerns\HasSequentialCode;
use App\Support\Code\CodeGenerator;
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

    public function activityLogLabel(): ?string
    {
        return $this->code.' '.$this->title;
    }
}
