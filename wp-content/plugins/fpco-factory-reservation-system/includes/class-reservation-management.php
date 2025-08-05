<?php
/**
 * 予約管理クラス
 * 
 * 予約の追加・編集機能を提供
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 予約ステータス定数
 */
define('FPCO_RESERVATION_STATUS_NEW', 'new');           // 新規受付
define('FPCO_RESERVATION_STATUS_PENDING', 'pending');   // 確認中  
define('FPCO_RESERVATION_STATUS_APPROVED', 'approved'); // 承認
define('FPCO_RESERVATION_STATUS_REJECTED', 'rejected'); // 否認
define('FPCO_RESERVATION_STATUS_CANCELLED', 'cancelled'); // キャンセル

class FPCO_Reservation_Management {
    
    public function __construct() {
        // 管理画面メニューの追加
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // フォーム処理
        add_action('admin_init', array($this, 'handle_form_submission'));
        
        // スタイルの読み込み
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * 管理画面メニューを追加
     */
    public function add_admin_menu() {
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
                array($this, 'display_admin_page'),
                'dashicons-plus-alt',
                25
            );
        }
    }
    
    /**
     * スタイルとスクリプトの読み込み
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_reservation-management') {
            return;
        }
        
        wp_enqueue_style(
            'fpco-reservation-management-style',
            FPCO_RESERVATION_PLUGIN_URL . 'assets/css/reservation-management.css',
            array(),
            FPCO_RESERVATION_VERSION
        );
    }
    
    /**
     * フォーム送信処理
     */
    public function handle_form_submission() {
        if (!isset($_POST['submit_reservation']) || !isset($_POST['reservation_nonce'])) {
            return;
        }
        
        $result = $this->handle_reservation_form_submission();
        
        if ($result['success']) {
            set_transient('fpco_reservation_success_message', $result['message'], 30);
        } else {
            set_transient('fpco_reservation_error_message', implode('<br>', $result['errors']), 30);
            if (isset($result['field_errors'])) {
                set_transient('fpco_reservation_field_errors', $result['field_errors'], 30);
            }
        }
        
        // リダイレクト
        wp_redirect(admin_url('admin.php?page=reservation-management'));
        exit;
    }
    
    /**
     * 元のreservation-management.phpと同等のフォーム送信処理
     */
    private function handle_reservation_form_submission() {
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
        $validation_result = $this->validate_reservation_form($_POST);
        
        if (!empty($validation_result['errors'])) {
            return [
                'success' => false, 
                'errors' => $validation_result['errors'],
                'field_errors' => $validation_result['field_errors']
            ];
        }
        
        // データの準備
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
        $type_data = $this->get_type_specific_data($_POST);
        
        // 交通手段の処理
        $transportation_mapping = [
            'car' => 'car',
            'chartered_bus' => 'bus',
            'local_bus' => 'bus', 
            'taxi' => 'taxi',
            'other' => 'other'
        ];
        
        $transportation_input = isset($_POST['transportation']) ? sanitize_text_field($_POST['transportation']) : 'other';
        $transportation = isset($transportation_mapping[$transportation_input]) ? $transportation_mapping[$transportation_input] : 'other';
        
        $transportation_other_text = '';
        if ($transportation_input === 'other' && isset($_POST['transportation_other_text'])) {
            $transportation_other_text = sanitize_text_field($_POST['transportation_other_text'] ?? '');
        }

        // ステータス値の妥当性チェック
        $valid_statuses = $this->get_valid_reservation_statuses();
        $status_input = isset($_POST['reservation_status']) ? sanitize_text_field($_POST['reservation_status']) : FPCO_RESERVATION_STATUS_NEW;
        $status = in_array($status_input, $valid_statuses) ? $status_input : FPCO_RESERVATION_STATUS_NEW;
        
        // 実際のテーブル構造に合わせたデータ
        $data = [
            'factory_id' => intval($_POST['factory_id'] ?? 0),
            'date' => sanitize_text_field($_POST['visit_date'] ?? ''),
            'time_slot' => $time_slot,
            'applicant_name' => sanitize_text_field($_POST['applicant_name'] ?? ''),
            'applicant_kana' => sanitize_text_field($_POST['applicant_kana'] ?? ''),
            'is_travel_agency' => (isset($_POST['is_travel_agency']) && $_POST['is_travel_agency'] === 'yes') ? 1 : 0,
            'agency_data' => $agency_data,
            'reservation_type' => $this->get_reservation_type_enum($_POST),
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
    private function validate_reservation_form($data) {
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
        $valid_statuses = $this->get_valid_reservation_statuses();
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
        $type_validation = $this->validate_reservation_type_fields($data);
        $errors = array_merge($errors, $type_validation['errors']);
        $field_errors = array_merge($field_errors, $type_validation['field_errors']);
        
        return [
            'errors' => $errors,
            'field_errors' => $field_errors
        ];
    }
    
    /**
     * 予約タイプごとの必須フィールドチェック
     */
    private function validate_reservation_type_fields($data) {
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
    private function get_reservation_type_enum($data) {
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
    private function get_type_specific_data($data) {
        $type_data = [];
        
        // 交通機関の「その他」詳細を含める
        if (isset($data['transportation']) && $data['transportation'] === 'other' && 
            isset($data['transportation_other_text']) && !empty($data['transportation_other_text'])) {
            $type_data['transportation_other_detail'] = sanitize_text_field($data['transportation_other_text']);
        }
        
        switch ($data['reservation_type']) {
            case 'school':
                $type_data = [
                    'school_name' => sanitize_text_field($data['school_name'] ?? ''),
                    'school_name_kana' => sanitize_text_field($data['school_name_kana'] ?? ''),
                    'representative_name' => sanitize_text_field($data['representative_name'] ?? ''),
                    'representative_name_kana' => sanitize_text_field($data['representative_name_kana'] ?? ''),
                    'grade' => intval($data['grade'] ?? 0),
                    'class_count' => intval($data['class_count'] ?? 0),
                    'student_count' => intval($data['student_count'] ?? 0),
                    'supervisor_count' => intval($data['supervisor_count'] ?? 0)
                ];
                break;
                
            case 'student_recruit':
                $type_data = [
                    'recruit_school_name' => sanitize_text_field($data['recruit_school_name'] ?? ''),
                    'recruit_department' => sanitize_text_field($data['recruit_department'] ?? ''),
                    'recruit_grade' => sanitize_text_field($data['recruit_grade'] ?? ''),
                    'recruit_visitor_count' => intval($data['recruit_visitor_count'] ?? 0),
                ];
                
                // 同行者情報を追加
                if (!empty($data['recruit_visitor_count'])) {
                    $visitor_count = intval($data['recruit_visitor_count']);
                    for ($i = 1; $i < $visitor_count && $i <= 10; $i++) {
                        $type_data["companion_name_$i"] = sanitize_text_field($data["companion_name_$i"] ?? '');
                        $type_data["companion_department_$i"] = sanitize_text_field($data["companion_department_$i"] ?? '');
                    }
                }
                break;
                
            case 'family':
            case 'company':
            case 'municipality':
            case 'other':
                $type_data = [
                    'company_name' => sanitize_text_field($data['company_name'] ?? ''),
                    'company_name_kana' => sanitize_text_field($data['company_name_kana'] ?? ''),
                    'representative_name' => sanitize_text_field($data['representative_name'] ?? ''),
                    'representative_name_kana' => sanitize_text_field($data['representative_name_kana'] ?? ''),
                    'adult_count' => intval($data['adult_count'] ?? 0),
                    'child_count' => intval($data['child_count'] ?? 0),
                    'child_grade' => sanitize_text_field($data['child_grade'] ?? '')
                ];
                break;
        }
        
        return json_encode($type_data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 有効なステータス値を取得
     */
    private function get_valid_reservation_statuses() {
        return [
            FPCO_RESERVATION_STATUS_NEW,
            FPCO_RESERVATION_STATUS_PENDING,
            FPCO_RESERVATION_STATUS_APPROVED,
            FPCO_RESERVATION_STATUS_REJECTED,
            FPCO_RESERVATION_STATUS_CANCELLED
        ];
    }

    /**
     * ステータス表示名を取得
     */
    private function get_reservation_status_label($status) {
        $labels = [
            FPCO_RESERVATION_STATUS_NEW => '新規受付',
            FPCO_RESERVATION_STATUS_PENDING => '確認中',
            FPCO_RESERVATION_STATUS_APPROVED => '承認',
            FPCO_RESERVATION_STATUS_REJECTED => '否認',
            FPCO_RESERVATION_STATUS_CANCELLED => 'キャンセル'
        ];
        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * 予約データをフォームデータに変換
     */
    private function convert_reservation_to_form_data($reservation) {
        $form_data = [];
        
        // 基本情報
        $form_data['factory_id'] = $reservation['factory_id'] ?? '';
        $form_data['visit_date'] = $reservation['date'] ?? '';
        
        // 時間スロットの分解
        if (!empty($reservation['time_slot'])) {
            $time_parts = explode('-', $reservation['time_slot']);
            if (count($time_parts) >= 2) {
                $start_time = explode(':', $time_parts[0]);
                $end_time = explode(':', $time_parts[1]);
                
                $form_data['visit_time_start_hour'] = $start_time[0] ?? '';
                $form_data['visit_time_start_minute'] = $start_time[1] ?? '';
                $form_data['visit_time_end_hour'] = $end_time[0] ?? '';
                $form_data['visit_time_end_minute'] = $end_time[1] ?? '';
            }
        }
        
        // 申込者情報
        $form_data['applicant_name'] = $reservation['applicant_name'] ?? '';
        $form_data['applicant_kana'] = $reservation['applicant_kana'] ?? '';
        $form_data['is_travel_agency'] = $reservation['is_travel_agency'] ? 'yes' : 'no';
        
        // 旅行会社情報
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
        
        // 予約タイプの変換
        $type_mapping = [
            'school' => 'school',
            'personal' => 'student_recruit',
            'corporate' => 'company',
            'municipal' => 'municipality',
            'other' => 'other'
        ];
        $form_data['reservation_type'] = $type_mapping[$reservation['reservation_type'] ?? ''] ?? 'other';
        
        // タイプデータの展開
        if (!empty($reservation['type_data'])) {
            $type_data = json_decode($reservation['type_data'], true);
            if ($type_data) {
                foreach ($type_data as $key => $value) {
                    $form_data[$key] = $value;
                }
            }
        }
        
        // 連絡先情報
        $form_data['applicant_zip'] = $reservation['address_zip'] ?? '';
        $form_data['applicant_prefecture'] = $reservation['address_prefecture'] ?? '';
        $form_data['applicant_city'] = $reservation['address_city'] ?? '';
        $form_data['applicant_address'] = $reservation['address_street'] ?? '';
        $form_data['applicant_phone'] = $reservation['phone'] ?? '';
        $form_data['emergency_contact'] = $reservation['day_of_contact'] ?? '';
        $form_data['applicant_email'] = $reservation['email'] ?? '';
        
        // 交通手段
        $transportation_mapping = [
            'car' => 'car',
            'bus' => 'chartered_bus',
            'taxi' => 'taxi',
            'other' => 'other'
        ];
        $form_data['transportation'] = $transportation_mapping[$reservation['transportation_method'] ?? ''] ?? 'other';
        $form_data['vehicle_count'] = $reservation['transportation_count'] ?? '';
        
        // その他
        $form_data['visit_purpose'] = $reservation['purpose'] ?? '';
        $form_data['total_visitors'] = $reservation['participant_count'] ?? '';
        $form_data['elementary_visitors'] = $reservation['participants_child_count'] ?? '';
        $form_data['status'] = $reservation['status'] ?? '';
        
        return $form_data;
    }
    
    /**
     * 管理画面の表示
     */
    public function display_admin_page() {
        // インクルードファイルから管理画面表示を呼び出し
        include FPCO_RESERVATION_PLUGIN_DIR . 'includes/views/reservation-management-form.php';
    }
}

// インスタンスを作成
new FPCO_Reservation_Management();