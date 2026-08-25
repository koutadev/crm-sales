<?php

namespace App\Tables;

use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 社員マスタ一覧の定義。
 */
class EmployeeTable extends TableDefinition
{
    public function key(): string
    {
        return 'employees';
    }

    public function routeName(): string
    {
        return 'masters.employees';
    }

    /**
     * よく使う絞り込みを保存ビュー(マイビュー)として残せるようにする。
     */
    public function savedViews(): bool
    {
        return true;
    }

    public function query(): Builder
    {
        return Employee::query()->with(['department:id,name', 'position:id,name']);
    }

    public function columns(): array
    {
        return [
            new Column('code', '社員コード', sortable: true, wrap: false),
            new Column('name', '氏名', sortable: true),
            new Column('department_id', '部署'),
            new Column('position_id', '役職'),
            new Column('email', 'メール'),
            new Column('employment_status', '在籍状態', sortable: true, align: 'center'),
            new Column('is_active', '状態', sortable: true, align: 'center'),
            new Column('updated_at', '更新日時', sortable: true, wrap: false),
        ];
    }

    public function searchable(): array
    {
        return ['code', 'name', 'email'];
    }

    public function searchPlaceholder(): string
    {
        return '社員コード・氏名・メールで検索';
    }

    public function filters(): array
    {
        return [
            new Filter('department_id', '部署', $this->departmentOptions()),
            new Filter('position_id', '役職', $this->positionOptions()),
            new Filter('employment_status', '在籍状態', EmploymentStatus::options()),
            Filter::activeFlag(),
        ];
    }

    public function toCsvRow(Model $model): array
    {
        /** @var Employee $model */
        return [
            $model->code,
            $model->name,
            $model->department?->name,
            $model->position?->name,
            $model->email,
            $model->employment_status->label(),
            $model->activeLabel(),
            $model->updated_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * @return array<array-key, string>
     */
    private function departmentOptions(): array
    {
        return $this->cachedOptions(
            'departments',
            static fn (): array => Department::query()->orderBy('code')->pluck('name', 'id')->all(),
        );
    }

    /**
     * @return array<array-key, string>
     */
    private function positionOptions(): array
    {
        return $this->cachedOptions(
            'positions',
            static fn (): array => Position::query()->orderBy('code')->pluck('name', 'id')->all(),
        );
    }
}
