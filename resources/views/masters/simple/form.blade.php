{{-- 部署 / 役職 / 商品分類で共有する登録・編集画面 --}}
<x-master-form :record="$record" :resource-label="$resourceLabel" :route-name="$routeName">
    @include('masters.simple.fields')
</x-master-form>
