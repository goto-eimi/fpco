/**
 * 予約状況カレンダー JavaScript
 */

class ReservationCalendar {
    constructor() {
        this.currentMonth = new Date().getFullYear() + '-' + String(new Date().getMonth() + 1).padStart(2, '0');
        this.factoryId = this.getFactoryId();
        this.calendarData = {};
        this.factoryTimeslots = null;
        this.selectedDate = null;
        this.selectedTimeslot = null;
        
        this.init();
    }
    
    async init() {
        this.bindEvents();
        await this.loadFactoryTimeslots();
        this.loadCalendarData(this.currentMonth);
    }
    
    bindEvents() {
        // 月選択の変更
        const monthSelect = document.getElementById('calendar-month-select');
        if (monthSelect) {
            monthSelect.addEventListener('change', (e) => {
                this.currentMonth = e.target.value;
                this.loadCalendarData(this.currentMonth);
            });
        }
        
        // モーダル関連（要素が存在する場合のみ）
        setTimeout(() => {
            const modalClose = document.querySelector('.modal-close');
            if (modalClose) {
                modalClose.addEventListener('click', () => this.closeModal());
            }
        }, 100);
        
        // オーバーレイクリックで閉じる
        setTimeout(() => {
            const timeslotModal = document.getElementById('timeslot-modal');
            if (timeslotModal) {
                timeslotModal.addEventListener('click', (e) => {
                    if (e.target.id === 'timeslot-modal') {
                        this.closeModal();
                    }
                });
            }
        }, 100);
    }
    
    getFactoryId() {
        // まずdata属性から取得（ショートコード用）
        const calendarEl = document.getElementById('calendar');
        if (calendarEl && calendarEl.dataset.factoryId) {
            return calendarEl.dataset.factoryId;
        }
        
        // 次にcalendarDataグローバル変数から取得（ショートコード用）
        if (typeof calendarData !== 'undefined' && calendarData.factoryId) {
            return calendarData.factoryId;
        }
        
        // 最後にURLパラメータから取得（ページテンプレート用）
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('factory') || '1';
    }
    
    async loadFactoryTimeslots() {
        try {
            const ajaxUrl = typeof calendarData !== 'undefined' && calendarData.ajaxUrl ? 
                           calendarData.ajaxUrl : '/wp-admin/admin-ajax.php';
            
            const requestUrl = `${ajaxUrl}?action=get_factory_timeslots&factory=${this.factoryId}`;
            
            const response = await fetch(requestUrl);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: 工場時間設定の取得に失敗しました`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || '工場時間設定の取得に失敗しました');
            }
            
            this.factoryTimeslots = result.data;
            
        } catch (error) {
            console.error('Factory timeslots loading error:', error);
            // エラーの場合はデフォルト設定を使用
            this.factoryTimeslots = null;
        }
    }
    
    async loadCalendarData(yearMonth) {
        try {
            this.showLoading();
            
            // AJAXエンドポイントURLを構築
            const ajaxUrl = typeof calendarData !== 'undefined' && calendarData.ajaxUrl ? 
                           calendarData.ajaxUrl : '/wp-admin/admin-ajax.php';
            
            const requestUrl = `${ajaxUrl}?action=get_calendar_data&month=${yearMonth}&factory=${this.factoryId}`;
            
            const response = await fetch(requestUrl);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: データの取得に失敗しました`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'データの取得に失敗しました');
            }
            
