<?php
// WordPress環境を読み込み
require_once 'wp-config.php';
require_once 'wp-includes/wp-db.php';

// グローバル変数を設定
global $wpdb;
$wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);

echo "=== データベース接続テスト ===\n";

// データベース接続テスト
if ($wpdb->last_error) {
    echo "データベース接続エラー: " . $wpdb->last_error . "\n";
    exit;
} else {
    echo "データベース接続: 成功\n";
}

// テーブル一覧を取得
echo "\n=== テーブル一覧 ===\n";
$tables = $wpdb->get_results("SHOW TABLES");
foreach ($tables as $table) {
    $table_array = (array) $table;
    echo "- " . array_values($table_array)[0] . "\n";
}

// wp_reservationsテーブルの存在確認
echo "\n=== wp_reservationsテーブル確認 ===\n";
$table_name = $wpdb->prefix . 'reservations';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

if ($table_exists) {
    echo "テーブル '$table_name' は存在します\n";
    
    // テーブル構造を確認
    echo "\n=== テーブル構造 ===\n";
    $columns = $wpdb->get_results("DESCRIBE $table_name");
    foreach ($columns as $column) {
        echo "- {$column->Field} ({$column->Type})\n";
    }
    
    // レコード数を確認
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "\n=== レコード数 ===\n";
    echo "総レコード数: $count\n";
    
    // 最新の5件を表示
    if ($count > 0) {
        echo "\n=== 最新の5件 ===\n";
        $recent_records = $wpdb->get_results("SELECT reservation_id, applicant_name, created_at FROM $table_name ORDER BY created_at DESC LIMIT 5");
        foreach ($recent_records as $record) {
            echo "- ID: {$record->reservation_id}, 名前: {$record->applicant_name}, 作成日: {$record->created_at}\n";
        }
    }
} else {
    echo "テーブル '$table_name' は存在しません\n";
    
    // テーブル作成を試行
    echo "\n=== テーブル作成を試行 ===\n";
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        reservation_id varchar(20) NOT NULL,
        factory_id varchar(10) NOT NULL,
        reservation_date date NOT NULL,
        timeslot varchar(20) NOT NULL,
        applicant_name varchar(100) NOT NULL,
        applicant_name_kana varchar(100),
        email varchar(100) NOT NULL,
        phone varchar(20),
        mobile varchar(20),
        postal_code varchar(10),
        prefecture varchar(20),
        city varchar(50),
        address varchar(100),
        building varchar(100),
        transportation varchar(50),
        transportation_other varchar(100),
        vehicle_count int DEFAULT 0,
        purpose text,
        is_travel_agency varchar(10) DEFAULT 'no',
        visitor_category varchar(50),
        total_visitors int DEFAULT 0,
        form_data longtext,
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY reservation_id (reservation_id)
    ) $charset_collate;";
    
    $result = $wpdb->query($sql);
    
    if ($result === false) {
        echo "テーブル作成失敗: " . $wpdb->last_error . "\n";
    } else {
        echo "テーブル作成成功\n";
    }
}

echo "\n=== 処理完了 ===\n";
?>