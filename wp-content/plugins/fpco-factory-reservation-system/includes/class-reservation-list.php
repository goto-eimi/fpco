<?php
/**
 * 予約一覧クラス
 * 
 * 予約一覧の表示機能を提供
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPCO_Reservation_List {
    
    public function __construct() {
        // 管理画面メニューの追加
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
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
                '予約一覧',
                '予約一覧',
                'read',  // 権限を緩和
                'reservation-list',
                array($this, 'display_admin_page'),
                'dashicons-list-view',
                26
            );
        }
    }
    
    /**
     * スタイルとスクリプトの読み込み
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_reservation-list') {
            return;
        }
        
        wp_enqueue_style(
            'fpco-reservation-list-style',
            FPCO_RESERVATION_PLUGIN_URL . 'assets/css/reservation-list.css',
            array(),
            FPCO_RESERVATION_VERSION
        );
    }
    
    /**
     * 予約ステータス表示名を取得
     */
    private function get_status_label($status) {
        $labels = array(
            'new' => '新規受付',
            'pending' => '確認中',
            'approved' => '承認',
            'rejected' => '否認',
            'cancelled' => 'キャンセル'
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
    
    /**
     * 工場名を取得
     */
    private function get_factory_name($factory_id) {
        global $wpdb;
        
        $factory = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}factorys WHERE id = %d",
                $factory_id
            )
        );
        
        return $factory ? $factory->name : '不明';
    }
    
    /**
     * 管理画面の表示
     */
    public function display_admin_page() {
        global $wpdb;
        
        // 現在のユーザーを取得
        $current_user = wp_get_current_user();
        $is_admin = ($current_user->ID == 1 || $current_user->user_login == 'admin' || current_user_can('manage_options'));
        
        // 表示する予約を取得
        if ($is_admin) {
            // 管理者の場合は全ての予約を表示
            $reservations = $wpdb->get_results(
                "SELECT r.*, f.name as factory_name 
                 FROM {$wpdb->prefix}reservations r 
                 LEFT JOIN {$wpdb->prefix}factorys f ON r.factory_id = f.id 
                 ORDER BY r.created_at DESC 
                 LIMIT 100"
            );
        } else {
            // 工場アカウントの場合は割り当てられた工場の予約のみ表示
            $assigned_factory = get_user_meta($current_user->ID, 'assigned_factory', true);
            
            if ($assigned_factory) {
                $reservations = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT r.*, f.name as factory_name 
                         FROM {$wpdb->prefix}reservations r 
                         LEFT JOIN {$wpdb->prefix}factorys f ON r.factory_id = f.id 
                         WHERE r.factory_id = %d 
                         ORDER BY r.created_at DESC 
                         LIMIT 100",
                        $assigned_factory
                    )
                );
            } else {
                $reservations = array();
            }
        }
        
        ?>
        <div class="wrap">
            <h1>予約一覧</h1>
            
            <?php if (empty($reservations)): ?>
                <p>予約がありません。</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column">ID</th>
                            <th scope="col" class="manage-column">工場</th>
                            <th scope="col" class="manage-column">見学日</th>
                            <th scope="col" class="manage-column">時間帯</th>
                            <th scope="col" class="manage-column">申込者</th>
                            <th scope="col" class="manage-column">メール</th>
                            <th scope="col" class="manage-column">人数</th>
                            <th scope="col" class="manage-column">ステータス</th>
                            <th scope="col" class="manage-column">申込日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td><?php echo esc_html($reservation->id); ?></td>
                                <td><?php echo esc_html($reservation->factory_name); ?></td>
                                <td><?php echo esc_html(date('Y年m月d日', strtotime($reservation->date))); ?></td>
                                <td><?php echo esc_html($reservation->time_slot); ?></td>
                                <td><?php echo esc_html($reservation->applicant_name); ?></td>
                                <td><?php echo esc_html($reservation->email); ?></td>
                                <td><?php echo esc_html($reservation->participant_count); ?>名</td>
                                <td>
                                    <span class="status-<?php echo esc_attr($reservation->status); ?>">
                                        <?php echo esc_html($this->get_status_label($reservation->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date('Y/m/d H:i', strtotime($reservation->created_at))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <style>
        .status-new {
            color: #0073aa;
            font-weight: bold;
        }
        
        .status-pending {
            color: #d63638;
            font-weight: bold;
        }
        
        .status-approved {
            color: #00a32a;
            font-weight: bold;
        }
        
        .status-rejected {
            color: #d63638;
        }
        
        .status-cancelled {
            color: #646970;
        }
        </style>
        <?php
    }
}

// インスタンスを作成
new FPCO_Reservation_List();