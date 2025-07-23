<?php
/**
 * Template Name: 予約状況カレンダー
 * 
 * 工場見学の予約状況を月間カレンダー形式で表示
 */

get_header(); ?>

<main id="main" class="wp-block-group">
    <div class="wp-block-group__inner-container">
        <header class="entry-header">
            <h1 class="entry-title">[フロント]予約状況カレンダー</h1>
            <p class="calendar-description">
                日付を選択し、表示されたポップアップよりご希望の時間帯を選択してください。
            </p>
        </header>

        <div class="calendar-container">
            <!-- 年月選択エリア -->
            <div class="calendar-controls">
                <div class="month-selector">
                    <label for="calendar-month-select">表示月:</label>
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
                
                <!-- 工場選択（URLパラメータから取得） -->
                <div class="factory-selector">
                    <?php
                    $factory_id = isset($_GET['factory']) ? sanitize_text_field($_GET['factory']) : 1;
                    $factory_name = get_factory_name($factory_id);
                    ?>
                    <span class="selected-factory"><?php echo esc_html($factory_name); ?>工場</span>
                </div>
            </div>

            <!-- カレンダー本体（PC版） -->
            <div class="calendar-grid-container desktop-only">
                <div class="calendar-grid" id="calendar-grid">
                    <!-- JavaScriptで動的に生成 -->
                    <div class="calendar-loading">
                        <div class="spinner"></div>
                        <p>カレンダーを読み込み中...</p>
                    </div>
                </div>
            </div>

            <!-- カレンダー（スマホ版） -->
            <div class="calendar-list-container mobile-only">
                <div class="calendar-list" id="calendar-list">
                    <!-- JavaScriptで動的に生成 -->
                    <div class="calendar-loading">
                        <div class="spinner"></div>
                        <p>カレンダーを読み込み中...</p>
                    </div>
                </div>
            </div>

            <!-- 凡例 -->
            <div class="calendar-legend">
                <div class="legend-items">
                    <div class="legend-item">
                        <span class="legend-symbol available">〇</span>
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
        </div>
        </div>

        <!-- 時間帯選択モーダル -->
        <div id="timeslot-modal" class="modal-overlay" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>時間帯の指定</h3>
                    <button type="button" class="modal-close" aria-label="閉じる">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="selected-date">
                        <p id="modal-selected-date"></p>
                    </div>
                    <div class="timeslot-options" id="timeslot-options">
                        <!-- JavaScriptで動的に生成 -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
/* カレンダーの基本スタイル */
.calendar-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.calendar-controls {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 30px;
    padding: 15px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    border: 1px solid #dee2e6;
    gap: 30px;
}

.calendar-controls .month-selector {
    text-align: center;
}

.calendar-controls .factory-selector {
    text-align: center;
}

.month-selector select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}

.selected-factory {
    font-weight: bold;
    color: #007cba;
    font-size: 18px;
}

/* PC版カレンダーグリッド */
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: #E0E0E0;
    border: 2px solid #E0E0E0;
    border-radius: 8px;
    overflow: hidden;
}

.calendar-day-header {
    background: #007cba;
    color: white;
    padding: 8px 5px;
    text-align: center;
    font-weight: bold;
}

.calendar-day {
    background: white;
    min-height: 100px;
    padding: 0px 5px;
    position: relative;
    cursor: default;
}

.calendar-day.clickable {
    cursor: pointer;
}

.calendar-day.clickable:hover {
    background: #f0f8ff;
}

.calendar-day.today {
    background: #fff3cd;
}

.calendar-day.other-month {
    background: #f5f5f5;
    color: #999;
}

.day-number {
    display: block;
    text-align: center;
    font-weight: bold;
    border-bottom: 1px solid #ddd;
    margin-bottom: 10px;
}

.day-number.sunday {
    color: #dc3545;
}

.day-number.saturday {
    color: #0066cc;
}

.time-slots {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 0 10px;
    margin-bottom: 8px;
}

