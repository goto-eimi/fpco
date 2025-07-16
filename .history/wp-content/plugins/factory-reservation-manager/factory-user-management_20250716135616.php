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

// 工場ごとの見学時間帯設定
function get_factory_timeslots($factory_id) {
    $factory_timeslots = array(
        // 関東リサイクル工場
        '1' => array(
            'am' => ['9:00 - 10:00', '9:30 - 10:30', '10:00 - 11:00'],
            'pm' => ['13:00 - 14:00', '13:30 - 14:30', '14:00 - 15:00']
        ),
        // 中部リサイクル工場
        '2' => array(
            'am' => ['9:00 - 10:30', '10:30 - 12:00'],
            'pm' => ['14:00 - 15:30', '15:30 - 17:00']
        ),
        // 福山リサイクル工場
        '3' => array(
            'am' => ['8:30 - 9:30', '9:30 - 10:30', '10:30 - 11:30'],
            'pm' => ['13:30 - 14:30', '14:30 - 15:30', '15:30 - 16:30']
        ),
        // 山形選別センター
        '4' => array(
            'am' => ['8:30 - 9:30', '9:30 - 10:30', '10:30 - 11:30'],
            'pm' => ['13:30 - 14:30', '14:30 - 15:30', '15:30 - 16:30']
        ),
        // 松本選別センター
        '5' => array(
            'am' => ['8:30 - 9:30', '9:30 - 10:30', '10:30 - 11:30'],
            'pm' => ['13:30 - 14:30', '14:30 - 15:30', '15:30 - 16:30']
        ),
        // 西宮選別センター
        '6' => array(
            'am' => ['8:30 - 9:30', '9:30 - 10:30', '10:30 - 11:30'],
            'pm' => ['13:30 - 14:30', '14:30 - 15:30', '15:30 - 16:30']
        ),
        // 東海選別センター
        '7' => array(
            'am' => ['8:30 - 9:30', '9:30 - 10:30', '10:30 - 11:30'],
            'pm' => ['13:30 - 14:30', '14:30 - 15:30', '15:30 - 16:30']
        ),
        // 金沢選別センター
        '8' => array(
            'am' => ['8:30 - 9:30', '9:30 - 10:30', '10:30 - 11:30'],
            'pm' => ['13:30 - 14:30', '14:30 - 15:30', '15:30 - 16:30']
        ),
        // 九州選別センター
        '9' => array(
            'am' => ['8:30 - 9:30', '9:30 - 10:30', '10:30 - 11:30'],
            'pm' => ['13:30 - 14:30', '14:30 - 15:30', '15:30 - 16:30']
        )
    );
    
    // デフォルト時間帯
    $default = array(
        'am' => ['9:00 - 10:00', '9:30 - 10:30', '11:00 - 12:00'],
        'pm' => ['14:00 - 15:00', '14:30 - 15:30', '16:00 - 17:00']
    );
    
    // 指定された工場IDの時間帯を返す（なければデフォルト）
    return isset($factory_timeslots[$factory_id]) ? $factory_timeslots[$factory_id] : $default;
}

