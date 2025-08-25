<?php
/**
 * Twenty Twenty-Five functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Five
 * @since Twenty Twenty-Five 1.0
 */

// Adds theme support for post formats.
if ( ! function_exists( 'twentytwentyfive_post_format_setup' ) ) :
	/**
	 * Adds theme support for post formats.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_post_format_setup() {
		add_theme_support( 'post-formats', array( 'aside', 'audio', 'chat', 'gallery', 'image', 'link', 'quote', 'status', 'video' ) );
	}
endif;
add_action( 'after_setup_theme', 'twentytwentyfive_post_format_setup' );

// Enqueues editor-style.css in the editors.
if ( ! function_exists( 'twentytwentyfive_editor_style' ) ) :
	/**
	 * Enqueues editor-style.css in the editors.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_editor_style() {
		add_editor_style( get_parent_theme_file_uri( 'assets/css/editor-style.css' ) );
	}
endif;
add_action( 'after_setup_theme', 'twentytwentyfive_editor_style' );

// Enqueues style.css on the front.
if ( ! function_exists( 'twentytwentyfive_enqueue_styles' ) ) :
	/**
	 * Enqueues style.css on the front.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_enqueue_styles() {
		wp_enqueue_style(
			'twentytwentyfive-style',
			get_parent_theme_file_uri( 'style.css' ),
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}
endif;
add_action( 'wp_enqueue_scripts', 'twentytwentyfive_enqueue_styles' );

// Registers custom block styles.
if ( ! function_exists( 'twentytwentyfive_block_styles' ) ) :
	/**
	 * Registers custom block styles.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_block_styles() {
		register_block_style(
			'core/list',
			array(
				'name'         => 'checkmark-list',
				'label'        => __( 'Checkmark', 'twentytwentyfive' ),
				'inline_style' => '
				ul.is-style-checkmark-list {
					list-style-type: "\2713";
				}

				ul.is-style-checkmark-list li {
					padding-inline-start: 1ch;
				}',
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_block_styles' );

// Registers pattern categories.
if ( ! function_exists( 'twentytwentyfive_pattern_categories' ) ) :
	/**
	 * Registers pattern categories.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_pattern_categories() {

		register_block_pattern_category(
			'twentytwentyfive_page',
			array(
				'label'       => __( 'Pages', 'twentytwentyfive' ),
				'description' => __( 'A collection of full page layouts.', 'twentytwentyfive' ),
			)
		);

		register_block_pattern_category(
			'twentytwentyfive_post-format',
			array(
				'label'       => __( 'Post formats', 'twentytwentyfive' ),
				'description' => __( 'A collection of post format patterns.', 'twentytwentyfive' ),
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_pattern_categories' );

// Registers block binding sources.
if ( ! function_exists( 'twentytwentyfive_register_block_bindings' ) ) :
	/**
	 * Registers the post format block binding source.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_register_block_bindings() {
		register_block_bindings_source(
			'twentytwentyfive/format',
			array(
				'label'              => _x( 'Post format name', 'Label for the block binding placeholder in the editor', 'twentytwentyfive' ),
				'get_value_callback' => 'twentytwentyfive_format_binding',
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_register_block_bindings' );

// Registers block binding callback function for the post format name.
if ( ! function_exists( 'twentytwentyfive_format_binding' ) ) :
	/**
	 * Callback function for the post format name block binding source.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return string|void Post format name, or nothing if the format is 'standard'.
	 */
	function twentytwentyfive_format_binding() {
		$post_format_slug = get_post_format();

		if ( $post_format_slug && 'standard' !== $post_format_slug ) {
			return get_post_format_string( $post_format_slug );
		}
	}
endif;

/**
 * 予約カレンダーのショートコード実装
 * 使用方法: [reservation_calendar factory="1"]
 */
