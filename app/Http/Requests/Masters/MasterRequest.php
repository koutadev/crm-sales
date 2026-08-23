<?php

namespace App\Http\Requests\Masters;

use Illuminate\Foundation\Http\FormRequest;

/**
 * マスタ登録 / 編集フォームの共通処理。
 *
 * 権限チェックはルート側(master.manage)で行うため、ここでは常に許可する。
 */
abstract class MasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 編集中のレコード ID(新規登録時は null)。
     */
    protected function recordId(): ?int
    {
        $id = $this->route('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * 入力値の正規化。
     *
     *   - 空文字はすべて null にする(未選択のセレクトが '' で送られてくるため)
     *   - チェックボックスは未チェックだと送信されないので明示的に false を入れる
     */
    protected function prepareForValidation(): void
    {
        $isActive = $this->boolean('is_active');

        $this->merge(
            collect($this->all())
                ->map(static fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : $value)
                ->all()
        );

        $this->merge(['is_active' => $isActive]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '名称',
            'is_active' => '状態',
        ];
    }
}
