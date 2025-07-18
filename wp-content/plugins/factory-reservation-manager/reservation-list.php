<?php
/**
 * Plugin Name: Reservation List
 * Description: 予約一覧画面
 * Version: 1.0
 */

// プラグインの直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CSSファイルの読み込み
 */
add_action('admin_enqueue_scripts', 'reservation_list_enqueue_scripts');

function reservation_list_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_reservation-list') {
        return;
    }
    wp_enqueue_style('reservation-list-css', plugin_dir_url(__FILE__) . 'reservation-list.css', [], '1.0');
    wp_enqueue_script('reservation-list-js', plugin_dir_url(__FILE__) . 'reservation-list.js', ['jquery'], '1.0', true);
    
    // Ajax用のデータを渡す
    wp_localize_script('reservation-list-js', 'reservation_list_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('reservation_list_nonce')
    ]);
}

/**
 * 管理画面メニューを追加
 */
add_action('admin_menu', 'reservation_list_admin_menu');

function reservation_list_admin_menu() {
    add_menu_page(
        '予約一覧',
        '予約一覧',
        'manage_options',
        'reservation-list',
        'reservation_list_admin_page',
        'dashicons-list-view',
        25
    );
}

/**
 * 検索条件を取得
 */
function get_search_conditions() {
    return [
        'reservation_number' => isset($_GET['reservation_number']) ? sanitize_text_field($_GET['reservation_number']) : '',
        'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
        'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
        'time_slot' => isset($_GET['time_slot']) ? sanitize_text_field($_GET['time_slot']) : '',
        'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
        'per_page' => isset($_GET['per_page']) ? intval($_GET['per_page']) : 20,
        'page' => isset($_GET['paged']) ? intval($_GET['paged']) : 1,
        'orderby' => isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'id',
        'order' => isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC'
    ];
}

/**
 * 予約データを取得
 */
function get_reservations($conditions) {
    global $wpdb;
    
    $where_clauses = ['1=1'];
    $params = [];
    
    // 予約番号検索
    if (!empty($conditions['reservation_number'])) {
        $where_clauses[] = 'r.id LIKE %s';
        $params[] = '%' . $conditions['reservation_number'] . '%';
    }
    
    // 日付範囲検索
    if (!empty($conditions['date_from'])) {
        $where_clauses[] = 'r.date >= %s';
        $params[] = $conditions['date_from'];
    }
    
    if (!empty($conditions['date_to'])) {
        $where_clauses[] = 'r.date <= %s';
        $params[] = $conditions['date_to'];
    }
    
    // 時間帯検索
    if (!empty($conditions['time_slot'])) {
        if ($conditions['time_slot'] === 'AM') {
            $where_clauses[] = 'r.time_slot LIKE %s';
            $params[] = '%AM%';
        } elseif ($conditions['time_slot'] === 'PM') {
            $where_clauses[] = 'r.time_slot LIKE %s';
            $params[] = '%PM%';
        } else {
            $where_clauses[] = 'r.time_slot = %s';
            $params[] = $conditions['time_slot'];
        }
    }
    
    // ステータス検索
    if (!empty($conditions['status'])) {
        $where_clauses[] = 'r.status = %s';
        $params[] = $conditions['status'];
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // ソート条件
    $allowed_orderby = ['id', 'date', 'status', 'applicant_name'];
    $orderby = in_array($conditions['orderby'], $allowed_orderby) ? $conditions['orderby'] : 'id';
    $order = strtoupper($conditions['order']) === 'ASC' ? 'ASC' : 'DESC';
    
    // 総件数を取得
    $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}reservations r WHERE {$where_sql}";
    $total_items = $wpdb->get_var($wpdb->prepare($count_sql, ...$params));
    
    // ページネーション計算
    $per_page = max(1, min(100, $conditions['per_page']));
    $page = max(1, $conditions['page']);
    $offset = ($page - 1) * $per_page;
    $total_pages = ceil($total_items / $per_page);
    
    // データ取得
    $sql = "SELECT r.*, f.name as factory_name 
            FROM {$wpdb->prefix}reservations r 
            LEFT JOIN {$wpdb->prefix}factorys f ON r.factory_id = f.id 
            WHERE {$where_sql} 
            ORDER BY r.{$orderby} {$order} 
            LIMIT %d OFFSET %d";
    
    $reservations = $wpdb->get_results(
        $wpdb->prepare($sql, ...array_merge($params, [$per_page, $offset])),
        ARRAY_A
    );
    
    return [
        'data' => $reservations,
        'total_items' => $total_items,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'per_page' => $per_page
    ];
}

