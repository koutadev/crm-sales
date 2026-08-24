<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\PartnerContactRequest;
use App\Models\Partner;
use App\Models\PartnerContact;
use Illuminate\Http\RedirectResponse;

/**
 * 顧客詳細「担当者」タブのインライン CRUD。
 *
 * 担当者は会社に従属する情報なので独立した画面は持たせず、
 * 顧客詳細の中だけで追加・編集・無効化(is_active)を行う。
 */
class CustomerContactController extends Controller
{
    public function store(PartnerContactRequest $request, int $customerId): RedirectResponse
    {
        $customer = Partner::query()->findOrFail($customerId);

        $customer->contacts()->create($request->validated());

        return $this->backToContacts($customerId, '担当者を追加しました。');
    }

    public function update(PartnerContactRequest $request, int $customerId, int $contactId): RedirectResponse
    {
        $contact = PartnerContact::query()
            ->where('partner_id', $customerId)
            ->findOrFail($contactId);

        $contact->update($request->validated());

        return $this->backToContacts($customerId, '担当者を更新しました。');
    }

    private function backToContacts(int $customerId, string $message): RedirectResponse
    {
        return redirect()
            ->route('customers.show', ['id' => $customerId, 'tab' => 'contacts'])
            ->with('status', $message);
    }
}
