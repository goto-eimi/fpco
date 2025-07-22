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

        <header class="entry-header">
            <h1 class="entry-title">予約フォーム（見学者情報入力）</h1>
        </header>

        <form id="reservation-form" class="reservation-form" method="post" action="<?php echo home_url('/reservation-confirm/'); ?>">
            <!-- 予約基本情報（非表示フィールド） -->
            <input type="hidden" name="factory_id" value="<?php echo esc_attr($factory_id); ?>">
            <input type="hidden" name="date" value="<?php echo esc_attr($date); ?>">
            <input type="hidden" name="timeslot" value="<?php echo esc_attr($timeslot); ?>">

            <!-- 予約内容表示 -->
            <div class="reservation-summary">
                <h2>ご予約内容</h2>
                <dl>
                    <dt>見学工場</dt>
                    <dd><?php echo esc_html($factory_name); ?>工場</dd>
                    <dt>見学日</dt>
                    <dd><?php echo esc_html(format_display_date($date)); ?></dd>
                    <dt>見学時間帯</dt>
                    <dd><?php echo esc_html($timeslot_info['display']); ?></dd>
                    <dt>見学時間</dt>
                    <dd><?php echo esc_html($timeslot_info['duration']); ?>分</dd>
                </dl>
            </div>

            <!-- 申込者情報 -->
            <section class="form-section">
                <h2>申込者様情報</h2>
                
                <div class="form-group required">
                    <label for="applicant_name_kana">申込者様氏名（ふりがな）</label>
                    <input type="text" id="applicant_name_kana" name="applicant_name_kana" placeholder="やまだ たろう" required>
                    <span class="error-message"></span>
                </div>

                <div class="form-group required">
                    <label for="applicant_name">申込者様氏名</label>
                    <input type="text" id="applicant_name" name="applicant_name" placeholder="山田 太郎" required>
                    <span class="error-message"></span>
                </div>

                <div class="form-group">
                    <label>申込者様は旅行会社の方ですか？</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="is_travel_agency" value="no" checked>
                            <span>いいえ</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="is_travel_agency" value="yes">
                            <span>はい</span>
                        </label>
                    </div>
                </div>

                <div class="form-group required">
                    <label for="postal_code">郵便番号</label>
                    <div class="postal-code-group">
                        <input type="text" id="postal_code" name="postal_code" placeholder="7218607" maxlength="7" required>
                        <button type="button" class="btn-postal-search">住所検索</button>
                    </div>
                    <span class="error-message"></span>
                </div>

                <div class="form-group required">
                    <label for="prefecture">都道府県</label>
                    <select id="prefecture" name="prefecture" required>
                        <option value="">都道府県を選択</option>
                        <?php foreach (get_prefectures() as $pref): ?>
                            <option value="<?php echo esc_attr($pref); ?>"><?php echo esc_html($pref); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="error-message"></span>
                </div>

                <div class="form-group required">
                    <label for="city">市区町村</label>
                    <input type="text" id="city" name="city" placeholder="福山市" required>
                    <span class="error-message"></span>
                </div>

                <div class="form-group required">
                    <label for="address">番地</label>
                    <input type="text" id="address" name="address" placeholder="曙町1-13-15" required>
                    <span class="error-message"></span>
                </div>

                <div class="form-group">
                    <label for="building">建物名・部屋番号</label>
                    <input type="text" id="building" name="building" placeholder="エフピコビル5F">
                </div>

                <div class="form-group required">
                    <label for="phone">申込者様電話番号</label>
                    <input type="tel" id="phone" name="phone" placeholder="08412345678" required>
                    <span class="error-message"></span>
                </div>

                <div class="form-group required">
                    <label for="mobile">申込者様携帯番号</label>
                    <input type="tel" id="mobile" name="mobile" placeholder="08012345678" required>
                    <span class="error-message"></span>
                </div>

                <div class="form-group required">
                    <label for="email">申込者様メールアドレス</label>
                    <input type="email" id="email" name="email" placeholder="user@example.com" required>
                    <span class="error-message"></span>
                </div>

                <div class="form-group required">
                    <label>ご利用の交通機関</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="transportation" value="car" required>
                            <span>車</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="transportation" value="chartered_bus">
                            <span>貸切バス</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="transportation" value="route_bus">
                            <span>路線バス</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="transportation" value="taxi">
                            <span>タクシー</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="transportation" value="other">
                            <span>その他</span>
                        </label>
                    </div>
                    <span class="error-message"></span>
                </div>

                <div class="form-group conditional" id="transportation-other-group" style="display: none;">
                    <label for="transportation_other">その他の交通機関</label>
                    <input type="text" id="transportation_other" name="transportation_other" placeholder="詳細を入力">
                </div>

                <div class="form-group conditional" id="vehicle-count-group" style="display: none;">
                    <label for="vehicle_count">台数</label>
                    <div class="input-with-unit">
                        <input type="number" id="vehicle_count" name="vehicle_count" min="1" max="99">
                        <span class="unit">台</span>
                    </div>
                    <span class="error-message"></span>
                </div>

                <div class="form-group required">
                    <label for="purpose">見学目的</label>
                    <textarea id="purpose" name="purpose" rows="5" maxlength="500" required></textarea>
                    <span class="char-counter">0 / 500</span>
                    <span class="error-message"></span>
                </div>
            </section>

            <!-- 旅行会社情報（条件付き表示） -->
            <section class="form-section conditional" id="travel-agency-section" style="display: none;">
                <h2>旅行会社情報</h2>
                
                <div class="form-group required">
                    <label for="agency_name">旅行会社名</label>
                    <input type="text" id="agency_name" name="agency_name" placeholder="株式会社ABC旅行">
                    <span class="error-message"></span>
                </div>

                <div class="form-group required">
                    <label for="agency_phone">旅行会社電話番号</label>
                    <input type="tel" id="agency_phone" name="agency_phone" placeholder="0841234567">
                    <span class="error-message"></span>
                </div>

                <div class="form-group required">
                    <label for="agency_postal_code">郵便番号</label>
                    <div class="postal-code-group">
                        <input type="text" id="agency_postal_code" name="agency_postal_code" placeholder="7218607" maxlength="7">
                        <button type="button" class="btn-postal-search">住所検索</button>
                    </div>
                    <span class="error-message"></span>
                </div>

                <div class="form-group required">
                    <label for="agency_prefecture">都道府県</label>
                    <select id="agency_prefecture" name="agency_prefecture">
                        <option value="">都道府県を選択</option>
                        <?php foreach (get_prefectures() as $pref): ?>
                            <option value="<?php echo esc_attr($pref); ?>"><?php echo esc_html($pref); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="error-message"></span>
                </div>

                <div class="form-group required">
                    <label for="agency_city">市区町村</label>
                    <input type="text" id="agency_city" name="agency_city" placeholder="福山市">
                    <span class="error-message"></span>
                </div>

                <div class="form-group required">
                    <label for="agency_address">番地</label>
                    <input type="text" id="agency_address" name="agency_address" placeholder="曙町1-13-15">
                    <span class="error-message"></span>
                </div>

                <div class="form-group">
                    <label for="agency_building">建物名・部屋番号</label>
                    <input type="text" id="agency_building" name="agency_building" placeholder="旅行ビル3F">
                </div>

                <div class="form-group">
                    <label for="agency_fax">旅行会社FAX番号</label>
                    <input type="tel" id="agency_fax" name="agency_fax" placeholder="0841112222">
                </div>

                <div class="form-group">
                    <label for="agency_contact_mobile">担当者携帯番号</label>
                    <input type="tel" id="agency_contact_mobile" name="agency_contact_mobile" placeholder="08012345678">
                </div>

                <div class="form-group required">
                    <label for="agency_contact_email">担当者メールアドレス</label>
                    <input type="email" id="agency_contact_email" name="agency_contact_email" placeholder="tantou@example.com">
                    <span class="error-message"></span>
                </div>
            </section>

            <!-- 見学者様の分類 -->
            <section class="form-section">
                <h2>見学者様の分類</h2>
                
                <div class="form-group required">
                    <label>見学者様の分類を選択してください</label>
                    <div class="radio-group vertical">
                        <label class="radio-label">
                            <input type="radio" name="visitor_category" value="school" required>
                            <span>小学校・中学校・大学</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="visitor_category" value="recruit">
                            <span>個人(大学生・高校生のリクルート)</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="visitor_category" value="family">
                            <span>個人・親子見学・ご家族など</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="visitor_category" value="company">
                            <span>企業(研修など)</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="visitor_category" value="government">
                            <span>自治体主体ツアーなど</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="visitor_category" value="other">
                            <span>その他(グループ・団体)</span>
                        </label>
                    </div>
                    <span class="error-message"></span>
                </div>
            </section>

            <!-- 分類別入力項目 -->
            <section class="form-section conditional" id="category-details" style="display: none;">
                <!-- JavaScriptで動的に生成 -->
            </section>

            <!-- 見学者様人数（統合表示） -->
            <section class="form-section">
                <div class="visitor-count-summary">
                    <h3>見学者様人数</h3>
                    <div id="total-visitor-count">
                        <span class="count-label">合計</span>
                        <span class="count-value">0</span>
                        <span class="count-unit">名</span>
                    </div>
                    <div class="count-warning" style="display: none;">
                        <p>予約可能人数（50名）を超えています</p>
                    </div>
                </div>
            </section>

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
    color: #666;
}

.breadcrumb a {
    color: #007cba;
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
    align-items: center;
    position: relative;
    padding: 0 20px;
}

.step:not(:last-child)::after {
    content: '';
    position: absolute;
    right: -20px;
    width: 40px;
    height: 2px;
    background: #ddd;
    top: 50%;
    transform: translateY(-50%);
}

.step.active .step-number {
    background: #007cba;
    color: white;
}

.step.active .step-label {
    color: #007cba;
    font-weight: bold;
}

.step-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #ddd;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
    font-weight: bold;
}

.step-label {
    color: #666;
}

.reservation-form {
    max-width: 800px;
    margin: 0 auto;
}

.reservation-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.reservation-summary h2 {
    margin-top: 0;
    font-size: 20px;
    color: #333;
}

.reservation-summary dl {
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: 10px;
    margin: 0;
}

.reservation-summary dt {
    font-weight: bold;
    color: #666;
}

.reservation-summary dd {
    margin: 0;
    color: #333;
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