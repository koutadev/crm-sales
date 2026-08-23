<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 動作確認用のユーザーを作成する(本番環境では実行しないこと)。
 *
 * パスワードはいずれも "password"。
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleName::cases() as $roleName) {
            $email = $roleName->value.'@example.com';

            $user = User::firstWhere('email', $email);

            if ($user === null) {
                $user = new User;

                // email_verified_at は fillable ではないため forceFill で設定する
                $user->forceFill([
                    'name' => $roleName->label().'ユーザー',
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ])->save();
            }

            $user->syncRoles([$roleName->value]);
        }
    }
}
