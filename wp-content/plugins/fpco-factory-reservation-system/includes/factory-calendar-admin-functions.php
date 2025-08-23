<?php
/**
 * 工場カレンダー管理機能（統合プラグイン版）
 * 元のfactory-reservation-manager/factory-calendar-admin.phpから移植
 */

// プラグインの直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 管理画面メニューを追加
 */
add_action('admin_menu', 'fpco_factory_calendar_admin_menu');

function fpco_factory_calendar_admin_menu() {
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
                    fpco_factory_calendar_admin_page($factory->id);
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
add_action('admin_enqueue_scripts', 'fpco_factory_calendar_admin_scripts');

function fpco_factory_calendar_admin_scripts($hook) {
    // 各工場のカレンダーページでスクリプトを読み込む
    if (!preg_match('/^toplevel_page_factory-calendar-\d+$/', $hook)) {
        return;
    }
    
    // FullCalendar（ローカルファイル）
    wp_enqueue_script('fullcalendar', FPCO_RESERVATION_PLUGIN_URL . 'assets/js/fullcalendar/fullcalendar.min.js', array(), '6.1.8');
    wp_enqueue_script('fullcalendar-ja', FPCO_RESERVATION_PLUGIN_URL . 'assets/js/fullcalendar/fullcalendar-ja.min.js', array('fullcalendar'), '6.1.8');
    
    // カスタムスクリプト（キャッシュ回避のため現在時刻を追加）
    wp_enqueue_script('factory-calendar-admin', FPCO_RESERVATION_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'fullcalendar'), '1.0.1.' . time(), true);
    
    // Ajax用のデータを渡す
    wp_localize_script('factory-calendar-admin', 'factory_calendar', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('factory_calendar_nonce')
    ));
}

/**
 * カレンダー管理画面の表示
 */
function fpco_factory_calendar_admin_page($factory_id = null) {
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
add_action('wp_ajax_get_calendar_events', 'fpco_factory_get_calendar_events');

function fpco_factory_get_calendar_events() {
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
    
    // 予約がある日を取得（タイムスタンプも含める）
    $reservations = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT date, time_slot, created_at, updated_at FROM {$wpdb->prefix}reservations 
             WHERE factory_id = %d AND date BETWEEN %s AND %s 
             AND status NOT IN ('cancelled', 'rejected')",
            $factory_id,
            $start,
            $end
        )
    );
    
    // 予約がある日を配列に整理（タイムスタンプも含める）
    $reservation_days = array();
    foreach ($reservations as $reservation) {
        $date = $reservation->date;
        if (!isset($reservation_days[$date])) {
            $reservation_days[$date] = array(
                'am' => false, 
                'pm' => false,
                'latest_am_timestamp' => null,
                'latest_pm_timestamp' => null
            );
        }
        
        $reservation_timestamp = strtotime($reservation->updated_at ?: $reservation->created_at);
        
        // 時間帯を判定（time_slotから開始時間を抽出）
        $time_slot = $reservation->time_slot;
        if (preg_match('/^(\d{1,2}):/', $time_slot, $matches)) {
            $hour = intval($matches[1]);
            if ($hour < 12) {
                $reservation_days[$date]['am'] = true;
                if (!$reservation_days[$date]['latest_am_timestamp'] || $reservation_timestamp > $reservation_days[$date]['latest_am_timestamp']) {
                    $reservation_days[$date]['latest_am_timestamp'] = $reservation_timestamp;
                }
            } else {
                $reservation_days[$date]['pm'] = true;
                if (!$reservation_days[$date]['latest_pm_timestamp'] || $reservation_timestamp > $reservation_days[$date]['latest_pm_timestamp']) {
                    $reservation_days[$date]['latest_pm_timestamp'] = $reservation_timestamp;
                }
            }
        }
    }
    
    // 見学不可日の配列を作成（タイムスタンプ比較ロジック適用）
    $unavailable_array = array();
    
    // 手動設定日を処理
    foreach ($unavailable_days as $day) {
        $date = $day->date;
        $manual_timestamp = strtotime($day->updated_at ?: $day->created_at);
        
        // タイムスタンプ比較による最終判定
        $am_unavailable = (bool)$day->am_unavailable;
        $pm_unavailable = (bool)$day->pm_unavailable;
        
        if (isset($reservation_days[$date])) {
            // AM時間帯の判定
            if ($reservation_days[$date]['am'] && $reservation_days[$date]['latest_am_timestamp'] && 
                $reservation_days[$date]['latest_am_timestamp'] > $manual_timestamp) {
                $am_unavailable = true; // 予約の方が新しい場合は強制的に見学不可
            }
            // 手動設定が新しい場合は手動設定を完全に優先（予約があっても手動設定に従う）
            
            // PM時間帯の判定
            if ($reservation_days[$date]['pm'] && $reservation_days[$date]['latest_pm_timestamp'] && 
                $reservation_days[$date]['latest_pm_timestamp'] > $manual_timestamp) {
                $pm_unavailable = true; // 予約の方が新しい場合は強制的に見学不可
            }
            // 手動設定が新しい場合は手動設定を完全に優先（予約があっても手動設定に従う）
        }
        
        $unavailable_array[$date] = array(
            'id' => $day->id,
            'am_unavailable' => $am_unavailable,
            'pm_unavailable' => $pm_unavailable
        );
    }
    
    // 手動設定がない日で予約がある日を処理
    foreach ($reservation_days as $date => $times) {
        if (!isset($unavailable_array[$date])) {
            $unavailable_array[$date] = array(
                'id' => null,
                'am_unavailable' => $times['am'],
                'pm_unavailable' => $times['pm']
            );
        }
    }
    
    // イベント配列を作成
    foreach ($unavailable_array as $date => $data) {
        $events[] = array(
            'id' => $data['id'] ? 'unavailable_' . $data['id'] : 'reservation_' . $date,
            'title' => '',
            'start' => $date,
            'color' => 'transparent',
            'type' => 'unavailable',
            'am_unavailable' => $data['am_unavailable'],
            'pm_unavailable' => $data['pm_unavailable'],
            'has_reservation' => isset($reservation_days[$date])
        );
    }
    
    wp_send_json_success($events);
}

