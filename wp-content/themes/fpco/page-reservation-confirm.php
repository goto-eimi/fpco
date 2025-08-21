<?php
/**
 * Template Name: 予約フォーム確認
 * 
 * 工場見学の予約フォーム（入力内容確認）
 */

get_header(); 

// POSTデータを取得・検証
$form_data = validate_form_data($_POST);

// フォームデータが無い場合はフォームに戻る
if (!$form_data) {
    wp_redirect(home_url('/reservation-form/'));
    exit;
}

// 工場名を取得
$factory_name = get_factory_name($form_data['factory_id']);

// 時間帯情報を解析
$timeslot_info = parse_timeslot($form_data['timeslot'], $form_data['factory_id']);
?>

<style>
/* プレースホルダーの文字色を#E5E5E5に設定 */
input::placeholder,
textarea::placeholder,
select option[value=""] {
    color: #E5E5E5 !important;
}

/* WebKit系ブラウザ対応 */
input::-webkit-input-placeholder,
textarea::-webkit-input-placeholder {
    color: #E5E5E5 !important;
}

/* Mozilla Firefox対応 */
input::-moz-placeholder,
textarea::-moz-placeholder {
    color: #E5E5E5 !important;
    opacity: 1;
}

/* Internet Explorer対応 */
input:-ms-input-placeholder,
textarea:-ms-input-placeholder {
    color: #E5E5E5 !important;
}

.reservation-info-box {
    max-width: none !important;
    width: 100% !important;
}

/* 項目ラベルの幅を調整 */
.info-row .info-label {
    width: 250px !important;
    min-width: 250px !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}

/* 入力エリアの幅を調整 */
.info-row .info-input {
    flex: 1 !important;
    max-width: none !important;
}

/* 見学者様の分類の改行調整 */
.info-label br {
    line-height: 1.5 !important;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .info-row .info-label {
        width: 200px !important;
        min-width: 200px !important;
    }
}

/* フォームスタイルの完全適用 */
.reservation-form {
    max-width: 900px;
    margin: 0 auto;
}

/* 必須ラベルのスタイル */
.required-label {
    background-color: #E65100;
    color: white;
    padding: 4px 8px;
    font-size: 12px;
    font-weight: bold;
    margin-right: 8px;
    display: inline-block;
}

.info-row {
    display: flex;
    align-items: center;
    min-height: 92px;
    padding: 0 40px;
    position: relative;
}

.info-row:not(:last-child)::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 35px;
    right: 35px;
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
    line-height: 1.6;
    word-wrap: break-word;
    word-break: break-word;
    overflow-wrap: break-word;
    max-width: calc(100% - 250px);
}

/* 同行者様の入力情報は改行を制御 */
.info-row.long-text .info-value .companion-detail {
    overflow: visible;
}

/* 同行者様の情報エリアの幅制限を解除 */
.info-row .info-value .companion-detail {
    max-width: none;
    width: auto;
}

/* 同行者様の各行の幅制限を解除 */
.companion-detail > * {
    max-width: none;
    width: auto;
}

/* 長いテキスト用の特別なスタイル */
.info-row.long-text {
    align-items: center;
    min-height: auto;
    padding: 20px 40px;
}

.info-row.long-text .info-label {
    align-self: center;
}

.info-row.long-text .info-value {
    padding-left: 20px;
    white-space: pre-wrap;
    align-self: center;
}

/* 同行者様の詳細情報スタイル */
.companion-detail {
    line-height: 1.8;
}

.companion-detail > * {
    white-space: nowrap;
    display: block;
}

.companion-field {
    display: inline-block;
    width: 40px;
    text-align: justify;
    text-align-last: justify;
    margin-right: 20px;
    vertical-align: middle;
    white-space: nowrap;
    flex-shrink: 0;
}

.companion-value {
    display: inline;
    vertical-align: middle;
    white-space: nowrap;
    flex-shrink: 0;
}

/* 確認画面追加スタイル */
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

/* ステップ1と2の間の線のみ#5C5548に変更 */
.step:first-child::after {
    background: #5C5548;
}

.step.active .step-number {
    background: #5C5548;
    color: white;
}

.step.active .step-label {
    color: #5C5548;
    font-weight: bold;
}

.step.completed .step-number {
    background: #5C5548;
    color: white;
}

