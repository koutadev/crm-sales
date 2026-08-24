<?php

namespace App\Support\Crm;

/**
 * 内税(税込)の金額計算。
 *
 * 設計(docs/basic-design.md 6章):
 *   - 単価・金額とも税込を正として保持する
 *   - 消費税額 = 税込金額 - 税込金額 ÷ (1 + 税率)   … 切り捨て
 *   - 税抜金額 = 税込金額 - 消費税額
 *   - 端数処理は「同一税率の明細をまとめた税込合計に対して 1 回だけ」行う
 *     (1 明細ずつ切り捨てると、税率ごとの合計と一致しなくなるため)
 *
 * 明細ごとの消費税額・税抜金額は、税率グループの消費税額を税込金額で按分した
 * 内訳として保持する(合計は必ずグループの値と一致する)。
 *
 * 金額はすべて整数(最小通貨単位)。浮動小数を使わず整数演算だけで切り捨てるため、
 * 桁が大きくなっても誤差が出ない。
 */
class TaxCalculator
{
    /**
     * 税込金額から消費税額を求める(切り捨て)。
     *
     * floor(incl - incl * 100 / (100 + rate)) は
     * incl - ceil(incl * 100 / (100 + rate)) と等しいので、整数演算だけで計算できる。
     */
    public static function taxFromInclusive(int $amountInclTax, int $ratePercent): int
    {
        if ($amountInclTax <= 0 || $ratePercent <= 0) {
            return 0;
        }

        $divisor = 100 + $ratePercent;

        // 税抜金額(切り上げ) = ceil(incl * 100 / (100 + rate))
        $amountExclTax = intdiv($amountInclTax * 100 + $divisor - 1, $divisor);

        return $amountInclTax - $amountExclTax;
    }

    /**
     * 明細の一覧から、税率ごと・全体の金額内訳を求める。
     *
     * @param  list<array{amount_incl_tax: int, tax_rate_percent: int}>  $lines
     */
    public static function summarize(array $lines): AmountSummary
    {
        /** @var array<int, list<int>> $indexesByRate 税率% => 明細の添字 */
        $indexesByRate = [];

        foreach ($lines as $index => $line) {
            $indexesByRate[$line['tax_rate_percent']][] = $index;
        }

        // 税率の高い順に並べる(表示のときに標準税率が先に来るように)
        krsort($indexesByRate);

        $groups = [];
        $lineTaxAmounts = [];

        foreach ($indexesByRate as $ratePercent => $indexes) {
            $groupInclTax = 0;

            foreach ($indexes as $index) {
                $groupInclTax += $lines[$index]['amount_incl_tax'];
            }

            $groupTax = self::taxFromInclusive($groupInclTax, $ratePercent);

            foreach (self::allocate($groupTax, array_map(
                static fn (int $index): int => $lines[$index]['amount_incl_tax'],
                $indexes,
            )) as $position => $tax) {
                $lineTaxAmounts[$indexes[$position]] = $tax;
            }

            $groups[] = new TaxRateAmount(
                ratePercent: $ratePercent,
                amountInclTax: $groupInclTax,
                taxAmount: $groupTax,
            );
        }

        ksort($lineTaxAmounts);

        $lineAmounts = [];

        foreach ($lineTaxAmounts as $index => $taxAmount) {
            $lineAmounts[] = new LineAmount(
                amountInclTax: $lines[$index]['amount_incl_tax'],
                taxAmount: $taxAmount,
                amountExclTax: $lines[$index]['amount_incl_tax'] - $taxAmount,
            );
        }

        return new AmountSummary($groups, $lineAmounts);
    }

    /**
     * 税率グループの消費税額を、明細の税込金額で按分する。
     *
     * 按分は切り捨てで行い、余りは端数(按分しきれなかった分)が大きい明細から
     * 1 円ずつ配る。これにより「按分後の合計 = グループの消費税額」が常に成り立つ。
     *
     * @param  list<int>  $amounts  明細の税込金額
     * @return list<int> 明細ごとの消費税額($amounts と同じ並び)
     */
    private static function allocate(int $groupTax, array $amounts): array
    {
        $total = array_sum($amounts);

        if ($total <= 0 || $groupTax <= 0) {
            return array_map(static fn (): int => 0, $amounts);
        }

        $allocated = [];
        $remainders = [];

        foreach ($amounts as $position => $amount) {
            $allocated[$position] = intdiv($amount * $groupTax, $total);
            $remainders[$position] = ($amount * $groupTax) % $total;
        }

        $rest = $groupTax - array_sum($allocated);

        // 端数の大きい明細から 1 円ずつ。同じ端数なら先に出てくる明細を優先する
        arsort($remainders);

        foreach (array_keys($remainders) as $position) {
            if ($rest <= 0) {
                break;
            }

            $allocated[$position]++;
            $rest--;
        }

        ksort($allocated);

        return array_values($allocated);
    }
}
