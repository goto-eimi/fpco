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
$timeslot_info = parse_timeslot($form_data['timeslot']);
$reservation_id = $form_data['reservation_id'] ?? '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>工場見学予約内容 - <?php bloginfo('name'); ?></title>
    
    <style>
        /* 印刷専用スタイル */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Hiragino Sans', 'Yu Gothic', 'Meiryo', sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #333;
            background: white;
            padding: 20mm;
        }
        
        .print-container {
            max-width: 100%;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30pt;
            border-bottom: 2pt solid #333;
            padding-bottom: 10pt;
        }
        
        .header h1 {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 5pt;
        }
        
        .header .company-info {
            font-size: 10pt;
            color: #666;
        }
        
        .reservation-id {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 20pt;
            color: #007cba;
        }
        
        .section {
            margin-bottom: 25pt;
            page-break-inside: avoid;
        }
        
        .section h2 {
            font-size: 14pt;
            font-weight: bold;
            background: #f0f0f0;
            padding: 8pt 12pt;
            border-left: 4pt solid #007cba;
            margin-bottom: 10pt;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15pt;
        }
        
        .info-table th,
        .info-table td {
            border: 1pt solid #ccc;
            padding: 8pt 12pt;
            text-align: left;
            vertical-align: top;
        }
        
        .info-table th {
            background: #f8f9fa;
            font-weight: bold;
            width: 30%;
            color: #555;
        }
        
        .info-table td {
            background: white;
        }
        
        .total-visitors {
            background: #e8f4fd !important;
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            color: #007cba;
        }
        
        .notice-box {
            background: #fff3cd;
            border: 1pt solid #ffeaa7;
            padding: 15pt;
            margin-top: 20pt;
            border-radius: 4pt;
        }
        
        .notice-box h3 {
            color: #856404;
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 8pt;
        }
        
        .notice-box ul {
            color: #856404;
            margin-left: 15pt;
        }
        
        .notice-box li {
            margin-bottom: 4pt;
        }
        
        .footer {
            margin-top: 30pt;
            text-align: center;
            font-size: 10pt;
            color: #666;
            border-top: 1pt solid #ccc;
            padding-top: 10pt;
        }
        
        /* 改ページ制御 */
        .page-break {
            page-break-before: always;
        }
        
        /* 印刷時のスタイル調整 */
        @media print {
            body {
                padding: 10mm;
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
                株式会社エフピコ<br>
                〒721-8607 広島県福山市曙町1-13-15
            </div>
        </div>

        <!-- 予約受付番号 -->
        <?php if ($reservation_id): ?>
        <div class="reservation-id">
            予約受付番号：<?php echo esc_html($reservation_id); ?>
        </div>
        <?php endif; ?>

        <!-- ご予約内容 -->
        <div class="section">
            <h2>ご予約内容</h2>
            <table class="info-table">
                <tr>
                    <th>見学工場</th>
                    <td><?php echo esc_html($factory_name); ?>工場</td>
                </tr>
                <tr>
                    <th>見学日</th>
                    <td><?php echo esc_html(format_display_date($form_data['date'])); ?></td>
                </tr>
                <tr>
                    <th>見学時間帯</th>
                    <td><?php echo esc_html($timeslot_info['display']); ?></td>
                </tr>
                <tr>
                    <th>見学時間</th>
                    <td><?php echo esc_html($timeslot_info['duration']); ?>分</td>
                </tr>
            </table>
        </div>

        <!-- 申込者様情報 -->
        <div class="section">
            <h2>申込者様情報</h2>
            <table class="info-table">
                <tr>
                    <th>氏名（ふりがな）</th>
                    <td><?php echo esc_html($form_data['applicant_name']); ?>（<?php echo esc_html($form_data['applicant_name_kana']); ?>）</td>
                </tr>
                <tr>
                    <th>住所</th>
                    <td>
                        〒<?php echo esc_html($form_data['postal_code']); ?><br>
                        <?php echo esc_html($form_data['prefecture']); ?><?php echo esc_html($form_data['city']); ?><?php echo esc_html($form_data['address']); ?>
                        <?php if (!empty($form_data['building'])): ?>
                            <br><?php echo esc_html($form_data['building']); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>電話番号</th>
                    <td><?php echo esc_html($form_data['phone']); ?></td>
                </tr>
                <tr>
                    <th>携帯番号</th>
                    <td><?php echo esc_html($form_data['mobile']); ?></td>
                </tr>
                <tr>
                    <th>メールアドレス</th>
                    <td><?php echo esc_html($form_data['email']); ?></td>
                </tr>
                <tr>
                    <th>ご利用の交通機関</th>
                    <td>
                        <?php echo esc_html(get_transportation_display($form_data['transportation'], $form_data)); ?>
                        <?php if (!empty($form_data['vehicle_count'])): ?>
                            （<?php echo esc_html($form_data['vehicle_count']); ?>台）
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>見学目的</th>
                    <td><?php echo nl2br(esc_html($form_data['purpose'])); ?></td>
                </tr>
            </table>
        </div>

        <!-- 旅行会社情報（該当する場合） -->
        <?php if ($form_data['is_travel_agency'] === 'yes'): ?>
        <div class="section">
            <h2>旅行会社情報</h2>
            <table class="info-table">
                <tr>
                    <th>旅行会社名</th>
                    <td><?php echo esc_html($form_data['agency_name']); ?></td>
                </tr>
                <tr>
                    <th>電話番号</th>
                    <td><?php echo esc_html($form_data['agency_phone']); ?></td>
                </tr>
                <tr>
                    <th>住所</th>
                    <td>
                        〒<?php echo esc_html($form_data['agency_postal_code']); ?><br>
                        <?php echo esc_html($form_data['agency_prefecture']); ?><?php echo esc_html($form_data['agency_city']); ?><?php echo esc_html($form_data['agency_address']); ?>
                        <?php if (!empty($form_data['agency_building'])): ?>
                            <br><?php echo esc_html($form_data['agency_building']); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!empty($form_data['agency_fax'])): ?>
                <tr>
                    <th>FAX番号</th>
                    <td><?php echo esc_html($form_data['agency_fax']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($form_data['agency_contact_mobile'])): ?>
                <tr>
                    <th>担当者携帯番号</th>
                    <td><?php echo esc_html($form_data['agency_contact_mobile']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>担当者メールアドレス</th>
                    <td><?php echo esc_html($form_data['agency_contact_email']); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <!-- 見学者様情報 -->
        <div class="section">
            <h2>見学者様情報</h2>
            <table class="info-table">
                <tr>
                    <th>見学者様の分類</th>
                    <td><?php echo esc_html(get_visitor_category_display($form_data['visitor_category'])); ?></td>
                </tr>
                
                <?php echo generate_visitor_details_table($form_data); ?>
                
                <tr>
                    <th>見学者様人数（合計）</th>
                    <td class="total-visitors"><?php echo calculate_total_visitors($form_data); ?>名</td>
                </tr>
            </table>
        </div>

        <!-- 注意事項 -->
        <div class="notice-box">
            <h3>見学当日のご案内</h3>
            <ul>
                <li>受付時間の10分前にお越しください。</li>
                <li>安全のため、見学時はヘルメットの着用が必要です（当社で準備いたします）。</li>
                <li>機械の稼働状況により、見学コースが変更になる場合があります。</li>
                <li>キャンセルやご変更の場合は、お早めにご連絡ください。</li>
                <li>安全上、歩きやすい靴でお越しください。</li>
            </ul>
        </div>

        <!-- フッター -->
        <div class="footer">
            印刷日時：<?php echo date('Y年m月d日 H:i'); ?><br>
            お問い合わせ：TEL 084-953-1411（平日 9:00-17:00）
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

function generate_visitor_details_table($form_data) {
    $category = $form_data['visitor_category'];
    $html = '';
    
    switch ($category) {
        case 'school':
            $html .= '<tr><th>学校・団体名</th><td>' . esc_html($form_data['school_name']) . '（' . esc_html($form_data['school_name_kana']) . '）</td></tr>';
            
            if (!empty($form_data['representative_name'])) {
                $html .= '<tr><th>代表者様氏名</th><td>' . esc_html($form_data['representative_name']);
                if (!empty($form_data['representative_name_kana'])) {
                    $html .= '（' . esc_html($form_data['representative_name_kana']) . '）';
                }
                $html .= '</td></tr>';
            }
            
            $html .= '<tr><th>学年・クラス</th><td>' . esc_html($form_data['grade']) . '年生 ' . esc_html($form_data['class_count']) . 'クラス</td></tr>';
            $html .= '<tr><th>見学者様人数</th><td>児童・生徒 ' . esc_html($form_data['student_count']) . '名、引率 ' . esc_html($form_data['teacher_count']) . '名</td></tr>';
            break;
            
        case 'recruit':
            $html .= '<tr><th>学校名</th><td>' . esc_html($form_data['recruit_school_name']) . '</td></tr>';
            $html .= '<tr><th>学年・学部</th><td>' . esc_html($form_data['recruit_grade']) . '年生 ' . esc_html($form_data['recruit_department']) . '</td></tr>';
            $html .= '<tr><th>見学者様人数</th><td>' . esc_html($form_data['recruit_visitor_count']) . '名</td></tr>';
            break;
            
        case 'family':
            $html .= '<tr><th>見学者様人数</th><td>大人 ' . esc_html($form_data['adult_count']) . '名、子ども ' . esc_html($form_data['child_count']) . '名</td></tr>';
            
            if (!empty($form_data['child_grades'])) {
                $html .= '<tr><th>学年</th><td>' . esc_html($form_data['child_grades']) . '</td></tr>';
            }
            break;
            
        default:
            $html .= '<tr><th>会社・団体名</th><td>' . esc_html($form_data['organization_name']) . '（' . esc_html($form_data['organization_name_kana']) . '）</td></tr>';
            $html .= '<tr><th>見学者様人数</th><td>大人 ' . esc_html($form_data['org_adult_count']) . '名、子ども ' . esc_html($form_data['org_child_count']) . '名</td></tr>';
            
            if (!empty($form_data['org_child_grades'])) {
                $html .= '<tr><th>学年</th><td>' . esc_html($form_data['org_child_grades']) . '</td></tr>';
            }
            break;
    }
    
    return $html;
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

function parse_timeslot($timeslot) {
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

function calculate_total_visitors($form_data) {
    $category = $form_data['visitor_category'];
    $total = 0;
    
    switch ($category) {
        case 'school':
            $total = (int)$form_data['student_count'] + (int)$form_data['teacher_count'];
            break;
            
        case 'recruit':
            $total = (int)$form_data['recruit_visitor_count'];
            break;
            
        case 'family':
            $total = (int)$form_data['adult_count'] + (int)$form_data['child_count'];
            break;
            
        default:
            $total = (int)$form_data['org_adult_count'] + (int)$form_data['org_child_count'];
            break;
    }
    
    return $total;
}
?>