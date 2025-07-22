<?php
/**
 * Template Name: 予約完了
 * 
 * 工場見学の予約完了画面
 */

get_header(); 

// POSTデータを取得・処理
$form_data = validate_and_process_reservation($_POST);

if (!$form_data) {
    // データが不正な場合は入力画面に戻る
    wp_redirect(home_url('/reservation-form/'));
    exit;
}

// 予約処理を実行
$reservation_result = process_reservation($form_data);

if (!$reservation_result['success']) {
    // 予約処理に失敗した場合
    wp_die('予約処理中にエラーが発生しました。しばらく時間をおいてから再度お試しください。');
}

$reservation_id = $reservation_result['reservation_id'];
?>

<main id="main" class="wp-block-group">
    <div class="wp-block-group__inner-container">
        <!-- パンくずリスト -->
        <nav class="breadcrumb">
            <a href="<?php echo home_url(); ?>">TOP</a> &gt; 
            <a href="<?php echo home_url('/reservation-calendar/'); ?>">予約状況カレンダー</a> &gt; 
            <a href="<?php echo home_url('/reservation-form/'); ?>">予約フォーム</a> &gt; 
            <a href="<?php echo home_url('/reservation-confirm/'); ?>">入力内容の確認</a> &gt; 
            <span>予約完了</span>
        </nav>

        <!-- ステップインジケーター -->
        <div class="step-indicator">
            <div class="step completed">
                <span class="step-number">1</span>
                <span class="step-label">必要事項の入力</span>
            </div>
            <div class="step completed">
                <span class="step-number">2</span>
                <span class="step-label">入力内容の確認</span>
            </div>
            <div class="step active">
                <span class="step-number">3</span>
                <span class="step-label">送信完了</span>
            </div>
        </div>

        <div class="completion-content">
            <!-- 完了メッセージ -->
            <div class="completion-message">
                <div class="success-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="12" fill="#28a745"/>
                        <path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h1 class="main-message">予約の申込みが完了しました。</h1>
                <p class="sub-message">
                    予約受付番号: <strong><?php echo esc_html($reservation_id); ?></strong><br>
                    確認後、改めて入力いただいたメールアドレスへご連絡いたしますので少々お待ちください。
                </p>
            </div>

            <!-- 予約内容サマリー -->
            <div class="reservation-summary">
                <h2>ご予約内容</h2>
                <dl class="summary-list">
                    <dt>見学工場</dt>
                    <dd><?php echo esc_html(get_factory_name($form_data['factory_id'])); ?>工場</dd>
                    
                    <dt>見学日</dt>
                    <dd><?php echo esc_html(format_display_date($form_data['date'])); ?></dd>
                    
                    <dt>見学時間帯</dt>
                    <dd><?php echo esc_html(parse_timeslot($form_data['timeslot'])['display']); ?></dd>
                    
                    <dt>申込者様</dt>
                    <dd><?php echo esc_html($form_data['applicant_name']); ?></dd>
                    
                    <dt>見学者様人数</dt>
                    <dd><?php echo calculate_total_visitors($form_data); ?>名</dd>
                    
                    <dt>連絡先</dt>
                    <dd><?php echo esc_html($form_data['email']); ?></dd>
                </dl>
            </div>

            <!-- 注意事項 -->
            <div class="important-notice">
                <h3>重要なお知らせ</h3>
                <ul>
                    <li>この度は工場見学にお申込みいただき、ありがとうございます。</li>
                    <li>担当者が内容を確認後、1〜2営業日以内にメールでご連絡いたします。</li>
                    <li>見学当日は、受付時間の10分前にお越しください。</li>
                    <li>安全のため、見学時はヘルメットの着用が必要です（当社で準備いたします）。</li>
                    <li>機械の稼働状況により、見学コースが変更になる場合があります。</li>
                    <li>キャンセルやご変更の場合は、お早めにご連絡ください。</li>
                </ul>
            </div>

            <!-- アクションボタン -->
            <div class="action-buttons">
                <button type="button" class="btn-print" onclick="printReservation()">
                    予約内容を印刷する
                </button>
                <a href="<?php echo home_url(); ?>" class="btn-home">
                    TOPへ戻る →
                </a>
            </div>
        </div>

        <!-- 印刷用の隠しフォーム -->
        <form id="print-form" method="post" action="<?php echo home_url('/reservation-print/'); ?>" target="_blank" style="display: none;">
            <input type="hidden" name="reservation_id" value="<?php echo esc_attr($reservation_id); ?>">
            <?php foreach ($form_data as $key => $value): ?>
                <?php if (is_array($value)): ?>
                    <?php foreach ($value as $subKey => $subValue): ?>
                        <input type="hidden" name="<?php echo esc_attr($key . '[' . $subKey . ']'); ?>" value="<?php echo esc_attr($subValue); ?>">
                    <?php endforeach; ?>
                <?php else: ?>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
        </form>
    </div>
