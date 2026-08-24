<?php

namespace App\Http\Controllers\Masters;

use App\Enums\EmploymentStatus;
use App\Http\Requests\Masters\EmployeeRequest;
use App\Models\BaseModel;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Support\DataTable\TableDefinition;
use App\Tables\EmployeeTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployeeController extends MasterController
{
    protected function definition(): TableDefinition
    {
        return new EmployeeTable;
    }

    protected function viewPath(): string
    {
        return 'masters.employees';
    }

    protected function modelClass(): string
    {
        return Employee::class;
    }

    protected function resourceLabel(): string
    {
        return '社員';
    }

    public function create(): View
    {
        return view($this->viewPath().'.form', $this->formData(new Employee));
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        // code は HasSequentialCode により EMP-0001 形式で自動採番される
        Employee::create($request->validated());

        return $this->redirectToIndex('社員を登録しました。');
    }

    public function edit(int $id): View
    {
        $employee = Employee::query()->findOrFail($id);

        return view($this->viewPath().'.form', $this->formData($employee));
    }

    public function update(EmployeeRequest $request, int $id): RedirectResponse
    {
        Employee::query()->findOrFail($id)->update($request->validated());

        return $this->redirectToIndex('社員を更新しました。');
    }

    /**
     * @return array<string, string|null>
     */
    protected function detailRows(BaseModel $record): array
    {
        /** @var Employee $record */
        return [
            '社員コード' => $record->code,
            '氏名' => $record->name,
            '部署' => $record->department?->name,
            '役職' => $record->position?->name,
            'メールアドレス' => $record->email,
            '在籍状態' => $record->employment_status->label(),
            'ログインユーザー' => $record->user?->name,
            '状態' => $record->activeLabel(),
            '登録日時' => $record->created_at?->format('Y/m/d H:i'),
            '最終更新' => $record->updated_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(BaseModel $employee): array
    {
        /** @var Employee $employee */
        return array_merge($this->sharedViewData(), [
            'employee' => $employee,
            'departmentOptions' => Department::query()->active()->orderBy('code')->pluck('name', 'id')->all(),
            'positionOptions' => Position::query()->active()->orderBy('code')->pluck('name', 'id')->all(),
            'statusOptions' => EmploymentStatus::options(),
            'userOptions' => User::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
