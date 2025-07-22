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

        <div class="calendar-main-layout">
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

        </div>
        
        <!-- 右側の説明文セクション -->
        <div class="calendar-sidebar">
            <div class="calendar-info">
                <h3>[フロント]予約状況カレンダー</h3>
                <ul class="info-list">
                    <li>各工場毎にカレンダーのページが存在する。</li>
                    <li>URLにパラメータを付与し、その値から表示する工場のカレンダーの切り分けを行う</li>
                    <li>予約状況によってそれぞれ日付に以下が表示される</li>
                </ul>
                
                <div class="status-explanations">
                    <div class="status-item">
                        <span class="status-symbol available-blue">〇</span>
                        <span class="status-text">：予約可（見学不可の場合）⇒ クリック可</span>
                    </div>
                    <div class="status-item">
                        <span class="status-symbol adjusting-green">△</span>
                        <span class="status-text">：調整中（見学不可が設定されている）<br>且つ予約仮受付履歴、確認欄が入っている場合 ⇒ クリック不可</span>
                    </div>
                    <div class="status-item">
                        <span class="status-symbol unavailable-red">―</span>
                        <span class="status-text">：見学不可（見学不可が設定されている ⇒ クリック不可</span>
                    </div>
                </div>
                
                <p class="additional-note">「〇」をクリックするとページを表示</p>
                <p class="month-note">※1 今月～12ヶ月分の選択肢があり、変更時にカレンダーが切り替わる</p>
            </div>
            
            <!-- 凡例 -->
            <div class="calendar-legend">
                <h3>凡例</h3>
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
/* メインレイアウト（左右2列） */
.calendar-main-layout {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    display: flex;
    gap: 40px;
    align-items: flex-start;
}

/* カレンダーの基本スタイル */
.calendar-container {
    flex: 2;
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* サイドバー（右側の説明文） */
.calendar-sidebar {
    flex: 1;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.calendar-info h3 {
    color: #495057;
    border-bottom: 2px solid #007cba;
    padding-bottom: 8px;
    margin-bottom: 15px;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0 0 20px 0;
}

.info-list li {
    padding: 5px 0;
    border-left: 3px solid #007cba;
    padding-left: 10px;
    margin-bottom: 8px;
    font-size: 14px;
    line-height: 1.4;
}

.status-explanations {
    margin: 20px 0;
}

.status-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 12px;
    font-size: 13px;
    line-height: 1.3;
}

.status-symbol {
    margin-right: 8px;
    font-weight: bold;
    min-width: 20px;
}

.available-blue {
    color: #007bff;
}

.adjusting-green {
    color: #28a745;
}

.unavailable-red {
    color: #dc3545;
}

.additional-note, .month-note {
    font-size: 12px;
    color: #6c757d;
    margin: 10px 0;
    padding: 8px;
    background: #ffffff;
    border-radius: 4px;
    border-left: 3px solid #17a2b8;
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

/* 予約状況アイコンの正確な色設定 */
.status-symbol.available {
    color: #007bff; /* 青い円 */
    font-size: 18px;
    font-weight: bold;
}

.status-symbol.adjusting {
    color: #28a745; /* 緑の三角形 */
    font-size: 16px;
    font-weight: bold;
}

.status-symbol.unavailable {
    color: #dc3545; /* 赤い点線 */
    font-size: 16px;
    font-weight: bold;
}

/* 凡例のアイコン色 */
.legend-symbol.available {
    color: #007bff;
}

.legend-symbol.adjusting {
    color: #28a745;
}

.legend-symbol.unavailable {
    color: #dc3545;
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
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.calendar-list-item:last-child {
    border-bottom: none;
}

.calendar-list-item.clickable {
    cursor: pointer;
    background: linear-gradient(90deg, #f8ffed 0%, #ffffff 50%);
}

.calendar-list-item.clickable:hover {
    background: linear-gradient(90deg, #e8f5d8 0%, #f0f8ff 50%);
    transform: translateX(2px);
}

.calendar-list-item.today {
    background: linear-gradient(90deg, #fff3cd 0%, #ffffff 50%);
    border-left: 4px solid #ffc107;
}

.calendar-list-item.past {
    background: #f8f9fa;
    color: #6c757d;
}

/* SPECIFICATION.md準拠の1行レイアウト */
.list-content {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 16px;
}

.list-day-number {
    font-size: 22px;
    font-weight: bold;
    min-width: 35px;
    text-align: center;
}

.list-day-number.sunday {
    color: #dc3545;
}

.list-day-number.saturday {
    color: #0066cc;
}

.list-weekday {
    font-size: 16px;
    min-width: 20px;
    color: #495057;
}

.list-am-slot, .list-pm-slot {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 50px;
}

.slot-label {
    font-size: 14px;
    font-weight: 600;
    color: #495057;
    min-width: 24px;
}

.list-content .status-symbol {
    font-size: 18px;
    font-weight: bold;
    width: 20px;
    text-align: center;
}

/* 凡例 */
.calendar-legend {
    margin-top: 20px;
    padding: 20px;
    background: white;
    border-radius: 8px;
    border: 1px solid #dee2e6;
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
    
    .calendar-main-layout {
        flex-direction: column;
        gap: 20px;
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
    
    .legend-items {
        grid-template-columns: 1fr;
    }
    
    /* スマホ版でのスクロール最適化 */
    .calendar-container {
        padding: 10px;
    }
    
    .calendar-sidebar {
        padding: 15px;
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