function fpco_reservation_calendar_shortcode($atts) {
    // ショートコード属性のデフォルト値を設定
    $atts = shortcode_atts(array(
        'factory' => '1', // デフォルト工場ID
    ), $atts, 'reservation_calendar');
    
    // factory パラメータを GET パラメータとして設定
    $_GET['factory'] = $atts['factory'];
    
    // カレンダーのCSSとJSを読み込み
    wp_enqueue_style('calendar-style', get_template_directory_uri() . '/assets/css/calendar.css', array(), '1.0');
    wp_enqueue_script('calendar-script', get_template_directory_uri() . '/assets/js/calendar.js', array('jquery'), '1.0', true);
    
    // カレンダー用のデータをJavaScriptに渡す
    wp_localize_script('calendar-script', 'calendarData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'factoryId' => $atts['factory'],
        'reservationFormUrl' => home_url('/reservation-form/')
    ));
    
    // 出力バッファリングを開始
    ob_start();
    
    // カレンダーの HTML を出力
    ?>
    <div class="reservation-calendar-shortcode alignfull">
        <div class="calendar-container">
            <!-- 年月選択エリア -->
            <div class="calendar-controls">
                <div class="month-selector">
                    <select id="calendar-month-select" class="month-select">
                        <?php
                        // 今月から12ヶ月先まで表示
                        $current_date = new DateTime();
                        for ($i = 0; $i <= 12; $i++) {
                            $date = clone $current_date;
                            $date->add(new DateInterval("P{$i}M"));
                            $year = $date->format('Y');
                            $month = $date->format('n');
                            $display = $date->format('Y年n月');
                            $value = $date->format('Y-m');
                            
                            $selected = ($i === 0) ? 'selected' : '';
                            echo "<option value=\"{$value}\" {$selected}>{$display}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <!-- 工場選択（非表示） -->
                <div class="factory-selector" style="display: none;">
                    <?php
                    $factory_id = $atts['factory'];
                    $factory_name = fpco_get_factory_name($factory_id);
                    ?>
                    <span class="selected-factory"><?php echo esc_html($factory_name); ?></span>
                </div>
            </div>

            <!-- カレンダー本体 -->
            <div id="calendar" class="calendar" data-factory-id="<?php echo esc_attr($factory_id); ?>">
                <!-- PC版カレンダーグリッド -->
                <div class="calendar-grid-container desktop-only">
                    <div id="calendar-grid" class="calendar-grid">
                        <!-- JavaScriptで動的に生成 -->
                    </div>
                </div>
                
                <!-- スマホ版カレンダーリスト -->
                <div class="calendar-list-container mobile-only">
                    <div id="calendar-list" class="calendar-list">
                        <!-- JavaScriptで動的に生成 -->
                    </div>
                </div>
            </div>
            
            <!-- 凡例 -->
            <div class="calendar-legend">
                <div class="legend-items">
                    <div class="legend-item">
                        <div class="legend-symbol available">〇</div>
                        <div class="legend-text">・・・空きがあります。ご希望の日付をクリックしてください。(※ 50名まで可)</div>
                    </div>
                    <div class="legend-item">
                        <div class="legend-symbol adjusting">△</div>
                        <div class="legend-text">・・・調整中です。</div>
                    </div>
                    <div class="legend-item">
                        <div class="legend-symbol unavailable">－</div>
                        <div class="legend-text">・・・受付を行っておりません。</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- タイムスロット選択モーダル -->
        <div id="timeslot-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-date-title">時間帯を選択</h3>
                    <span class="modal-close">&times;</span>
                </div>
                <div class="modal-body">
                    <p id="modal-selected-date"></p>
                    <div id="timeslot-options">
                        <!-- 動的に生成される -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button button-secondary" onclick="window.reservationCalendar.closeModal()">キャンセル</button>
                    <button type="button" class="button button-primary" id="confirm-timeslot" disabled>予約フォームへ進む</button>
                </div>
            </div>
        </div>
    </div>
    <?php
    
    // バッファリングされた内容を取得して返す
    return ob_get_clean();
}
add_shortcode('reservation_calendar', 'fpco_reservation_calendar_shortcode');

/**
 * 工場名を取得するヘルパー関数
 */
function fpco_get_factory_name($factory_id) {
    $factories = array(
        1 => '関東リサイクル',
        2 => '中部リサイクル',
        3 => '福山リサイクル',
        4 => '山形選別センター',
        5 => '松本選別センター',
        6 => '西宮選別センター',
        7 => '東海選別センター',
        8 => '金沢選別センター',
        9 => '九州選別センター'
    );
    
    return isset($factories[$factory_id]) ? $factories[$factory_id] : '';
}

/**
 * カレンダーデータを取得するAJAXハンドラー
 */
add_action('wp_ajax_get_calendar_data', 'fpco_ajax_get_calendar_data');
add_action('wp_ajax_nopriv_get_calendar_data', 'fpco_ajax_get_calendar_data');

/**
 * 工場別タイムスロットデータを取得するAJAXハンドラー
 */