.step.completed .step-label {
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

/* 予約情報ボックススタイル */
.reservation-info-box {
    border: 2px solid #4A4A4A;
    border-radius: 0;
    background: #fff;
    margin-bottom: 0;
    max-width: 900px;
    margin: 0 auto 30px auto;
}

/* 確認ボタンスタイル - フォームスタイルに合わせる */
.form-actions {
    text-align: center !important;
    margin-top: 30px;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    width: 100% !important;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}

.btn-back {
    background-color: #6c757d !important;
    color: white !important;
    border: none !important;
    border-radius: 50px !important;
    padding: 15px 30px !important;
    font-size: 16px !important;
    font-weight: bold !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 10px !important;
    transition: background-color 0.3s ease !important;
}

.btn-back:hover {
    background-color: #5a6268 !important;
}

.btn-submit {
    background-color: #5C5548 !important;
    color: white !important;
    border: none !important;
    border-radius: 50px !important;
    padding: 15px 40px !important;
    font-size: 16px !important;
    font-weight: bold !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 10px !important;
    transition: background-color 0.3s ease !important;
}

.btn-submit:hover {
    background-color: #4a453a !important;
}

.btn-text {
    color: white !important;
}

.btn-arrow {
    background-color: white !important;
    color: #5C5548 !important;
    border-radius: 50% !important;
    width: 25px !important;
    height: 25px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-weight: bold !important;
}

.btn-back .btn-arrow {
    background-color: white !important;
    color: #6c757d !important;
}

/* フォーム下部の枠 */
.form-bottom-border {
    border-bottom: 2px solid #4A4A4A;
    width: 100%;
    margin-top: 20px;
}

/* 人数の強調表示 */
.total-visitor-count {
    font-size: 18px !important;
    font-weight: bold !important;
    color: #5C5548 !important;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
        gap: 15px;
    }
    
    .btn-back,
    .btn-submit {
        width: 100%;
    }
}
</style>

