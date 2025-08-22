<?php
/**
 * 予約一覧機能（統合プラグイン版）
 * 元のfactory-reservation-manager/reservation-list.phpから移植
 */

// プラグインの直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 予約日時を統合表示用にフォーマットする
 */
function format_reservation_datetime($date, $time_slot, $factory_id) {
    if (!$date || !$time_slot) {
        return '';
    }
    
    // 日付をフォーマット
    $date_formatted = date('Y年n月j日', strtotime($date));
    
    // 時間スロット情報を取得
    $time_info = get_time_slot_info($time_slot, $factory_id);
    
    return $date_formatted . ' ' . $time_info;
}

/**
 * タイムスロットから時間情報を取得
 */
function get_time_slot_info($time_slot, $factory_id) {
    // プラグインの関数を読み込み
    require_once FPCO_RESERVATION_PLUGIN_DIR . 'includes/factory-user-management-functions.php';
    
    $parts = explode('-', $time_slot);
    $period = $parts[0] ?? '';
    
    // 60分・90分パターンの判定
    if (isset($parts[1]) && in_array($parts[1], ['60', '90'])) {
        // 固定時間パターン（例: am-60-1）
        $time_ranges = [
            'am-60-1' => '9:00 ~ 10:00',
            'am-60-2' => '10:30 ~ 11:30',
            'am-90-1' => '9:00 ~ 10:30',
            'am-90-2' => '10:00 ~ 11:30',
            'pm-60-1' => '14:00 ~ 15:00',
            'pm-60-2' => '15:30 ~ 16:30',
            'pm-90-1' => '14:00 ~ 15:30',
            'pm-90-2' => '15:00 ~ 16:30'
        ];
        
        return $time_ranges[$time_slot] ?? $time_slot;
    } else {
        // AM/PMパターン - プラグインから動的取得
        $index = $parts[1] ?? '1';
        
        if ($factory_id && function_exists('fpco_get_factory_timeslots')) {
            $timeslots = fpco_get_factory_timeslots($factory_id);
            
            if (isset($timeslots[$period])) {
                $period_slots = $timeslots[$period];
                $slot_index = intval($index) - 1;
                
                if (isset($period_slots[$slot_index])) {
                    return $period_slots[$slot_index];
                }
            }
        }
        
        // フォールバック
        return $time_slot;
    }
}

/**
 * CSSファイルの読み込み
 */
add_action('admin_enqueue_scripts', 'fpco_reservation_list_enqueue_scripts');

// CSV出力用のAjaxアクション
add_action('wp_ajax_export_reservations_csv', 'fpco_ajax_export_reservations_csv');

function fpco_ajax_export_reservations_csv() {
    // Nonce検証
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'reservation_list_nonce')) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 検索条件を取得
    $conditions = fpco_get_search_conditions();
    
    // CSV出力を実行
    fpco_export_reservations_csv($conditions);
}

function fpco_reservation_list_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_reservation-list') {
        return;
    }
    wp_enqueue_style('reservation-list-css', FPCO_RESERVATION_PLUGIN_URL . 'assets/css/reservation-list.css', [], '1.0');
    wp_enqueue_script('reservation-list-js', FPCO_RESERVATION_PLUGIN_URL . 'assets/js/reservation-list.js', ['jquery'], '1.0', true);
    
    // Ajax用のデータを渡す
    wp_localize_script('reservation-list-js', 'reservation_list_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('reservation_list_nonce')
    ]);
}

/**
 * 管理画面メニューを追加
 */
add_action('admin_menu', 'fpco_reservation_list_admin_menu');

function fpco_reservation_list_admin_menu() {
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
            'fpco_reservation_list_admin_page',
            'dashicons-list-view',
            25
        );
    }
}

/**
 * 検索条件を取得
 */
