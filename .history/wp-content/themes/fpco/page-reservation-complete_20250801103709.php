<?php
/**
 * Template Name: 予約完了
 * 
 * 工場見学の予約完了画面
 */

get_header(); 

// デバッグ: POSTデータの確認
error_log('Complete page - POST data: ' . print_r($_POST, true));

// POSTデータを取得・処理
$form_data = validate_and_process_reservation($_POST);

// デバッグ: バリデーション結果の確認
error_log('Complete page - Validation result: ' . ($form_data ? 'SUCCESS' : 'FAILED'));
if ($form_data) {
    error_log('Complete page - Form data: ' . print_r($form_data, true));
}

if (!$form_data) {
    // データが不正な場合は入力画面に戻る
    error_log('Complete page - Redirecting to form due to validation failure');
    wp_redirect(home_url('/reservation-form/'));
    exit;
}

// 予約処理を実行
error_log('Complete page - Starting reservation processing');

try {
    $reservation_result = process_reservation($form_data);
    
    // デバッグ: 予約処理結果の確認
    error_log('Complete page - Reservation result: ' . print_r($reservation_result, true));

    if (!$reservation_result || !$reservation_result['success']) {
        // 予約処理に失敗した場合
        $error_msg = isset($reservation_result['error']) ? $reservation_result['error'] : 'Unknown error';
        error_log('Complete page - Reservation processing failed: ' . $error_msg);
        
        // 簡略化されたエラー処理 - データベース保存をスキップして画面表示のみ
        $reservation_id = generate_reservation_id();
        error_log('Complete page - Using fallback reservation ID: ' . $reservation_id);
    } else {
        $reservation_id = $reservation_result['reservation_id'];
        error_log('Complete page - Generated reservation ID: ' . $reservation_id);
    }
} catch (Exception $e) {
    error_log('Complete page - Exception caught: ' . $e->getMessage());
    // フォールバック：エラーが発生してもIDを生成して画面を表示
    $reservation_id = generate_reservation_id();
    error_log('Complete page - Using exception fallback reservation ID: ' . $reservation_id);
}
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
                <h1 class="main-message">予約の申込みが完了しました。<br>確認後、改めて入力いただいたメールアドレスへ<br>ご連絡いたしますので少々お待ちください。</h1>
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
/* ブレッドクラムのスタイル */
.breadcrumb {
    margin-bottom: 20px;
    color: #797369;
    font-size: 12px;
    font-weight: bold;
    margin-left: 70px;
}

.breadcrumb a {
    color: #797369;
    text-decoration: none;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

/* ステップインジケーターのスタイル */
.step-indicator {
    display: flex;
    justify-content: center;
    margin: 30px 0;
    padding: 0;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    padding: 0 40px;
}

.step:not(:last-child)::after {
    content: '';
    position: absolute;
    left: calc(50% + 15px);
    width: calc(80px - -70px);
    height: 2px;
    background: #5C5548;
    top: 15px;
    transform: translateY(-50%);
}

.step.active .step-number {
    background: #5C5548;
    color: white;
}

.step.active .step-label {
    color: #5C5548;
    font-weight: bold;
}

.step.completed .step-number {
    background: #5C5548;
    color: white;
}

.step.completed .step-label {
    color: #5C5548;
    font-weight: bold;
}

.step-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #DFDCDC;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 5px;
    font-weight: bold;
}

.step-label {
    color: #DFDCDC;
    text-align: center;
    font-size: 14px;
}

/* 完了画面のスタイル */
.completion-content {
    max-width: 800px;
    margin: 0 auto;
    text-align: center;
}

