/**
 * Factory Reservation Manager - Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // フォームバリデーション
    function validateForm() {
        var isValid = true;
        var errors = [];
        
        // 必須フィールドのチェック
        $('.form-input[required], .form-select[required]').each(function() {
            var $field = $(this);
            var value = $field.val().trim();
            
            if (!value) {
                $field.addClass('error');
                isValid = false;
                
                var label = $field.closest('.form-field').find('.form-label').text().replace('*', '').trim();
                errors.push(label + 'は必須項目です。');
            } else {
                $field.removeClass('error');
            }
        });
        
        // 旅行会社情報のバリデーション
        if ($('#travel_agency_yes').is(':checked')) {
            var travelRequiredFields = [
                'travel_agency_name',
                'travel_agency_zip',
                'travel_agency_prefecture', 
                'travel_agency_city',
                'travel_agency_phone',
                'contact_email'
            ];
            
            travelRequiredFields.forEach(function(fieldName) {
                var $field = $('[name="' + fieldName + '"]');
                var value = $field.val().trim();
                
                if (!value) {
                    $field.addClass('error');
                    isValid = false;
                    
                    var label = $field.closest('.form-field').find('.form-label').text().replace('*', '').trim();
                    errors.push(label + 'は必須項目です。');
                } else {
                    $field.removeClass('error');
                }
            });
        }
        
        // 予約タイプ別のバリデーション
        var reservationType = $('input[name="reservation_type"]:checked').val();
        
        if (reservationType === 'school') {
            var schoolRequiredFields = [
                'school_name',
                'school_name_kana',
                'grade',
                'class_count',
                'student_count',
                'supervisor_count'
            ];
            
            schoolRequiredFields.forEach(function(fieldName) {
                var $field = $('[name="' + fieldName + '"]');
                var value = $field.val().trim();
                
                if (!value) {
                    $field.addClass('error');
                    isValid = false;
                }
            });
        }
        
        if (reservationType === 'student_recruit') {
            var recruitRequiredFields = [
                'recruit_school_name',
                'recruit_department', 
                'recruit_grade',
                'recruit_visitor_count'
            ];
            
            recruitRequiredFields.forEach(function(fieldName) {
                var $field = $('[name="' + fieldName + '"]');
                var value = $field.val().trim();
                
                if (!value) {
                    $field.addClass('error');
                    isValid = false;
                }
            });
            
            // 同行者フィールドのバリデーション
            var visitorCount = parseInt($('#recruit_visitor_count').val()) || 0;
            if (visitorCount > 1) {
                for (var i = 1; i < visitorCount; i++) {
                    var companionName = $('[name="companion_name_' + i + '"]').val().trim();
                    var companionDept = $('[name="companion_department_' + i + '"]').val().trim();
                    
                    if (!companionName || !companionDept) {
                        isValid = false;
                        errors.push('同行者' + i + 'の情報を入力してください。');
                    }
                }
            }
        }
        
        if (['family', 'company', 'municipality', 'other'].includes(reservationType)) {
            var generalRequiredFields = [
                'company_name',
                'company_name_kana',
                'adult_count',
                'child_count'
            ];
            
            generalRequiredFields.forEach(function(fieldName) {
                var $field = $('[name="' + fieldName + '"]');
                var value = $field.val().trim();
                
                if (!value) {
                    $field.addClass('error');
                    isValid = false;
                }
            });
            
            // 子ども人数が1人以上の場合、学年も必須
            var childCount = parseInt($('#child_count').val()) || 0;
            if (childCount >= 1) {
                var childGrade = $('#child_grade').val().trim();
                if (!childGrade) {
                    $('#child_grade').addClass('error');
                    isValid = false;
                    errors.push('学年は必須項目です。');
                }
            }
        }
        
        // メールアドレスの形式チェック
        $('input[type="email"]').each(function() {
            var email = $(this).val().trim();
            if (email && !isValidEmail(email)) {
                $(this).addClass('error');
                isValid = false;
                errors.push('メールアドレスの形式が正しくありません。');
            }
        });
        
        // 電話番号の形式チェック
        $('input[type="tel"]').each(function() {
            var phone = $(this).val().trim();
            if (phone && !isValidPhone(phone)) {
                $(this).addClass('error');
                isValid = false;
                errors.push('電話番号の形式が正しくありません。');
            }
        });
        
        // エラーメッセージの表示
        if (!isValid) {
            var errorMessage = 'エラーがあります:\n\n' + errors.join('\n');
            alert(errorMessage);
        }
        
        return isValid;
    }
    
    // メールアドレスのバリデーション
    function isValidEmail(email) {
        var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }
    
    // 電話番号のバリデーション
    function isValidPhone(phone) {
        var regex = /^[\d\-\(\)\+\s]+$/;
        return regex.test(phone) && phone.replace(/[\D]/g, '').length >= 10;
    }
    
    // フォーム送信時のバリデーション
    $('#register_reservation').on('click', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            return false;
        }
        
        // バリデーション成功時の処理
        var formData = new FormData($('#reservation_form')[0]);
        formData.append('action', 'save_reservation');
        formData.append('status', $('#reservation_status').val());
        formData.append('nonce', ajax_object.nonce);
        
        // ローディング表示
        $(this).prop('disabled', true).text('登録中...');
        
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('予約を登録しました。');
                    location.reload();
                } else {
                    alert('エラー: ' + response.data);
                }
            },
            error: function() {
                alert('通信エラーが発生しました。');
            },
            complete: function() {
                $('#register_reservation').prop('disabled', false).text('登録');
            }
        });
    });
    
    // 返信メール作成ボタン
    $('#create_reply_email').on('click', function() {
        var status = $('#reservation_status').val();
        var applicantName = $('#applicant_name').val();
        var visitDate = $('#visit_date').val();
        var factoryId = $('#factory_id').val();
        
        if (!applicantName || !visitDate || !factoryId) {
            alert('必要な情報が入力されていません。');
            return;
        }
        
        // メールテンプレートの生成
        var emailTemplate = generateEmailTemplate(status, applicantName, visitDate, factoryId);
        
        // メール作成画面を開く
        showEmailModal(emailTemplate);
    });
    
    // メールテンプレートの生成
    function generateEmailTemplate(status, applicantName, visitDate, factoryId) {
        var template = '';
        var factoryName = $('#factory_id option:selected').text();
        
        switch(status) {
            case 'approved':
                template = applicantName + ' 様\n\n';
                template += 'この度は、' + factoryName + 'の工場見学にお申し込みいただき、ありがとうございます。\n\n';
                template += '見学日: ' + visitDate + '\n';
                template += 'ご予約を承認いたしました。\n\n';
                template += '当日は以下の点にご注意ください：\n';
                template += '・受付開始時刻の15分前にお越しください\n';
                template += '・安全のため、ヒールの高い靴はお避けください\n';
                template += '・写真撮影は指定された場所のみ可能です\n\n';
                template += 'ご質問がございましたら、お気軽にお問い合わせください。\n\n';
                template += 'エフピコ 工場見学担当';
                break;
                
            case 'rejected':
                template = applicantName + ' 様\n\n';
                template += 'この度は、' + factoryName + 'の工場見学にお申し込みいただき、ありがとうございます。\n\n';
                template += '見学日: ' + visitDate + '\n';
                template += '申し訳ございませんが、ご希望の日程での見学をお受けできません。\n\n';
                template += '他の日程でのご案内が可能な場合もございますので、\n';
                template += 'お気軽にお問い合わせください。\n\n';
                template += 'エフピコ 工場見学担当';
                break;
                
            case 'checking':
                template = applicantName + ' 様\n\n';
                template += 'この度は、' + factoryName + 'の工場見学にお申し込みいただき、ありがとうございます。\n\n';
                template += '見学日: ' + visitDate + '\n';
                template += '現在、ご予約の確認を行っております。\n\n';
                template += '確認が完了次第、改めてご連絡いたします。\n';
                template += '今しばらくお待ちください。\n\n';
                template += 'エフピコ 工場見学担当';
                break;
                
            default:
                template = applicantName + ' 様\n\n';
                template += 'この度は、' + factoryName + 'の工場見学にお申し込みいただき、ありがとうございます。\n\n';
                template += '見学日: ' + visitDate + '\n\n';
                template += 'エフピコ 工場見学担当';
        }
        
        return template;
    }
    
    // メールモーダルの表示
    function showEmailModal(template) {
        var modal = $('<div id="email-modal" class="email-modal">');
        var modalContent = $('<div class="email-modal-content">');
        var closeBtn = $('<span class="email-close">&times;</span>');
        var title = $('<h2>返信メール作成</h2>');
        var textarea = $('<textarea id="email-content" rows="20" cols="80">').val(template);
        var buttons = $('<div class="email-buttons">');
        var copyBtn = $('<button type="button" class="button">コピー</button>');
        var cancelBtn = $('<button type="button" class="button">キャンセル</button>');
        
        buttons.append(copyBtn, cancelBtn);
        modalContent.append(closeBtn, title, textarea, buttons);
        modal.append(modalContent);
        $('body').append(modal);
        
        modal.show();
        
        // コピーボタン
        copyBtn.on('click', function() {
            textarea.select();
            document.execCommand('copy');
            alert('メール内容をコピーしました。');
        });
        
        // 閉じるボタン
        closeBtn.on('click', function() {
            modal.remove();
        });
        
        cancelBtn.on('click', function() {
            modal.remove();
        });
        
        // モーダル外クリックで閉じる
        modal.on('click', function(e) {
            if (e.target === modal[0]) {
                modal.remove();
            }
        });
    }
    
    // リアルタイムバリデーション
    $('.form-input, .form-select').on('blur', function() {
        var $field = $(this);
        var value = $field.val().trim();
        
        if ($field.attr('required') && !value) {
            $field.addClass('error');
        } else {
            $field.removeClass('error');
        }
        
        // メールアドレスの形式チェック
        if ($field.attr('type') === 'email' && value && !isValidEmail(value)) {
            $field.addClass('error');
        }
        
        // 電話番号の形式チェック
        if ($field.attr('type') === 'tel' && value && !isValidPhone(value)) {
            $field.addClass('error');
        }
    });
    
    // 数値フィールドの制限
    $('input[type="number"]').on('input', function() {
        var min = parseInt($(this).attr('min'));
        var max = parseInt($(this).attr('max'));
        var value = parseInt($(this).val());
        
        if (!isNaN(min) && value < min) {
            $(this).val(min);
        }
        if (!isNaN(max) && value > max) {
            $(this).val(max);
        }
    });
    
    // 郵便番号の自動フォーマット
    $('input[name$="_zip"]').on('input', function() {
        var value = $(this).val().replace(/[^\d]/g, '');
        if (value.length > 7) {
            value = value.substring(0, 7);
        }
        $(this).val(value);
    });
    
    // 自動保存機能（下書き）
    var autoSaveTimeout;
    $('.form-input, .form-select, input[type="radio"]').on('change input', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            saveFormDraft();
        }, 5000); // 5秒後に自動保存
    });
    
    // 下書き保存
    function saveFormDraft() {
        var formData = {};
        $('.form-input, .form-select').each(function() {
            formData[$(this).attr('name')] = $(this).val();
        });
        
        $('input[type="radio"]:checked').each(function() {
            formData[$(this).attr('name')] = $(this).val();
        });
        
        localStorage.setItem('reservation_draft', JSON.stringify(formData));
        console.log('下書きを保存しました');
    }
    
    // 下書き読み込み
    function loadFormDraft() {
        var draft = localStorage.getItem('reservation_draft');
        if (draft) {
            try {
                var formData = JSON.parse(draft);
                Object.keys(formData).forEach(function(name) {
                    var $field = $('[name="' + name + '"]');
                    if ($field.attr('type') === 'radio') {
                        $field.filter('[value="' + formData[name] + '"]').prop('checked', true);
                    } else {
                        $field.val(formData[name]);
                    }
                });
                console.log('下書きを読み込みました');
            } catch (e) {
                console.log('下書きの読み込みに失敗しました');
            }
        }
    }
    
    // ページ読み込み時に下書きを復元
    // loadFormDraft(); // 必要に応じてコメントアウトを解除
    
    // フォーム送信成功時に下書きをクリア
    function clearFormDraft() {
        localStorage.removeItem('reservation_draft');
    }
    
});

/**
 * CSS for email modal
 */
var emailModalCSS = `
.email-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.email-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    border-radius: 4px;
    width: 80%;
    max-width: 800px;
    position: relative;
}

.email-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    position: absolute;
    right: 15px;
    top: 10px;
}

.email-close:hover,
.email-close:focus {
    color: #000;
}

#email-content {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: monospace;
    resize: vertical;
}

.email-buttons {
    margin-top: 15px;
    text-align: right;
}

.email-buttons button {
    margin-left: 10px;
}
`;

// CSSを動的に追加
if (!document.getElementById('email-modal-css')) {
    var style = document.createElement('style');
    style.id = 'email-modal-css';
    style.textContent = emailModalCSS;
    document.head.appendChild(style);
}