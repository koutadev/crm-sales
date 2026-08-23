<x-master-form :record="$employee" :resource-label="$resourceLabel" :route-name="$routeName">
    <x-form-field name="name" label="氏名" :required="true">
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      :value="old('name', $employee->name)" required autofocus />
    </x-form-field>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <x-form-field name="department_id" label="部署">
            <x-select-input id="department_id" name="department_id" class="mt-1 block w-full"
                            :options="$departmentOptions"
                            :selected="old('department_id', $employee->department_id)"
                            placeholder="未設定" />
        </x-form-field>

        <x-form-field name="position_id" label="役職">
            <x-select-input id="position_id" name="position_id" class="mt-1 block w-full"
                            :options="$positionOptions"
                            :selected="old('position_id', $employee->position_id)"
                            placeholder="未設定" />
        </x-form-field>
    </div>

    <x-form-field name="email" label="メールアドレス">
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                      :value="old('email', $employee->email)" />
    </x-form-field>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <x-form-field name="employment_status" label="在籍状態" :required="true">
            <x-select-input id="employment_status" name="employment_status" class="mt-1 block w-full"
                            :options="$statusOptions"
                            :selected="old('employment_status', $employee->employment_status?->value ?? \App\Enums\EmploymentStatus::Active->value)" />
        </x-form-field>

        <x-form-field name="user_id" label="ログインユーザー"
                      help="この社員がシステムにログインする場合に紐付けます。">
            <x-select-input id="user_id" name="user_id" class="mt-1 block w-full"
                            :options="$userOptions"
                            :selected="old('user_id', $employee->user_id)"
                            placeholder="紐付けない" />
        </x-form-field>
    </div>

    <div>
        <x-active-checkbox :record="$employee" />
    </div>
</x-master-form>
