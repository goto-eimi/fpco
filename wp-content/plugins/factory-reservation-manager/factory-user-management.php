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
        <?php if ($is_admin): ?>
            <!-- 管理者のみ工場選択可能 -->
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
            <!-- 非管理者用：工場名表示のみ -->
            <tr>
                <th><label>担当工場</label></th>
                <td>
                    <?php if ($current_factory): ?>
                        <strong><?php echo esc_html($factory_name); ?></strong>
                        <input type="hidden" name="assigned_factory" value="<?php echo esc_attr($current_factory); ?>" />
                    <?php else: ?>
                        <strong>未割り当て</strong>
                    <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
        
        <!-- 予約可能人数 -->
        <tr>
            <th><label for="max_participants">予約可能人数</label></th>
            <td>
                <?php if ($is_admin): ?>
                    <!-- 管理者のみ編集可能 -->
                    <?php if ($current_factory): ?>
                    <input type="number" 
                           name="max_participants" 
                           id="max_participants" 
                               value="<?php echo esc_attr($max_participants); ?>" 
                           min="1" 
                           style="width: 80px;" />
                    <?php else: ?>
                        <input type="number" 
                               name="max_participants" 
                               id="max_participants" 
                               value="<?php echo esc_attr($max_participants); ?>" 
                               min="1" 
                               style="width: 80px;" 
                               disabled />
                    <?php endif; ?>
                <?php else: ?>
                    <!-- 非管理者用：表示のみ -->
                    <strong><?php echo esc_html($max_participants ? $max_participants : '未設定'); ?>名</strong>
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
                <?php if (!$current_factory): ?>
                    <p class="description">工場が割り当てられると見学時間帯が表示されます。</p>
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
    
    // トランザクション開始
    $wpdb->query('START TRANSACTION');
    
    // 工場割当の保存
    if (isset($_POST['assigned_factory'])) {
        $factory_id = absint($_POST['assigned_factory']);
        
        // 現在のユーザーの既存の工場割当を取得
        $current_factory = get_user_meta($user_id, 'assigned_factory', true);
        
        if ($factory_id > 0) {
            // 他のユーザーに既に割り当てられていないかチェック
            $existing_user = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT user_id FROM {$wpdb->usermeta} 
                     WHERE meta_key = 'assigned_factory' 
                     AND meta_value = %s 
                     AND user_id != %d",
                    $factory_id,
                    $user_id
                )
            );
            
            if (!$existing_user) {
                // 既存の工場割当がある場合は、その工場のmanager_user_idをNULLに設定
                if ($current_factory && $current_factory != $factory_id) {
                    $result = $wpdb->update(
                        'wp_factorys',
                    array('manager_user_id' => null),
                        array('id' => $current_factory)
                );
                
                    if ($result === false) {
                        $wpdb->query('ROLLBACK');
                        return false;
                    }
                }
                
                // 新しい工場にユーザーを割り当て
                $meta_result = update_user_meta($user_id, 'assigned_factory', $factory_id);
                
                // wp_factorysテーブルのmanager_user_idを更新
                $factory_result = $wpdb->update(
                    'wp_factorys',
                    array('manager_user_id' => $user_id),
                    array('id' => $factory_id)
                );
                
                if ($factory_result === false) {
                    $wpdb->query('ROLLBACK');
                    return false;
                }
            }
        } else {
            // 工場割当を削除
            delete_user_meta($user_id, 'assigned_factory');
            
            // 既存の工場割当がある場合は、その工場のmanager_user_idをNULLに設定
            if ($current_factory) {
                $result = $wpdb->update(
                    'wp_factorys',
                    array('manager_user_id' => null),
                    array('id' => $current_factory)
                );
                
                if ($result === false) {
                    $wpdb->query('ROLLBACK');
                    return false;
                }
            }
        }
    }
    
    // 予約可能人数の保存
    if (isset($_POST['max_participants'])) {
        $max_participants = absint($_POST['max_participants']);
        if ($max_participants > 0 && $max_participants <= 999) {
            // 現在のユーザーが割り当てられている工場を取得
            $assigned_factory = get_user_meta($user_id, 'assigned_factory', true);
            
            if ($assigned_factory) {
                // wp_factorysテーブルのcapacityを更新
                $capacity_result = $wpdb->update(
                    'wp_factorys',
                    array('capacity' => $max_participants),
                    array('id' => $assigned_factory)
                );
                
                if ($capacity_result === false) {
                    $wpdb->query('ROLLBACK');
                    return false;
                }
            }
            
            // 念のため、ユーザーメタも更新（後方互換性のため）
            update_user_meta($user_id, 'max_participants', $max_participants);
        }
    }
    
    // トランザクション完了
    $wpdb->query('COMMIT');
    
    return true;
}

/**
 * Ajax処理：工場のcapacityを取得
 */
add_action('wp_ajax_get_factory_capacity', 'get_factory_capacity_ajax');
add_action('wp_ajax_nopriv_get_factory_capacity', 'get_factory_capacity_ajax');

