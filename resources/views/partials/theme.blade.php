{{--
    テーマ配色の注入。

    config/theme.php の値を CSS カスタムプロパティとして上書きする。
    Tailwind のユーティリティ（bg-primary など）は var(--color-primary) を参照しているため、
    ここを差し替えるだけでアセットを再ビルドせずに配色が変わる。
--}}
<style>{{ \App\Support\Theme\Theme::cssVariables() }}</style>
