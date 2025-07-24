<?php
/**
 * Template Name: 予約フォーム
 * 
 * 工場見学の予約フォーム（見学者情報入力）
 */

get_header(); 

// URLパラメータから予約情報を取得
$factory_id = isset($_GET['factory']) ? sanitize_text_field($_GET['factory']) : '';
$date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
$timeslot = isset($_GET['timeslot']) ? sanitize_text_field($_GET['timeslot']) : '';

// 工場名を取得
$factory_name = get_factory_name($factory_id);

// 時間帯情報を解析
$timeslot_info = parse_timeslot($timeslot);
?>

<main id="main" class="wp-block-group">
    <div class="wp-block-group__inner-container">
        <!-- パンくずリスト -->
        <nav class="breadcrumb">
            <a href="<?php echo home_url(); ?>">TOP</a> &gt; 
            <a href="<?php echo home_url('/reservation-calendar/'); ?>">予約状況カレンダー</a> &gt; 
            <span>予約フォーム</span>
        </nav>

        <!-- ステップインジケーター -->
        <div class="step-indicator">
            <div class="step active">
                <span class="step-number">1</span>
                <span class="step-label">必要事項の入力</span>
            </div>
            <div class="step">
                <span class="step-number">2</span>
                <span class="step-label">入力内容の確認</span>
            </div>
            <div class="step">
                <span class="step-number">3</span>
                <span class="step-label">送信完了</span>
            </div>
        </div>

        <form id="reservation-form" class="reservation-form" method="post" action="<?php echo home_url('/reservation-confirm/'); ?>">
            <!-- 予約基本情報（非表示フィールド） -->
            <input type="hidden" name="factory_id" value="<?php echo esc_attr($factory_id); ?>">
            <input type="hidden" name="date" value="<?php echo esc_attr($date); ?>">
            <input type="hidden" name="timeslot" value="<?php echo esc_attr($timeslot); ?>">

            <!-- 予約情報表示部分 -->
            <div class="reservation-info-box">
                <div class="info-row">
                    <span class="info-label">見学日</span>
                    <span class="info-value"><?php echo esc_html(format_display_date($date)); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">見学時間</span>
                    <span class="info-value"><?php echo esc_html($timeslot_info['duration']); ?>分</span>
                </div>
                <div class="info-row">
                    <span class="info-label">見学時間帯</span>
                    <span class="info-value"><?php echo esc_html($timeslot_info['display']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">申込者様氏名</span>
                    <span class="info-input">
                        <input type="text" id="applicant_name" name="applicant_name" placeholder="" required>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">申込者氏名(ふりがな)</span>
                    <span class="info-input">
                        <input type="text" id="applicant_name_kana" name="applicant_name_kana" placeholder="" required>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">申込者様は旅行会社の方ですか？</span>
                    <span class="info-input">
                        <label class="radio-inline">
                            <input type="radio" name="is_travel_agency" value="yes">
                            <span>はい</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="is_travel_agency" value="no" checked>
                            <span>いいえ</span>
                        </label>
                    </span>
                </div>
                <!-- 旅行会社情報（条件付き表示） -->
                <div class="conditional" id="travel-agency-section" style="display: none;">
                <div class="info-row">
                    <span class="info-label">旅行会社名</span>
                    <span class="info-input">
                        <input type="text" id="agency_name" name="agency_name" placeholder="" required>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">旅行会社電話番号</span>
                    <span class="info-input">
                        <input type="tel" id="agency_phone" name="agency_phone" placeholder="" required>
                    </span>
                </div>
                <div class="info-row address-row">
                    <span class="info-label">旅行会社住所</span>
                    <span class="info-input address-input">
                        <div class="postal-code-group">
                            <span class="postal-prefix">〒</span>
                            <input type="text" id="agency_postal_code" name="agency_postal_code" placeholder="0000000" maxlength="7" class="postal-code-input" pattern="[0-9]{7}" required>
                            <button type="button" class="btn-postal-search" data-target="agency">住所検索</button>
                        </div>
                        <div class="address-fields-row">
                            <select id="agency_prefecture" name="agency_prefecture" class="prefecture-select" required>
                                <option value="">都道府県</option>
                                <?php foreach (get_prefectures() as $pref): ?>
                                    <option value="<?php echo esc_attr($pref); ?>"><?php echo esc_html($pref); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" id="agency_city" name="agency_city" placeholder="市区町村" class="city-input" required>
                            <input type="text" id="agency_address" name="agency_address" placeholder="番地・建物名" class="address-input-field" required>
                            <input type="hidden" id="agency_building" name="agency_building" value="">
                        </div>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">旅行会社FAX番号</span>
                    <span class="info-input">
                        <input type="tel" id="agency_fax" name="agency_fax" placeholder="">
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">担当者携帯番号</span>
                    <span class="info-input">
                        <input type="tel" id="agency_contact_mobile" name="agency_contact_mobile" placeholder="">
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">担当者メールアドレス</span>
                    <span class="info-input">
                        <input type="email" id="agency_contact_email" name="agency_contact_email" placeholder="" required>
                    </span>
                </div>
                </div>
                <!-- 申込者情報続き -->
                <div class="info-row address-row">
                    <span class="info-label">申込者様住所</span>
                    <span class="info-input address-input">
                        <div class="postal-code-group">
                            <span class="postal-prefix">〒</span>
                            <input type="text" id="postal_code" name="postal_code" placeholder="0000000" maxlength="7" class="postal-code-input" pattern="[0-9]{7}" required>
                            <button type="button" class="btn-postal-search" data-target="applicant">住所検索</button>
                        </div>
                        <div class="address-fields-row">
                            <select id="prefecture" name="prefecture" class="prefecture-select" required>
                                <option value="">都道府県</option>
                                <?php foreach (get_prefectures() as $pref): ?>
                                    <option value="<?php echo esc_attr($pref); ?>"><?php echo esc_html($pref); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" id="city" name="city" placeholder="市区町村" class="city-input" required>
                            <input type="text" id="address" name="address" placeholder="番地・建物名" class="address-input-field" required>
                            <input type="hidden" id="building" name="building" value="">
                        </div>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">申込者様電話番号</span>
                    <span class="info-input">
                        <input type="tel" id="phone" name="phone" placeholder="" required>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">申込者様携帯番号</span>
                    <span class="info-input">
                        <input type="tel" id="mobile" name="mobile" placeholder="" required>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">申込者様メールアドレス</span>
                    <span class="info-input">
                        <input type="email" id="email" name="email" placeholder="" required>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">ご利用の交通機関</span>
                    <span class="info-input">
                        <label class="radio-inline">
                            <input type="radio" name="transportation" value="car" required>
                            <span>車</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="transportation" value="chartered_bus">
                            <span>貸切バス</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="transportation" value="route_bus">
                            <span>路線バス</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="transportation" value="taxi">
                            <span>タクシー</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="transportation" value="other">
                            <span>その他</span>
                        </label>
                    </span>
                </div>
                <div class="info-row conditional" id="transportation-other-row" style="display: none;">
                    <span class="info-label">その他の交通機関</span>
                    <span class="info-input">
                        <input type="text" id="transportation_other" name="transportation_other" placeholder="">
                    </span>
                </div>
                <div class="info-row conditional" id="vehicle-count-row" style="display: none;">
                    <span class="info-label">台数</span>
                    <span class="info-input">
                        <input type="number" id="vehicle_count" name="vehicle_count" min="1" max="99">
                        <span class="unit">台</span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">見学目的</span>
                    <span class="info-input">
                        <textarea id="purpose" name="purpose" rows="3" maxlength="500" required></textarea>
                    </span>
                </div>
                <!-- 見学者様の分類 -->
                <div class="info-row">
                    <span class="info-label">見学者様の分類</span>
                    <span class="info-input">
                        <label class="radio-inline">
                            <input type="radio" name="visitor_category" value="school" required>
                            <span>小学校・中学校・大学</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="visitor_category" value="recruit">
                            <span>個人(大学生・高校生のリクルート)</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="visitor_category" value="family">
                            <span>個人・親子見学・ご家族など</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="visitor_category" value="company">
                            <span>企業(研修など)</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="visitor_category" value="government">
                            <span>自治体主体ツアーなど</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="visitor_category" value="other">
                            <span>その他(グループ・団体)</span>
                        </label>
                    </span>
                </div>
                <!-- 分類別詳細情報（条件付き表示） -->
                <div class="conditional" id="school-details" style="display: none;">
                <div class="info-row">
                    <span class="info-label">学校名</span>
                    <span class="info-input">
                        <input type="text" id="school_name" name="school_name" placeholder="">
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">学年</span>
                    <span class="info-input">
                        <input type="text" id="grade" name="grade" placeholder="">
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">見学者人数</span>
                    <span class="info-input">
                        <input type="number" id="school_visitor_count" name="school_visitor_count" min="1" max="50" placeholder="">
                        <span class="unit">名</span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">小学生以下の人数</span>
                    <span class="info-input">
                        <input type="number" id="school_child_count" name="school_child_count" min="0" placeholder="">
                        <span class="unit">名</span>
                    </span>
                </div>
                </div>

                <div class="conditional" id="recruit-details" style="display: none;">
                <div class="info-row">
                    <span class="info-label">見学者人数</span>
                    <span class="info-input">
                        <input type="number" id="recruit_visitor_count" name="recruit_visitor_count" min="1" max="50" placeholder="">
                        <span class="unit">名</span>
                    </span>
                </div>
                </div>

                <div class="conditional" id="family-details" style="display: none;">
                <div class="info-row">
                    <span class="info-label">見学者人数</span>
                    <span class="info-input">
                        <input type="number" id="family_visitor_count" name="family_visitor_count" min="1" max="50" placeholder="">
                        <span class="unit">名</span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">小学生以下の人数</span>
                    <span class="info-input">
                        <input type="number" id="family_child_count" name="family_child_count" min="0" placeholder="">
                        <span class="unit">名</span>
                    </span>
                </div>
                </div>

                <div class="conditional" id="company-details" style="display: none;">
                <div class="info-row">
                    <span class="info-label">会社名</span>
                    <span class="info-input">
                        <input type="text" id="company_name" name="company_name" placeholder="">
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">見学者人数</span>
                    <span class="info-input">
                        <input type="number" id="company_visitor_count" name="company_visitor_count" min="1" max="50" placeholder="">
                        <span class="unit">名</span>
                    </span>
                </div>
                </div>

                <div class="conditional" id="government-details" style="display: none;">
                <div class="info-row">
                    <span class="info-label">団体名</span>
                    <span class="info-input">
                        <input type="text" id="government_name" name="government_name" placeholder="">
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">見学者人数</span>
                    <span class="info-input">
                        <input type="number" id="government_visitor_count" name="government_visitor_count" min="1" max="50" placeholder="">
                        <span class="unit">名</span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">小学生以下の人数</span>
                    <span class="info-input">
                        <input type="number" id="government_child_count" name="government_child_count" min="0" placeholder="">
                        <span class="unit">名</span>
                    </span>
                </div>
                </div>

                <div class="conditional" id="other-details" style="display: none;">
                <div class="info-row">
                    <span class="info-label">団体名</span>
                    <span class="info-input">
                        <input type="text" id="other_group_name" name="other_group_name" placeholder="">
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">見学者人数</span>
                    <span class="info-input">
                        <input type="number" id="other_visitor_count" name="other_visitor_count" min="1" max="50" placeholder="">
                        <span class="unit">名</span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">小学生以下の人数</span>
                    <span class="info-input">
                        <input type="number" id="other_child_count" name="other_child_count" min="0" placeholder="">
                        <span class="unit">名</span>
                    </span>
                </div>
                </div>
            </div>

            <!-- 送信ボタン -->
            <div class="form-actions">
                <button type="submit" class="btn-submit" disabled>
                    入力内容の確認 →
                </button>
            </div>
        </form>
    </div>
</main>

<style>
/* 予約フォームのスタイル */
.breadcrumb {
    margin-bottom: 20px;
    color: #797369;
    font-size: 12px;
    font-weight: bold;
    margin-left: 70px;
}

.breadcrumb a {
    color: #797369;
    text-decoration: none;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

.step-indicator {
    display: flex;
    justify-content: center;
    margin: 30px 0;
    padding: 0;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    padding: 0 40px;
}

.step:not(:last-child)::after {
    content: '';
    position: absolute;
    left: calc(50% + 15px);
    width: calc(80px - -70px);
    height: 2px;
    background: #ddd;
    top: 15px;
    transform: translateY(-50%);
}

.step.active .step-number {
    background: #5C5548;
    color: white;
}

.step.active .step-label {
    color: #5C5548;
    font-weight: bold;
}

.step-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #DFDCDC;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 5px;
    font-weight: bold;
}

.step-label {
    color: #DFDCDC;
    text-align: center;
    font-size: 14px;
}

.reservation-form {
    max-width: 800px;
    margin: 0 auto;
}

/* 予約情報ボックススタイル */
.reservation-info-box {
    border: 2px solid #4A4A4A;
    border-radius: 0;
    background: #fff;
    margin-bottom: 0;
}

.reservation-info-box + .reservation-info-box {
    border-top: none;
}

/* 最初のボックス以外の上部ボーダーを削除 */
.reservation-info-box:not(:first-of-type) {
    border-top: none;
}

/* 最後のボックス以外の下部ボーダーを削除 */
.reservation-info-box:not(:last-of-type) {
    border-bottom: none;
}

.info-row {
    display: flex;
    align-items: center;
    min-height: 92px;
    padding: 0 20px;
    position: relative;
}

.info-row:not(:last-child)::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 20px;
    right: 20px;
    height: 1px;
    background: #E0E0E0;
}

