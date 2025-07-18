<?php
/**
 * Plugin Name: Factory Calendar Admin
 * Description: 工場見学カレンダー管理画面
 * Version: 1.0
 */

// プラグインの直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * プラグイン有効化時にテーブルを作成
 */
register_activation_hook(__FILE__, 'factory_calendar_create_tables');

function factory_calendar_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // 工場テーブル
    $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}factorys (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        capacity int(11) NOT NULL DEFAULT 50,
        manager_user_id bigint(20) UNSIGNED DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_manager (manager_user_id)
    ) $charset_collate;";
    
    // 見学不可日テーブル
    $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}unavailable_days (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        factory_id bigint(20) UNSIGNED NOT NULL,
        date date NOT NULL,
        am_unavailable tinyint(1) DEFAULT 0,
        pm_unavailable tinyint(1) DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY unique_factory_date (factory_id, date),
        KEY idx_factory (factory_id)
    ) $charset_collate;";
    
    // 予約テーブル
    $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}reservations (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        factory_id bigint(20) UNSIGNED NOT NULL,
        date date NOT NULL,
        time_slot varchar(11) NOT NULL,
        applicant_name varchar(255) NOT NULL,
        applicant_kana varchar(255) DEFAULT NULL,
        applicant_type enum('individual','group','school','company') DEFAULT 'individual',
        details longtext DEFAULT NULL,
        participants_total int(11) NOT NULL DEFAULT 1,
        participants_child int(11) NOT NULL DEFAULT 0,
        status enum('new','pending','approved','rejected','cancelled') DEFAULT 'new',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_factory_date (factory_id, date),
        KEY idx_status (status)
    ) $charset_collate;";
    
    // メール送信履歴テーブル
    $sql4 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}email_logs (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        reservation_id bigint(20) UNSIGNED NOT NULL,
        sender_user_id bigint(20) UNSIGNED NOT NULL,
        template_type varchar(50) DEFAULT NULL,
        subject varchar(255) NOT NULL,
        body longtext NOT NULL,
        sent_at datetime DEFAULT CURRENT_TIMESTAMP,
        status enum('sent','failed') DEFAULT 'sent',
        PRIMARY KEY (id),
        KEY idx_reservation (reservation_id),
        KEY idx_sender (sender_user_id),
        KEY idx_sent_at (sent_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
}

/**
 * 管理画面メニューを追加
 */
add_action('admin_menu', 'factory_calendar_admin_menu');

function factory_calendar_admin_menu() {
    global $wpdb;
    
    // 現在のユーザーを取得
    $current_user = wp_get_current_user();
    
    // 管理者またはfactoryロールのユーザーのみメニューを表示
    $can_access = false;
    $is_admin = false;
    
    // 管理者チェック（ユーザーID：1またはuser_login：admin）
    if ($current_user->ID == 1 || $current_user->user_login == 'admin' || current_user_can('manage_options')) {
        $can_access = true;
        $is_admin = true;
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
        // 工場一覧を取得
        if ($is_admin) {
            // 管理者の場合は全工場を取得（名前順）
            $factories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}factorys ORDER BY name ASC");
        } else {
            // 工場アカウントの場合は割り当てられた工場のみ取得
            $factories = array();
            if ($assigned_factory) {
                $factory = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}factorys WHERE id = %d",
                        $assigned_factory
                    )
                );
                if ($factory) {
                    $factories[] = $factory;
                }
            }
            
            // manager_user_idでも確認
            $managed_factories = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}factorys WHERE manager_user_id = %d",
                    $current_user->ID
                )
            );
            foreach ($managed_factories as $mf) {
                $factories[] = $mf;
            }
            
            // 重複を除去
            $factory_ids = array();
            $unique_factories = array();
            foreach ($factories as $f) {
                if (!in_array($f->id, $factory_ids)) {
                    $factory_ids[] = $f->id;
                    $unique_factories[] = $f;
                }
            }
            $factories = $unique_factories;
        }
        
        // 各工場ごとにメニューを追加
        $position = 30; // 開始位置
        foreach ($factories as $factory) {
            $menu_slug = 'factory-calendar-' . $factory->id;
            $menu_title = $factory->name . 'カレンダー';
            
            add_menu_page(
                $menu_title,
                $menu_title,
                'read',  // 権限を緩和
                $menu_slug,
                function() use ($factory) {
                    factory_calendar_admin_page($factory->id);
                },
                'dashicons-calendar-alt',
                $position++  // 位置を1ずつ増やす
            );
        }
    }
}

