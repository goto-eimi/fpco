<?php
/**
 * 祝日管理機能
 * 内閣府の祝日データを取得・管理
 */

// プラグインの直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 祝日テーブルを作成
 */
function fpco_create_holidays_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'holidays';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        date date NOT NULL,
        name varchar(255) NOT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_date (date),
        KEY idx_date (date)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * 内閣府の祝日CSVを取得して祝日データを更新
 */
function fpco_update_holidays_data() {
    global $wpdb;
    
    // 祝日テーブルが存在しない場合は作成
    fpco_create_holidays_table();
    
    // 内閣府の祝日CSVのURL
    $csv_url = 'https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv';
    
    // CSVデータを取得
    $response = wp_remote_get($csv_url, array(
        'timeout' => 30,
        'headers' => array(
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
        )
    ));
    
    if (is_wp_error($response)) {
        error_log('祝日データの取得に失敗: ' . $response->get_error_message());
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        error_log('祝日データが空です');
        return false;
    }
    
    // CSVをパース
    $lines = explode("\n", $body);
    $holidays_data = array();
    
    foreach ($lines as $line_num => $line) {
        // ヘッダー行をスキップ
        if ($line_num === 0) {
            continue;
        }
        
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        
        // CSVを分析（日付,祝日名の形式）
        $data = str_getcsv($line);
        if (count($data) >= 2) {
            $date = trim($data[0]);
            $name = trim($data[1]);
            
            // 日付の形式を確認（YYYY/M/D -> YYYY-MM-DD）
            if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $date, $matches)) {
                $formatted_date = sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
                $holidays_data[] = array(
                    'date' => $formatted_date,
                    'name' => $name
                );
            }
        }
    }
    
    if (empty($holidays_data)) {
        error_log('祝日データのパースに失敗');
        return false;
    }
    
    // 既存の祝日データを削除
    $table_name = $wpdb->prefix . 'holidays';
    $wpdb->query("TRUNCATE TABLE $table_name");
    
    // 新しい祝日データを一括挿入
    $values = array();
    $placeholders = array();
    
    foreach ($holidays_data as $holiday) {
        $values[] = $holiday['date'];
        $values[] = $holiday['name'];
        $placeholders[] = '(%s, %s)';
    }
    
    if (!empty($values)) {
        $sql = "INSERT INTO $table_name (date, name) VALUES " . implode(', ', $placeholders);
        $result = $wpdb->query($wpdb->prepare($sql, $values));
        
        if ($result === false) {
            error_log('祝日データの挿入に失敗: ' . $wpdb->last_error);
            return false;
        }
        
        error_log('祝日データを更新しました。件数: ' . count($holidays_data));
        return true;
    }
    
    return false;
}

/**
 * 指定された日付が祝日かどうかチェック
 */
function fpco_is_holiday($date) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'holidays';
    
    $result = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE date = %s",
            $date
        )
    );
    
    return $result > 0;
}

/**
 * 指定された期間の祝日一覧を取得
 */
function fpco_get_holidays($start_date, $end_date) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'holidays';
    
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT date, name FROM $table_name 
             WHERE date BETWEEN %s AND %s 
             ORDER BY date ASC",
            $start_date,
            $end_date
        )
    );
    
    $holidays = array();
    foreach ($results as $result) {
        $holidays[$result->date] = $result->name;
    }
    
    return $holidays;
}

/**
 * 祝日データの定期更新（WordPressの cron を使用）
 */
add_action('wp', 'fpco_schedule_holiday_update');

function fpco_schedule_holiday_update() {
    if (!wp_next_scheduled('fpco_update_holidays_cron')) {
        // 月1回更新（毎月1日午前2時）
        wp_schedule_event(time(), 'monthly', 'fpco_update_holidays_cron');
    }
}

add_action('fpco_update_holidays_cron', 'fpco_update_holidays_data');

/**
 * プラグイン有効化時に祝日データを初期化
 */
register_activation_hook(__FILE__, 'fpco_init_holidays_on_activation');

function fpco_init_holidays_on_activation() {
    fpco_update_holidays_data();
}

/**
 * プラグイン無効化時にcronをクリア
 */
register_deactivation_hook(__FILE__, 'fpco_clear_holiday_cron');

function fpco_clear_holiday_cron() {
    wp_clear_scheduled_hook('fpco_update_holidays_cron');
}
?>