</main>

<style>
/* 完了画面のスタイル */
.completion-content {
    max-width: 800px;
    margin: 0 auto;
    text-align: center;
}

.completion-message {
    background: white;
    padding: 40px;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 30px;
}

.success-icon {
    margin-bottom: 20px;
}

.main-message {
    font-size: 28px;
    color: #28a745;
    margin-bottom: 20px;
    font-weight: bold;
}

.sub-message {
    font-size: 16px;
    color: #666;
    line-height: 1.6;
    margin: 0;
}

.sub-message strong {
    color: #007cba;
    font-size: 18px;
}

.reservation-summary {
    background: #f8f9fa;
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 30px;
    text-align: left;
}

.reservation-summary h2 {
    text-align: center;
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 22px;
    color: #333;
}

.summary-list {
    display: grid;
    gap: 15px;
    margin: 0;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.summary-list dt {
    font-weight: bold;
    color: #666;
    margin-bottom: 5px;
}

.summary-list dd {
    margin: 0 0 15px 0;
    color: #333;
    background: white;
    padding: 10px;
    border-radius: 4px;
    font-size: 16px;
}

.important-notice {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    text-align: left;
}

.important-notice h3 {
    color: #856404;
    margin-top: 0;
    margin-bottom: 15px;
}

.important-notice ul {
    color: #856404;
    margin: 0;
    padding-left: 20px;
}

.important-notice li {
    margin-bottom: 8px;
    line-height: 1.5;
}

.action-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    margin-top: 40px;
}

.btn-print {
    padding: 15px 30px;
    background: #6c757d;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.btn-print:hover {
    background: #5a6268;
}

.btn-home {
    padding: 15px 30px;
    background: #007cba;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.btn-home:hover {
    background: #005a87;
    color: white;
}

.step.active .step-number {
    background: #28a745;
    color: white;
}

