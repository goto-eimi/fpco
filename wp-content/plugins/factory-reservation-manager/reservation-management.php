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
 * 予約ステータス定数
 */
define('RESERVATION_STATUS_NEW', 'new');           // 新規受付
define('RESERVATION_STATUS_PENDING', 'pending');   // 確認中  
define('RESERVATION_STATUS_APPROVED', 'approved'); // 承認
define('RESERVATION_STATUS_REJECTED', 'rejected'); // 否認
define('RESERVATION_STATUS_CANCELLED', 'cancelled'); // キャンセル

/**
 * 有効なステータス値を取得
 */
function get_valid_reservation_statuses() {
    return [
        RESERVATION_STATUS_NEW,
        RESERVATION_STATUS_PENDING,
        RESERVATION_STATUS_APPROVED,
        RESERVATION_STATUS_REJECTED,
        RESERVATION_STATUS_CANCELLED
    ];
}

/**
 * ステータス表示名を取得
 */
function get_reservation_status_label($status) {
    $labels = [
        RESERVATION_STATUS_NEW => '新規受付',
        RESERVATION_STATUS_PENDING => '確認中',
        RESERVATION_STATUS_APPROVED => '承認',
        RESERVATION_STATUS_REJECTED => '否認',
        RESERVATION_STATUS_CANCELLED => 'キャンセル'
    ];
    return isset($labels[$status]) ? $labels[$status] : $status;
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
            29  // 工場カレンダーメニューの前に配置
        );
    }
}

/**
 * フォーム送信処理
 */

function handle_reservation_form_submission() {
    if (!isset($_POST['submit_reservation']) || !isset($_POST['reservation_nonce'])) {
        return ['success' => false, 'errors' => []];
    }
    
    // Nonceチェック
    if (!wp_verify_nonce($_POST['reservation_nonce'], 'reservation_form')) {
        return ['success' => false, 'errors' => ['セキュリティチェックに失敗しました。']];
    }
    
    // 権限チェック
    if (!current_user_can('read')) {
        return ['success' => false, 'errors' => ['この操作を行う権限がありません。']];
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    
    // 編集モードかどうかチェック
    $is_edit_mode = isset($_POST['reservation_id']) && !empty($_POST['reservation_id']);
    $reservation_id = $is_edit_mode ? intval($_POST['reservation_id']) : null;
    
    // バリデーション
    $validation_result = validate_reservation_form($_POST);
    
    if (!empty($validation_result['errors'])) {
        return [
            'success' => false, 
            'errors' => $validation_result['errors'],
            'field_errors' => $validation_result['field_errors']
        ];
    }
    
    // データの準備
    // 時間の組み立て
    $start_hour = str_pad(sanitize_text_field($_POST['visit_time_start_hour'] ?? ''), 2, '0', STR_PAD_LEFT);
    $start_minute = str_pad(sanitize_text_field($_POST['visit_time_start_minute'] ?? ''), 2, '0', STR_PAD_LEFT);
    $end_hour = str_pad(sanitize_text_field($_POST['visit_time_end_hour'] ?? ''), 2, '0', STR_PAD_LEFT);
    $end_minute = str_pad(sanitize_text_field($_POST['visit_time_end_minute'] ?? ''), 2, '0', STR_PAD_LEFT);
    
    $visit_time_start = $start_hour . ':' . $start_minute;
    $visit_time_end = $end_hour . ':' . $end_minute;
    $time_slot = $visit_time_start . '-' . $visit_time_end;
    
    // 旅行会社情報の処理
    $agency_data = null;
    if (isset($_POST['is_travel_agency']) && $_POST['is_travel_agency'] === 'yes') {
        $agency_data = json_encode([
            'name' => sanitize_text_field($_POST['travel_agency_name'] ?? ''),
            'zip' => sanitize_text_field($_POST['travel_agency_zip'] ?? ''),
            'prefecture' => sanitize_text_field($_POST['travel_agency_prefecture'] ?? ''),
            'city' => sanitize_text_field($_POST['travel_agency_city'] ?? ''),
            'address' => sanitize_text_field($_POST['travel_agency_address'] ?? ''),
            'phone' => sanitize_text_field($_POST['travel_agency_phone'] ?? ''),
            'fax' => sanitize_text_field($_POST['travel_agency_fax'] ?? ''),
            'contact_mobile' => sanitize_text_field($_POST['contact_mobile'] ?? ''),
            'contact_email' => sanitize_email($_POST['contact_email'] ?? '')
        ], JSON_UNESCAPED_UNICODE);
    }
    
    // 予約タイプごとのデータ処理
    $type_data = get_type_specific_data($_POST);
    
    // 交通手段の処理（DBのenumに合わせる: car, bus, taxi, other）
    $transportation_mapping = [
        'car' => 'car',
        'chartered_bus' => 'bus',
        'local_bus' => 'bus', 
        'taxi' => 'taxi',
        'other' => 'other'
    ];
    
    $transportation_input = isset($_POST['transportation']) ? sanitize_text_field($_POST['transportation']) : 'other';
    $transportation = isset($transportation_mapping[$transportation_input]) ? $transportation_mapping[$transportation_input] : 'other';
    
    // 交通手段がその他の場合、詳細をtype_dataに含める
    $transportation_other_text = '';
    if ($transportation_input === 'other' && isset($_POST['transportation_other_text'])) {
        $transportation_other_text = sanitize_text_field($_POST['transportation_other_text'] ?? '');
    }

    // ステータス値の妥当性チェック
    $valid_statuses = get_valid_reservation_statuses();
    $status_input = isset($_POST['reservation_status']) ? sanitize_text_field($_POST['reservation_status']) : RESERVATION_STATUS_NEW;
    $status = in_array($status_input, $valid_statuses) ? $status_input : RESERVATION_STATUS_NEW;
    
    // 実際のテーブル構造に合わせたデータ
    $data = [
        'factory_id' => intval($_POST['factory_id'] ?? 0),
        'date' => sanitize_text_field($_POST['visit_date'] ?? ''),
        'time_slot' => $time_slot,
        'applicant_name' => sanitize_text_field($_POST['applicant_name'] ?? ''),
        'applicant_kana' => sanitize_text_field($_POST['applicant_kana'] ?? ''),
        'is_travel_agency' => (isset($_POST['is_travel_agency']) && $_POST['is_travel_agency'] === 'yes') ? 1 : 0,
        'agency_data' => $agency_data,
        'reservation_type' => get_reservation_type_enum($_POST),
        'type_data' => $type_data,
        'address_zip' => sanitize_text_field($_POST['applicant_zip'] ?? ''),
        'address_prefecture' => sanitize_text_field($_POST['applicant_prefecture'] ?? ''),
        'address_city' => sanitize_text_field($_POST['applicant_city'] ?? ''),
        'address_street' => sanitize_text_field($_POST['applicant_address'] ?? ''),
        'phone' => sanitize_text_field($_POST['applicant_phone'] ?? ''),
        'day_of_contact' => sanitize_text_field($_POST['emergency_contact'] ?? ''),
        'email' => sanitize_email($_POST['applicant_email'] ?? ''),
        'transportation_method' => $transportation,
        'transportation_count' => intval($_POST['vehicle_count'] ?? 0),
        'purpose' => sanitize_textarea_field($_POST['visit_purpose'] ?? ''),
        'participant_count' => intval($_POST['total_visitors'] ?? 0),
        'participants_child_count' => intval($_POST['elementary_visitors'] ?? 0),
        'status' => $status
    ];
    
    $format = [
        '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', 
        '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', 
        '%s', '%d', '%d', '%s'
    ];
    
    if ($is_edit_mode) {
        // 更新処理
        $result = $wpdb->update(
            $table_name, 
            $data, 
            ['id' => $reservation_id],
            $format,
            ['%d']
        );
        
        if ($result === false) {
            return ['success' => false, 'errors' => ['データベースへの更新に失敗しました。']];
        } else {
            return ['success' => true, 'message' => '予約を正常に更新しました。予約番号: ' . $reservation_id];
        }
    } else {
        // 新規登録処理
        $result = $wpdb->insert($table_name, $data, $format);
        
        if ($result === false) {
            return ['success' => false, 'errors' => ['データベースへの保存に失敗しました。']];
        } else {
            return ['success' => true, 'message' => '予約を正常に登録しました。予約番号: ' . $wpdb->insert_id];
        }
    }
}

/**
 * バリデーション関数
 */
function validate_reservation_form($data) {
    $errors = [];
    $field_errors = [];
    
    // フィールドエラーを追加するヘルパー関数
    $add_field_error = function($field, $message) use (&$errors, &$field_errors) {
        $errors[] = $message;
        $field_errors[$field] = $message;
    };
    
    // 必須フィールドのチェック
    $required_fields = [
        'factory_id' => '見学工場',
        'visit_date' => '見学日',
        'visit_time_start_hour' => '見学開始時間（時）',
        'visit_time_start_minute' => '見学開始時間（分）',
        'visit_time_end_hour' => '見学終了時間（時）',
        'visit_time_end_minute' => '見学終了時間（分）',
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
        'visit_purpose' => '見学目的',
        'total_visitors' => '見学者人数'
    ];
    
    foreach ($required_fields as $field => $label) {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            $add_field_error($field, $label . 'は必須項目です。');
        }
    }

    // ステータス値の妥当性チェック
    $valid_statuses = get_valid_reservation_statuses();
    if (isset($_POST['reservation_status']) && !in_array($_POST['reservation_status'], $valid_statuses)) {
        $add_field_error('reservation_status', '無効な予約ステータスが選択されています。');
    }

    // 台数の条件付きバリデーション（車、貸切バス、タクシーの場合のみ必須）
    if (isset($data['transportation']) && in_array($data['transportation'], ['car', 'chartered_bus', 'taxi'])) {
        if (!isset($data['vehicle_count']) || $data['vehicle_count'] === '' || $data['vehicle_count'] === null) {
            $add_field_error('vehicle_count', '台数は必須項目です。');
        }
    }
    
    // 日付の形式・範囲チェック
    if (!empty($data['visit_date'])) {
        $visit_date = $data['visit_date'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visit_date)) {
            $add_field_error('visit_date', '見学日の形式が正しくありません。');
        } else {
            $date_obj = DateTime::createFromFormat('Y-m-d', $visit_date);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $visit_date) {
                $add_field_error('visit_date', '見学日が正しくありません。');
            } else {
                $today = new DateTime();
                if ($date_obj < $today) {
                    $add_field_error('visit_date', '見学日は今日以降の日付を選択してください。');
                }
            }
        }
    }
    
    // 時間の形式・範囲チェック
    $start_hour = isset($data['visit_time_start_hour']) ? intval($data['visit_time_start_hour']) : null;
    $start_minute = isset($data['visit_time_start_minute']) ? intval($data['visit_time_start_minute']) : null;
    $end_hour = isset($data['visit_time_end_hour']) ? intval($data['visit_time_end_hour']) : null;
    $end_minute = isset($data['visit_time_end_minute']) ? intval($data['visit_time_end_minute']) : null;
    
    // 時間の範囲チェック
    if ($start_hour !== null && ($start_hour < 0 || $start_hour > 23)) {
        $add_field_error('visit_time_start_hour', '開始時間（時）は0〜23の範囲で入力してください。');
    }
    if ($start_minute !== null && ($start_minute < 0 || $start_minute > 59)) {
        $add_field_error('visit_time_start_minute', '開始時間（分）は0〜59の範囲で入力してください。');
    }
    if ($end_hour !== null && ($end_hour < 0 || $end_hour > 23)) {
        $add_field_error('visit_time_end_hour', '終了時間（時）は0〜23の範囲で入力してください。');
    }
    if ($end_minute !== null && ($end_minute < 0 || $end_minute > 59)) {
        $add_field_error('visit_time_end_minute', '終了時間（分）は0〜59の範囲で入力してください。');
    }
    
    // 開始時間と終了時間の比較
    if ($start_hour !== null && $start_minute !== null && $end_hour !== null && $end_minute !== null) {
        $start_total_minutes = $start_hour * 60 + $start_minute;
        $end_total_minutes = $end_hour * 60 + $end_minute;
        
        if ($start_total_minutes >= $end_total_minutes) {
            $add_field_error('visit_time_end_hour', '終了時間は開始時間よりも後の時間を入力してください。');
        }
    }
    
    // 郵便番号の形式チェック
    if (!empty($data['applicant_zip'])) {
        if (!preg_match('/^\d{7}$/', $data['applicant_zip'])) {
            $add_field_error('applicant_zip', '郵便番号は7桁の数字で入力してください。');
        }
    }
    
    // 電話番号の形式チェック
    if (!empty($data['applicant_phone'])) {
        if (!preg_match('/^[\d-]+$/', $data['applicant_phone'])) {
            $add_field_error('applicant_phone', '電話番号は数字とハイフンのみで入力してください。');
        }
    }
    
    if (!empty($data['emergency_contact'])) {
        if (!preg_match('/^[\d-]+$/', $data['emergency_contact'])) {
            $add_field_error('emergency_contact', '当日連絡先は数字とハイフンのみで入力してください。');
        }
    }
    
    // メールアドレスの形式チェック
    if (!empty($data['applicant_email']) && !is_email($data['applicant_email'])) {
        $add_field_error('applicant_email', '正しいメールアドレスを入力してください。');
    }
    
    // 数値フィールドのチェック
    $numeric_fields = [
        'vehicle_count' => '台数',
        'total_visitors' => '見学者人数',
        'elementary_visitors' => '小学生以下人数'
    ];
    
    foreach ($numeric_fields as $field => $label) {
        if (!empty($data[$field])) {
            if (!is_numeric($data[$field]) || intval($data[$field]) < 0) {
                $add_field_error($field, $label . 'は0以上の数値で入力してください。');
            }
        }
    }
    
    // 見学者人数の整合性チェック
    if (!empty($data['total_visitors']) && !empty($data['elementary_visitors'])) {
        if (intval($data['elementary_visitors']) > intval($data['total_visitors'])) {
            $add_field_error('elementary_visitors', '小学生以下の人数は見学者人数を超えることはできません。');
        }
    }
    
    // 交通機関「その他」の場合の入力チェック
    if (isset($data['transportation']) && $data['transportation'] === 'other' && 
        (!isset($data['transportation_other_text']) || $data['transportation_other_text'] === '' || $data['transportation_other_text'] === null)) {
        $add_field_error('transportation_other_text', '交通機関で「その他」を選択した場合は、内容を入力してください。');
    }
    
    // 旅行会社の場合の追加チェック
    if (isset($data['is_travel_agency']) && $data['is_travel_agency'] === 'yes') {
        $agency_required = [
            'travel_agency_name' => '旅行会社氏名',
            'travel_agency_prefecture' => '旅行会社都道府県',
            'travel_agency_city' => '旅行会社市区町村',
            'travel_agency_phone' => '旅行会社電話番号',
            'contact_email' => '担当者メールアドレス'
        ];
        
        foreach ($agency_required as $field => $label) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $add_field_error($field, $label . 'は必須項目です。');
            }
        }
        
        // 旅行会社の郵便番号チェック
        if (!empty($data['travel_agency_zip'])) {
            if (!preg_match('/^\d{7}$/', $data['travel_agency_zip'])) {
                $add_field_error('travel_agency_zip', '旅行会社の郵便番号は7桁の数字で入力してください。');
            }
        }
        
        // 旅行会社の電話番号チェック
        if (!empty($data['travel_agency_phone'])) {
            if (!preg_match('/^[\d-]+$/', $data['travel_agency_phone'])) {
                $add_field_error('travel_agency_phone', '旅行会社の電話番号は数字とハイフンのみで入力してください。');
            }
        }
        
        // 担当者メールアドレスの形式チェック
        if (!empty($data['contact_email']) && !is_email($data['contact_email'])) {
            $add_field_error('contact_email', '担当者メールアドレスの形式が正しくありません。');
        }
    }
    
    // 予約タイプごとの必須フィールドチェック
    $type_validation = validate_reservation_type_fields($data);
    $errors = array_merge($errors, $type_validation['errors']);
    $field_errors = array_merge($field_errors, $type_validation['field_errors']);
    
    return ['errors' => $errors, 'field_errors' => $field_errors];
}

