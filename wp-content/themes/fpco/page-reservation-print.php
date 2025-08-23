<?php
/**
 * Template Name: 予約内容印刷
 * 
 * 工場見学の予約内容印刷画面
 */

// ヘッダーを出力せず、印刷専用のレイアウト
$form_data = $_POST;

if (empty($form_data)) {
    wp_die('印刷データが見つかりません。');
}

$factory_name = get_factory_name($form_data['factory_id']);
$timeslot_info = parse_timeslot($form_data['timeslot'], $form_data['factory_id']);
$reservation_id = $form_data['reservation_id'] ?? '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>工場見学予約内容 - <?php bloginfo('name'); ?></title>
    
    <style>
        /* 印刷専用スタイル - 確認画面ベース */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Hiragino Sans', 'Yu Gothic', 'Meiryo', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            background: white;
            padding: 20px;
        }
        
        .print-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        /* ヘッダー */
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: bold;
            color: #5C5548;
            margin-bottom: 10px;
        }
        
        .header .company-info {
            font-size: 14px;
            color: #666;
        }
        
        /* 予約情報ボックス */
        .reservation-info-box {
            border: 2px solid #4A4A4A;
            border-radius: 0;
            background: #fff;
            margin-bottom: 20px;
        }
        
        /* 情報行 */
        .info-row {
            display: flex;
            align-items: center;
            min-height: 60px;
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
            flex: 0 0 250px;
            padding: 15px 0;
            background: transparent;
            font-weight: bold;
            font-size: 15px;
            color: #5C5548;
            display: flex;
            align-items: center;
            white-space: nowrap;
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
        }
        
        /* 長いテキスト用のスタイル */
        .info-row.long-text {
            align-items: flex-start;
            min-height: 80px;
            padding: 15px 40px;
        }
        
        .info-row.long-text .info-label {
            align-self: flex-start;
            margin-top: 5px;
        }
        
        .info-row.long-text .info-value {
            align-self: flex-start;
            padding-top: 5px;
            white-space: pre-wrap;
        }
        
        /* 同行者詳細スタイル */
        .companion-detail {
            line-height: 1.8;
        }
        
        .companion-detail > * {
            white-space: nowrap;
            display: block;
        }
        
        /* 印刷時のスタイル調整 */
        @media print {
            body {
                padding: 10mm;
                font-size: 12px;
            }
            
            .print-container {
                max-width: none;
            }
            
            .info-row {
                min-height: 50px;
                padding: 0 20px;
            }
            
            .info-label {
                flex: 0 0 200px;
                font-size: 12px;
            }
            
            .info-value {
                font-size: 12px;
                padding-left: 15px;
            }
            
            .header h1 {
                font-size: 20px;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="print-container">
        <!-- ヘッダー -->
        <div class="header">
            <h1>工場見学予約申込書</h1>
            <div class="company-info">
                印刷日時：<?php echo date('Y年m月d日 H:i', current_time('timestamp')); ?>
            </div>
        </div>

        <!-- 予約情報ボックス -->
        <div class="reservation-info-box">
            
            <div class="info-row">
                <span class="info-label">見学日</span>
                <span class="info-value"><?php echo esc_html(format_display_date($form_data['date'])); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">見学時間</span>
                <span class="info-value"><?php echo esc_html($timeslot_info['duration']); ?></span>
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
            
            <?php echo generate_visitor_details_rows($form_data); ?>
            
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
                <span class="info-label">当日連絡先（携帯番号）</span>
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
                <span class="info-value"><?php echo calculate_total_visitors($form_data); ?>名　内小学生以下<?php echo esc_html($form_data['total_child_count'] ?? '0'); ?>名</span>
            </div>
        </div>
    </div>

    <script>
        // 印刷ダイアログを自動で開く
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>

<?php
// ヘルパー関数（他のファイルから複製）

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

function get_transportation_display($transportation, $form_data) {
    $transportation_labels = [
        'car' => '車',
        'chartered_bus' => '貸切バス',
        'local_bus' => '路線バス',
        'route_bus' => '路線バス',  // 古いデータの互換性のため
        'taxi' => 'タクシー',
        'other' => 'その他'
    ];
    
    $label = $transportation_labels[$transportation] ?? '';
    
    if ($transportation === 'other' && !empty($form_data['transportation_other_text'])) {
        $label .= '（' . $form_data['transportation_other_text'] . '）';
    }
    
    return $label;
}

function generate_visitor_details_rows($form_data) {
    $category = $form_data['visitor_category'];
    $html = '';
    
    switch ($category) {
        case 'school':
            if (!empty($form_data['school_name'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">学校・団体名</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['school_name']) . '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['school_name_kana'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">学校・団体名（ふりがな）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['school_name_kana']) . '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['school_representative_name'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">代表者様氏名</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['school_representative_name']) . '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['school_representative_kana'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">代表者様氏名（ふりがな）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['school_representative_kana']) . '</span>';
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
                $html .= '<span class="info-label">クラス数</span>';
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
            
            // 同行者情報を追加
            if (!empty($form_data['recruit_visitor_count']) && $form_data['recruit_visitor_count'] > 1) {
                for ($i = 1; $i < intval($form_data['recruit_visitor_count']) && $i <= 8; $i++) {
                    if (!empty($form_data["companion_{$i}_name"])) {
                        $html .= '<div class="info-row">';
                        $html .= '<span class="info-label">同行者様' . numberToCircle($i) . '</span>';
                        $html .= '<span class="info-value">';
                        $html .= '<div class="companion-detail">';
                        $html .= '<span>氏名 ' . esc_html($form_data["companion_{$i}_name"]) . '</span>';
                        if (!empty($form_data["companion_{$i}_department"])) {
                            $html .= '<span>学部 ' . esc_html($form_data["companion_{$i}_department"]) . '</span>';
                        }
                        $html .= '</div>';
                        $html .= '</span>';
                        $html .= '</div>';
                    }
                }
            }
            break;
            
        case 'family':
            if (!empty($form_data['family_organization_name'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">会社・団体名</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['family_organization_name']) . '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['family_organization_kana'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">会社・団体名（ふりがな）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['family_organization_kana']) . '</span>';
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
                $html .= '<span class="info-value">' . esc_html($form_data['company_name']) . '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['company_kana'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">会社・団体名（ふりがな）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['company_kana']) . '</span>';
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
                $html .= '<span class="info-value">' . esc_html($form_data['government_name']) . '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['government_kana'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">会社・団体名（ふりがな）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['government_kana']) . '</span>';
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
                $html .= '<span class="info-value">' . esc_html($form_data['other_group_name']) . '</span>';
                $html .= '</div>';
            }
            
            if (!empty($form_data['other_group_kana'])) {
                $html .= '<div class="info-row">';
                $html .= '<span class="info-label">会社・団体名（ふりがな）</span>';
                $html .= '<span class="info-value">' . esc_html($form_data['other_group_kana']) . '</span>';
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

function numberToCircle($num) {
    $circles = ['①', '②', '③', '④', '⑤', '⑥', '⑦', '⑧'];
    return $circles[$num - 1] ?? $num;
}

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

function parse_timeslot($timeslot, $factory_id = null) {
    // プラグインの関数を読み込み
    require_once WP_PLUGIN_DIR . '/fpco-factory-reservation-system/includes/reservation-management-functions.php';
    require_once WP_PLUGIN_DIR . '/fpco-factory-reservation-system/includes/factory-user-management-functions.php';
    
    // timeslot形式: am-60-1, pm-90-2, am-1, pm-2 など
    $parts = explode('-', $timeslot);
    $period = $parts[0] ?? '';
    $duration = '';
    $index = '';
    $formatted_time_range = '';
    
    // 60分・90分パターンの判定
    if (isset($parts[1]) && in_array($parts[1], ['60', '90'])) {
        $duration = $parts[1];
        $index = $parts[2] ?? '1';
        
        // 60分・90分パターンでも時間帯を取得
        if ($factory_id && function_exists('fpco_get_factory_timeslots')) {
            $timeslots = fpco_get_factory_timeslots($factory_id);
            
            // duration形式の時間スロットを検索
            $duration_key = $duration . 'min';
            if (isset($timeslots[$duration_key]) && isset($timeslots[$duration_key][$period])) {
                $period_slots = $timeslots[$duration_key][$period];
                $slot_index = intval($index) - 1;
                
                if (isset($period_slots[$slot_index])) {
                    $formatted_time_range = $period_slots[$slot_index];
                }
            }
        }
    } else {
        // AM/PMパターンの場合、工場IDからプラグインで時間を取得
        $index = $parts[1] ?? '1';
        
        if ($factory_id && function_exists('fpco_get_factory_timeslots')) {
            $timeslots = fpco_get_factory_timeslots($factory_id);
            
            // 対応する時間スロットを検索
            if (isset($timeslots[$period])) {
                $period_slots = $timeslots[$period];
                $slot_index = intval($index) - 1; // 0ベースのインデックスに変換
                
                if (isset($period_slots[$slot_index])) {
                    $time_range = $period_slots[$slot_index];
                    $formatted_time_range = $time_range;
                    
                    // "10:30 ~ 11:30" 形式から分数を計算
                    if (preg_match('/(\d{1,2}):(\d{2})\s*~\s*(\d{1,2}):(\d{2})/', $time_range, $matches)) {
                        $start_hour = intval($matches[1]);
                        $start_min = intval($matches[2]);
                        $end_hour = intval($matches[3]);
                        $end_min = intval($matches[4]);
                        
                        $duration_minutes = ($end_hour * 60 + $end_min) - ($start_hour * 60 + $start_min);
                        $duration = (string)$duration_minutes;
                    }
                }
            }
        }
        
        // プラグインから取得できない場合のデフォルト
        if (empty($duration)) {
            $duration = '90';
        }
    }
    
    $period_text = ($period === 'am') ? 'AM' : 'PM';
    
    // 時間範囲があれば表示形式を作成
    if (!empty($formatted_time_range)) {
        $period_display = $period_text . '(' . $formatted_time_range . ')';
    } else {
        $period_display = $period_text;
    }
    
    return [
        'display' => $period_display,
        'duration' => $duration . '分',
        'period' => $period,
        'index' => $index
    ];
}

function format_display_date($date) {
    $timestamp = strtotime($date);
    if ($timestamp) {
        return date('Y年m月d日', $timestamp);
    }
    return '';
}

function calculate_total_visitors($form_data) {
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
?>