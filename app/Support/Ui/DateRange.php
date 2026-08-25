<?php

namespace App\Support\Ui;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 絞り込みに使う期間。
 *
 * 画面からは「プリセットのキー」または「開始日・終了日」が送られてくる。
 * プリセットの場合は**受け取った側で毎回計算する**ので、月が替わっても
 * 「今月」は常にその時点の今月になる。
 *
 *   $range = DateRange::fromRequest($request, 'closed');
 *   $range->apply($query, 'expected_close_date');
 */
class DateRange
{
    public function __construct(
        public readonly DateRangePreset $preset,
        public readonly ?CarbonImmutable $from = null,
        public readonly ?CarbonImmutable $to = null,
    ) {}

    /**
     * 画面から送られた値(<x-date-range name="closed" /> の 3 つの hidden)を期間に解決する。
     */
    public static function fromRequest(Request $request, string $name, ?CarbonInterface $asOf = null): self
    {
        return self::fromValues(
            $request->input($name.'_preset'),
            $request->input($name.'_from'),
            $request->input($name.'_to'),
            $asOf,
        );
    }

    /**
     * 値から期間を組み立てる。
     *
     * 一覧の状態保持(セッション)から復元する場合など、リクエスト以外の経路で使う。
     */
    public static function fromValues(mixed $preset, mixed $from, mixed $to, ?CarbonInterface $asOf = null): self
    {
        $preset = DateRangePreset::resolve(is_string($preset) ? $preset : null);

        if ($preset->isRelative()) {
            [$rangeFrom, $rangeTo] = $preset->range($asOf);

            return new self($preset, $rangeFrom, $rangeTo);
        }

        $from = self::parse($from);
        $to = self::parse($to);

        if ($from === null && $to === null) {
            return new self(DateRangePreset::None);
        }

        // 開始と終了が逆に入力されていたら入れ替える
        if ($from !== null && $to !== null && $from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return new self(DateRangePreset::Custom, $from, $to);
    }

    /**
     * 期間の指定が無い(全期間)か。
     */
    public function isEmpty(): bool
    {
        return $this->from === null && $this->to === null;
    }

    /**
     * クエリに期間の条件を足す。時刻は見ない(日付だけで比較する)。
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<covariant TModel>  $query
     * @return Builder<covariant TModel>
     */
    public function apply(Builder $query, string $column): Builder
    {
        if ($this->from !== null) {
            $query->whereDate($column, '>=', $this->from->toDateString());
        }

        if ($this->to !== null) {
            $query->whereDate($column, '<=', $this->to->toDateString());
        }

        return $query;
    }

    /**
     * 画面に出す期間の表示(例: 2026/08/01 〜 2026/08/31)。
     */
    public function label(): string
    {
        if ($this->isEmpty()) {
            return DateRangePreset::None->label();
        }

        $from = $this->from?->format('Y/m/d') ?? '';
        $to = $this->to?->format('Y/m/d') ?? '';

        return match (true) {
            $from !== '' && $to !== '' => $from.' 〜 '.$to,
            $from !== '' => $from.' 以降',
            default => $to.' まで',
        };
    }

    /**
     * 一覧の URL に引き継ぐためのクエリパラメータ。
     *
     * @return array<string, string>
     */
    public function toQuery(string $name): array
    {
        if ($this->preset->isRelative()) {
            return [$name.'_preset' => $this->preset->value];
        }

        if ($this->isEmpty()) {
            return [];
        }

        return array_filter([
            $name.'_preset' => DateRangePreset::Custom->value,
            $name.'_from' => $this->from?->toDateString() ?? '',
            $name.'_to' => $this->to?->toDateString() ?? '',
        ], static fn (string $value): bool => $value !== '');
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
