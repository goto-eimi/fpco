<?php
/**
 * 工場ユーザー管理クラス
 * 
 * 工場アカウントの管理機能を提供
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPCO_Factory_User_Management {
    
    public function __construct() {
        // 管理画面メニューの追加（管理者のみ）
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
        // 管理者のみアクセス可能
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_menu_page(
            '工場ユーザー管理',
            '工場ユーザー管理',
            'manage_options',
            'factory-user-management',
            array($this, 'display_admin_page'),
            'dashicons-admin-users',
            28
        );
    }
    
    /**
     * スタイルとスクリプトの読み込み
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_factory-user-management') {
            return;
        }
        
        wp_enqueue_style(
            'fpco-factory-user-management-style',
            FPCO_RESERVATION_PLUGIN_URL . 'assets/css/factory-user-management.css',
            array(),
            FPCO_RESERVATION_VERSION
        );
    }
    
    /**
     * フォーム送信処理
     */
    public function handle_form_submission() {
        if (!isset($_POST['action'])) {
            return;
        }
        
        // nonceチェック
        if (!wp_verify_nonce($_POST['nonce'], 'factory_user_management_nonce')) {
            wp_die('Security check failed');
        }
        
        $action = $_POST['action'];
        
        switch ($action) {
            case 'create_factory_user':
                $result = $this->create_factory_user($_POST);
                break;
            case 'update_factory_assignment':
                $result = $this->update_factory_assignment($_POST);
                break;
            default:
                return;
        }
        
        if ($result) {
            set_transient('fpco_factory_user_success_message', '操作が正常に完了しました。', 30);
        } else {
            set_transient('fpco_factory_user_error_message', '操作に失敗しました。', 30);
        }
        
        // リダイレクト
        wp_redirect(admin_url('admin.php?page=factory-user-management'));
        exit;
    }
    
    /**
     * 工場ユーザーの作成
     */
    private function create_factory_user($data) {
        $username = sanitize_user($data['username']);
        $email = sanitize_email($data['email']);
        $password = $data['password'];
        $factory_id = intval($data['factory_id']);
        
        // ユーザー作成
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return false;
        }
        
        // ロールをfactoryに設定
        $user = new WP_User($user_id);
        $user->set_role('factory');
        
        // 工場を割り当て
        update_user_meta($user_id, 'assigned_factory', $factory_id);
        
        return true;
    }
    
    /**
     * 工場の割り当て更新
     */
    private function update_factory_assignment($data) {
        $user_id = intval($data['user_id']);
        $factory_id = intval($data['factory_id']);
        
        // 工場の割り当てを更新
        update_user_meta($user_id, 'assigned_factory', $factory_id);
        
        return true;
    }
    
    /**
     * 管理画面の表示
     */
    public function display_admin_page() {
        global $wpdb;
        
        // メッセージの取得
        $success_message = get_transient('fpco_factory_user_success_message');
        $error_message = get_transient('fpco_factory_user_error_message');
        
        // メッセージのクリア
        delete_transient('fpco_factory_user_success_message');
        delete_transient('fpco_factory_user_error_message');
        
        // 工場リストを取得
        $factories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}factorys ORDER BY name ASC");
        
        // 工場ユーザー（factoryロール）を取得
        $factory_users = get_users(array('role' => 'factory'));
        
        ?>
        <div class="wrap">
            <h1>工場ユーザー管理</h1>
            
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
            
            <h2>工場ユーザー一覧</h2>
            
            <?php if (empty($factory_users)): ?>
                <p>工場ユーザーがありません。</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column">ユーザー名</th>
                            <th scope="col" class="manage-column">メールアドレス</th>
                            <th scope="col" class="manage-column">割り当て工場</th>
                            <th scope="col" class="manage-column">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($factory_users as $user): ?>
                            <?php
                            $assigned_factory_id = get_user_meta($user->ID, 'assigned_factory', true);
                            $assigned_factory_name = '';
                            
                            if ($assigned_factory_id) {
                                foreach ($factories as $factory) {
                                    if ($factory->id == $assigned_factory_id) {
                                        $assigned_factory_name = $factory->name;
                                        break;
                                    }
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html($user->user_login); ?></td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td>
                                    <form method="post" action="" style="display: inline;">
                                        <?php wp_nonce_field('factory_user_management_nonce', 'nonce'); ?>
                                        <input type="hidden" name="action" value="update_factory_assignment">
                                        <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                        
                                        <select name="factory_id" onchange="this.form.submit()">
                                            <option value="">選択してください</option>
                                            <?php foreach ($factories as $factory): ?>
                                                <option value="<?php echo $factory->id; ?>" 
                                                        <?php selected($assigned_factory_id, $factory->id); ?>>
                                                    <?php echo esc_html($factory->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>" class="button">編集</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <h2>新しい工場ユーザーを作成</h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('factory_user_management_nonce', 'nonce'); ?>
                <input type="hidden" name="action" value="create_factory_user">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="username">ユーザー名</label></th>
                        <td>
                            <input type="text" name="username" id="username" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="email">メールアドレス</label></th>
                        <td>
                            <input type="email" name="email" id="email" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="password">パスワード</label></th>
                        <td>
                            <input type="password" name="password" id="password" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="factory_id">割り当て工場</label></th>
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
                </table>
                
                <?php submit_button('ユーザー作成'); ?>
            </form>
        </div>
        <?php
    }
}

// インスタンスを作成
new FPCO_Factory_User_Management();