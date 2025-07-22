/**
 * カレンダーショートコード用JavaScript
 * 既存のReservationCalendarクラスを拡張
 */

class ReservationCalendarShortcode extends ReservationCalendar {
    constructor(config) {
        // 親クラスのコンストラクタをオーバーライド
        super();
        
        // ショートコード固有の設定
        this.containerId = config.containerId;
        this.factoryId = config.factoryId;
        this.monthSelectId = config.monthSelectId;
        this.gridId = config.gridId;
        this.listId = config.listId;
        this.modalId = config.modalId;
        this.modalDateId = config.modalDateId;
        this.timeslotOptionsId = config.timeslotOptionsId;
        
        // 初期化をやり直し
        this.currentMonth = new Date().getFullYear() + '-' + String(new Date().getMonth() + 1).padStart(2, '0');
        this.calendarData = {};
        this.selectedDate = null;
        this.selectedTimeslot = null;
        
        this.init();
    }
    
    // IDセレクタをオーバーライド
    bindEvents() {
        const container = document.getElementById(this.containerId);
        if (!container) return;
        
        // 月選択の変更
        const monthSelect = document.getElementById(this.monthSelectId);
        if (monthSelect) {
            monthSelect.addEventListener('change', (e) => {
                this.currentMonth = e.target.value;
                this.loadCalendarData(this.currentMonth);
            });
        }
        
        // モーダル関連
        const modal = document.getElementById(this.modalId);
        if (modal) {
            const closeBtn = modal.querySelector('.modal-close');
            const cancelBtn = modal.querySelector('.btn-cancel');
            const proceedBtn = modal.querySelector('.btn-proceed');
            
            if (closeBtn) closeBtn.addEventListener('click', () => this.closeModal());
            if (cancelBtn) cancelBtn.addEventListener('click', () => this.closeModal());
            if (proceedBtn) proceedBtn.addEventListener('click', () => this.proceedToReservation());
            
            // オーバーレイクリックで閉じる
            modal.addEventListener('click', (e) => {
                if (e.target.id === this.modalId) {
                    this.closeModal();
                }
            });
        }
    }
    
    getFactoryIdFromURL() {
        // ショートコードでは設定値を使用
        return this.factoryId;
    }
    
    showLoading() {
        // PC版
        const calendarGrid = document.getElementById(this.gridId);
        if (calendarGrid) {
            calendarGrid.innerHTML = `
                <div class="calendar-loading">
                    <div class="spinner"></div>
                    <p>カレンダーを読み込み中...</p>
                </div>
            `;
        }
        
        // スマホ版
        const calendarList = document.getElementById(this.listId);
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
        const calendarGrid = document.getElementById(this.gridId);
        if (calendarGrid) {
            calendarGrid.innerHTML = errorHtml;
        }
        
        // スマホ版
        const calendarList = document.getElementById(this.listId);
        if (calendarList) {
            calendarList.innerHTML = errorHtml;
        }
    }
    
    renderDesktopCalendar(year, month, firstDay, lastDay, today) {
        const calendarGrid = document.getElementById(this.gridId);
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
        const calendarList = document.getElementById(this.listId);
        if (!calendarList) return;
        
        let html = '';
        
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const currentDate = new Date(year, month - 1, day);
            html += this.renderMobileListItem(currentDate, today);
        }
        
        calendarList.innerHTML = html;
        
        // クリックイベントを追加
        this.addMobileClickEvents();
    }
    
    addDayClickEvents() {
        const container = document.getElementById(this.containerId);
        if (!container) return;
        
        container.querySelectorAll('.calendar-day.clickable').forEach(day => {
            day.addEventListener('click', (e) => {
                const date = day.getAttribute('data-date');
                this.openTimeslotModal(date);
            });
        });
    }
    
    addMobileClickEvents() {
        const container = document.getElementById(this.containerId);
        if (!container) return;
        
        container.querySelectorAll('.calendar-list-item.clickable').forEach(item => {
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
        
        const modalDate = document.getElementById(this.modalDateId);
        if (modalDate) {
            modalDate.textContent = displayDate;
        }
        
        // 時間帯選択肢を生成
        this.renderTimeslotOptions(dateStr);
        
        const modal = document.getElementById(this.modalId);
        if (modal) {
            modal.style.display = 'flex';
            
            // 進むボタンを無効化
            const proceedBtn = modal.querySelector('.btn-proceed');
            if (proceedBtn) {
                proceedBtn.disabled = true;
            }
        }
    }
    
    renderTimeslotOptions(dateStr) {
        const optionsContainer = document.getElementById(this.timeslotOptionsId);
        if (!optionsContainer) return;
        
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
        optionsContainer.querySelectorAll('.timeslot-option').forEach(option => {
            option.addEventListener('click', (e) => {
                optionsContainer.querySelectorAll('.timeslot-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                option.classList.add('selected');
                
                this.selectedTimeslot = option.getAttribute('data-timeslot');
                
                const modal = document.getElementById(this.modalId);
                if (modal) {
                    const proceedBtn = modal.querySelector('.btn-proceed');
                    if (proceedBtn) {
                        proceedBtn.disabled = false;
                    }
                }
            });
        });
    }
    
    closeModal() {
        const modal = document.getElementById(this.modalId);
        if (modal) {
            modal.style.display = 'none';
        }
        this.selectedDate = null;
        this.selectedTimeslot = null;
    }
}