<?php
/**
 * データベース管理クラス
 * 
 * データベーステーブルの作成と管理機能を提供
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPCO_Database {
    
    /**
     * データベーステーブルを作成
     */
    public static function create_tables() {
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
        
        // 予約テーブル（既存の構造をそのまま維持）
        $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}reservations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            factory_id bigint(20) UNSIGNED NOT NULL,
            date date NOT NULL,
            time_slot varchar(20) NOT NULL,
            applicant_name varchar(255) NOT NULL,
            applicant_kana varchar(255) DEFAULT NULL,
            is_travel_agency tinyint(1) DEFAULT 0,
            agency_data longtext DEFAULT NULL,
            reservation_type varchar(50) DEFAULT NULL,
            type_data longtext DEFAULT NULL,
            address_zip varchar(10) DEFAULT NULL,
            address_prefecture varchar(20) DEFAULT NULL,
            address_city varchar(50) DEFAULT NULL,
            address_street varchar(100) DEFAULT NULL,
            phone varchar(20) DEFAULT NULL,
            day_of_contact varchar(20) DEFAULT NULL,
            email varchar(100) NOT NULL,
            transportation_method varchar(50) DEFAULT NULL,
            transportation_count int DEFAULT 0,
            purpose text DEFAULT NULL,
            participant_count int DEFAULT 0,
            participants_child_count int DEFAULT 0,
            status enum('new','pending','approved','rejected','cancelled') DEFAULT 'new',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
     * 初期データを設定
     */
    public static function setup_initial_data() {
        global $wpdb;
        
        // 工場データの初期設定（存在しない場合のみ）
        $existing_factories = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}factorys");
        
        if ($existing_factories == 0) {
            $factories = [
                ['name' => '関東リサイクル', 'capacity' => 50],
                ['name' => '中部リサイクル', 'capacity' => 50],
                ['name' => '福山リサイクル', 'capacity' => 50],
                ['name' => '山形選別センター', 'capacity' => 50],
                ['name' => '松本選別センター', 'capacity' => 50],
                ['name' => '西宮選別センター', 'capacity' => 50],
                ['name' => '東海選別センター', 'capacity' => 50],
                ['name' => '金沢選別センター', 'capacity' => 50],
                ['name' => '九州選別センター', 'capacity' => 50],
            ];
            
            foreach ($factories as $factory) {
                $wpdb->insert(
                    $wpdb->prefix . 'factorys',
                    $factory
                );
            }
        }
    }
    
    /**
     * 過去の見学不可データを定期的にクリーンアップ
     */
    public static function cleanup_old_data() {
        // 1日1回実行（WordPressのtransientを使用）
        if (get_transient('fpco_cleanup_unavailable_done')) {
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
        set_transient('fpco_cleanup_unavailable_done', true, DAY_IN_SECONDS);
    }
}

// クリーンアップ処理をフックに登録
add_action('wp', array('FPCO_Database', 'cleanup_old_data'));