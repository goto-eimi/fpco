# テスト用URL一覧

## 基本URL構成
あなたのWordPressサイトのドメインを `https://your-site.com` として記載しています。
実際のドメインに置き換えてご使用ください。

## 1. カレンダーページ
```
https://your-site.com/calendar-reservation/
```
- 「予約状況カレンダー」テンプレートが設定されたページ
- ○ボタンをクリックして時間帯選択

## 2. 予約フォーム（直接アクセス用）
```
# 福山リサイクル工場、2025年6月1日、午前9:00-10:00の例
https://your-site.com/reservation-form/?factory=3&date=2025-06-01&timeslot=am-60-1

# 関東リサイクル工場、2025年6月15日、午後14:00-15:30の例
https://your-site.com/reservation-form/?factory=1&date=2025-06-15&timeslot=pm-90-1
```

## 3. 各ページの個別確認
```
# 予約フォーム（パラメータなし）
https://your-site.com/reservation-form/

# 入力確認画面（通常はフォーム送信後にアクセス）
https://your-site.com/reservation-confirm/

# 予約完了画面（通常は確認画面送信後にアクセス）
https://your-site.com/reservation-complete/

# 印刷画面（通常は完了画面からアクセス）
https://your-site.com/reservation-print/
```

## 動作確認手順

### 手順1: WordPressページ作成確認
1. WordPress管理画面にログイン
2. 「固定ページ」→「固定ページ一覧」で以下4つのページが存在することを確認：
   - 予約フォーム（スラッグ: reservation-form）
   - 予約内容確認（スラッグ: reservation-confirm）
   - 予約完了（スラッグ: reservation-complete）
   - 予約内容印刷（スラッグ: reservation-print）

### 手順2: テンプレート適用確認
各ページを編集して、以下のテンプレートが選択されていることを確認：
1. 予約フォーム → 「予約フォーム」テンプレート
2. 予約内容確認 → 「予約フォーム確認」テンプレート
3. 予約完了 → 「予約完了」テンプレート
4. 予約内容印刷 → 「予約内容印刷」テンプレート

### 手順3: パーマリンク再設定
1. WordPress管理画面「設定」→「パーマリンク」
2. 「投稿名」を選択
3. 「変更を保存」をクリック

### 手順4: 動作テスト
1. 上記テスト用URLにアクセス
2. 予約フォームが表示されることを確認
3. フォームに適当なデータを入力
4. 「入力内容の確認 →」ボタンをクリック
5. 確認画面が表示されることを確認

## よくある問題と解決方法

### 404エラーが表示される
- 原因: WordPressページが作成されていない、またはスラッグが間違っている
- 解決: 上記手順1〜3を再実行

### テンプレートが適用されない
- 原因: テンプレートファイルが存在しない、またはテンプレートが選択されていない
- 解決: テーマフォルダ内のファイル存在確認、WordPressページのテンプレート選択確認

### フォーム送信時にエラー
- 原因: 送信先ページが存在しない、またはPHPエラー
- 解決: エラーログの確認、データベーステーブルの作成確認

### JavaScript動作しない  
- 原因: JavaScriptファイルのパス間違い、またはjQuery未読み込み
- 解決: ブラウザのデベロッパーツールでエラー確認

## デバッグ情報

### ファイル存在確認
以下のファイルがテーマフォルダに存在することを確認：
```
wp-content/themes/fpco/page-reservation-form.php
wp-content/themes/fpco/page-reservation-confirm.php  
wp-content/themes/fpco/page-reservation-complete.php
wp-content/themes/fpco/page-reservation-print.php
wp-content/themes/fpco/assets/js/reservation-form.js
```

### WordPressデバッグ有効化
wp-config.phpに以下を追加してエラー表示を有効化：
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);  
define('WP_DEBUG_DISPLAY', true);
```