function get_factory_capacity_ajax() {
    // 権限チェック：管理者のみ
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', '', array('response' => 403));
    }
    
    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'], 'factory_capacity_nonce')) {
        wp_die('Invalid nonce', '', array('response' => 403));
    }
    
    $factory_id = absint($_POST['factory_id']);
    
    if ($factory_id <= 0) {
        wp_send_json_error(array('message' => 'Invalid factory ID'));
    }
    
    global $wpdb;
    
    $capacity = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT capacity FROM wp_factorys WHERE id = %d",
            $factory_id
        )
    );
        
    if ($capacity === null) {
        wp_send_json_error(array('message' => 'Factory not found'));
    }
    
    // 工場IDに基づいて時間帯を取得
    $timeslots = get_factory_timeslots($factory_id);
    
    wp_send_json_success(array(
        'capacity' => $capacity,
        'timeslots' => $timeslots
    ));
}

/**
 * 管理画面でのJavaScript/CSSの読み込み
 */
add_action('admin_enqueue_scripts', 'factory_user_management_scripts');

function factory_user_management_scripts($hook) {
    // ユーザー編集画面でのみ読み込み
    if ($hook !== 'profile.php' && $hook !== 'user-edit.php') {
        return;
    }
    
    wp_enqueue_script('jquery');
    
    // インラインスクリプトを追加
    $script = '
    jQuery(document).ready(function($) {
        $("#assigned_factory").on("change", function() {
            var factoryId = $(this).val();
            var $participantsField = $("#max_participants");
            var $description = $participantsField.next(".description");
            
            if (factoryId) {
                // Ajax呼び出し
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "get_factory_capacity",
                        factory_id: factoryId,
                        nonce: "' . wp_create_nonce('factory_capacity_nonce') . '"
                    },
                    beforeSend: function() {
                        $participantsField.prop("disabled", true);
                        $description.text("読み込み中...");
                    },
                    success: function(response) {
                        if (response.success) {
                            // 予約可能人数を更新
                            $participantsField.val(response.data.capacity);
                            $participantsField.prop("disabled", false);
                            $description.text("");
                            
                            // 見学時間帯を更新
                            updateTimeslots(response.data.timeslots);
                        } else {
                            $participantsField.val(50);
                            $participantsField.prop("disabled", false);
                            $description.text("エラー: " + response.data.message);
                        }
                    },
                    error: function() {
                        $participantsField.val(50);
                        $participantsField.prop("disabled", false);
                        $description.text("エラー: サーバーとの通信に失敗しました。");
                    }
                });
            } else {
                // 工場が選択されていない場合
                $participantsField.val(50);
                $participantsField.prop("disabled", true);
                $description.text("");
                
                // 見学時間帯をデフォルト値に戻す
                resetTimeslots();
            }
        });
        
        // 見学時間帯を更新する関数（読み取り専用）
        function updateTimeslots(timeslots) {
            var $container = $("#timeslots-container");
            var html = "";
            
            html += "<div>";
            html += "<strong>AM</strong><br>";
            html += "<div id=\"am-timeslots\">";
            for (var i = 0; i < timeslots.am.length; i++) {
                html += timeslots.am[i] + "<br>";
            }
            html += "</div>";
            html += "</div>";
            
            html += "<div style=\"margin-top: 15px;\">";
            html += "<strong>PM</strong><br>";
            html += "<div id=\"pm-timeslots\">";
            for (var i = 0; i < timeslots.pm.length; i++) {
                html += timeslots.pm[i] + "<br>";
            }
            html += "</div>";
            html += "</div>";
            
            
            $container.html(html);
            }
            
        // 見学時間帯をデフォルト値に戻す関数
        function resetTimeslots() {
            var $container = $("#timeslots-container");
            var defaultTimeslots = {
                am: ["9:00 - 10:00", "9:30 - 10:30", "11:00 - 12:00"],
                pm: ["14:00 - 15:00", "14:30 - 15:30", "16:00 - 17:00"]
            };
            
            var html = "";
            html += "<div>";
            html += "<strong>AM</strong><br>";
            html += "<div id=\"am-timeslots\">";
            for (var i = 0; i < defaultTimeslots.am.length; i++) {
                html += defaultTimeslots.am[i] + "<br>";
            }
            html += "</div>";
            html += "</div>";
            
            html += "<div style=\"margin-top: 15px;\">";
            html += "<strong>PM</strong><br>";
            html += "<div id=\"pm-timeslots\">";
            for (var i = 0; i < defaultTimeslots.pm.length; i++) {
                html += defaultTimeslots.pm[i] + "<br>";
            }
            html += "</div>";
            html += "</div>";
            
            html += "<p class=\"description\">工場が割り当てられると見学時間帯が表示されます。</p>";
            
            $container.html(html);
        }
    });
    ';
    
    wp_add_inline_script('jquery', $script);
}