/**
 * 必要なスクリプトとスタイルを読み込む
 */
add_action('admin_enqueue_scripts', 'factory_calendar_admin_scripts');

function factory_calendar_admin_scripts($hook) {
    // 各工場のカレンダーページでスクリプトを読み込む
    if (!preg_match('/^toplevel_page_factory-calendar-\d+$/', $hook)) {
        return;
    }
    
    // FullCalendar
    wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', array(), '6.1.8');
    wp_enqueue_script('fullcalendar-ja', 'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/locales/ja.global.min.js', array('fullcalendar'), '6.1.8');
    
    // カスタムスクリプト
    wp_enqueue_script('factory-calendar-admin', plugin_dir_url(__FILE__) . 'admin.js', array('jquery', 'fullcalendar'), '1.0', true);
    
    // Ajax用のデータを渡す
    wp_localize_script('factory-calendar-admin', 'factory_calendar', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('factory_calendar_nonce')
    ));
}

/**
 * カレンダー管理画面の表示
 */
function factory_calendar_admin_page($factory_id = null) {
    global $wpdb;
    
    // 指定された工場IDの工場情報を取得
    if ($factory_id) {
        $factory = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}factorys WHERE id = %d",
                $factory_id
            )
        );
    } else {
        echo '<div class="wrap"><h1>エラー</h1><p>工場が指定されていません。</p></div>';
        return;
    }
    
    if (!$factory) {
        echo '<div class="wrap"><h1>エラー</h1><p>指定された工場が見つかりません。</p></div>';
        return;
    }
    ?>
    
    <div class="wrap" style="background-color: white; padding: 20px;">
        <div style="max-width: 1200px;">
            <h1 id="factory-name" style="font-size: 24px; margin-bottom: 10px;">
                <?php echo esc_html($factory->name); ?>カレンダー
            </h1>
            
            <div style="margin-bottom: 20px;">
                <span style="font-size: 16px;">予約可能人数：<strong id="factory-capacity"><?php echo esc_html($factory->capacity); ?></strong>名</span>
            </div>
            
            <!-- カレンダー表示エリア -->
            <div id="calendar" style="border: 1px solid #C0B6B3;"></div>
            
            <!-- 見学不可設定モーダル -->
            <div id="unavailable-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                 background: white; border: 2px solid #333; padding: 20px; z-index: 1000; box-shadow: 0 0 10px rgba(0,0,0,0.5); min-width: 300px;">
                <h3>見学不可設定</h3>
                <p>日付：<span id="modal-date" style="font-weight: bold;"></span></p>
                <div style="margin: 15px 0; padding: 15px; background-color: #f5f5f5;">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; cursor: pointer;">
                            <input type="checkbox" id="am-unavailable" style="margin-right: 10px;"> 
                            <strong>午前（AM）</strong>を見学不可にする
                        </label>
                    </div>
                    <div>
                        <label style="display: block; cursor: pointer;">
                            <input type="checkbox" id="pm-unavailable" style="margin-right: 10px;"> 
                            <strong>午後（PM）</strong>を見学不可にする
                        </label>
                    </div>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" id="save-unavailable" class="button button-primary" style="margin-right: 10px;">保存</button>
                    <button type="button" id="cancel-unavailable" class="button">キャンセル</button>
                </div>
            </div>
            <div id="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                 background: rgba(0,0,0,0.5); z-index: 999;"></div>
        </div>
    </div>
    
    <style>
    /* FullCalendarのスタイル調整 */
    .fc .fc-toolbar {
        background-color: #666 !important;
        color: white !important;
        padding: 10px !important;
        margin-bottom: 5px !important;
    }
    
    .fc .fc-toolbar-title {
        font-size: 18px !important;
        font-weight: bold !important;
    }
    
    .fc .fc-button {
        background-color: transparent !important;
        border: none !important;
        color: white !important;
        font-size: 20px !important;
        padding: 0 15px !important;
        text-shadow: none !important;
        font-weight: normal !important;
    }
    
    .fc .fc-button:hover {
        background-color: rgba(255,255,255,0.1) !important;
    }
    
    .fc .fc-button:focus {
        box-shadow: none !important;
    }
    
    .fc .fc-col-header-cell {
        background-color: #D7CCC8 !important;
        color: white !important;
        border: 1px solid #999 !important;
        padding: 10px 0 !important;
        font-size: 16px !important;
        font-weight: normal !important;
    }
    
    .fc .fc-col-header-cell .fc-col-header-cell-cushion {
        color: white !important;
    }
    
    .fc .fc-daygrid-day {
        border: 1px solid #999 !important;
        height: 100px !important;
    }
    
    .fc .fc-daygrid-day.fc-day-today {
        background-color: #f0f0f0 !important;
    }
    
    .fc .fc-daygrid-day-number {
        font-size: 14px !important;
        font-weight: bold !important;
        padding: 5px !important;
    }
    
    .fc .fc-event {
        border: none !important;
        background-color: transparent !important;
    }
    </style>
    
    <script>
    var currentFactoryId = <?php echo $factory->id; ?>;
    </script>
    <?php
}