/**
 * 予約タイプごとの必須フィールドチェック
 */
function validate_reservation_type_fields($data) {
    $errors = [];
    $field_errors = [];
    
    if (!isset($data['reservation_type'])) {
        return ['errors' => $errors, 'field_errors' => $field_errors];
    }
    
    $type = $data['reservation_type'];
    
    // フィールドエラーを追加するヘルパー関数
    $add_field_error = function($field, $message) use (&$errors, &$field_errors) {
        $errors[] = $message;
        $field_errors[$field] = $message;
    };
    
    switch ($type) {
        case 'school':
            $school_required = [
                'school_name' => '学校・団体名',
                'school_name_kana' => '学校・団体名(ふりがな)',
                'grade' => '学年',
                'class_count' => 'クラス数',
                'student_count' => '見学者人数(児童・生徒)',
                'supervisor_count' => '見学者人数(引率)'
            ];
            
            foreach ($school_required as $field => $label) {
                if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                    $add_field_error($field, $label . 'は必須項目です。');
                }
            }
            
            // 数値フィールドのチェック
            if (!empty($data['grade']) && (!is_numeric($data['grade']) || intval($data['grade']) < 1 || intval($data['grade']) > 12)) {
                $add_field_error('grade', '学年は1〜12の数値で入力してください。');
            }
            
            if (!empty($data['class_count']) && (!is_numeric($data['class_count']) || intval($data['class_count']) < 1)) {
                $add_field_error('class_count', 'クラス数は1以上の数値で入力してください。');
            }
            
            break;
            
        case 'student_recruit':
            $recruit_required = [
                'recruit_school_name' => '学校名',
                'recruit_department' => '学部',
                'recruit_grade' => '学年',
                'recruit_visitor_count' => '見学者様人数'
            ];
            
            foreach ($recruit_required as $field => $label) {
                if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                    $add_field_error($field, $label . 'は必須項目です。');
                }
            }
            
            // 同行者情報のチェック
            if (!empty($data['recruit_visitor_count'])) {
                $visitor_count = intval($data['recruit_visitor_count']);
                if ($visitor_count < 1 || $visitor_count > 10) {
                    $add_field_error('recruit_visitor_count', '見学者様人数は1〜10人の範囲で入力してください。');
                }
                
                // 同行者情報の必須チェック
                for ($i = 1; $i < $visitor_count; $i++) {
                    if (!isset($data["companion_name_$i"]) || $data["companion_name_$i"] === '' || $data["companion_name_$i"] === null) {
                        $add_field_error("companion_name_$i", "同行者様{$i}の氏名は必須項目です。");
                    }
                    if (!isset($data["companion_department_$i"]) || $data["companion_department_$i"] === '' || $data["companion_department_$i"] === null) {
                        $add_field_error("companion_department_$i", "同行者様{$i}の学部は必須項目です。");
                    }
                }
            }
            
            break;
            
        case 'family':
        case 'company':
        case 'municipality':
        case 'other':
            $general_required = [
                'company_name' => '会社・団体名',
                'company_name_kana' => '会社・団体名(ふりがな)',
                'adult_count' => '見学者人数(大人)',
                'child_count' => '見学者人数(子ども)'
            ];
            
            foreach ($general_required as $field => $label) {
                if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                    $add_field_error($field, $label . 'は必須項目です。');
                }
            }
            
            // 子どもの学年チェック
            if (!empty($data['child_count']) && intval($data['child_count']) > 0) {
                if (!isset($data['child_grade']) || $data['child_grade'] === '' || $data['child_grade'] === null) {
                    $add_field_error('child_grade', '子どもがいる場合は学年の入力が必要です。');
                }
            }
            
            break;
    }
    
    return ['errors' => $errors, 'field_errors' => $field_errors];
}

/**
 * 予約タイプをDBのenumに変換
 */
function get_reservation_type_enum($data) {
    $type_mapping = [
        'school' => 'school',
        'student_recruit' => 'personal',
        'family' => 'personal',
        'company' => 'corporate',
        'municipality' => 'municipal',
        'other' => 'other'
    ];
    
    $input_type = isset($data['reservation_type']) ? sanitize_text_field($data['reservation_type']) : 'other';
    return isset($type_mapping[$input_type]) ? $type_mapping[$input_type] : 'other';
}

/**
 * 予約タイプごとのデータを取得
 */
