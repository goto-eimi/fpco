<?php
/**
 * 予約返信メール機能（統合プラグイン版）
 * 元のfactory-reservation-manager/reply-email.phpから移植
 */

// プラグインの直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// WordPressが完全に読み込まれるまで待機
if (!function_exists('add_action')) {
    return;
}

/**
 * CSSファイルの読み込み
 */
add_action('admin_enqueue_scripts', 'fpco_reply_email_enqueue_scripts');

function fpco_reply_email_enqueue_scripts($hook) {
    if (!$hook || $hook !== 'toplevel_page_reply-email') {
        return;
    }
    wp_enqueue_style('reply-email-css', FPCO_RESERVATION_PLUGIN_URL . 'assets/css/reply-email.css', [], '1.0');
}

/**
 * 管理画面メニューを追加
 */
add_action('admin_menu', 'fpco_reply_email_admin_menu');

function fpco_reply_email_admin_menu() {
    // 通常はreservation-managementから遷移するため、直接のメニューは非表示に設定
    add_submenu_page(
        null, // 親ページなし（直接アクセス不可）
        '予約返信メール',
        '予約返信メール',
        'manage_options',
        'reply-email',
        'fpco_reply_email_admin_page'
    );
}

/**
 * メールテンプレート定義
 */
function fpco_get_email_templates() {
    return [
        'approval' => [
            'name' => '承認用テンプレート',
            'subject' => '【承認】工場見学のご予約について',
            'body' => '{申込者名} 様

この度は、弊社工場見学をお申込みいただき、誠にありがとうございます。
下記の内容でご予約を承認いたしました。

【見学日時】{見学日} {時間帯}
【見学工場】{工場名}
【見学時間】{見学時間}分
【見学者数】{見学者人数}名

当日は、開始時刻の10分前までに受付へお越しください。
ご不明な点がございましたら、お気軽にお問い合わせください。

何卒よろしくお願いいたします。'
        ],
        'rejection' => [
            'name' => '否認用テンプレート',
            'subject' => '【お詫び】工場見学のご予約について',
            'body' => '{申込者名} 様

この度は、弊社工場見学をお申込みいただき、誠にありがとうございます。

大変申し訳ございませんが、ご希望の日時は既に予約が埋まっており、
ご予約をお受けすることができません。

別の日程でのご見学をご検討いただければ幸いです。
予約カレンダーより、空き状況をご確認ください。

ご迷惑をおかけして誠に申し訳ございません。
何卒ご理解のほど、よろしくお願いいたします。'
        ]
    ];
}

/**
 * プレースホルダー変数を実際の値に置換
 */
function fpco_replace_placeholders($text, $reservation_data) {
    // null値対策でデータを安全に取得
    $placeholders = [
        '{申込者名}' => $reservation_data['applicant_name'] ?? '',
        '{見学日}' => !empty($reservation_data['date']) ? date('Y年m月d日', strtotime($reservation_data['date'])) : '',
        '{時間帯}' => $reservation_data['time_slot'] ?? '',
        '{工場名}' => $reservation_data['factory_name'] ?? '',
        '{見学時間}' => '60', // デフォルト、後で実際のデータに置換
        '{見学者人数}' => $reservation_data['participant_count'] ?? '',
        '{予約番号}' => $reservation_data['id'] ?? '',
        '{組織名}' => $reservation_data['organization_name'] ?? ''
    ];
    
    // nullでないことを確認してから置換
    if ($text === null) {
        return '';
    }
    
    return str_replace(array_keys($placeholders), array_values($placeholders), $text);
}

/**
 * メール送信処理
 */