<main id="main" class="wp-block-group">
    <div class="wp-block-group__inner-container">
        <!-- パンくずリスト -->
        <nav class="breadcrumb">
            <a href="<?php echo home_url(); ?>">TOP</a> &gt; 
            <a href="<?php echo home_url('/reservation-calendar/'); ?>">予約状況カレンダー</a> &gt; 
            <a href="<?php echo home_url('/reservation-form/'); ?>">予約フォーム</a> &gt; 
            <span>入力内容の確認</span>
        </nav>

        <!-- ステップインジケーター -->
        <div class="step-indicator">
            <div class="step completed">
                <span class="step-number">1</span>
                <span class="step-label">必要事項の入力</span>
            </div>
            <div class="step active">
                <span class="step-number">2</span>
                <span class="step-label">入力内容の確認</span>
            </div>
            <div class="step">
                <span class="step-number">3</span>
                <span class="step-label">送信完了</span>
            </div>
        </div>

        <!-- 入力内容確認説明 -->
        <div style="text-align: center; margin: 20px 0 30px 0; color: #666;">
            以下の内容でよろしければ「この内容で送信する」ボタンをクリックしてください。<br>
            修正する場合は「内容を修正する」ボタンをクリックしてください。
        </div>

        <!-- 予約情報表示部分 -->
        <div class="reservation-form">
            <div class="reservation-info-box">
            <div class="info-row">
                <span class="info-label">見学日</span>
                <span class="info-value"><?php echo esc_html(format_display_date($form_data['date'])); ?></span>
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
                <span class="info-value"><?php echo esc_html($form_data['applicant_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">申込者氏名(ふりがな)</span>
                <span class="info-value"><?php echo esc_html($form_data['applicant_name_kana']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">申込者様は旅行会社の方ですか？</span>
                <span class="info-value"><?php echo $form_data['is_travel_agency'] === 'yes' ? 'はい' : 'いいえ'; ?></span>
            </div>

            <!-- 旅行会社情報（該当する場合） -->
            <?php if ($form_data['is_travel_agency'] === 'yes'): ?>
            <div class="info-row">
                <span class="info-label">旅行会社名</span>
                <span class="info-value"><?php echo esc_html($form_data['agency_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">旅行会社住所</span>
                <span class="info-value">
                    〒<?php echo esc_html($form_data['agency_postal_code']); ?><br>
                    <?php echo esc_html($form_data['agency_prefecture']); ?><?php echo esc_html($form_data['agency_city']); ?><?php echo esc_html($form_data['agency_address']); ?>
                    <?php if (!empty($form_data['agency_building'])): ?>
                        <br><?php echo esc_html($form_data['agency_building']); ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">旅行会社電話番号</span>
                <span class="info-value"><?php echo esc_html($form_data['agency_phone']); ?></span>
            </div>
            <?php if (!empty($form_data['agency_fax'])): ?>
            <div class="info-row">
                <span class="info-label">旅行会社FAX番号</span>
                <span class="info-value"><?php echo esc_html($form_data['agency_fax']); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($form_data['agency_contact_mobile'])): ?>
            <div class="info-row">
                <span class="info-label">担当者携帯番号</span>
                <span class="info-value"><?php echo esc_html($form_data['agency_contact_mobile']); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">担当者メールアドレス</span>
                <span class="info-value"><?php echo esc_html($form_data['agency_contact_email']); ?></span>
            </div>
            <?php endif; ?>

            <div class="info-row">
                <span class="info-label">見学者様の分類</span>
                <span class="info-value"><?php echo esc_html(get_visitor_category_display($form_data['visitor_category'])); ?></span>
            </div>
            
            <?php echo generate_visitor_details_display_new($form_data); ?>

            <div class="info-row">
                <span class="info-label">申込者様住所</span>
                <span class="info-value">
                    〒<?php echo esc_html($form_data['postal_code']); ?><br>
                    <?php echo esc_html($form_data['prefecture']); ?><?php echo esc_html($form_data['city']); ?><?php echo esc_html($form_data['address']); ?>
                    <?php if (!empty($form_data['building'])): ?>
                        <br><?php echo esc_html($form_data['building']); ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">申込者様電話番号</span>
                <span class="info-value"><?php echo esc_html($form_data['phone']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">申込者様携帯番号</span>
                <span class="info-value"><?php echo esc_html($form_data['mobile']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">申込者様メールアドレス</span>
                <span class="info-value"><?php echo esc_html($form_data['email']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">ご利用の交通機関</span>
                <span class="info-value">
                    <?php echo esc_html(get_transportation_display($form_data['transportation'], $form_data)); ?>
                    <?php if (!empty($form_data['vehicle_count'])): ?>
                        （<?php echo esc_html($form_data['vehicle_count']); ?>台）
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row long-text">
                <span class="info-label">見学目的</span>
                <span class="info-value"><?php echo nl2br(esc_html($form_data['purpose'])); ?></span>
            </div>

            <div class="info-row">
                <span class="info-label">見学者様人数</span>
                <span class="info-value">
                    <?php echo esc_html($form_data['total_visitor_count']); ?>名
                    <?php if (!empty($form_data['total_child_count']) && $form_data['total_child_count'] > 0): ?>
                        　内小学生以下 <?php echo esc_html($form_data['total_child_count']); ?>名
                    <?php endif; ?>
                </span>
            </div>
            </div>
            
            <!-- フォーム下部の枠 -->
            <div class="form-bottom-border"></div>
        </div>

        <!-- 確認・送信フォーム -->
        <form id="confirm-form" method="post" action="<?php echo home_url('/reservation-complete/'); ?>">
            <!-- 全フォームデータを隠しフィールドとして保持 -->
            <?php foreach ($form_data as $key => $value): ?>
                <?php if (is_array($value)): ?>
                    <?php foreach ($value as $subKey => $subValue): ?>
                        <input type="hidden" name="<?php echo esc_attr($key . '[' . $subKey . ']'); ?>" value="<?php echo esc_attr($subValue); ?>">
                    <?php endforeach; ?>
                <?php else: ?>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="form-actions">
                <button type="button" class="btn-back" onclick="history.back()">
                    <span class="btn-arrow">←</span>
                    <span class="btn-text">内容を修正する</span>
                </button>
                <button type="submit" class="btn-submit">
                    <span class="btn-text">この内容で送信する</span>
                    <span class="btn-arrow">→</span>
                </button>
            </div>
        </form>
    </div>
</main>


<?php
// ヘルパー関数
function get_factory_name($factory_id) {
    $factories = [
        1 => '関東リサイクル',
        2 => '中部リサイクル',
        3 => '関西リサイクル',
        4 => '中四国リサイクル'
    ];
    
    return isset($factories[$factory_id]) ? $factories[$factory_id] : '';
}

function parse_timeslot($timeslot) {
    // timeslot形式: am-60-1, pm-90-2 など
    $parts = explode('-', $timeslot);
    $period = $parts[0] ?? '';
    $duration = $parts[1] ?? '';
    $session = $parts[2] ?? '';
    
    $period_display = $period === 'am' ? '午前' : '午後';
    
    return [
        'display' => $period_display,
        'duration' => $duration
    ];
}

function format_display_date($date) {
    $timestamp = strtotime($date);
    if ($timestamp) {
        return date('Y年m月d日', $timestamp);
    }
    return $date;
}

function validate_form_data($post_data) {
    // POSTデータが空の場合
    if (empty($post_data)) {
        return false;
    }
    
    // 必須の基本項目をチェック
    $required_fields = ['factory_id', 'date', 'timeslot', 'applicant_name'];
    foreach ($required_fields as $field) {
        if (!isset($post_data[$field]) || empty($post_data[$field])) {
            return false;
        }
    }
    
    return $post_data;
}

function get_transportation_display($transportation, $form_data) {
    $transportation_labels = [
        'car' => '車',
        'chartered_bus' => '貸切バス',
        'route_bus' => '路線バス',
        'taxi' => 'タクシー',
        'other' => 'その他'
    ];
    
    $label = $transportation_labels[$transportation] ?? '';
    
    if ($transportation === 'other' && !empty($form_data['transportation_other'])) {
        $label .= '（' . $form_data['transportation_other'] . '）';
    }
    
    return $label;
}

function get_visitor_category_display($category) {
    $category_labels = [
        'school' => '小学校・中学校・大学',
        'recruit' => '個人(大学生・高校生のリクルート)',
        'family' => '個人・親子見学・ご家族など',
        'company' => '企業(研修など)',
        'government' => '自治体主体ツアーなど',
        'other' => 'その他(グループ・団体)'
    ];
    
    return $category_labels[$category] ?? '';
}

function generate_visitor_details_display_new($form_data) {
    $category = $form_data['visitor_category'];
    $html = '';
    
    switch ($category) {
        case 'school':
            if (!empty($form_data['school_name'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">学校・団体名</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['school_name']);
                if (!empty($form_data['school_kana'])) {
                    $html .= '（' . esc_html($form_data['school_kana']) . '）';
                }
                $html .= '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['school_representative_name'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">代表者様氏名</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['school_representative_name']);
                if (!empty($form_data['school_representative_kana'])) {
                    $html .= '（' . esc_html($form_data['school_representative_kana']) . '）';
                }
                $html .= '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['grade'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">学年</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['grade']) . '年生</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['class_count'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">クラス</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['class_count']) . 'クラス</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['school_student_count'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">見学者様人数（児童・生徒）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['school_student_count']) . '名</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['school_supervisor_count'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">見学者様人数（引率）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['school_supervisor_count']) . '名</span>';
                $html .= '</div>';
            }
            break;
            
        case 'recruit':
            if (!empty($form_data['recruit_school_name'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">学校名</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['recruit_school_name']) . '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['recruit_department'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">学部</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['recruit_department']) . '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['recruit_grade'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">学年</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['recruit_grade']) . '年生</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['recruit_visitor_count'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">見学者様人数</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['recruit_visitor_count']) . '名</span>';
                $html .= '</div>';
            }
            
            // 同行者情報がある場合
            $companionCount = (int)($form_data['recruit_visitor_count'] ?? 0) - 1;
            if ($companionCount > 0) {
                $circles = ['①', '②', '③', '④', '⑤', '⑥', '⑦', '⑧'];
                for ($i = 1; $i <= $companionCount; $i++) {
                    if (!empty($form_data["companion_{$i}_name"])) {
                        $circle = $circles[$i - 1] ?? $i;
                        $companionDetails = '<div class="companion-detail">';
                        
                        // 氏名
                        $companionDetails .= '<span class="companion-field">氏名</span>';
                        $companionDetails .= '<span class="companion-value">' . esc_html($form_data["companion_{$i}_name"]) . '</span>';
                        
                        // 学部
                        if (!empty($form_data["companion_{$i}_department"])) {
                            $companionDetails .= '<br><span class="companion-field">学部</span>';
                            $companionDetails .= '<span class="companion-value">' . esc_html($form_data["companion_{$i}_department"]) . '</span>';
                        }
                        
                        $companionDetails .= '</div>';
                        
                        $html .= '<div class="info-row long-text">';
                        $html .= '<span class="info-label">同行者様' . $circle . '</span>';
                        $html .= '<span class="info-value">' . $companionDetails . '</span>';
                        $html .= '</div>';
                    }
                }
            }
            break;
            
        case 'family':
            if (!empty($form_data['family_organization_name'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">会社・団体名</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['family_organization_name']);
                if (!empty($form_data['family_organization_kana'])) {
                    $html .= '（' . esc_html($form_data['family_organization_kana']) . '）';
                }
                $html .= '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['family_adult_count'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">見学者様人数（大人）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['family_adult_count']) . '名</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['family_child_count'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">見学者様人数（子ども）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['family_child_count']) . '名</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['family_child_grade'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">学年</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['family_child_grade']) . '</span>';
                $html .= '</div>';
            }
            break;
            
        case 'company':
            if (!empty($form_data['company_name'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">会社・団体名</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['company_name']);
                if (!empty($form_data['company_kana'])) {
                    $html .= '（' . esc_html($form_data['company_kana']) . '）';
                }
                $html .= '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['company_adult_count'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">見学者様人数（大人）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['company_adult_count']) . '名</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['company_child_count'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">見学者様人数（子ども）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['company_child_count']) . '名</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['company_child_grade'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">学年</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['company_child_grade']) . '</span>';
                $html .= '</div>';
            }
            break;
            
        case 'government':
            if (!empty($form_data['government_name'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">会社・団体名</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['government_name']);
                if (!empty($form_data['government_kana'])) {
                    $html .= '（' . esc_html($form_data['government_kana']) . '）';
                }
                $html .= '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['government_adult_count'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">見学者様人数（大人）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['government_adult_count']) . '名</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['government_child_count'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">見学者様人数（子ども）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['government_child_count']) . '名</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['government_child_grade'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">学年</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['government_child_grade']) . '</span>';
                $html .= '</div>';
            }
            break;
            
        case 'other':
            if (!empty($form_data['other_group_name'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">会社・団体名</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['other_group_name']);
                if (!empty($form_data['other_group_kana'])) {
                    $html .= '（' . esc_html($form_data['other_group_kana']) . '）';
                }
                $html .= '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['other_adult_count'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">見学者様人数（大人）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['other_adult_count']) . '名</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['other_child_count'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">見学者様人数（子ども）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['other_child_count']) . '名</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['other_child_grade'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">学年</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['other_child_grade']) . '</span>';
                $html .= '</div>';
            }
            break;
    }
    
    return $html;
}


function calculate_total_visitors($form_data) {
    // total_visitor_countがある場合はそれを使用
    if (!empty($form_data['total_visitor_count'])) {
        return (int)$form_data['total_visitor_count'];
    }
    
    $category = $form_data['visitor_category'];
    $total = 0;
    
    switch ($category) {
        case 'school':
            $total = (int)($form_data['school_student_count'] ?? 0) + (int)($form_data['school_supervisor_count'] ?? 0);
            break;
            
        case 'recruit':
            $total = (int)($form_data['recruit_visitor_count'] ?? 0);
            break;
            
        case 'family':
            $total = (int)($form_data['family_adult_count'] ?? 0) + (int)($form_data['family_child_count'] ?? 0);
            break;
            
        case 'company':
            $total = (int)($form_data['company_adult_count'] ?? 0) + (int)($form_data['company_child_count'] ?? 0);
            break;
            
        case 'government':
            $total = (int)($form_data['government_adult_count'] ?? 0) + (int)($form_data['government_child_count'] ?? 0);
            break;
            
        case 'other':
            $total = (int)($form_data['other_adult_count'] ?? 0) + (int)($form_data['other_child_count'] ?? 0);
            break;
    }
    
    return $total;
}

function parse_timeslot($timeslot, $factory_id = null) {
    // プラグインファイルを読み込み
    $plugin_file = WP_PLUGIN_DIR . '/fpco-factory-reservation-system/includes/factory-user-management-functions.php';
    if (file_exists($plugin_file)) {
        require_once $plugin_file;
    }
    
    // 工場の時間設定を取得
    if (function_exists('fpco_get_factory_timeslots') && $factory_id) {
        $factory_timeslots = fpco_get_factory_timeslots($factory_id);
        
        $parts = explode('-', $timeslot);
        $period = $parts[0] ?? '';
        $duration_or_index = $parts[1] ?? '';
        $index = isset($parts[2]) ? intval($parts[2]) - 1 : intval($duration_or_index) - 1;
        
        // 60分・90分パターンの場合
        if (in_array($duration_or_index, ['60', '90'])) {
            $duration_key = $duration_or_index . 'min';
            if (isset($factory_timeslots[$duration_key][$period][$index])) {
                $time_range = $factory_timeslots[$duration_key][$period][$index];
                return [
                    'period' => strtoupper($period),
                    'duration' => $duration_or_index,
                    'time_range' => $time_range,
                    'display' => strtoupper($period) . '(' . $time_range . ')'
                ];
            }
        } else {
            // AM/PMパターンの場合
            if (isset($factory_timeslots[$period]) && isset($factory_timeslots[$period][$index])) {
                $time_range = $factory_timeslots[$period][$index];
                return [
                    'period' => strtoupper($period),
                    'duration' => '', // AM/PMパターンでは時間は固定
                    'time_range' => $time_range,
                    'display' => strtoupper($period) . '(' . $time_range . ')'
                ];
            }
        }
    }
    
    // フォールバック: デフォルトの時間テーブル
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

get_footer();
?>