/**
 * Ajax: 工場情報を取得
 */
add_action('wp_ajax_get_factory_info', 'fpco_factory_get_factory_info');

function fpco_factory_get_factory_info() {
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
 * 過去の見学不可データを定期的にクリーンアップ
 */
add_action('wp', 'fpco_factory_cleanup_old_unavailable_data');

function fpco_factory_cleanup_old_unavailable_data() {
    // 1日1回実行（WordPressのtransientを使用）
    if (get_transient('fpco_factory_cleanup_unavailable_done')) {
        return;
    }
    
    global $wpdb;
    
    // 過去の日付のデータを削除
    $deleted = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}unavailable_days WHERE date < %s",
            current_time('Y-m-d')
        )
    );
    
    // 24時間後まで実行を停止
    set_transient('fpco_factory_cleanup_unavailable_done', true, DAY_IN_SECONDS);
}

/**
 * Ajax: 見学不可設定を保存
 */
add_action('wp_ajax_save_unavailable', 'fpco_factory_save_unavailable');

function fpco_factory_save_unavailable() {
    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'], 'factory_calendar_nonce')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    
    $factory_id = intval($_POST['factory_id']);
    $date = sanitize_text_field($_POST['date']);
    $am_unavailable = $_POST['am_unavailable'] === 'true' ? 1 : 0;
    $pm_unavailable = $_POST['pm_unavailable'] === 'true' ? 1 : 0;
    
    // テーブル存在確認
    $table_name = $wpdb->prefix . 'unavailable_days';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    // テーブルが存在しない場合は作成
    if (!$table_exists) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            factory_id bigint(20) UNSIGNED NOT NULL,
            date date NOT NULL,
            am_unavailable tinyint(1) DEFAULT 0,
            pm_unavailable tinyint(1) DEFAULT 0,
            is_manual tinyint(1) DEFAULT 1 COMMENT '手動設定かどうか（1:手動, 0:自動）',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_factory_date (factory_id, date),
            KEY idx_factory (factory_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    } else {
        // 既存テーブルにカラムを追加（存在しない場合のみ）
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        $column_names = wp_list_pluck($columns, 'Field');
        
        if (!in_array('is_manual', $column_names)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN is_manual tinyint(1) DEFAULT 1 COMMENT '手動設定かどうか（1:手動, 0:自動）'");
        }
        
        if (!in_array('created_at', $column_names)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN created_at timestamp DEFAULT CURRENT_TIMESTAMP");
        }
        
        if (!in_array('updated_at', $column_names)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
    }
    
    // 過去の日付のデータを削除（今日より前の日付）
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}unavailable_days WHERE date < %s",
            current_time('Y-m-d')
        )
    );
    
    // 既存のレコードを確認
    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}unavailable_days 
             WHERE factory_id = %d AND date = %s",
            $factory_id,
            $date
        )
    );
    
    // 手動設定を常に保存（チェックがない場合も記録として残す）
    if ($existing) {
        // 更新
        $wpdb->update(
            $wpdb->prefix . 'unavailable_days',
            array(
                'am_unavailable' => $am_unavailable,
                'pm_unavailable' => $pm_unavailable,
                'is_manual' => 1
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
                'pm_unavailable' => $pm_unavailable,
                'is_manual' => 1
            )
        );
    }
    
    // データベースエラーがある場合はエラーレスポンスを返す
    if ($wpdb->last_error) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
    } else {
        wp_send_json_success(array(
            'message' => 'Saved successfully',
            'debug_saved_values' => array(
                'factory_id' => $factory_id,
                'date' => $date,
                'am_unavailable' => $am_unavailable,
                'pm_unavailable' => $pm_unavailable
            )
        ));
    }
}

