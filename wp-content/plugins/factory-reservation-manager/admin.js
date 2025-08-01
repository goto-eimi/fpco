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
                        var dateStr = arg.date.toISOString().split('T')[0]; // YYYY-MM-DD形式に変換
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
                            
                            if (isWeekend) {
                                // 土日は常に見学不可に設定
                                if (amCheckbox) amCheckbox.checked = true;
                                if (pmCheckbox) pmCheckbox.checked = true;
                                
                                // 土日のデフォルト設定をデータベースに保存（データがない場合のみ）
                                if (!data.success) {
                                    fetch(factory_calendar.ajax_url, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: 'action=save_unavailable&factory_id=' + factoryId + 
                                              '&date=' + dateStr + 
                                              '&am_unavailable=true' + 
                                              '&pm_unavailable=true' + 
                                              '&nonce=' + factory_calendar.nonce
                                    });
                                }
                            } else if (data.success) {
                                // 平日はデータベースの設定を使用
                                if (amCheckbox) {
                                    amCheckbox.checked = data.data.am_unavailable || false;
                                }
                                if (pmCheckbox) {
                                    pmCheckbox.checked = data.data.pm_unavailable || false;
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
                    var date = e.target.getAttribute('data-date');
                    var isAM = e.target.classList.contains('am-checkbox');
                    var amCheckbox = document.querySelector('.am-checkbox[data-date="' + date + '"]');
                    var pmCheckbox = document.querySelector('.pm-checkbox[data-date="' + date + '"]');
                    var amChecked = amCheckbox ? amCheckbox.checked : false;
                    var pmChecked = pmCheckbox ? pmCheckbox.checked : false;
                    
                    fetch(factory_calendar.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=save_unavailable&factory_id=' + currentFactoryId + 
                              '&date=' + date + 
                              '&am_unavailable=' + amChecked + 
                              '&pm_unavailable=' + pmChecked + 
                              '&nonce=' + factory_calendar.nonce
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            alert('保存に失敗しました');
                            // 失敗時は元に戻す
                            if (isAM && amCheckbox) {
                                amCheckbox.checked = !amChecked;
                            } else if (!isAM && pmCheckbox) {
                                pmCheckbox.checked = !pmChecked;
                            }
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