/**
 * Ajax: カレンダーイベントを取得
 */
add_action('wp_ajax_get_calendar_events', 'factory_get_calendar_events');

function factory_get_calendar_events() {
    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'], 'factory_calendar_nonce')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    
    $factory_id = intval($_POST['factory_id']);
    $start = sanitize_text_field($_POST['start']);
    $end = sanitize_text_field($_POST['end']);
    
    $events = array();
    
    // 見学不可日を取得
    $unavailable_days = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}unavailable_days 
             WHERE factory_id = %d AND date BETWEEN %s AND %s",
            $factory_id,
            $start,
            $end
        )
    );
    
    foreach ($unavailable_days as $day) {
        $events[] = array(
            'id' => 'unavailable_' . $day->id,
            'title' => '',
            'start' => $day->date,
            'color' => 'transparent',
            'type' => 'unavailable',
            'am_unavailable' => (bool)$day->am_unavailable,
            'pm_unavailable' => (bool)$day->pm_unavailable
        );
    }
    
    wp_send_json_success($events);
}

/**
 * Ajax: 工場情報を取得
 */
add_action('wp_ajax_get_factory_info', 'factory_get_factory_info');

function factory_get_factory_info() {
    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'], 'factory_calendar_nonce')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    
    $factory_id = intval($_POST['factory_id']);
    
    $factory = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}factorys WHERE id = %d",
            $factory_id
        )
    );
    
    if ($factory) {
        wp_send_json_success(array(
            'name' => $factory->name,
            'capacity' => $factory->capacity
        ));
    } else {
        wp_send_json_error();
    }
}

/**
 * Ajax: 見学不可設定を保存
 */
add_action('wp_ajax_save_unavailable', 'factory_save_unavailable');

function factory_save_unavailable() {
    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'], 'factory_calendar_nonce')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    
    $factory_id = intval($_POST['factory_id']);
    $date = sanitize_text_field($_POST['date']);
    $am_unavailable = $_POST['am_unavailable'] === 'true' ? 1 : 0;
    $pm_unavailable = $_POST['pm_unavailable'] === 'true' ? 1 : 0;
    
    // 既存のレコードを確認
    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}unavailable_days 
             WHERE factory_id = %d AND date = %s",
            $factory_id,
            $date
        )
    );
    
    if ($am_unavailable || $pm_unavailable) {
        if ($existing) {
            // 更新
            $wpdb->update(
                $wpdb->prefix . 'unavailable_days',
                array(
                    'am_unavailable' => $am_unavailable,
                    'pm_unavailable' => $pm_unavailable
                ),
                array('id' => $existing->id)
            );
        } else {
            // 新規作成
            $wpdb->insert(
                $wpdb->prefix . 'unavailable_days',
                array(
                    'factory_id' => $factory_id,
                    'date' => $date,
                    'am_unavailable' => $am_unavailable,
                    'pm_unavailable' => $pm_unavailable
                )
            );
        }
    } else {
        // 両方とも見学可能な場合はレコードを削除
        if ($existing) {
            $wpdb->delete(
                $wpdb->prefix . 'unavailable_days',
                array('id' => $existing->id)
            );
        }
    }
    
    wp_send_json_success();
}

/**
 * Ajax: 見学不可情報を取得
 */
add_action('wp_ajax_get_unavailable_info', 'factory_get_unavailable_info');

function factory_get_unavailable_info() {
    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'], 'factory_calendar_nonce')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    
    $factory_id = intval($_POST['factory_id']);
    $date = sanitize_text_field($_POST['date']);
    
    $info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}unavailable_days 
             WHERE factory_id = %d AND date = %s",
            $factory_id,
            $date
        )
    );
    
    if ($info) {
        $result = array(
            'am_unavailable' => (bool)$info->am_unavailable,
            'pm_unavailable' => (bool)$info->pm_unavailable
        );
        wp_send_json_success($result);
    } else {
        $result = array(
            'am_unavailable' => false,
            'pm_unavailable' => false
        );
        wp_send_json_success($result);
    }
}