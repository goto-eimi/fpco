<?php
/**
 * Template Name: 予約フォーム確認
 * 
 * 工場見学の予約フォーム（入力内容確認）
 */

get_header(); 

// POSTデータを取得・検証
$form_data = validate_form_data($_POST);

// デバッグ用（一時的）
if (WP_DEBUG) {
    error_log('POST data: ' . print_r($_POST, true));
    error_log('Form data: ' . print_r($form_data, true));
}

// 一時的にリダイレクトを無効化してデバッグ
if (!$form_data) {
    // 本来はリダイレクトするが、デバッグのため表示を続ける
    echo '<div style="background: red; color: white; padding: 20px; margin: 20px;">';
    echo '<h2>デバッグ情報</h2>';
    echo '<p>フォームデータの検証に失敗しました。</p>';
    echo '<h3>POSTデータ:</h3><pre>' . print_r($_POST, true) . '</pre>';
    echo '<h3>リクエストメソッド:</h3>' . $_SERVER['REQUEST_METHOD'];
    echo '<h3>現在のURL:</h3>' . $_SERVER['REQUEST_URI'];
    echo '</div>';
    
    // デモ用のダミーデータを設定
    $form_data = [
        'factory_id' => '1',
        'date' => '2024-12-01',
        'timeslot' => 'am-60-1',
        'applicant_name' => 'テスト 太郎',
        'applicant_name_kana' => 'てすと たろう',
        'is_travel_agency' => 'no',
        'visitor_category' => 'family',
        'family_organization_name' => 'テスト会社',
        'family_organization_kana' => 'てすとかいしゃ',
        'family_adult_count' => '2',
        'family_child_count' => '1',
        'postal_code' => '1234567',
        'prefecture' => '東京都',
        'city' => '渋谷区',
        'address' => '1-1-1',
        'phone' => '03-1234-5678',
        'mobile' => '090-1234-5678',
        'email' => 'test@example.com',
        'transportation' => 'car',
        'vehicle_count' => '1',
        'purpose' => 'テスト目的',
        'total_visitor_count' => '3'
    ];
}

// 工場名を取得
$factory_name = get_factory_name($form_data['factory_id']);

// 時間帯情報を解析
$timeslot_info = parse_timeslot($form_data['timeslot']);
?>

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

        <header class="entry-header">
            <h1 class="entry-title">入力内容の確認</h1>
            <p class="confirm-description">
                以下の内容でよろしければ「この内容で送信する」ボタンをクリックしてください。<br>
                修正する場合は「内容を修正する」ボタンをクリックしてください。
            </p>
        </header>

        <div class="confirmation-content">
            <!-- 予約内容表示 -->
            <section class="confirm-section">
                <h2>ご予約内容</h2>
                <dl class="confirm-list">
                    <dt>見学工場</dt>
                    <dd><?php echo esc_html($factory_name); ?>工場</dd>
                    <dt>見学日</dt>
                    <dd><?php echo esc_html(format_display_date($form_data['date'])); ?></dd>
                    <dt>見学時間帯</dt>
                    <dd><?php echo esc_html($timeslot_info['display']); ?></dd>
                    <dt>見学時間</dt>
                    <dd><?php echo esc_html($timeslot_info['duration']); ?>分</dd>
                </dl>
            </section>

            <!-- 申込者情報 -->
            <section class="confirm-section">
                <h2>申込者様情報</h2>
                <dl class="confirm-list">
                    <dt>申込者様氏名（ふりがな）</dt>
                    <dd><?php echo esc_html($form_data['applicant_name']); ?>（<?php echo esc_html($form_data['applicant_name_kana']); ?>）</dd>
                    
                    <dt>住所</dt>
                    <dd>
                        〒<?php echo esc_html($form_data['postal_code']); ?><br>
                        <?php echo esc_html($form_data['prefecture']); ?><?php echo esc_html($form_data['city']); ?><?php echo esc_html($form_data['address']); ?>
                        <?php if (!empty($form_data['building'])): ?>
                            <br><?php echo esc_html($form_data['building']); ?>
                        <?php endif; ?>
                    </dd>
                    
                    <dt>電話番号</dt>
                    <dd><?php echo esc_html($form_data['phone']); ?></dd>
                    
                    <dt>携帯番号</dt>
                    <dd><?php echo esc_html($form_data['mobile']); ?></dd>
                    
                    <dt>メールアドレス</dt>
                    <dd><?php echo esc_html($form_data['email']); ?></dd>
                    
                    <dt>ご利用の交通機関</dt>
                    <dd>
                        <?php echo esc_html(get_transportation_display($form_data['transportation'], $form_data)); ?>
                        <?php if (!empty($form_data['vehicle_count'])): ?>
                            （<?php echo esc_html($form_data['vehicle_count']); ?>台）
                        <?php endif; ?>
                    </dd>
                    
                    <dt>見学目的</dt>
                    <dd><?php echo nl2br(esc_html($form_data['purpose'])); ?></dd>
                </dl>
            </section>

            <!-- 旅行会社情報（該当する場合） -->
            <?php if ($form_data['is_travel_agency'] === 'yes'): ?>
            <section class="confirm-section">
                <h2>旅行会社情報</h2>
                <dl class="confirm-list">
                    <dt>旅行会社名</dt>
                    <dd><?php echo esc_html($form_data['agency_name']); ?></dd>
                    
                    <dt>電話番号</dt>
                    <dd><?php echo esc_html($form_data['agency_phone']); ?></dd>
                    
                    <dt>住所</dt>
                    <dd>
                        〒<?php echo esc_html($form_data['agency_postal_code']); ?><br>
                        <?php echo esc_html($form_data['agency_prefecture']); ?><?php echo esc_html($form_data['agency_city']); ?><?php echo esc_html($form_data['agency_address']); ?>
                        <?php if (!empty($form_data['agency_building'])): ?>
                            <br><?php echo esc_html($form_data['agency_building']); ?>
                        <?php endif; ?>
                    </dd>
                    
                    <?php if (!empty($form_data['agency_fax'])): ?>
                    <dt>FAX番号</dt>
                    <dd><?php echo esc_html($form_data['agency_fax']); ?></dd>
                    <?php endif; ?>
                    
                    <?php if (!empty($form_data['agency_contact_mobile'])): ?>
                    <dt>担当者携帯番号</dt>
                    <dd><?php echo esc_html($form_data['agency_contact_mobile']); ?></dd>
                    <?php endif; ?>
                    
                    <dt>担当者メールアドレス</dt>
                    <dd><?php echo esc_html($form_data['agency_contact_email']); ?></dd>
                </dl>
            </section>
            <?php endif; ?>

            <!-- 見学者情報 -->
            <section class="confirm-section">
                <h2>見学者様情報</h2>
                <dl class="confirm-list">
                    <dt>見学者様の分類</dt>
                    <dd><?php echo esc_html(get_visitor_category_display($form_data['visitor_category'])); ?></dd>
                    
                    <?php echo generate_visitor_details_display($form_data); ?>
                    
                    <dt>見学者様人数（合計）</dt>
                    <dd class="total-count"><?php echo calculate_total_visitors($form_data); ?>名</dd>
                </dl>
            </section>
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
                    ← 内容を修正する
                </button>
                <button type="submit" class="btn-submit">
                    この内容で送信する →
                </button>
            </div>
        </form>
    </div>