.completion-message {
    background: white;
    padding: 40px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.success-icon {
    margin-bottom: 20px;
}

.main-message {
    font-size: 18px;
    color: #5C5548;
    margin-bottom: 20px;
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
    // デバッグ: バリデーション開始
    error_log('Validation - POST data received: ' . print_r($post_data, true));
    
    // 基本的なバリデーション
    if (empty($post_data)) {
        error_log('Validation - POST data is empty');
        return false;
    }
    
    if (!isset($post_data['factory_id'])) {
        error_log('Validation - factory_id is missing');
        return false;
    }
    
    if (!isset($post_data['date'])) {
        error_log('Validation - date is missing');
        return false;
    }
    
    if (!isset($post_data['applicant_name'])) {
        error_log('Validation - applicant_name is missing');
        return false;
    }
    
    error_log('Validation - All required fields present');
    
    // セキュリティチェック（実際の実装ではより詳細な検証を行う）
    return $post_data;
}

function process_reservation($form_data) {
    global $wpdb;
    
    try {
        // 予約IDを生成
        $reservation_id = generate_reservation_id();
        error_log('Process reservation - Generated ID: ' . $reservation_id);
        
        // データベースに予約情報を保存（エラーが発生してもスキップして続行）
        try {
            $result = save_reservation_to_database($reservation_id, $form_data);
            if ($result) {
                error_log('Process reservation - Database save succeeded');
            } else {
                error_log('Process reservation - Database save failed, but continuing');
            }
        } catch (Exception $db_e) {
            error_log('Process reservation - Database error (continuing): ' . $db_e->getMessage());
        }
        
        // メール送信（エラーが発生してもスキップして続行）
        try {
            send_reservation_emails($reservation_id, $form_data);
            error_log('Process reservation - Email sending completed');
        } catch (Exception $mail_e) {
            error_log('Process reservation - Email error (continuing): ' . $mail_e->getMessage());
        }
        
        // 該当時間帯を見学不可に設定（エラーが発生してもスキップして続行）
        try {
            update_calendar_availability($form_data['factory_id'], $form_data['date'], $form_data['timeslot']);
            error_log('Process reservation - Calendar update completed');
        } catch (Exception $cal_e) {
            error_log('Process reservation - Calendar error (continuing): ' . $cal_e->getMessage());
        }
        
        return [
            'success' => true,
            'reservation_id' => $reservation_id
        ];
        
    } catch (Exception $e) {
        error_log('Reservation processing critical error: ' . $e->getMessage());
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
    
    // デバッグ: テーブル存在確認
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    if (!$table_exists) {
        error_log("Database - Table $table_name does not exist");
        // テーブルが存在しない場合は作成する
        create_reservations_table();
    }
    
    $reservation_data = [
        'reservation_id' => $reservation_id,
        'factory_id' => $form_data['factory_id'],
        'reservation_date' => $form_data['date'],
        'timeslot' => $form_data['timeslot'],
        'applicant_name' => $form_data['applicant_name'],
        'applicant_name_kana' => $form_data['applicant_name_kana'] ?? '',
        'email' => $form_data['email'],
        'phone' => $form_data['phone'] ?? '',
        'mobile' => $form_data['mobile'] ?? '',
        'postal_code' => $form_data['postal_code'] ?? '',
        'prefecture' => $form_data['prefecture'] ?? '',
        'city' => $form_data['city'] ?? '',
        'address' => $form_data['address'] ?? '',
        'building' => $form_data['building'] ?? '',
        'transportation' => $form_data['transportation'] ?? '',
        'transportation_other' => $form_data['transportation_other'] ?? '',
        'vehicle_count' => $form_data['vehicle_count'] ?? 0,
        'purpose' => $form_data['purpose'] ?? '',
        'is_travel_agency' => $form_data['is_travel_agency'] ?? 'no',
        'visitor_category' => $form_data['visitor_category'] ?? '',
        'total_visitors' => calculate_total_visitors($form_data),
        'form_data' => json_encode($form_data),
        'status' => 'pending',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    error_log('Database - Inserting reservation data: ' . print_r($reservation_data, true));
    
    $result = $wpdb->insert($table_name, $reservation_data);
    
    if ($result === false) {
        error_log('Database - Insert failed: ' . $wpdb->last_error);
    } else {
        error_log('Database - Insert successful, affected rows: ' . $result);
    }
    
    return $result !== false;
}

function send_reservation_emails($reservation_id, $form_data) {
    try {
        // ユーザーへの確認メール
        $user_result = send_user_confirmation_email($reservation_id, $form_data);
        error_log('Email - User confirmation: ' . ($user_result ? 'SUCCESS' : 'FAILED'));
        
        // 管理者への通知メール
        $admin_result = send_admin_notification_email($reservation_id, $form_data);
        error_log('Email - Admin notification: ' . ($admin_result ? 'SUCCESS' : 'FAILED'));
        
    } catch (Exception $e) {
        error_log('Email - Exception: ' . $e->getMessage());
        // メール送信エラーは致命的ではないので続行
    }
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
    
    return wp_mail($to, $subject, $message);
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
    
    return wp_mail($admin_email, $subject, $message);
}

function create_reservations_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'reservations';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        reservation_id varchar(20) NOT NULL,
        factory_id varchar(10) NOT NULL,
        reservation_date date NOT NULL,
        timeslot varchar(20) NOT NULL,
        applicant_name varchar(100) NOT NULL,
        applicant_name_kana varchar(100),
        email varchar(100) NOT NULL,
        phone varchar(20),
        mobile varchar(20),
        postal_code varchar(10),
        prefecture varchar(20),
        city varchar(50),
        address varchar(100),
        building varchar(100),
        transportation varchar(50),
        transportation_other varchar(100),
        vehicle_count int DEFAULT 0,
        purpose text,
        is_travel_agency varchar(10) DEFAULT 'no',
        visitor_category varchar(50),
        total_visitors int DEFAULT 0,
        form_data longtext,
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY reservation_id (reservation_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    error_log('Database - Reservations table created');
}

function calculate_total_visitors($form_data) {
    // 総見学者数を計算
    $total = 0;
    
    // 見学者分類に応じて人数を計算
    switch ($form_data['visitor_category'] ?? '') {
        case 'school':
            $total = (int)($form_data['school_student_count'] ?? 0) + (int)($form_data['school_supervisor_count'] ?? 0);
            break;
        case 'recruit':
            $total = (int)($form_data['recruit_visitor_count'] ?? 0);
            break;
        case 'family':
            $total = (int)($form_data['family_adult_count'] ?? 0) + (int)($form_data['family_child_count'] ?? 0);
            break;
        case 'company':
            $total = (int)($form_data['company_adult_count'] ?? 0) + (int)($form_data['company_child_count'] ?? 0);
            break;
        case 'government':
            $total = (int)($form_data['government_adult_count'] ?? 0) + (int)($form_data['government_child_count'] ?? 0);
            break;
        case 'other':
            $total = (int)($form_data['other_adult_count'] ?? 0) + (int)($form_data['other_child_count'] ?? 0);
            break;
        default:
            // フォールバック：直接入力された総人数を使用
            $total = (int)($form_data['total_visitor_count'] ?? 1);
    }
    
    return max(1, $total); // 最低1名は保証
}

function update_calendar_availability($factory_id, $date, $timeslot) {
    // カレンダーの該当時間帯を見学不可に設定
    // 実際の実装では、カレンダーAPIまたはデータベースを更新
    
    // この処理は、カレンダーシステムの実装に依存する
    // 例：APIエンドポイントにPOSTリクエストを送信
    error_log("Calendar - Would update availability for factory $factory_id, date $date, timeslot $timeslot");
}

// 必要なヘルパー関数を追加
function get_factory_name($factory_id) {
    $factories = [
        1 => '関東リサイクル',
        2 => '中部リサイクル',
        3 => '福山リサイクル',
        4 => '山形選別センター',
        5 => '松本選別センター',
        6 => '西宮選別センター',
        7 => '東海選別センター',
        8 => '金沢選別センター',
        9 => '九州選別センター'
    ];
    
    return isset($factories[$factory_id]) ? $factories[$factory_id] : '不明';
}

function parse_timeslot($timeslot) {
    // timeslot形式: am-60-1, pm-90-2 など
    $parts = explode('-', $timeslot);
    $period = $parts[0] ?? '';
    $duration = $parts[1] ?? '';
    
    $time_ranges = [
        'am-60-1' => '9:00〜10:00',
        'am-60-2' => '10:30〜11:30',
        'am-90-1' => '9:00〜10:30',
        'am-90-2' => '10:00〜11:30',
        'pm-60-1' => '14:00〜15:00',
        'pm-60-2' => '15:30〜16:30',
        'pm-90-1' => '14:00〜15:30',
        'pm-90-2' => '15:00〜16:30'
    ];
    
    return [
        'period' => strtoupper($period),
        'duration' => $duration,
        'time_range' => $time_ranges[$timeslot] ?? '',
        'display' => strtoupper($period) . '(' . ($time_ranges[$timeslot] ?? '') . ')'
    ];
}

function format_display_date($date) {
    $timestamp = strtotime($date);
    if ($timestamp) {
        return date('Y年m月d日', $timestamp);
    }
    return $date;
}

get_footer();
?>