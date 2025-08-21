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
                
                <!-- 工場選択 -->
                <div class="factory-selector">
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
    
    // 月の開始日と終了日
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));
    
    // 見学不可日を取得
    $unavailable_days = $wpdb->get_results($wpdb->prepare(
        "SELECT date, am_unavailable, pm_unavailable 
         FROM {$wpdb->prefix}unavailable_days 
         WHERE factory_id = %d 
         AND date >= %s 
         AND date <= %s",
        $factory_id,
        $start_date,
        $end_date
    ), ARRAY_A);
    
    // 予約データを取得
    $reservations = $wpdb->get_results($wpdb->prepare(
        "SELECT date, time_slot, 
         COUNT(*) as reservation_count,
         SUM(participant_count) as total_participants
         FROM {$wpdb->prefix}reservations 
         WHERE factory_id = %d 
         AND date >= %s 
         AND date <= %s
         AND status IN ('approved', 'pending')
         GROUP BY date, time_slot",
        $factory_id,
        $start_date,
        $end_date
    ), ARRAY_A);
    
    // 工場の定員を取得
    $factory_capacity = $wpdb->get_var($wpdb->prepare(
        "SELECT capacity FROM {$wpdb->prefix}factorys WHERE id = %d",
        $factory_id
    )) ?: 50;
    
    // データを整形
    $calendar_data = array(
        'year' => $year,
        'month' => $month,
        'factory_id' => $factory_id,
        'factory_capacity' => $factory_capacity,
        'unavailable_days' => array(),
        'reservations' => array()
    );
    
    // 見学不可日を整形
    foreach ($unavailable_days as $day) {
        $calendar_data['unavailable_days'][$day['date']] = array(
            'am' => (bool)$day['am_unavailable'],
            'pm' => (bool)$day['pm_unavailable']
        );
    }
    
    // 予約データを整形
    foreach ($reservations as $reservation) {
        if (!isset($calendar_data['reservations'][$reservation['date']])) {
            $calendar_data['reservations'][$reservation['date']] = array();
        }
        
        $calendar_data['reservations'][$reservation['date']][$reservation['time_slot']] = array(
            'count' => intval($reservation['reservation_count']),
            'participants' => intval($reservation['total_participants'])
        );
    }
    
    return $calendar_data;
}

