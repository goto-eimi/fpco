/**
 * フロントエンド用カレンダーJavaScript
 * 既存のテーマファイル（page-calendar.php）と統合プラグインのAPIを連携
 */

// グローバル変数
window.reservationCalendar = null;

document.addEventListener('DOMContentLoaded', function() {
    
    // カレンダーインスタンスを作成
    window.reservationCalendar = new ReservationCalendar();
    
    class ReservationCalendar {
        constructor() {
            this.currentFactory = this.getFactoryFromUrl() || 1;
            this.currentDate = new Date();
            this.calendarData = {};
            
            this.init();
        }
        
        init() {
            this.setupEventListeners();
            this.loadCalendarData();
        }
        
        setupEventListeners() {
            // 月変更セレクター
            const monthSelect = document.getElementById('calendar-month-select');
            if (monthSelect) {
                monthSelect.addEventListener('change', (e) => {
                    const [year, month] = e.target.value.split('-');
                    this.currentDate = new Date(year, month - 1, 1);
                    this.loadCalendarData();
                });
            }
            
            // モーダルクローズ
            const closeBtn = document.querySelector('.modal-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.closeModal();
                });
            }
            
            // モーダル背景クリック
            const modalOverlay = document.getElementById('timeslot-modal');
            if (modalOverlay) {
                modalOverlay.addEventListener('click', (e) => {
                    if (e.target === modalOverlay) {
                        this.closeModal();
                    }
                });
            }
        }
        
        getFactoryFromUrl() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('factory') || urlParams.get('factory_id');
        }
        
        async loadCalendarData() {
            try {
                this.showLoading();
                
                const year = this.currentDate.getFullYear();
                const month = this.currentDate.getMonth() + 1;
                
                // 統合プラグインのAPIエンドポイントを使用
                const response = await fetch(fpco_calendar_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'get_reservation_data',
                        factory_id: this.currentFactory,
                        year: year,
                        month: month,
                        nonce: fpco_calendar_ajax.nonce
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.calendarData = data.data.calendar_data;
                    this.renderCalendar();
                } else {
                    console.error('カレンダーデータの取得に失敗:', data);
                    this.showError('カレンダーデータの読み込みに失敗しました。');
                }
                
            } catch (error) {
                console.error('エラー:', error);
                this.showError('カレンダーデータの読み込み中にエラーが発生しました。');
            }
        }
        
        renderCalendar() {
            this.renderDesktopCalendar();
            this.renderMobileCalendar();
        }
        
        renderDesktopCalendar() {
            const container = document.getElementById('calendar-grid');
            if (!container) return;
            
            container.innerHTML = '';
            
            // 曜日ヘッダー
            const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
            weekdays.forEach(day => {
                const header = document.createElement('div');
                header.className = 'calendar-day-header';
                header.textContent = day;
                container.appendChild(header);
            });
            
            // カレンダーの日付を生成
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - firstDay.getDay());
            
            for (let i = 0; i < 42; i++) {
                const currentDate = new Date(startDate);
                currentDate.setDate(startDate.getDate() + i);
                
                const dayElement = this.createDayElement(currentDate, month);
                container.appendChild(dayElement);
            }
        }
        
        renderMobileCalendar() {
            const container = document.getElementById('calendar-list');
            if (!container) return;
            
            container.innerHTML = '';
            
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            
            for (let day = 1; day <= lastDay.getDate(); day++) {
                const currentDate = new Date(year, month, day);
                const dayElement = this.createMobileDayElement(currentDate);
                container.appendChild(dayElement);
            }
        }
        
        createDayElement(date, currentMonth) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            
            const today = new Date();
            const isToday = this.isSameDate(date, today);
            const isPast = date < today && !isToday;
            const isOtherMonth = date.getMonth() !== currentMonth;
            
            if (isToday) dayElement.classList.add('today');
            if (isPast) dayElement.classList.add('past');
            if (isOtherMonth) dayElement.classList.add('other-month');
            
            // 日付番号
            const dayNumber = document.createElement('span');
            dayNumber.className = 'day-number';
            dayNumber.textContent = date.getDate();
            
            if (date.getDay() === 0) dayNumber.classList.add('sunday');
            if (date.getDay() === 6) dayNumber.classList.add('saturday');
            
            dayElement.appendChild(dayNumber);
            
            // 過去の日付でない場合のみ時間帯を表示
            if (!isPast && !isOtherMonth) {
                const timeSlotsElement = this.createTimeSlots(date);
                dayElement.appendChild(timeSlotsElement);
                dayElement.classList.add('clickable');
            }
            
            return dayElement;
        }
        
        createMobileDayElement(date) {
            const listItem = document.createElement('div');
            listItem.className = 'calendar-list-item';
            
            const today = new Date();
            const isToday = this.isSameDate(date, today);
            const isPast = date < today && !isToday;
            
            if (isToday) listItem.classList.add('today');
            if (isPast) listItem.classList.add('past');
            
            const content = document.createElement('div');
            content.className = 'list-content';
            
            // 日付セクション
            const dateSection = document.createElement('div');
            dateSection.className = 'list-date-section';
            const dayNumber = document.createElement('div');
            dayNumber.className = 'list-day-number';
            dayNumber.textContent = date.getDate();
            if (date.getDay() === 0) dayNumber.classList.add('sunday');
            if (date.getDay() === 6) dayNumber.classList.add('saturday');
            dateSection.appendChild(dayNumber);
            
            // 曜日セクション
            const weekdaySection = document.createElement('div');
            weekdaySection.className = 'list-weekday-section';
            const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
            weekdaySection.innerHTML = `<div class="list-weekday">${weekdays[date.getDay()]}</div>`;
            
            // 時間セクション
            const timeSection = document.createElement('div');
            timeSection.className = 'list-time-section';
            
            if (!isPast) {
                const amSlot = this.createMobileTimeSlot(date, 'AM');
                const pmSlot = this.createMobileTimeSlot(date, 'PM');
                timeSection.appendChild(amSlot);
                timeSection.appendChild(pmSlot);
            }
            
            content.appendChild(dateSection);
            content.appendChild(weekdaySection);
            content.appendChild(timeSection);
            listItem.appendChild(content);
            
            return listItem;
        }
        
        createTimeSlots(date) {
            const timeSlotsContainer = document.createElement('div');
            timeSlotsContainer.className = 'time-slots';
            
            // AM枠
            const amSlot = document.createElement('div');
            amSlot.className = 'time-slot';
            amSlot.innerHTML = `
                <span class="time-label">AM</span>
                ${this.createStatusButton(date, 'AM')}
            `;
            
            // PM枠
            const pmSlot = document.createElement('div');
            pmSlot.className = 'time-slot';
            pmSlot.innerHTML = `
                <span class="time-label">PM</span>
                ${this.createStatusButton(date, 'PM')}
            `;
            
            timeSlotsContainer.appendChild(amSlot);
            timeSlotsContainer.appendChild(pmSlot);
            
            return timeSlotsContainer;
        }
        
        createMobileTimeSlot(date, period) {
            const slot = document.createElement('div');
            slot.className = `list-${period.toLowerCase()}-slot`;
            slot.innerHTML = `
                <span class="time-label">${period}</span>
                ${this.createMobileStatusButton(date, period)}
            `;
            return slot;
        }
        
        createStatusButton(date, period) {
            const dateStr = this.formatDate(date);
            const status = this.getStatusForDatePeriod(dateStr, period);
            
            if (status === 'available') {
                return `<button class="status-button available" onclick="openTimeslotSelection('${dateStr}', '${period}')">〇</button>`;
            } else if (status === 'adjusting') {
                return `<span class="status-button adjusting">△</span>`;
            } else if (status === 'unavailable') {
                return `<span class="status-button unavailable">－</span>`;
            } else {
                return `<span class="status-button none"></span>`;
            }
        }
        
        createMobileStatusButton(date, period) {
            const dateStr = this.formatDate(date);
            const status = this.getStatusForDatePeriod(dateStr, period);
            
            if (status === 'available') {
                return `<button class="mobile-status-button available" onclick="openTimeslotSelection('${dateStr}', '${period}')">〇</button>`;
            } else if (status === 'adjusting') {
                return `<span class="mobile-status-button adjusting">△</span>`;
            } else if (status === 'unavailable') {
                return `<span class="mobile-status-button unavailable">－</span>`;
            } else {
                return `<span class="mobile-status-button none"></span>`;
            }
        }
        
        getStatusForDatePeriod(dateStr, period) {
            const dayData = this.calendarData[dateStr];
            if (!dayData) return 'available';
            
            const periodData = dayData[period];
            if (!periodData) return 'available';
            
            return periodData.status;
        }
        
        openTimeslotModal(dateStr, period) {
            const modal = document.getElementById('timeslot-modal');
            const selectedDateEl = document.getElementById('modal-selected-date');
            const optionsContainer = document.getElementById('timeslot-options');
            
            if (!modal || !selectedDateEl || !optionsContainer) return;
            
            // 日付表示を更新
            const date = new Date(dateStr);
            const formattedDate = `${date.getFullYear()}年${date.getMonth() + 1}月${date.getDate()}日`;
            selectedDateEl.textContent = formattedDate;
            
            // 時間帯オプションを生成
            optionsContainer.innerHTML = this.generateTimeslotOptions(period);
            
            // モーダルを表示
            modal.style.display = 'flex';
        }
        
        generateTimeslotOptions(period) {
            const timeslots = this.getTimeslotsByPeriod(period);
            
            return timeslots.map(slot => `
                <div class="timeslot-option" onclick="this.selectTimeslot('${slot.value}')">
                    <div class="timeslot-time">${slot.label}</div>
                </div>
            `).join('');
        }
        
        getTimeslotsByPeriod(period) {
            if (period === 'AM') {
                return [
                    { value: 'am-60-1', label: '9:00～10:00 (60分)' },
                    { value: 'am-90-1', label: '9:00～10:30 (90分)' },
                    { value: 'am-60-2', label: '10:30～11:30 (60分)' },
                    { value: 'am-90-2', label: '10:00～11:30 (90分)' }
                ];
            } else {
                return [
                    { value: 'pm-60-1', label: '14:00～15:00 (60分)' },
                    { value: 'pm-90-1', label: '14:00～15:30 (90分)' },
                    { value: 'pm-60-2', label: '15:30～16:30 (60分)' },
                    { value: 'pm-90-2', label: '15:00～16:30 (90分)' }
                ];
            }
        }
        
        selectTimeslot(timeslot) {
            // 予約フォームページに遷移
            const factory = this.currentFactory;
            const date = document.getElementById('modal-selected-date').textContent;
            
            // 予約フォームのURLを構築
            const formUrl = new URL('/reservation-form/', window.location.origin);
            formUrl.searchParams.set('factory_id', factory);
            formUrl.searchParams.set('timeslot', timeslot);
            
            window.location.href = formUrl.toString();
        }
        
        closeModal() {
            const modal = document.getElementById('timeslot-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
        
        formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        
        isSameDate(date1, date2) {
            return date1.getFullYear() === date2.getFullYear() &&
                   date1.getMonth() === date2.getMonth() &&
                   date1.getDate() === date2.getDate();
        }
        
        showLoading() {
            const containers = ['calendar-grid', 'calendar-list'];
            containers.forEach(containerId => {
                const container = document.getElementById(containerId);
                if (container) {
                    container.innerHTML = `
                        <div class="calendar-loading">
                            <div class="spinner"></div>
                            <p>カレンダーを読み込み中...</p>
                        </div>
                    `;
                }
            });
        }
        
        showError(message) {
            const containers = ['calendar-grid', 'calendar-list'];
            containers.forEach(containerId => {
                const container = document.getElementById(containerId);
                if (container) {
                    container.innerHTML = `
                        <div class="calendar-loading">
                            <p style="color: red;">${message}</p>
                        </div>
                    `;
                }
            });
        }
    }
});

// グローバル関数（HTML内のonclickから呼び出し用）
function openTimeslotSelection(dateStr, period) {
    if (window.reservationCalendar) {
        window.reservationCalendar.openTimeslotModal(dateStr, period);
    }
}