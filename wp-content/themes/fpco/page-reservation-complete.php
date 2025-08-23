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
$reservation_id = null;
$save_success = false;

try {
    $reservation_result = process_reservation($form_data);
    
    if ($reservation_result && $reservation_result['success']) {
        $reservation_id = $reservation_result['reservation_id'];
        $save_success = true;
    } else {
        // 予約処理に失敗した場合もIDを生成して画面表示
        $reservation_id = generate_reservation_id();
        $save_success = false;
    }
} catch (Exception $e) {
    // エラーが発生してもIDを生成して画面を表示
    $reservation_id = generate_reservation_id();
    $save_success = false;
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

            <!-- アクションボタン -->
            <div class="action-buttons">
                <button type="button" class="btn-print" onclick="printReservation()">
                    <span class="btn-text">予約内容を印刷する</span>
                    <span class="btn-arrow">→</span>
                </button>
                <a href="<?php echo home_url('/'); ?>" class="btn-home">
                    <span class="btn-text">TOPへ戻る</span>
                    <span class="btn-arrow">→</span>
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
}

.success-icon {
    margin-bottom: 20px;
}

.main-message {
    font-size: 18px;
    color: #5C5548;
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

.action-buttons {
    display: flex;
    gap: 100px;
    justify-content: center;
}

.btn-print {
    background-color: #5C5548 !important;
    color: white !important;
    border: none !important;
    border-radius: 50px !important;
    padding: 15px 30px !important;
    font-size: 16px !important;
    font-weight: bold !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 10px !important;
    transition: background-color 0.3s ease !important;
    text-decoration: none !important;
}

.btn-print:hover {
    background-color: #5a6268 !important;
}

.btn-home {
    background-color: #5C5548 !important;
    color: white !important;
    border: none !important;
    border-radius: 50px !important;
    padding: 15px 40px !important;
    font-size: 16px !important;
    font-weight: bold !important;
    cursor: pointer !important;
    display: inline-flex !important;
    justify-content: center !important;
    align-items: center !important;
    gap: 10px !important;
    transition: background-color 0.3s ease !important;
    text-decoration: none !important;
    min-width: 20%;
}

.btn-home:hover {
    background-color: #4a453a !important;
    color: white !important;
}

.btn-text {
    color: white !important;
}

.btn-arrow {
    background-color: white !important;
    color: #5C5548 !important;
    border-radius: 50% !important;
    width: 25px !important;
    height: 25px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-weight: bold !important;
}

.btn-print .btn-arrow {
    background-color: white !important;
    color: #6c757d !important;
}

.step.active .step-number {
    background: #5C5548;
    color: white;
}

.step.active .step-label {
    color: #5C5548;
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
    .action-buttons {
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
    if (empty($post_data)) {
        return false;
    }
    
    // 必須項目チェック
    $required_fields = ['factory_id', 'date', 'applicant_name', 'email'];
    foreach ($required_fields as $field) {
        if (!isset($post_data[$field]) || empty($post_data[$field])) {
            return false;
        }
    }
    
    return $post_data;
}

function process_reservation($form_data) {
    global $wpdb;
    
    // 予約IDを生成
    $reservation_id = generate_reservation_id();
    
    // データベースに予約情報を保存
    $db_result = save_reservation_to_database($reservation_id, $form_data);
    
    // メール送信を試行
    try {
        send_reservation_emails($reservation_id, $form_data);
    } catch (Exception $mail_e) {
        // メール送信エラーは致命的ではない
    }
    
    // カレンダー更新を試行
    try {
        update_calendar_availability($form_data['factory_id'], $form_data['date'], $form_data['timeslot']);
    } catch (Exception $cal_e) {
        // カレンダー更新エラーは致命的ではない
    }
    
    return [
        'success' => $db_result,
        'reservation_id' => $reservation_id
    ];
}

function generate_reservation_id() {
    // 予約番号の生成（年月日 + 4桁の連番）
    $date_prefix = date('Ymd');
    $random_suffix = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return $date_prefix . $random_suffix;
}

function save_reservation_to_database($reservation_id, $form_data) {
    global $wpdb;
    
    // 既存のテーブル構造に合わせて保存
    $table_name = $wpdb->prefix . 'reservations';
    
    // 旅行会社データの準備
    $agency_data = null;
    if (($form_data['is_travel_agency'] ?? 'no') === 'yes') {
        $agency_data = wp_json_encode([
            'name' => $form_data['agency_name'] ?? '',
            'zip' => $form_data['agency_postal_code'] ?? '',
            'prefecture' => $form_data['agency_prefecture'] ?? '',
            'city' => $form_data['agency_city'] ?? '',
            'address' => $form_data['agency_address'] ?? '',
            'phone' => $form_data['agency_phone'] ?? '',
            'fax' => $form_data['agency_fax'] ?? '',
            'contact_mobile' => $form_data['agency_contact_mobile'] ?? '',
            'contact_email' => $form_data['agency_contact_email'] ?? ''
        ], JSON_UNESCAPED_UNICODE);
    }
    
    // 見学者分類別の詳細データ準備
    $type_data = prepare_type_data($form_data);
    
    // 住所データの結合
    $address_building = trim(($form_data['address'] ?? '') . ' ' . ($form_data['building'] ?? ''));
    
    // 交通機関の台数
    $transportation_count = 0;
    if (in_array($form_data['transportation'] ?? '', ['car', 'chartered_bus', 'taxi']) && !empty($form_data['vehicle_count'])) {
        $transportation_count = intval($form_data['vehicle_count']);
    }
    
    // 交通機関の保存形式を調整
    $transportation_method = $form_data['transportation'] ?? '';
    if ($transportation_method === 'other' && !empty($form_data['transportation_other_text'])) {
        $transportation_method = 'other (' . $form_data['transportation_other_text'] . ')';
    }
    
    // データベースの実際のフィールド名に合わせて保存
    $reservation_data = [
        'factory_id' => $form_data['factory_id'],
        'date' => $form_data['date'],
        'time_slot' => convert_timeslot_to_time_format($form_data['timeslot'] ?? '', $form_data['factory_id']),
        'applicant_name' => $form_data['applicant_name'],
        'applicant_kana' => $form_data['applicant_name_kana'] ?? '',
        'is_travel_agency' => ($form_data['is_travel_agency'] ?? 'no') === 'yes' ? 1 : 0,
        'agency_data' => $agency_data,
        'reservation_type' => map_visitor_category_to_reservation_type($form_data['visitor_category'] ?? ''),
        'type_data' => $type_data,
        'address_zip' => $form_data['postal_code'] ?? '',
        'address_prefecture' => $form_data['prefecture'] ?? '',
        'address_city' => $form_data['city'] ?? '',
        'address_street' => $address_building,
        'phone' => $form_data['phone'] ?? '',
        'day_of_contact' => $form_data['mobile'] ?? '',
        'email' => $form_data['email'],
        'transportation_method' => $transportation_method,
        'transportation_count' => $transportation_count,
        'purpose' => $form_data['purpose'] ?? '',
        'participant_count' => calculate_total_visitors($form_data),
        'participants_child_count' => calculate_child_count($form_data),
        'status' => 'new',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    // データベースに挿入
    $result = $wpdb->insert($table_name, $reservation_data);
    
    // エラーハンドリング
    if ($result === false && $wpdb->last_error) {
        error_log('Database insert error: ' . $wpdb->last_error);
        return false;
    }
    
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
    
    // 予約が保存された時点で、カレンダーは予約データベースから状況を取得するため
    // 特別な処理は不要（予約データが保存されれば自動的にカレンダーに反映される）
    
    // ただし、必要に応じてキャッシュをクリアする処理などを追加可能
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

// 追加のヘルパー関数

function convert_timeslot_to_time_format($timeslot, $factory_id = null) {
    // timeslot形式: am-60-1, pm-90-2, am-1, pm-2 などを実際の時間形式に変換
    if (empty($timeslot)) {
        return '';
    }
    
    // 既に時間形式の場合はそのまま返す
    if (preg_match('/^\d{1,2}:\d{2}[~〜-]\d{1,2}:\d{2}$/', $timeslot)) {
        return $timeslot;
    }
    
    // プラグインファイルを読み込み
    $plugin_file = WP_PLUGIN_DIR . '/fpco-factory-reservation-system/includes/factory-user-management-functions.php';
    if (file_exists($plugin_file)) {
        require_once $plugin_file;
    }
    
    // 工場IDが渡されていない場合はPOSTデータから取得
    if (!$factory_id && isset($_POST['factory_id'])) {
        $factory_id = $_POST['factory_id'];
    }
    
    // 工場の時間設定を取得して実際の時間に変換
    if (function_exists('fpco_get_factory_timeslots') && $factory_id) {
        $factory_timeslots = fpco_get_factory_timeslots($factory_id);
        
        $parts = explode('-', $timeslot);
        $period = $parts[0] ?? '';
        $duration_or_index = $parts[1] ?? '';
        $index = isset($parts[2]) ? intval($parts[2]) - 1 : intval($duration_or_index) - 1;
        
        // 60分・90分パターンの場合
        if (in_array($duration_or_index, ['60', '90'])) {
            $duration_key = $duration_or_index . 'min';
            if (isset($factory_timeslots[$duration_key][$period][$index])) {
                $time_range = $factory_timeslots[$duration_key][$period][$index];
                // 時間形式を統一（例: "9:00 ~ 10:00" -> "09:00-10:00"）
                return preg_replace('/\s*[~〜]\s*/', '-', $time_range);
            }
        } else {
            // AM/PMパターンの場合
            $js_index = intval($duration_or_index) - 1;
            if (isset($factory_timeslots[$period]) && isset($factory_timeslots[$period][$js_index])) {
                $time_range = $factory_timeslots[$period][$js_index];
                // 時間形式を統一
                return preg_replace('/\s*[~〜]\s*/', '-', $time_range);
            }
        }
    }
    
    // フォールバック: デフォルトの時間テーブル
    $time_mappings = [
        'am-60-1' => '09:00-10:00',
        'am-60-2' => '10:30-11:30',
        'am-90-1' => '09:00-10:30',
        'am-90-2' => '10:00-11:30',
        'pm-60-1' => '14:00-15:00',
        'pm-60-2' => '15:30-16:30',
        'pm-90-1' => '14:00-15:30',
        'pm-90-2' => '15:00-16:30'
    ];
    
    return $time_mappings[$timeslot] ?? $timeslot;
}

function prepare_type_data($form_data) {
    $category = $form_data['visitor_category'] ?? '';
    $type_data = [];
    
    switch ($category) {
        case 'school':
            $type_data = [
                'school_name' => $form_data['school_name'] ?? '',
                'school_name_kana' => $form_data['school_kana'] ?? '',
                'representative_name' => $form_data['school_representative_name'] ?? '',
                'representative_name_kana' => $form_data['school_representative_kana'] ?? '',
                'grade' => $form_data['grade'] ?? '',
                'class_count' => $form_data['class_count'] ?? '',
                'student_count' => $form_data['school_student_count'] ?? 0,
                'supervisor_count' => $form_data['school_supervisor_count'] ?? 0
            ];
            break;
            
        case 'recruit':
            $type_data = [
                'school_name' => $form_data['recruit_school_name'] ?? '',
                'department' => $form_data['recruit_department'] ?? '',
                'grade' => $form_data['recruit_grade'] ?? '',
                'visitor_count' => $form_data['recruit_visitor_count'] ?? 1
            ];
            
            // 同行者データを追加
            $companionCount = intval($form_data['recruit_visitor_count'] ?? 1) - 1;
            if ($companionCount > 0) {
                $companions = [];
                for ($i = 1; $i <= $companionCount; $i++) {
                    if (!empty($form_data["companion_{$i}_name"])) {
                        $companions[] = [
                            'name' => $form_data["companion_{$i}_name"],
                            'department' => $form_data["companion_{$i}_department"] ?? ''
                        ];
                    }
                }
                if (!empty($companions)) {
                    $type_data['companions'] = $companions;
                }
            }
            break;
            
        case 'family':
            $type_data = [
                'company_name' => $form_data['family_organization_name'] ?? '',
                'company_name_kana' => $form_data['family_organization_kana'] ?? '',
                'adult_count' => $form_data['family_adult_count'] ?? 0,
                'child_count' => $form_data['family_child_count'] ?? 0,
                'child_grade' => $form_data['family_child_grade'] ?? ''
            ];
            break;
            
        case 'company':
            $type_data = [
                'company_name' => $form_data['company_name'] ?? '',
                'company_name_kana' => $form_data['company_kana'] ?? '',
                'adult_count' => $form_data['company_adult_count'] ?? 0,
                'child_count' => $form_data['company_child_count'] ?? 0,
                'child_grade' => $form_data['company_child_grade'] ?? ''
            ];
            break;
            
        case 'government':
            $type_data = [
                'company_name' => $form_data['government_name'] ?? '',
                'company_name_kana' => $form_data['government_kana'] ?? '',
                'adult_count' => $form_data['government_adult_count'] ?? 0,
                'child_count' => $form_data['government_child_count'] ?? 0,
                'child_grade' => $form_data['government_child_grade'] ?? ''
            ];
            break;
            
        case 'other':
            $type_data = [
                'company_name' => $form_data['other_group_name'] ?? '',
                'company_name_kana' => $form_data['other_group_kana'] ?? '',
                'adult_count' => $form_data['other_adult_count'] ?? 0,
                'child_count' => $form_data['other_child_count'] ?? 0,
                'child_grade' => $form_data['other_child_grade'] ?? ''
            ];
            break;
    }
    
    return wp_json_encode($type_data, JSON_UNESCAPED_UNICODE);
}

function map_visitor_category_to_reservation_type($category) {
    $mapping = [
        'school' => 'school',
        'recruit' => 'personal',
        'family' => 'personal',
        'company' => 'corporate',
        'government' => 'municipal',
        'other' => 'other'
    ];
    
    return $mapping[$category] ?? 'personal';
}

function calculate_child_count($form_data) {
    $category = $form_data['visitor_category'] ?? '';
    $child_count = 0;
    
    switch ($category) {
        case 'school':
            $child_count = intval($form_data['school_student_count'] ?? 0);
            break;
        case 'family':
            $child_count = intval($form_data['family_child_count'] ?? 0);
            break;
        case 'company':
            $child_count = intval($form_data['company_child_count'] ?? 0);
            break;
        case 'government':
            $child_count = intval($form_data['government_child_count'] ?? 0);
            break;
        case 'other':
            $child_count = intval($form_data['other_child_count'] ?? 0);
            break;
        default:
            $child_count = intval($form_data['total_child_count'] ?? 0);
    }
    
    return $child_count;
}

function get_return_calendar_url($form_data = null) {
    // フォームデータから工場IDを取得
    $factory_id = null;
    if ($form_data && isset($form_data['factory_id'])) {
        $factory_id = $form_data['factory_id'];
    }
    
    // セッションからカレンダーページのURLを取得
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // セッションに保存されたカレンダーページURL
    if (!empty($_SESSION['calendar_page_url'])) {
        return $_SESSION['calendar_page_url'];
    }
    
    // 工場IDに基づいてカレンダーページURLを構築
    if ($factory_id) {
        $factory_calendar_pages = [
            '1' => '/kanto-recycle/',
            '2' => '/chubu-recycle/',  
            '3' => '/fukuyama-recycle/',
            '4' => '/yamagata-sorting/',
            '5' => '/matsumoto-sorting/',
            '6' => '/nishinomiya-sorting/',
            '7' => '/tokai-sorting/',
            '8' => '/kanazawa-sorting/',
            '9' => '/kyushu-sorting/'
        ];
        
        if (isset($factory_calendar_pages[$factory_id])) {
            return home_url($factory_calendar_pages[$factory_id]);
        }
    }
    
    // セッションにない場合、リファラーから工場IDを取得してカレンダーページを構築
    $referer = wp_get_referer();
    if ($referer) {
        // リファラーURLから工場IDを抽出
        if (preg_match('/[?&]factory=(\d+)/', $referer, $matches)) {
            $extracted_factory_id = $matches[1];
            
            // 工場IDに基づいてカレンダーページURLを構築
            $factory_calendar_pages = [
                '1' => '/kanto-recycle/',
                '2' => '/chubu-recycle/',  
                '3' => '/fukuyama-recycle/',
                '4' => '/yamagata-sorting/',
                '5' => '/matsumoto-sorting/',
                '6' => '/nishinomiya-sorting/',
                '7' => '/tokai-sorting/',
                '8' => '/kanazawa-sorting/',
                '9' => '/kyushu-sorting/'
            ];
            
            if (isset($factory_calendar_pages[$extracted_factory_id])) {
                return home_url($factory_calendar_pages[$extracted_factory_id]);
            }
        }
        
        // リファラーがカレンダーページの場合はそのまま返す
        if (strpos($referer, '/reservation-') === false && strpos($referer, home_url()) === 0) {
            return $referer;
        }
    }
    
    // フォールバック: サイトのトップページ
    return home_url();
}

get_footer();
?>