/**
 * CSV出力
 */
function export_reservations_csv($conditions) {
    global $wpdb;
    
    $where_clauses = ['1=1'];
    $params = [];
    
    // 検索条件を適用（get_reservations関数と同じロジック）
    if (!empty($conditions['reservation_number'])) {
        $where_clauses[] = 'r.id LIKE %s';
        $params[] = '%' . $conditions['reservation_number'] . '%';
    }
    
    if (!empty($conditions['date_from'])) {
        $where_clauses[] = 'r.date >= %s';
        $params[] = $conditions['date_from'];
    }
    
    if (!empty($conditions['date_to'])) {
        $where_clauses[] = 'r.date <= %s';
        $params[] = $conditions['date_to'];
    }
    
    if (!empty($conditions['time_slot'])) {
        if ($conditions['time_slot'] === 'AM') {
            $where_clauses[] = 'r.time_slot LIKE %s';
            $params[] = '%AM%';
        } elseif ($conditions['time_slot'] === 'PM') {
            $where_clauses[] = 'r.time_slot LIKE %s';
            $params[] = '%PM%';
        } else {
            $where_clauses[] = 'r.time_slot = %s';
            $params[] = $conditions['time_slot'];
        }
    }
    
    if (!empty($conditions['status'])) {
        $where_clauses[] = 'r.status = %s';
        $params[] = $conditions['status'];
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // 全データを取得
    $sql = "SELECT r.*, f.name as factory_name 
            FROM {$wpdb->prefix}reservations r 
            LEFT JOIN {$wpdb->prefix}factorys f ON r.factory_id = f.id 
            WHERE {$where_sql} 
            ORDER BY r.id DESC";
    
    $reservations = $wpdb->get_results(
        empty($params) ? $sql : $wpdb->prepare($sql, ...$params),
        ARRAY_A
    );
    
    // CSV出力
    $filename = 'reservations_' . date('Ymd_His') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // BOM追加（Excel対応）
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // ヘッダー行
    $headers = [
        '予約番号', '予約日', '見学時間帯', '見学時間（分）', '申込者氏名', '申込者氏名（ふりがな）',
        '申込者郵便番号', '申込者住所', '申込者電話番号', '申込者メールアドレス',
        '旅行会社フラグ', '旅行会社名', '見学者分類', '組織名', '組織名（ふりがな）',
        '代表者氏名', '見学者人数（大人）', '見学者人数（子ども）', '交通機関', '台数',
        '見学目的', '予約ステータス', '作成日時', '更新日時'
    ];
    
    fputcsv($output, $headers);
    
    // データ行
    foreach ($reservations as $reservation) {
        $row = [
            $reservation['id'] ?? '',
            $reservation['date'] ?? '',
            $reservation['time_slot'] ?? '',
            '60', // デフォルト値、実際のデータがあれば置換
            $reservation['applicant_name'] ?? '',
            $reservation['applicant_kana'] ?? '',
            $reservation['postal_code'] ?? '',
            ($reservation['applicant_prefecture'] ?? '') . ($reservation['applicant_city'] ?? '') . ($reservation['applicant_address'] ?? ''),
            $reservation['phone'] ?? '',
            $reservation['email'] ?? '',
            ($reservation['is_travel_agency'] ?? false) ? 'はい' : 'いいえ',
            $reservation['travel_agency_name'] ?? '',
            $reservation['visitor_type'] ?? '',
            $reservation['organization_name'] ?? '',
            $reservation['organization_kana'] ?? '',
            $reservation['representative_name'] ?? '',
            $reservation['participant_count'] ?? '',
            $reservation['participants_child_count'] ?? '',
            $reservation['transportation_method'] ?? '',
            $reservation['transportation_count'] ?? '',
            $reservation['purpose'] ?? '',
            get_reservation_status_label($reservation['status'] ?? ''),
            $reservation['created_at'] ?? '',
            $reservation['updated_at'] ?? ''
        ];
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}


/**
 * 管理画面表示
 */
function reservation_list_admin_page() {
    // CSV出力処理
    if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
        $conditions = get_search_conditions();
        export_reservations_csv($conditions);
        return;
    }
    
    // 検索条件取得
    $conditions = get_search_conditions();
    
    // データ取得
    $result = get_reservations($conditions);
    $reservations = $result['data'];
    $pagination = [
        'total_items' => $result['total_items'],
        'total_pages' => $result['total_pages'],
        'current_page' => $result['current_page'],
        'per_page' => $result['per_page']
    ];
    ?>
    
    <div class="wrap">
        <h1 class="wp-heading-inline">予約一覧</h1>
        
        <!-- 検索・絞り込みエリア -->
        <div class="search-filters-area">
            <form method="get" action="" class="search-form">
                <input type="hidden" name="page" value="reservation-list">
                
                <div class="search-row">
                    <div class="search-field">
                        <label for="reservation_number">予約番号</label>
                        <input type="text" name="reservation_number" id="reservation_number" 
                               value="<?php echo esc_attr($conditions['reservation_number'] ?? ''); ?>" 
                               placeholder="予約番号" pattern="[0-9]*">
                    </div>
                    
                    <div class="search-field">
                        <label for="date_from">予約日（開始）</label>
                        <input type="date" name="date_from" id="date_from" 
                               value="<?php echo esc_attr($conditions['date_from'] ?? ''); ?>">
                    </div>
                    
                    <div class="search-field">
                        <label for="date_to">予約日（終了）</label>
                        <input type="date" name="date_to" id="date_to" 
                               value="<?php echo esc_attr($conditions['date_to'] ?? ''); ?>">
                    </div>
                    
                    <div class="search-field">
                        <label for="time_slot">予約時間</label>
                        <select name="time_slot" id="time_slot">
                            <option value="">全て</option>
                            <option value="AM" <?php selected($conditions['time_slot'], 'AM'); ?>>AM</option>
                            <option value="PM" <?php selected($conditions['time_slot'], 'PM'); ?>>PM</option>
                            <option value="9:00 ~ 10:00" <?php selected($conditions['time_slot'], '9:00 ~ 10:00'); ?>>9:00 ~ 10:00</option>
                            <option value="9:30 ~ 10:30" <?php selected($conditions['time_slot'], '9:30 ~ 10:30'); ?>>9:30 ~ 10:30</option>
                            <option value="10:00 ~ 11:00" <?php selected($conditions['time_slot'], '10:00 ~ 11:00'); ?>>10:00 ~ 11:00</option>
                            <option value="10:30 ~ 11:30" <?php selected($conditions['time_slot'], '10:30 ~ 11:30'); ?>>10:30 ~ 11:30</option>
                            <option value="11:00 ~ 12:00" <?php selected($conditions['time_slot'], '11:00 ~ 12:00'); ?>>11:00 ~ 12:00</option>
                            <option value="14:00 ~ 15:00" <?php selected($conditions['time_slot'], '14:00 ~ 15:00'); ?>>14:00 ~ 15:00</option>
                            <option value="14:30 ~ 15:30" <?php selected($conditions['time_slot'], '14:30 ~ 15:30'); ?>>14:30 ~ 15:30</option>
                            <option value="15:00 ~ 16:00" <?php selected($conditions['time_slot'], '15:00 ~ 16:00'); ?>>15:00 ~ 16:00</option>
                            <option value="15:30 ~ 16:30" <?php selected($conditions['time_slot'], '15:30 ~ 16:30'); ?>>15:30 ~ 16:30</option>
                            <option value="16:00 ~ 17:00" <?php selected($conditions['time_slot'], '16:00 ~ 17:00'); ?>>16:00 ~ 17:00</option>
                        </select>
                    </div>
                    
                    <div class="search-field">
                        <label for="status">予約ステータス</label>
                        <select name="status" id="status">
                            <option value="">全て</option>
                            <option value="new" <?php selected($conditions['status'], 'new'); ?>>新規受付</option>
                            <option value="pending" <?php selected($conditions['status'], 'pending'); ?>>確認中</option>
                            <option value="approved" <?php selected($conditions['status'], 'approved'); ?>>承認</option>
                            <option value="rejected" <?php selected($conditions['status'], 'rejected'); ?>>否認</option>
                            <option value="cancelled" <?php selected($conditions['status'], 'cancelled'); ?>>キャンセル</option>
                        </select>
                    </div>
                </div>
                
                <div class="search-buttons">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-search"></span> 絞り込み
                    </button>
                    <button type="button" class="button" onclick="clearSearchForm()">
                        <span class="dashicons dashicons-dismiss"></span> クリア
                    </button>
                </div>
            </form>
        </div>
        
        <!-- アクションボタンエリア -->
        <div class="action-buttons-area">
            <a href="admin.php?page=reservation-management" class="button button-primary">
                <span class="dashicons dashicons-plus-alt"></span> 新規追加
            </a>
            <a href="?page=reservation-list&action=export_csv&<?php echo http_build_query(array_filter($conditions, function($value) { return $value !== '' && $value !== null; })); ?>" 
               class="button button-secondary">
                <span class="dashicons dashicons-download"></span> CSV出力
            </a>
            <div class="items-count">
                <?php echo esc_html($pagination['total_items'] ?? 0); ?>個の項目
            </div>
        </div>
        
        <!-- 予約一覧テーブル -->
        <div class="reservations-table-container">
            <?php if (empty($reservations)): ?>
                <div class="no-data-message">
                    <p>該当する予約が見つかりませんでした。</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="sortable <?php echo $conditions['orderby'] === 'id' ? 'sorted ' . strtolower($conditions['order']) : ''; ?>">
                                <a href="?<?php echo http_build_query(array_filter(array_merge($conditions, ['page' => 'reservation-list', 'orderby' => 'id', 'order' => ($conditions['orderby'] === 'id' && $conditions['order'] === 'ASC') ? 'DESC' : 'ASC']), function($value) { return $value !== '' && $value !== null; })); ?>">
                                    予約番号 
                                    <span class="sorting-indicator">
                                        <?php if ($conditions['orderby'] === 'id'): ?>
                                            <span class="dashicons dashicons-arrow-<?php echo strtolower($conditions['order']) === 'asc' ? 'up' : 'down'; ?>-alt2"></span>
                                        <?php else: ?>
                                            <span class="dashicons dashicons-sort"></span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            </th>
                            <th>予約者</th>
                            <th class="sortable <?php echo $conditions['orderby'] === 'date' ? 'sorted ' . strtolower($conditions['order']) : ''; ?>">
                                <a href="?<?php echo http_build_query(array_filter(array_merge($conditions, ['page' => 'reservation-list', 'orderby' => 'date', 'order' => ($conditions['orderby'] === 'date' && $conditions['order'] === 'ASC') ? 'DESC' : 'ASC']), function($value) { return $value !== '' && $value !== null; })); ?>">
                                    予約日時
                                    <span class="sorting-indicator">
                                        <?php if ($conditions['orderby'] === 'date'): ?>
                                            <span class="dashicons dashicons-arrow-<?php echo strtolower($conditions['order']) === 'asc' ? 'up' : 'down'; ?>-alt2"></span>
                                        <?php else: ?>
                                            <span class="dashicons dashicons-sort"></span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            </th>
                            <th>電話番号</th>
                            <th>予約タイプ</th>
                            <th class="sortable <?php echo $conditions['orderby'] === 'status' ? 'sorted ' . strtolower($conditions['order']) : ''; ?>">
                                <a href="?<?php echo http_build_query(array_filter(array_merge($conditions, ['page' => 'reservation-list', 'orderby' => 'status', 'order' => ($conditions['orderby'] === 'status' && $conditions['order'] === 'ASC') ? 'DESC' : 'ASC']), function($value) { return $value !== '' && $value !== null; })); ?>">
                                    ステータス
                                    <span class="sorting-indicator">
                                        <?php if ($conditions['orderby'] === 'status'): ?>
                                            <span class="dashicons dashicons-arrow-<?php echo strtolower($conditions['order']) === 'asc' ? 'up' : 'down'; ?>-alt2"></span>
                                        <?php else: ?>
                                            <span class="dashicons dashicons-sort"></span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr class="reservation-row" data-id="<?php echo esc_attr($reservation['id'] ?? ''); ?>">
                                <td class="reservation-number">
                                    <a href="admin.php?page=reservation-management&reservation_id=<?php echo esc_attr($reservation['id'] ?? ''); ?>" 
                                       class="reservation-link">
                                        <?php echo esc_html($reservation['id'] ?? ''); ?>
                                    </a>
                                </td>
                                <td class="reservation-applicant">
                                    <div class="applicant-name">
                                        <?php echo esc_html($reservation['applicant_name'] ?? ''); ?>
                                    </div>
                                    <div class="applicant-address">
                                        <?php 
                                        $address_parts = array_filter([
                                            $reservation['postal_code'] ? '〒' . $reservation['postal_code'] : '',
                                            $reservation['applicant_prefecture'] ?? '',
                                            $reservation['applicant_city'] ?? '',
                                            $reservation['applicant_address'] ?? ''
                                        ]);
                                        echo esc_html(implode(' ', $address_parts));
                                        ?>
                                    </div>
                                </td>
                                <td class="reservation-datetime">
                                    <div class="reservation-date">
                                        <?php echo esc_html($reservation['date'] ? date('Y年n月j日', strtotime($reservation['date'])) : ''); ?>
                                    </div>
                                    <div class="reservation-time">
                                        <?php echo esc_html($reservation['time_slot'] ?? ''); ?>
                                    </div>
                                </td>
                                <td class="reservation-phone">
                                    <?php echo esc_html($reservation['phone'] ?? ''); ?>
                                </td>
                                <td class="reservation-type">
                                    <?php echo esc_html($reservation['visitor_type'] ?? '一般'); ?>
                                </td>
                                <td class="reservation-status">
                                    <span class="status-badge status-<?php echo esc_attr($reservation['status'] ?? ''); ?>">
                                        <?php echo esc_html(get_reservation_status_label($reservation['status'] ?? '')); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- ページネーション -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="pagination-area">
                <div class="pagination-controls">
                    <div class="per-page-selector">
                        <label for="per_page">表示件数:</label>
                        <select name="per_page" id="per_page" onchange="changePage(1, this.value)">
                            <option value="20" <?php selected($conditions['per_page'], 20); ?>>20件</option>
                            <option value="50" <?php selected($conditions['per_page'], 50); ?>>50件</option>
                            <option value="100" <?php selected($conditions['per_page'], 100); ?>>100件</option>
                        </select>
                    </div>
                    
                    <div class="page-navigation">
                        <button onclick="changePage(1)" 
                                <?php disabled($pagination['current_page'], 1); ?> 
                                class="page-btn first-page">
                            &lt;&lt;
                        </button>
                        <button onclick="changePage(<?php echo max(1, $pagination['current_page'] - 1); ?>)" 
                                <?php disabled($pagination['current_page'], 1); ?> 
                                class="page-btn prev-page">
                            &lt;
                        </button>
                        <span class="page-info">
                            <?php echo esc_html($pagination['current_page']); ?> / <?php echo esc_html($pagination['total_pages']); ?>
                        </span>
                        <button onclick="changePage(<?php echo min($pagination['total_pages'], $pagination['current_page'] + 1); ?>)" 
                                <?php disabled($pagination['current_page'], $pagination['total_pages']); ?> 
                                class="page-btn next-page">
                            &gt;
                        </button>
                        <button onclick="changePage(<?php echo $pagination['total_pages']; ?>)" 
                                <?php disabled($pagination['current_page'], $pagination['total_pages']); ?> 
                                class="page-btn last-page">
                            &gt;&gt;
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    function changePage(page, perPage = null) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('paged', page);
        if (perPage) {
            urlParams.set('per_page', perPage);
        }
        window.location.href = '?' + urlParams.toString();
    }
    
    // 自動更新機能（5分間隔）
    setInterval(function() {
        // 現在のページをリロード（検索条件を維持）
        window.location.reload();
    }, 5 * 60 * 1000);
    </script>
    
    <?php
}