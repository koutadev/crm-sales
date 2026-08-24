@php
    /** @var \App\Models\PartnerContact|null $contact */
    $editing = $contact !== null;
    $formKey = $contact?->id ?? 0;

    // 直前の送信がこのフォームだった場合だけ old() とエラーを復元する
    // (同じ画面に複数のフォームが並ぶため)
    $isSubmitted = (int) old('contact_id', -1) === $formKey;

    $fields = [
        ['name', '氏名', 'text', true],
        ['department', '部署', 'text', false],
        ['position', '役職', 'text', false],
        ['email', 'メールアドレス', 'email', false],
        ['phone', '電話番号', 'text', false],
    ];

    $action = $editing
        ? route('customers.contacts.update', ['id' => $customer->id, 'contact' => $contact->id])
        : route('customers.contacts.store', ['id' => $customer->id]);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <input type="hidden" name="contact_id" value="{{ $formKey }}">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($fields as [$field, $label, $type, $required])
            @php $inputId = 'contact-'.$formKey.'-'.$field; @endphp

            <div class="space-y-1">
                <label for="{{ $inputId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $label }}
                    @if ($required)
                        <span class="ms-1 text-xs font-normal text-rose-600 dark:text-rose-400">必須</span>
                    @endif
                </label>

                <x-text-input :id="$inputId" :name="$field" :type="$type" class="mt-1 block w-full"
                              :value="$isSubmitted ? old($field, $contact?->{$field}) : $contact?->{$field}"
                              :required="$required" />

                @if ($isSubmitted)
                    <x-input-error :messages="$errors->get($field)" class="mt-1" />
                @endif
            </div>
        @endforeach
    </div>

    <div>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1"
                   @checked($isSubmitted ? old('is_active') : ($contact?->is_active ?? true))
                   class="rounded border-gray-300 text-primary-text shadow-sm focus:ring-primary dark:border-gray-700 dark:bg-gray-900">
            <span class="text-sm text-gray-700 dark:text-gray-300">有効</span>
        </label>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            無効にすると、今後の選択肢には出なくなりますが、過去データからは参照できます。
        </p>
    </div>

    <div class="flex items-center gap-3">
        <x-primary-button type="submit">{{ $editing ? '更新' : '追加' }}</x-primary-button>

        @if ($editing)
            <button type="button" x-on:click="editing = null"
                    class="text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                キャンセル
            </button>
        @endif
    </div>
</form>
