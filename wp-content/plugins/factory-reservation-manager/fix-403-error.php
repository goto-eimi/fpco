<?php
/**
 * Plugin Name: Fix 403 Error for AJAX
 * Description: 403エラーを修正するための設定
 * Version: 1.0
 */

// プラグインの直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Heartbeat設定の調整
 */
add_filter('heartbeat_settings', 'modify_heartbeat_settings');
function modify_heartbeat_settings($settings) {
    // Heartbeatの頻度を60秒に設定（デフォルトは15秒）
    $settings['interval'] = 60;
    return $settings;
}

/**
 * 管理画面以外でHeartbeatを無効化
 */
add_action('init', 'conditionally_disable_heartbeat', 1);
function conditionally_disable_heartbeat() {
    global $pagenow;
    
    // 編集画面以外でHeartbeatを無効化
    if ($pagenow != 'post.php' && $pagenow != 'post-new.php') {
        wp_deregister_script('heartbeat');
    }
}

/**
 * AJAXのnonceを確実に設定
 */
add_action('admin_enqueue_scripts', 'fix_ajax_nonce', 1);
function fix_ajax_nonce() {
    // WordPressのAJAX URLとnonceを確実に設定
    wp_localize_script('jquery', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ajax-nonce')
    ));
}

/**
 * ログインセッションの期限を延長
 */
add_filter('auth_cookie_expiration', 'extend_login_session', 10, 3);
function extend_login_session($expiration, $user_id, $remember) {
    // ログイン状態を8時間維持
    return 8 * HOUR_IN_SECONDS;
}

/**
 * セキュリティヘッダーの追加（403エラーを防ぐ）
 */
add_action('send_headers', 'add_security_headers');
function add_security_headers() {
    if (is_admin()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
    }
}

/**
 * デバッグ用：403エラーの詳細をログに記録
 */
add_action('wp_ajax_debug_403_error', 'debug_403_error');
add_action('wp_ajax_nopriv_debug_403_error', 'debug_403_error');
function debug_403_error() {
    error_log('=== 403 Debug Info ===');
    error_log('User ID: ' . get_current_user_id());
    error_log('User Can: ' . (current_user_can('edit_posts') ? 'Yes' : 'No'));
    error_log('Nonce: ' . $_REQUEST['_wpnonce'] ?? 'Not set');
    error_log('Action: ' . $_REQUEST['action'] ?? 'Not set');
    
    wp_send_json_success(array(
        'message' => 'Debug info logged',
        'user_id' => get_current_user_id(),
        'can_edit' => current_user_can('edit_posts')
    ));
}