function fpco_get_search_conditions() {
    // 空の文字列の場合はemptyとして扱う
    $reservation_number = isset($_GET['reservation_number']) ? trim(sanitize_text_field($_GET['reservation_number'])) : '';
    $date_from = isset($_GET['date_from']) ? trim(sanitize_text_field($_GET['date_from'])) : '';
    $date_to = isset($_GET['date_to']) ? trim(sanitize_text_field($_GET['date_to'])) : '';
    $time_slot = isset($_GET['time_slot']) ? trim(sanitize_text_field($_GET['time_slot'])) : '';
    $status = isset($_GET['status']) ? trim(sanitize_text_field($_GET['status'])) : '';
    
    return [
        'reservation_number' => empty($reservation_number) ? '' : $reservation_number,
        'date_from' => empty($date_from) ? '' : $date_from,
        'date_to' => empty($date_to) ? '' : $date_to,
        'time_slot' => empty($time_slot) ? '' : $time_slot,
        'status' => empty($status) ? '' : $status,
        'per_page' => isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 20,
        'page' => isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1,
        'orderby' => isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'id',
        'order' => isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC'
    ];
}

/**
 * 予約データを取得
 */
function fpco_get_reservations($conditions) {
    global $wpdb;
    
    $where_clauses = ['1=1'];
    $params = [];
    
    // 工場フィルタリング（工場アカウントの場合）
    $current_user = wp_get_current_user();
    $is_admin = ($current_user->ID == 1 || $current_user->user_login == 'admin' || current_user_can('manage_options'));
    
    if (!$is_admin) {
        // 工場アカウントの場合は、割り当てられた工場の予約のみ表示
        $assigned_factory = get_user_meta($current_user->ID, 'assigned_factory', true);
        
        if ($assigned_factory) {
            $where_clauses[] = 'r.factory_id = %d';
            $params[] = intval($assigned_factory);
        } else {
            // 工場が割り当てられていない場合は何も表示しない
            $where_clauses[] = '1=0';
        }
    }
    
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
    
    // 時間帯検索（部分一致対応）
    if (!empty($conditions['time_slot'])) {
        if ($conditions['time_slot'] === 'AM') {
            // AM検索：12:00未満の時間帯にマッチ（09:00、9:00、10:00、11:00など）
            $where_clauses[] = 'r.time_slot REGEXP %s';
            $params[] = '^(0?[0-9]:[0-9]{2}|1[0-1]:[0-9]{2})-';
        } elseif ($conditions['time_slot'] === 'PM') {
            // PM検索：12:00以降の時間帯にマッチ
            $where_clauses[] = 'r.time_slot REGEXP %s';
            $params[] = '^(1[2-9]:[0-9]{2}|2[0-3]:[0-9]{2})-';
        } else {
            // その他の場合は部分一致検索（例：「10:00」で「10:00-11:00」がヒット）
            $where_clauses[] = 'r.time_slot LIKE %s';
            $params[] = '%' . $conditions['time_slot'] . '%';
        }
    }
    
    // ステータス検索
    if (!empty($conditions['status'])) {
        $where_clauses[] = 'r.status = %s';
        $params[] = $conditions['status'];
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // ソート条件
    $allowed_orderby = ['id', 'date', 'status', 'applicant_name', 'time_slot', 'reservation_type'];
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
    
    // reservation_typeでソートする場合は全データ取得が必要
    if ($orderby === 'reservation_type') {
        // 全データを取得（ソート用）
        $sql = "SELECT r.*, f.name as factory_name 
                FROM {$wpdb->prefix}reservations r 
                LEFT JOIN {$wpdb->prefix}factorys f ON r.factory_id = f.id 
                WHERE {$where_sql}";
        
        $all_reservations = $wpdb->get_results(
            empty($params) ? $sql : $wpdb->prepare($sql, ...$params),
            ARRAY_A
        );
        
        // 各予約の表示名を計算してソート
        foreach ($all_reservations as &$reservation) {
            $visitor_category = $reservation['visitor_category'] ?? $reservation['reservation_type'] ?? '';
            $type_data = !empty($reservation['type_data']) ? json_decode($reservation['type_data'], true) : null;
            $reservation['_display_type'] = fpco_get_reservation_type_display_name($visitor_category, $type_data);
        }
        unset($reservation);
        
        // 表示名でソート
        usort($all_reservations, function($a, $b) use ($order) {
            $compare = strcmp($a['_display_type'], $b['_display_type']);
            return $order === 'ASC' ? $compare : -$compare;
        });
        
        // ページネーションを適用
        $reservations = array_slice($all_reservations, $offset, $per_page);
    } else {
        // 通常のデータ取得
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
    }
    
    // time_slotでソートする場合は、PHP側で追加ソートを実行
    if ($orderby === 'time_slot' && !empty($reservations)) {
        usort($reservations, function($a, $b) use ($order) {
            // まず日付でソート
            $date_compare = strcmp($a['date'], $b['date']);
            if ($date_compare !== 0) {
                return $order === 'ASC' ? $date_compare : -$date_compare;
            }
            
            // 日付が同じ場合は時間でソート
            $time_a = fpco_extract_start_time($a['time_slot']);
            $time_b = fpco_extract_start_time($b['time_slot']);
            
            $time_compare = strcmp($time_a, $time_b);
            return $order === 'ASC' ? $time_compare : -$time_compare;
        });
    }
    
    return [
        'data' => $reservations,
        'total_items' => $total_items,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'per_page' => $per_page
    ];
}

/**
 * time_slotから開始時刻を抽出してソート用文字列に変換
 */
function fpco_extract_start_time($time_slot) {
    // HH:MM-HH:MM形式から開始時刻を抽出
    if (preg_match('/^(\d{1,2}):(\d{2})-/', $time_slot, $matches)) {
        $hour = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $minute = $matches[2];
        return $hour . ':' . $minute;
    }
    
    // AM（午前）の場合は、実際に午前の時間が含まれているかチェック
    if (stripos($time_slot, 'AM') !== false) {
        // AMで具体的な時間が含まれている場合（例: "9:00 AM", "AM 10:00"）
        if (preg_match('/(\d{1,2}):(\d{2})/', $time_slot, $matches)) {
            $hour = intval($matches[1]);
            if ($hour >= 8 && $hour <= 11) { // 8:00-11:59は午前
                return str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . $matches[2];
            }
        }
        // AM単体の場合は午前の中間時刻として扱う（8:00-11:59の中間）
        return '09:30';
    }
    
    // PM（午後）の場合は、実際に午後の時間が含まれているかチェック
    if (stripos($time_slot, 'PM') !== false) {
        // PMで具体的な時間が含まれている場合（例: "2:00 PM", "PM 15:00"）
        if (preg_match('/(\d{1,2}):(\d{2})/', $time_slot, $matches)) {
            $hour = intval($matches[1]);
            if ($hour >= 12 && $hour <= 18) { // 12:00-18:59は午後（24時間表記）
                return str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . $matches[2];
            } elseif ($hour >= 1 && $hour <= 6) { // 1:00-6:59 PMは13:00-18:59に変換
                $hour = $hour + 12;
                return str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . $matches[2];
            }
        }
        // PM単体の場合は午後の中間時刻として扱う（12:00-18:00の中間）
        return '15:00';
    }
    
    // その他の場合はそのまま返す
    return $time_slot;
}

/**
 * 予約タイプの表示名を取得
 */
function fpco_get_reservation_type_display_name($type, $type_data = null) {
    // フロントエンドのvisitor_categoryの値に直接対応
    $frontend_type_names = [
        'school' => '小学校・中学校・大学',
        'recruit' => '個人（大学生・高校生のリクルート）',
        'family' => '個人・親子見学・ご家族など',
        'company' => '企業（研修など）',
        'government' => '自治体主体ツアーなど',
        'other' => 'その他（グループ・団体）'
    ];
    
    // まずフロントエンドの値をチェック
    if (isset($frontend_type_names[$type])) {
        return $frontend_type_names[$type];
    }
    
    // 管理画面の値体系（後方互換性のため）
    $backend_type_names = [
        'personal' => '個人',
        'corporate' => '企業',
        'municipal' => '自治体'
    ];
    
    // personalタイプの場合は、type_dataから詳細を判定
    if ($type === 'personal' && $type_data) {
        $data = is_string($type_data) ? json_decode($type_data, true) : $type_data;
        if ($data && isset($data['school_name'])) {
            // recruit_school_nameがあればリクルート
            return '個人（大学生・高校生のリクルート）';
        }
        // それ以外は家族
        return '個人・親子見学・ご家族など';
    }
    
    return isset($backend_type_names[$type]) ? $backend_type_names[$type] : '未設定';
}

/**
 * CSV出力
 */
function fpco_export_reservations_csv($conditions) {
    global $wpdb;
    
    $where_clauses = ['1=1'];
    $params = [];
    
    // 工場フィルタリング（工場アカウントの場合）- fpco_get_reservations関数と同じロジック
    $current_user = wp_get_current_user();
    $is_admin = ($current_user->ID == 1 || $current_user->user_login == 'admin' || current_user_can('manage_options'));
    
    if (!$is_admin) {
        // 工場アカウントの場合は、割り当てられた工場の予約のみエクスポート
        $assigned_factory = get_user_meta($current_user->ID, 'assigned_factory', true);
        
        if ($assigned_factory) {
            $where_clauses[] = 'r.factory_id = %d';
            $params[] = intval($assigned_factory);
        } else {
            // 工場が割り当てられていない場合は何もエクスポートしない
            $where_clauses[] = '1=0';
        }
    }
    
    // 検索条件を適用（fpco_get_reservations関数と同じロジック）
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
            // AM検索：12:00未満の時間帯にマッチ（09:00、9:00、10:00、11:00など）
            $where_clauses[] = 'r.time_slot REGEXP %s';
            $params[] = '^(0?[0-9]:[0-9]{2}|1[0-1]:[0-9]{2})-';
        } elseif ($conditions['time_slot'] === 'PM') {
            // PM検索：12:00以降の時間帯にマッチ
            $where_clauses[] = 'r.time_slot REGEXP %s';
            $params[] = '^(1[2-9]:[0-9]{2}|2[0-3]:[0-9]{2})-';
        } else {
            // その他の場合は部分一致検索（例：「10:00」で「10:00-11:00」がヒット）
            $where_clauses[] = 'r.time_slot LIKE %s';
            $params[] = '%' . $conditions['time_slot'] . '%';
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
        // 旅行会社データをデコード
        $agency_data = !empty($reservation['agency_data']) ? json_decode($reservation['agency_data'], true) : [];
        
        // 組織データをデコード（type_dataから取得）
        $type_data = !empty($reservation['type_data']) ? json_decode($reservation['type_data'], true) : [];
        
        // 組織名を取得（タイプに応じて異なるキーから取得）
        $organization_name = '';
        $organization_kana = '';
        $representative_name = '';
        
        switch ($reservation['visitor_category'] ?? $reservation['reservation_type']) {
            case 'school':
                $organization_name = $type_data['school_name'] ?? '';
                $organization_kana = $type_data['school_name_kana'] ?? '';
                $representative_name = $type_data['teacher_name'] ?? $type_data['representative_name'] ?? '';
                break;
            case 'corporate':
                $organization_name = $type_data['company_name'] ?? '';
                $organization_kana = $type_data['company_name_kana'] ?? '';
                $representative_name = $type_data['contact_person'] ?? '';
                break;
            case 'municipal':
                $organization_name = $type_data['organization_name'] ?? $type_data['company_name'] ?? '';
                $organization_kana = $type_data['organization_name_kana'] ?? $type_data['company_name_kana'] ?? '';
                $representative_name = $type_data['contact_person'] ?? '';
                break;
            case 'other':
                $organization_name = $type_data['organization_name'] ?? $type_data['company_name'] ?? '';
                $organization_kana = $type_data['organization_name_kana'] ?? $type_data['company_name_kana'] ?? '';
                $representative_name = $type_data['contact_person'] ?? '';
                break;
            case 'personal':
                // 個人でもtype_dataに学校のような情報が入っている場合がある
                if (!empty($type_data['school_name'])) {
                    $organization_name = $type_data['school_name'] ?? '';
                    $organization_kana = $type_data['school_name_kana'] ?? $type_data['department'] ?? '';
                    $representative_name = $type_data['teacher_name'] ?? '';
                }
                break;
            default:
                // その他の場合
                break;
        }
        
        // データベースのカラムからも取得を試みる（フォールバック）
        if (empty($organization_name)) {
            $organization_name = $reservation['organization_name'] ?? '';
        }
        if (empty($organization_kana)) {
            $organization_kana = $reservation['organization_kana'] ?? '';
        }
        
        if (empty($representative_name)) {
            $representative_name = $reservation['representative_name'] ?? '';
        }
        
        $row = [
            $reservation['id'] ?? '',
            $reservation['date'] ?? '',
            $reservation['time_slot'] ?? '',
            '60', // デフォルト値、実際のデータがあれば置換
            $reservation['applicant_name'] ?? '',
            $reservation['applicant_kana'] ?? '',
            $reservation['address_zip'] ?? '',
            ($reservation['address_prefecture'] ?? '') . ($reservation['address_city'] ?? '') . ($reservation['address_street'] ?? ''),
            $reservation['phone'] ?? '',
            $reservation['email'] ?? '',
            ($reservation['is_travel_agency'] ?? false) ? 'はい' : 'いいえ',
            $agency_data['name'] ?? '',
            fpco_get_reservation_type_display_name($reservation['visitor_category'] ?? $reservation['reservation_type'] ?? '', $reservation['type_data'] ?? null),
            $organization_name,
            $organization_kana,
            $representative_name,
            // 見学者人数を取得（type_dataも考慮）
            $reservation['participant_count'] ?? $type_data['adult_count'] ?? $type_data['supervisor_count'] ?? '',
            $reservation['participants_child_count'] ?? $type_data['child_count'] ?? $type_data['student_count'] ?? '',
            $reservation['transportation_method'] ?? '',
            $reservation['transportation_count'] ?? '',
            $reservation['purpose'] ?? '',
            (function($status) {
                $status_labels = [
                    'new' => '新規受付',
                    'pending' => '確認中',
                    'approved' => '承認',
                    'rejected' => '否認',
                    'cancelled' => 'キャンセル'
                ];
                return $status_labels[$status] ?? $status;
            })($reservation['status'] ?? ''),
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
function fpco_reservation_list_admin_page() {
    global $wpdb;
    
    
    // 通常の画面表示の権限チェック
    $current_user = wp_get_current_user();
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
    
    if (!$can_access) {
        wp_die(__('このページにアクセスする権限がありません。'));
    }
    
    // 検索条件取得
    $conditions = fpco_get_search_conditions();
    
    // データ取得
    $result = fpco_get_reservations($conditions);
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
        <a href="admin.php?page=reservation-management" class="page-title-action">
            <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span> 新規追加
        </a>
        
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
                               value="<?php echo esc_attr($conditions['date_from'] ?? ''); ?>" 
                               max="9999-12-31">
                    </div>
                    
                    <div class="search-field">
                        <label for="date_to">予約日（終了）</label>
                        <input type="date" name="date_to" id="date_to" 
                               value="<?php echo esc_attr($conditions['date_to'] ?? ''); ?>" 
                               max="9999-12-31">
                    </div>
                    
                    <div class="search-field">
                        <label for="time_slot">予約時間</label>
                        <input type="text" name="time_slot" id="time_slot" 
                               value="<?php echo esc_attr($conditions['time_slot'] ?? ''); ?>" 
                               placeholder="例: 9:00-10:00 または AM/PM">
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
            <button id="export-csv-btn" class="button button-secondary" 
                    data-nonce="<?php echo wp_create_nonce('reservation_list_nonce'); ?>">
                <span class="dashicons dashicons-download"></span> CSV出力
            </button>
            <div class="items-count-and-pagination">
                <div class="items-count">
                    <?php echo esc_html($pagination['total_items'] ?? 0); ?>個の項目
                </div>
                
                <!-- 表示件数選択は常に表示 -->
                <div class="per-page-selector">
                    <label for="per_page_top">表示件数:</label>
                    <select name="per_page_top" id="per_page_top" onchange="changePage(1, this.value)">
                        <option value="20" <?php selected($conditions['per_page'], 20); ?>>20件</option>
                        <option value="50" <?php selected($conditions['per_page'], 50); ?>>50件</option>
                        <option value="100" <?php selected($conditions['per_page'], 100); ?>>100件</option>
                    </select>
                </div>
                
                <?php if ($pagination['total_pages'] > 1): ?>
                    
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
                <?php endif; ?>
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
                            <th class="sortable <?php echo $conditions['orderby'] === 'reservation_type' ? 'sorted ' . strtolower($conditions['order']) : ''; ?>">
                                <a href="?<?php echo http_build_query(array_filter(array_merge($conditions, ['page' => 'reservation-list', 'orderby' => 'reservation_type', 'order' => ($conditions['orderby'] === 'reservation_type' && $conditions['order'] === 'ASC') ? 'DESC' : 'ASC']), function($value) { return $value !== '' && $value !== null; })); ?>">
                                    予約タイプ
                                    <span class="sorting-indicator">
                                        <?php if ($conditions['orderby'] === 'reservation_type'): ?>
                                            <span class="dashicons dashicons-arrow-<?php echo strtolower($conditions['order']) === 'asc' ? 'up' : 'down'; ?>-alt2"></span>
                                        <?php else: ?>
                                            <span class="dashicons dashicons-sort"></span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            </th>
                            <th>ステータス</th>
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
                                            $reservation['address_zip'] ? '〒' . $reservation['address_zip'] : '',
                                            $reservation['address_prefecture'] ?? '',
                                            $reservation['address_city'] ?? '',
                                            $reservation['address_street'] ?? ''
                                        ]);
                                        echo esc_html(implode(' ', $address_parts));
                                        ?>
                                    </div>
                                </td>
                                <td class="reservation-datetime">
                                    <?php 
                                    echo esc_html(format_reservation_datetime($reservation['date'], $reservation['time_slot'], $reservation['factory_id']));
                                    ?>
                                </td>
                                <td class="reservation-phone">
                                    <?php echo esc_html($reservation['phone'] ?? ''); ?>
                                </td>
                                <td class="reservation-type">
                                    <?php echo esc_html(fpco_get_reservation_type_display_name($reservation['visitor_category'] ?? $reservation['reservation_type'] ?? '', $reservation['type_data'] ?? null)); ?>
                                </td>
                                <td class="reservation-status">
                                    <span class="status-badge status-<?php echo esc_attr($reservation['status'] ?? ''); ?>">
                                        <?php 
                                        $status = $reservation['status'] ?? '';
                                        $status_labels = [
                                            'new' => '新規受付',
                                            'pending' => '確認中',
                                            'approved' => '承認',
                                            'rejected' => '否認',
                                            'cancelled' => 'キャンセル'
                                        ];
                                        echo esc_html($status_labels[$status] ?? $status);
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
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
    
    // CSV出力処理
    jQuery(document).ready(function($) {
        $('#export-csv-btn').on('click', function(e) {
            e.preventDefault();
            
            const nonce = $(this).data('nonce');
            
            // 現在のページのURLパラメータから検索条件を取得
            const urlParams = new URLSearchParams(window.location.search);
            const searchConditions = {};
            
            // 検索フォームから直接値を取得
            searchConditions.reservation_number = $('#reservation_number').val() || '';
            searchConditions.date_from = $('#date_from').val() || '';
            searchConditions.date_to = $('#date_to').val() || '';
            searchConditions.time_slot = $('#time_slot').val() || '';
            searchConditions.status = $('#status').val() || '';
            searchConditions.per_page = urlParams.get('per_page') || '20';
            searchConditions.orderby = urlParams.get('orderby') || 'id';
            searchConditions.order = urlParams.get('order') || 'DESC';
            
            // CSV出力用のパラメータを構築
            const params = new URLSearchParams({
                action: 'export_reservations_csv',
                nonce: nonce,
                ...searchConditions
            });
            
            // CSVダウンロード用のURLを作成
            const downloadUrl = reservation_list_ajax.ajax_url + '?' + params.toString();
            
            // 新しいウィンドウで開く（ダウンロード）
            window.location.href = downloadUrl;
        });
    });
    </script>
    
    <?php
}
?>