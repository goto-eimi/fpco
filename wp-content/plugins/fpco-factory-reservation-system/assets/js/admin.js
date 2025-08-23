document.addEventListener('DOMContentLoaded', function() {
    var calendar;
    var currentFactoryId = window.currentFactoryId;
    var checkboxEventHandlerAdded = false;
    
    // FullCalendarの初期化
    function initCalendar(factoryId) {
        var calendarEl = document.getElementById('calendar');
        
        calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'ja',
            headerToolbar: {
                left: 'prev',
                center: 'title',
                right: 'next'
            },
            viewDidMount: function(viewInfo) {
                // タイトルを手動で日本語形式に変更
                var titleEl = document.querySelector('.fc-toolbar-title');
                if (titleEl) {
                    var currentDate = calendar.getDate();
                    var year = currentDate.getFullYear();
                    var month = String(currentDate.getMonth() + 1).padStart(2, '0');
                    titleEl.textContent = year + '年' + month + '月';
                }
                
                // 初期表示時の前へボタン制御
                var prevButton = document.querySelector('.fc-prev-button');
                if (prevButton) {
                    var today = new Date();
                    var currentCalendarDate = calendar.getDate();
                    var isCurrentMonth = currentCalendarDate.getFullYear() === today.getFullYear() && 
                                       currentCalendarDate.getMonth() === today.getMonth();
                    
                    if (isCurrentMonth) {
                        prevButton.style.opacity = '0.3';
                        prevButton.style.pointerEvents = 'none';
                    } else {
                        prevButton.style.opacity = '1';
                        prevButton.style.pointerEvents = 'auto';
                    }
                }
            },
            buttonText: {
                prev: '<',
                next: '>'
            },
            initialView: 'dayGridMonth',
            height: 'auto',
            dayMaxEvents: false,
            fixedWeekCount: false,
            showNonCurrentDates: true, // 前後の月の日付を表示する
            firstDay: 0, // 日曜日始まり
            validRange: function() {
                var today = new Date();
                var currentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                return {
                    start: currentMonth.toISOString().split('T')[0]
                };
            },
            
            // 日付セルのカスタマイズ
            dayCellDidMount: function(arg) {
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                var cellDate = arg.date;
                
                // セルのスタイル調整
                arg.el.style.border = '1px solid #999';
                arg.el.style.height = '100px';
                arg.el.style.position = 'relative';
                arg.el.style.setProperty('background-color', '#ffffff', 'important');
                arg.el.style.cursor = 'default';
                
                // 日付番号を中央上部に配置
                var dayNumberEl = arg.el.querySelector('.fc-daygrid-day-number');
                if (dayNumberEl) {
                    dayNumberEl.style.width = '100%';
                    dayNumberEl.style.textAlign = 'center';
                    dayNumberEl.style.fontSize = '14px';
                    dayNumberEl.style.fontWeight = 'bold';
                    dayNumberEl.style.padding = '5px 0';
                    dayNumberEl.style.position = 'relative';
                    dayNumberEl.style.marginBottom = '5px';
                }
                
                // 過去の日付の処理
                if (cellDate < today) {
                    // 既存のイベントコンテナを取得または作成
                    var eventsEl = arg.el.querySelector('.fc-daygrid-day-events');
                    if (eventsEl) {
                        eventsEl.style.margin = '0';
                        eventsEl.innerHTML = '<div style="text-align: center; color: #999; font-size: 12px;">終了</div>';
                    }
                } else if (cellDate.getTime() === today.getTime()) {
                    // 当日の処理 - 見学不可にする
                    var eventsEl = arg.el.querySelector('.fc-daygrid-day-events');
                    if (eventsEl) {
                        eventsEl.style.margin = '0';
                        eventsEl.innerHTML = '<div style="text-align: center; color: #d32f2f; font-size: 12px;">○<br>当日予約不可</div>';
                    }
                } else {
                    // AM/PMチェックボックスを追加
                    var eventsEl = arg.el.querySelector('.fc-daygrid-day-events');
                    if (eventsEl) {
                        eventsEl.style.margin = '0';
                        eventsEl.innerHTML = '';
                        
                        // 土日チェック
                        var dayOfWeek = cellDate.getDay();
                        var isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
                        
                        // チェックボックスコンテナ
                        // ローカルタイムゾーンでYYYY-MM-DD形式に変換（UTCずれを防ぐ）
                        var year = cellDate.getFullYear();
                        var month = String(cellDate.getMonth() + 1).padStart(2, '0');
                        var day = String(cellDate.getDate()).padStart(2, '0');
                        var dateStr = year + '-' + month + '-' + day;
                        
                        var checkboxHtml = '<div style="font-size: 11px; line-height: 1.3; padding: 0 5px; margin-bottom: 10px;">' +
                            '<div style="margin-bottom: 8px;">' +
                                '<div style="font-weight: bold;">AM</div>' +
                                '<label style="display: block; margin-top: 2px; cursor: pointer;">' +
                                    '<input type="checkbox" class="am-checkbox" data-date="' + dateStr + '" ' + 
                                    (isWeekend ? 'checked' : '') + 
                                    ' style="margin-right: 2px;">見学不可' +
                                '</label>' +
                            '</div>' +
                            '<div>' +
                                '<div style="font-weight: bold;">PM</div>' +
                                '<label style="display: block; margin-top: 2px; cursor: pointer;">' +
                                    '<input type="checkbox" class="pm-checkbox" data-date="' + dateStr + '" ' + 
                                    (isWeekend ? 'checked' : '') + 
                                    ' style="margin-right: 2px;">見学不可' +
                                '</label>' +
                            '</div>' +
                        '</div>';
                        
                        eventsEl.innerHTML = checkboxHtml;
                        
                        // 既存の設定を取得して反映
                        fetch(factory_calendar.ajax_url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=get_unavailable_info&factory_id=' + factoryId + 
                                  '&date=' + dateStr + '&nonce=' + factory_calendar.nonce
                        })
                        .then(response => response.json())
                        .then(data => {
                            var amCheckbox = arg.el.querySelector('.am-checkbox');
                            var pmCheckbox = arg.el.querySelector('.pm-checkbox');
                            var amLabel = amCheckbox ? amCheckbox.closest('label') : null;
                            var pmLabel = pmCheckbox ? pmCheckbox.closest('label') : null;
                            
                            if (data.success) {
                                console.log('読み込みデータ:', data.data); // デバッグ用
                                if (data.data.has_data) {
                                    // データベースの設定を使用（土日・平日問わず）
                                    if (amCheckbox) {
                                        amCheckbox.checked = data.data.am_unavailable || false;
                                        
                                        // 予約がある場合は視覚的に区別するが、無効化はしない
                                        if (data.data.has_am_reservation) {
                                            amCheckbox.disabled = false; // 予約があっても変更可能
                                            if (amLabel) {
                                                amLabel.style.opacity = '0.8';
                                                amLabel.style.cursor = 'pointer';
                                                amLabel.title = '予約があります';
                                            }
                                        } else {
                                            amCheckbox.disabled = false;
                                            if (amLabel) {
                                                amLabel.style.opacity = '1';
                                                amLabel.style.cursor = 'pointer';
                                                amLabel.title = '';
                                            }
                                        }
                                    }
                                    
                                    if (pmCheckbox) {
                                        pmCheckbox.checked = data.data.pm_unavailable || false;
                                        
                                        // 予約がある場合は視覚的に区別するが、無効化はしない
                                        if (data.data.has_pm_reservation) {
                                            pmCheckbox.disabled = false; // 予約があっても変更可能
                                            if (pmLabel) {
                                                pmLabel.style.opacity = '0.8';
                                                pmLabel.style.cursor = 'pointer';
                                                pmLabel.title = '予約があります';
                                            }
                                        } else {
                                            pmCheckbox.disabled = false;
                                            if (pmLabel) {
                                                pmLabel.style.opacity = '1';
                                                pmLabel.style.cursor = 'pointer';
                                                pmLabel.title = '';
                                            }
                                        }
                                    }
                                } else {
                                    // データがない場合（土日・平日問わず）
                                    if (isWeekend) {
                                        // 土日はデフォルトでチェック状態にするが、データベースには保存しない
                                        if (amCheckbox) amCheckbox.checked = true;
                                        if (pmCheckbox) pmCheckbox.checked = true;
                                    } else {
                                        // 平日はチェックなし
                                        if (amCheckbox) amCheckbox.checked = false;
                                        if (pmCheckbox) pmCheckbox.checked = false;
                                    }
                                    
                                    // 予約がない場合は有効にする
                                    if (amCheckbox) {
                                        amCheckbox.disabled = false;
                                        if (amLabel) {
                                            amLabel.style.opacity = '1';
                                            amLabel.style.cursor = 'pointer';
                                            amLabel.title = '';
                                        }
                                    }
                                    if (pmCheckbox) {
                                        pmCheckbox.disabled = false;
                                        if (pmLabel) {
                                            pmLabel.style.opacity = '1';
                                            pmLabel.style.cursor = 'pointer';
                                            pmLabel.title = '';
                                        }
                                    }
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching unavailable info:', error);
                        });
                    }
                }
                
                // 土日の色設定
                var dayOfWeek = cellDate.getDay();
                if (dayNumberEl) {
                    if (dayOfWeek === 0) {
                        dayNumberEl.style.color = '#ff0000';
                    } else if (dayOfWeek === 6) {
                        dayNumberEl.style.color = '#0066cc';
                    }
                }
            },
            
            // セルクリック時の処理を無効化
            dateClick: function() {
                return false;
            },
            
            // ヘッダーのカスタマイズ
            dayHeaderDidMount: function(arg) {
                arg.el.style.setProperty('background-color', '#D7CCC8', 'important');
                arg.el.style.setProperty('color', 'white', 'important');
                arg.el.style.border = '1px solid #999';
                arg.el.style.padding = '10px 0';
                arg.el.style.fontSize = '16px';
                arg.el.style.fontWeight = 'normal';
                
                // 内部のテキスト要素も白色に設定
                var innerText = arg.el.querySelector('.fc-col-header-cell-cushion');
                if (innerText) {
                    innerText.style.setProperty('color', 'white', 'important');
                }
            }
        });
        
        calendar.render();
        
        // ツールバーのスタイル調整
        var toolbar = document.querySelector('.fc-toolbar');
        if (toolbar) {
            toolbar.style.backgroundColor = '#666';
            toolbar.style.color = 'white';
            toolbar.style.padding = '10px';
            toolbar.style.marginBottom = '5px';
        }
        
        var toolbarTitle = document.querySelector('.fc-toolbar-title');
        if (toolbarTitle) {
            toolbarTitle.style.fontSize = '18px';
            toolbarTitle.style.fontWeight = 'bold';
        }
        
        var buttons = document.querySelectorAll('.fc-button');
        buttons.forEach(function(button) {
            button.style.backgroundColor = 'transparent';
            button.style.border = 'none';
            button.style.color = 'white';
            button.style.fontSize = '20px';
            button.style.padding = '0 15px';
            
            button.addEventListener('mouseenter', function() {
                this.style.backgroundColor = 'rgba(255,255,255,0.1)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.backgroundColor = 'transparent';
            });
            
            // 前/次ボタンクリック時にタイトルを更新と制御
            button.addEventListener('click', function() {
                // タイトルの更新を即座に実行
                var titleEl = document.querySelector('.fc-toolbar-title');
                if (titleEl) {
                    var currentDate = calendar.getDate();
                    var year = currentDate.getFullYear();
                    var month = String(currentDate.getMonth() + 1).padStart(2, '0');
                    titleEl.textContent = year + '年' + month + '月';
                }
                
                // 前へボタンの制御
                var prevButton = document.querySelector('.fc-prev-button');
                if (prevButton) {
                    var today = new Date();
                    var currentCalendarDate = calendar.getDate();
                    var isCurrentMonth = currentCalendarDate.getFullYear() === today.getFullYear() && 
                                       currentCalendarDate.getMonth() === today.getMonth();
                    
                    if (isCurrentMonth) {
                        prevButton.style.opacity = '0.3';
                        prevButton.style.pointerEvents = 'none';
                    } else {
                        prevButton.style.opacity = '1';
                        prevButton.style.pointerEvents = 'auto';
                    }
                }
            });
        });
        
        // チェックボックスのイベントハンドラを一回だけ設定
        if (!checkboxEventHandlerAdded) {
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('am-checkbox') || e.target.classList.contains('pm-checkbox')) {
                    e.stopPropagation();
                    
                    // 無効化チェックを削除（予約があっても変更可能）
                    
                    var date = e.target.getAttribute('data-date');
                    var isAM = e.target.classList.contains('am-checkbox');
                    
                    // 同じ日付のセル内でのみチェックボックスを検索
                    var cellElement = e.target.closest('.fc-daygrid-day');
                    if (!cellElement) {
                        console.error('親セル要素が見つかりません');
                        return;
                    }
                    
                    var amCheckbox = cellElement.querySelector('.am-checkbox');
                    var pmCheckbox = cellElement.querySelector('.pm-checkbox');
                    var amChecked = amCheckbox ? amCheckbox.checked : false;
                    var pmChecked = pmCheckbox ? pmCheckbox.checked : false;
                    
                    
                    var requestBody = 'action=save_unavailable&factory_id=' + currentFactoryId + 
                              '&date=' + date + 
                              '&am_unavailable=' + amChecked + 
                              '&pm_unavailable=' + pmChecked + 
                              '&nonce=' + factory_calendar.nonce;
                    
                    
                    fetch(factory_calendar.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: requestBody
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('保存レスポンス:', data); // デバッグ用
                        if (!data.success) {
                            console.error('保存エラー:', data);
                            alert('保存に失敗しました: ' + (data.data || 'Unknown error'));
                            // 失敗時は元に戻す
                            if (isAM && amCheckbox) {
                                amCheckbox.checked = !amChecked;
                            } else if (!isAM && pmCheckbox) {
                                pmCheckbox.checked = !pmChecked;
                            }
                        } else {
                            console.log('保存成功:', data.data); // デバッグ用
                        }
                    })
                    .catch(error => {
                        console.error('通信エラー:', error);
                        alert('通信エラーが発生しました');
                        // エラー時は元に戻す
                        if (isAM && amCheckbox) {
                            amCheckbox.checked = !amChecked;
                        } else if (!isAM && pmCheckbox) {
                            pmCheckbox.checked = !pmChecked;
                        }
                    });
                }
            });
            checkboxEventHandlerAdded = true;
        }
    }
    
    // 初期化
    if (currentFactoryId) {
        initCalendar(currentFactoryId);
    }
    
});