add_action('wp_ajax_get_factory_timeslots', 'fpco_ajax_get_factory_timeslots');
add_action('wp_ajax_nopriv_get_factory_timeslots', 'fpco_ajax_get_factory_timeslots');

function fpco_ajax_get_calendar_data() {
    // パラメータの取得
    $month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : date('Y-m');
    $factory_id = isset($_GET['factory']) ? intval($_GET['factory']) : 1;
    
    // 月の情報を解析
    $date_parts = explode('-', $month);
    $year = intval($date_parts[0]);
    $month_num = intval($date_parts[1]);
    
    // カレンダーデータを生成
    $calendar_data = fpco_generate_calendar_data($year, $month_num, $factory_id);
    
    // JSONで返す
    wp_send_json_success($calendar_data);
}

/**
 * カレンダーデータを生成
 */
function fpco_generate_calendar_data($year, $month, $factory_id) {
    global $wpdb;
    
    // 月の開始日と終了日を計算
    $first_day = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
    $last_day = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
    
    // カレンダー表示用に前後の日付も含める
    $calendar_start = date('Y-m-d', strtotime('last Sunday', strtotime($first_day)));
    if ($calendar_start == $first_day) {
        $calendar_start = date('Y-m-d', strtotime('-1 week', strtotime($first_day)));
    }
    $calendar_end = date('Y-m-d', strtotime('next Saturday', strtotime($last_day)));
    if ($calendar_end == $last_day) {
        $calendar_end = date('Y-m-d', strtotime('+1 week', strtotime($last_day)));
    }
    
    // 祝日データを取得
    $holidays = fpco_get_holidays_for_period($calendar_start, $calendar_end);
    
    // 見学不可日を取得（タイムスタンプも含む）
    $unavailable_days_results = $wpdb->get_results($wpdb->prepare(
        "SELECT date, am_unavailable, pm_unavailable, is_manual, created_at, updated_at
         FROM {$wpdb->prefix}unavailable_days 
         WHERE factory_id = %d 
         AND date >= %s 
         AND date <= %s",
        $factory_id,
        $calendar_start,
        $calendar_end
    ), ARRAY_A);
    
    // 見学不可日を配列に整形（タイムスタンプ情報も含む）
    $unavailable_days = array();
    foreach ($unavailable_days_results as $day) {
        $unavailable_days[$day['date']] = array(
            'am' => (bool)$day['am_unavailable'],
            'pm' => (bool)$day['pm_unavailable'],
            'is_manual' => (bool)$day['is_manual'],
            'created_at' => $day['created_at'],
            'updated_at' => $day['updated_at']
        );
    }
    
    // 予約データを取得（タイムスタンプも含む）
    $reservations_results = $wpdb->get_results($wpdb->prepare(
        "SELECT date, time_slot, status, created_at
         FROM {$wpdb->prefix}reservations 
         WHERE factory_id = %d 
         AND date >= %s 
         AND date <= %s
         AND status IN ('new', 'pending', 'approved')",
        $factory_id,
        $calendar_start,
        $calendar_end
    ), ARRAY_A);
    
    // 予約データを配列に整形
    $reservations = array();
    foreach ($reservations_results as $reservation) {
        if (!isset($reservations[$reservation['date']])) {
            $reservations[$reservation['date']] = array();
        }
        $reservations[$reservation['date']][] = $reservation;
    }
    
    // 工場情報
    $factory_info = array(
        'id' => $factory_id,
        'name' => fpco_get_factory_name($factory_id),
        'capacity' => 50
    );
    
    // 各日付の状況を計算
    $calendar_days = array();
    $current_date = $calendar_start;
    
    while ($current_date <= $calendar_end) {
        $date_obj = new DateTime($current_date);
        $weekday = intval($date_obj->format('w'));
        
        // 土日は見学不可
        $is_weekend = ($weekday === 0 || $weekday === 6);
        
        // 祝日チェック
        $is_holiday = isset($holidays[$current_date]);
        
        // 各時間帯の状況を判定（優先度ロジック付き）
        $am_status = fpco_calculate_slot_status_with_priority($current_date, 'am', $unavailable_days, $reservations, $is_weekend, $is_holiday);
        $pm_status = fpco_calculate_slot_status_with_priority($current_date, 'pm', $unavailable_days, $reservations, $is_weekend, $is_holiday);
        
        $calendar_days[$current_date] = array(
            'date' => $current_date,
            'weekday' => $weekday,
            'is_other_month' => ($date_obj->format('Y-m') !== sprintf('%04d-%02d', $year, $month)),
            'is_holiday' => $is_holiday,
            'am' => $am_status,
            'pm' => $pm_status
        );
        
        $current_date = date('Y-m-d', strtotime('+1 day', strtotime($current_date)));
    }
    
    return array(
        'year' => $year,
        'month' => $month,
        'calendar_start' => $calendar_start,
        'calendar_end' => $calendar_end,
        'factory' => $factory_info,
        'days' => $calendar_days
    );
}

