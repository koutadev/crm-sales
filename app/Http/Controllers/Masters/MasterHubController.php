<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Support\Masters\MasterCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * マスタ管理のハブ画面。
 *
 * 各マスタへの入口をカードで一覧する。どのマスタがあるかは MasterCatalog が持つ。
 * マスタごとに必要な権限が違う場合(管理者だけが扱うマスタなど)に備えて、
 * そのユーザーが開けるカードだけを並べる。
 */
class MasterHubController extends Controller
{
    public function __construct(private readonly MasterCatalog $catalog) {}

    public function index(Request $request): View
    {
        return view('masters.index', [
            'cards' => $this->catalog->visibleCards($request->user()),
            'counts' => $this->catalog->counts(),
        ]);
    }
}
