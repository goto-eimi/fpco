<?php
/**
 * 予約管理フォーム表示テンプレート
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$errors = [];
$field_errors = [];
$success_message = '';
$form_data = [];
$is_edit_mode = false;
$reservation_id = null;

// メッセージの取得
$success_message = get_transient('fpco_reservation_success_message');
$error_message = get_transient('fpco_reservation_error_message');
$field_errors = get_transient('fpco_reservation_field_errors') ?: [];

// メッセージのクリア
delete_transient('fpco_reservation_success_message');
delete_transient('fpco_reservation_error_message');
delete_transient('fpco_reservation_field_errors');

// エラーメッセージを配列に変換
if ($error_message) {
    $errors = explode('<br>', $error_message);
}

// 編集モードチェック
if (isset($_GET['reservation_id']) && !empty($_GET['reservation_id'])) {
    $reservation_id = intval($_GET['reservation_id']);
    $is_edit_mode = true;
    
    // 予約データを取得して変換
    $reservation = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}reservations WHERE id = %d",
            $reservation_id
        ),
        ARRAY_A
    );
    
    if ($reservation) {
        $form_data = $this->convert_reservation_to_form_data($reservation);
    } else {
        $errors[] = '指定された予約が見つかりません。';
    }
}

// 工場リストを取得
$factories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}factorys ORDER BY name ASC");

// 都道府県リスト
$prefectures = [
    '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
    '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
    '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県',
    '三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
    '鳥取県', '島根県', '岡山県', '広島県', '山口県',
    '徳島県', '香川県', '愛媛県', '高知県',
    '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
];

function has_field_error($field, $field_errors) {
    return isset($field_errors[$field]);
}

function get_field_error($field, $field_errors) {
    return isset($field_errors[$field]) ? $field_errors[$field] : '';
}

function get_field_value($field, $form_data) {
    return isset($form_data[$field]) ? esc_attr($form_data[$field]) : '';
}
?>

<div class="wrap">
    <h1><?php echo $is_edit_mode ? '予約編集' : '予約追加'; ?></h1>
    
    <?php if ($success_message): ?>
        <div class="updated inline" style="margin: 20px 0; padding: 12px; background: #fff; border-left: 4px solid #46b450; box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);">
            <p style="margin: 0.5em 0; font-size: 14px;"><?php echo esc_html($success_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="error inline" style="margin: 20px 0; padding: 12px; background: #fff; border-left: 4px solid #dc3232; box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);">
            <ul style="margin: 0.5em 0; font-size: 14px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="reservation-form-container">
        <div class="reservation-content">
            <form method="post" action="">
                <?php wp_nonce_field('reservation_form', 'reservation_nonce'); ?>
                <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="reservation_id" value="<?php echo $reservation_id; ?>">
                <?php endif; ?>
                
                <!-- 見学工場情報 -->
                <div class="form-section-header with-border">
                    <h3 class="form-section-title">見学工場情報</h3>
                </div>
                <div class="form-section-content">
                    <div class="form-field">
                        <label class="form-label">見学工場 <span class="required">*</span></label>
                        <select name="factory_id" class="form-select <?php echo has_field_error('factory_id', $field_errors) ? 'error-field' : ''; ?>" required>
                            <option value="">選択してください</option>
                            <?php foreach ($factories as $factory): ?>
                                <option value="<?php echo $factory->id; ?>" <?php selected(get_field_value('factory_id', $form_data), $factory->id); ?>>
                                    <?php echo esc_html($factory->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (has_field_error('factory_id', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('factory_id', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">見学日 <span class="required">*</span></label>
                        <input type="date" name="visit_date" class="form-input <?php echo has_field_error('visit_date', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('visit_date', $form_data); ?>" required min="<?php echo date('Y-m-d'); ?>">
                        <?php if (has_field_error('visit_date', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('visit_date', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">見学時間 <span class="required">*</span></label>
                        <div class="time-range">
                            <select name="visit_time_start_hour" class="time-input <?php echo has_field_error('visit_time_start_hour', $field_errors) ? 'error-field' : ''; ?>" required>
                                <option value="">時</option>
                                <?php for ($h = 0; $h <= 23; $h++): ?>
                                    <option value="<?php echo $h; ?>" <?php selected(get_field_value('visit_time_start_hour', $form_data), $h); ?>>
                                        <?php echo sprintf('%02d', $h); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <span>:</span>
                            <select name="visit_time_start_minute" class="time-input <?php echo has_field_error('visit_time_start_minute', $field_errors) ? 'error-field' : ''; ?>" required>
                                <option value="">分</option>
                                <?php for ($m = 0; $m <= 59; $m += 5): ?>
                                    <option value="<?php echo $m; ?>" <?php selected(get_field_value('visit_time_start_minute', $form_data), $m); ?>>
                                        <?php echo sprintf('%02d', $m); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <span>〜</span>
                            <select name="visit_time_end_hour" class="time-input <?php echo has_field_error('visit_time_end_hour', $field_errors) ? 'error-field' : ''; ?>" required>
                                <option value="">時</option>
                                <?php for ($h = 0; $h <= 23; $h++): ?>
                                    <option value="<?php echo $h; ?>" <?php selected(get_field_value('visit_time_end_hour', $form_data), $h); ?>>
                                        <?php echo sprintf('%02d', $h); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <span>:</span>
                            <select name="visit_time_end_minute" class="time-input <?php echo has_field_error('visit_time_end_minute', $field_errors) ? 'error-field' : ''; ?>" required>
                                <option value="">分</option>
                                <?php for ($m = 0; $m <= 59; $m += 5): ?>
                                    <option value="<?php echo $m; ?>" <?php selected(get_field_value('visit_time_end_minute', $form_data), $m); ?>>
                                        <?php echo sprintf('%02d', $m); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <?php if (has_field_error('visit_time_start_hour', $field_errors) || has_field_error('visit_time_end_hour', $field_errors)): ?>
                            <span class="field-error">見学時間は必須項目です。</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 申込者情報 -->
                <div class="form-section-header with-border">
                    <h3 class="form-section-title">申込者情報</h3>
                </div>
                <div class="form-section-content">
                    <div class="form-field">
                        <label class="form-label">申込者氏名 <span class="required">*</span></label>
                        <input type="text" name="applicant_name" class="form-input <?php echo has_field_error('applicant_name', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('applicant_name', $form_data); ?>" required>
                        <?php if (has_field_error('applicant_name', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('applicant_name', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">申込者氏名(ふりがな) <span class="required">*</span></label>
                        <input type="text" name="applicant_kana" class="form-input <?php echo has_field_error('applicant_kana', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('applicant_kana', $form_data); ?>" required>
                        <?php if (has_field_error('applicant_kana', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('applicant_kana', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">旅行会社かどうか <span class="required">*</span></label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="is_travel_agency" value="no" <?php checked(get_field_value('is_travel_agency', $form_data), 'no'); ?> <?php checked(get_field_value('is_travel_agency', $form_data), ''); ?> required>
                                いいえ
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="is_travel_agency" value="yes" <?php checked(get_field_value('is_travel_agency', $form_data), 'yes'); ?> required>
                                はい
                            </label>
                        </div>
                    </div>
                    <?php if (has_field_error('is_travel_agency', $field_errors)): ?>
                        <div class="form-field">
                            <label class="form-label"></label>
                            <span class="field-error"><?php echo esc_html(get_field_error('is_travel_agency', $field_errors)); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- 旅行会社情報（条件付き表示） -->
                <div id="travel-agency-section" style="display: none;">
                    <div class="form-section-header with-border">
                        <h3 class="form-section-title">旅行会社情報</h3>
                    </div>
                    <div class="form-section-content">
                        <div class="form-field">
                            <label class="form-label">旅行会社氏名 <span class="required">*</span></label>
                            <input type="text" name="travel_agency_name" class="form-input <?php echo has_field_error('travel_agency_name', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('travel_agency_name', $form_data); ?>">
                            <?php if (has_field_error('travel_agency_name', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('travel_agency_name', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">旅行会社郵便番号</label>
                            <input type="text" name="travel_agency_zip" class="form-input <?php echo has_field_error('travel_agency_zip', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('travel_agency_zip', $form_data); ?>" placeholder="例: 1234567">
                            <?php if (has_field_error('travel_agency_zip', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('travel_agency_zip', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">旅行会社都道府県 <span class="required">*</span></label>
                            <select name="travel_agency_prefecture" class="form-select <?php echo has_field_error('travel_agency_prefecture', $field_errors) ? 'error-field' : ''; ?>">
                                <option value="">選択してください</option>
                                <?php foreach ($prefectures as $prefecture): ?>
                                    <option value="<?php echo esc_attr($prefecture); ?>" <?php selected(get_field_value('travel_agency_prefecture', $form_data), $prefecture); ?>>
                                        <?php echo esc_html($prefecture); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (has_field_error('travel_agency_prefecture', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('travel_agency_prefecture', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">旅行会社市区町村 <span class="required">*</span></label>
                            <input type="text" name="travel_agency_city" class="form-input <?php echo has_field_error('travel_agency_city', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('travel_agency_city', $form_data); ?>">
                            <?php if (has_field_error('travel_agency_city', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('travel_agency_city', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">旅行会社住所</label>
                            <input type="text" name="travel_agency_address" class="form-input <?php echo has_field_error('travel_agency_address', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('travel_agency_address', $form_data); ?>">
                            <?php if (has_field_error('travel_agency_address', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('travel_agency_address', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">旅行会社電話番号 <span class="required">*</span></label>
                            <input type="tel" name="travel_agency_phone" class="form-input <?php echo has_field_error('travel_agency_phone', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('travel_agency_phone', $form_data); ?>">
                            <?php if (has_field_error('travel_agency_phone', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('travel_agency_phone', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">旅行会社FAX</label>
                            <input type="tel" name="travel_agency_fax" class="form-input <?php echo has_field_error('travel_agency_fax', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('travel_agency_fax', $form_data); ?>">
                            <?php if (has_field_error('travel_agency_fax', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('travel_agency_fax', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">担当者携帯番号</label>
                            <input type="tel" name="contact_mobile" class="form-input <?php echo has_field_error('contact_mobile', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('contact_mobile', $form_data); ?>">
                            <?php if (has_field_error('contact_mobile', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('contact_mobile', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">担当者メールアドレス <span class="required">*</span></label>
                            <input type="email" name="contact_email" class="form-input <?php echo has_field_error('contact_email', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('contact_email', $form_data); ?>">
                            <?php if (has_field_error('contact_email', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('contact_email', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 見学者様の分類 -->
                <div class="form-section-header with-border">
                    <h3 class="form-section-title">見学者様の分類</h3>
                </div>
                <div class="form-section-content">
                    <div class="form-field">
                        <label class="form-label">見学者様の分類 <span class="required">*</span></label>
                        <div class="radio-group reservation-type-group">
                            <label class="radio-option">
                                <input type="radio" name="reservation_type" value="school" <?php checked(get_field_value('reservation_type', $form_data), 'school'); ?> required>
                                学校見学
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="reservation_type" value="student_recruit" <?php checked(get_field_value('reservation_type', $form_data), 'student_recruit'); ?> required>
                                学生（就活等）
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="reservation_type" value="family" <?php checked(get_field_value('reservation_type', $form_data), 'family'); ?> required>
                                個人・ご家族
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="reservation_type" value="company" <?php checked(get_field_value('reservation_type', $form_data), 'company'); ?> required>
                                企業
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="reservation_type" value="municipality" <?php checked(get_field_value('reservation_type', $form_data), 'municipality'); ?> required>
                                自治体・地域団体
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="reservation_type" value="other" <?php checked(get_field_value('reservation_type', $form_data), 'other'); ?> required>
                                その他
                            </label>
                        </div>
                    </div>
                    <?php if (has_field_error('reservation_type', $field_errors)): ?>
                        <div class="form-field">
                            <label class="form-label"></label>
                            <span class="field-error"><?php echo esc_html(get_field_error('reservation_type', $field_errors)); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- 学校見学の詳細 -->
                <div id="school-details" class="reservation-type-details" style="display: none;">
                    <div class="form-section-header with-border">
                        <h3 class="form-section-title">学校見学詳細</h3>
                    </div>
                    <div class="form-section-content">
                        <div class="form-field">
                            <label class="form-label">学校・団体名 <span class="required">*</span></label>
                            <input type="text" name="school_name" class="form-input <?php echo has_field_error('school_name', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('school_name', $form_data); ?>">
                            <?php if (has_field_error('school_name', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('school_name', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">学校・団体名(ふりがな) <span class="required">*</span></label>
                            <input type="text" name="school_name_kana" class="form-input <?php echo has_field_error('school_name_kana', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('school_name_kana', $form_data); ?>">
                            <?php if (has_field_error('school_name_kana', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('school_name_kana', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">代表者氏名</label>
                            <input type="text" name="representative_name" class="form-input <?php echo has_field_error('representative_name', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('representative_name', $form_data); ?>">
                            <?php if (has_field_error('representative_name', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('representative_name', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">代表者氏名(ふりがな)</label>
                            <input type="text" name="representative_name_kana" class="form-input <?php echo has_field_error('representative_name_kana', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('representative_name_kana', $form_data); ?>">
                            <?php if (has_field_error('representative_name_kana', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('representative_name_kana', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">学年 <span class="required">*</span></label>
                            <input type="number" name="grade" class="form-input <?php echo has_field_error('grade', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('grade', $form_data); ?>" min="1" max="12">
                            <?php if (has_field_error('grade', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('grade', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">クラス数 <span class="required">*</span></label>
                            <input type="number" name="class_count" class="form-input <?php echo has_field_error('class_count', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('class_count', $form_data); ?>" min="1">
                            <?php if (has_field_error('class_count', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('class_count', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">見学者人数(児童・生徒) <span class="required">*</span></label>
                            <input type="number" name="student_count" class="form-input <?php echo has_field_error('student_count', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('student_count', $form_data); ?>" min="1">
                            <?php if (has_field_error('student_count', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('student_count', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">見学者人数(引率) <span class="required">*</span></label>
                            <input type="number" name="supervisor_count" class="form-input <?php echo has_field_error('supervisor_count', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('supervisor_count', $form_data); ?>" min="1">
                            <?php if (has_field_error('supervisor_count', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('supervisor_count', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 学生（就活等）の詳細 -->
                <div id="student-recruit-details" class="reservation-type-details" style="display: none;">
                    <div class="form-section-header with-border">
                        <h3 class="form-section-title">学生（就活等）詳細</h3>
                    </div>
                    <div class="form-section-content">
                        <div class="form-field">
                            <label class="form-label">学校名 <span class="required">*</span></label>
                            <input type="text" name="recruit_school_name" class="form-input <?php echo has_field_error('recruit_school_name', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('recruit_school_name', $form_data); ?>">
                            <?php if (has_field_error('recruit_school_name', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('recruit_school_name', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">学部 <span class="required">*</span></label>
                            <input type="text" name="recruit_department" class="form-input <?php echo has_field_error('recruit_department', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('recruit_department', $form_data); ?>">
                            <?php if (has_field_error('recruit_department', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('recruit_department', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">学年 <span class="required">*</span></label>
                            <input type="text" name="recruit_grade" class="form-input <?php echo has_field_error('recruit_grade', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('recruit_grade', $form_data); ?>">
                            <?php if (has_field_error('recruit_grade', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('recruit_grade', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">見学者様人数 <span class="required">*</span></label>
                            <select name="recruit_visitor_count" class="form-select <?php echo has_field_error('recruit_visitor_count', $field_errors) ? 'error-field' : ''; ?>" id="recruit-visitor-count">
                                <option value="">選択してください</option>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected(get_field_value('recruit_visitor_count', $form_data), $i); ?>>
                                        <?php echo $i; ?>人
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <?php if (has_field_error('recruit_visitor_count', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('recruit_visitor_count', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 同行者情報（動的生成） -->
                        <div id="companion-fields">
                            <?php 
                            $visitor_count = get_field_value('recruit_visitor_count', $form_data);
                            if ($visitor_count && $visitor_count > 1) {
                                for ($i = 1; $i < $visitor_count; $i++) {
                                    echo '<div class="companion-field-group" data-companion="' . $i . '">';
                                    echo '<div class="form-field">';
                                    echo '<label class="form-label">同行者様' . $i . ' 氏名 <span class="required">*</span></label>';
                                    echo '<input type="text" name="companion_name_' . $i . '" class="form-input ' . (has_field_error("companion_name_$i", $field_errors) ? 'error-field' : '') . '" value="' . get_field_value("companion_name_$i", $form_data) . '">';
                                    if (has_field_error("companion_name_$i", $field_errors)) {
                                        echo '<span class="field-error">' . esc_html(get_field_error("companion_name_$i", $field_errors)) . '</span>';
                                    }
                                    echo '</div>';
                                    echo '<div class="form-field">';
                                    echo '<label class="form-label">同行者様' . $i . ' 学部 <span class="required">*</span></label>';
                                    echo '<input type="text" name="companion_department_' . $i . '" class="form-input ' . (has_field_error("companion_department_$i", $field_errors) ? 'error-field' : '') . '" value="' . get_field_value("companion_department_$i", $form_data) . '">';
                                    if (has_field_error("companion_department_$i", $field_errors)) {
                                        echo '<span class="field-error">' . esc_html(get_field_error("companion_department_$i", $field_errors)) . '</span>';
                                    }
                                    echo '</div>';
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- その他タイプの詳細 -->
                <div id="general-details" class="reservation-type-details" style="display: none;">
                    <div class="form-section-header with-border">
                        <h3 class="form-section-title">団体・会社詳細</h3>
                    </div>
                    <div class="form-section-content">
                        <div class="form-field">
                            <label class="form-label">会社・団体名 <span class="required">*</span></label>
                            <input type="text" name="company_name" class="form-input <?php echo has_field_error('company_name', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('company_name', $form_data); ?>">
                            <?php if (has_field_error('company_name', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('company_name', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">会社・団体名(ふりがな) <span class="required">*</span></label>
                            <input type="text" name="company_name_kana" class="form-input <?php echo has_field_error('company_name_kana', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('company_name_kana', $form_data); ?>">
                            <?php if (has_field_error('company_name_kana', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('company_name_kana', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">代表者氏名</label>
                            <input type="text" name="representative_name" class="form-input <?php echo has_field_error('representative_name', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('representative_name', $form_data); ?>">
                            <?php if (has_field_error('representative_name', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('representative_name', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">代表者氏名(ふりがな)</label>
                            <input type="text" name="representative_name_kana" class="form-input <?php echo has_field_error('representative_name_kana', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('representative_name_kana', $form_data); ?>">
                            <?php if (has_field_error('representative_name_kana', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('representative_name_kana', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">見学者人数(大人) <span class="required">*</span></label>
                            <input type="number" name="adult_count" class="form-input <?php echo has_field_error('adult_count', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('adult_count', $form_data); ?>" min="0">
                            <?php if (has_field_error('adult_count', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('adult_count', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">見学者人数(子ども) <span class="required">*</span></label>
                            <input type="number" name="child_count" class="form-input <?php echo has_field_error('child_count', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('child_count', $form_data); ?>" min="0">
                            <?php if (has_field_error('child_count', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('child_count', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">子どもの学年</label>
                            <input type="text" name="child_grade" class="form-input <?php echo has_field_error('child_grade', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('child_grade', $form_data); ?>" placeholder="例: 小学3年生">
                            <?php if (has_field_error('child_grade', $field_errors)): ?>
                                <span class="field-error"><?php echo esc_html(get_field_error('child_grade', $field_errors)); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 申込者連絡先 -->
                <div class="form-section-header with-border">
                    <h3 class="form-section-title">申込者連絡先</h3>
                </div>
                <div class="form-section-content">
                    <div class="form-field">
                        <label class="form-label">申込者郵便番号 <span class="required">*</span></label>
                        <input type="text" name="applicant_zip" class="form-input <?php echo has_field_error('applicant_zip', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('applicant_zip', $form_data); ?>" placeholder="例: 1234567">
                        <?php if (has_field_error('applicant_zip', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('applicant_zip', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">申込者都道府県 <span class="required">*</span></label>
                        <select name="applicant_prefecture" class="form-select <?php echo has_field_error('applicant_prefecture', $field_errors) ? 'error-field' : ''; ?>">
                            <option value="">選択してください</option>
                            <?php foreach ($prefectures as $prefecture): ?>
                                <option value="<?php echo esc_attr($prefecture); ?>" <?php selected(get_field_value('applicant_prefecture', $form_data), $prefecture); ?>>
                                    <?php echo esc_html($prefecture); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (has_field_error('applicant_prefecture', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('applicant_prefecture', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">申込者市区町村 <span class="required">*</span></label>
                        <input type="text" name="applicant_city" class="form-input <?php echo has_field_error('applicant_city', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('applicant_city', $form_data); ?>">
                        <?php if (has_field_error('applicant_city', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('applicant_city', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">申込者住所</label>
                        <input type="text" name="applicant_address" class="form-input <?php echo has_field_error('applicant_address', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('applicant_address', $form_data); ?>">
                        <?php if (has_field_error('applicant_address', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('applicant_address', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">申込者電話番号 <span class="required">*</span></label>
                        <input type="tel" name="applicant_phone" class="form-input <?php echo has_field_error('applicant_phone', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('applicant_phone', $form_data); ?>">
                        <?php if (has_field_error('applicant_phone', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('applicant_phone', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">当日連絡先 <span class="required">*</span></label>
                        <input type="tel" name="emergency_contact" class="form-input <?php echo has_field_error('emergency_contact', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('emergency_contact', $form_data); ?>">
                        <?php if (has_field_error('emergency_contact', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('emergency_contact', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">申込者メールアドレス <span class="required">*</span></label>
                        <input type="email" name="applicant_email" class="form-input <?php echo has_field_error('applicant_email', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('applicant_email', $form_data); ?>">
                        <?php if (has_field_error('applicant_email', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('applicant_email', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 交通手段 -->
                <div class="form-section-header with-border">
                    <h3 class="form-section-title">交通手段</h3>
                </div>
                <div class="form-section-content">
                    <div class="form-field">
                        <label class="form-label">交通機関 <span class="required">*</span></label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="transportation" value="car" <?php checked(get_field_value('transportation', $form_data), 'car'); ?> required>
                                車
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="transportation" value="chartered_bus" <?php checked(get_field_value('transportation', $form_data), 'chartered_bus'); ?> required>
                                貸切バス
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="transportation" value="local_bus" <?php checked(get_field_value('transportation', $form_data), 'local_bus'); ?> required>
                                路線バス
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="transportation" value="taxi" <?php checked(get_field_value('transportation', $form_data), 'taxi'); ?> required>
                                タクシー
                            </label>
                            <div class="transportation-other-option">
                                <label class="radio-option">
                                    <input type="radio" name="transportation" value="other" <?php checked(get_field_value('transportation', $form_data), 'other'); ?> required>
                                    その他
                                </label>
                                <input type="text" name="transportation_other_text" class="transportation-other-input form-input <?php echo has_field_error('transportation_other_text', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('transportation_other_text', $form_data); ?>" placeholder="その他の交通手段を入力" disabled>
                            </div>
                        </div>
                    </div>
                    <?php if (has_field_error('transportation', $field_errors)): ?>
                        <div class="form-field">
                            <label class="form-label"></label>
                            <span class="field-error"><?php echo esc_html(get_field_error('transportation', $field_errors)); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (has_field_error('transportation_other_text', $field_errors)): ?>
                        <div class="form-field">
                            <label class="form-label"></label>
                            <span class="field-error"><?php echo esc_html(get_field_error('transportation_other_text', $field_errors)); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-field" id="vehicle-count-field" style="display: none;">
                        <label class="form-label">台数 <span class="required">*</span></label>
                        <input type="number" name="vehicle_count" class="form-input <?php echo has_field_error('vehicle_count', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('vehicle_count', $form_data); ?>" min="1">
                        <?php if (has_field_error('vehicle_count', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('vehicle_count', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- その他の情報 -->
                <div class="form-section-header with-border">
                    <h3 class="form-section-title">その他の情報</h3>
                </div>
                <div class="form-section-content">
                    <div class="form-field">
                        <label class="form-label">見学目的 <span class="required">*</span></label>
                        <textarea name="visit_purpose" class="form-input <?php echo has_field_error('visit_purpose', $field_errors) ? 'error-field' : ''; ?>" rows="4" required><?php echo get_field_value('visit_purpose', $form_data); ?></textarea>
                        <?php if (has_field_error('visit_purpose', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('visit_purpose', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">見学者人数 <span class="required">*</span></label>
                        <input type="number" name="total_visitors" class="form-input <?php echo has_field_error('total_visitors', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('total_visitors', $form_data); ?>" min="1" required>
                        <?php if (has_field_error('total_visitors', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('total_visitors', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">小学生以下人数</label>
                        <input type="number" name="elementary_visitors" class="form-input <?php echo has_field_error('elementary_visitors', $field_errors) ? 'error-field' : ''; ?>" value="<?php echo get_field_value('elementary_visitors', $form_data); ?>" min="0">
                        <?php if (has_field_error('elementary_visitors', $field_errors)): ?>
                            <span class="field-error"><?php echo esc_html(get_field_error('elementary_visitors', $field_errors)); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="btn-register-container">
                    <input type="submit" name="submit_reservation" value="<?php echo $is_edit_mode ? '更新' : '新規追加'; ?>" class="btn-register">
                </div>
            </form>
        </div>
        
        <!-- ステータス管理パネル -->
        <?php if ($is_edit_mode): ?>
        <div class="reservation-status">
            <div class="form-section-header">
                <h3 class="form-section-title">ステータス管理</h3>
            </div>
            <div class="form-section-content">
                <select name="reservation_status" class="form-select status" form="status-form">
                    <option value="new" <?php selected(get_field_value('status', $form_data), 'new'); ?>>新規受付</option>
                    <option value="pending" <?php selected(get_field_value('status', $form_data), 'pending'); ?>>確認中</option>
                    <option value="approved" <?php selected(get_field_value('status', $form_data), 'approved'); ?>>承認</option>
                    <option value="rejected" <?php selected(get_field_value('status', $form_data), 'rejected'); ?>>否認</option>
                    <option value="cancelled" <?php selected(get_field_value('status', $form_data), 'cancelled'); ?>>キャンセル</option>
                </select>
                
                <button type="button" class="btn-reply-email" onclick="openEmailDialog()">返信メール作成</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// 旅行会社セクションの表示/非表示
function toggleTravelAgencySection() {
    const isAgency = document.querySelector('input[name="is_travel_agency"]:checked')?.value === 'yes';
    const section = document.getElementById('travel-agency-section');
    if (section) {
        section.style.display = isAgency ? 'block' : 'none';
    }
}

// 予約タイプの詳細セクション表示/非表示
function toggleReservationTypeDetails() {
    const selectedType = document.querySelector('input[name="reservation_type"]:checked')?.value;
    
    // 全てのセクションを非表示にする
    document.querySelectorAll('.reservation-type-details').forEach(section => {
        section.style.display = 'none';
    });
    
    // 選択されたタイプに応じてセクションを表示
    if (selectedType === 'school') {
        document.getElementById('school-details').style.display = 'block';
    } else if (selectedType === 'student_recruit') {
        document.getElementById('student-recruit-details').style.display = 'block';
    } else if (['family', 'company', 'municipality', 'other'].includes(selectedType)) {
        document.getElementById('general-details').style.display = 'block';
    }
}

// 交通手段の選択による台数フィールドの表示/非表示
function toggleVehicleCountField() {
    const selectedTransportation = document.querySelector('input[name="transportation"]:checked')?.value;
    const vehicleCountField = document.getElementById('vehicle-count-field');
    const otherInput = document.querySelector('input[name="transportation_other_text"]');
    
    // 台数フィールドの表示/非表示
    if (['car', 'chartered_bus', 'taxi'].includes(selectedTransportation)) {
        vehicleCountField.style.display = 'block';
    } else {
        vehicleCountField.style.display = 'none';
    }
    
    // その他の入力フィールドの有効/無効
    if (selectedTransportation === 'other') {
        otherInput.disabled = false;
        otherInput.required = true;
    } else {
        otherInput.disabled = true;
        otherInput.required = false;
        otherInput.value = '';
    }
}

// 同行者フィールドの動的生成
function updateCompanionFields() {
    const count = parseInt(document.getElementById('recruit-visitor-count').value) || 0;
    const container = document.getElementById('companion-fields');
    
    // 既存のフィールドをクリア
    container.innerHTML = '';
    
    // 2人以上の場合に同行者フィールドを生成
    for (let i = 1; i < count && i <= 10; i++) {
        const groupDiv = document.createElement('div');
        groupDiv.className = 'companion-field-group';
        groupDiv.setAttribute('data-companion', i);
        
        groupDiv.innerHTML = `
            <div class="form-field">
                <label class="form-label">同行者様${i} 氏名 <span class="required">*</span></label>
                <input type="text" name="companion_name_${i}" class="form-input" required>
            </div>
            <div class="form-field">
                <label class="form-label">同行者様${i} 学部 <span class="required">*</span></label>
                <input type="text" name="companion_department_${i}" class="form-input" required>
            </div>
        `;
        
        container.appendChild(groupDiv);
    }
}

// イベントリスナーの設定
document.addEventListener('DOMContentLoaded', function() {
    // 初期状態の設定
    toggleTravelAgencySection();
    toggleReservationTypeDetails();
    toggleVehicleCountField();
    
    // 旅行会社ラジオボタンのイベント
    document.querySelectorAll('input[name="is_travel_agency"]').forEach(radio => {
        radio.addEventListener('change', toggleTravelAgencySection);
    });
    
    // 予約タイプラジオボタンのイベント
    document.querySelectorAll('input[name="reservation_type"]').forEach(radio => {
        radio.addEventListener('change', toggleReservationTypeDetails);
    });
    
    // 交通手段ラジオボタンのイベント
    document.querySelectorAll('input[name="transportation"]').forEach(radio => {
        radio.addEventListener('change', toggleVehicleCountField);
    });
    
    // 見学者人数の変更イベント
    const visitorCountSelect = document.getElementById('recruit-visitor-count');
    if (visitorCountSelect) {
        visitorCountSelect.addEventListener('change', updateCompanionFields);
    }
});

function openEmailDialog() {
    alert('メール作成機能は今後実装予定です。');
}
</script>