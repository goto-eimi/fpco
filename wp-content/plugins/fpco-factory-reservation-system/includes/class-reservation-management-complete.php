<?php
/**
 * 予約管理クラス（完全版）
 * 
 * 予約の追加・編集機能を提供（元のreservation-management.phpの全機能を含む）
 */

if (!defined('ABSPATH')) {
    exit;
}

// 予約ステータス定数
define('FPCO_RESERVATION_STATUS_NEW', 'new');           // 新規受付
define('FPCO_RESERVATION_STATUS_PENDING', 'pending');   // 確認中  
define('FPCO_RESERVATION_STATUS_APPROVED', 'approved'); // 承認
define('FPCO_RESERVATION_STATUS_REJECTED', 'rejected'); // 否認
define('FPCO_RESERVATION_STATUS_CANCELLED', 'cancelled'); // キャンセル

class FPCO_Reservation_Management_Complete {
    
    public function __construct() {
        // 管理画面メニューの追加
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // フォーム処理
        add_action('admin_init', array($this, 'handle_form_submission'));
        
        // スタイルの読み込み
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Ajax処理
        add_action('wp_ajax_fpco_search_address', array($this, 'ajax_search_address'));
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
                'dashicons-clipboard',
                29  // 工場カレンダーメニューの前に配置
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
        
        wp_enqueue_script(
            'fpco-reservation-management-script',
            FPCO_RESERVATION_PLUGIN_URL . 'assets/js/reservation-management.js',
            array('jquery'),
            FPCO_RESERVATION_VERSION,
            true
        );
        
        // Ajax用の設定
        wp_localize_script('fpco-reservation-management-script', 'fpco_reservation_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fpco_reservation_nonce')
        ));
    }
    
    /**
     * フォーム送信処理
     */
    public function handle_form_submission() {
        if (!isset($_POST['submit_reservation']) || !isset($_POST['reservation_nonce'])) {
            return;
        }
        
        // Nonceチェック
        if (!wp_verify_nonce($_POST['reservation_nonce'], 'reservation_form')) {
            $this->set_error_and_redirect(['セキュリティチェックに失敗しました。']);
            return;
        }
        
        // 権限チェック
        if (!current_user_can('read')) {
            $this->set_error_and_redirect(['この操作を行う権限がありません。']);
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservations';
        
        // 編集モードかどうかチェック
        $is_edit_mode = isset($_POST['reservation_id']) && !empty($_POST['reservation_id']);
        $reservation_id = $is_edit_mode ? intval($_POST['reservation_id']) : null;
        
        // バリデーション
        $validation_result = $this->validate_reservation_form($_POST);
        
        if (!empty($validation_result['errors'])) {
            $this->set_error_and_redirect($validation_result['errors'], $validation_result['field_errors']);
            return;
        }
        
        // データの準備
        $data = $this->prepare_reservation_data($_POST);
        
        if ($is_edit_mode) {
            // 更新処理
            $result = $wpdb->update(
                $table_name, 
                $data, 
                ['id' => $reservation_id]
            );
            
            if ($result === false) {
                $this->set_error_and_redirect(['データベースへの更新に失敗しました。']);
            } else {
                $this->set_success_and_redirect('予約を正常に更新しました。予約番号: ' . $reservation_id);
            }
        } else {
            // 新規登録処理
            $result = $wpdb->insert($table_name, $data);
            
            if ($result === false) {
                $this->set_error_and_redirect(['データベースへの保存に失敗しました。']);
            } else {
                $this->set_success_and_redirect('予約を正常に登録しました。予約番号: ' . $wpdb->insert_id);
            }
        }
    }
    
    /**
     * 予約データの準備
     */
    private function prepare_reservation_data($post_data) {
        // 時間の組み立て
        $start_hour = str_pad(sanitize_text_field($post_data['visit_time_start_hour'] ?? ''), 2, '0', STR_PAD_LEFT);
        $start_minute = str_pad(sanitize_text_field($post_data['visit_time_start_minute'] ?? ''), 2, '0', STR_PAD_LEFT);
        $end_hour = str_pad(sanitize_text_field($post_data['visit_time_end_hour'] ?? ''), 2, '0', STR_PAD_LEFT);
        $end_minute = str_pad(sanitize_text_field($post_data['visit_time_end_minute'] ?? ''), 2, '0', STR_PAD_LEFT);
        
        $visit_time_start = $start_hour . ':' . $start_minute;
        $visit_time_end = $end_hour . ':' . $end_minute;
        $time_slot = $visit_time_start . '-' . $visit_time_end;
        
        // 旅行会社情報の処理
        $agency_data = null;
        if (isset($post_data['is_travel_agency']) && $post_data['is_travel_agency'] === 'yes') {
            $agency_data = wp_json_encode([
                'name' => sanitize_text_field($post_data['travel_agency_name'] ?? ''),
                'zip' => sanitize_text_field($post_data['travel_agency_zip'] ?? ''),
                'prefecture' => sanitize_text_field($post_data['travel_agency_prefecture'] ?? ''),
                'city' => sanitize_text_field($post_data['travel_agency_city'] ?? ''),
                'address' => sanitize_text_field($post_data['travel_agency_address'] ?? ''),
                'phone' => sanitize_text_field($post_data['travel_agency_phone'] ?? ''),
                'fax' => sanitize_text_field($post_data['travel_agency_fax'] ?? ''),
                'contact_mobile' => sanitize_text_field($post_data['contact_mobile'] ?? ''),
                'contact_email' => sanitize_email($post_data['contact_email'] ?? '')
            ], JSON_UNESCAPED_UNICODE);
        }
        
        // 予約タイプごとのデータ処理
        $type_data = $this->get_type_specific_data($post_data);
        
        // 交通手段の処理（DBのenumに合わせる: car, bus, taxi, other）
        $transportation_mapping = [
            'car' => 'car',
            'chartered_bus' => 'bus',
            'local_bus' => 'bus', 
            'taxi' => 'taxi',
            'other' => 'other'
        ];
        
        $transportation_input = isset($post_data['transportation']) ? sanitize_text_field($post_data['transportation']) : 'other';
        $transportation = isset($transportation_mapping[$transportation_input]) ? $transportation_mapping[$transportation_input] : 'other';
        
        // 交通手段がその他の場合、詳細をtype_dataに含める
        $transportation_other_text = '';
        if ($transportation_input === 'other' && isset($post_data['transportation_other_text'])) {
            $transportation_other_text = sanitize_text_field($post_data['transportation_other_text'] ?? '');
        }

        // ステータス値の妥当性チェック
        $valid_statuses = $this->get_valid_reservation_statuses();
        $status_input = isset($post_data['reservation_status']) ? sanitize_text_field($post_data['reservation_status']) : FPCO_RESERVATION_STATUS_NEW;
        $status = in_array($status_input, $valid_statuses) ? $status_input : FPCO_RESERVATION_STATUS_NEW;
        
        // 実際のテーブル構造に合わせたデータ
        return [
            'factory_id' => intval($post_data['factory_id'] ?? 0),
            'date' => sanitize_text_field($post_data['visit_date'] ?? ''),
            'time_slot' => $time_slot,
            'applicant_name' => sanitize_text_field($post_data['applicant_name'] ?? ''),
            'applicant_kana' => sanitize_text_field($post_data['applicant_kana'] ?? ''),
            'is_travel_agency' => (isset($post_data['is_travel_agency']) && $post_data['is_travel_agency'] === 'yes') ? 1 : 0,
            'agency_data' => $agency_data,
            'reservation_type' => $this->get_reservation_type_enum($post_data),
            'type_data' => $type_data,
            'address_zip' => sanitize_text_field($post_data['applicant_zip'] ?? ''),
            'address_prefecture' => sanitize_text_field($post_data['applicant_prefecture'] ?? ''),
            'address_city' => sanitize_text_field($post_data['applicant_city'] ?? ''),
            'address_street' => sanitize_text_field($post_data['applicant_address'] ?? ''),
            'phone' => sanitize_text_field($post_data['applicant_phone'] ?? ''),
            'day_of_contact' => sanitize_text_field($post_data['emergency_contact'] ?? ''),
            'email' => sanitize_email($post_data['applicant_email'] ?? ''),
            'transportation_method' => $transportation,
            'transportation_count' => intval($post_data['vehicle_count'] ?? 0),
            'purpose' => sanitize_textarea_field($post_data['visit_purpose'] ?? ''),
            'participant_count' => intval($post_data['total_visitors'] ?? 0),
            'participants_child_count' => intval($post_data['elementary_visitors'] ?? 0),
            'status' => $status
        ];
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
        if (isset($data['reservation_status']) && !in_array($data['reservation_status'], $valid_statuses)) {
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
        
        return ['errors' => $errors, 'field_errors' => $field_errors];
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
     * エラーを設定してリダイレクト
     */
    private function set_error_and_redirect($errors, $field_errors = []) {
        set_transient('fpco_reservation_errors', $errors, 60);
        set_transient('fpco_reservation_field_errors', $field_errors, 60);
        set_transient('fpco_reservation_form_data', $_POST, 60);
        wp_redirect(admin_url('admin.php?page=reservation-management&error=1'));
        exit;
    }
    
    /**
     * 成功メッセージを設定してリダイレクト
     */
    private function set_success_and_redirect($message) {
        set_transient('fpco_reservation_success_message', $message, 60);
        wp_redirect(admin_url('admin.php?page=reservation-management&success=1'));
        exit;
    }
    
    /**
     * フォームフィールドの値を取得するヘルパー関数
     */
    private function get_form_value($field_name, $form_data, $default = '') {
        return isset($form_data[$field_name]) ? esc_attr($form_data[$field_name] ?? '') : $default;
    }
    
    /**
     * ラジオボタンの選択状態を取得するヘルパー関数
     */
    private function is_radio_checked($field_name, $value, $form_data) {
        return isset($form_data[$field_name]) && $form_data[$field_name] === $value ? 'checked' : '';
    }
    
    /**
     * セレクトボックスの選択状態を取得するヘルパー関数
     */
    private function is_option_selected($field_name, $value, $form_data, $default = '') {
        if (isset($form_data[$field_name])) {
            return $form_data[$field_name] === $value ? 'selected' : '';
        }
        // フォームデータがない場合はデフォルト値をチェック
        return $value === $default ? 'selected' : '';
    }
    
    /**
     * フィールドごとのエラーメッセージを表示するヘルパー関数
     */
    private function display_field_error($field_name, $field_errors) {
        if (isset($field_errors[$field_name])) {
            echo '<div class="field-error">' . esc_html($field_errors[$field_name] ?? '') . '</div>';
        }
    }
    
    /**
     * フィールドにエラークラスを追加するヘルパー関数
     */
    private function get_field_error_class($field_name, $field_errors) {
        return isset($field_errors[$field_name]) ? 'error-field' : '';
    }
    
    /**
     * 予約データをフォームデータに変換
     */
    private function convert_reservation_to_form_data($reservation) {
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
        
        // 予約タイプの判定
        // 1. まずvisitor_categoryフィールドを確認（フロントエンドからの保存）
        if (!empty($reservation['visitor_category'])) {
            // visitor_categoryの値を直接使用
            if ($reservation['visitor_category'] === 'recruit') {
                $form_data['reservation_type'] = 'student_recruit';
            } elseif ($reservation['visitor_category'] === 'family') {
                $form_data['reservation_type'] = 'family';
            } elseif ($reservation['visitor_category'] === 'company') {
                $form_data['reservation_type'] = 'company';
            } elseif ($reservation['visitor_category'] === 'government') {
                $form_data['reservation_type'] = 'municipality';
            } else {
                $form_data['reservation_type'] = $reservation['visitor_category'];
            }
        } else {
            // 2. visitor_categoryがない場合は既存のマッピングを使用（後方互換性）
            $reservation_type_reverse_mapping = [
                'school' => 'school',
                'personal' => 'family', // デフォルトはfamily
                'corporate' => 'company',
                'municipal' => 'municipality',
                'other' => 'other'
            ];
            
            // 3. personalの場合は、type_dataから詳細を判定
            $db_type = $reservation['reservation_type'] ?? '';
            if ($db_type === 'personal' && !empty($reservation['type_data'])) {
                $type_data = json_decode($reservation['type_data'], true);
                if ($type_data && isset($type_data['school_name'])) {
                    // school_nameがあればリクルート
                    $form_data['reservation_type'] = 'student_recruit';
                } else {
                    $form_data['reservation_type'] = 'family';
                }
            } else {
                $form_data['reservation_type'] = $reservation_type_reverse_mapping[$db_type] ?? 'other';
            }
        }
        
        // タイプ別データ
        if (!empty($reservation['type_data'])) {
            $type_data = json_decode($reservation['type_data'], true);
            if ($type_data) {
                // リクルートタイプの場合は特別なフィールドマッピングを適用
                if (($form_data['reservation_type'] ?? '') === 'student_recruit') {
                    // フロントエンドのフィールド名を管理画面のフィールド名にマッピング
                    $recruit_mapping = [
                        'school_name' => 'recruit_school_name',
                        'department' => 'recruit_department',
                        'grade' => 'recruit_grade',
                        'visitor_count' => 'recruit_visitor_count'
                    ];
                    
                    foreach ($type_data as $key => $value) {
                        if (isset($recruit_mapping[$key])) {
                            $form_data[$recruit_mapping[$key]] = $value;
                        } else {
                            $form_data[$key] = $value;
                        }
                    }
                    
                    // 同行者情報の復元
                    if (isset($type_data['companions']) && is_array($type_data['companions'])) {
                        foreach ($type_data['companions'] as $index => $companion) {
                            $companion_num = $index + 1;
                            $form_data["companion_name_{$companion_num}"] = $companion['name'] ?? '';
                            $form_data["companion_department_{$companion_num}"] = $companion['department'] ?? '';
                        }
                    }
                } else {
                    // その他のタイプはそのまま展開
                    foreach ($type_data as $key => $value) {
                        $form_data[$key] = $value;
                    }
                }
            }
        }
        
        // form_dataフィールドも確認（フロントエンドからの完全なデータ）
        if (!empty($reservation['form_data'])) {
            $frontend_data = json_decode($reservation['form_data'], true);
            if ($frontend_data && isset($frontend_data['visitor_category'])) {
                // visitor_categoryを優先的に使用
                if ($frontend_data['visitor_category'] === 'recruit') {
                    $form_data['reservation_type'] = 'student_recruit';
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
     * 管理画面の表示
     */
    public function display_admin_page() {
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
                $form_data = $this->convert_reservation_to_form_data($reservation);
            } else {
                $errors[] = '指定された予約が見つかりません。';
            }
        }
        
        // transientからのメッセージ・データ取得
        if (isset($_GET['success'])) {
            $success_message = get_transient('fpco_reservation_success_message');
            delete_transient('fpco_reservation_success_message');
        }
        
        if (isset($_GET['error'])) {
            $errors = get_transient('fpco_reservation_errors') ?: [];
            $field_errors = get_transient('fpco_reservation_field_errors') ?: [];
            $form_data = get_transient('fpco_reservation_form_data') ?: [];
            
            delete_transient('fpco_reservation_errors');
            delete_transient('fpco_reservation_field_errors');
            delete_transient('fpco_reservation_form_data');
        }
        
        // ヘルパー関数をメソッドとして使用
        $get_form_value = array($this, 'get_form_value');
        $is_radio_checked = array($this, 'is_radio_checked');
        $is_option_selected = array($this, 'is_option_selected');
        $display_field_error = array($this, 'display_field_error');
        $get_field_error_class = array($this, 'get_field_error_class');
        
        // 管理画面のHTMLを表示（長いので別ファイルから読み込むことも可能）
        include FPCO_RESERVATION_PLUGIN_DIR . 'includes/views/reservation-management-form.php';
    }
    
    /**
     * Ajax: 住所検索
     */
    public function ajax_search_address() {
        // 実装は元のファイルのJavaScript部分を参照
        wp_die();
    }
}

// インスタンスを作成
new FPCO_Reservation_Management_Complete();