.time-slot {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.time-label {
    font-weight: bold;
    font-size: 14px;
}

.status-button {
    display: inline-block;
    width: 75px;
    height: 24px;
    border-radius: 20px;
    text-align: center;
    line-height: 24px;
    font-size: 16px;
    font-weight: bold;
    cursor: default;
    border: none;
    text-decoration: none;
}

.status-button.available {
    background-color: #1A76D2;
    color: white;
    cursor: pointer;
}

.status-button.available:hover {
    background-color: #1565C0;
}

.status-button.adjusting {
    background-color: #25DF01;
    color: white;
}

.status-button.unavailable {
    background-color: #E0E0E0;
    color: red;
}

.status-button.none {
    background: transparent;
    cursor: default;
}


/* スマホ版リスト表示 */
.calendar-list {
    display: none;
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: white;
    -webkit-overflow-scrolling: touch; /* iOS慣性スクロール */
}

.calendar-list-item {
    display: block;
    border-bottom: 1px solid #ddd;
    transition: background-color 0.2s ease;
}

.calendar-list-item:last-child {
    border-bottom: none;
}

.calendar-list-item.today {
    background: linear-gradient(90deg, #fff3cd 0%, #ffffff 50%);
}

.calendar-list-item.past {
    opacity: 0.6;
}

/* 新しいスマホ版レイアウト - 縦線区切り */
.list-content {
    display: flex;
    align-items: center;
    font-size: 16px;
    min-height: 50px;
}

.list-date-section {
    background: #BAB6AD;
    color: white;
    padding: 12px 8px;
    text-align: center;
    min-width: 50px;
    border-right: 1px solid #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
}

.list-weekday-section {
    background: white;
    color: black;
    padding: 12px 8px;
    text-align: center;
    min-width: 40px;
    border-right: 1px solid #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
}

.list-time-section {
    background: white;
    padding: 8px;
    display: flex;
    flex: 1;
    align-items: center;
    justify-content: space-around;
    gap: 20px;
}

.list-am-slot, .list-pm-slot {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    justify-content: center;
}

.list-day-number {
    font-size: 18px;
    font-weight: bold;
    color: white;
}

.list-day-number.sunday {
    color: #ffcdd2;
}

.list-day-number.saturday {
    color: #bbdefb;
}

.list-weekday {
    font-size: 14px;
    color: black;
    font-weight: normal;
}

.list-am-slot, .list-pm-slot {
    font-size: 14px;
    font-weight: 600;
    color: #495057;
}

/* スマホ版ボタンスタイル */
.mobile-status-button {
    display: inline-block;
    width: 50px;
    height: 20px;
    border-radius: 15px;
    text-align: center;
    line-height: 20px;
    font-size: 14px;
    font-weight: bold;
    cursor: default;
    border: none;
    text-decoration: none;
}

.mobile-status-button.available {
    background-color: #1A76D2;
    color: white;
    cursor: pointer;
}

.mobile-status-button.available:hover {
    background-color: #1565C0;
}

.mobile-status-button.adjusting {
    background-color: #25DF01;
    color: white;
}

.mobile-status-button.unavailable {
    background-color: #E0E0E0;
    color: red;
}

.mobile-status-button.none {
    background: transparent;
    cursor: default;
}

/* 凡例 */
.calendar-legend {
    margin-top: 40px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.calendar-legend .legend-items {
    display: grid;
    gap: 10px;
    grid-template-columns: 1fr;
}

.calendar-legend h3 {
    margin-top: 0;
    margin-bottom: 15px;
}

.legend-items {
    display: grid;
    gap: 10px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.legend-symbol {
    display: inline-block;
    width: 75px;
    height: 24px;
    border-radius: 20px;
    text-align: center;
    line-height: 24px;
    font-size: 16px;
    font-weight: bold;
}

.legend-symbol.available {
    background-color: #1A76D2;
    color: white;
}

.legend-symbol.adjusting {
    background-color: #25DF01;
    color: white;
}

.legend-symbol.unavailable {
    background-color: #E0E0E0;
    color: red;
}

/* モーダル */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #ddd;
    background: #8DC641;
    color: white;
}

.modal-header h3 {
    margin: 0;
    color: white;
    text-align: center;
    width: 100%;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: white;
}

.modal-close:hover {
    color: #333;
}

.modal-body {
    padding: 20px;
}

.selected-date {
    text-align: center;
    margin-bottom: 20px;
    padding: 10px 15px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    min-height: auto;
}

.timeslot-options {
    display: grid;
    gap: 10px;
}

.timeslot-option {
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
}

.timeslot-option:hover {
    background: #f0f8ff;
    border-color: #007cba;
}

.timeslot-option.selected {
    background: #007cba;
    color: white;
}


/* 見学時間・時間帯選択のスタイル */
.duration-selection, .timeslot-selection {
    text-align: center;
}

.duration-selection h4, .timeslot-selection h4 {
    margin: 0 0 20px 0;
    color: #333;
    font-size: 16px;
}

.duration-options {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
}

.duration-option {
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    text-align: center;
}

.duration-option:hover {
    border-color: #007cba;
    background: #f0f8ff;
}

.duration-label {
    font-size: 16px;
    font-weight: bold;
    color: #333;
}

.timeslot-options-grid {
    display: grid;
    gap: 10px;
    margin-bottom: 20px;
}

.duration-selection {
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
    padding: 15px;
}

.timeslot-selection {
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
    padding: 15px;
    margin-top: 15px;
}

.timeslot-option {
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
    background: white;
}

.timeslot-option:hover {
    background: #f0f8ff;
    border-color: #007cba;
}

.timeslot-option.selected {
    background: #007cba;
    color: white;
}

.timeslot-time {
    font-size: 16px;
    font-weight: bold;
}

.btn-back {
    padding: 8px 16px;
    background: #6c757d;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.btn-back:hover {
    background: #5a6268;
}

/* ローディング */
.calendar-loading {
    grid-column: 1 / -1;
    text-align: center;
    padding: 50px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007cba;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* レスポンシブ */
@media (max-width: 768px) {
    .desktop-only {
        display: none !important;
    }
    
    .mobile-only {
        display: block !important;
    }
    
    .calendar-controls {
        flex-direction: column;
        gap: 15px;
        text-align: center;
        padding: 15px;
    }
    
    .calendar-list {
        display: block !important;
        margin: 20px 0;
    }
    
    .list-date-section,
    .list-weekday-section,
    .list-time-section {
        min-height: 45px;
    }
    
    .list-date-section {
        min-width: 45px;
    }
    
    .list-weekday-section {
        min-width: 35px;
    }
    
    .list-time-section {
        min-width: 140px;
    }
    
    .legend-items {
        grid-template-columns: 1fr;
    }
    
    /* スマホ版でのスクロール最適化 */
    .calendar-container {
        padding: 10px;
    }
    
    .selected-factory {
        font-size: 16px;
    }
    
    .month-selector select {
        font-size: 14px;
        padding: 6px 10px;
    }
}

@media (min-width: 769px) {
    .mobile-only {
        display: none !important;
    }
    
    .desktop-only {
        display: block !important;
    }
}
</style>

<script src="<?php echo get_template_directory_uri(); ?>/assets/js/calendar.js"></script>

<script>
// グローバル関数として定義（HTML内のonclickから呼び出せるように）
function openTimeslotSelection(dateStr, period) {
    if (window.reservationCalendar) {
        window.reservationCalendar.openTimeslotModal(dateStr, period);
    }
}
</script>

<?php
// 工場名を取得するヘルパー関数
function get_factory_name($factory_id) {
    $factories = [
        1 => '関東リサイクル',
        2 => '中部リサイクル',
        3 => '福山リサイクル',
        4 => '山形選別センター',
        5 => '松本選別センター',
        6 => '西宮選別センター',
        7 => '東海選別センター',
        8 => '金沢選別センター',
        9 => '九州選別センター'
    ];
    
    return isset($factories[$factory_id]) ? $factories[$factory_id] : '関東リサイクル';
}

get_footer();
?>