function factory_add_user_fields($user) {
    // 権限チェック：管理者または自分自身のプロフィール表示
    if (!current_user_can('manage_options') && get_current_user_id() != $user->ID) {
        return;
    }
    
    global $wpdb;
    
    // 工場データを取得
    $factories = $wpdb->get_results("SELECT id, name FROM wp_factorys ORDER BY name ASC");
    
    // 管理者（ユーザーID：1、admin）の場合は制限なし
    $assigned_factories = array();
    if ($user->ID != 1 && $user->user_login != 'admin') {
        // 他のユーザーに割り当てられている工場IDを取得
    $assigned_factories = $wpdb->get_col(
        $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->usermeta} 
                 WHERE meta_key = 'assigned_factory' 
                 AND user_id != %d",
            $user->ID
        )
    );
    }
    
    // 現在のユーザーの設定を取得
    $current_factory = get_user_meta($user->ID, 'assigned_factory', true);
    
    // ユーザーが管理者かどうかを判定
    $is_admin = ($user->ID == 1 || $user->user_login == 'admin' || current_user_can('manage_options'));
    
    // 工場アカウント（子アカウント）かどうかを判定
    $is_factory_account = !$is_admin && $current_factory;
    
    // 工場が選択されている場合は、その工場のcapacityと名前を取得
    $max_participants = '';
    $factory_name = '';
    
    if ($current_factory) {
        $factory_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT capacity, name FROM wp_factorys WHERE id = %d",
            $current_factory
            )
        );
        if ($factory_data) {
            if ($factory_data->capacity !== null) {
                $max_participants = $factory_data->capacity;
    }
            $factory_name = $factory_data->name;
        }
        
        // 工場IDに基づいて時間帯を取得
        $timeslots = get_factory_timeslots($current_factory);
    } else {
        // デフォルト時間帯
        $timeslots = get_factory_timeslots(null);
    }
    ?>
    
    <?php if ($is_factory_account): ?>
        <h2><?php echo esc_html($factory_name); ?></h2>
    <?php endif; ?>
    
    <table class="form-table">
        <?php if (!$is_factory_account): ?>
            <!-- 管理者用：工場選択 -->
        <tr>
                <th><label for="assigned_factory">担当工場</label></th>
            <td>
                    <select name="assigned_factory" id="assigned_factory">
                        <option value="">選択してください</option>
                        <?php foreach ($factories as $factory) : ?>
                            <?php 
                            // 他のユーザーに割り当てられている工場は表示しない
                            if (in_array($factory->id, $assigned_factories)) {
                                continue;
                            }
                            
                            $selected = ($current_factory == $factory->id) ? 'selected' : '';
                            ?>
                            <option value="<?php echo esc_attr($factory->id); ?>" 
                                    <?php echo $selected; ?>>
                                <?php echo esc_html($factory->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php else: ?>
            <!-- 工場アカウント用：工場名表示のみ -->
            <tr>
                <th><label>担当工場</label></th>
                <td>
                    <strong><?php echo esc_html($factory_name); ?></strong>
                    <input type="hidden" name="assigned_factory" value="<?php echo esc_attr($current_factory); ?>" />
                    <p class="description">担当工場は管理者のみが変更できます。</p>
            </td>
        </tr>
        <?php endif; ?>
        
        <!-- 予約可能人数 -->
        <tr>
            <th><label for="max_participants">予約可能人数</label></th>
            <td>
                <?php if (!$is_factory_account): ?>
                    <!-- 管理者用：編集可能 -->
                    <?php if ($current_factory): ?>
                    <input type="number" 
                           name="max_participants" 
                           id="max_participants" 
                               value="<?php echo esc_attr($max_participants); ?>" 
                           min="1" 
                           style="width: 80px;" />
                        <p class="description">選択された工場の予約可能人数を設定します。</p>
                    <?php else: ?>
                        <input type="number" 
                               name="max_participants" 
                               id="max_participants" 
                               value="<?php echo esc_attr($max_participants); ?>" 
                               min="1" 
                               style="width: 80px;" 
                               disabled />
                        <p class="description">工場を選択してから予約可能人数を設定してください。</p>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- 工場アカウント用：表示のみ -->
                    <strong><?php echo esc_html($max_participants ? $max_participants : '未設定'); ?>名</strong>
                    <p class="description">予約可能人数は管理者のみが変更できます。</p>
                <?php endif; ?>
            </td>
        </tr>
        
        <!-- 見学時間帯 -->
        <tr>
            <th><label>見学時間帯</label></th>
            <td id="timeslots-container">
                    <div>
                        <strong>AM</strong><br>
                    <div id="am-timeslots">
                        <?php foreach ($timeslots['am'] as $slot) : ?>
                            <?php echo esc_html($slot); ?><br>
                        <?php endforeach; ?>
                    </div>
                </div>
                    <div style="margin-top: 15px;">
                        <strong>PM</strong><br>
                    <div id="pm-timeslots">
                        <?php foreach ($timeslots['pm'] as $slot) : ?>
                            <?php echo esc_html($slot); ?><br>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($current_factory): ?>
                    <p class="description">選択された工場の見学時間帯です。</p>
                <?php else: ?>
                    <p class="description">工場を選択すると見学時間帯が表示されます。</p>
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
    // 管理者のみが編集可能
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