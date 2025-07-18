// 予約一覧画面のJavaScript

jQuery(document).ready(function($) {
    // テーブル行クリックで編集画面へ遷移
    $('.reservation-row').on('click', function(e) {
        // リンクのクリックは除外
        if (e.target.tagName.toLowerCase() === 'a') {
            return;
        }
        
        const reservationId = $(this).data('id');
        if (reservationId) {
            window.location.href = 'admin.php?page=reservation-management&reservation_id=' + reservationId;
        }
    });
    
    // カード形式（スマホ版）の実装
    function createMobileCards() {
        if (window.innerWidth <= 767) {
            const tableContainer = $('.reservations-table-container');
            const table = tableContainer.find('table');
            
            if (table.length && !$('.reservations-card-container').length) {
                const cardContainer = $('<div class="reservations-card-container"></div>');
                
                table.find('tbody tr').each(function() {
                    const row = $(this);
                    const reservationId = row.data('id');
                    const reservationNumber = row.find('.reservation-number a').text().trim();
                    const applicantName = row.find('.applicant-name').text().trim();
                    const applicantAddress = row.find('.applicant-address').text().trim();
                    const reservationDate = row.find('.reservation-date').text().trim();
                    const reservationTime = row.find('.reservation-time').text().trim();
                    const phone = row.find('.reservation-phone').text().trim();
                    const type = row.find('.reservation-type').text().trim();
                    const statusBadge = row.find('.status-badge').prop('outerHTML');
                    
                    const card = $(`
                        <div class="reservation-card" data-id="${reservationId}">
                            <div class="card-header">
                                <span class="card-reservation-number">予約番号: ${reservationNumber}</span>
                                ${statusBadge}
                            </div>
                            <div class="card-applicant-name">${applicantName}</div>
                            <div class="card-datetime">${reservationDate} ${reservationTime}</div>
                            <div class="card-type">${type}</div>
                            <div class="card-phone">TEL: ${phone}</div>
                        </div>
                    `);
                    
                    cardContainer.append(card);
                });
                
                tableContainer.after(cardContainer);
                tableContainer.hide();
            }
        } else {
            $('.reservations-card-container').remove();
            $('.reservations-table-container').show();
        }
    }
    
    // 画面リサイズ時の処理
    $(window).on('resize', function() {
        createMobileCards();
    });
    
    // 初期実行
    createMobileCards();
    
    // カードクリック処理
    $(document).on('click', '.reservation-card', function() {
        const reservationId = $(this).data('id');
        if (reservationId) {
            window.location.href = 'admin.php?page=reservation-management&reservation_id=' + reservationId;
        }
    });
    
    // 検索フォームのデバウンス機能
    let searchTimeout;
    $('.search-field input[type="text"], .search-field input[type="number"]').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            // 自動検索は実装しないが、ここに追加可能
        }, 300);
    });
    
    // CSV出力時のローディング表示
    $('a[href*="export_csv"]').on('click', function() {
        showLoading('CSV出力中...');
        
        // 5秒後にローディングを非表示（通常はダウンロード完了で自動的に非表示になる）
        setTimeout(function() {
            hideLoading();
        }, 5000);
    });
    
    // ローディング表示
    function showLoading(message = 'Loading...') {
        const overlay = $(`
            <div class="loading-overlay">
                <div style="text-align: center;">
                    <div class="loading-spinner"></div>
                    <div style="margin-top: 10px; font-size: 14px; color: #495057;">${message}</div>
                </div>
            </div>
        `);
        $('body').append(overlay);
    }
    
    // ローディング非表示
    function hideLoading() {
        $('.loading-overlay').remove();
    }
    
    // 更新通知
    function showUpdateNotification(message) {
        const notification = $(`
            <div style="position: fixed; top: 32px; left: 50%; transform: translateX(-50%); 
                        background: #d4edda; color: #155724; padding: 10px 20px; 
                        border: 1px solid #c3e6cb; border-radius: 4px; z-index: 9999;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                ${message}
            </div>
        `);
        
        $('body').append(notification);
        
        setTimeout(function() {
            notification.fadeOut(function() {
                notification.remove();
            });
        }, 3000);
    }
    
    // 行のハイライト機能
    function highlightRow(reservationId) {
        const row = $(`.reservation-row[data-id="${reservationId}"]`);
        if (row.length) {
            row.css('background-color', '#fff3cd').animate({
                backgroundColor: '#f8f9fa'
            }, 3000);
        }
    }
    
    // 自動更新機能のカスタマイズ
    let autoUpdateEnabled = true;
    let lastUpdateTime = Date.now();
    
    // 5分間隔での自動更新
    setInterval(function() {
        if (autoUpdateEnabled && (Date.now() - lastUpdateTime) >= 5 * 60 * 1000) {
            // ページの可視性をチェック
            if (!document.hidden) {
                showUpdateNotification('データを更新しています...');
                window.location.reload();
            }
        }
    }, 5 * 60 * 1000);
    
    // ページの可視性変更を監視
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            lastUpdateTime = Date.now();
        }
    });
    
    // 手動更新ボタン（必要に応じて追加）
    function addManualUpdateButton() {
        const updateButton = $(`
            <button type="button" class="button button-secondary" id="manual-update-btn" 
                    style="margin-left: 10px;">
                <span class="dashicons dashicons-update"></span> 更新
            </button>
        `);
        
        $('.action-buttons-area').find('.button').last().after(updateButton);
        
        updateButton.on('click', function() {
            showLoading('データを更新中...');
            window.location.reload();
        });
    }
    
    // 手動更新ボタンを追加
    addManualUpdateButton();
    
    // 検索条件の保存と復元
    function saveSearchConditions() {
        const conditions = {};
        $('.search-form input, .search-form select').each(function() {
            const name = $(this).attr('name');
            const value = $(this).val();
            if (name && value) {
                conditions[name] = value;
            }
        });
        sessionStorage.setItem('reservation_search_conditions', JSON.stringify(conditions));
    }
    
    function restoreSearchConditions() {
        const saved = sessionStorage.getItem('reservation_search_conditions');
        if (saved) {
            try {
                const conditions = JSON.parse(saved);
                Object.keys(conditions).forEach(function(key) {
                    $(`[name="${key}"]`).val(conditions[key]);
                });
            } catch (e) {
                console.log('検索条件の復元に失敗しました:', e);
            }
        }
    }
    
    // 検索フォーム送信時に条件を保存
    $('.search-form').on('submit', function() {
        saveSearchConditions();
    });
    
    // ページロード時に条件を復元
    restoreSearchConditions();
    
    // ソート矢印の改善
    $('.sortable a').each(function() {
        const link = $(this);
        const indicator = link.find('.sorting-indicator');
        
        if (indicator.find('.dashicons-sort').length) {
            indicator.html('<span class="dashicons dashicons-sort" style="opacity: 0.5;"></span>');
        }
    });
    
    // テーブルの列幅調整
    function adjustTableColumns() {
        if (window.innerWidth > 1200) {
            $('.wp-list-table').css('table-layout', 'fixed');
            $('.wp-list-table th:nth-child(1)').css('width', '80px');  // 予約番号
            $('.wp-list-table th:nth-child(2)').css('width', '250px'); // 予約者
            $('.wp-list-table th:nth-child(3)').css('width', '160px'); // 予約日時
            $('.wp-list-table th:nth-child(4)').css('width', '120px'); // 電話番号
            $('.wp-list-table th:nth-child(5)').css('width', '150px'); // 予約タイプ
            $('.wp-list-table th:nth-child(6)').css('width', '100px'); // ステータス
        } else {
            $('.wp-list-table').css('table-layout', 'auto');
            $('.wp-list-table th').css('width', 'auto');
        }
    }
    
    $(window).on('resize', adjustTableColumns);
    adjustTableColumns();
});

// 検索フォームクリア機能
function clearSearchForm() {
    // フォームの全入力項目をクリア
    $('.search-form input[type="text"]').val('');
    $('.search-form input[type="number"]').val('');
    $('.search-form input[type="date"]').val('');
    $('.search-form select').prop('selectedIndex', 0);
    
    // セッションストレージもクリア
    sessionStorage.removeItem('reservation_search_conditions');
    
    // クリア後のページに遷移
    window.location.href = '?page=reservation-list';
}