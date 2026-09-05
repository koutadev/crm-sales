#!/usr/bin/env bash
#
# 共通基盤(laravel-business-template)の共通デザインシステムを、このリポジトリへ取り込む。
#
#   bin/sync-shared-ui.sh            # 取り込む
#   bin/sync-shared-ui.sh --check    # 差分があるかだけ見る(取り込まない)
#   TEMPLATE_PATH=… bin/sync-shared-ui.sh
#
# 取り込むのは「業務によらない共通部分」だけ。CRM 固有の画面・コントローラ
# (crm/ 配下、商品と税率のマスタ画面など)は対象外にしてある。
#
# 同期は rsync --delete なので、共通部分のディレクトリの中に置いた製品固有の
# ファイルは、そのままだと消えてしまう。消したくないものは KEEP に並べておくこと
# (KEEP は --exclude になり、転送も削除もされなくなる)。
# 取り込み後は差分を git で確認し、npm run build と composer ci を通すこと。

set -euo pipefail

TEMPLATE_PATH="${TEMPLATE_PATH:-$(cd "$(dirname "$0")/../../laravel-business-template" && pwd)}"
PROJECT_PATH="$(cd "$(dirname "$0")/.." && pwd)"

if [ ! -d "$TEMPLATE_PATH" ]; then
    echo "共通基盤が見つかりません: $TEMPLATE_PATH" >&2
    exit 1
fi

# 共通基盤から丸ごと持ってくるパス
# (app/Models/Employee.php は CRM 固有の関連を持つため対象外。組織の追加など
#  共通の変更が入ったときは、こちら側にも同じ変更を手で入れる)
PATHS=(
    "app/Support/DataTable"
    # app/Support/Navigation は同期しない
    #   … NavigationMenu.php を製品ごとに書き換えているため(メニュー構成は製品固有)。
    #     NavItem / NavSection に共通の変更が入ったときは手で取り込む。
    "app/Support/Ui"
    "app/Support/Masters"
    "app/Support/Dashboard"
    "app/Support/Routing"
    "app/View/Components"
    "app/Http/Controllers/Masters/MasterController.php"
    "app/Http/Controllers/Masters/MasterHubController.php"
    "app/Http/Controllers/Masters/SimpleMasterController.php"
    "app/Http/Controllers/Masters/EmployeeController.php"
    "app/Http/Controllers/Masters/PartnerController.php"
    "app/Http/Controllers/Masters/DepartmentController.php"
    "app/Http/Controllers/Masters/PositionController.php"
    "app/Http/Controllers/Masters/ProductCategoryController.php"
    "app/Enums/OrganizationType.php"
    "app/Models/Organization.php"
    "app/Tables/OrganizationTable.php"
    "app/Http/Controllers/Masters/OrganizationController.php"
    "app/Http/Requests/Masters/OrganizationRequest.php"
    "database/factories/OrganizationFactory.php"
    "database/migrations/2026_08_25_200000_create_organizations_table.php"
    "database/migrations/2026_08_25_200100_add_organization_id_to_employees_table.php"
    "database/migrations/2026_08_26_100000_add_prefecture_to_organizations_table.php"
    "resources/views/masters/organizations"
    "app/Http/Controllers/SavedViewController.php"
    "app/Http/Requests/SavedViewRequest.php"
    "app/Models/SavedView.php"
    "database/factories/SavedViewFactory.php"
    "database/migrations/2026_08_25_100000_create_saved_views_table.php"
    "app/Http/Requests/Masters/MasterRequest.php"
    "app/Http/Requests/Masters/SimpleMasterRequest.php"
    "app/Http/Requests/Masters/EmployeeRequest.php"
    "app/Http/Requests/Masters/PartnerRequest.php"
    "app/Tables/EmployeeTable.php"
    "app/Tables/PartnerTable.php"
    "app/Tables/SimpleMasterTable.php"
    "app/Tables/UserTable.php"
    "config/ui.php"
    "resources/css/app.css"
    "resources/js"
    "resources/views/components"
    "resources/views/layouts/app.blade.php"
    "resources/views/layouts/guest.blade.php"
    "resources/views/pagination"
    "resources/views/masters/_detail.blade.php"
    "resources/views/masters/index.blade.php"
    "resources/views/masters/employees"
    "resources/views/masters/partners"
    "resources/views/masters/simple"
    "resources/views/users"
)

# 共通部分のディレクトリの中にある「この製品だけのファイル」。同期で消さない。
KEEP=(
    # いまのところ CRM 固有のファイルは共通部分のディレクトリに置いていない。
    # (CRM の画面は crm/ 配下、メニューの差し替えは app/Support/Crm/CrmNavigationMenu.php)
)

# そのパスに効かせる --exclude を組み立てる(KEEP からの相対パスにする)
excludes_for() {
    local path="$1" keep
    EXCLUDES=()

    for keep in ${KEEP[@]+"${KEEP[@]}"}; do
        case "$keep" in
            "$path/"*) EXCLUDES+=("--exclude=${keep#"$path/"}") ;;
        esac
    done
}

# --delete で「基盤から消えたファイル」を追随して消す。
# --delete-excluded は使わない(除外＝この製品固有のファイルまで消してしまうため)。
RSYNC_FLAGS=(-a --delete)

if [ "${1:-}" = "--check" ]; then
    RSYNC_FLAGS+=(--dry-run --itemize-changes)
fi

for path in "${PATHS[@]}"; do
    src="$TEMPLATE_PATH/$path"
    dest="$PROJECT_PATH/$path"

    if [ ! -e "$src" ]; then
        echo "  (skip) $path — 共通基盤にありません"
        continue
    fi

    excludes_for "$path"

    if [ -d "$src" ]; then
        mkdir -p "$dest"
        rsync "${RSYNC_FLAGS[@]}" ${EXCLUDES[@]+"${EXCLUDES[@]}"} "$src/" "$dest/"
    else
        mkdir -p "$(dirname "$dest")"
        rsync "${RSYNC_FLAGS[@]}" ${EXCLUDES[@]+"${EXCLUDES[@]}"} "$src" "$dest"
    fi
done

if [ "${1:-}" = "--check" ]; then
    echo
    echo "--check なので取り込みはしていません。"
    echo "上の *deleting の行が「同期すると消えるファイル」です。"
    echo "製品固有のものが並んでいたら、KEEP に足してから同期してください。"
else
    echo
    echo "取り込みました。git diff で差分を確認し、npm run build と composer ci を通してください。"
fi
