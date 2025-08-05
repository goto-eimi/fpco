<?php
/**
 * 予約管理クラス
 * 
 * 予約の追加・編集機能を提供
 */

if (!defined('ABSPATH')) {
    exit;
}

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
        if (!isset($_POST['action']) || $_POST['action'] !== 'save_reservation') {
            return;
        }
        
        // nonceチェック
        if (!wp_verify_nonce($_POST['nonce'], 'save_reservation_nonce')) {
            wp_die('Security check failed');
        }
        
        // データのバリデーション
        $validation_result = $this->validate_form_data($_POST);
        
        if ($validation_result['success']) {
            // データベースに保存
            $save_result = $this->save_reservation($_POST);
            
            if ($save_result) {
                // 成功メッセージを設定
                set_transient('fpco_reservation_success_message', '予約を正常に追加しました。', 30);
            } else {
                // エラーメッセージを設定
                set_transient('fpco_reservation_error_message', '予約の保存に失敗しました。', 30);
            }
        } else {
            // バリデーションエラーを設定
            set_transient('fpco_reservation_validation_errors', $validation_result['errors'], 30);
        }
        
        // リダイレクト
        wp_redirect(admin_url('admin.php?page=reservation-management'));
        exit;
    }
    
    /**
     * フォームデータのバリデーション
     */
    private function validate_form_data($data) {
        $errors = array();
        
        // 必須項目のチェック
        $required_fields = array(
            'factory_id' => '工場',
            'date' => '見学日',
            'time_slot' => '時間帯',
            'applicant_name' => '申込者氏名',
            'email' => 'メールアドレス'
        );
        
        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                $errors[] = $label . 'は必須項目です。';
            }
        }
        
        // メールアドレスの形式チェック
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'メールアドレスの形式が正しくありません。';
        }
        
        return array(
            'success' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * 予約データの保存
     */
    private function save_reservation($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'reservations';
        
        // 保存データの準備
        $reservation_data = array(
            'factory_id' => intval($data['factory_id']),
            'date' => sanitize_text_field($data['date']),
            'time_slot' => sanitize_text_field($data['time_slot']),
            'applicant_name' => sanitize_text_field($data['applicant_name']),
            'applicant_kana' => sanitize_text_field($data['applicant_kana'] ?? ''),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'participant_count' => intval($data['participant_count'] ?? 1),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // データベースに挿入
        $result = $wpdb->insert($table_name, $reservation_data);
        
        return $result !== false;
    }
    
    /**
     * 管理画面の表示
     */
    public function display_admin_page() {
        global $wpdb;
        
        // メッセージの取得
        $success_message = get_transient('fpco_reservation_success_message');
        $error_message = get_transient('fpco_reservation_error_message');
        $validation_errors = get_transient('fpco_reservation_validation_errors');
        
        // メッセージのクリア
        delete_transient('fpco_reservation_success_message');
        delete_transient('fpco_reservation_error_message');
        delete_transient('fpco_reservation_validation_errors');
        
        // 工場リストを取得
        $factories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}factorys ORDER BY name ASC");
        
        ?>
        <div class="wrap">
            <h1>予約追加</h1>
            
            <?php if ($success_message): ?>
                <div class="updated inline" style="margin: 20px 0; padding: 12px; background: #fff; border-left: 4px solid #46b450; box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);">
                    <p style="margin: 0.5em 0; font-size: 14px;"><?php echo esc_html($success_message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="error inline" style="margin: 20px 0; padding: 12px; background: #fff; border-left: 4px solid #dc3232; box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);">
                    <p style="margin: 0.5em 0; font-size: 14px;"><?php echo esc_html($error_message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($validation_errors): ?>
                <div class="error inline" style="margin: 20px 0; padding: 12px; background: #fff; border-left: 4px solid #dc3232; box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);">
                    <ul style="margin: 0.5em 0; font-size: 14px;">
                        <?php foreach ($validation_errors as $error): ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('save_reservation_nonce', 'nonce'); ?>
                <input type="hidden" name="action" value="save_reservation">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="factory_id">工場 <span style="color: red;">*</span></label></th>
                        <td>
                            <select name="factory_id" id="factory_id" required>
                                <option value="">選択してください</option>
                                <?php foreach ($factories as $factory): ?>
                                    <option value="<?php echo $factory->id; ?>">
                                        <?php echo esc_html($factory->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="date">見学日 <span style="color: red;">*</span></label></th>
                        <td>
                            <input type="date" name="date" id="date" required min="<?php echo date('Y-m-d'); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="time_slot">時間帯 <span style="color: red;">*</span></label></th>
                        <td>
                            <select name="time_slot" id="time_slot" required>
                                <option value="">選択してください</option>
                                <option value="09:00-10:00">AM (09:00-10:00)</option>
                                <option value="10:30-11:30">AM (10:30-11:30)</option>
                                <option value="14:00-15:00">PM (14:00-15:00)</option>
                                <option value="15:30-16:30">PM (15:30-16:30)</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="applicant_name">申込者氏名 <span style="color: red;">*</span></label></th>
                        <td>
                            <input type="text" name="applicant_name" id="applicant_name" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="applicant_kana">申込者氏名（カナ）</label></th>
                        <td>
                            <input type="text" name="applicant_kana" id="applicant_kana" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="email">メールアドレス <span style="color: red;">*</span></label></th>
                        <td>
                            <input type="email" name="email" id="email" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="phone">電話番号</label></th>
                        <td>
                            <input type="tel" name="phone" id="phone" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="participant_count">参加人数</label></th>
                        <td>
                            <input type="number" name="participant_count" id="participant_count" min="1" value="1">
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('新規追加'); ?>
            </form>
        </div>
        <?php
    }
}

// インスタンスを作成
new FPCO_Reservation_Management();