<?php
/**
 * カレンダーショートコード機能
 */

// プラグインの直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * カレンダーショートコードを登録
 */
add_shortcode('reservation_calendar', 'render_reservation_calendar_shortcode');

function render_reservation_calendar_shortcode($atts) {
    // ショートコード属性を解析
    $atts = shortcode_atts(array(
        'factory' => '1',
        'height' => 'auto',
        'show_legend' => 'true',
    ), $atts, 'reservation_calendar');
    
    $factory_id = intval($atts['factory']);
    $show_legend = ($atts['show_legend'] === 'true');
    
    // 一意のIDを生成（同一ページに複数設置可能）
    $calendar_id = 'calendar-' . uniqid();
    
    // カレンダーHTML
    ob_start();
    ?>
    <div class="calendar-shortcode-container" id="<?php echo esc_attr($calendar_id); ?>">
        <div class="calendar-container">
            <!-- 年月選択エリア -->
            <div class="calendar-controls">
                <div class="month-selector">
                    <label for="<?php echo esc_attr($calendar_id); ?>-month-select">表示月:</label>
                    <select id="<?php echo esc_attr($calendar_id); ?>-month-select" class="month-select">
                        <?php
                        $current_date = new DateTime();
                        for ($i = 0; $i <= 12; $i++) {
                            $date = clone $current_date;
                            $date->add(new DateInterval("P{$i}M"));
                            $display = $date->format('Y年n月');
                            $value = $date->format('Y-m');
                            $selected = ($i === 0) ? 'selected' : '';
                            echo "<option value=\"{$value}\" {$selected}>{$display}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="factory-selector">
                    <span class="selected-factory"><?php echo esc_html(get_factory_name_shortcode($factory_id)); ?>工場</span>
                </div>
            </div>

            <!-- カレンダー本体（PC版） -->
            <div class="calendar-grid-container desktop-only">
                <div class="calendar-grid" id="<?php echo esc_attr($calendar_id); ?>-grid">
                    <div class="calendar-loading">
                        <div class="spinner"></div>
                        <p>カレンダーを読み込み中...</p>
                    </div>
                </div>
            </div>

            <!-- カレンダー（スマホ版） -->
            <div class="calendar-list-container mobile-only">
                <div class="calendar-list" id="<?php echo esc_attr($calendar_id); ?>-list">
                    <div class="calendar-loading">
                        <div class="spinner"></div>
                        <p>カレンダーを読み込み中...</p>
                    </div>
                </div>
            </div>

            <?php if ($show_legend): ?>
            <!-- 凡例 -->
            <div class="calendar-legend">
                <div class="legend-items">
                    <div class="legend-item">
                        <span class="legend-symbol available">◯</span>
                        <span class="legend-text">空きがあります。ご希望の日付をクリックしてください。(※ 50名まで可)</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-symbol adjusting">△</span>
                        <span class="legend-text">調整中です。</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-symbol unavailable">－</span>
                        <span class="legend-text">受付を行っておりません。</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 時間帯選択モーダル -->
        <div id="<?php echo esc_attr($calendar_id); ?>-modal" class="modal-overlay" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>時間帯を選択してください</h3>
                    <button type="button" class="modal-close" aria-label="閉じる">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="selected-date">
                        <p id="<?php echo esc_attr($calendar_id); ?>-modal-date"></p>
                    </div>
                    <div class="timeslot-options" id="<?php echo esc_attr($calendar_id); ?>-timeslot-options">
                        <!-- JavaScriptで動的に生成 -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel">キャンセル</button>
                    <button type="button" class="btn-proceed">予約フォームへ進む</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        // ショートコード用のカレンダー初期化
        document.addEventListener('DOMContentLoaded', function() {
            const calendarInstance = new ReservationCalendarShortcode({
                containerId: '<?php echo esc_js($calendar_id); ?>',
                factoryId: '<?php echo esc_js($factory_id); ?>',
                monthSelectId: '<?php echo esc_js($calendar_id); ?>-month-select',
                gridId: '<?php echo esc_js($calendar_id); ?>-grid',
                listId: '<?php echo esc_js($calendar_id); ?>-list',
                modalId: '<?php echo esc_js($calendar_id); ?>-modal',
                modalDateId: '<?php echo esc_js($calendar_id); ?>-modal-date',
                timeslotOptionsId: '<?php echo esc_js($calendar_id); ?>-timeslot-options'
            });
        });
    })();
    </script>
    <?php
    
    $output = ob_get_clean();
    
    // スタイルとスクリプトをエンキュー
    wp_enqueue_style('calendar-shortcode-style', plugins_url('assets/css/calendar-shortcode.css', dirname(__FILE__)), array(), '1.0.0');
    wp_enqueue_script('calendar-shortcode-script', plugins_url('assets/js/calendar-shortcode.js', dirname(__FILE__)), array(), '1.0.0', true);
    
    return $output;
}

/**
 * 工場名を取得するヘルパー関数
 */
function get_factory_name_shortcode($factory_id) {
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
    
    return isset($factories[$factory_id]) ? $factories[$factory_id] : '関東リサイクル';
}