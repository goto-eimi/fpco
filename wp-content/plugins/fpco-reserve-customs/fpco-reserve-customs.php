<?php
/*
* Plugin Name: fpco reserve customs
*/

function fpco_reserve_customs_activate()
{
    // ユーザー権限グループ(工場アカウント)追加
    add_role(
        'factory',
        '工場アカウント',
        array(
            'read'         => true,
            'edit_posts'   => true,
            'delete_posts' => true,
            'publish_posts' => true,
            'upload_files' => true,
            'edit_published_posts' => true,
            'delete_published_posts' => true,
            'edit_others_posts' => true,
            'delete_others_posts' => true,
            'create_posts' => true,
            'manage_categories' => true, // カテゴリー管理
            'moderate_comments' => true, // コメント moderation
            'unfiltered_html' => true, // HTMLのアンフィルター
        )
    );
}
register_activation_hook( __FILE__, 'fpco_reserve_customs_activate' );

function fpco_reserve_customs_deactivate()
{
    remove_role('factory');
}
register_deactivation_hook( __FILE__, 'fpco_reserve_customs_deactivate' );

// ユーザー編集画面に見学時間帯を追加
function change_user_profile_fields($user){
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
        </tbody>
    </table>
    <?php
}
add_action('show_user_profile', 'change_user_profile_fields');
add_action('edit_user_profile', 'change_user_profile_fields');