<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Support\Masters\MasterCatalog;
use Illuminate\View\View;

/**
 * マスタ管理のハブ画面。
 *
 * 各マスタへの入口をカードで一覧する。どのマスタがあるかは MasterCatalog が持つ。
 */
class MasterHubController extends Controller
{
    public function __construct(private readonly MasterCatalog $catalog) {}

    public function index(): View
    {
        return view('masters.index', [
            'cards' => $this->catalog->availableCards(),
            'counts' => $this->catalog->counts(),
        ]);
    }
}
