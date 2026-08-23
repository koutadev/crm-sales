<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TaxRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 税率マスタ。
 *
 * 税率が変わったときは既存レコードを書き換えず、適用開始日(effective_from)の
 * 新しいレコードを追加して世代管理する。
 * 確定済みの金額は商談明細側に税率(%)をコピー保持する設計のため、
 * ここに新しい世代を足しても過去データの金額は変わらない。
 *
 * @property int $id
 * @property string $name
 * @property int $rate_percent
 * @property Carbon $effective_from
 */
class TaxRate extends BaseModel
{
    /** @use HasFactory<TaxRateFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'rate_percent',
        'effective_from',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_percent' => 'integer',
            'effective_from' => 'date',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * 既定の標準税率。
     *
     * 名称が config('tax.default_rate_name') のレコードのうち、
     * 基準日時点で適用中(適用開始日 <= 基準日)の最新世代を返す。
     * 商品マスタで税率が選ばれなかったときのフォールバックに使う。
     */
    public static function standard(?CarbonInterface $asOf = null): ?self
    {
        return static::query()
            ->active()
            ->where('name', config('tax.default_rate_name'))
            ->where('effective_from', '<=', ($asOf ?? Carbon::now())->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * この税率を引き当てた商談明細(CRM)。
     *
     * @return HasMany<DealItem, $this>
     */
    public function dealItems(): HasMany
    {
        return $this->hasMany(DealItem::class);
    }

    /**
     * セレクトボックス用の選択肢([id => ラベル])。適用開始日の新しい順。
     *
     * @param  bool  $activeOnly  有効な税率だけに絞るか(登録フォーム用)
     * @return array<array-key, string>
     */
    public static function options(bool $activeOnly = false): array
    {
        return static::query()
            ->when($activeOnly, fn ($query) => $query->active())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (self $rate): array => [$rate->id => $rate->optionLabel()])
            ->all();
    }

    /**
     * 一覧・CSV 用の表示名(例: 標準 10%)。
     */
    public function label(): string
    {
        return $this->name.' '.$this->rate_percent.'%';
    }

    /**
     * 選択肢用の表示名。世代を見分けられるよう適用開始日を添える。
     *
     * 例: 標準 10%(2019/10/01〜)
     */
    public function optionLabel(): string
    {
        return $this->label().'('.$this->effective_from->format('Y/m/d').'〜)';
    }

    public function activityLogLabel(): ?string
    {
        return $this->optionLabel();
    }
}
