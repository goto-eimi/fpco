<?php
/**
 * Plugin Name: Reservation Management
 * Description: 予約の一覧、追加・編集画面
 * Version: 1.0
 */

// プラグインの直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CSSファイルの読み込み
 */
add_action('admin_enqueue_scripts', 'reservation_management_enqueue_scripts');

function reservation_management_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_reservation-management') {
        return;
    }
    
    wp_enqueue_style(
        'reservation-management-style',
        plugin_dir_url(__FILE__) . 'reservation-management.css',
        array(),
        '1.0'
    );
}

/**
 * 管理画面メニューを追加
 */
add_action('admin_menu', 'reservation_management_admin_menu');

function reservation_management_admin_menu() {
    // 現在のユーザーを取得
    $current_user = wp_get_current_user();
    
    // 管理者またはfactoryロールのユーザーのみメニューを表示
    $can_access = false;
    
    // 管理者チェック（ユーザーID：1またはuser_login：adminまたはmanage_options権限）
    if ($current_user->ID == 1 || $current_user->user_login == 'admin' || current_user_can('manage_options')) {
        $can_access = true;
    }
    
    // factoryロールチェック
    if (in_array('factory', $current_user->roles)) {
        $can_access = true;
    }
    
    // 工場が割り当てられているかチェック
    $assigned_factory = get_user_meta($current_user->ID, 'assigned_factory', true);
    if ($assigned_factory) {
        $can_access = true;
    }
    
    if ($can_access) {
        add_menu_page(
            '予約追加',
            '予約追加',
            'read',  // 権限を緩和
            'reservation-management',
            'reservation_management_admin_page',
            'dashicons-clipboard',
            31
        );
    }
}

/**
 * フォーム送信処理
 */
add_action('admin_init', 'handle_reservation_form_submission');

