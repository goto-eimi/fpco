<?php
/**
 * Plugin Name: Factory User Management
 * Description: ユーザー編集画面に工場管理機能を追加
 * Version: 1.0
 */

// プラグインの直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ユーザー編集画面にカスタムフィールドを追加
 */
add_action('show_user_profile', 'factory_add_user_fields');
add_action('edit_user_profile', 'factory_add_user_fields');

function factory_add_user_fields($user) {
    $current_user = wp_get_current_user();
    
    // 管理者の場合は常に表示
    if (current_user_can('manage_options')) {
        // 管理者は全ユーザーの情報を編集可能
    } 
    // 工場ユーザーの場合は自分のプロフィールのみ表示
    else if (in_array('factory', $current_user->roles) && $current_user->ID == $user->ID) {
        // 工場ユーザーは自分の情報のみ閲覧可能
    } 
    // それ以外は表示しない
    else {
        return;
    }
    
    global $wpdb;
    
    // 工場データを取得
    $factories = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}factorys ORDER BY name ASC");
    
    // 他のユーザーに割り当てられている工場IDを取得（編集対象のユーザー以外）
    $assigned_factories = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}factorys 
             WHERE manager_user_id IS NOT NULL 
             AND manager_user_id != %d 
             AND manager_user_id != 0",
            $user->ID
        )
    );
    
    // 編集対象ユーザーの工場割り当てを取得
    $current_factory = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}factorys WHERE manager_user_id = %d",
        $user->ID
    ));
    
    // 編集対象ユーザーの設定を取得
    $max_participants = get_user_meta($user->ID, 'max_participants', true);
    
    // 工場の見学時間帯を取得
    $factory_timeslots = '';
    $timeslots = array('am' => [], 'pm' => []);
    
    if ($current_factory) {
        $factory_timeslots = $wpdb->get_var($wpdb->prepare(
            "SELECT time_slots FROM {$wpdb->prefix}factorys WHERE id = %d",
            $current_factory
        ));
        
        if ($factory_timeslots) {
            $timeslots = unserialize($factory_timeslots);
        }
    }
    
    // デフォルト値の設定
    if (empty($timeslots['am'])) {
        $timeslots['am'] = ['9:00 - 10:00', '9:30 - 10:30', '11:00 - 12:00'];
    }
    if (empty($timeslots['pm'])) {
        $timeslots['pm'] = ['14:00 - 15:00', '14:30 - 15:30', '16:00 - 17:00'];
    }
    
    // 工場ユーザーかどうかをチェック（現在ログインしているユーザーが工場ユーザーかどうか）
    $is_factory_user = in_array('factory', $current_user->roles) && !current_user_can('manage_options');
    
    // 編集対象ユーザーの工場名を取得
    $factory_name = '';
    if ($current_factory) {
        $factory_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}factorys WHERE id = %d",
            $current_factory
        ));
    }
    ?>
    
    <table class="form-table">
        <!-- 工場 -->
        <tr>
            <th><label for="assigned_factory">工場</label></th>
            <td>
                <?php if ($is_factory_user): ?>
                    <!-- 工場ユーザーは閲覧のみ -->
                    <span><?php echo esc_html($factory_name ?: '未設定'); ?></span>
                <?php else: ?>
                    <!-- 管理者は編集可能 -->
                    <select name="assigned_factory" id="assigned_factory">
                        <option value="">選択してください</option>
                        <?php foreach ($factories as $factory) : ?>
                            <?php 
                            // 他のユーザーに割り当てられている工場は表示しない（現在のユーザーに割り当てられている場合は表示）
                            if (in_array($factory->id, $assigned_factories) && $current_factory != $factory->id) {
                                continue;
                            }
                            $selected = ($current_factory == $factory->id) ? 'selected' : '';
                            ?>
                            <option value="<?php echo esc_attr($factory->id); ?>" <?php echo $selected; ?>>
                                <?php echo esc_html($factory->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </td>
        </tr>
        
        <!-- 予約可能人数 -->
        <tr>
            <th><label for="max_participants">予約可能人数</label></th>
            <td>
                <?php if ($is_factory_user): ?>
                    <!-- 工場ユーザーは閲覧のみ -->
                    <span><?php echo esc_html($max_participants ?: 50); ?></span>
                <?php else: ?>
                    <!-- 管理者は編集可能 -->
                    <input type="number" 
                           name="max_participants" 
                           id="max_participants" 
                           value="<?php echo esc_attr($max_participants ?: 50); ?>" 
                           min="1" 
                           style="width: 80px;" />
                <?php endif; ?>
            </td>
        </tr>
        
        <!-- 見学時間帯 -->
        <tr>
            <th><label>見学時間帯</label></th>
            <td>
                <?php if ($is_factory_user): ?>
                    <!-- 工場ユーザーは閲覧のみ -->
                    <div>
                        <strong>AM</strong><br>
                        <?php foreach ($timeslots['am'] as $slot) : ?>
                            <?php echo esc_html($slot); ?><br>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 15px;">
                        <strong>PM</strong><br>
                        <?php foreach ($timeslots['pm'] as $slot) : ?>
                            <?php echo esc_html($slot); ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- 管理者は編集可能 -->
                    <div>
                        <strong>AM</strong><br>
                        <div id="am_timeslots">
                            <?php foreach ($timeslots['am'] as $index => $slot) : ?>
                                <div class="timeslot-row" style="margin-bottom: 5px;">
                                    <input type="text" name="am_timeslots[]" value="<?php echo esc_attr($slot); ?>" style="width: 150px;">
                                    <button type="button" class="button remove-timeslot" onclick="removeTimeslot(this)">削除</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button" onclick="addTimeslot('am')">AM時間帯を追加</button>
                    </div>
                    <div style="margin-top: 15px;">
                        <strong>PM</strong><br>
                        <div id="pm_timeslots">
                            <?php foreach ($timeslots['pm'] as $index => $slot) : ?>
                                <div class="timeslot-row" style="margin-bottom: 5px;">
                                    <input type="text" name="pm_timeslots[]" value="<?php echo esc_attr($slot); ?>" style="width: 150px;">
                                    <button type="button" class="button remove-timeslot" onclick="removeTimeslot(this)">削除</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button" onclick="addTimeslot('pm')">PM時間帯を追加</button>
                    </div>
                    
                    <script>
                    function addTimeslot(period) {
                        var container = document.getElementById(period + '_timeslots');
                        var div = document.createElement('div');
                        div.className = 'timeslot-row';
                        div.style.marginBottom = '5px';
                        div.innerHTML = '<input type="text" name="' + period + '_timeslots[]" value="" style="width: 150px;" placeholder="例: 9:00 - 10:00">' +
                                       '<button type="button" class="button remove-timeslot" onclick="removeTimeslot(this)">削除</button>';
                        container.appendChild(div);
                    }
                    
                    function removeTimeslot(button) {
                        button.parentNode.remove();
                    }
                    </script>
                <?php endif; ?>
            </td>
        </tr>
        
        <!-- 名前 -->
        <tr>
            <th><label>名前</label></th>
            <td>
                <div>
                    ユーザー名　　<input type="text" value="<?php echo esc_attr($user->user_login); ?>" readonly style="background-color: #f0f0f0;" />
                    ユーザー名は変更できません。
                </div>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * ユーザー情報を保存
 */
add_action('personal_options_update', 'factory_save_user_fields');
add_action('edit_user_profile_update', 'factory_save_user_fields');

function factory_save_user_fields($user_id) {
    // 管理者権限チェック
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    global $wpdb;
    
    // 工場割当の保存
    if (isset($_POST['assigned_factory'])) {
        $factory_id = absint($_POST['assigned_factory']);
        
        if ($factory_id > 0) {
            // 他のユーザーに既に割り当てられていないかチェック
            $existing_manager = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT manager_user_id FROM {$wpdb->prefix}factorys 
                     WHERE id = %d 
                     AND manager_user_id IS NOT NULL 
                     AND manager_user_id != %d 
                     AND manager_user_id != 0",
                    $factory_id,
                    $user_id
                )
            );
            
            if (!$existing_manager) {
                // 現在のユーザーに割り当てられている工場があれば先にクリア
                $wpdb->update(
                    $wpdb->prefix . 'factorys',
                    array('manager_user_id' => null),
                    array('manager_user_id' => $user_id),
                    array('%d'),
                    array('%d')
                );
                
                // 新しい工場にmanager_user_idを設定
                $wpdb->update(
                    $wpdb->prefix . 'factorys',
                    array('manager_user_id' => $user_id),
                    array('id' => $factory_id),
                    array('%d'),
                    array('%d')
                );
                
                // 後方互換性のためuser_metaも更新
                update_user_meta($user_id, 'assigned_factory', $factory_id);
            }
        } else {
            // 割り当て解除
            $wpdb->update(
                $wpdb->prefix . 'factorys',
                array('manager_user_id' => null),
                array('manager_user_id' => $user_id),
                array('%d'),
                array('%d')
            );
            
            // 後方互換性のためuser_metaもクリア
            delete_user_meta($user_id, 'assigned_factory');
        }
    }
    
    // 予約可能人数の保存
    if (isset($_POST['max_participants'])) {
        $max_participants = absint($_POST['max_participants']);
        if ($max_participants > 0 && $max_participants <= 999) {
            update_user_meta($user_id, 'max_participants', $max_participants);
        }
    }
    
    // 見学時間帯の保存（工場のtime_slotsカラムに保存）
    if (isset($_POST['am_timeslots']) || isset($_POST['pm_timeslots'])) {
        // 現在のユーザーが管理する工場を取得
        $user_factory = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}factorys WHERE manager_user_id = %d",
            $user_id
        ));
        
        if ($user_factory) {
            $timeslots = array(
                'am' => array(),
                'pm' => array()
            );
            
            // AM時間帯の処理
            if (isset($_POST['am_timeslots']) && is_array($_POST['am_timeslots'])) {
                foreach ($_POST['am_timeslots'] as $slot) {
                    $slot = sanitize_text_field($slot);
                    if (!empty($slot)) {
                        $timeslots['am'][] = $slot;
                    }
                }
            }
            
            // PM時間帯の処理
            if (isset($_POST['pm_timeslots']) && is_array($_POST['pm_timeslots'])) {
                foreach ($_POST['pm_timeslots'] as $slot) {
                    $slot = sanitize_text_field($slot);
                    if (!empty($slot)) {
                        $timeslots['pm'][] = $slot;
                    }
                }
            }
            
            // 工場のtime_slotsカラムを更新
            $wpdb->update(
                $wpdb->prefix . 'factorys',
                array('time_slots' => serialize($timeslots)),
                array('id' => $user_factory),
                array('%s'),
                array('%d')
            );
        }
    }
}