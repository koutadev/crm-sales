@php
    use App\Enums\RoleName;

    /** @var \App\Models\User $user */
    $user = auth()->user();
@endphp

{{--
    ダッシュボードの「枠」。

    KPI カードとグラフの中身は DashboardController から配列で渡ってくるため、
    各業務システムではコントローラ側を差し替えるだけでこのレイアウトを再利用できる。
    権限を持たないユーザーには、そのブロックごと表示されない。
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ __('Dashboard') }}
            </h2>

            <p class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span>{{ $user->name }}</span>
                @foreach ($user->getRoleNames() as $roleName)
                    <span class="inline-flex items-center rounded-full bg-primary-soft px-2 py-0.5 text-xs font-medium text-primary-soft-fg">
                        {{ RoleName::tryFrom($roleName)?->label() ?? $roleName }}
                    </span>
                @endforeach
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-flash />

            @if ($kpis === [] && $charts === [] && $pipeline === [] && $recentActivities === null)
                <div class="rounded-lg border border-gray-200 bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    表示できる情報がありません。必要な権限が付与されているか管理者にご確認ください。
                </div>
            @endif

            {{-- KPI カード --}}
            @if ($kpis !== [])
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($kpis as $kpi)
                        <x-dashboard.kpi-card :kpi="$kpi" />
                    @endforeach
                </div>
            @endif

            {{-- グラフ --}}
            @if ($charts !== [])
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    @foreach ($charts as $chart)
                        <x-dashboard.chart-card :chart="$chart" />
                    @endforeach
                </div>
            @endif

            {{-- 組織別(地域 > エリア > 店舗 > 担当者)の売上 --}}
            @if ($organizationSales !== null)
                @include('crm.dashboard.organization-sales')
            @endif

            {{-- パイプライン(ステータス別の商談金額) --}}
            @if ($pipeline !== [])
                <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">パイプライン（ステータス別）</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">金額はすべて税込。加重見込み＝税込金額 × 確度</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">ステータス</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">件数</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">税込合計</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">加重見込み</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($pipeline as $row)
                                    <tr>
                                        <td class="whitespace-nowrap px-5 py-3">
                                            <a href="{{ route('deals.index', ['status' => $row->status->value]) }}"
                                               class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $row->status->badgeClass() }}">
                                                {{ $row->status->label() }}
                                            </a>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                            {{ number_format($row->dealCount) }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3 text-right font-medium tabular-nums">
                                            {{ number_format($row->totalInclTax) }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                            {{ number_format($row->weightedTotal) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- 最近の操作ログ --}}
            @if ($recentActivities !== null)
                <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">最近の操作</h3>
                        <a href="{{ route('activity-logs.index') }}"
                           class="text-xs font-medium text-primary-text hover:text-primary-hover">
                            すべて見る &rarr;
                        </a>
                    </div>

                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($recentActivities as $log)
                            <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 text-sm">
                                <span class="w-36 shrink-0 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ $log->created_at?->format('Y/m/d H:i') }}
                                </span>
                                <span class="font-medium text-gray-800 dark:text-gray-200">
                                    {{ $log->user?->name ?? 'システム' }}
                                </span>
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                    {{ $log->actionLabel() }}
                                </span>
                                <span class="text-gray-500 dark:text-gray-400">
                                    @if ($log->subject_type)
                                        {{ class_basename($log->subject_type) }}
                                        {{ $log->subject_label ?? '#'.$log->subject_id }}
                                    @endif
                                </span>
                            </li>
                        @empty
                            <li class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                操作ログはまだありません。
                            </li>
                        @endforelse
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