function handle_reservation_form_submission() {
    if (!isset($_POST['submit_reservation']) || !isset($_POST['reservation_nonce'])) {
        return;
    }
    
    // Nonceチェック
    if (!wp_verify_nonce($_POST['reservation_nonce'], 'reservation_form')) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック
    if (!current_user_can('read')) {
        wp_die('この操作を行う権限がありません。');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    
    // バリデーション
    $errors = validate_reservation_form($_POST);
    if (!empty($errors)) {
        set_transient('reservation_errors', $errors, 60);
        set_transient('reservation_form_data', $_POST, 60);
        wp_redirect(add_query_arg('error', '1', wp_get_referer()));
        exit;
    }
    
    // データの準備
    $time_slot = sanitize_text_field($_POST['visit_time_start']) . '-' . sanitize_text_field($_POST['visit_time_end']);
    
    // 旅行会社情報の処理
    $agency_data = null;
    if ($_POST['is_travel_agency'] === 'yes') {
        $agency_data = json_encode([
            'name' => sanitize_text_field($_POST['travel_agency_name']),
            'zip' => sanitize_text_field($_POST['travel_agency_zip']),
            'prefecture' => sanitize_text_field($_POST['travel_agency_prefecture']),
            'city' => sanitize_text_field($_POST['travel_agency_city']),
            'address' => sanitize_text_field($_POST['travel_agency_address']),
            'phone' => sanitize_text_field($_POST['travel_agency_phone']),
            'fax' => sanitize_text_field($_POST['travel_agency_fax']),
            'contact_mobile' => sanitize_text_field($_POST['contact_mobile']),
            'contact_email' => sanitize_email($_POST['contact_email'])
        ], JSON_UNESCAPED_UNICODE);
    }
    
    // 予約タイプごとのデータ処理
    $type_data = get_type_specific_data($_POST);
    
    // 交通手段の処理
    $transportation = sanitize_text_field($_POST['transportation']);
    $transportation_other = null;
    if ($transportation === 'other') {
        $transportation_other = sanitize_text_field($_POST['transportation_other_text']);
    }
    
    // データベースに保存
    $data = [
        'factory_id' => intval($_POST['factory_id']),
        'date' => sanitize_text_field($_POST['visit_date']),
        'time_slot' => $time_slot,
        'applicant_name' => sanitize_text_field($_POST['applicant_name']),
        'applicant_kana' => sanitize_text_field($_POST['applicant_kana']),
        'is_travel_agency' => $_POST['is_travel_agency'] === 'yes' ? 1 : 0,
        'agency_data' => $agency_data,
        'reservation_type' => sanitize_text_field($_POST['reservation_type']),
        'type_data' => $type_data,
        'address_zip' => sanitize_text_field($_POST['applicant_zip']),
        'address_prefecture' => sanitize_text_field($_POST['applicant_prefecture']),
        'address_city' => sanitize_text_field($_POST['applicant_city']),
        'address_street' => sanitize_text_field($_POST['applicant_address']),
        'phone' => sanitize_text_field($_POST['applicant_phone']),
        'day_of_contact' => sanitize_text_field($_POST['emergency_contact']),
        'email' => sanitize_email($_POST['applicant_email']),
        'transportation_method' => $transportation,
        'transportation_other' => $transportation_other,
        'transportation_count' => intval($_POST['vehicle_count']),
        'purpose' => sanitize_textarea_field($_POST['visit_purpose']),
        'participant_count' => intval($_POST['total_visitors']),
        'participants_child_count' => intval($_POST['elementary_visitors']),
        'status' => 'new'
    ];
    
    $format = [
        '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s',
        '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
        '%d', '%s', '%d', '%d', '%s'
    ];
    
    $result = $wpdb->insert($table_name, $data, $format);
    
    if ($result === false) {
        set_transient('reservation_error_message', 'データベースへの保存に失敗しました。', 60);
        wp_redirect(add_query_arg('error', '1', wp_get_referer()));
    } else {
        set_transient('reservation_success_message', '予約を正常に登録しました。予約番号: ' . $wpdb->insert_id, 60);
        wp_redirect(add_query_arg('success', '1', wp_get_referer()));
    }
    exit;
}

/**
 * バリデーション関数
 */
function validate_reservation_form($data) {
    $errors = [];
    
    // 必須フィールドのチェック
    $required_fields = [
        'factory_id' => '見学工場',
        'visit_date' => '見学日',
        'visit_time_start' => '見学開始時間',
        'visit_time_end' => '見学終了時間',
        'applicant_name' => '申込者氏名',
        'applicant_kana' => '申込者氏名(ふりがな)',
        'is_travel_agency' => '旅行会社かどうか',
        'reservation_type' => '予約タイプ',
        'applicant_zip' => '申込者郵便番号',
        'applicant_prefecture' => '申込者都道府県',
        'applicant_city' => '申込者市区町村',
        'applicant_phone' => '申込者電話番号',
        'emergency_contact' => '当日連絡先',
        'applicant_email' => '申込者メールアドレス',
        'transportation' => '交通機関',
        'vehicle_count' => '台数',
        'visit_purpose' => '見学目的',
        'total_visitors' => '見学者人数'
    ];
    
    foreach ($required_fields as $field => $label) {
        if (empty($data[$field])) {
            $errors[] = $label . 'は必須項目です。';
        }
    }
    
    // メールアドレスの形式チェック
    if (!empty($data['applicant_email']) && !is_email($data['applicant_email'])) {
        $errors[] = '正しいメールアドレスを入力してください。';
    }
    
    // 旅行会社の場合の追加チェック
    if ($data['is_travel_agency'] === 'yes') {
        $agency_required = [
            'travel_agency_name' => '旅行会社氏名',
            'travel_agency_prefecture' => '旅行会社都道府県',
            'travel_agency_city' => '旅行会社市区町村',
            'travel_agency_phone' => '旅行会社電話番号',
            'contact_email' => '担当者メールアドレス'
        ];
        
        foreach ($agency_required as $field => $label) {
            if (empty($data[$field])) {
                $errors[] = $label . 'は必須項目です。';
            }
        }
    }
    
    return $errors;
}

/**
 * 予約タイプごとのデータを取得
 */
function get_type_specific_data($data) {
    $type_data = [];
    
    switch ($data['reservation_type']) {
        case 'school':
            $type_data = [
                'school_name' => sanitize_text_field($data['school_name']),
                'school_name_kana' => sanitize_text_field($data['school_name_kana']),
                'representative_name' => sanitize_text_field($data['representative_name']),
                'representative_name_kana' => sanitize_text_field($data['representative_name_kana']),
                'grade' => intval($data['grade']),
                'class_count' => intval($data['class_count']),
                'student_count' => intval($data['student_count']),
                'supervisor_count' => intval($data['supervisor_count'])
            ];
            break;
            
        case 'student_recruit':
            $type_data = [
                'school_name' => sanitize_text_field($data['recruit_school_name']),
                'department' => sanitize_text_field($data['recruit_department']),
                'grade' => intval($data['recruit_grade']),
                'visitor_count' => intval($data['recruit_visitor_count'])
            ];
            
            // 同行者情報
            $companions = [];
            for ($i = 1; $i < intval($data['recruit_visitor_count']); $i++) {
                if (!empty($data["companion_name_$i"])) {
                    $companions[] = [
                        'name' => sanitize_text_field($data["companion_name_$i"]),
                        'department' => sanitize_text_field($data["companion_department_$i"])
                    ];
                }
            }
            if (!empty($companions)) {
                $type_data['companions'] = $companions;
            }
            break;
            
        case 'family':
        case 'company':
        case 'municipality':
        case 'other':
            $type_data = [
                'company_name' => sanitize_text_field($data['company_name']),
                'company_name_kana' => sanitize_text_field($data['company_name_kana']),
                'adult_count' => intval($data['adult_count']),
                'child_count' => intval($data['child_count'])
            ];
            
            if (!empty($data['child_grade'])) {
                $type_data['child_grade'] = sanitize_text_field($data['child_grade']);
            }
            break;
    }
    
    return json_encode($type_data, JSON_UNESCAPED_UNICODE);
}

/**
 * 予約管理画面の表示
 */
function reservation_management_admin_page() {
    global $wpdb;
    
    // メッセージ表示
    if (isset($_GET['success'])) {
        $message = get_transient('reservation_success_message');
        if ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            delete_transient('reservation_success_message');
        }
    }
    
    if (isset($_GET['error'])) {
        $errors = get_transient('reservation_errors');
        $error_message = get_transient('reservation_error_message');
        
        if ($errors) {
            echo '<div class="notice notice-error is-dismissible">';
            foreach ($errors as $error) {
                echo '<p>' . esc_html($error) . '</p>';
            }
            echo '</div>';
            delete_transient('reservation_errors');
        } elseif ($error_message) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
            delete_transient('reservation_error_message');
        }
    }
    
    ?>
    <div class="wrap">
        <h1>予約追加・編集</h1>
        
        <!-- 予約フォーム -->
        <div class="reservation-form-container">
            <!-- 予約内容 -->
            <div class="reservation-content">
                <div class="form-section-header with-border">
                    <h2 class="form-section-title">予約内容</h2>
                </div>
                <div class="form-section-content">
                    <form method="post" action="">
                        <?php wp_nonce_field('reservation_form', 'reservation_nonce'); ?>
                        <!-- 予約番号 -->
                        <div class="form-field">
                            <label for="reservation_number" class="form-label">
                                予約番号
                            </label>
                            <?php
                            // wp_reservationsテーブルから最新のIDを取得して次の番号を生成
                            $next_id = $wpdb->get_var("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$wpdb->prefix}reservations'");
                            if (!$next_id) {
                                // テーブルが空の場合は1から開始
                                $next_id = 1;
                            }
                            $reservation_number = $next_id;
                            ?>
                            <span><?php echo esc_html($reservation_number); ?></span>
                            <input type="hidden" name="reservation_number" value="<?php echo esc_attr($reservation_number); ?>">
                        </div>

                        <!-- 見学工場 -->
                        <div class="form-field">
                            <label for="factory_id" class="form-label">
                                見学工場 <span class="required">*</span>
                            </label>
                            <?php
                            $current_user = wp_get_current_user();
                            $assigned_factory = get_user_meta($current_user->ID, 'assigned_factory', true);
                            $is_factory_account = !current_user_can('manage_options') && $assigned_factory;
                            
                            $factories = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}factorys ORDER BY name");
                            ?>
                            <select name="factory_id" id="factory_id" class="form-select" <?php echo $is_factory_account ? 'disabled' : ''; ?>>
                                <option value="">選択してください</option>
                                <?php foreach ($factories as $factory) : ?>
                                    <option value="<?php echo esc_attr($factory->id); ?>" 
                                            <?php echo ($is_factory_account && $factory->id == $assigned_factory) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($factory->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($is_factory_account): ?>
                                <input type="hidden" name="factory_id" value="<?php echo esc_attr($assigned_factory); ?>">
                            <?php endif; ?>
                        </div>

                        <!-- 見学日 -->
                        <div class="form-field">
                            <label for="visit_date" class="form-label">
                                見学日 <span class="required">*</span>
                            </label>
                            <input type="date" name="visit_date" id="visit_date" class="form-input">
                        </div>

                        <!-- 見学時間帯 -->
                        <div class="form-field">
                            <label class="form-label">
                                見学時間帯 <span class="required">*</span>
                            </label>
                            <div class="time-range">
                                <input type="time" name="visit_time_start" id="visit_time_start" class="time-input">
                                <span>〜</span>
                                <input type="time" name="visit_time_end" id="visit_time_end" class="time-input">
                            </div>
                        </div>

                        <!-- 申込者氏名 -->
                        <div class="form-field">
                            <label for="applicant_name" class="form-label">
                                申込者氏名 <span class="required">*</span>
                            </label>
                            <input type="text" name="applicant_name" id="applicant_name" class="form-input">
                        </div>

                        <!-- 申込者氏名(ふりがな) -->
                        <div class="form-field">
                            <label for="applicant_kana" class="form-label">
                                申込者氏名(ふりがな) <span class="required">*</span>
                            </label>
                            <input type="text" name="applicant_kana" id="applicant_kana" class="form-input">
                        </div>

                        <!-- 旅行会社の方ですか -->
                        <div class="form-field">
                            <label class="form-label">
                                申込者様は旅行会社の方ですか？ <span class="required">*</span>
                            </label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="is_travel_agency" value="yes" id="travel_agency_yes"> はい
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="is_travel_agency" value="no" id="travel_agency_no"> いいえ
                                </label>
                            </div>
                        </div>

                        <!-- 旅行会社情報（「はい」の場合のみ表示） -->
                        <div id="travel_agency_fields" style="display: none;">
                            <!-- 旅行会社氏名 -->
                            <div class="form-field">
                                <label for="travel_agency_name" class="form-label">
                                    旅行会社氏名 <span class="required">*</span>
                                </label>
                                <input type="text" name="travel_agency_name" id="travel_agency_name" class="form-input">
                            </div>

                            <!-- 旅行会社住所 -->
                            <div class="form-field" style="align-items: flex-start;">
                                <label class="form-label" style="margin-top: 10px;">
                                    旅行会社住所 <span class="required">*</span>
                                </label>
                                <div class="address-fields">
                                    <!-- 郵便番号 -->
                                    <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                        <span style="margin-right: 5px;">〒</span>
                                        <input type="text" name="travel_agency_zip" id="travel_agency_zip" 
                                               placeholder="1234567" maxlength="7" class="form-input" style="width: 100px !important;" 
                                               oninput="searchAddress(this.value)">
                                        <span style="margin-left: 10px; font-size: 12px; color: #666;">郵便番号を入力すると住所が入力されます</span>
                                    </div>
                                    
                                    <!-- 県名 -->
                                    <div style="margin-bottom: 8px;">
                                        <select name="travel_agency_prefecture" id="travel_agency_prefecture" 
                                                class="form-select" style="width: 150px;">
                                            <option value="">都道府県を選択</option>
                                            <option value="北海道">北海道</option>
                                            <option value="青森県">青森県</option>
                                            <option value="岩手県">岩手県</option>
                                            <option value="宮城県">宮城県</option>
                                            <option value="秋田県">秋田県</option>
                                            <option value="山形県">山形県</option>
                                            <option value="福島県">福島県</option>
                                            <option value="茨城県">茨城県</option>
                                            <option value="栃木県">栃木県</option>
                                            <option value="群馬県">群馬県</option>
                                            <option value="埼玉県">埼玉県</option>
                                            <option value="千葉県">千葉県</option>
                                            <option value="東京都">東京都</option>
                                            <option value="神奈川県">神奈川県</option>
                                            <option value="新潟県">新潟県</option>
                                            <option value="富山県">富山県</option>
                                            <option value="石川県">石川県</option>
                                            <option value="福井県">福井県</option>
                                            <option value="山梨県">山梨県</option>
                                            <option value="長野県">長野県</option>
                                            <option value="岐阜県">岐阜県</option>
                                            <option value="静岡県">静岡県</option>
                                            <option value="愛知県">愛知県</option>
                                            <option value="三重県">三重県</option>
                                            <option value="滋賀県">滋賀県</option>
                                            <option value="京都府">京都府</option>
                                            <option value="大阪府">大阪府</option>
                                            <option value="兵庫県">兵庫県</option>
                                            <option value="奈良県">奈良県</option>
                                            <option value="和歌山県">和歌山県</option>
                                            <option value="鳥取県">鳥取県</option>
                                            <option value="島根県">島根県</option>
                                            <option value="岡山県">岡山県</option>
                                            <option value="広島県">広島県</option>
                                            <option value="山口県">山口県</option>
                                            <option value="徳島県">徳島県</option>
                                            <option value="香川県">香川県</option>
                                            <option value="愛媛県">愛媛県</option>
                                            <option value="高知県">高知県</option>
                                            <option value="福岡県">福岡県</option>
                                            <option value="佐賀県">佐賀県</option>
                                            <option value="長崎県">長崎県</option>
                                            <option value="熊本県">熊本県</option>
                                            <option value="大分県">大分県</option>
                                            <option value="宮崎県">宮崎県</option>
                                            <option value="鹿児島県">鹿児島県</option>
                                            <option value="沖縄県">沖縄県</option>
                                        </select>
                                    </div>
                                    
                                    <!-- 市区町村 -->
                                    <div style="margin-bottom: 8px;">
                                        <input type="text" name="travel_agency_city" id="travel_agency_city" 
                                               placeholder="市区町村" class="form-input" style="width: 200px;">
                                    </div>
                                    
                                    <!-- 番地・建物名 -->
                                    <div>
                                        <input type="text" name="travel_agency_address" id="travel_agency_address" 
                                               placeholder="番地・建物名" class="form-input">
                                    </div>
                                </div>
                            </div>

                            <!-- 旅行会社電話番号 -->
                            <div class="form-field">
                                <label for="travel_agency_phone" class="form-label">
                                    旅行会社電話番号 <span class="required">*</span>
                                </label>
                                <input type="tel" name="travel_agency_phone" id="travel_agency_phone" class="form-input">
                            </div>

                            <!-- 旅行会社FAX番号 -->
                            <div class="form-field">
                                <label for="travel_agency_fax" class="form-label">
                                    旅行会社FAX番号
                                </label>
                                <input type="tel" name="travel_agency_fax" id="travel_agency_fax" class="form-input">
                            </div>

                            <!-- 担当者携帯番号 -->
                            <div class="form-field">
                                <label for="contact_mobile" class="form-label">
                                    担当者携帯番号
                                </label>
                                <input type="tel" name="contact_mobile" id="contact_mobile" class="form-input">
                            </div>

                            <!-- 担当者メールアドレス -->
                            <div class="form-field">
                                <label for="contact_email" class="form-label">
                                    担当者メールアドレス <span class="required">*</span>
                                </label>
                                <input type="email" name="contact_email" id="contact_email" class="form-input">
                            </div>
                        </div>

                        <!-- 予約タイプ -->
                        <div class="form-field">
                            <label class="form-label">
                                予約タイプ <span class="required">*</span>
                            </label>
                            <div class="radio-group reservation-type-group">
                                <label class="radio-option">
                                    <input type="radio" name="reservation_type" value="school" id="reservation_type_school"> 小学校・中学校・大学
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="reservation_type" value="student_recruit" id="reservation_type_student_recruit"> 個人（大学生・高校生のリクルート）
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="reservation_type" value="family" id="reservation_type_family"> 個人・親子見学・ご家族など
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="reservation_type" value="company" id="reservation_type_company"> 企業（研修など）
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="reservation_type" value="municipality" id="reservation_type_municipality"> 自治体主体ツアーなど
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="reservation_type" value="other" id="reservation_type_other"> その他（グループ・団体）
                                </label>
                            </div>
                        </div>

                        <!-- 学校・団体情報（「小学校・中学校・大学」の場合のみ表示） -->
                        <div id="school_fields" style="display: none;">
                            <!-- 学校・団体名 -->
                            <div class="form-field">
                                <label for="school_name" class="form-label">
                                    学校・団体名 <span class="required">*</span>
                                </label>
                                <input type="text" name="school_name" id="school_name" class="form-input">
                            </div>

                            <!-- 学校・団体名(ふりがな) -->
                            <div class="form-field">
                                <label for="school_name_kana" class="form-label">
                                    学校・団体名(ふりがな) <span class="required">*</span>
                                </label>
                                <input type="text" name="school_name_kana" id="school_name_kana" class="form-input">
                            </div>

                            <!-- 代表者氏名 -->
                            <div class="form-field">
                                <label for="representative_name" class="form-label">
                                    代表者氏名
                                </label>
                                <input type="text" name="representative_name" id="representative_name" class="form-input">
                            </div>

                            <!-- 代表者氏名(ふりがな) -->
                            <div class="form-field">
                                <label for="representative_name_kana" class="form-label">
                                    代表者氏名(ふりがな)
                                </label>
                                <input type="text" name="representative_name_kana" id="representative_name_kana" class="form-input">
                            </div>

                            <!-- 学年 -->
                            <div class="form-field">
                                <label for="grade" class="form-label">
                                    学年 <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="grade" id="grade" class="form-input" style="width: 50px !important;" min="1" max="12">
                                    <span style="margin-left: 5px;">年生</span>
                                </div>
                            </div>

                            <!-- クラス数 -->
                            <div class="form-field">
                                <label for="class_count" class="form-label">
                                    クラス数 <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="class_count" id="class_count" class="form-input" style="width: 50px !important;" min="1">
                                    <span style="margin-left: 5px;">クラス</span>
                                </div>
                            </div>

                            <!-- 見学者人数(児童・生徒) -->
                            <div class="form-field">
                                <label for="student_count" class="form-label">
                                    見学者人数(児童・生徒) <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="student_count" id="student_count" class="form-input" style="width: 50px !important;" min="0">
                                    <span style="margin-left: 5px;">名</span>
                                </div>
                            </div>

                            <!-- 見学者人数(引率) -->
                            <div class="form-field">
                                <label for="supervisor_count" class="form-label">
                                    見学者人数(引率) <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="supervisor_count" id="supervisor_count" class="form-input" style="width: 50px !important;" min="0">
                                    <span style="margin-left: 5px;">名</span>
                                </div>
                            </div>
                        </div>

                        <!-- リクルート情報（「個人（大学生・高校生のリクルート）」の場合のみ表示） -->
                        <div id="recruit_fields" style="display: none;">
                            <!-- 学校名 -->
                            <div class="form-field">
                                <label for="recruit_school_name" class="form-label">
                                    学校名 <span class="required">*</span>
                                </label>
                                <input type="text" name="recruit_school_name" id="recruit_school_name" class="form-input">
                            </div>

                            <!-- 学部 -->
                            <div class="form-field">
                                <label for="recruit_department" class="form-label">
                                    学部 <span class="required">*</span>
                                </label>
                                <input type="text" name="recruit_department" id="recruit_department" class="form-input">
                            </div>

                            <!-- 学年 -->
                            <div class="form-field">
                                <label for="recruit_grade" class="form-label">
                                    学年 <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="recruit_grade" id="recruit_grade" class="form-input" style="width: 50px !important;" min="1" max="6">
                                    <span style="margin-left: 5px;">年生</span>
                                </div>
                            </div>

                            <!-- 見学者様人数 -->
                            <div class="form-field">
                                <label for="recruit_visitor_count" class="form-label">
                                    見学者様人数 <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="recruit_visitor_count" id="recruit_visitor_count" class="form-input" style="width: 50px !important;" min="1" onchange="updateCompanionFields()">
                                    <span style="margin-left: 5px;">名</span>
                                </div>
                            </div>

                            <!-- 同行者様情報（動的に表示） -->
                            <div id="companion_fields"></div>
                        </div>

                        <!-- 一般・企業情報（「個人・親子見学・ご家族など」「企業」「自治体主体」「その他」の場合のみ表示） -->
                        <div id="general_fields" style="display: none;">
                            <!-- 会社・団体名 -->
                            <div class="form-field">
                                <label for="company_name" class="form-label">
                                    会社・団体名 <span class="required">*</span>
                                </label>
                                <input type="text" name="company_name" id="company_name" class="form-input">
                            </div>

                            <!-- 会社・団体名(ふりがな) -->
                            <div class="form-field">
                                <label for="company_name_kana" class="form-label">
                                    会社・団体名(ふりがな) <span class="required">*</span>
                                </label>
                                <input type="text" name="company_name_kana" id="company_name_kana" class="form-input">
                            </div>

                            <!-- 見学者人数(大人) -->
                            <div class="form-field">
                                <label for="adult_count" class="form-label">
                                    見学者人数(大人) <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="adult_count" id="adult_count" class="form-input" style="width: 50px !important;" min="0">
                                    <span style="margin-left: 5px;">名</span>
                                </div>
                            </div>

                            <!-- 見学者人数(子ども) -->
                            <div class="form-field">
                                <label for="child_count" class="form-label">
                                    見学者人数(子ども) <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="child_count" id="child_count" class="form-input" style="width: 50px !important;" min="0" onchange="updateChildGradeFields()">
                                    <span style="margin-left: 5px;">名</span>
                                </div>
                            </div>

                            <!-- 学年（子ども人数が1人以上の場合のみ表示） -->
                            <div id="child_grade_field" style="display: none;">
                                <div class="form-field">
                                    <label for="child_grade" class="form-label">
                                        学年 <span class="required">*</span>
                                    </label>
                                    <input type="text" name="child_grade" id="child_grade" class="form-input" placeholder="例：小学1年生、小学3年生">
                                </div>
                            </div>
                        </div>

                        <!-- 申込者様住所 -->
                        <div class="form-field" style="align-items: flex-start;">
                            <label class="form-label" style="margin-top: 10px;">
                                申込者様住所 <span class="required">*</span>
                            </label>
                            <div class="address-fields">
                                <!-- 郵便番号 -->
                                <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                    <span style="margin-right: 5px;">〒</span>
                                    <input type="text" name="applicant_zip" id="applicant_zip" 
                                           placeholder="1234567" maxlength="7" class="form-input" style="width: 100px !important;" 
                                           oninput="searchApplicantAddress(this.value)">
                                    <span style="margin-left: 10px; font-size: 12px; color: #666;">郵便番号を入力すると住所が入力されます</span>
                                </div>
                                
                                <!-- 県名 -->
                                <div style="margin-bottom: 8px;">
                                    <select name="applicant_prefecture" id="applicant_prefecture" 
                                            class="form-select" style="width: 150px;">
                                        <option value="">都道府県を選択</option>
                                        <option value="北海道">北海道</option>
                                        <option value="青森県">青森県</option>
                                        <option value="岩手県">岩手県</option>
                                        <option value="宮城県">宮城県</option>
                                        <option value="秋田県">秋田県</option>
                                        <option value="山形県">山形県</option>
                                        <option value="福島県">福島県</option>
                                        <option value="茨城県">茨城県</option>
                                        <option value="栃木県">栃木県</option>
                                        <option value="群馬県">群馬県</option>
                                        <option value="埼玉県">埼玉県</option>
                                        <option value="千葉県">千葉県</option>
                                        <option value="東京都">東京都</option>
                                        <option value="神奈川県">神奈川県</option>
                                        <option value="新潟県">新潟県</option>
                                        <option value="富山県">富山県</option>
                                        <option value="石川県">石川県</option>
                                        <option value="福井県">福井県</option>
                                        <option value="山梨県">山梨県</option>
                                        <option value="長野県">長野県</option>
                                        <option value="岐阜県">岐阜県</option>
                                        <option value="静岡県">静岡県</option>
                                        <option value="愛知県">愛知県</option>
                                        <option value="三重県">三重県</option>
                                        <option value="滋賀県">滋賀県</option>
                                        <option value="京都府">京都府</option>
                                        <option value="大阪府">大阪府</option>
                                        <option value="兵庫県">兵庫県</option>
                                        <option value="奈良県">奈良県</option>
                                        <option value="和歌山県">和歌山県</option>
                                        <option value="鳥取県">鳥取県</option>
                                        <option value="島根県">島根県</option>
                                        <option value="岡山県">岡山県</option>
                                        <option value="広島県">広島県</option>
                                        <option value="山口県">山口県</option>
                                        <option value="徳島県">徳島県</option>
                                        <option value="香川県">香川県</option>
                                        <option value="愛媛県">愛媛県</option>
                                        <option value="高知県">高知県</option>
                                        <option value="福岡県">福岡県</option>
                                        <option value="佐賀県">佐賀県</option>
                                        <option value="長崎県">長崎県</option>
                                        <option value="熊本県">熊本県</option>
                                        <option value="大分県">大分県</option>
                                        <option value="宮崎県">宮崎県</option>
                                        <option value="鹿児島県">鹿児島県</option>
                                        <option value="沖縄県">沖縄県</option>
                                    </select>
                                </div>
                                
                                <!-- 市区町村 -->
                                <div style="margin-bottom: 8px;">
                                    <input type="text" name="applicant_city" id="applicant_city" 
                                           placeholder="市区町村" class="form-input" style="width: 200px;">
                                </div>
                                
                                <!-- 番地・建物名 -->
                                <div>
                                    <input type="text" name="applicant_address" id="applicant_address" 
                                           placeholder="番地・建物名" class="form-input">
                                </div>
                            </div>
                        </div>

                        <!-- 申込者様電話番号 -->
                        <div class="form-field">
                            <label for="applicant_phone" class="form-label">
                                申込者様電話番号 <span class="required">*</span>
                            </label>
                            <input type="tel" name="applicant_phone" id="applicant_phone" class="form-input">
                        </div>

                        <!-- 当日連絡先(携帯番号) -->
                        <div class="form-field">
                            <label for="emergency_contact" class="form-label">
                                当日連絡先(携帯番号) <span class="required">*</span>
                            </label>
                            <input type="tel" name="emergency_contact" id="emergency_contact" class="form-input">
                        </div>

                        <!-- 申込者様メールアドレス -->
                        <div class="form-field">
                            <label for="applicant_email" class="form-label">
                                申込者様メールアドレス <span class="required">*</span>
                            </label>
                            <input type="email" name="applicant_email" id="applicant_email" class="form-input">
                        </div>

                        <!-- ご利用の交通機関 -->
                        <div class="form-field">
                            <label class="form-label">
                                ご利用の交通機関 <span class="required">*</span>
                            </label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="transportation" value="car" id="transportation_car"> 車
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="transportation" value="chartered_bus" id="transportation_chartered_bus"> 貸切バス
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="transportation" value="local_bus" id="transportation_local_bus"> 路線バス
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="transportation" value="taxi" id="transportation_taxi"> タクシー
                                </label>
                                <label class="radio-option transportation-other-option">
                                    <input type="radio" name="transportation" value="other" id="transportation_other"> その他
                                    <input type="text" name="transportation_other_text" id="transportation_other_text" class="form-input transportation-other-input" disabled>
                                </label>
                            </div>
                        </div>

                        <!-- 台数 -->
                        <div class="form-field">
                            <label for="vehicle_count" class="form-label">
                                台数 <span class="required">*</span>
                            </label>
                            <div style="display: flex; align-items: center;">
                                <input type="number" name="vehicle_count" id="vehicle_count" class="form-input" style="width: 50px !important;" min="1">
                                <span style="margin-left: 5px;">台</span>
                            </div>
                        </div>

                        <!-- 見学目的 -->
                        <div class="form-field" style="align-items: flex-start;">
                            <label for="visit_purpose" class="form-label" style="margin-top: 10px;">
                                見学目的 <span class="required">*</span>
                            </label>
                            <textarea name="visit_purpose" id="visit_purpose" class="form-input" rows="4" style="width: 100%; resize: vertical;"></textarea>
                        </div>

                        <!-- 見学者人数 -->
                        <div class="form-field">
                            <label class="form-label">
                                見学者人数 <span class="required">*</span>
                            </label>
                            <div style="display: flex; align-items: center; gap: 20px;">
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="total_visitors" id="total_visitors" class="form-input" style="width: 50px !important;" min="1">
                                    <span style="margin-left: 5px;">名</span>
                                </div>
                                <div style="display: flex; align-items: center;">
                                    <span style="margin-right: 5px;">内小学生以下</span>
                                    <input type="number" name="elementary_visitors" id="elementary_visitors" class="form-input" style="width: 50px !important;" min="0">
                                    <span style="margin-left: 5px;">名</span>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 予約ステータス -->
            <div class="reservation-status">
                <div class="form-section-header">
                    <h2 class="form-section-title">予約ステータス</h2>
                </div>
                <div class="form-section-content">
                    <select name="reservation_status" id="reservation_status" class="form-select status">
                        <option value="new">新規受付</option>
                        <option value="checking">確認中</option>
                        <option value="approved">承認</option>
                        <option value="rejected">否認</option>
                        <option value="cancelled">キャンセル</option>
                    </select>
                    
                    <button type="button" id="create_reply_email" class="btn-reply-email">
                        返信メールを作成
                    </button>
                    
                    <div class="btn-register-container">
                        <button type="submit" name="submit_reservation" id="register_reservation" class="btn-register">
                            登録
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // 旅行会社情報の表示/非表示制御
    document.addEventListener('DOMContentLoaded', function() {
        const travelAgencyYes = document.getElementById('travel_agency_yes');
        const travelAgencyNo = document.getElementById('travel_agency_no');
        const travelAgencyFields = document.getElementById('travel_agency_fields');
        
        function toggleTravelAgencyFields() {
            if (travelAgencyYes.checked) {
                travelAgencyFields.style.display = 'block';
            } else {
                travelAgencyFields.style.display = 'none';
            }
        }
        
        travelAgencyYes.addEventListener('change', toggleTravelAgencyFields);
        travelAgencyNo.addEventListener('change', toggleTravelAgencyFields);
        
        // 学校・団体情報の表示/非表示制御
        const reservationTypeSchool = document.getElementById('reservation_type_school');
        const reservationTypeStudentRecruit = document.getElementById('reservation_type_student_recruit');
        const reservationTypeFamily = document.getElementById('reservation_type_family');
        const reservationTypeCompany = document.getElementById('reservation_type_company');
        const reservationTypeMunicipality = document.getElementById('reservation_type_municipality');
        const reservationTypeOther = document.getElementById('reservation_type_other');
        const schoolFields = document.getElementById('school_fields');
        const recruitFields = document.getElementById('recruit_fields');
        const generalFields = document.getElementById('general_fields');
        
        function toggleSchoolFields() {
            if (reservationTypeSchool.checked) {
                schoolFields.style.display = 'block';
            } else {
                schoolFields.style.display = 'none';
            }
        }
        
        function toggleRecruitFields() {
            if (reservationTypeStudentRecruit.checked) {
                recruitFields.style.display = 'block';
            } else {
                recruitFields.style.display = 'none';
            }
        }
        
        function toggleGeneralFields() {
            if (reservationTypeFamily.checked || reservationTypeCompany.checked || 
                reservationTypeMunicipality.checked || reservationTypeOther.checked) {
                generalFields.style.display = 'block';
            } else {
                generalFields.style.display = 'none';
            }
        }
        
        function toggleAllFields() {
            toggleSchoolFields();
            toggleRecruitFields();
            toggleGeneralFields();
        }
        
        reservationTypeSchool.addEventListener('change', toggleAllFields);
        reservationTypeStudentRecruit.addEventListener('change', toggleAllFields);
        reservationTypeFamily.addEventListener('change', toggleAllFields);
        reservationTypeCompany.addEventListener('change', toggleAllFields);
        reservationTypeMunicipality.addEventListener('change', toggleAllFields);
        reservationTypeOther.addEventListener('change', toggleAllFields);
        
        // 交通機関の「その他」入力フィールドの有効/無効制御
        const transportationOther = document.getElementById('transportation_other');
        const transportationCar = document.getElementById('transportation_car');
        const transportationCharteredBus = document.getElementById('transportation_chartered_bus');
        const transportationLocalBus = document.getElementById('transportation_local_bus');
        const transportationTaxi = document.getElementById('transportation_taxi');
        const transportationOtherText = document.getElementById('transportation_other_text');
        
        function toggleTransportationOtherField() {
            if (transportationOther.checked) {
                transportationOtherText.disabled = false;
                transportationOtherText.focus();
            } else {
                transportationOtherText.disabled = true;
                transportationOtherText.value = '';
            }
        }
        
        transportationOther.addEventListener('change', toggleTransportationOtherField);
        transportationCar.addEventListener('change', toggleTransportationOtherField);
        transportationCharteredBus.addEventListener('change', toggleTransportationOtherField);
        transportationLocalBus.addEventListener('change', toggleTransportationOtherField);
        transportationTaxi.addEventListener('change', toggleTransportationOtherField);
    });
    
    // 見学者人数に応じた同行者フィールドの動的表示
    function updateCompanionFields() {
        const visitorCount = parseInt(document.getElementById('recruit_visitor_count').value) || 0;
        const companionFields = document.getElementById('companion_fields');
        
        companionFields.innerHTML = '';
        
        if (visitorCount > 1) {
            for (let i = 1; i < visitorCount; i++) {
                const companionDiv = document.createElement('div');
                companionDiv.className = 'form-field';
                companionDiv.innerHTML = `
                    <label class="form-label" style="align-items: flex-start;">
                        同行者様${i}
                    </label>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <div>
                            <label class="form-label" style="margin-right: 45px;">氏名 <span class="required">*</span></label>
                            <input type="text" name="companion_name_${i}" id="companion_name_${i}" class="form-input" style="width: 215px !important;" required>
                        </div>
                        <div>
                            <label class="form-label" style="margin-right: 45px;">学部 <span class="required">*</span></label>
                            <input type="text" name="companion_department_${i}" id="companion_department_${i}" class="form-input" style="width: 215px !important;" required>
                        </div>
                    </div>
                `;
                companionFields.appendChild(companionDiv);
            }
        }
    }
    
    // 子ども人数に応じた学年フィールドの動的表示
    function updateChildGradeFields() {
        const childCount = parseInt(document.getElementById('child_count').value) || 0;
        const childGradeField = document.getElementById('child_grade_field');
        
        if (childCount >= 1) {
            childGradeField.style.display = 'block';
        } else {
            childGradeField.style.display = 'none';
        }
    }
    
    // 申込者様住所の郵便番号から住所を自動入力する関数
    function searchApplicantAddress(zipcode) {
        if (zipcode.length === 7 && /^\d{7}$/.test(zipcode)) {
            fetch('https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + zipcode)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 200 && data.results) {
                        const result = data.results[0];
                        
                        const prefectureSelect = document.getElementById('applicant_prefecture');
                        prefectureSelect.value = result.address1;
                        
                        const cityField = document.getElementById('applicant_city');
                        cityField.value = result.address2 + result.address3;
                        
                        console.log('申込者様住所が自動入力されました');
                    } else {
                        console.log('該当する住所が見つかりませんでした');
                    }
                })
                .catch(error => {
                    console.log('住所検索エラー:', error);
                });
        }
    }
    
    // 郵便番号から住所を自動入力する関数
    function searchAddress(zipcode) {
        if (zipcode.length === 7 && /^\d{7}$/.test(zipcode)) {
            fetch('https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + zipcode)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 200 && data.results) {
                        const result = data.results[0];
                        
                        const prefectureSelect = document.getElementById('travel_agency_prefecture');
                        prefectureSelect.value = result.address1;
                        
                        const cityField = document.getElementById('travel_agency_city');
                        cityField.value = result.address2 + result.address3;
                        
                        console.log('住所が自動入力されました');
                    } else {
                        console.log('該当する住所が見つかりませんでした');
                    }
                })
                .catch(error => {
                    console.log('住所検索エラー:', error);
                });
        }
    }
    </script>
    <?php
}