</main>

<style>
/* 確認画面のスタイル */
.confirm-description {
    text-align: center;
    color: #666;
    margin-bottom: 30px;
}

.confirmation-content {
    max-width: 800px;
    margin: 0 auto;
}

.confirm-section {
    background: white;
    padding: 30px;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 30px;
}

.confirm-section h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 20px;
    color: #333;
    border-bottom: 2px solid #007cba;
    padding-bottom: 10px;
}

.confirm-list {
    display: grid;
    gap: 15px;
    margin: 0;
}

.confirm-list dt {
    font-weight: bold;
    color: #666;
    margin-bottom: 5px;
}

.confirm-list dd {
    margin: 0 0 15px 0;
    color: #333;
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    line-height: 1.6;
}

.confirm-list .total-count {
    font-size: 18px;
    font-weight: bold;
    color: #007cba;
    text-align: center;
}

.step.completed .step-number {
    background: #28a745;
    color: white;
}

.step.completed .step-label {
    color: #28a745;
    font-weight: bold;
}

.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 40px;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.btn-back {
    padding: 15px 30px;
    background: #6c757d;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-back:hover {
    background: #5a6268;
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

.btn-submit:hover {
    background: #005a87;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .confirm-section {
        padding: 20px;
    }
    
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

<?php
// ヘルパー関数
function validate_form_data($post_data) {
    // デバッグ用ログ出力
    if (WP_DEBUG) {
        error_log('validate_form_data called with: ' . print_r($post_data, true));
    }
    
    // POSTデータが空の場合
    if (empty($post_data)) {
        if (WP_DEBUG) {
            error_log('validate_form_data: POST data is empty');
        }
        return false;
    }
    
    // 必須の基本項目をチェック
    $required_fields = ['factory_id', 'date', 'timeslot', 'applicant_name'];
    foreach ($required_fields as $field) {
        if (!isset($post_data[$field]) || empty($post_data[$field])) {
            if (WP_DEBUG) {
                error_log("validate_form_data: Required field '{$field}' is missing or empty");
            }
            return false;
        }
    }
    
    if (WP_DEBUG) {
        error_log('validate_form_data: Validation passed');
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

function generate_visitor_details_display($form_data) {
    $category = $form_data['visitor_category'];
    $html = '';
    
    switch ($category) {
        case 'school':
            $html .= '<dt>学校・団体名</dt>';
            $html .= '<dd>' . esc_html($form_data['school_name']);
            if (!empty($form_data['school_kana'])) {
                $html .= '（' . esc_html($form_data['school_kana']) . '）';
            }
            $html .= '</dd>';
            
            if (!empty($form_data['school_representative_name'])) {
                $html .= '<dt>代表者様氏名</dt>';
                $html .= '<dd>' . esc_html($form_data['school_representative_name']);
                if (!empty($form_data['school_representative_kana'])) {
                    $html .= '（' . esc_html($form_data['school_representative_kana']) . '）';
                }
                $html .= '</dd>';
            }
            
            $html .= '<dt>学年・クラス</dt>';
            $html .= '<dd>' . esc_html($form_data['grade']) . '年生 ' . esc_html($form_data['class_count']) . 'クラス</dd>';
            
            $html .= '<dt>見学者様人数</dt>';
            $html .= '<dd>児童・生徒 ' . esc_html($form_data['school_student_count']) . '名、引率 ' . esc_html($form_data['school_supervisor_count']) . '名</dd>';
            break;
            
        case 'recruit':
            $html .= '<dt>学校名</dt>';
            $html .= '<dd>' . esc_html($form_data['recruit_school_name']) . '</dd>';
            
            $html .= '<dt>学年・学部</dt>';
            $html .= '<dd>' . esc_html($form_data['recruit_grade']) . '年生 ' . esc_html($form_data['recruit_department']) . '</dd>';
            
            $html .= '<dt>見学者様人数</dt>';
            $html .= '<dd>' . esc_html($form_data['recruit_visitor_count']) . '名</dd>';
            
            // 同行者情報がある場合
            $companionCount = (int)$form_data['recruit_visitor_count'] - 1;
            if ($companionCount > 0) {
                $html .= '<dt>同行者様</dt>';
                $html .= '<dd>';
                for ($i = 1; $i <= $companionCount; $i++) {
                    if (!empty($form_data["companion_{$i}_name"])) {
                        $html .= $i . '. ' . esc_html($form_data["companion_{$i}_name"]);
                        if (!empty($form_data["companion_{$i}_department"])) {
                            $html .= '（' . esc_html($form_data["companion_{$i}_department"]) . '）';
                        }
                        $html .= '<br>';
                    }
                }
                $html .= '</dd>';
            }
            break;
            
        case 'family':
            $html .= '<dt>会社・団体名</dt>';
            $html .= '<dd>' . esc_html($form_data['family_organization_name']);
            if (!empty($form_data['family_organization_kana'])) {
                $html .= '（' . esc_html($form_data['family_organization_kana']) . '）';
            }
            $html .= '</dd>';
            
            $html .= '<dt>見学者様人数</dt>';
            $html .= '<dd>大人 ' . esc_html($form_data['family_adult_count']) . '名、子ども ' . esc_html($form_data['family_child_count']) . '名</dd>';
            
            if (!empty($form_data['family_child_grade'])) {
                $html .= '<dt>学年</dt>';
                $html .= '<dd>' . esc_html($form_data['family_child_grade']) . '</dd>';
            }
            break;
            
        case 'company':
            $html .= '<dt>会社・団体名</dt>';
            $html .= '<dd>' . esc_html($form_data['company_name']);
            if (!empty($form_data['company_kana'])) {
                $html .= '（' . esc_html($form_data['company_kana']) . '）';
            }
            $html .= '</dd>';
            
            $html .= '<dt>見学者様人数</dt>';
            $html .= '<dd>大人 ' . esc_html($form_data['company_adult_count']) . '名、子ども ' . esc_html($form_data['company_child_count']) . '名</dd>';
            
            if (!empty($form_data['company_child_grade'])) {
                $html .= '<dt>学年</dt>';
                $html .= '<dd>' . esc_html($form_data['company_child_grade']) . '</dd>';
            }
            break;
            
        case 'government':
            $html .= '<dt>会社・団体名</dt>';
            $html .= '<dd>' . esc_html($form_data['government_name']);
            if (!empty($form_data['government_kana'])) {
                $html .= '（' . esc_html($form_data['government_kana']) . '）';
            }
            $html .= '</dd>';
            
            $html .= '<dt>見学者様人数</dt>';
            $html .= '<dd>大人 ' . esc_html($form_data['government_adult_count']) . '名、子ども ' . esc_html($form_data['government_child_count']) . '名</dd>';
            
            if (!empty($form_data['government_child_grade'])) {
                $html .= '<dt>学年</dt>';
                $html .= '<dd>' . esc_html($form_data['government_child_grade']) . '</dd>';
            }
            break;
            
        case 'other':
            $html .= '<dt>会社・団体名</dt>';
            $html .= '<dd>' . esc_html($form_data['other_group_name']);
            if (!empty($form_data['other_group_kana'])) {
                $html .= '（' . esc_html($form_data['other_group_kana']) . '）';
            }
            $html .= '</dd>';
            
            $html .= '<dt>見学者様人数</dt>';
            $html .= '<dd>大人 ' . esc_html($form_data['other_adult_count']) . '名、子ども ' . esc_html($form_data['other_child_count']) . '名</dd>';
            
            if (!empty($form_data['other_child_grade'])) {
                $html .= '<dt>学年</dt>';
                $html .= '<dd>' . esc_html($form_data['other_child_grade']) . '</dd>';
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

get_footer();
?>