            this.calendarData = result.data;
            this.renderCalendar(yearMonth);
            
        } catch (error) {
            console.error('Calendar data loading error:', error);
            this.showError(`カレンダーデータの読み込みに失敗しました。<br>${error.message}`);
        }
    }
    
    showLoading() {
        // PC版
        const calendarGrid = document.getElementById('calendar-grid');
        if (calendarGrid) {
            calendarGrid.innerHTML = `
                <div class="calendar-loading">
                    <div class="spinner"></div>
                    <p>カレンダーを読み込み中...</p>
                </div>
            `;
        }
        
        // スマホ版
        const calendarList = document.getElementById('calendar-list');
        if (calendarList) {
            calendarList.innerHTML = `
                <div class="calendar-loading">
                    <div class="spinner"></div>
                    <p>カレンダーを読み込み中...</p>
                </div>
            `;
        }
    }
    
    showError(message) {
        const errorHtml = `
            <div class="calendar-error">
                <p>${message}</p>
                <button onclick="location.reload()" class="btn-reload">再読み込み</button>
            </div>
        `;
        
        // PC版
        const calendarGrid = document.getElementById('calendar-grid');
        if (calendarGrid) {
            calendarGrid.innerHTML = errorHtml;
        }
        
        // スマホ版
        const calendarList = document.getElementById('calendar-list');
        if (calendarList) {
            calendarList.innerHTML = errorHtml;
        }
    }
    
    renderCalendar(yearMonth) {
        const [year, month] = yearMonth.split('-').map(Number);
        const firstDay = new Date(year, month - 1, 1);
        const lastDay = new Date(year, month, 0);
        const today = new Date();
        
        // PC版カレンダー描画
        this.renderDesktopCalendar(year, month, firstDay, lastDay, today);
        
        // スマホ版カレンダー描画
        this.renderMobileCalendar(year, month, firstDay, lastDay, today);
    }
    
    renderDesktopCalendar(year, month, firstDay, lastDay, today) {
        const calendarGrid = document.getElementById('calendar-grid');
        if (!calendarGrid) return;
        
        let html = '';
        
        // 曜日ヘッダー
        const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        weekdays.forEach(day => {
            html += `<div class="calendar-day-header">${day}</div>`;
        });
        
        // 前月の日付（余白）
        const startDay = firstDay.getDay();
        for (let i = 0; i < startDay; i++) {
            const prevDate = new Date(year, month - 1, 1 - startDay + i);
            html += this.renderDayCell(prevDate, true, today);
        }
        
        // 当月の日付
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const currentDate = new Date(year, month - 1, day);
            html += this.renderDayCell(currentDate, false, today);
        }
        
        // 次月の日付（余白）
        const totalCells = Math.ceil((startDay + lastDay.getDate()) / 7) * 7;
        const remainingCells = totalCells - (startDay + lastDay.getDate());
        for (let i = 1; i <= remainingCells; i++) {
            const nextDate = new Date(year, month, i);
            html += this.renderDayCell(nextDate, true, today);
        }
        
        calendarGrid.innerHTML = html;
        
        // クリックイベントを追加
        this.addDayClickEvents();
    }
    
    renderMobileCalendar(year, month, firstDay, lastDay, today) {
        const calendarList = document.getElementById('calendar-list');
        if (!calendarList) return;
        
        let html = '';
        
        // 全月の日付を表示（SPECIFICATION.mdに従い縦スクロール対応）
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const currentDate = new Date(year, month - 1, day);
            html += this.renderMobileListItem(currentDate, today);
        }
        
        calendarList.innerHTML = html;
        
        // スクロール位置を保持
        this.restoreScrollPosition(calendarList);
        
        // クリックイベントを追加
        this.addMobileClickEvents();
        
        // スクロール位置を記録
        this.saveScrollPosition(calendarList);
    }
    
    saveScrollPosition(container) {
        if (container) {
            container.addEventListener('scroll', () => {
                sessionStorage.setItem('calendar-scroll-position', container.scrollTop);
            });
        }
    }
    
    restoreScrollPosition(container) {
        if (container) {
            const savedPosition = sessionStorage.getItem('calendar-scroll-position');
            if (savedPosition) {
                container.scrollTop = parseInt(savedPosition, 10);
            }
        }
    }
    
    renderDayCell(date, isOtherMonth, today) {
        const dateStr = this.formatDate(date);
        const dayNumber = date.getDate();
        const weekday = date.getDay();
        const isToday = this.isSameDate(date, today);
        const isPast = date < today && !this.isSameDate(date, today);
        
        // デモデータ（実際のAPIからのデータに置き換え予定）
        const dayData = this.getDayData(dateStr);
        
        let classes = ['calendar-day'];
        if (isOtherMonth) classes.push('other-month');
        if (isToday) classes.push('today');
        if (isPast) classes.push('past');
        if (dayData.clickable && !isPast) classes.push('clickable');
        
        let dayNumberClass = 'day-number';
        if (weekday === 0) dayNumberClass += ' sunday';
        if (weekday === 6) dayNumberClass += ' saturday';
        
        let timeSlotsHtml = '';
        if (!isOtherMonth) {
            // 過去の日付の場合はAM/PM両方とも「－」にする
            const amButton = isPast 
                ? `<span class="status-button unavailable">－</span>`
                : dayData.am.status === 'available' 
                    ? `<button class="status-button available" data-date="${dateStr}" data-period="am" onclick="openTimeslotSelection('${dateStr}', 'am')">${dayData.am.symbol}</button>`
                    : `<span class="status-button ${dayData.am.status}">${dayData.am.symbol}</span>`;
                
            const pmButton = isPast 
                ? `<span class="status-button unavailable">－</span>`
                : dayData.pm.status === 'available'
                    ? `<button class="status-button available" data-date="${dateStr}" data-period="pm" onclick="openTimeslotSelection('${dateStr}', 'pm')">${dayData.pm.symbol}</button>`
                    : `<span class="status-button ${dayData.pm.status}">${dayData.pm.symbol}</span>`;
                
            timeSlotsHtml = `
                <div class="time-slots">
                    <div class="time-slot">
                        <span class="time-label">AM</span>
                        ${amButton}
                    </div>
                    <div class="time-slot">
                        <span class="time-label">PM</span>
                        ${pmButton}
                    </div>
                </div>
            `;
        }
        
        return `
            <div class="${classes.join(' ')}" data-date="${dateStr}">
                <div class="${dayNumberClass}">${dayNumber}</div>
                ${timeSlotsHtml}
            </div>
        `;
    }
    
    renderMobileListItem(date, today) {
        const dateStr = this.formatDate(date);
        const dayNumber = date.getDate();
        const weekday = date.getDay();
        const weekdayNames = ['日', '月', '火', '水', '木', '金', '土'];
        const isPast = date < today && !this.isSameDate(date, today);
        const isToday = this.isSameDate(date, today);
        
        // デモデータ
        const dayData = this.getDayData(dateStr);
        
        let classes = ['calendar-list-item'];
        if (dayData.clickable && !isPast) classes.push('clickable');
        if (isToday) classes.push('today');
        if (isPast) classes.push('past');
        
        let dayNumberClass = 'list-day-number';
        if (weekday === 0) dayNumberClass += ' sunday';
        if (weekday === 6) dayNumberClass += ' saturday';
        
        // スマホ版カレンダーの新デザイン
        // 過去の日付の場合はAM/PM両方とも「－」にする
        const amButton = isPast 
            ? `<span class="mobile-status-button unavailable">－</span>`
            : dayData.am.status === 'available'
                ? `<button class="mobile-status-button available" data-date="${dateStr}" data-period="am" onclick="openTimeslotSelection('${dateStr}', 'am')">${dayData.am.symbol}</button>`
                : `<span class="mobile-status-button ${dayData.am.status}">${dayData.am.symbol}</span>`;
            
        const pmButton = isPast 
            ? `<span class="mobile-status-button unavailable">－</span>`
            : dayData.pm.status === 'available'
                ? `<button class="mobile-status-button available" data-date="${dateStr}" data-period="pm" onclick="openTimeslotSelection('${dateStr}', 'pm')">${dayData.pm.symbol}</button>`
                : `<span class="mobile-status-button ${dayData.pm.status}">${dayData.pm.symbol}</span>`;
        
        return `
            <div class="${classes.join(' ')}" data-date="${dateStr}">
                <div class="list-content">
                    <div class="list-date-section">
                        <span class="${dayNumberClass}">${dayNumber}</span>
                    </div>
                    <div class="list-weekday-section">
                        <span class="list-weekday">${weekdayNames[weekday]}</span>
                    </div>
                    <div class="list-time-section">
                        <div class="list-am-slot">AM ${amButton}</div>
                        <div class="list-pm-slot">PM ${pmButton}</div>
                    </div>
                </div>
            </div>
        `;
    }
    
    getDayData(dateStr) {
        // APIから取得したデータを使用
        if (this.calendarData && this.calendarData.days && this.calendarData.days[dateStr]) {
            const dayData = this.calendarData.days[dateStr];
            return {
                clickable: dayData.am.status === 'available' || dayData.pm.status === 'available',
                am: dayData.am,
                pm: dayData.pm
            };
        }
        
        // フォールバック: 仕様に基づくダミーデータ
        const date = new Date(dateStr);
        const day = date.getDate();
        const weekday = date.getDay();
        
        // 土日は受付不可
        if (weekday === 0 || weekday === 6) {
            return {
                clickable: false,
                am: { status: 'unavailable', symbol: '－' },
                pm: { status: 'unavailable', symbol: '－' }
            };
        }
        
        // 仕様に基づくサンプルデータ
        if (day === 1) {
            return {
                clickable: false,
                am: { status: 'unavailable', symbol: '－' },
                pm: { status: 'unavailable', symbol: '－' }
            };
        }
        
        // 木曜日のサンプルデータ（weekday === 4）- 日付より優先
        if (weekday === 4) {
            return {
                clickable: true,
                am: { status: 'available', symbol: '〇' },
                pm: { status: 'adjusting', symbol: '△' }
            };
        }
        
        // 7日、14日、21日、28日は表示なし（受付対象外）- 木曜日以外
        if (day === 7 || day === 14 || day === 21 || day === 28) {
            return {
                clickable: false,
                am: { status: 'none', symbol: '' },
                pm: { status: 'none', symbol: '' }
            };
        }
        
        // その他の平日は基本的に空きあり
        return {
            clickable: true,
            am: { status: 'available', symbol: '〇' },
            pm: { status: 'available', symbol: '〇' }
        };
    }
    
    addDayClickEvents() {
        // ◯ボタン以外の部分はクリック無効化（この関数は使用しない）
        // クリックイベントは個別のボタンで処理
    }
    
    addMobileClickEvents() {
        // モバイル版では個別ボタンのクリックで処理
        // ボタンのクリックイベントは、HTMLのonclick属性で処理される
    }
    
    openTimeslotModal(dateStr, period = null) {
        this.selectedDate = dateStr;
        this.selectedPeriod = period;
        this.selectedTimeslot = null;
        this.selectedDuration = null;
        
        const date = new Date(dateStr);
        const displayDate = this.formatDisplayDate(date);
        const periodLabel = period === 'am' ? '（午前）' : period === 'pm' ? '（午後）' : '';
        
        // モーダルタイトルを更新
        const modalTitle = document.getElementById('modal-date-title');
        if (modalTitle) {
            modalTitle.textContent = '時間帯を選択';
        }
        
        // 時間帯選択肢を生成
        this.renderDurationOptions(dateStr, period);
        
        document.getElementById('timeslot-modal').style.display = 'flex';
        
        // モーダル表示のみ（ボタン関連の処理削除）
    }
    
    renderDurationOptions(dateStr, period) {
        const optionsContainer = document.getElementById('timeslot-options');
        
        // モーダルタイトルを更新
        const modalTitle = document.getElementById('modal-date-title');
        if (modalTitle) {
            modalTitle.textContent = '時間帯を選択';
        }
        
        // 日付情報をmodal-selected-date要素に表示
        const date = new Date(dateStr);
        const displayDate = this.formatDisplayDate(date);
        const periodLabel = period === 'am' ? '（午前）' : period === 'pm' ? '（午後）' : '';
        
        const modalDateElement = document.getElementById('modal-selected-date');
        if (modalDateElement) {
            modalDateElement.textContent = `《${displayDate}${periodLabel}》`;
            modalDateElement.style.textAlign = 'center';
        }
        
        // 工場の時間設定をチェック
        if (!this.factoryTimeslots) {
            optionsContainer.innerHTML = `
                <div class="no-timeslots">
                    <p>申し訳ございません。時間設定を読み込めませんでした。</p>
                    <button type="button" class="btn-back" onclick="window.reservationCalendar.closeModal()">閉じる</button>
                </div>
            `;
            return;
        }
        
        // プラグインのデータ構造を判定
        // duration pattern (60min/90min構造がある場合)
        if (this.factoryTimeslots['60min'] || this.factoryTimeslots['90min']) {
            const has60min = this.factoryTimeslots['60min'] && this.factoryTimeslots['60min'][period] && this.factoryTimeslots['60min'][period].length > 0;
            const has90min = this.factoryTimeslots['90min'] && this.factoryTimeslots['90min'][period] && this.factoryTimeslots['90min'][period].length > 0;
            
            // どちらの時間帯もない場合はエラー表示
            if (!has60min && !has90min) {
                optionsContainer.innerHTML = `
                    <div class="no-timeslots">
                        <p>申し訳ございません。この時間帯はご利用いただけません。</p>
                        <button type="button" class="btn-back" onclick="window.reservationCalendar.closeModal()">閉じる</button>
                    </div>
                `;
                return;
            }
            
            let html = `
                <div class="duration-selection">
                    <h4>ご希望の見学時間をクリックしてください</h4>
                    <div class="duration-options">
            `;
            
            if (has60min) {
                html += `
                    <div class="duration-option" data-duration="60">
                        <div class="duration-label">60分</div>
                    </div>
                `;
            }
            
            if (has90min) {
                html += `
                    <div class="duration-option" data-duration="90">
                        <div class="duration-label">90分</div>
                    </div>
                `;
            }
            
            html += `
                    </div>
                </div>
            `;
            
            optionsContainer.innerHTML = html;
            
            // 60分・90分選択のクリックイベント
            document.querySelectorAll('.duration-option').forEach(option => {
                option.addEventListener('click', (e) => {
                    const duration = option.getAttribute('data-duration');
                    this.selectedDuration = duration;
                    this.renderTimeslotOptions(dateStr, period, duration);
                });
            });
        } else {
            // AM/PMパターン（直接時間帯配列がある場合）
            this.renderAmPmTimeslots(dateStr, period);
        }
    }
    
    renderTimeslotOptions(dateStr, period, duration) {
        const optionsContainer = document.getElementById('timeslot-options');
        
        // モーダルタイトルを更新
        const modalTitle = document.getElementById('modal-date-title');
        if (modalTitle) {
            modalTitle.textContent = '時間帯を選択';
        }
        
        // 日付情報をmodal-selected-date要素に表示
        const date = new Date(dateStr);
        const displayDate = this.formatDisplayDate(date);
        const periodLabel = period === 'am' ? '（午前）' : period === 'pm' ? '（午後）' : '';
        
        const modalDateElement = document.getElementById('modal-selected-date');
        if (modalDateElement) {
            modalDateElement.textContent = `《${displayDate}${periodLabel}》`;
            modalDateElement.style.textAlign = 'center';
        }
        
        // 期間と時間に基づく具体的な時間帯選択肢
        const timeslots = this.getTimeslotsForPeriodAndDuration(period, duration);
        
        let html = `
            <div class="timeslot-selection">
                <h4>ご希望の時間帯をクリックしてください</h4>
                <div class="timeslot-options-grid">
        `;
        
        timeslots.forEach(slot => {
            html += `
                <div class="timeslot-option" data-timeslot="${slot.id}">
                    <div class="timeslot-time">${slot.time}</div>
                </div>
            `;
        });
        
        html += `
                </div>
                <div class="back-to-duration">
                    <button type="button" class="btn-back">← 時間選択に戻る</button>
                </div>
            </div>
        `;
        
        optionsContainer.innerHTML = html;
        
        // 戻るボタンのイベント
        document.querySelector('.btn-back').addEventListener('click', () => {
            this.renderDurationOptions(dateStr, period);
        });
        
        // 時間帯選択のクリックイベント
        document.querySelectorAll('.timeslot-option').forEach(option => {
            option.addEventListener('click', (e) => {
                document.querySelectorAll('.timeslot-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                option.classList.add('selected');
                
                this.selectedTimeslot = option.getAttribute('data-timeslot');
                // 時間帯選択後、自動的に予約フォームへ遷移
                setTimeout(() => {
                    this.proceedToReservation();
                }, 500);
            });
        });
    }
    
    renderAmPmTimeslots(dateStr, period) {
        const optionsContainer = document.getElementById('timeslot-options');
        
        // モーダルタイトルを更新
        const modalTitle = document.getElementById('modal-date-title');
        if (modalTitle) {
            modalTitle.textContent = '時間帯を選択';
        }
        
        // 日付情報をmodal-selected-date要素に表示
        const date = new Date(dateStr);
        const displayDate = this.formatDisplayDate(date);
        const periodLabel = period === 'am' ? '（午前）' : period === 'pm' ? '（午後）' : '';
        
        const modalDateElement = document.getElementById('modal-selected-date');
        if (modalDateElement) {
            modalDateElement.textContent = `《${displayDate}${periodLabel}》`;
            modalDateElement.style.textAlign = 'center';
        }
        
        // AM/PMパターンの時間帯を取得
        if (!this.factoryTimeslots[period] || this.factoryTimeslots[period].length === 0) {
            optionsContainer.innerHTML = `
                <div class="no-timeslots">
                    <p>申し訳ございません。この時間帯はご利用いただけません。</p>
                    <button type="button" class="btn-back" onclick="window.reservationCalendar.closeModal()">閉じる</button>
                </div>
            `;
            return;
        }
        
        const timeslots = this.factoryTimeslots[period];
        
        let html = `
            <div class="timeslot-selection">
                <h4>ご希望の時間帯をクリックしてください</h4>
                <div class="timeslot-options-grid">
        `;
        
        timeslots.forEach((timeSlot, index) => {
            html += `
                <div class="timeslot-option" data-timeslot="${period}-${index + 1}">
                    <div class="timeslot-time">${timeSlot}</div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
        
        optionsContainer.innerHTML = html;
        
        // 時間帯選択のクリックイベント
        document.querySelectorAll('.timeslot-option').forEach(option => {
            option.addEventListener('click', (e) => {
                document.querySelectorAll('.timeslot-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                option.classList.add('selected');
                
                this.selectedTimeslot = option.getAttribute('data-timeslot');
                // 時間帯選択後、自動的に予約フォームへ遷移
                setTimeout(() => {
                    this.proceedToReservation();
                }, 500);
            });
        });
    }
    
    getTimeslotsForPeriodAndDuration(period, duration) {
        // プラグインから取得した工場設定を使用
        if (this.factoryTimeslots) {
            const durationKey = duration + 'min'; // 60 -> 60min, 90 -> 90min
            if (this.factoryTimeslots[durationKey] && this.factoryTimeslots[durationKey][period]) {
                return this.factoryTimeslots[durationKey][period].map((time, index) => ({
                    id: `${period}-${duration}-${index + 1}`,
                    time: time
                }));
            }
        }
        
        // フォールバック: デフォルト設定
        const defaultTimeslots = {
            am: {
                60: [
                    { id: 'am-60-1', time: '9:00〜10:00' },
                    { id: 'am-60-2', time: '10:30〜11:30' }
                ],
                90: [
                    { id: 'am-90-1', time: '9:00〜10:30' },
                    { id: 'am-90-2', time: '10:00〜11:30' }
                ]
            },
            pm: {
                60: [
                    { id: 'pm-60-1', time: '14:00〜15:00' },
                    { id: 'pm-60-2', time: '15:30〜16:30' }
                ],
                90: [
                    { id: 'pm-90-1', time: '14:00〜15:30' },
                    { id: 'pm-90-2', time: '15:00〜16:30' }
                ]
            }
        };
        
        return defaultTimeslots[period]?.[duration] || [];
    }
    
    closeModal() {
        document.getElementById('timeslot-modal').style.display = 'none';
        this.selectedDate = null;
        this.selectedTimeslot = null;
    }
    
    proceedToReservation() {
        if (this.selectedDate && this.selectedTimeslot) {
            // 予約フォームへ遷移
            const params = new URLSearchParams({
                factory: this.factoryId,
                date: this.selectedDate,
                timeslot: this.selectedTimeslot
            });
            
            // 予約フォームのURLを取得（ショートコード用の設定を優先）
            const reservationFormUrl = typeof calendarData !== 'undefined' && calendarData.reservationFormUrl ? 
                                     calendarData.reservationFormUrl : '/reservation-form/';
            
            window.location.href = `${reservationFormUrl}?${params.toString()}`;
        }
    }
    
    // ユーティリティ関数
    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    formatDisplayDate(date) {
        const year = date.getFullYear();
        const month = date.getMonth() + 1;
        const day = date.getDate();
        
        return `${year}年${month}月${day}日`;
    }
    
    isSameDate(date1, date2) {
        return date1.getFullYear() === date2.getFullYear() &&
               date1.getMonth() === date2.getMonth() &&
               date1.getDate() === date2.getDate();
    }
}

// DOM読み込み完了後に初期化
document.addEventListener('DOMContentLoaded', function() {
    window.reservationCalendar = new ReservationCalendar();
});

// グローバル関数として定義（HTML内のonclickから呼び出せるように）
window.openTimeslotSelection = function(dateStr, period) {
    if (window.reservationCalendar) {
        window.reservationCalendar.openTimeslotModal(dateStr, period);
    }
};