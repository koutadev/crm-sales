<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\DataTable\Table;
use App\Tables\UserTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ユーザー管理(最小)。
 *
 * user.manage 権限を持つ管理者が、ユーザーのロールを付け替えるためだけの画面。
 * ユーザーの新規作成・削除は行わない(新規登録は本人が /register から行う)。
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        // 一覧基盤をそのまま再利用する(CSV と削除済み表示は UserTable 側で無効)
        $table = Table::make(new UserTable, $request);

        return view('users.index', ['table' => $table]);
    }

    public function edit(int $id): View
    {
        $user = User::query()->findOrFail($id);

        return view('users.edit', [
            'user' => $user,
            'roleOptions' => collect(RoleName::cases())
                ->mapWithKeys(static fn (RoleName $role): array => [$role->value => $role->label()])
                ->all(),
            'currentRoles' => $user->getRoleNames()->all(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        $validated = $request->validate([
            'roles' => ['array'],
            'roles.*' => [Rule::in(RoleName::values())],
        ], [], ['roles' => 'ロール']);

        $roles = $validated['roles'] ?? [];

        // 自分自身から管理者ロールを外すと、誰も管理できなくなる恐れがあるため防ぐ
        $removingOwnAdminRole = $user->is($request->user())
            && $user->hasRole(RoleName::Admin->value)
            && ! in_array(RoleName::Admin->value, $roles, true);

        if ($removingOwnAdminRole) {
            return back()->withErrors([
                'roles' => '自分自身から管理者ロールを外すことはできません。',
            ]);
        }

        $user->syncRoles($roles);

        return redirect()
            ->route('users.index')
            ->with('status', $user->name.' のロールを更新しました。');
    }
}