function get_type_specific_data($data) {
    $type_data = [];
    
    // 交通機関の「その他」詳細を含める
    if (isset($data['transportation']) && $data['transportation'] === 'other' && 
        isset($data['transportation_other_text']) && !empty($data['transportation_other_text'])) {
        $type_data['transportation_other_detail'] = sanitize_text_field($data['transportation_other_text']);
    }
    
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
 * フォームフィールドの値を取得するヘルパー関数
 */
function get_form_value($field_name, $form_data, $default = '') {
    return isset($form_data[$field_name]) ? esc_attr($form_data[$field_name] ?? '') : $default;
}

/**
 * ラジオボタンの選択状態を取得するヘルパー関数
 */
function is_radio_checked($field_name, $value, $form_data) {
    return isset($form_data[$field_name]) && $form_data[$field_name] === $value ? 'checked' : '';
}

/**
 * セレクトボックスの選択状態を取得するヘルパー関数
 */
function is_option_selected($field_name, $value, $form_data, $default = '') {
    if (isset($form_data[$field_name])) {
        return $form_data[$field_name] === $value ? 'selected' : '';
    }
    // フォームデータがない場合はデフォルト値をチェック
    return $value === $default ? 'selected' : '';
}

/**
 * フィールドごとのエラーメッセージを表示するヘルパー関数
 */
function display_field_error($field_name, $field_errors) {
    if (isset($field_errors[$field_name])) {
        echo '<div class="field-error">' . esc_html($field_errors[$field_name] ?? '') . '</div>';
    }
}

/**
 * フィールドにエラークラスを追加するヘルパー関数
 */
function get_field_error_class($field_name, $field_errors) {
    return isset($field_errors[$field_name]) ? 'error-field' : '';
}

/**
 * 予約データをフォームデータに変換
 */
function convert_reservation_to_form_data($reservation) {
    $form_data = [];
    
    // 基本情報
    $form_data['factory_id'] = $reservation['factory_id'] ?? '';
    $form_data['visit_date'] = $reservation['date'] ?? '';
    $form_data['reservation_status'] = $reservation['status'] ?? 'new';
    
    // 時刻を分解
    if (!empty($reservation['time_slot'])) {
        if (preg_match('/(\d+):(\d+)-(\d+):(\d+)/', $reservation['time_slot'], $matches)) {
            $form_data['visit_time_start_hour'] = $matches[1];
            $form_data['visit_time_start_minute'] = $matches[2];
            $form_data['visit_time_end_hour'] = $matches[3];
            $form_data['visit_time_end_minute'] = $matches[4];
        }
    }
    
    // 申込者情報
    $form_data['applicant_name'] = $reservation['applicant_name'] ?? '';
    $form_data['applicant_kana'] = $reservation['applicant_kana'] ?? '';
    $form_data['applicant_zip'] = $reservation['address_zip'] ?? '';
    $form_data['applicant_prefecture'] = $reservation['address_prefecture'] ?? '';
    $form_data['applicant_city'] = $reservation['address_city'] ?? '';
    $form_data['applicant_address'] = $reservation['address_street'] ?? '';
    $form_data['applicant_phone'] = $reservation['phone'] ?? '';
    $form_data['emergency_contact'] = $reservation['day_of_contact'] ?? '';
    $form_data['applicant_email'] = $reservation['email'] ?? '';
    
    // 旅行会社情報
    $form_data['is_travel_agency'] = ($reservation['is_travel_agency'] ?? 0) ? 'yes' : 'no';
    if (!empty($reservation['agency_data'])) {
        $agency_data = json_decode($reservation['agency_data'], true);
        if ($agency_data) {
            $form_data['travel_agency_name'] = $agency_data['name'] ?? '';
            $form_data['travel_agency_zip'] = $agency_data['zip'] ?? '';
            $form_data['travel_agency_prefecture'] = $agency_data['prefecture'] ?? '';
            $form_data['travel_agency_city'] = $agency_data['city'] ?? '';
            $form_data['travel_agency_address'] = $agency_data['address'] ?? '';
            $form_data['travel_agency_phone'] = $agency_data['phone'] ?? '';
            $form_data['travel_agency_fax'] = $agency_data['fax'] ?? '';
            $form_data['contact_mobile'] = $agency_data['contact_mobile'] ?? '';
            $form_data['contact_email'] = $agency_data['contact_email'] ?? '';
        }
    }
    
    // 予約タイプ
    $form_data['reservation_type'] = $reservation['reservation_type'] ?? '';
    
    // タイプ別データ
    if (!empty($reservation['type_data'])) {
        $type_data = json_decode($reservation['type_data'], true);
        if ($type_data) {
            // タイプごとのデータを展開
            foreach ($type_data as $key => $value) {
                $form_data[$key] = $value;
            }
        }
    }
    
    // 交通手段
    $transportation_reverse_mapping = [
        'car' => 'car',
        'bus' => 'chartered_bus',
        'taxi' => 'taxi',
        'other' => 'other'
    ];
    $form_data['transportation'] = $transportation_reverse_mapping[$reservation['transportation_method'] ?? ''] ?? 'other';
    $form_data['vehicle_count'] = $reservation['transportation_count'] ?? '';
    
    // その他
    $form_data['visit_purpose'] = $reservation['purpose'] ?? '';
    $form_data['total_visitors'] = $reservation['participant_count'] ?? '';
    $form_data['elementary_visitors'] = $reservation['participants_child_count'] ?? '';
    
    return $form_data;
}

/**
 * 予約管理画面の表示
 */
function reservation_management_admin_page() {
    global $wpdb;
    
    $errors = [];
    $field_errors = [];
    $success_message = '';
    $form_data = [];
    $is_edit_mode = false;
    $reservation_id = null;
    
    // 予約IDパラメータをチェック（編集モード）
    if (isset($_GET['reservation_id']) && !empty($_GET['reservation_id'])) {
        $reservation_id = intval($_GET['reservation_id']);
        $is_edit_mode = true;
        
        // 予約データを取得
        $reservation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}reservations WHERE id = %d",
                $reservation_id
            ),
            ARRAY_A
        );
        
        if ($reservation) {
            // 予約データをフォームデータに変換
            $form_data = convert_reservation_to_form_data($reservation);
        } else {
            $errors[] = '指定された予約が見つかりません。';
        }
    }
    
    // フォーム送信処理
    if (isset($_POST['submit_reservation'])) {
        $result = handle_reservation_form_submission();
        if ($result['success']) {
            $success_message = $result['message'];
            // 成功時はフォームデータをクリア
            $form_data = [];
        } else {
            $errors = $result['errors'];
            $field_errors = $result['field_errors'];
            // エラー時はフォームデータを保持
            $form_data = $_POST;
        }
    }
    
    // メッセージ表示
    if ($success_message) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($success_message) . '</p></div>';
    }
    
    // トップのエラーメッセージ表示（必須項目未入力の場合）
    if (!empty($field_errors)) {
        echo '<div class="notice notice-error is-dismissible"><p>必須項目が未入力です。</p></div>';
    }
    
    // transientからのメッセージ表示（リダイレクト後の場合）
    if (isset($_GET['success'])) {
        $message = get_transient('reservation_success_message');
        if ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            delete_transient('reservation_success_message');
        }
    }
    
    if (isset($_GET['error'])) {
        $transient_errors = get_transient('reservation_errors');
        $error_message = get_transient('reservation_error_message');
        
        if ($transient_errors) {
            echo '<div class="notice notice-error is-dismissible">';
            foreach ($transient_errors as $error) {
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
        <h1><?php echo $is_edit_mode ? '予約編集' : '予約追加'; ?></h1>
        
        <!-- 予約フォーム -->
        <div class="reservation-form-container">
            <!-- 予約内容 -->
            <div class="reservation-content">
                <div class="form-section-header with-border">
                    <h2 class="form-section-title">予約内容</h2>
                </div>
                <div class="form-section-content">
                    <form method="post" action="<?php echo admin_url('admin.php?page=reservation-management'); ?>">
                        <?php wp_nonce_field('reservation_form', 'reservation_nonce'); ?>
                        <?php if ($is_edit_mode && $reservation_id): ?>
                            <input type="hidden" name="reservation_id" value="<?php echo esc_attr($reservation_id); ?>">
                        <?php endif; ?>
                        <!-- 予約番号 -->
                        <div class="form-field">
                            <label for="reservation_number" class="form-label">
                                予約番号
                            </label>
                            <?php if ($is_edit_mode && $reservation_id): ?>
                                <span><?php echo esc_html($reservation_id); ?></span>
                            <?php else: ?>
                                <?php
                                // wp_reservationsテーブルから次の予約番号を取得
                                $max_id = $wpdb->get_var("SELECT MAX(id) FROM {$wpdb->prefix}reservations");
                                if ($max_id === null) {
                                    // テーブルが空の場合は1から開始
                                    $reservation_number = 1;
                                } else {
                                    // 最大IDに1を追加
                                    $reservation_number = intval($max_id) + 1;
                                }
                                ?>
                                <span><?php echo esc_html($reservation_number ?? ''); ?></span>
                                <input type="hidden" name="reservation_number" value="<?php echo esc_attr($reservation_number ?? ''); ?>">
                            <?php endif; ?>
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
                            <select name="factory_id" id="factory_id" class="form-select <?php echo get_field_error_class('factory_id', $field_errors); ?>" <?php echo $is_factory_account ? 'disabled' : ''; ?>>
                                <option value="">選択してください</option>
                                <?php foreach ($factories as $factory) : ?>
                                    <option value="<?php echo esc_attr($factory->id ?? ''); ?>" 
                                            <?php echo ($is_factory_account && $factory->id == $assigned_factory) ? 'selected' : ''; ?>
                                            <?php echo is_option_selected('factory_id', $factory->id, $form_data); ?>>
                                        <?php echo esc_html($factory->name ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($is_factory_account): ?>
                                <input type="hidden" name="factory_id" value="<?php echo esc_attr($assigned_factory); ?>">
                            <?php endif; ?>
                            <?php display_field_error('factory_id', $field_errors); ?>
                        </div>

                        <!-- 見学日 -->
                        <div class="form-field">
                            <label for="visit_date" class="form-label">
                                見学日 <span class="required">*</span>
                            </label>
                            <input type="date" name="visit_date" id="visit_date" class="form-input <?php echo get_field_error_class('visit_date', $field_errors); ?>" value="<?php echo get_form_value('visit_date', $form_data); ?>">
                            <?php display_field_error('visit_date', $field_errors); ?>
                        </div>

                        <!-- 見学時間帯 -->
                        <div class="form-field">
                            <label class="form-label">
                                見学時間帯 <span class="required">*</span>
                            </label>
                            <div class="time-range">
                                <!-- 開始時間 -->
                                <div style="display: inline-block;">
                                    <input type="number" name="visit_time_start_hour" id="visit_time_start_hour" class="time-input <?php echo get_field_error_class('visit_time_start', $field_errors); ?>" 
                                           min="0" max="23" placeholder="時" style="width: 50px;" value="<?php echo get_form_value('visit_time_start_hour', $form_data); ?>">
                                    <span>:</span>
                                    <input type="number" name="visit_time_start_minute" id="visit_time_start_minute" class="time-input <?php echo get_field_error_class('visit_time_start', $field_errors); ?>" 
                                           min="0" max="59" placeholder="分" style="width: 50px;" value="<?php echo get_form_value('visit_time_start_minute', $form_data); ?>">
                                </div>
                                <span style="margin: 0 10px;">〜</span>
                                <!-- 終了時間 -->
                                <div style="display: inline-block;">
                                    <input type="number" name="visit_time_end_hour" id="visit_time_end_hour" class="time-input <?php echo get_field_error_class('visit_time_end', $field_errors); ?>" 
                                           min="0" max="23" placeholder="時" style="width: 50px;" value="<?php echo get_form_value('visit_time_end_hour', $form_data); ?>">
                                    <span>:</span>
                                    <input type="number" name="visit_time_end_minute" id="visit_time_end_minute" class="time-input <?php echo get_field_error_class('visit_time_end', $field_errors); ?>" 
                                           min="0" max="59" placeholder="分" style="width: 50px;" value="<?php echo get_form_value('visit_time_end_minute', $form_data); ?>">
                                </div>
                            </div>
                            <?php display_field_error('visit_time_start', $field_errors); ?>
                            <?php display_field_error('visit_time_end', $field_errors); ?>
                        </div>

                        <!-- 申込者氏名 -->
                        <div class="form-field">
                            <label for="applicant_name" class="form-label">
                                申込者氏名 <span class="required">*</span>
                            </label>
                            <input type="text" name="applicant_name" id="applicant_name" class="form-input <?php echo get_field_error_class('applicant_name', $field_errors); ?>" value="<?php echo get_form_value('applicant_name', $form_data); ?>">
                            <?php display_field_error('applicant_name', $field_errors); ?>
                        </div>

                        <!-- 申込者氏名(ふりがな) -->
                        <div class="form-field">
                            <label for="applicant_kana" class="form-label">
                                申込者氏名(ふりがな) <span class="required">*</span>
                            </label>
                            <input type="text" name="applicant_kana" id="applicant_kana" class="form-input <?php echo get_field_error_class('applicant_kana', $field_errors); ?>" value="<?php echo get_form_value('applicant_kana', $form_data); ?>">
                            <?php display_field_error('applicant_kana', $field_errors); ?>
                        </div>

                        <!-- 旅行会社の方ですか -->
                        <div class="form-field">
                            <label class="form-label">
                                申込者様は旅行会社の方ですか？ <span class="required">*</span>
                            </label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="is_travel_agency" value="yes" id="travel_agency_yes" class="<?php echo get_field_error_class('is_travel_agency', $field_errors); ?>" <?php echo is_radio_checked('is_travel_agency', 'yes', $form_data); ?>> はい
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="is_travel_agency" value="no" id="travel_agency_no" class="<?php echo get_field_error_class('is_travel_agency', $field_errors); ?>" <?php echo is_radio_checked('is_travel_agency', 'no', $form_data); ?>> いいえ
                                </label>
                            </div>
                            <?php display_field_error('is_travel_agency', $field_errors); ?>
                        </div>

                        <!-- 旅行会社情報（「はい」の場合のみ表示） -->
                        <div id="travel_agency_fields" style="display: none;">
                            <!-- 旅行会社氏名 -->
                            <div class="form-field">
                                <label for="travel_agency_name" class="form-label">
                                    旅行会社氏名 <span class="required">*</span>
                                </label>
                                <input type="text" name="travel_agency_name" id="travel_agency_name" class="form-input" value="<?php echo get_form_value('travel_agency_name', $form_data); ?>">
                                <?php display_field_error('travel_agency_name', $field_errors); ?>
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
                                               oninput="searchAddress(this.value)" value="<?php echo get_form_value('travel_agency_zip', $form_data); ?>">
                                        <?php display_field_error('travel_agency_zip', $field_errors); ?>
                                        <span style="margin-left: 10px; font-size: 12px; color: #666;">郵便番号を入力すると住所が入力されます</span>
                                    </div>
                                    
                                    <!-- 県名 -->
                                    <div style="margin-bottom: 8px;">
                                        <select name="travel_agency_prefecture" id="travel_agency_prefecture" 
                                                class="form-select" style="width: 150px;">
                                            <option value="">都道府県を選択</option>
                                            <option value="北海道" <?php echo is_option_selected('travel_agency_prefecture', '北海道', $form_data); ?>>北海道</option>
                                            <option value="青森県" <?php echo is_option_selected('travel_agency_prefecture', '青森県', $form_data); ?>>青森県</option>
                                            <option value="岩手県" <?php echo is_option_selected('travel_agency_prefecture', '岩手県', $form_data); ?>>岩手県</option>
                                            <option value="宮城県" <?php echo is_option_selected('travel_agency_prefecture', '宮城県', $form_data); ?>>宮城県</option>
                                            <option value="秋田県" <?php echo is_option_selected('travel_agency_prefecture', '秋田県', $form_data); ?>>秋田県</option>
                                            <option value="山形県" <?php echo is_option_selected('travel_agency_prefecture', '山形県', $form_data); ?>>山形県</option>
                                            <option value="福島県" <?php echo is_option_selected('travel_agency_prefecture', '福島県', $form_data); ?>>福島県</option>
                                            <option value="茨城県" <?php echo is_option_selected('travel_agency_prefecture', '茨城県', $form_data); ?>>茨城県</option>
                                            <option value="栃木県" <?php echo is_option_selected('travel_agency_prefecture', '栃木県', $form_data); ?>>栃木県</option>
                                            <option value="群馬県" <?php echo is_option_selected('travel_agency_prefecture', '群馬県', $form_data); ?>>群馬県</option>
                                            <option value="埼玉県" <?php echo is_option_selected('travel_agency_prefecture', '埼玉県', $form_data); ?>>埼玉県</option>
                                            <option value="千葉県" <?php echo is_option_selected('travel_agency_prefecture', '千葉県', $form_data); ?>>千葉県</option>
                                            <option value="東京都" <?php echo is_option_selected('travel_agency_prefecture', '東京都', $form_data); ?>>東京都</option>
                                            <option value="神奈川県" <?php echo is_option_selected('travel_agency_prefecture', '神奈川県', $form_data); ?>>神奈川県</option>
                                            <option value="新潟県" <?php echo is_option_selected('travel_agency_prefecture', '新潟県', $form_data); ?>>新潟県</option>
                                            <option value="富山県" <?php echo is_option_selected('travel_agency_prefecture', '富山県', $form_data); ?>>富山県</option>
                                            <option value="石川県" <?php echo is_option_selected('travel_agency_prefecture', '石川県', $form_data); ?>>石川県</option>
                                            <option value="福井県" <?php echo is_option_selected('travel_agency_prefecture', '福井県', $form_data); ?>>福井県</option>
                                            <option value="山梨県" <?php echo is_option_selected('travel_agency_prefecture', '山梨県', $form_data); ?>>山梨県</option>
                                            <option value="長野県" <?php echo is_option_selected('travel_agency_prefecture', '長野県', $form_data); ?>>長野県</option>
                                            <option value="岐阜県" <?php echo is_option_selected('travel_agency_prefecture', '岐阜県', $form_data); ?>>岐阜県</option>
                                            <option value="静岡県" <?php echo is_option_selected('travel_agency_prefecture', '静岡県', $form_data); ?>>静岡県</option>
                                            <option value="愛知県" <?php echo is_option_selected('travel_agency_prefecture', '愛知県', $form_data); ?>>愛知県</option>
                                            <option value="三重県" <?php echo is_option_selected('travel_agency_prefecture', '三重県', $form_data); ?>>三重県</option>
                                            <option value="滋賀県" <?php echo is_option_selected('travel_agency_prefecture', '滋賀県', $form_data); ?>>滋賀県</option>
                                            <option value="京都府" <?php echo is_option_selected('travel_agency_prefecture', '京都府', $form_data); ?>>京都府</option>
                                            <option value="大阪府" <?php echo is_option_selected('travel_agency_prefecture', '大阪府', $form_data); ?>>大阪府</option>
                                            <option value="兵庫県" <?php echo is_option_selected('travel_agency_prefecture', '兵庫県', $form_data); ?>>兵庫県</option>
                                            <option value="奈良県" <?php echo is_option_selected('travel_agency_prefecture', '奈良県', $form_data); ?>>奈良県</option>
                                            <option value="和歌山県" <?php echo is_option_selected('travel_agency_prefecture', '和歌山県', $form_data); ?>>和歌山県</option>
                                            <option value="鳥取県" <?php echo is_option_selected('travel_agency_prefecture', '鳥取県', $form_data); ?>>鳥取県</option>
                                            <option value="島根県" <?php echo is_option_selected('travel_agency_prefecture', '島根県', $form_data); ?>>島根県</option>
                                            <option value="岡山県" <?php echo is_option_selected('travel_agency_prefecture', '岡山県', $form_data); ?>>岡山県</option>
                                            <option value="広島県" <?php echo is_option_selected('travel_agency_prefecture', '広島県', $form_data); ?>>広島県</option>
                                            <option value="山口県" <?php echo is_option_selected('travel_agency_prefecture', '山口県', $form_data); ?>>山口県</option>
                                            <option value="徳島県" <?php echo is_option_selected('travel_agency_prefecture', '徳島県', $form_data); ?>>徳島県</option>
                                            <option value="香川県" <?php echo is_option_selected('travel_agency_prefecture', '香川県', $form_data); ?>>香川県</option>
                                            <option value="愛媛県" <?php echo is_option_selected('travel_agency_prefecture', '愛媛県', $form_data); ?>>愛媛県</option>
                                            <option value="高知県" <?php echo is_option_selected('travel_agency_prefecture', '高知県', $form_data); ?>>高知県</option>
                                            <option value="福岡県" <?php echo is_option_selected('travel_agency_prefecture', '福岡県', $form_data); ?>>福岡県</option>
                                            <option value="佐賀県" <?php echo is_option_selected('travel_agency_prefecture', '佐賀県', $form_data); ?>>佐賀県</option>
                                            <option value="長崎県" <?php echo is_option_selected('travel_agency_prefecture', '長崎県', $form_data); ?>>長崎県</option>
                                            <option value="熊本県" <?php echo is_option_selected('travel_agency_prefecture', '熊本県', $form_data); ?>>熊本県</option>
                                            <option value="大分県" <?php echo is_option_selected('travel_agency_prefecture', '大分県', $form_data); ?>>大分県</option>
                                            <option value="宮崎県" <?php echo is_option_selected('travel_agency_prefecture', '宮崎県', $form_data); ?>>宮崎県</option>
                                            <option value="鹿児島県" <?php echo is_option_selected('travel_agency_prefecture', '鹿児島県', $form_data); ?>>鹿児島県</option>
                                            <option value="沖縄県" <?php echo is_option_selected('travel_agency_prefecture', '沖縄県', $form_data); ?>>沖縄県</option>
                                        </select>
                                        <?php display_field_error('travel_agency_prefecture', $field_errors); ?>
                                    </div>
                                    
                                    <!-- 市区町村 -->
                                    <div style="margin-bottom: 8px;">
                                        <input type="text" name="travel_agency_city" id="travel_agency_city" 
                                               placeholder="市区町村" class="form-input" style="width: 200px;" value="<?php echo get_form_value('travel_agency_city', $form_data); ?>">
                                        <?php display_field_error('travel_agency_city', $field_errors); ?>
                                    </div>
                                    
                                    <!-- 番地・建物名 -->
                                    <div>
                                        <input type="text" name="travel_agency_address" id="travel_agency_address" 
                                               placeholder="番地・建物名" class="form-input" value="<?php echo get_form_value('travel_agency_address', $form_data); ?>">
                                        <?php display_field_error('travel_agency_address', $field_errors); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 旅行会社電話番号 -->
                            <div class="form-field">
                                <label for="travel_agency_phone" class="form-label">
                                    旅行会社電話番号 <span class="required">*</span>
                                </label>
                                <input type="tel" name="travel_agency_phone" id="travel_agency_phone" class="form-input" value="<?php echo get_form_value('travel_agency_phone', $form_data); ?>">
                                <?php display_field_error('travel_agency_phone', $field_errors); ?>
                            </div>

                            <!-- 旅行会社FAX番号 -->
                            <div class="form-field">
                                <label for="travel_agency_fax" class="form-label">
                                    旅行会社FAX番号
                                </label>
                                <input type="tel" name="travel_agency_fax" id="travel_agency_fax" class="form-input" value="<?php echo get_form_value('travel_agency_fax', $form_data); ?>">
                                <?php display_field_error('travel_agency_fax', $field_errors); ?>
                            </div>

                            <!-- 担当者携帯番号 -->
                            <div class="form-field">
                                <label for="contact_mobile" class="form-label">
                                    担当者携帯番号
                                </label>
                                <input type="tel" name="contact_mobile" id="contact_mobile" class="form-input" value="<?php echo get_form_value('contact_mobile', $form_data); ?>">
                                <?php display_field_error('contact_mobile', $field_errors); ?>
                            </div>

                            <!-- 担当者メールアドレス -->
                            <div class="form-field">
                                <label for="contact_email" class="form-label">
                                    担当者メールアドレス <span class="required">*</span>
                                </label>
                                <input type="email" name="contact_email" id="contact_email" class="form-input" value="<?php echo get_form_value('contact_email', $form_data); ?>">
                                <?php display_field_error('contact_email', $field_errors); ?>
                            </div>
                        </div>

                        <!-- 予約タイプ -->
                        <div class="form-field">
                            <label class="form-label">
                                予約タイプ <span class="required">*</span>
                            </label>
                            <div class="radio-group reservation-type-group">
                                <label class="radio-option">
                                    <input type="radio" name="reservation_type" value="school" id="reservation_type_school" class="<?php echo get_field_error_class('reservation_type', $field_errors); ?>" <?php echo is_radio_checked('reservation_type', 'school', $form_data); ?>> 小学校・中学校・大学
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="reservation_type" value="student_recruit" id="reservation_type_student_recruit" class="<?php echo get_field_error_class('reservation_type', $field_errors); ?>" <?php echo is_radio_checked('reservation_type', 'student_recruit', $form_data); ?>> 個人（大学生・高校生のリクルート）
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="reservation_type" value="family" id="reservation_type_family" class="<?php echo get_field_error_class('reservation_type', $field_errors); ?>" <?php echo is_radio_checked('reservation_type', 'family', $form_data); ?>> 個人・親子見学・ご家族など
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="reservation_type" value="company" id="reservation_type_company" class="<?php echo get_field_error_class('reservation_type', $field_errors); ?>" <?php echo is_radio_checked('reservation_type', 'company', $form_data); ?>> 企業（研修など）
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="reservation_type" value="municipality" id="reservation_type_municipality" class="<?php echo get_field_error_class('reservation_type', $field_errors); ?>" <?php echo is_radio_checked('reservation_type', 'municipality', $form_data); ?>> 自治体主体ツアーなど
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="reservation_type" value="other" id="reservation_type_other" class="<?php echo get_field_error_class('reservation_type', $field_errors); ?>" <?php echo is_radio_checked('reservation_type', 'other', $form_data); ?>> その他（グループ・団体）
                                </label>
                            </div>
                            <?php display_field_error('reservation_type', $field_errors); ?>
                        </div>

                        <!-- 学校・団体情報（「小学校・中学校・大学」の場合のみ表示） -->
                        <div id="school_fields" style="display: none;">
                            <!-- 学校・団体名 -->
                            <div class="form-field">
                                <label for="school_name" class="form-label">
                                    学校・団体名 <span class="required">*</span>
                                </label>
                                <input type="text" name="school_name" id="school_name" class="form-input" value="<?php echo get_form_value('school_name', $form_data); ?>">
                                <?php display_field_error('school_name', $field_errors); ?>
                            </div>

                            <!-- 学校・団体名(ふりがな) -->
                            <div class="form-field">
                                <label for="school_name_kana" class="form-label">
                                    学校・団体名(ふりがな) <span class="required">*</span>
                                </label>
                                <input type="text" name="school_name_kana" id="school_name_kana" class="form-input" value="<?php echo get_form_value('school_name_kana', $form_data); ?>">
                                <?php display_field_error('school_name_kana', $field_errors); ?>
                            </div>

                            <!-- 代表者氏名 -->
                            <div class="form-field">
                                <label for="representative_name" class="form-label">
                                    代表者氏名
                                </label>
                                <input type="text" name="representative_name" id="representative_name" class="form-input" value="<?php echo get_form_value('representative_name', $form_data); ?>">
                                <?php display_field_error('representative_name', $field_errors); ?>
                            </div>

                            <!-- 代表者氏名(ふりがな) -->
                            <div class="form-field">
                                <label for="representative_name_kana" class="form-label">
                                    代表者氏名(ふりがな)
                                </label>
                                <input type="text" name="representative_name_kana" id="representative_name_kana" class="form-input" value="<?php echo get_form_value('representative_name_kana', $form_data); ?>">
                                <?php display_field_error('representative_name_kana', $field_errors); ?>
                            </div>

                            <!-- 学年 -->
                            <div class="form-field">
                                <label for="grade" class="form-label">
                                    学年 <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="grade" id="grade" class="form-input" style="width: 50px !important;" min="1" max="12" value="<?php echo get_form_value('grade', $form_data); ?>">
                                <?php display_field_error('grade', $field_errors); ?>
                                    <span style="margin-left: 5px;">年生</span>
                                </div>
                            </div>

                            <!-- クラス数 -->
                            <div class="form-field">
                                <label for="class_count" class="form-label">
                                    クラス数 <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="class_count" id="class_count" class="form-input" style="width: 50px !important;" min="1" value="<?php echo get_form_value('class_count', $form_data); ?>">
                                <?php display_field_error('class_count', $field_errors); ?>
                                    <span style="margin-left: 5px;">クラス</span>
                                </div>
                            </div>

                            <!-- 見学者人数(児童・生徒) -->
                            <div class="form-field">
                                <label for="student_count" class="form-label">
                                    見学者人数(児童・生徒) <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="student_count" id="student_count" class="form-input" style="width: 50px !important;" min="0" value="<?php echo get_form_value('student_count', $form_data); ?>">
                                <?php display_field_error('student_count', $field_errors); ?>
                                    <span style="margin-left: 5px;">名</span>
                                </div>
                            </div>

                            <!-- 見学者人数(引率) -->
                            <div class="form-field">
                                <label for="supervisor_count" class="form-label">
                                    見学者人数(引率) <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="supervisor_count" id="supervisor_count" class="form-input" style="width: 50px !important;" min="0" value="<?php echo get_form_value('supervisor_count', $form_data); ?>">
                                <?php display_field_error('supervisor_count', $field_errors); ?>
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
                                <input type="text" name="recruit_school_name" id="recruit_school_name" class="form-input" value="<?php echo get_form_value('recruit_school_name', $form_data); ?>">
                                <?php display_field_error('recruit_school_name', $field_errors); ?>
                            </div>

                            <!-- 学部 -->
                            <div class="form-field">
                                <label for="recruit_department" class="form-label">
                                    学部 <span class="required">*</span>
                                </label>
                                <input type="text" name="recruit_department" id="recruit_department" class="form-input" value="<?php echo get_form_value('recruit_department', $form_data); ?>">
                                <?php display_field_error('recruit_department', $field_errors); ?>
                            </div>

                            <!-- 学年 -->
                            <div class="form-field">
                                <label for="recruit_grade" class="form-label">
                                    学年 <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="recruit_grade" id="recruit_grade" class="form-input" style="width: 50px !important;" min="1" max="6" value="<?php echo get_form_value('recruit_grade', $form_data); ?>">
                                <?php display_field_error('recruit_grade', $field_errors); ?>
                                    <span style="margin-left: 5px;">年生</span>
                                </div>
                            </div>

                            <!-- 見学者様人数 -->
                            <div class="form-field">
                                <label for="recruit_visitor_count" class="form-label">
                                    見学者様人数 <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="recruit_visitor_count" id="recruit_visitor_count" class="form-input" style="width: 50px !important;" min="1" onchange="updateCompanionFields()" value="<?php echo get_form_value('recruit_visitor_count', $form_data); ?>">
                                <?php display_field_error('recruit_visitor_count', $field_errors); ?>
                                    <span style="margin-left: 5px;">名</span>
                                </div>
                            </div>

                            <!-- 同行者様情報（動的に表示） -->
                            <div id="companion_fields">
                                <?php
                                // フォームエラー時に同行者フィールドを復元
                                if (isset($form_data['recruit_visitor_count']) && $form_data['recruit_visitor_count'] > 1) {
                                    $visitor_count = intval($form_data['recruit_visitor_count']);
                                    for ($i = 1; $i < $visitor_count; $i++) {
                                        echo '<div class="form-field">';
                                        echo '<label class="form-label" style="align-items: flex-start;">同行者様' . $i . '</label>';
                                        echo '<div style="display: flex; flex-direction: column; gap: 10px;">';
                                        echo '<div>';
                                        echo '<label class="form-label" style="margin-right: 45px;">氏名 <span class="required">*</span></label>';
                                        echo '<input type="text" name="companion_name_' . $i . '" id="companion_name_' . $i . '" class="form-input" style="width: 215px !important;" value="' . get_form_value('companion_name_' . $i, $form_data) . '" required>';
                                        echo '</div>';
                                        echo '<div>';
                                        echo '<label class="form-label" style="margin-right: 45px;">学部 <span class="required">*</span></label>';
                                        echo '<input type="text" name="companion_department_' . $i . '" id="companion_department_' . $i . '" class="form-input" style="width: 215px !important;" value="' . get_form_value('companion_department_' . $i, $form_data) . '" required>';
                                        echo '</div>';
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>

                        <!-- 一般・企業情報（「個人・親子見学・ご家族など」「企業」「自治体主体」「その他」の場合のみ表示） -->
                        <div id="general_fields" style="display: none;">
                            <!-- 会社・団体名 -->
                            <div class="form-field">
                                <label for="company_name" class="form-label">
                                    会社・団体名 <span class="required">*</span>
                                </label>
                                <input type="text" name="company_name" id="company_name" class="form-input" value="<?php echo get_form_value('company_name', $form_data); ?>">
                                <?php display_field_error('company_name', $field_errors); ?>
                            </div>

                            <!-- 会社・団体名(ふりがな) -->
                            <div class="form-field">
                                <label for="company_name_kana" class="form-label">
                                    会社・団体名(ふりがな) <span class="required">*</span>
                                </label>
                                <input type="text" name="company_name_kana" id="company_name_kana" class="form-input" value="<?php echo get_form_value('company_name_kana', $form_data); ?>">
                                <?php display_field_error('company_name_kana', $field_errors); ?>
                            </div>

                            <!-- 見学者人数(大人) -->
                            <div class="form-field">
                                <label for="adult_count" class="form-label">
                                    見学者人数(大人) <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="adult_count" id="adult_count" class="form-input" style="width: 50px !important;" min="0" value="<?php echo get_form_value('adult_count', $form_data); ?>">
                                <?php display_field_error('adult_count', $field_errors); ?>
                                    <span style="margin-left: 5px;">名</span>
                                </div>
                            </div>

                            <!-- 見学者人数(子ども) -->
                            <div class="form-field">
                                <label for="child_count" class="form-label">
                                    見学者人数(子ども) <span class="required">*</span>
                                </label>
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="child_count" id="child_count" class="form-input" style="width: 50px !important;" min="0" onchange="updateChildGradeFields()" value="<?php echo get_form_value('child_count', $form_data); ?>">
                                <?php display_field_error('child_count', $field_errors); ?>
                                    <span style="margin-left: 5px;">名</span>
                                </div>
                            </div>

                            <!-- 学年（子ども人数が1人以上の場合のみ表示） -->
                            <div id="child_grade_field" style="display: none;">
                                <div class="form-field">
                                    <label for="child_grade" class="form-label">
                                        学年 <span class="required">*</span>
                                    </label>
                                    <input type="text" name="child_grade" id="child_grade" class="form-input" placeholder="例：小学1年生、小学3年生" value="<?php echo get_form_value('child_grade', $form_data); ?>">
                                    <?php display_field_error('child_grade', $field_errors); ?>
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
                                           placeholder="1234567" maxlength="7" class="form-input <?php echo get_field_error_class('applicant_zip', $field_errors); ?>" style="width: 100px !important;" 
                                           oninput="searchApplicantAddress(this.value)" value="<?php echo get_form_value('applicant_zip', $form_data); ?>">
                                    <span style="margin-left: 10px; font-size: 12px; color: #666;">郵便番号を入力すると住所が入力されます</span>
                                </div>
                                <?php display_field_error('applicant_zip', $field_errors); ?>
                                
                                <!-- 県名 -->
                                <div style="margin-bottom: 8px;">
                                    <select name="applicant_prefecture" id="applicant_prefecture" 
                                            class="form-select <?php echo get_field_error_class('applicant_prefecture', $field_errors); ?>" style="width: 150px;">
                                        <option value="">都道府県を選択</option>
                                        <option value="北海道" <?php echo is_option_selected('applicant_prefecture', '北海道', $form_data); ?>>北海道</option>
                                        <option value="青森県" <?php echo is_option_selected('applicant_prefecture', '青森県', $form_data); ?>>青森県</option>
                                        <option value="岩手県" <?php echo is_option_selected('applicant_prefecture', '岩手県', $form_data); ?>>岩手県</option>
                                        <option value="宮城県" <?php echo is_option_selected('applicant_prefecture', '宮城県', $form_data); ?>>宮城県</option>
                                        <option value="秋田県" <?php echo is_option_selected('applicant_prefecture', '秋田県', $form_data); ?>>秋田県</option>
                                        <option value="山形県" <?php echo is_option_selected('applicant_prefecture', '山形県', $form_data); ?>>山形県</option>
                                        <option value="福島県" <?php echo is_option_selected('applicant_prefecture', '福島県', $form_data); ?>>福島県</option>
                                        <option value="茨城県" <?php echo is_option_selected('applicant_prefecture', '茨城県', $form_data); ?>>茨城県</option>
                                        <option value="栃木県" <?php echo is_option_selected('applicant_prefecture', '栃木県', $form_data); ?>>栃木県</option>
                                        <option value="群馬県" <?php echo is_option_selected('applicant_prefecture', '群馬県', $form_data); ?>>群馬県</option>
                                        <option value="埼玉県" <?php echo is_option_selected('applicant_prefecture', '埼玉県', $form_data); ?>>埼玉県</option>
                                        <option value="千葉県" <?php echo is_option_selected('applicant_prefecture', '千葉県', $form_data); ?>>千葉県</option>
                                        <option value="東京都" <?php echo is_option_selected('applicant_prefecture', '東京都', $form_data); ?>>東京都</option>
                                        <option value="神奈川県" <?php echo is_option_selected('applicant_prefecture', '神奈川県', $form_data); ?>>神奈川県</option>
                                        <option value="新潟県" <?php echo is_option_selected('applicant_prefecture', '新潟県', $form_data); ?>>新潟県</option>
                                        <option value="富山県" <?php echo is_option_selected('applicant_prefecture', '富山県', $form_data); ?>>富山県</option>
                                        <option value="石川県" <?php echo is_option_selected('applicant_prefecture', '石川県', $form_data); ?>>石川県</option>
                                        <option value="福井県" <?php echo is_option_selected('applicant_prefecture', '福井県', $form_data); ?>>福井県</option>
                                        <option value="山梨県" <?php echo is_option_selected('applicant_prefecture', '山梨県', $form_data); ?>>山梨県</option>
                                        <option value="長野県" <?php echo is_option_selected('applicant_prefecture', '長野県', $form_data); ?>>長野県</option>
                                        <option value="岐阜県" <?php echo is_option_selected('applicant_prefecture', '岐阜県', $form_data); ?>>岐阜県</option>
                                        <option value="静岡県" <?php echo is_option_selected('applicant_prefecture', '静岡県', $form_data); ?>>静岡県</option>
                                        <option value="愛知県" <?php echo is_option_selected('applicant_prefecture', '愛知県', $form_data); ?>>愛知県</option>
                                        <option value="三重県" <?php echo is_option_selected('applicant_prefecture', '三重県', $form_data); ?>>三重県</option>
                                        <option value="滋賀県" <?php echo is_option_selected('applicant_prefecture', '滋賀県', $form_data); ?>>滋賀県</option>
                                        <option value="京都府" <?php echo is_option_selected('applicant_prefecture', '京都府', $form_data); ?>>京都府</option>
                                        <option value="大阪府" <?php echo is_option_selected('applicant_prefecture', '大阪府', $form_data); ?>>大阪府</option>
                                        <option value="兵庫県" <?php echo is_option_selected('applicant_prefecture', '兵庫県', $form_data); ?>>兵庫県</option>
                                        <option value="奈良県" <?php echo is_option_selected('applicant_prefecture', '奈良県', $form_data); ?>>奈良県</option>
                                        <option value="和歌山県" <?php echo is_option_selected('applicant_prefecture', '和歌山県', $form_data); ?>>和歌山県</option>
                                        <option value="鳥取県" <?php echo is_option_selected('applicant_prefecture', '鳥取県', $form_data); ?>>鳥取県</option>
                                        <option value="島根県" <?php echo is_option_selected('applicant_prefecture', '島根県', $form_data); ?>>島根県</option>
                                        <option value="岡山県" <?php echo is_option_selected('applicant_prefecture', '岡山県', $form_data); ?>>岡山県</option>
                                        <option value="広島県" <?php echo is_option_selected('applicant_prefecture', '広島県', $form_data); ?>>広島県</option>
                                        <option value="山口県" <?php echo is_option_selected('applicant_prefecture', '山口県', $form_data); ?>>山口県</option>
                                        <option value="徳島県" <?php echo is_option_selected('applicant_prefecture', '徳島県', $form_data); ?>>徳島県</option>
                                        <option value="香川県" <?php echo is_option_selected('applicant_prefecture', '香川県', $form_data); ?>>香川県</option>
                                        <option value="愛媛県" <?php echo is_option_selected('applicant_prefecture', '愛媛県', $form_data); ?>>愛媛県</option>
                                        <option value="高知県" <?php echo is_option_selected('applicant_prefecture', '高知県', $form_data); ?>>高知県</option>
                                        <option value="福岡県" <?php echo is_option_selected('applicant_prefecture', '福岡県', $form_data); ?>>福岡県</option>
                                        <option value="佐賀県" <?php echo is_option_selected('applicant_prefecture', '佐賀県', $form_data); ?>>佐賀県</option>
                                        <option value="長崎県" <?php echo is_option_selected('applicant_prefecture', '長崎県', $form_data); ?>>長崎県</option>
                                        <option value="熊本県" <?php echo is_option_selected('applicant_prefecture', '熊本県', $form_data); ?>>熊本県</option>
                                        <option value="大分県" <?php echo is_option_selected('applicant_prefecture', '大分県', $form_data); ?>>大分県</option>
                                        <option value="宮崎県" <?php echo is_option_selected('applicant_prefecture', '宮崎県', $form_data); ?>>宮崎県</option>
                                        <option value="鹿児島県" <?php echo is_option_selected('applicant_prefecture', '鹿児島県', $form_data); ?>>鹿児島県</option>
                                        <option value="沖縄県" <?php echo is_option_selected('applicant_prefecture', '沖縄県', $form_data); ?>>沖縄県</option>
                                    </select>
                                    <?php display_field_error('applicant_prefecture', $field_errors); ?>
                                </div>
                                
                                <!-- 市区町村 -->
                                <div style="margin-bottom: 8px;">
                                    <input type="text" name="applicant_city" id="applicant_city" 
                                           placeholder="市区町村" class="form-input <?php echo get_field_error_class('applicant_city', $field_errors); ?>" style="width: 200px;" value="<?php echo get_form_value('applicant_city', $form_data); ?>">
                                    <?php display_field_error('applicant_city', $field_errors); ?>
                                </div>
                                
                                <!-- 番地・建物名 -->
                                <div>
                                    <input type="text" name="applicant_address" id="applicant_address" 
                                           placeholder="番地・建物名" class="form-input <?php echo get_field_error_class('applicant_address', $field_errors); ?>" value="<?php echo get_form_value('applicant_address', $form_data); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- 申込者様電話番号 -->
                        <div class="form-field">
                            <label for="applicant_phone" class="form-label">
                                申込者様電話番号 <span class="required">*</span>
                            </label>
                            <input type="tel" name="applicant_phone" id="applicant_phone" class="form-input <?php echo get_field_error_class('applicant_phone', $field_errors); ?>" value="<?php echo get_form_value('applicant_phone', $form_data); ?>">
                            <?php display_field_error('applicant_phone', $field_errors); ?>
                        </div>

                        <!-- 当日連絡先(携帯番号) -->
                        <div class="form-field">
                            <label for="emergency_contact" class="form-label">
                                当日連絡先(携帯番号) <span class="required">*</span>
                            </label>
                            <input type="tel" name="emergency_contact" id="emergency_contact" class="form-input <?php echo get_field_error_class('emergency_contact', $field_errors); ?>" value="<?php echo get_form_value('emergency_contact', $form_data); ?>">
                            <?php display_field_error('emergency_contact', $field_errors); ?>
                        </div>

                        <!-- 申込者様メールアドレス -->
                        <div class="form-field">
                            <label for="applicant_email" class="form-label">
                                申込者様メールアドレス <span class="required">*</span>
                            </label>
                            <input type="email" name="applicant_email" id="applicant_email" class="form-input <?php echo get_field_error_class('applicant_email', $field_errors); ?>" value="<?php echo get_form_value('applicant_email', $form_data); ?>">
                            <?php display_field_error('applicant_email', $field_errors); ?>
                        </div>

                        <!-- ご利用の交通機関 -->
                        <div class="form-field">
                            <label class="form-label">
                                ご利用の交通機関 <span class="required">*</span>
                            </label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="transportation" value="car" id="transportation_car" class="<?php echo get_field_error_class('transportation', $field_errors); ?>" <?php echo is_radio_checked('transportation', 'car', $form_data); ?>> 車
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="transportation" value="chartered_bus" id="transportation_chartered_bus" class="<?php echo get_field_error_class('transportation', $field_errors); ?>" <?php echo is_radio_checked('transportation', 'chartered_bus', $form_data); ?>> 貸切バス
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="transportation" value="local_bus" id="transportation_local_bus" class="<?php echo get_field_error_class('transportation', $field_errors); ?>" <?php echo is_radio_checked('transportation', 'local_bus', $form_data); ?>> 路線バス
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="transportation" value="taxi" id="transportation_taxi" class="<?php echo get_field_error_class('transportation', $field_errors); ?>" <?php echo is_radio_checked('transportation', 'taxi', $form_data); ?>> タクシー
                                </label>
                                <label class="radio-option transportation-other-option">
                                    <input type="radio" name="transportation" value="other" id="transportation_other" class="<?php echo get_field_error_class('transportation', $field_errors); ?>" <?php echo is_radio_checked('transportation', 'other', $form_data); ?>> その他
                                    <input type="text" name="transportation_other_text" id="transportation_other_text" class="form-input transportation-other-input" value="<?php echo get_form_value('transportation_other_text', $form_data); ?>" <?php echo is_radio_checked('transportation', 'other', $form_data) ? '' : 'disabled'; ?>>
                                </label>
                            </div>
                            <?php display_field_error('transportation', $field_errors); ?>
                        </div>

                        <!-- 台数 -->
                        <div class="form-field">
                            <label for="vehicle_count" class="form-label">
                                台数 <span class="required">*</span>
                            </label>
                            <div style="display: flex; align-items: center;">
                                <input type="number" name="vehicle_count" id="vehicle_count" class="form-input <?php echo get_field_error_class('vehicle_count', $field_errors); ?>" style="width: 50px !important;" min="1" value="<?php echo get_form_value('vehicle_count', $form_data); ?>">
                                <span style="margin-left: 5px;">台</span>
                            </div>
                            <?php display_field_error('vehicle_count', $field_errors); ?>
                        </div>

                        <!-- 見学目的 -->
                        <div class="form-field" style="align-items: flex-start;">
                            <label for="visit_purpose" class="form-label" style="margin-top: 10px;">
                                見学目的 <span class="required">*</span>
                            </label>
                            <textarea name="visit_purpose" id="visit_purpose" class="form-input <?php echo get_field_error_class('visit_purpose', $field_errors); ?>" rows="4" style="width: 100%; resize: vertical;"><?php echo get_form_value('visit_purpose', $form_data); ?></textarea>
                            <?php display_field_error('visit_purpose', $field_errors); ?>
                        </div>

                        <!-- 見学者人数 -->
                        <div class="form-field">
                            <label class="form-label">
                                見学者人数 <span class="required">*</span>
                            </label>
                            <div style="display: flex; align-items: center; gap: 20px;">
                                <div style="display: flex; align-items: center;">
                                    <input type="number" name="total_visitors" id="total_visitors" class="form-input <?php echo get_field_error_class('total_visitors', $field_errors); ?>" style="width: 50px !important;" min="1" value="<?php echo get_form_value('total_visitors', $form_data); ?>">
                                    <span style="margin-left: 5px;">名</span>
                                </div>
                                <div style="display: flex; align-items: center;">
                                    <span style="margin-right: 5px;">内小学生以下</span>
                                    <input type="number" name="elementary_visitors" id="elementary_visitors" class="form-input <?php echo get_field_error_class('elementary_visitors', $field_errors); ?>" style="width: 50px !important;" min="0" value="<?php echo get_form_value('elementary_visitors', $form_data); ?>">
                                    <span style="margin-left: 5px;">名</span>
                                </div>
                            </div>
                            <?php display_field_error('total_visitors', $field_errors); ?>
                            <?php display_field_error('elementary_visitors', $field_errors); ?>
                        </div>
                </div>
            </div>
            
            <!-- 予約ステータス -->
            <div class="reservation-status">
                <div class="form-section-header">
                    <h2 class="form-section-title">予約ステータス</h2>
                </div>
                <div class="form-section-content">
                    <select name="reservation_status" id="reservation_status" class="form-select status">
                        <option value="new" <?php echo is_option_selected('reservation_status', 'new', $form_data, 'new'); ?>>新規受付</option>
                        <option value="pending" <?php echo is_option_selected('reservation_status', 'pending', $form_data, 'new'); ?>>確認中</option>
                        <option value="approved" <?php echo is_option_selected('reservation_status', 'approved', $form_data, 'new'); ?>>承認</option>
                        <option value="rejected" <?php echo is_option_selected('reservation_status', 'rejected', $form_data, 'new'); ?>>否認</option>
                        <option value="cancelled" <?php echo is_option_selected('reservation_status', 'cancelled', $form_data, 'new'); ?>>キャンセル</option>
                    </select>
                    
                    <button type="button" id="create_reply_email" class="btn-reply-email">
                        返信メールを作成
                    </button>
                    
                    <div class="btn-register-container">
                        <button type="submit" name="submit_reservation" id="register_reservation" class="btn-register">
                            <?php echo $is_edit_mode ? '更新' : '登録'; ?>
                        </button>
                    </div>
                </div>
            </div>
                    </form>
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
        const vehicleCountField = document.getElementById('vehicle_count').closest('.form-field');
        
        function toggleTransportationOtherField() {
            if (transportationOther.checked) {
                transportationOtherText.disabled = false;
                transportationOtherText.focus();
            } else {
                transportationOtherText.disabled = true;
                transportationOtherText.value = '';
            }
        }

        function toggleVehicleCountField() {
            const showVehicleCount = transportationCar.checked || 
                                   transportationCharteredBus.checked || 
                                   transportationTaxi.checked;
            
            if (vehicleCountField) {
                vehicleCountField.style.display = showVehicleCount ? 'flex' : 'none';
                
                // フィールドが非表示の場合は値をクリアして必須バリデーションをスキップ
                const vehicleCountInput = document.getElementById('vehicle_count');
                if (!showVehicleCount && vehicleCountInput) {
                    vehicleCountInput.value = '';
                }
            }
        }
        
        function handleTransportationChange() {
            toggleTransportationOtherField();
            toggleVehicleCountField();
        }

        transportationOther.addEventListener('change', handleTransportationChange);
        transportationCar.addEventListener('change', handleTransportationChange);
        transportationCharteredBus.addEventListener('change', handleTransportationChange);
        transportationLocalBus.addEventListener('change', handleTransportationChange);
        transportationTaxi.addEventListener('change', handleTransportationChange);
        
        // 初期状態の設定（フォームエラー時でも選択状態を維持）
        toggleTravelAgencyFields();
        toggleAllFields();
        toggleTransportationOtherField();
        toggleVehicleCountField();
        
        // 同行者フィールドの初期化（個人リクルート選択時）
        if (recruitFields && recruitFields.style.display !== 'none') {
            // updateCompanionFieldsはグローバル関数なので、window経由で呼び出す
            if (typeof window.updateCompanionFields === 'function') {
                window.updateCompanionFields();
            }
        }
        
        // 子ども学年フィールドの初期化（一般フィールド表示時）
        if (generalFields && generalFields.style.display !== 'none') {
            if (typeof window.updateChildGradeFields === 'function') {
                window.updateChildGradeFields();
            }
        }
        
        // フォーム送信時のバリデーション
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                } else {
                    // フォーム送信が成功する場合は離脱確認を無効化
                    window.formChanged = false;
                }
            });
        }

        // フォーム変更検知とページ離脱防止
        let formChanged = false;
        window.formChanged = false;

        // フォーム要素の変更を監視
        const formElements = form.querySelectorAll('input, select, textarea');
        formElements.forEach(element => {
            element.addEventListener('change', function() {
                formChanged = true;
                window.formChanged = true;
            });
            
            // テキスト入力の場合はinputイベントも監視
            if (element.type === 'text' || element.type === 'email' || element.type === 'tel' || element.type === 'number' || element.tagName === 'TEXTAREA') {
                element.addEventListener('input', function() {
                    formChanged = true;
                    window.formChanged = true;
                });
            }
        });

        // ページ離脱・リロード時の確認
        window.addEventListener('beforeunload', function(e) {
            if (window.formChanged) {
                const message = 'このページから離れてもよろしいですか？入力中のデータが失われる可能性があります。';
                e.preventDefault();
                e.returnValue = message;
                return message;
            }
        });
    });
    
    // クライアントサイドバリデーション関数
    function validateForm() {
        const errors = [];
        
        // 必須フィールドのチェック
        const requiredFields = [
            { id: 'factory_id', name: '見学工場' },
            { id: 'visit_date', name: '見学日' },
            { id: 'visit_time_start_hour', name: '見学開始時間（時）' },
            { id: 'visit_time_start_minute', name: '見学開始時間（分）' },
            { id: 'visit_time_end_hour', name: '見学終了時間（時）' },
            { id: 'visit_time_end_minute', name: '見学終了時間（分）' },
            { id: 'applicant_name', name: '申込者氏名' },
            { id: 'applicant_kana', name: '申込者氏名(ふりがな)' },
            { id: 'applicant_zip', name: '申込者郵便番号' },
            { id: 'applicant_prefecture', name: '申込者都道府県' },
            { id: 'applicant_city', name: '申込者市区町村' },
            { id: 'applicant_phone', name: '申込者電話番号' },
            { id: 'emergency_contact', name: '当日連絡先' },
            { id: 'applicant_email', name: '申込者メールアドレス' },
            { id: 'visit_purpose', name: '見学目的' },
            { id: 'total_visitors', name: '見学者人数' }
        ];
        
        requiredFields.forEach(field => {
            const element = document.getElementById(field.id);
            if (element && !element.value.trim()) {
                errors.push(field.name + 'は必須項目です。');
            }
        });

        // 台数の条件付きバリデーション（車、貸切バス、タクシーの場合のみ必須）
        const transportationCar = document.getElementById('transportation_car');
        const transportationCharteredBus = document.getElementById('transportation_chartered_bus');
        const transportationTaxi = document.getElementById('transportation_taxi');
        const vehicleCountInput = document.getElementById('vehicle_count');
        
        if ((transportationCar && transportationCar.checked) || 
            (transportationCharteredBus && transportationCharteredBus.checked) || 
            (transportationTaxi && transportationTaxi.checked)) {
            if (vehicleCountInput && !vehicleCountInput.value.trim()) {
                errors.push('台数は必須項目です。');
            }
        }
        
        // 旅行会社・予約タイプの必須チェック
        const travelAgencyYes = document.getElementById('travel_agency_yes');
        const travelAgencyNo = document.getElementById('travel_agency_no');
        if (!travelAgencyYes.checked && !travelAgencyNo.checked) {
            errors.push('旅行会社かどうかを選択してください。');
        }
        
        const reservationTypes = document.querySelectorAll('input[name="reservation_type"]');
        let reservationTypeSelected = false;
        reservationTypes.forEach(radio => {
            if (radio.checked) {
                reservationTypeSelected = true;
            }
        });
        if (!reservationTypeSelected) {
            errors.push('予約タイプを選択してください。');
        }
        
        const transportations = document.querySelectorAll('input[name="transportation"]');
        let transportationSelected = false;
        transportations.forEach(radio => {
            if (radio.checked) {
                transportationSelected = true;
            }
        });
        if (!transportationSelected) {
            errors.push('交通機関を選択してください。');
        }
        
        // 日付の過去チェック
        const visitDate = document.getElementById('visit_date');
        if (visitDate && visitDate.value) {
            const selectedDate = new Date(visitDate.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                errors.push('見学日は今日以降の日付を選択してください。');
            }
        }
        
        // 時間の範囲チェック
        const startHour = document.getElementById('visit_time_start_hour');
        const startMinute = document.getElementById('visit_time_start_minute');
        const endHour = document.getElementById('visit_time_end_hour');
        const endMinute = document.getElementById('visit_time_end_minute');
        
        // 時間の範囲チェック
        if (startHour && startHour.value && (parseInt(startHour.value) < 0 || parseInt(startHour.value) > 23)) {
            errors.push('開始時間（時）は0〜23の範囲で入力してください。');
        }
        if (startMinute && startMinute.value && (parseInt(startMinute.value) < 0 || parseInt(startMinute.value) > 59)) {
            errors.push('開始時間（分）は0〜59の範囲で入力してください。');
        }
        if (endHour && endHour.value && (parseInt(endHour.value) < 0 || parseInt(endHour.value) > 23)) {
            errors.push('終了時間（時）は0〜23の範囲で入力してください。');
        }
        if (endMinute && endMinute.value && (parseInt(endMinute.value) < 0 || parseInt(endMinute.value) > 59)) {
            errors.push('終了時間（分）は0〜59の範囲で入力してください。');
        }
        
        // 開始時間と終了時間の比較
        if (startHour && startMinute && endHour && endMinute && 
            startHour.value && startMinute.value && endHour.value && endMinute.value) {
            const startTotalMinutes = parseInt(startHour.value) * 60 + parseInt(startMinute.value);
            const endTotalMinutes = parseInt(endHour.value) * 60 + parseInt(endMinute.value);
            
            if (startTotalMinutes >= endTotalMinutes) {
                errors.push('終了時間は開始時間よりも後の時間を入力してください。');
            }
        }
        
        // 郵便番号の形式チェック
        const zipCode = document.getElementById('applicant_zip');
        if (zipCode && zipCode.value && !/^\d{7}$/.test(zipCode.value)) {
            errors.push('郵便番号は7桁の数字で入力してください。');
        }
        
        // メールアドレスの形式チェック
        const email = document.getElementById('applicant_email');
        if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
            errors.push('正しいメールアドレスを入力してください。');
        }
        
        // 数値フィールドのチェック
        const numericFields = [
            { id: 'total_visitors', name: '見学者人数' },
            { id: 'elementary_visitors', name: '小学生以下人数' }
        ];
        
        numericFields.forEach(field => {
            const element = document.getElementById(field.id);
            if (element && element.value) {
                const value = parseInt(element.value);
                if (isNaN(value) || value < 0) {
                    errors.push(field.name + 'は0以上の数値で入力してください。');
                }
            }
        });

        // 台数の数値チェック（表示されている場合のみ）
        if (vehicleCountInput && vehicleCountInput.value && vehicleCountField && vehicleCountField.style.display !== 'none') {
            const vehicleCountValue = parseInt(vehicleCountInput.value);
            if (isNaN(vehicleCountValue) || vehicleCountValue < 0) {
                errors.push('台数は0以上の数値で入力してください。');
            }
        }
        
        // 見学者人数の整合性チェック
        const totalVisitors = document.getElementById('total_visitors');
        const elementaryVisitors = document.getElementById('elementary_visitors');
        if (totalVisitors && elementaryVisitors && totalVisitors.value && elementaryVisitors.value) {
            if (parseInt(elementaryVisitors.value) > parseInt(totalVisitors.value)) {
                errors.push('小学生以下の人数は見学者人数を超えることはできません。');
            }
        }
        
        // 交通機関「その他」のチェック
        const transportationOther = document.getElementById('transportation_other');
        const transportationOtherText = document.getElementById('transportation_other_text');
        if (transportationOther && transportationOther.checked && transportationOtherText && !transportationOtherText.value.trim()) {
            errors.push('交通機関で「その他」を選択した場合は、内容を入力してください。');
        }
        
        // 旅行会社情報のチェック
        if (travelAgencyYes && travelAgencyYes.checked) {
            const agencyRequiredFields = [
                { id: 'travel_agency_name', name: '旅行会社氏名' },
                { id: 'travel_agency_prefecture', name: '旅行会社都道府県' },
                { id: 'travel_agency_city', name: '旅行会社市区町村' },
                { id: 'travel_agency_phone', name: '旅行会社電話番号' },
                { id: 'contact_email', name: '担当者メールアドレス' }
            ];
            
            agencyRequiredFields.forEach(field => {
                const element = document.getElementById(field.id);
                if (element && !element.value.trim()) {
                    errors.push(field.name + 'は必須項目です。');
                }
            });
        }
        
        // 予約タイプごとの必須フィールドチェック
        const selectedReservationType = document.querySelector('input[name="reservation_type"]:checked');
        if (selectedReservationType) {
            const type = selectedReservationType.value;
            
            if (type === 'school') {
                const schoolFields = [
                    { id: 'school_name', name: '学校・団体名' },
                    { id: 'school_name_kana', name: '学校・団体名(ふりがな)' },
                    { id: 'grade', name: '学年' },
                    { id: 'class_count', name: 'クラス数' },
                    { id: 'student_count', name: '見学者人数(児童・生徒)' },
                    { id: 'supervisor_count', name: '見学者人数(引率)' }
                ];
                
                schoolFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (element && !element.value.trim()) {
                        errors.push(field.name + 'は必須項目です。');
                    }
                });
            } else if (type === 'student_recruit') {
                const recruitFields = [
                    { id: 'recruit_school_name', name: '学校名' },
                    { id: 'recruit_department', name: '学部' },
                    { id: 'recruit_grade', name: '学年' },
                    { id: 'recruit_visitor_count', name: '見学者様人数' }
                ];
                
                recruitFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (element && !element.value.trim()) {
                        errors.push(field.name + 'は必須項目です。');
                    }
                });
            } else if (['family', 'company', 'municipality', 'other'].includes(type)) {
                const generalFields = [
                    { id: 'company_name', name: '会社・団体名' },
                    { id: 'company_name_kana', name: '会社・団体名(ふりがな)' },
                    { id: 'adult_count', name: '見学者人数(大人)' },
                    { id: 'child_count', name: '見学者人数(子ども)' }
                ];
                
                generalFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (element && !element.value.trim()) {
                        errors.push(field.name + 'は必須項目です。');
                    }
                });
            }
        }
        
        // エラーがある場合はアラートを表示
        if (errors.length > 0) {
            alert('以下のエラーがあります：\n\n' + errors.join('\n'));
            return false;
        }
        
        return true;
    }
    
    // 見学者人数に応じた同行者フィールドの動的表示
    function updateCompanionFields() {
        const visitorCount = parseInt(document.getElementById('recruit_visitor_count').value) || 0;
        const companionFields = document.getElementById('companion_fields');
        
        // 既存の値を保存
        const existingValues = {};
        for (let i = 1; i < 10; i++) {
            const nameField = document.getElementById(`companion_name_${i}`);
            const deptField = document.getElementById(`companion_department_${i}`);
            if (nameField) existingValues[`companion_name_${i}`] = nameField.value;
            if (deptField) existingValues[`companion_department_${i}`] = deptField.value;
        }
        
        companionFields.innerHTML = '';
        
        if (visitorCount > 1) {
            for (let i = 1; i < visitorCount; i++) {
                const companionDiv = document.createElement('div');
                companionDiv.className = 'form-field';
                
                // 既存値を使用、なければ空文字
                const nameValue = existingValues[`companion_name_${i}`] || '';
                const deptValue = existingValues[`companion_department_${i}`] || '';
                
                companionDiv.innerHTML = `
                    <label class="form-label" style="align-items: flex-start;">
                        同行者様${i}
                    </label>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <div>
                            <label class="form-label" style="margin-right: 45px;">氏名 <span class="required">*</span></label>
                            <input type="text" name="companion_name_${i}" id="companion_name_${i}" class="form-input" style="width: 215px !important;" value="${nameValue}" required>
                        </div>
                        <div>
                            <label class="form-label" style="margin-right: 45px;">学部 <span class="required">*</span></label>
                            <input type="text" name="companion_department_${i}" id="companion_department_${i}" class="form-input" style="width: 215px !important;" value="${deptValue}" required>
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

    // 返信メールボタンの機能
    document.addEventListener('DOMContentLoaded', function() {
        const replyEmailBtn = document.getElementById('create_reply_email');
        const statusSelect = document.getElementById('reservation_status');
        
        function updateReplyEmailButton() {
            if (statusSelect && replyEmailBtn) {
                const status = statusSelect.value;
                // 管理画面では全てのステータスでボタンを表示し、
                // 実際の送信可否は返信メール画面でチェック
                replyEmailBtn.style.display = 'inline-block';
                replyEmailBtn.disabled = false;
                
                // ステータスに応じてボタンのスタイルを変更
                if (status === 'pending') {
                    replyEmailBtn.className = 'btn-reply-email btn-primary';
                    replyEmailBtn.textContent = '返信メールを作成';
                } else {
                    replyEmailBtn.className = 'btn-reply-email btn-secondary';
                    replyEmailBtn.textContent = '返信メールを作成';
                }
            }
        }
        
        // 初期状態の設定
        updateReplyEmailButton();
        
        // ステータス変更時の処理
        if (statusSelect) {
            statusSelect.addEventListener('change', updateReplyEmailButton);
        }
        
        // 返信メールボタンクリック時の処理
        if (replyEmailBtn) {
            replyEmailBtn.addEventListener('click', function() {
                const currentUrl = new URL(window.location.href);
                const reservationId = currentUrl.searchParams.get('reservation_id');
                
                if (!reservationId) {
                    alert('予約データを保存してから返信メールを作成してください。');
                    return;
                }
                
                // 返信メール作成画面へ遷移
                const replyUrl = 'admin.php?page=reply-email&reservation_id=' + reservationId;
                window.location.href = replyUrl;
            });
        }
    });
    </script>
    <?php
}