function fpco_send_reservation_email($reservation_id, $subject, $body, $template_type = '') {
    global $wpdb;
    
    // 予約データを取得
    $reservation = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT r.*, f.name as factory_name 
             FROM {$wpdb->prefix}reservations r 
             JOIN {$wpdb->prefix}factorys f ON r.factory_id = f.id 
             WHERE r.id = %d",
            $reservation_id
        ),
        ARRAY_A
    );
    
    if (!$reservation) {
        return ['success' => false, 'message' => '予約データが見つかりません。'];
    }
    
    // プレースホルダーを置換
    $final_subject = fpco_replace_placeholders($subject, $reservation);
    $final_body = fpco_replace_placeholders($body, $reservation);
    
    // 送信先設定
    $to = $reservation['email'] ?? '';
    if (empty($to)) {
        return ['success' => false, 'message' => '送信先メールアドレスが設定されていません。'];
    }
    
    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'Bcc: admin@example.com', // 管理者メール（実際のメールアドレスに変更）
    ];
    
    // メール送信
    $sent = wp_mail($to, $final_subject, $final_body, $headers);
    
    if ($sent) {
        // 送信履歴を記録
        $wpdb->insert(
            $wpdb->prefix . 'email_logs',
            [
                'reservation_id' => intval($reservation_id),
                'sender_user_id' => get_current_user_id() ?: 0,
                'template_type' => $template_type ?? '',
                'subject' => $final_subject ?? '',
                'body' => $final_body ?? '',
                'sent_at' => current_time('mysql'),
                'status' => 'sent'
            ]
        );
        
        // テンプレートに応じてステータス更新
        if ($template_type === 'approval') {
            $wpdb->update(
                $wpdb->prefix . 'reservations',
                ['status' => 'approved'],
                ['id' => $reservation_id]
            );
        } elseif ($template_type === 'rejection') {
            $wpdb->update(
                $wpdb->prefix . 'reservations',
                ['status' => 'rejected'],
                ['id' => $reservation_id]
            );
        }
        
        return ['success' => true, 'message' => 'メールを送信しました。'];
    } else {
        return ['success' => false, 'message' => 'メール送信に失敗しました。'];
    }
}

/**
 * フォーム送信処理
 */
function fpco_handle_email_form_submission() {
    if (!isset($_POST['send_email']) || !wp_verify_nonce($_POST['_wpnonce'], 'send_reservation_email')) {
        return ['success' => false, 'message' => 'セキュリティチェックに失敗しました。'];
    }
    
    $reservation_id = intval($_POST['reservation_id'] ?? 0);
    $subject = sanitize_text_field($_POST['subject'] ?? '');
    $body = sanitize_textarea_field($_POST['body'] ?? '');
    $template_type = sanitize_text_field($_POST['template_type'] ?? '');
    
    // バリデーション
    if (empty($subject)) {
        return ['success' => false, 'message' => '件名は必須項目です。'];
    }
    
    if (empty($body)) {
        return ['success' => false, 'message' => '本文は必須項目です。'];
    }
    
    if (strlen($subject ?? '') > 100) {
        return ['success' => false, 'message' => '件名は100文字以内で入力してください。'];
    }
    
    if (strlen($body ?? '') > 5000) {
        return ['success' => false, 'message' => '本文は5000文字以内で入力してください。'];
    }
    
    return fpco_send_reservation_email($reservation_id, $subject, $body, $template_type);
}

/**
 * 管理画面表示
 */
