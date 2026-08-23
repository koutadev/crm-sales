<?php

namespace App\Http\Controllers\Masters;

use App\Enums\EmploymentStatus;
use App\Http\Requests\Masters\EmployeeRequest;
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
     * @return array<string, mixed>
     */
    private function formData(Employee $employee): array
    {
        return array_merge($this->sharedViewData(), [
            'employee' => $employee,
            'departmentOptions' => Department::query()->active()->orderBy('code')->pluck('name', 'id')->all(),
            'positionOptions' => Position::query()->active()->orderBy('code')->pluck('name', 'id')->all(),
            'statusOptions' => EmploymentStatus::options(),
            'userOptions' => User::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
