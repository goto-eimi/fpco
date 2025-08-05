<?php
/**
 * ユーザー管理クラス
 * 
 * 工場アカウント用のユーザーロール管理と
 * ユーザープロフィール項目の追加機能を提供
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPCO_User_Management {
    
    public function __construct() {
        // ユーザープロフィール項目の追加
        add_action('show_user_profile', array($this, 'add_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_user_profile_fields'));
        
        // プロフィール項目の保存
        add_action('personal_options_update', array($this, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile_fields'));
    }
    
    /**
     * ユーザープロフィールに見学時間帯を追加
     */
    public function add_user_profile_fields($user) {
        $timeArr = [
            'am' => [
                '9:00 ~ 10:00',
                '9:30 ~ 10:30',
                '10:00 ~ 11:00',
                '10:30 ~ 11:30',
            ],
            'pm' => [
                '12:00 ~ 13:00',
                '12:30 ~ 13:30',
                '13:00 ~ 14:00',
                '13:30 ~ 14:30',
            ]
        ];
        
        // 割り当て工場の取得
        $assigned_factory = get_user_meta($user->ID, 'assigned_factory', true);
        ?>
        <table class="form-table">
            <tbody>
            <tr class="acf-field">
                <td class="acf-label"><label>見学時間帯</label></td>
                <td class="acf-input">
                    AM<br>
                    <?php foreach ($timeArr['am'] as $time): ?>
                        <?php echo $time; ?><br>
                    <?php endforeach; ?>
                    <br>PM<br>
                    <?php foreach ($timeArr['pm'] as $time): ?>
                        <?php echo $time; ?><br>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php if (current_user_can('manage_options')): ?>
            <tr class="acf-field">
                <td class="acf-label">
                    <label for="assigned_factory">割り当て工場</label>
                </td>
                <td class="acf-input">
                    <select name="assigned_factory" id="assigned_factory">
                        <option value="">選択してください</option>
                        <?php
                        global $wpdb;
                        $factories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}factorys ORDER BY name ASC");
                        foreach ($factories as $factory):
                        ?>
                            <option value="<?php echo $factory->id; ?>" <?php selected($assigned_factory, $factory->id); ?>>
                                <?php echo esc_html($factory->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">このユーザーが管理する工場を選択してください。</p>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * ユーザープロフィール項目の保存
     */
    public function save_user_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        // 割り当て工場の保存（管理者のみ）
        if (current_user_can('manage_options') && isset($_POST['assigned_factory'])) {
            update_user_meta($user_id, 'assigned_factory', sanitize_text_field($_POST['assigned_factory']));
        }
    }
}

// インスタンスを作成
new FPCO_User_Management();