.info-label {
    flex: 0 0 180px;
    padding: 15px 0;
    background: transparent;
    font-weight: bold;
    font-size: 15px;
    color: #5C5548;
    display: flex;
    align-items: center;
}

.info-value {
    flex: 1;
    padding: 15px 0 15px 20px;
    color: #5C5548;
    font-weight: normal;
    font-size: 15px;
}

.info-input {
    flex: 1;
    padding: 10px 0 10px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-input input[type="text"],
.info-input input[type="email"],
.info-input input[type="tel"],
.info-input input[type="number"],
.info-input select {
    flex: 1;
    padding: 9px 12px;
    border: 3px solid #5E4037;
    border-radius: 0;
    font-size: 14px;
    height: auto;
    line-height: normal;
}

.info-input textarea {
    flex: 1;
    padding: 9px 12px;
    border: 3px solid #5E4037;
    border-radius: 0;
    font-size: 14px;
    resize: vertical;
    min-height: 80px;
}

.info-input input:focus,
.info-input select:focus,
.info-input textarea:focus {
    outline: none;
    border-color: #5E4037;
}

/* ラジオボタンのインラインスタイル */
.radio-inline {
    display: inline-flex;
    align-items: center;
    margin-right: 15px;
    cursor: pointer;
    font-weight: normal;
}

.radio-inline input[type="radio"] {
    margin-right: 5px;
    flex: none;
    accent-color: #000;
    width: 16px;
    height: 16px;
}

.radio-inline span {
    white-space: nowrap;
}

/* 住所入力行のスタイル */
.address-row .address-input {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.postal-code-group {
    display: flex;
    align-items: center;
    gap: 5px;
}

.address-fields-row {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.postal-prefix {
    font-size: 16px;
    color: #5C5548;
    font-weight: normal;
}

.postal-code-input {
    width: 100px !important;
    flex: none !important;
}

.prefecture-select {
    width: 130px !important;
    flex: none !important;
}

.city-input {
    width: 150px !important;
    flex: none !important;
}

.address-input-field {
    flex: 1 !important;
    min-width: 200px;
}

/* 住所検索ボタン */
.btn-postal-search {
    padding: 8px 16px;
    background: #007cba;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    white-space: nowrap;
    flex: none;
}

.btn-postal-search:hover {
    background: #0056a3;
}

/* 単位表示 */
.unit {
    margin-left: 5px;
    color: #666;
    font-size: 14px;
}

/* 条件付き表示 */
.conditional {
    display: none;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .info-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .info-label {
        flex: none;
        border-right: none;
        border-bottom: 1px solid #000;
        padding: 10px 15px;
    }
    
    .info-value,
    .info-input {
        padding: 10px 15px;
    }
    
    .radio-inline {
        margin-right: 10px;
        margin-bottom: 5px;
    }
    
    /* 住所入力行のレスポンシブ対応 */
    .address-row .address-input {
        flex-direction: column;
    }
    
    .postal-code-group {
        margin-bottom: 10px;
    }
    
    .prefecture-select,
    .city-input,
    .address-input-field {
        width: 100% !important;
        margin-bottom: 10px;
    }
}

.form-section {
    background: white;
    padding: 30px;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 30px;
}

.form-section h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 22px;
    color: #333;
    border-bottom: 2px solid #007cba;
    padding-bottom: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group.required label::after {
    content: ' *';
    color: #dc3545;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="tel"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #007cba;
}

.form-group.error input,
.form-group.error select,
.form-group.error textarea {
    border-color: #dc3545;
}

.error-message {
    display: none;
    color: #dc3545;
    font-size: 14px;
    margin-top: 5px;
}

.form-group.error .error-message {
    display: block;
}

.radio-group {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.radio-group.vertical {
    flex-direction: column;
    gap: 10px;
}

.radio-label {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.radio-label input[type="radio"] {
    margin-right: 5px;
}

.postal-code-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.postal-code-group input {
    flex: 1;
}

.btn-postal-search {
    padding: 10px 20px;
    background: #6c757d;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    white-space: nowrap;
}

.btn-postal-search:hover {
    background: #5a6268;
}

.input-with-unit {
    display: flex;
    align-items: center;
    gap: 10px;
}

.input-with-unit input {
    width: 100px;
}

.unit {
    color: #666;
}

.char-counter {
    display: block;
    text-align: right;
    color: #666;
    font-size: 14px;
    margin-top: 5px;
}

.conditional {
    display: none;
}

.visitor-count-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.visitor-count-summary h3 {
    margin-top: 0;
    margin-bottom: 15px;
}

#total-visitor-count {
    font-size: 24px;
    color: #333;
}

.count-value {
    font-weight: bold;
    color: #007cba;
    margin: 0 5px;
}

.count-warning {
    margin-top: 15px;
    padding: 10px;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
}

.count-warning p {
    margin: 0;
    color: #721c24;
}

.form-actions {
    text-align: right;
    margin-top: 30px;
}

.btn-submit {
    padding: 15px 30px;
    background: #007cba;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-submit:hover:not(:disabled) {
    background: #005a87;
}

.btn-submit:disabled {
    background: #ccc;
    cursor: not-allowed;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .step-indicator {
        flex-direction: column;
        align-items: center;
    }
    
    .step {
        margin-bottom: 20px;
    }
    
    .step:not(:last-child)::after {
        display: none;
    }
    
    .form-section {
        padding: 20px;
    }
    
    .reservation-summary dl {
        grid-template-columns: 1fr;
    }
    
    .radio-group {
        flex-direction: column;
    }
}
</style>

<script src="<?php echo get_template_directory_uri(); ?>/assets/js/reservation-form.js"></script>

<?php
// ヘルパー関数
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
    
    return isset($factories[$factory_id]) ? $factories[$factory_id] : '';
}

function parse_timeslot($timeslot) {
    // timeslot形式: am-60-1, pm-90-2 など
    $parts = explode('-', $timeslot);
    $period = $parts[0] ?? '';
    $duration = $parts[1] ?? '';
    
    $time_ranges = [
        'am-60-1' => '9:00〜10:00',
        'am-60-2' => '10:30〜11:30',
        'am-90-1' => '9:00〜10:30',
        'am-90-2' => '10:00〜11:30',
        'pm-60-1' => '14:00〜15:00',
        'pm-60-2' => '15:30〜16:30',
        'pm-90-1' => '14:00〜15:30',
        'pm-90-2' => '15:00〜16:30'
    ];
    
    return [
        'period' => strtoupper($period),
        'duration' => $duration,
        'time_range' => $time_ranges[$timeslot] ?? '',
        'display' => strtoupper($period) . '(' . ($time_ranges[$timeslot] ?? '') . ')'
    ];
}

function format_display_date($date) {
    $timestamp = strtotime($date);
    if ($timestamp) {
        return date('Y年m月d日', $timestamp);
    }
    return '';
}

function get_prefectures() {
    return [
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
        '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
        '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
        '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
    ];
}

get_footer();
?>