/**
 * 時間帯の状況を計算
 */
function fpco_calculate_slot_status($date, $time_period, $unavailable_days, $reservations, $is_weekend, $is_holiday = false) {
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
            
            // AM/PMの判定
            $is_am_slot = (strpos($time_slot, 'AM') !== false) || 
                         (strpos($time_slot, '午前') !== false) ||
                         (preg_match('/^(0[0-9]|1[0-1])/', $time_slot));
            $is_pm_slot = (strpos($time_slot, 'PM') !== false) || 
                         (strpos($time_slot, '午後') !== false) ||
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

function fpco_ajax_get_factory_timeslots() {
    $factory_id = isset($_GET['factory']) ? intval($_GET['factory']) : 1;
    
    // プラグインの工場別時間設定関数を呼び出し
    if (function_exists('fpco_get_factory_timeslots')) {
        $timeslots = fpco_get_factory_timeslots($factory_id);
        wp_send_json_success($timeslots);
    } else {
        wp_send_json_error(array('message' => '工場時間設定が取得できませんでした。'));
    }
}

/**
 * テーマ専用の祝日チェック関数
 */
function fpco_is_theme_holiday($date) {
    global $wpdb;
    
    // プラグインの祝日関数を使用（プラグインが有効な場合）
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

/**
 * 優先度ロジック付きの時間帯状況計算
 */
function fpco_calculate_slot_status_with_priority($date, $time_period, $unavailable_days, $reservations, $is_weekend, $is_holiday = false) {
    // 特別な日付（大晦日・元旦）をチェック
    $date_obj = new DateTime($date);
    $month = intval($date_obj->format('n'));
    $day = intval($date_obj->format('j'));
    $is_special_date = ($month === 12 && $day === 31) || ($month === 1 && $day === 1);
    $weekday = intval($date_obj->format('w'));
    
    // 特別日（大晦日・元旦）は一律見学不可
    if ($is_special_date) {
        return array('status' => 'unavailable', 'symbol' => '－');
    }
    
    // 予約データから該当する時間帯を取得（複数予約対応）
    $reservation_timestamp = null;
    $reservation_status = null;
    $has_pending_reservation = false; // 新規受付・確認中の予約があるかフラグ
    $has_approved_reservation = false; // 承認済み予約があるかフラグ
    
    if (isset($reservations[$date])) {
        foreach ($reservations[$date] as $reservation) {
            $time_slot = $reservation['time_slot'];
            
            // AM/PMの判定
            $is_am_slot = (strpos($time_slot, 'AM') !== false) || 
                         (strpos($time_slot, '午前') !== false) ||
                         (preg_match('/^(0[0-9]|1[0-1])/', $time_slot));
            $is_pm_slot = (strpos($time_slot, 'PM') !== false) || 
                         (strpos($time_slot, '午後') !== false) ||
                         (preg_match('/^(1[2-9]|2[0-3])/', $time_slot));
            
            $slot_matches = false;
            if ($time_period === 'am' && $is_am_slot) {
                $slot_matches = true;
            } elseif ($time_period === 'pm' && $is_pm_slot) {
                $slot_matches = true;
            }
            
            if ($slot_matches) {
                $reservation_timestamp = $reservation['created_at'];
                
                // 予約のステータスを判定
                if ($reservation['status'] === 'approved') {
                    $has_approved_reservation = true;
                } else {
                    // 新規受付・確認中（new, pending）
                    $has_pending_reservation = true;
                }
            }
        }
        
        // 優先度判定：新規受付・確認中があれば△、なければ承認済みの－
        if ($has_pending_reservation) {
            $reservation_status = 'pending'; // △表示用
        } elseif ($has_approved_reservation) {
            $reservation_status = 'approved'; // －表示用
        }
    }
    
    // 手動設定データを取得
    $manual_timestamp = null;
    $manual_unavailable = false;
    $has_manual_setting = false;
    $manual_available = false;
    
    if (isset($unavailable_days[$date])) {
        $unavailable = $unavailable_days[$date];
        $has_manual_setting = $unavailable['is_manual'];
        
        if (($time_period === 'am' && $unavailable['am']) || 
            ($time_period === 'pm' && $unavailable['pm'])) {
            $manual_unavailable = true;
            $manual_timestamp = $unavailable['updated_at'] ? $unavailable['updated_at'] : $unavailable['created_at'];
        } else if ($has_manual_setting) {
            // 手動設定があって、該当時間帯が利用可能な場合（チェックが外れている）
            $manual_available = true;
            $manual_timestamp = $unavailable['updated_at'] ? $unavailable['updated_at'] : $unavailable['created_at'];
        }
        
        // デバッグログ
        error_log("Debug: $date $time_period - manual_setting: " . ($has_manual_setting ? 'true' : 'false') . 
                 ", unavailable: " . ($manual_unavailable ? 'true' : 'false') . 
                 ", available: " . ($manual_available ? 'true' : 'false') . 
                 ", pending_res: " . ($has_pending_reservation ? 'true' : 'false') . 
                 ", approved_res: " . ($has_approved_reservation ? 'true' : 'false') . 
                 ", res_status: " . ($reservation_status ? $reservation_status : 'none'));
    }
    
    // 優先度判定
    // 1. 手動で利用可能にした場合（最優先）- 予約の有無に関わらず○を表示
    if ($manual_available) {
        error_log("Debug: $date $time_period - Returning ○ (manual available)");
        return array('status' => 'available', 'symbol' => '〇');
    }
    
    // 2. 管理画面でチェックがついていて予約がある場合は予約ステータスを優先
    if ($manual_unavailable && $reservation_timestamp) {
        if ($reservation_status === 'approved') {
            error_log("Debug: $date $time_period - Returning － (manual unavailable + approved)");
            return array('status' => 'unavailable', 'symbol' => '－');
        } else {
            error_log("Debug: $date $time_period - Returning △ (manual unavailable + pending)");
            return array('status' => 'adjusting', 'symbol' => '△');
        }
    }
    
    // 3. 手動で見学不可にした場合のみ（予約がない場合）
    if ($manual_unavailable) {
        error_log("Debug: $date $time_period - Returning － (manual unavailable only)");
        return array('status' => 'unavailable', 'symbol' => '－');
    }
    
    // 4. 祝日のデフォルト処理
    if ($is_holiday) {
        // 祝日で予約がある場合は予約ステータスに応じて表示
        if ($reservation_timestamp) {
            if ($reservation_status === 'approved') {
                return array('status' => 'unavailable', 'symbol' => '－');
            } else {
                return array('status' => 'adjusting', 'symbol' => '△');
            }
        }
        return array('status' => 'unavailable', 'symbol' => '－');
    }
    
    // 5. 土日（日曜日・土曜日）のデフォルト処理
    if ($is_weekend) {
        // 土日で予約がある場合は予約ステータスに応じて表示
        if ($reservation_timestamp) {
            if ($reservation_status === 'approved') {
                return array('status' => 'unavailable', 'symbol' => '－');
            } else {
                return array('status' => 'adjusting', 'symbol' => '△');
            }
        }
        return array('status' => 'unavailable', 'symbol' => '－');
    }
    
    // 6. 平日で予約のみある場合（手動設定なし）
    if ($reservation_timestamp) {
        if ($reservation_status === 'approved') {
            return array('status' => 'unavailable', 'symbol' => '－');
        } else {
            return array('status' => 'adjusting', 'symbol' => '△');
        }
    }
    
    // 7. 平日で何も設定がない場合は利用可能
    return array('status' => 'available', 'symbol' => '〇');
}

/**
 * 期間内の祝日データを取得
 */
function fpco_get_holidays_for_period($start_date, $end_date) {
    global $wpdb;
    
    // プラグインの祝日取得関数を使用（プラグインが有効な場合）
    if (function_exists('fpco_get_holidays')) {
        return fpco_get_holidays($start_date, $end_date);
    }
    
    // フォールバック: 直接データベースから取得
    $table_name = $wpdb->prefix . 'holidays';
    
    // テーブル存在確認
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        return array();
    }
    
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