/**
 * Ajax: 見学不可情報を取得
 */
add_action('wp_ajax_get_unavailable_info', 'fpco_factory_get_unavailable_info');

function fpco_factory_get_unavailable_info() {
    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'], 'factory_calendar_nonce')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    
    $factory_id = intval($_POST['factory_id']);
    $date = sanitize_text_field($_POST['date']);
    
    // 見学不可日の情報を取得
    $info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}unavailable_days 
             WHERE factory_id = %d AND date = %s",
            $factory_id,
            $date
        )
    );
    
    // 予約情報を取得して時間帯とタイムスタンプを判定
    $reservations = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT time_slot, created_at, updated_at FROM {$wpdb->prefix}reservations 
             WHERE factory_id = %d AND date = %s 
             AND status NOT IN ('cancelled', 'rejected')",
            $factory_id,
            $date
        )
    );
    
    // 予約による見学不可を判定と最新の予約タイムスタンプ取得
    $has_am_reservation = false;
    $has_pm_reservation = false;
    $latest_am_reservation_time = null;
    $latest_pm_reservation_time = null;
    
    foreach ($reservations as $reservation) {
        $time_slot = $reservation->time_slot;
        $reservation_timestamp = strtotime($reservation->updated_at ?: $reservation->created_at);
        
        if (preg_match('/^(\d{1,2}):/', $time_slot, $matches)) {
            $hour = intval($matches[1]);
            if ($hour < 12) {
                $has_am_reservation = true;
                if (!$latest_am_reservation_time || $reservation_timestamp > $latest_am_reservation_time) {
                    $latest_am_reservation_time = $reservation_timestamp;
                }
            } else {
                $has_pm_reservation = true;
                if (!$latest_pm_reservation_time || $reservation_timestamp > $latest_pm_reservation_time) {
                    $latest_pm_reservation_time = $reservation_timestamp;
                }
            }
        }
    }
    
    // タイムスタンプ比較による優先度判定ロジック
    if ($info) {
        // 手動設定のタイムスタンプを取得
        $manual_setting_timestamp = strtotime($info->updated_at ?: $info->created_at);
        
        // AM時間帯の判定：手動設定 vs 最新予約のタイムスタンプを比較
        if ($has_am_reservation && $latest_am_reservation_time && $latest_am_reservation_time > $manual_setting_timestamp) {
            // 予約の方が新しい場合：予約による自動チェック
            $am_unavailable = true;
        } else {
            // 手動設定の方が新しいか同等の場合：手動設定を完全に優先
            $am_unavailable = (bool)$info->am_unavailable;
        }
        
        // PM時間帯の判定：手動設定 vs 最新予約のタイムスタンプを比較
        if ($has_pm_reservation && $latest_pm_reservation_time && $latest_pm_reservation_time > $manual_setting_timestamp) {
            // 予約の方が新しい場合：予約による自動チェック
            $pm_unavailable = true;
        } else {
            // 手動設定の方が新しいか同等の場合：手動設定を完全に優先
            $pm_unavailable = (bool)$info->pm_unavailable;
        }
        
        $is_manual_setting = (bool)($info->is_manual ?? true);
    } else {
        // 設定がない場合は予約があれば自動チェック
        $am_unavailable = $has_am_reservation;
        $pm_unavailable = $has_pm_reservation;
        $is_manual_setting = false;
    }
    
    $result = array(
        'has_data' => $info !== null || !empty($reservations),
        'am_unavailable' => $am_unavailable,
        'pm_unavailable' => $pm_unavailable,
        'has_reservation' => !empty($reservations),
        'has_am_reservation' => $has_am_reservation,
        'has_pm_reservation' => $has_pm_reservation,
        // デバッグ情報
        'debug_info' => $info ? $info : null,
        'debug_has_manual_setting' => $info !== null,
        'debug_calculation_details' => array(
            'manual_data_found' => $info !== null,
            'is_manual_setting' => isset($is_manual_setting) ? $is_manual_setting : null,
            'manual_am_value' => $info ? (bool)$info->am_unavailable : null,
            'manual_pm_value' => $info ? (bool)$info->pm_unavailable : null,
            'reservation_am_detected' => $has_am_reservation,
            'reservation_pm_detected' => $has_pm_reservation,
            'final_am_result' => $am_unavailable,
            'final_pm_result' => $pm_unavailable,
            'priority_logic' => isset($is_manual_setting) && $is_manual_setting ? '手動設定 + 予約自動追加' : '予約自動判定'
        )
    );
    
    wp_send_json_success($result);
}
?>