.step.active .step-label {
    color: #28a745;
    font-weight: bold;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .completion-message {
        padding: 30px 20px;
    }
    
    .main-message {
        font-size: 24px;
    }
    
    .reservation-summary,
    .important-notice {
        padding: 20px;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .btn-print,
    .btn-home {
        width: 100%;
        max-width: 300px;
        justify-content: center;
    }
}

/* 印刷スタイル */
@media print {
    .breadcrumb,
    .step-indicator,
    .action-buttons,
    .important-notice {
        display: none;
    }
    
    .completion-content {
        max-width: none;
    }
}
</style>

<script>
function printReservation() {
    // 印刷用画面を別ウィンドウで開く
    document.getElementById('print-form').submit();
}
</script>

<?php
// ヘルパー関数

function validate_and_process_reservation($post_data) {
    // 基本的なバリデーション
    if (empty($post_data) || !isset($post_data['factory_id']) || !isset($post_data['date'])) {
        return false;
    }
    
    // セキュリティチェック（実際の実装ではより詳細な検証を行う）
    return $post_data;
}

function process_reservation($form_data) {
    global $wpdb;
    
    try {
        // 予約IDを生成
        $reservation_id = generate_reservation_id();
        
        // データベースに予約情報を保存
        $result = save_reservation_to_database($reservation_id, $form_data);
        
        if (!$result) {
            throw new Exception('データベース保存に失敗しました');
        }
        
        // メール送信
        send_reservation_emails($reservation_id, $form_data);
        
        // 該当時間帯を見学不可に設定
        update_calendar_availability($form_data['factory_id'], $form_data['date'], $form_data['timeslot']);
        
        return [
            'success' => true,
            'reservation_id' => $reservation_id
        ];
        
    } catch (Exception $e) {
        error_log('Reservation processing error: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function generate_reservation_id() {
    // 予約番号の生成（年月日 + 4桁の連番）
    $date_prefix = date('Ymd');
    $random_suffix = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return $date_prefix . $random_suffix;
}

function save_reservation_to_database($reservation_id, $form_data) {
    global $wpdb;
    
    // 予約テーブルに保存
    $table_name = $wpdb->prefix . 'reservations';
    
    $reservation_data = [
        'reservation_id' => $reservation_id,
        'factory_id' => $form_data['factory_id'],
        'reservation_date' => $form_data['date'],
        'timeslot' => $form_data['timeslot'],
        'applicant_name' => $form_data['applicant_name'],
        'applicant_name_kana' => $form_data['applicant_name_kana'],
        'email' => $form_data['email'],
        'phone' => $form_data['phone'],
        'mobile' => $form_data['mobile'],
        'postal_code' => $form_data['postal_code'],
        'prefecture' => $form_data['prefecture'],
        'city' => $form_data['city'],
        'address' => $form_data['address'],
        'building' => $form_data['building'] ?? '',
        'transportation' => $form_data['transportation'],
        'transportation_other' => $form_data['transportation_other'] ?? '',
        'vehicle_count' => $form_data['vehicle_count'] ?? 0,
        'purpose' => $form_data['purpose'],
        'is_travel_agency' => $form_data['is_travel_agency'],
        'visitor_category' => $form_data['visitor_category'],
        'total_visitors' => calculate_total_visitors($form_data),
        'form_data' => json_encode($form_data),
        'status' => 'pending',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    $result = $wpdb->insert($table_name, $reservation_data);
    
    return $result !== false;
}

function send_reservation_emails($reservation_id, $form_data) {
    // ユーザーへの確認メール
    send_user_confirmation_email($reservation_id, $form_data);
    
    // 管理者への通知メール
    send_admin_notification_email($reservation_id, $form_data);
}

function send_user_confirmation_email($reservation_id, $form_data) {
    $to = $form_data['email'];
    $subject = '【エフピコ】工場見学予約受付完了のお知らせ';
    
    $factory_name = get_factory_name($form_data['factory_id']);
    $timeslot_info = parse_timeslot($form_data['timeslot']);
    
    $message = "
{$form_data['applicant_name']} 様

この度は、エフピコ{$factory_name}工場見学にお申込みいただき、誠にありがとうございます。
以下の内容で予約を受付いたしました。

【予約受付番号】
{$reservation_id}

【ご予約内容】
見学工場：{$factory_name}工場
見学日：" . format_display_date($form_data['date']) . "
見学時間帯：{$timeslot_info['display']}
見学者様人数：" . calculate_total_visitors($form_data) . "名

担当者が内容を確認後、1〜2営業日以内に詳細についてご連絡いたします。

ご不明な点がございましたら、お気軽にお問い合わせください。

─────────────────────────────
株式会社エフピコ
〒721-8607 広島県福山市曙町1-13-15
TEL: 084-953-1411
─────────────────────────────
    ";
    
    wp_mail($to, $subject, $message);
}

function send_admin_notification_email($reservation_id, $form_data) {
    $admin_email = get_option('admin_email');
    $subject = '【新規予約】工場見学予約申込み - ' . $reservation_id;
    
    $factory_name = get_factory_name($form_data['factory_id']);
    $timeslot_info = parse_timeslot($form_data['timeslot']);
    
    $message = "
新しい工場見学予約の申込みがありました。

【予約受付番号】{$reservation_id}

【基本情報】
見学工場：{$factory_name}工場
見学日：" . format_display_date($form_data['date']) . "
見学時間帯：{$timeslot_info['display']}
見学者様人数：" . calculate_total_visitors($form_data) . "名

【申込者情報】
氏名：{$form_data['applicant_name']}（{$form_data['applicant_name_kana']}）
メールアドレス：{$form_data['email']}
電話番号：{$form_data['phone']}
携帯番号：{$form_data['mobile']}

管理画面より詳細をご確認ください。
    ";
    
    wp_mail($admin_email, $subject, $message);
}

function update_calendar_availability($factory_id, $date, $timeslot) {
    // カレンダーの該当時間帯を見学不可に設定
    // 実際の実装では、カレンダーAPIまたはデータベースを更新
    
    // この処理は、カレンダーシステムの実装に依存する
    // 例：APIエンドポイントにPOSTリクエストを送信
}

get_footer();
?>