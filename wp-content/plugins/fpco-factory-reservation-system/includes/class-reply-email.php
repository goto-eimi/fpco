<?php
/**
 * 返信メール機能クラス
 * 
 * 予約に対する返信メール送信機能を提供
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPCO_Reply_Email {
    
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
                '返信メール',
                '返信メール',
                'read',  // 権限を緩和
                'reply-email',
                array($this, 'display_admin_page'),
                'dashicons-email',
                27
            );
        }
    }
    
    /**
     * スタイルとスクリプトの読み込み
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_reply-email') {
            return;
        }
        
        wp_enqueue_style(
            'fpco-reply-email-style',
            FPCO_RESERVATION_PLUGIN_URL . 'assets/css/reply-email.css',
            array(),
            FPCO_RESERVATION_VERSION
        );
    }
    
    /**
     * フォーム送信処理
     */
    public function handle_form_submission() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'send_reply_email') {
            return;
        }
        
        // nonceチェック
        if (!wp_verify_nonce($_POST['nonce'], 'send_reply_email_nonce')) {
            wp_die('Security check failed');
        }
        
        // メール送信処理
        $result = $this->send_reply_email($_POST);
        
        if ($result) {
            // 成功メッセージを設定
            set_transient('fpco_reply_email_success_message', 'メールを正常に送信しました。', 30);
        } else {
            // エラーメッセージを設定
            set_transient('fpco_reply_email_error_message', 'メールの送信に失敗しました。', 30);
        }
        
        // リダイレクト
        wp_redirect(admin_url('admin.php?page=reply-email'));
        exit;
    }
    
    /**
     * 返信メールの送信
     */
    private function send_reply_email($data) {
        global $wpdb;
        
        $reservation_id = intval($data['reservation_id']);
        $subject = sanitize_text_field($data['subject']);
        $message = sanitize_textarea_field($data['message']);
        
        // 予約情報を取得
        $reservation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT r.*, f.name as factory_name 
                 FROM {$wpdb->prefix}reservations r 
                 LEFT JOIN {$wpdb->prefix}factorys f ON r.factory_id = f.id 
                 WHERE r.id = %d",
                $reservation_id
            )
        );
        
        if (!$reservation) {
            return false;
        }
        
        // メール送信
        $to = $reservation->email;
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        $mail_result = wp_mail($to, $subject, $message, $headers);
        
        // メール送信履歴を保存
        if ($mail_result) {
            $current_user = wp_get_current_user();
            
            $wpdb->insert(
                $wpdb->prefix . 'email_logs',
                array(
                    'reservation_id' => $reservation_id,
                    'sender_user_id' => $current_user->ID,
                    'template_type' => 'reply',
                    'subject' => $subject,
                    'body' => $message,
                    'sent_at' => current_time('mysql'),
                    'status' => 'sent'
                )
            );
        }
        
        return $mail_result;
    }
    
    /**
     * 管理画面の表示
     */
    public function display_admin_page() {
        global $wpdb;
        
        // メッセージの取得
        $success_message = get_transient('fpco_reply_email_success_message');
        $error_message = get_transient('fpco_reply_email_error_message');
        
        // メッセージのクリア
        delete_transient('fpco_reply_email_success_message');
        delete_transient('fpco_reply_email_error_message');
        
        // 現在のユーザーを取得
        $current_user = wp_get_current_user();
        $is_admin = ($current_user->ID == 1 || $current_user->user_login == 'admin' || current_user_can('manage_options'));
        
        // 予約一覧を取得（pending状態のもの）
        if ($is_admin) {
            $reservations = $wpdb->get_results(
                "SELECT r.*, f.name as factory_name 
                 FROM {$wpdb->prefix}reservations r 
                 LEFT JOIN {$wpdb->prefix}factorys f ON r.factory_id = f.id 
                 WHERE r.status = 'pending' 
                 ORDER BY r.created_at DESC"
            );
        } else {
            $assigned_factory = get_user_meta($current_user->ID, 'assigned_factory', true);
            
            if ($assigned_factory) {
                $reservations = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT r.*, f.name as factory_name 
                         FROM {$wpdb->prefix}reservations r 
                         LEFT JOIN {$wpdb->prefix}factorys f ON r.factory_id = f.id 
                         WHERE r.factory_id = %d AND r.status = 'pending' 
                         ORDER BY r.created_at DESC",
                        $assigned_factory
                    )
                );
            } else {
                $reservations = array();
            }
        }
        
        ?>
        <div class="wrap">
            <h1>返信メール</h1>
            
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
            
            <?php if (empty($reservations)): ?>
                <p>返信対象の予約がありません。</p>
            <?php else: ?>
                <form method="post" action="">
                    <?php wp_nonce_field('send_reply_email_nonce', 'nonce'); ?>
                    <input type="hidden" name="action" value="send_reply_email">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="reservation_id">対象予約</label></th>
                            <td>
                                <select name="reservation_id" id="reservation_id" required onchange="updateReservationInfo()">
                                    <option value="">選択してください</option>
                                    <?php foreach ($reservations as $reservation): ?>
                                        <option value="<?php echo $reservation->id; ?>" 
                                                data-name="<?php echo esc_attr($reservation->applicant_name); ?>"
                                                data-email="<?php echo esc_attr($reservation->email); ?>"
                                                data-factory="<?php echo esc_attr($reservation->factory_name); ?>"
                                                data-date="<?php echo esc_attr($reservation->date); ?>"
                                                data-time="<?php echo esc_attr($reservation->time_slot); ?>">
                                            <?php echo esc_html($reservation->id . ' - ' . $reservation->applicant_name . ' (' . $reservation->factory_name . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="reservation-info" style="margin-top: 10px; padding: 10px; background: #f9f9f9; display: none;">
                                    <p><strong>申込者:</strong> <span id="info-name"></span></p>
                                    <p><strong>メール:</strong> <span id="info-email"></span></p>
                                    <p><strong>工場:</strong> <span id="info-factory"></span></p>
                                    <p><strong>見学日:</strong> <span id="info-date"></span></p>
                                    <p><strong>時間帯:</strong> <span id="info-time"></span></p>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="subject">件名</label></th>
                            <td>
                                <input type="text" name="subject" id="subject" class="large-text" 
                                       value="【エフピコ】工場見学予約について" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="message">メッセージ</label></th>
                            <td>
                                <textarea name="message" id="message" rows="10" class="large-text" required></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('メール送信'); ?>
                </form>
            <?php endif; ?>
        </div>
        
        <script>
        function updateReservationInfo() {
            var select = document.getElementById('reservation_id');
            var option = select.options[select.selectedIndex];
            var infoDiv = document.getElementById('reservation-info');
            
            if (option.value) {
                document.getElementById('info-name').textContent = option.dataset.name;
                document.getElementById('info-email').textContent = option.dataset.email;
                document.getElementById('info-factory').textContent = option.dataset.factory;
                document.getElementById('info-date').textContent = option.dataset.date;
                document.getElementById('info-time').textContent = option.dataset.time;
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        }
        </script>
        <?php
    }
}

// インスタンスを作成
new FPCO_Reply_Email();