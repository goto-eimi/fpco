<?php
/**
 * ショートコードクラス
 * 
 * フロントエンド用のショートコード機能を提供
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPCO_Shortcodes {
    
    public function __construct() {
        // ショートコードの登録
        add_shortcode('fpco_factory_list', array($this, 'factory_list_shortcode'));
        add_shortcode('fpco_reservation_calendar', array($this, 'reservation_calendar_shortcode'));
        add_shortcode('fpco_reservation_form', array($this, 'reservation_form_shortcode'));
        
        // スクリプトとスタイルの読み込み
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * フロントエンド用スクリプトとスタイルの読み込み
     */
    public function enqueue_frontend_scripts() {
        // カレンダー用のスクリプト（必要なページのみ）
        if (has_shortcode(get_post()->post_content ?? '', 'fpco_reservation_calendar')) {
            wp_enqueue_script('fpco-calendar-frontend', FPCO_RESERVATION_PLUGIN_URL . 'assets/js/calendar-frontend.js', array('jquery'), FPCO_RESERVATION_VERSION, true);
            wp_localize_script('fpco-calendar-frontend', 'fpco_calendar_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fpco_calendar_nonce')
            ));
        }
        
        // 共通スタイル
        wp_enqueue_style('fpco-frontend', FPCO_RESERVATION_PLUGIN_URL . 'assets/css/frontend.css', array(), FPCO_RESERVATION_VERSION);
    }
    
    /**
     * 工場一覧ショートコード
     */
    public function factory_list_shortcode($atts) {
        global $wpdb;
        
        $atts = shortcode_atts(array(
            'limit' => -1,
            'order' => 'name'
        ), $atts);
        
        $limit_sql = $atts['limit'] > 0 ? "LIMIT " . intval($atts['limit']) : "";
        $order_sql = "ORDER BY " . sanitize_sql_orderby($atts['order']) . " ASC";
        
        $factories = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}factorys {$order_sql} {$limit_sql}"
        );
        
        if (empty($factories)) {
            return '<p>工場が見つかりません。</p>';
        }
        
        ob_start();
        ?>
        <div class="fpco-factory-list">
            <?php foreach ($factories as $factory): ?>
                <div class="fpco-factory-item">
                    <h3><?php echo esc_html($factory->name); ?></h3>
                    <p>定員: <?php echo esc_html($factory->capacity); ?>名</p>
                    <a href="<?php echo esc_url(add_query_arg('factory_id', $factory->id, home_url('/reservation-calendar/'))); ?>" 
                       class="fpco-btn">予約状況を確認</a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 予約カレンダーショートコード
     */
    public function reservation_calendar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'factory_id' => 0
        ), $atts);
        
        $factory_id = intval($atts['factory_id']);
        
        // URLパラメータからfactory_idを取得（ショートコード属性より優先）
        if (isset($_GET['factory_id'])) {
            $factory_id = intval($_GET['factory_id']);
        }
        
        if (!$factory_id) {
            return '<p>工場が指定されていません。</p>';
        }
        
        global $wpdb;
        $factory = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}factorys WHERE id = %d",
                $factory_id
            )
        );
        
        if (!$factory) {
            return '<p>指定された工場が見つかりません。</p>';
        }
        
        ob_start();
        ?>
        <div class="fpco-reservation-calendar">
            <h2><?php echo esc_html($factory->name); ?>の予約状況</h2>
            <p>定員: <?php echo esc_html($factory->capacity); ?>名</p>
            
            <div id="fpco-calendar" data-factory-id="<?php echo $factory_id; ?>"></div>
            
            <div class="fpco-calendar-legend">
                <div class="legend-item">
                    <span class="legend-color available"></span>
                    <span class="legend-text">予約可能</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color partial"></span>
                    <span class="legend-text">一部予約済み</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color full"></span>
                    <span class="legend-text">満員</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color unavailable"></span>
                    <span class="legend-text">見学不可</span>
                </div>
            </div>
            
            <div class="fpco-calendar-actions">
                <a href="<?php echo esc_url(add_query_arg('factory_id', $factory_id, home_url('/reservation-form/'))); ?>" 
                   class="fpco-btn fpco-btn-primary">予約申込み</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 予約フォームショートコード
     */
    public function reservation_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'factory_id' => 0
        ), $atts);
        
        $factory_id = intval($atts['factory_id']);
        
        // URLパラメータからfactory_idを取得（ショートコード属性より優先）
        if (isset($_GET['factory_id'])) {
            $factory_id = intval($_GET['factory_id']);
        }
        
        if (!$factory_id) {
            return '<p>工場が指定されていません。</p>';
        }
        
        global $wpdb;
        $factory = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}factorys WHERE id = %d",
                $factory_id
            )
        );
        
        if (!$factory) {
            return '<p>指定された工場が見つかりません。</p>';
        }
        
        ob_start();
        ?>
        <div class="fpco-reservation-form">
            <h2><?php echo esc_html($factory->name); ?>の見学予約</h2>
            
            <form method="post" action="<?php echo esc_url(home_url('/reservation-confirm/')); ?>">
                <input type="hidden" name="factory_id" value="<?php echo $factory_id; ?>">
                
                <div class="form-group">
                    <label for="date">見学希望日 <span class="required">*</span></label>
                    <input type="date" name="date" id="date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="timeslot">時間帯 <span class="required">*</span></label>
                    <select name="timeslot" id="timeslot" required>
                        <option value="">選択してください</option>
                        <option value="am-60-1">AM (09:00-10:00)</option>
                        <option value="am-60-2">AM (10:30-11:30)</option>
                        <option value="pm-60-1">PM (14:00-15:00)</option>
                        <option value="pm-60-2">PM (15:30-16:30)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="applicant_name">申込者氏名 <span class="required">*</span></label>
                    <input type="text" name="applicant_name" id="applicant_name" required>
                </div>
                
                <div class="form-group">
                    <label for="applicant_name_kana">申込者氏名（カナ）</label>
                    <input type="text" name="applicant_name_kana" id="applicant_name_kana">
                </div>
                
                <div class="form-group">
                    <label for="email">メールアドレス <span class="required">*</span></label>
                    <input type="email" name="email" id="email" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">電話番号</label>
                    <input type="tel" name="phone" id="phone">
                </div>
                
                <div class="form-group">
                    <label for="mobile">携帯電話番号</label>
                    <input type="tel" name="mobile" id="mobile">
                </div>
                
                <div class="form-group">
                    <label for="visitor_category">見学者分類 <span class="required">*</span></label>
                    <select name="visitor_category" id="visitor_category" required>
                        <option value="">選択してください</option>
                        <option value="school">学校関係</option>
                        <option value="recruit">就職活動</option>
                        <option value="family">家族</option>
                        <option value="company">企業関係</option>
                        <option value="government">行政関係</option>
                        <option value="other">その他</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="purpose">見学目的</label>
                    <textarea name="purpose" id="purpose" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="fpco-btn fpco-btn-primary">入力内容を確認</button>
                    <a href="<?php echo esc_url(add_query_arg('factory_id', $factory_id, home_url('/reservation-calendar/'))); ?>" 
                       class="fpco-btn fpco-btn-secondary">戻る</a>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}

// インスタンスを作成
new FPCO_Shortcodes();