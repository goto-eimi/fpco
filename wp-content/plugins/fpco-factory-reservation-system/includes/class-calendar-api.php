<?php
/**
 * カレンダーAPIクラス 
 * 
 * 工場見学カレンダーの予約状況取得機能を提供
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPCO_Calendar_API {
    
    public function __construct() {
        // AJAX エンドポイントの登録
        add_action('wp_ajax_get_reservation_data', array($this, 'get_reservation_data'));
        add_action('wp_ajax_nopriv_get_reservation_data', array($this, 'get_reservation_data'));
    }
    
    /**
     * 予約データを取得
     */
    public function get_reservation_data() {
        global $wpdb;
        
        // パラメータの取得
        $factory_id = intval($_GET['factory_id'] ?? 0);
        $year = intval($_GET['year'] ?? date('Y'));
        $month = intval($_GET['month'] ?? date('n'));
        
        if (!$factory_id) {
            wp_send_json_error('工場IDが指定されていません');
        }
        
        // 対象月の範囲を計算
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));
        
        // 予約データを取得
        $reservations = $wpdb->get_results($wpdb->prepare(
            "SELECT date, time_slot, status, participant_count 
             FROM {$wpdb->prefix}reservations 
             WHERE factory_id = %d 
             AND date BETWEEN %s AND %s 
             AND status IN ('pending', 'approved')",
            $factory_id,
            $start_date,
            $end_date
        ));
        
        // 見学不可日を取得
        $unavailable_days = $wpdb->get_results($wpdb->prepare(
            "SELECT date, am_unavailable, pm_unavailable 
             FROM {$wpdb->prefix}unavailable_days 
             WHERE factory_id = %d 
             AND date BETWEEN %s AND %s",
            $factory_id,
            $start_date,
            $end_date
        ));
        
        // 工場の定員を取得
        $factory = $wpdb->get_row($wpdb->prepare(
            "SELECT capacity FROM {$wpdb->prefix}factorys WHERE id = %d",
            $factory_id
        ));
        
        $capacity = $factory ? $factory->capacity : 50;
        
        // データを整理
        $calendar_data = array();
        $unavailable_data = array();
        
        // 見学不可日の処理
        foreach ($unavailable_days as $unavailable) {
            $unavailable_data[$unavailable->date] = array(
                'am_unavailable' => (bool)$unavailable->am_unavailable,
                'pm_unavailable' => (bool)$unavailable->pm_unavailable
            );
        }
        
        // 予約データの処理
        foreach ($reservations as $reservation) {
            $date = $reservation->date;
            $time_slot = $reservation->time_slot;
            
            if (!isset($calendar_data[$date])) {
                $calendar_data[$date] = array();
            }
            
            // 時間帯の判定（AM/PM）
            $period = $this->determine_period_from_time_slot($time_slot);
            
            if (!isset($calendar_data[$date][$period])) {
                $calendar_data[$date][$period] = array(
                    'count' => 0,
                    'status' => 'available'
                );
            }
            
            $calendar_data[$date][$period]['count'] += intval($reservation->participant_count);
            
            // ステータスの判定
            if ($calendar_data[$date][$period]['count'] >= $capacity) {
                $calendar_data[$date][$period]['status'] = 'full';
            } elseif ($calendar_data[$date][$period]['count'] > 0) {
                $calendar_data[$date][$period]['status'] = 'partial';
            }
        }
        
        // 見学不可日の状況を反映
        foreach ($unavailable_data as $date => $unavailable_info) {
            if (!isset($calendar_data[$date])) {
                $calendar_data[$date] = array();
            }
            
            if ($unavailable_info['am_unavailable']) {
                $calendar_data[$date]['AM'] = array(
                    'count' => 0,
                    'status' => 'unavailable'
                );
            }
            
            if ($unavailable_info['pm_unavailable']) {
                $calendar_data[$date]['PM'] = array(
                    'count' => 0,
                    'status' => 'unavailable'
                );
            }
        }
        
        wp_send_json_success(array(
            'calendar_data' => $calendar_data,
            'capacity' => $capacity,
            'factory_id' => $factory_id,
            'year' => $year,
            'month' => $month
        ));
    }
    
    /**
     * 時間スロットからAM/PMを判定
     */
    private function determine_period_from_time_slot($time_slot) {
        // 時間形式（09:00-10:00）の場合
        if (preg_match('/^(\d{1,2}):\d{2}-\d{1,2}:\d{2}$/', $time_slot, $matches)) {
            $hour = intval($matches[1]);
            return ($hour < 12) ? 'AM' : 'PM';
        }
        
        // コード形式（am-60-1）の場合
        if (strpos($time_slot, 'am-') === 0) {
            return 'AM';
        } elseif (strpos($time_slot, 'pm-') === 0) {
            return 'PM';
        }
        
        // デフォルト判定（時刻に基づく）
        $hour = intval(date('H', strtotime($time_slot)));
        return ($hour < 12) ? 'AM' : 'PM';
    }
}

// インスタンスを作成
new FPCO_Calendar_API();