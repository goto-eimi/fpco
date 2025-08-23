<?php
/**
 * Plugin Name: エフピコ工場見学予約システム
 * Plugin URI: https://www.fpco.jp/
 * Description: エフピコの工場見学予約を管理する統合システム。カレンダー管理、予約管理、ユーザー管理機能を含みます。
 * Version: 1.0.0
 * Author: エフピコ
 * Text Domain: fpco-factory-reservation
 * Domain Path: /languages
 */

// プラグインの直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// プラグイン定数の定義
define('FPCO_RESERVATION_VERSION', '1.0.0');
define('FPCO_RESERVATION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FPCO_RESERVATION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FPCO_RESERVATION_PLUGIN_FILE', __FILE__);

/**
 * メインプラグインクラス
 */
class FPCO_Factory_Reservation_System {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
    /**
     * シングルトンインスタンスを取得
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        
        // プラグイン有効化・無効化フック
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('FPCO_Factory_Reservation_System', 'uninstall'));
    }
    
    /**
     * プラグイン初期化
     */
    public function init() {
        // 言語ファイルの読み込み
        load_plugin_textdomain('fpco-factory-reservation', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // 各機能モジュールの読み込み
        $this->load_includes();
        
        // 管理画面の場合のみ管理機能を読み込み
        if (is_admin()) {
            $this->load_admin_includes();
        }
        
        // フロントエンド機能の読み込み
        $this->load_frontend_includes();
    }
    
    /**
     * 共通機能ファイルの読み込み
     */
    private function load_includes() {
        // カレンダーAPI（元ファイルから移植）
        require_once FPCO_RESERVATION_PLUGIN_DIR . 'includes/calendar-api-functions.php';
        
        // データベース管理
        require_once FPCO_RESERVATION_PLUGIN_DIR . 'includes/class-database.php';
        
        // 祝日管理機能（共通で読み込み）
        require_once FPCO_RESERVATION_PLUGIN_DIR . 'includes/holiday-functions.php';
    }
    
    /**
     * 管理画面機能ファイルの読み込み
     */
    private function load_admin_includes() {
        // カレンダー管理（元ファイルから移植）
        require_once FPCO_RESERVATION_PLUGIN_DIR . 'includes/factory-calendar-admin-functions.php';
        
        // 予約管理（元ファイルから移植）
        require_once FPCO_RESERVATION_PLUGIN_DIR . 'includes/reservation-management-functions.php';
        
        // 予約一覧（元ファイルから移植）
        require_once FPCO_RESERVATION_PLUGIN_DIR . 'includes/reservation-list-functions.php';
        
        // 返信メール機能（元ファイルから移植）
        require_once FPCO_RESERVATION_PLUGIN_DIR . 'includes/reply-email-functions.php';
        
        // 工場ユーザー管理（元ファイルから移植）
        require_once FPCO_RESERVATION_PLUGIN_DIR . 'includes/factory-user-management-functions.php';
    }
    
    /**
     * フロントエンド機能ファイルの読み込み
     */
    private function load_frontend_includes() {
        // ショートコード（元ファイルから移植）
        require_once FPCO_RESERVATION_PLUGIN_DIR . 'includes/calendar-shortcode-functions.php';
    }
    
    /**
     * プラグイン有効化時の処理
     */
    public function activate() {
        // データベーステーブルの作成
        $this->create_database_tables();
        
        // ユーザーロールの追加
        $this->add_user_roles();
        
        // 祝日データの初期化（includes読み込み後に確実に実行）
        require_once FPCO_RESERVATION_PLUGIN_DIR . 'includes/holiday-functions.php';
        if (function_exists('fpco_update_holidays_data')) {
            fpco_update_holidays_data();
        }
        
        // 初期データの設定
        $this->setup_initial_data();
        
        // リライトルールをフラッシュ
        flush_rewrite_rules();
    }
    
    /**
     * プラグイン無効化時の処理
     */
    public function deactivate() {
        // ユーザーロールの削除
        $this->remove_user_roles();
        
        // 祝日cronのクリア
        if (function_exists('fpco_clear_holiday_cron')) {
            fpco_clear_holiday_cron();
        }
        
        // リライトルールをフラッシュ
        flush_rewrite_rules();
    }
    
    /**
     * プラグイン削除時の処理
     */
    public static function uninstall() {
        // 設定データの削除（必要に応じて）
        // delete_option('fpco_reservation_settings');
        
        // テーブルの削除（必要に応じて）
        // 注意: 本番環境では通常テーブルは残しておく
    }
    
    /**
     * データベーステーブルの作成
     */
    private function create_database_tables() {
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
            transportation_method varchar(100) DEFAULT NULL,
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
        
        // 祝日テーブル
        $sql5 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}holidays (
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
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
        dbDelta($sql5);
    }
    
    /**
     * ユーザーロールの追加
     */
    private function add_user_roles() {
        // 工場アカウントロールの追加
        add_role(
            'factory',
            '工場アカウント',
            array(
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => true,
                'publish_posts' => true,
                'upload_files' => true,
                'edit_published_posts' => true,
                'delete_published_posts' => true,
                'edit_others_posts' => true,
                'delete_others_posts' => true,
                'create_posts' => true,
                'manage_categories' => true,
                'moderate_comments' => true,
                'unfiltered_html' => true,
            )
        );
    }
    
    /**
     * ユーザーロールの削除
     */
    private function remove_user_roles() {
        remove_role('factory');
    }
    
    /**
     * 初期データの設定
     */
    private function setup_initial_data() {
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
}

// プラグインの初期化
FPCO_Factory_Reservation_System::get_instance();