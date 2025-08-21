<?php
/**
 * Template for Calendar Reservation Page
 * 
 * 工場見学の予約状況を月間カレンダー形式で表示
 * ページスラッグ: calendar-reservation
 */

get_header(); ?>

<main id="main" class="wp-block-group">
    <div class="wp-block-group__inner-container">
        <header class="entry-header">
            <h1 class="entry-title">工場見学予約状況</h1>
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
                    $factory_name = get_factory_name_cal($factory_id);
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
                        <span class="legend-symbol available">◯</span>
                        <span class="legend-text">・・・空きがあります。ご希望の日付をクリックしてください。(※ 50名まで可)</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-symbol adjusting">△</span>
                        <span class="legend-text">・・・調整中です。</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-symbol unavailable">－</span>
                        <span class="legend-text">・・・受付を行っておりません。</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 時間帯選択モーダル -->
        <div id="timeslot-modal" class="modal-overlay" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>時間帯を選択してください</h3>
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
                <div class="modal-footer">
                    <button type="button" class="btn-cancel">キャンセル</button>
                    <button type="button" class="btn-proceed">予約フォームへ進む</button>
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
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
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
    background: #ddd;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.calendar-day-header {
    background: #007cba;
    color: white;
    padding: 15px 5px;
    text-align: center;
    font-weight: bold;
}

.calendar-day {
    background: white;
    min-height: 100px;
    padding: 10px 5px;
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
    position: absolute;
    top: 5px;
    left: 5px;
    font-weight: bold;
}

.day-number.sunday {
    color: #dc3545;
}

.day-number.saturday {
    color: #0066cc;
}

.time-slots {
    margin-top: 25px;
    font-size: 14px;
}

.time-slot {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    padding: 2px 0;
}

.time-label {
    font-weight: bold;
}

.status-symbol {
    font-size: 16px;
    font-weight: bold;
}

.status-symbol.available {
    color: #28a745;
}

.status-symbol.adjusting {
    color: #ffc107;
}

.status-symbol.unavailable {
    color: #6c757d;
}

/* スマホ版リスト表示 */
.calendar-list {
    display: none;
}

.calendar-list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: white;
    border: 1px solid #ddd;
    margin-bottom: 5px;
    border-radius: 4px;
}

.calendar-list-item.clickable {
    cursor: pointer;
}

.calendar-list-item.clickable:hover {
    background: #f0f8ff;
}

.list-date-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.list-day-number {
    font-size: 24px;
    font-weight: bold;
    min-width: 40px;
}

.list-day-number.sunday {
    color: #dc3545;
}

.list-day-number.saturday {
    color: #0066cc;
}

.list-weekday {
    font-size: 16px;
}

.list-time-slots {
    display: flex;
    gap: 20px;
}

.list-time-slot {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 14px;
}

/* 凡例 */
.calendar-legend {
    margin-top: 40px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
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
    font-size: 18px;
    font-weight: bold;
    width: 20px;
    text-align: center;
}

.legend-symbol.available {
    color: #28a745;
}

.legend-symbol.adjusting {
    color: #ffc107;
}

.legend-symbol.unavailable {
    color: #6c757d;
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
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
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
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
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

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 20px;
    border-top: 1px solid #ddd;
}

.btn-cancel, .btn-proceed {
    padding: 10px 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel {
    background: white;
    color: #333;
}

.btn-cancel:hover {
    background: #f8f9fa;
}

.btn-proceed {
    background: #007cba;
    color: white;
    border-color: #007cba;
}

.btn-proceed:hover {
    background: #005a87;
}

.btn-proceed:disabled {
    background: #ccc;
    border-color: #ccc;
    cursor: not-allowed;
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
    }
    
    .legend-items {
        grid-template-columns: 1fr;
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

<?php
// 工場名を取得するヘルパー関数
function get_factory_name_cal($factory_id) {
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