<?php
/**
 * カレンダーAPI機能（統合プラグイン版）
 * 元のfactory-reservation-manager/calendar-api.phpから移植
 */

// プラグインの直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST APIエンドポイントを登録
 */
add_action('rest_api_init', 'fpco_register_calendar_api_routes');

function fpco_register_calendar_api_routes() {
    register_rest_route('reservation/v1', '/calendar', array(
        'methods' => 'GET',
        'callback' => 'fpco_get_calendar_data',
        'permission_callback' => '__return_true',
        'args' => array(
            'month' => array(
                'required' => true,
                'validate_callback' => 'fpco_validate_month_parameter',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'factory' => array(
                'required' => true,
                'validate_callback' => 'fpco_validate_factory_parameter',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ));
    
    register_rest_route('reservation/v1', '/timeslots', array(
        'methods' => 'GET',
        'callback' => 'fpco_get_timeslot_data',
        'permission_callback' => '__return_true',
        'args' => array(
            'date' => array(
                'required' => true,
                'validate_callback' => 'fpco_validate_date_parameter',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'factory' => array(
                'required' => true,
                'validate_callback' => 'fpco_validate_factory_parameter',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ));
}

/**
 * 月パラメータの検証
 */
function fpco_validate_month_parameter($param, $request, $key) {
    return preg_match('/^\d{4}-\d{1,2}$/', $param);
}

/**
 * 工場IDパラメータの検証
 */
function fpco_validate_factory_parameter($param, $request, $key) {
    $valid_factories = range(1, 9); // 1-9の工場ID
    return in_array(intval($param), $valid_factories);
}

/**
 * 日付パラメータの検証
 */
function fpco_validate_date_parameter($param, $request, $key) {
    $date = DateTime::createFromFormat('Y-m-d', $param);
    return $date && $date->format('Y-m-d') === $param;
}

/**
 * カレンダーデータを取得
 */
function fpco_get_calendar_data($request) {
    global $wpdb;
    
    $month = $request['month'];
    $factory_id = intval($request['factory']);
    
    try {
        // 指定月の日付範囲を計算
        $date_parts = explode('-', $month);
        $year = intval($date_parts[0]);
        $month_num = intval($date_parts[1]);
        
        $first_day = date('Y-m-01', mktime(0, 0, 0, $month_num, 1, $year));
        $last_day = date('Y-m-t', mktime(0, 0, 0, $month_num, 1, $year));
        
        // カレンダー表示用に前後の日付も含める
        $calendar_start = date('Y-m-d', strtotime('last Sunday', strtotime($first_day)));
        if ($calendar_start == $first_day) {
            $calendar_start = date('Y-m-d', strtotime('-1 week', strtotime($first_day)));
        }
        $calendar_end = date('Y-m-d', strtotime('next Saturday', strtotime($last_day)));
        if ($calendar_end == $last_day) {
            $calendar_end = date('Y-m-d', strtotime('+1 week', strtotime($last_day)));
        }
        
        // 予約データを取得
        $reservations = fpco_get_reservations_for_period($factory_id, $calendar_start, $calendar_end);
        
        // 見学不可日を取得
        $unavailable_days = fpco_get_unavailable_days($factory_id, $calendar_start, $calendar_end);
        
        // 工場情報を取得
        $factory_info = fpco_get_factory_info($factory_id);
        
        // 各日付の状況を計算
        $calendar_data = array();
        $current_date = $calendar_start;
        
        while ($current_date <= $calendar_end) {
            $date_obj = new DateTime($current_date);
            $weekday = intval($date_obj->format('w'));
            
            // 基本的に土日祝日は見学不可
            $is_weekend = ($weekday === 0 || $weekday === 6);
            $is_holiday = fpco_is_japanese_holiday($current_date);
            
            $day_data = array(
                'date' => $current_date,
                'weekday' => $weekday,
                'is_other_month' => (
                    $date_obj->format('Y-m') !== sprintf('%04d-%02d', $year, $month_num)
                ),
                'am' => fpco_calculate_time_slot_status($current_date, 'am', $factory_id, $reservations, $unavailable_days, $is_weekend, $is_holiday),
                'pm' => fpco_calculate_time_slot_status($current_date, 'pm', $factory_id, $reservations, $unavailable_days, $is_weekend, $is_holiday),
            );
            
            $calendar_data[$current_date] = $day_data;
            $current_date = date('Y-m-d', strtotime('+1 day', strtotime($current_date)));
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'year' => $year,
                'month' => $month_num,
                'calendar_start' => $calendar_start,
                'calendar_end' => $calendar_end,
                'factory' => $factory_info,
                'days' => $calendar_data,
            ),
        ), 200);
        
    } catch (Exception $e) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'データの取得に失敗しました。',
            'error' => $e->getMessage(),
        ), 500);
    }
}

/**
 * 時間帯データを取得
 */
function fpco_get_timeslot_data($request) {
    $date = $request['date'];
    $factory_id = intval($request['factory']);
    
    try {
        $factory_info = fpco_get_factory_info($factory_id);
        
        // 工場の時間帯設定に基づいて利用可能な時間帯を取得
        $timeslots = fpco_get_available_timeslots($factory_id, $date);
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'date' => $date,
                'factory' => $factory_info,
                'timeslots' => $timeslots,
            ),
        ), 200);
        
    } catch (Exception $e) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => '時間帯データの取得に失敗しました。',
            'error' => $e->getMessage(),
        ), 500);
    }
}

/**
 * 指定期間の予約を取得
 */
