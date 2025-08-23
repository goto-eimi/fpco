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
 * holidays-jp.github.io APIを使用して祝日データを更新
 */
function fpco_update_holidays_data() {
    global $wpdb;
    
    // 祝日テーブルが存在しない場合は作成
    try {
        fpco_create_holidays_table();
    } catch (Exception $e) {
        error_log('祝日テーブルの作成に失敗: ' . $e->getMessage());
        return false;
    }
    
    $holidays_data = array();
    
    // 現在年と来年の祝日データを取得
    $current_year = date('Y');
    $next_year = $current_year + 1;
    
    foreach ([$current_year, $next_year] as $year) {
        // holidays-jp.github.io APIのURL
        $api_url = "https://holidays-jp.github.io/api/v1/{$year}/date.json";
        
        // APIからJSONデータを取得
        $response = wp_remote_get($api_url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            )
        ));
        
        if (is_wp_error($response)) {
            error_log("{$year}年の祝日データ取得に失敗: " . $response->get_error_message());
            continue; // エラーがあっても他の年は処理を続行
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            error_log("{$year}年の祝日データが空です");
            continue;
        }
        
        // JSONをデコード
        $json_data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("{$year}年の祝日データのJSON解析に失敗: " . json_last_error_msg());
            continue;
        }
        
        // 祝日データを配列に追加
        foreach ($json_data as $date => $name) {
            $holidays_data[] = array(
                'date' => $date,
                'name' => $name
            );
        }
    }
    
    if (empty($holidays_data)) {
        error_log('祝日データが取得できませんでした');
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
 * 祝日データを初期化（プラグイン有効化時に呼び出される）
 */
function fpco_init_holidays_on_activation() {
    fpco_update_holidays_data();
}

/**
 * cronをクリア（プラグイン無効化時に呼び出される）
 */
function fpco_clear_holiday_cron() {
    wp_clear_scheduled_hook('fpco_update_holidays_cron');
}

/**
 * デバッグ用：祝日テーブルの状態を確認
 */
function fpco_debug_holiday_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'holidays';
    
    // テーブル存在確認
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    
    if (!$table_exists) {
        return array(
            'table_exists' => false,
            'count' => 0,
            'sample_data' => array(),
            'message' => '祝日テーブルが存在しません'
        );
    }
    
    // データ件数
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    // サンプルデータ
    $sample_data = $wpdb->get_results("SELECT * FROM $table_name ORDER BY date LIMIT 10");
    
    return array(
        'table_exists' => true,
        'count' => $count,
        'sample_data' => $sample_data,
        'message' => "祝日テーブル: {$count}件のデータ"
    );
}
?>