function fpco_reply_email_admin_page() {
    global $wpdb;
    
    // 予約IDを取得
    $reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;
    
    if (!$reservation_id) {
        echo '<div class="wrap"><h1>エラー</h1><p>予約IDが指定されていません。</p></div>';
        return;
    }
    
    // 予約データを取得
    $reservation = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT r.*, f.name as factory_name 
             FROM {$wpdb->prefix}reservations r 
             JOIN {$wpdb->prefix}factorys f ON r.factory_id = f.id 
             WHERE r.id = %d",
            $reservation_id
        ),
        ARRAY_A
    );
    
    if (!$reservation) {
        echo '<div class="wrap"><h1>エラー</h1><p>予約データが見つかりません。</p></div>';
        return;
    }
    
    
    // フォーム送信処理
    $result = null;
    if (isset($_POST['send_email'])) {
        $result = fpco_handle_email_form_submission();
        if ($result['success']) {
            echo '<script>
                alert("' . esc_js($result['message']) . '");
                setTimeout(function() {
                    window.location.href = "admin.php?page=reservation-management";
                }, 3000);
            </script>';
        }
    }
    
    $templates = fpco_get_email_templates();
    ?>
    
    <div class="wrap">
        <h1>予約返信メール</h1>
        
        <?php if ($result && !$result['success']): ?>
            <div class="notice notice-error">
                <p><?php echo esc_html($result['message']); ?></p>
            </div>
        <?php endif; ?>
        
        
        <!-- メール作成エリア -->
        <form method="post" id="email-form">
            <?php wp_nonce_field('send_reservation_email'); ?>
            <input type="hidden" name="reservation_id" value="<?php echo esc_attr($reservation_id); ?>">
            
            <!-- 1つ目の枠：メール送信先 -->
            <div style="border: 2px solid #007cba; margin-bottom: 20px; background: white;">
                <h2 style="background: #007cba; color: white; margin: 0; padding: 12px 20px; font-size: 16px; font-weight: 600;">メール送信先</h2>
                <div style="padding: 20px;">
                    <div style="margin-bottom: 10px;">
                        <span style="font-weight: 600; color: #333; margin-right: 10px;">予約番号:</span>
                        <span style="color: #333;"><?php echo esc_html($reservation['id']); ?></span>
                    </div>
                    <div>
                        <span style="font-weight: 600; color: #333; margin-right: 10px;">送信先メールアドレス:</span>
                        <span style="color: #333;"><?php echo esc_html($reservation['email']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- 2つ目の枠：メール内容 -->
            <div style="border: 2px solid #007cba; margin-bottom: 20px; background: white;">
                <h2 style="background: #007cba; color: white; margin: 0; padding: 12px 20px; font-size: 16px; font-weight: 600;">メール内容</h2>
                <div style="padding: 20px;">
                    <!-- テンプレート選択 -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin-top: 0; margin-bottom: 10px;">テンプレート選択</h3>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <select name="template_type" id="template-select" style="flex: 1;">
                                <option value="">選択してください</option>
                                <option value="approval">承認用テンプレート</option>
                                <option value="rejection">否認用テンプレート</option>
                            </select>
                            <button type="button" id="apply-template" class="button">テンプレートを適用</button>
                        </div>
                    </div>
                    
                    <!-- 件名入力 -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin-top: 0; margin-bottom: 10px;">件名 <span class="required">*</span></h3>
                        <input type="text" name="subject" id="subject" maxlength="100" required 
                               style="width: 100%;" placeholder="メール件名を入力してください">
                        <div class="char-count">0/100文字</div>
                    </div>
                    
                    <!-- 本文入力 -->
                    <div>
                        <h3 style="margin-top: 0; margin-bottom: 10px;">本文 <span class="required">*</span></h3>
                        <textarea name="body" id="body" rows="12" maxlength="5000" required 
                                  style="width: 100%;" placeholder="メール本文を入力してください"></textarea>
                        <div class="char-count">0/5000文字</div>
                    </div>
                </div>
            </div>
        
        <!-- 送信ボタン（枠外） -->
        <div style="text-align: right; margin: 20px 0;">
            <button type="submit" name="send_email" class="button button-primary" 
                    onclick="return confirm('このメールを送信してもよろしいですか？');">
                メールを送信
            </button>
            <a href="admin.php?page=reservation-management" class="button" style="margin-left: 10px;">キャンセル</a>
        </div>
        </form>
        
        <!-- プレースホルダー一覧（枠外） -->
        <div style="margin-top: 30px;">
            <h3>使用可能なプレースホルダー</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px;">
                <div>{申込者名} - 申込者氏名</div>
                <div>{見学日} - 見学日（yyyy年mm月dd日形式）</div>
                <div>{時間帯} - 見学時間帯</div>
                <div>{工場名} - 見学工場名</div>
                <div>{見学時間} - 見学時間（60分/90分）</div>
                <div>{見学者人数} - 総見学者数</div>
                <div>{予約番号} - 予約番号</div>
                <div>{組織名} - 学校・会社・団体名</div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // テンプレートデータ
        const templates = <?php echo json_encode($templates); ?>;
        
        // 文字数カウント
        function updateCharCount(element, maxLength) {
            const current = element.val().length;
            element.siblings('.char-count').text(current + '/' + maxLength + '文字');
        }
        
        $('#subject').on('input', function() {
            updateCharCount($(this), 100);
        });
        
        $('#body').on('input', function() {
            updateCharCount($(this), 5000);
        });
        
        // テンプレート適用
        $('#apply-template').on('click', function() {
            const templateType = $('#template-select').val();
            if (!templateType) {
                alert('テンプレートを選択してください。');
                return;
            }
            
            if (templateType in templates) {
                $('#subject').val(templates[templateType].subject);
                $('#body').val(templates[templateType].body);
                updateCharCount($('#subject'), 100);
                updateCharCount($('#body'), 5000);
            }
        });
    });
    </script>
    
    <?php
}
?>