function fpco_get_reservations_for_period($factory_id, $start_date, $end_date) {
    global $wpdb;
    
    $sql = "
        SELECT date, time_slot, status, participant_count, participants_child_count
        FROM {$wpdb->prefix}reservations
        WHERE factory_id = %d 
        AND date BETWEEN %s AND %s
        AND status IN ('new', 'pending', 'approved')
    ";
    
    $results = $wpdb->get_results(
        $wpdb->prepare($sql, $factory_id, $start_date, $end_date),
        ARRAY_A
    );
    
    $reservations = array();
    foreach ($results as $row) {
        $date = $row['date'];
        if (!isset($reservations[$date])) {
            $reservations[$date] = array();
        }
        $reservations[$date][] = $row;
    }
    
    return $reservations;
}

/**
 * 見学不可日を取得
 */
function fpco_get_unavailable_days($factory_id, $start_date, $end_date) {
    global $wpdb;
    
    // wp_unavailable_daysテーブルが存在しない場合は空配列を返す
    $table_name = $wpdb->prefix . 'unavailable_days';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        return array();
    }
    
    $sql = "
        SELECT date, am_unavailable, pm_unavailable
        FROM {$wpdb->prefix}unavailable_days
        WHERE factory_id = %d 
        AND date BETWEEN %s AND %s
    ";
    
    $results = $wpdb->get_results(
        $wpdb->prepare($sql, $factory_id, $start_date, $end_date),
        ARRAY_A
    );
    
    $unavailable = array();
    foreach ($results as $row) {
        $unavailable[$row['date']] = array(
            'am' => (bool)$row['am_unavailable'],
            'pm' => (bool)$row['pm_unavailable'],
        );
    }
    
    return $unavailable;
}

/**
 * 工場情報を取得
 */
function fpco_get_factory_info($factory_id) {
    $factories = array(
        1 => array('name' => '関東リサイクル', 'capacity' => 50),
        2 => array('name' => '中部リサイクル', 'capacity' => 50),
        3 => array('name' => '福山リサイクル', 'capacity' => 50),
        4 => array('name' => '山形選別センター', 'capacity' => 50),
        5 => array('name' => '松本選別センター', 'capacity' => 50),
        6 => array('name' => '西宮選別センター', 'capacity' => 50),
        7 => array('name' => '東海選別センター', 'capacity' => 50),
        8 => array('name' => '金沢選別センター', 'capacity' => 50),
        9 => array('name' => '九州選別センター', 'capacity' => 50),
    );
    
    return isset($factories[$factory_id]) ? array_merge($factories[$factory_id], array('id' => $factory_id)) : $factories[1];
}

/**
 * 時間帯の状況を計算
 */
function fpco_calculate_time_slot_status($date, $time_period, $factory_id, $reservations, $unavailable_days, $is_weekend, $is_holiday) {
    // 特別な日付（大晦日・元旦）をチェック
    $date_obj = new DateTime($date);
    $month = intval($date_obj->format('n'));
    $day = intval($date_obj->format('j'));
    $is_special_date = ($month === 12 && $day === 31) || ($month === 1 && $day === 1);
    
    // 土日祝日・特別日は見学不可
    if ($is_weekend || $is_holiday || $is_special_date) {
        return array('status' => 'unavailable', 'symbol' => '－');
    }
    
    // 見学不可日設定をチェック
    if (isset($unavailable_days[$date])) {
        $unavailable = $unavailable_days[$date];
        if (($time_period === 'am' && $unavailable['am']) || 
            ($time_period === 'pm' && $unavailable['pm'])) {
            return array('status' => 'unavailable', 'symbol' => '－');
        }
    }
    
    // 予約があるかチェック
    if (isset($reservations[$date])) {
        foreach ($reservations[$date] as $reservation) {
            $time_slot = $reservation['time_slot'];
            
            // AM/PMの判定（時間帯文字列から判断）
            $is_am_slot = (strpos($time_slot, 'AM') !== false) || 
                         (preg_match('/^(0[0-9]|1[0-1])/', $time_slot));
            $is_pm_slot = (strpos($time_slot, 'PM') !== false) || 
                         (preg_match('/^(1[2-9]|2[0-3])/', $time_slot));
            
            $slot_matches = false;
            if ($time_period === 'am' && $is_am_slot) {
                $slot_matches = true;
            } elseif ($time_period === 'pm' && $is_pm_slot) {
                $slot_matches = true;
            }
            
            if ($slot_matches) {
                if ($reservation['status'] === 'approved') {
                    return array('status' => 'unavailable', 'symbol' => '－');
                } else {
                    return array('status' => 'adjusting', 'symbol' => '△');
                }
            }
        }
    }
    
    // 空きあり
    return array('status' => 'available', 'symbol' => '〇');
}

/**
 * 利用可能な時間帯を取得
 */
function fpco_get_available_timeslots($factory_id, $date) {
    // TODO: 実際の時間帯設定に基づいて動的に生成
    // デモとして固定の時間帯を返す
    return array(
        array(
            'id' => 'am',
            'label' => 'AM（午前）',
            'duration' => 60,
            'times' => array('9:00〜10:00', '10:30〜11:30'),
        ),
        array(
            'id' => 'pm',
            'label' => 'PM（午後）',
            'duration' => 60,
            'times' => array('14:00〜15:00', '15:30〜16:30'),
        ),
    );
}

/**
 * 日本の祝日判定
 */
function fpco_is_japanese_holiday($date) {
    global $wpdb;
    
    // holidays-jp.github.io APIから取得したデータを使用
    // holiday-functions.php の関数を利用
    if (function_exists('fpco_is_holiday')) {
        return fpco_is_holiday($date);
    }
    
    // フォールバック: 直接データベースをチェック
    $table_name = $wpdb->prefix . 'holidays';
    
    // テーブル存在確認
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        return false;
    }
    
    $result = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE date = %s",
            $date
        )
    );
    
    return $result > 0;
}
?>