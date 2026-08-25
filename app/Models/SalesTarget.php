<?php

namespace App\Models;

use App\Enums\TargetScope;
use App\Models\Concerns\HasSequentialCode;
use Database\Factories\SalesTargetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * 売上目標（予実管理の「予」）。
 *
 * 実績側（商談・明細）には手を入れず、独立して持つ。
 * 月次で持ち、四半期・年度はその合計として扱う。
 *
 * @property int $id
 * @property string $code
 * @property TargetScope $scope
 * @property int|null $target_id
 * @property int $year
 * @property int $month
 * @property int $amount
 */
class SalesTarget extends BaseModel
{
    /** @use HasFactory<SalesTargetFactory> */
    use HasFactory;

    use HasSequentialCode;

    protected $fillable = [
        'scope',
        'target_id',
        'year',
        'month',
        'amount',
        'is_active',
    ];

    public static function codePrefix(): string
    {
        return 'TGT';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => TargetScope::class,
            'target_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'amount' => 'integer',
        ];
    }

    /**
     * 年月で絞る。
     *
     * @param  Builder<SalesTarget>  $query
     * @return Builder<SalesTarget>
     */
    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->where('year', $year)->where('month', $month);
    }

    /**
     * 年度（4 月始まり）で絞る。
     *
     * @param  Builder<SalesTarget>  $query
     * @return Builder<SalesTarget>
     */
    public function scopeForFiscalYear(Builder $query, int $fiscalYear): Builder
    {
        $startMonth = (int) config('ui.fiscal_year_start_month', 4);

        return $query->where(function (Builder $sub) use ($fiscalYear, $startMonth): void {
            $sub->where(function (Builder $first) use ($fiscalYear, $startMonth): void {
                $first->where('year', $fiscalYear)->where('month', '>=', $startMonth);
            })->orWhere(function (Builder $second) use ($fiscalYear, $startMonth): void {
                $second->where('year', $fiscalYear + 1)->where('month', '<', $startMonth);
            });
        });
    }

    /**
     * 「2026年8月」の表示。
     */
    public function periodLabel(): string
    {
        return $this->year.'年'.$this->month.'月';
    }

    public function startOfMonth(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)?->startOfMonth() ?? Carbon::now()->startOfMonth();
    }

    /**
     * その年月が属する年度（4 月始まり）。
     */
    public function fiscalYear(): int
    {
        $startMonth = (int) config('ui.fiscal_year_start_month', 4);

        return $this->month >= $startMonth ? $this->year : $this->year - 1;
    }
}
