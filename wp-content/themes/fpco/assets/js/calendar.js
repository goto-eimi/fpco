/**
 * 予約状況カレンダー JavaScript
 */

class ReservationCalendar {
    constructor() {
        this.currentMonth = new Date().getFullYear() + '-' + String(new Date().getMonth() + 1).padStart(2, '0');
        this.factoryId = this.getFactoryIdFromURL();
        this.calendarData = {};
        this.selectedDate = null;
        this.selectedTimeslot = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadCalendarData(this.currentMonth);
    }
    
    bindEvents() {
        // 月選択の変更
        document.getElementById('calendar-month-select').addEventListener('change', (e) => {
            this.currentMonth = e.target.value;
            this.loadCalendarData(this.currentMonth);
        });
        
        // モーダル関連
        document.querySelector('.modal-close').addEventListener('click', () => this.closeModal());
        document.querySelector('.btn-cancel').addEventListener('click', () => this.closeModal());
        document.querySelector('.btn-proceed').addEventListener('click', () => this.proceedToReservation());
        
        // オーバーレイクリックで閉じる
        document.getElementById('timeslot-modal').addEventListener('click', (e) => {
            if (e.target.id === 'timeslot-modal') {
                this.closeModal();
            }
        });
    }
    
    getFactoryIdFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('factory') || '1';
    }
    
    async loadCalendarData(yearMonth) {
        try {
            this.showLoading();
            
            const response = await fetch(`/wp-json/reservation/v1/calendar?month=${yearMonth}&factory=${this.factoryId}`);
            
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
        if (dayData.clickable && !isPast) classes.push('clickable');
        
        let dayNumberClass = 'day-number';
        if (weekday === 0) dayNumberClass += ' sunday';
        if (weekday === 6) dayNumberClass += ' saturday';
        
        let timeSlotsHtml = '';
        if (!isOtherMonth && !isPast) {
            timeSlotsHtml = `
                <div class="time-slots">
                    <div class="time-slot">
                        <span class="time-label">AM</span>
                        <span class="status-symbol ${dayData.am.status}">${dayData.am.symbol}</span>
                    </div>
                    <div class="time-slot">
                        <span class="time-label">PM</span>
                        <span class="status-symbol ${dayData.pm.status}">${dayData.pm.symbol}</span>
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
        
        // SPECIFICATION.mdに基づく1行形式のレイアウト
        return `
            <div class="${classes.join(' ')}" data-date="${dateStr}">
                <div class="list-content">
                    <span class="${dayNumberClass}">${dayNumber}</span>
                    <span class="list-weekday">${weekdayNames[weekday]}</span>
                    <span class="list-am-slot">
                        <span class="slot-label">AM</span>
                        <span class="status-symbol ${dayData.am.status}">${dayData.am.symbol}</span>
                    </span>
                    <span class="list-pm-slot">
                        <span class="slot-label">PM</span>
                        <span class="status-symbol ${dayData.pm.status}">${dayData.pm.symbol}</span>
                    </span>
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
        
        // フォールバック: APIデータが取得できない場合のデフォルト値
        const date = new Date(dateStr);
        const weekday = date.getDay();
        
        if (weekday === 0 || weekday === 6) {
            return {
                clickable: false,
                am: { status: 'unavailable', symbol: '－' },
                pm: { status: 'unavailable', symbol: '－' }
            };
        }
        
        return {
            clickable: true,
            am: { status: 'available', symbol: '◯' },
            pm: { status: 'available', symbol: '◯' }
        };
    }
    
    addDayClickEvents() {
        document.querySelectorAll('.calendar-day.clickable').forEach(day => {
            day.addEventListener('click', (e) => {
                const date = day.getAttribute('data-date');
                this.openTimeslotModal(date);
            });
        });
    }
    
    addMobileClickEvents() {
        document.querySelectorAll('.calendar-list-item.clickable').forEach(item => {
            item.addEventListener('click', (e) => {
                const date = item.getAttribute('data-date');
                this.openTimeslotModal(date);
            });
        });
    }
    
    openTimeslotModal(dateStr) {
        this.selectedDate = dateStr;
        this.selectedTimeslot = null;
        
        const date = new Date(dateStr);
        const displayDate = this.formatDisplayDate(date);
        
        document.getElementById('modal-selected-date').textContent = displayDate;
        
        // 時間帯選択肢を生成
        this.renderTimeslotOptions(dateStr);
        
        document.getElementById('timeslot-modal').style.display = 'flex';
        
        // 進むボタンを無効化
        document.querySelector('.btn-proceed').disabled = true;
    }
    
    renderTimeslotOptions(dateStr) {
        const optionsContainer = document.getElementById('timeslot-options');
        
        // TODO: 工場の時間帯設定に基づいて動的に生成
        // デモとして固定の時間帯を表示
        const timeslots = [
            { id: 'am', label: 'AM（午前）', times: ['9:00〜10:00', '10:30〜11:30'] },
            { id: 'pm', label: 'PM（午後）', times: ['14:00〜15:00', '15:30〜16:30'] }
        ];
        
        let html = '';
        timeslots.forEach(slot => {
            html += `
                <div class="timeslot-option" data-timeslot="${slot.id}">
                    <div class="timeslot-label">${slot.label}</div>
                    <div class="timeslot-times">${slot.times.join(', ')}</div>
                </div>
            `;
        });
        
        optionsContainer.innerHTML = html;
        
        // クリックイベントを追加
        document.querySelectorAll('.timeslot-option').forEach(option => {
            option.addEventListener('click', (e) => {
                document.querySelectorAll('.timeslot-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                option.classList.add('selected');
                
                this.selectedTimeslot = option.getAttribute('data-timeslot');
                document.querySelector('.btn-proceed').disabled = false;
            });
        });
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
            
            // TODO: 実際の予約フォームURLに変更
            window.location.href = `/reservation-form?${params.toString()}`;
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
        const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        const weekday = weekdays[date.getDay()];
        
        return `${year}年${month}月${day}日（${weekday}）`;
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