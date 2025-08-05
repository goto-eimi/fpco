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
    
    
    // 行のハイライト機能
    function highlightRow(reservationId) {
        const row = $(`.reservation-row[data-id="${reservationId}"]`);
        if (row.length) {
            row.css('background-color', '#fff3cd').animate({
                backgroundColor: '#f8f9fa'
            }, 3000);
        }
    }
    
    
    
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
            $('.wp-list-table th:nth-child(1), .wp-list-table td:nth-child(1)').css('width', '80px');  // 予約番号
            $('.wp-list-table th:nth-child(2), .wp-list-table td:nth-child(2)').css('width', '100px'); // 予約者
            $('.wp-list-table th:nth-child(3), .wp-list-table td:nth-child(3)').css('width', '180px'); // 予約日時
            $('.wp-list-table th:nth-child(4), .wp-list-table td:nth-child(4)').css('width', '120px'); // 電話番号
            $('.wp-list-table th:nth-child(5), .wp-list-table td:nth-child(5)').css('width', '140px'); // 予約タイプ
            $('.wp-list-table th:nth-child(6), .wp-list-table td:nth-child(6)').css('width', '100px'); // ステータス
        } else {
            $('.wp-list-table').css('table-layout', 'auto');
            $('.wp-list-table th, .wp-list-table td').css('width', 'auto');
        }
    }
    
    $(window).on('resize', adjustTableColumns);
    adjustTableColumns();
    
    // 検索フォームクリア機能をグローバルに定義
    window.clearSearchForm = function() {
        // フォームの全入力項目をクリア
        jQuery('.search-form input[type="text"]').val('');
        jQuery('.search-form input[type="number"]').val('');
        jQuery('.search-form input[type="date"]').val('');
        jQuery('.search-form select').prop('selectedIndex', 0);
        
        // セッションストレージもクリア
        sessionStorage.removeItem('reservation_search_conditions');
        
        // クリア後のページに遷移
        window.location.href = '?page=reservation-list';
    };
});