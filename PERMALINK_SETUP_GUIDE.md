# WordPressパーマリンク設定詳細ガイド

## パーマリンクとは

パーマリンクは、WordPressサイトの各ページのURL構造を決定する設定です。この設定が正しくないと、作成したページにアクセスできない（404エラー）問題が発生します。

## 現在の問題

予約システムで以下のURLにアクセスする必要がありますが、パーマリンク設定が正しくないとこれらのURLが動作しません：

```
https://your-site.com/reservation-form/
https://your-site.com/reservation-confirm/
https://your-site.com/reservation-complete/
https://your-site.com/reservation-print/
```

## パーマリンク設定手順

### 1. WordPress管理画面にアクセス

1. WordPressサイトの管理画面にログイン
2. URLは通常 `https://your-site.com/wp-admin/` です

### 2. パーマリンク設定ページを開く

**方法1: メニューから**
1. 左側のメニューから「設定」をクリック
2. サブメニューから「パーマリンク」をクリック

**方法2: 直接URL**
```
https://your-site.com/wp-admin/options-permalink.php
```

### 3. パーマリンク構造を選択

画面に表示される選択肢から**「投稿名」**を選択してください：

```
⚪ 基本                ?p=123
⚪ 日付と投稿名        /2023/01/sample-post/
⚪ 月と投稿名          /2023/01/sample-post/
⚪ 数字ベース          /archives/123
⚫ 投稿名              /sample-post/          ← これを選択
⚪ カスタム構造        /%year%/%monthnum%/%day%/%postname%/
```

### 4. 設定を保存

1. 「投稿名」を選択後、ページ下部の**「変更を保存」**ボタンをクリック
2. 「パーマリンク構造を更新しました。」というメッセージが表示されることを確認

## 設定確認方法

### 確認1: 設定画面での確認
パーマリンク設定画面で「投稿名」にチェックが入っており、プレビューURLが以下の形式になっていることを確認：
```
https://your-site.com/sample-post/
```

### 確認2: 実際のページでの確認
1. 任意の固定ページまたは投稿を表示
2. URLが以下のような形式になっていることを確認：
   - 投稿: `https://your-site.com/投稿のスラッグ/`
   - 固定ページ: `https://your-site.com/ページのスラッグ/`

## よくある問題と解決方法

### 問題1: 「変更を保存」をクリックしても設定が反映されない

**原因**: `.htaccess`ファイルの書き込み権限がない

**解決方法**:
1. FTPまたはファイルマネージャーでサイトのルートディレクトリにアクセス
2. `.htaccess`ファイルの権限を`644`に設定
3. ファイルが存在しない場合は、空の`.htaccess`ファイルを作成
4. 再度WordPress管理画面でパーマリンクを保存

### 問題2: 設定後にサイト全体が表示されない

**原因**: サーバーがmod_rewriteをサポートしていない

**解決方法**:
1. サーバー管理者に`mod_rewrite`の有効化を依頼
2. または一時的に「基本」形式を選択して動作確認

### 問題3: 一部のページだけ404エラーになる

**原因**: WordPressの書き換えルールが正しく生成されていない

**解決方法**:
1. パーマリンク設定画面で再度「変更を保存」をクリック
2. WordPressキャッシュをクリア（キャッシュプラグインを使用している場合）

## 技術的な詳細

### .htaccessファイルの内容

パーマリンク設定が正しく行われると、`.htaccess`ファイルに以下のようなコードが自動追加されます：

```apache
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
```

### パーマリンク設定の影響

**設定前（基本形式）**:
```
https://your-site.com/?page_id=123
https://your-site.com/?p=456
```

**設定後（投稿名形式）**:
```
https://your-site.com/reservation-form/
https://your-site.com/sample-post/
```

## 予約システム特有の注意点

### 1. 必須の固定ページ

以下4つの固定ページが作成され、正しいスラッグが設定されている必要があります：

| ページ名 | スラッグ | URL |
|----------|----------|-----|
| 予約フォーム | `reservation-form` | `/reservation-form/` |
| 予約内容確認 | `reservation-confirm` | `/reservation-confirm/` |
| 予約完了 | `reservation-complete` | `/reservation-complete/` |
| 予約内容印刷 | `reservation-print` | `/reservation-print/` |

### 2. スラッグの確認・変更方法

**固定ページのスラッグ確認**:
1. WordPress管理画面「固定ページ」→「固定ページ一覧」
2. 各ページの「クイック編集」をクリック
3. 「スラッグ」欄を確認・修正

**正しいスラッグ設定例**:
```
タイトル: 予約フォーム
スラッグ: reservation-form  ← ハイフン区切り、英数字のみ
```

### 3. URLパラメータの動作確認

パーマリンク設定後、以下のURLが正常に動作することを確認：
```
https://your-site.com/reservation-form/?factory=1&date=2025-06-01&timeslot=am-60-1
```

## トラブルシューティング手順

### 手順1: 基本チェック
1. WordPress管理画面にアクセス可能か確認
2. 「設定」→「パーマリンク」ページが開けるか確認
3. 「投稿名」が選択されているか確認

### 手順2: ファイルシステムチェック
```bash
# サイトルートディレクトリで実行
ls -la .htaccess
# 権限が644または666になっているか確認
```

### 手順3: サーバー環境チェック
PHPの情報ページ（phpinfo()）で以下を確認：
- `mod_rewrite` モジュールが有効か
- `AllowOverride` が適切に設定されているか

### 手順4: WordPressデバッグ

wp-config.phpに以下を追加してエラー詳細を確認：
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
```

## 完了確認チェックリスト

設定完了後、以下を確認してください：

- [ ] WordPress管理画面「設定」→「パーマリンク」で「投稿名」が選択されている
- [ ] 設定保存時にエラーメッセージが表示されない
- [ ] 既存の投稿・固定ページが正常に表示される
- [ ] 404エラーページが表示されない
- [ ] 予約システムの4つのページが正常にアクセスできる

## 参考: 他のパーマリンク形式との比較

| 形式 | URL例 | メリット | デメリット |
|------|--------|----------|------------|
| 基本 | `/?p=123` | 確実に動作 | SEO的に不利、覚えにくい |
| 日付と投稿名 | `/2023/01/post/` | 日付が分かる | URLが長い |
| 投稿名 | `/post/` | 短く分かりやすい | 同名投稿で競合の可能性 |
| カスタム | 自由設定 | 柔軟性高い | 設定が複雑 |

予約システムには「投稿名」形式が最適です。