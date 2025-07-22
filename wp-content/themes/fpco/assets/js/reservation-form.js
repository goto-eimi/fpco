/**
 * 予約フォーム JavaScript
 */

class ReservationForm {
    constructor() {
        this.form = document.getElementById('reservation-form');
        this.maxVisitors = 50;
        this.totalVisitors = 0;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.validateForm();
    }
    
    bindEvents() {
        // 旅行会社の切り替え
        document.querySelectorAll('input[name="is_travel_agency"]').forEach(radio => {
            radio.addEventListener('change', () => this.toggleTravelAgencySection());
        });
        
        // 交通機関の切り替え
        document.querySelectorAll('input[name="transportation"]').forEach(radio => {
            radio.addEventListener('change', () => this.toggleTransportationFields());
        });
        
        // 見学者分類の切り替え
        document.querySelectorAll('input[name="visitor_category"]').forEach(radio => {
            radio.addEventListener('change', () => this.toggleVisitorCategoryFields());
        });
        
        // 郵便番号検索
        document.querySelectorAll('.btn-postal-search').forEach(btn => {
            btn.addEventListener('click', (e) => this.searchByPostalCode(e));
        });
        
        // 文字数カウンター
        const purposeField = document.getElementById('purpose');
        if (purposeField) {
            purposeField.addEventListener('input', () => this.updateCharCounter());
        }
        
        // リアルタイムバリデーション
        const inputs = this.form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => {
                this.validateField(input);
                this.validateForm();
            });
        });
        
        // フォーム送信
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
    }
    
    toggleTravelAgencySection() {
        const isTravelAgency = document.querySelector('input[name="is_travel_agency"]:checked').value === 'yes';
        const section = document.getElementById('travel-agency-section');
        
        if (isTravelAgency) {
            section.style.display = 'block';
            this.setRequiredFields(section, true);
        } else {
            section.style.display = 'none';
            this.setRequiredFields(section, false);
            this.clearFields(section);
        }
        
        this.validateForm();
    }
    
    toggleTransportationFields() {
        const selectedTransportation = document.querySelector('input[name="transportation"]:checked').value;
        const otherGroup = document.getElementById('transportation-other-group');
        const vehicleGroup = document.getElementById('vehicle-count-group');
        
        // その他の表示/非表示
        if (selectedTransportation === 'other') {
            otherGroup.style.display = 'block';
            otherGroup.querySelector('input').required = true;
        } else {
            otherGroup.style.display = 'none';
            otherGroup.querySelector('input').required = false;
            otherGroup.querySelector('input').value = '';
        }
        
        // 台数入力の表示/非表示
        if (selectedTransportation === 'car' || selectedTransportation === 'chartered_bus') {
            vehicleGroup.style.display = 'block';
            vehicleGroup.querySelector('input').required = true;
        } else {
            vehicleGroup.style.display = 'none';
            vehicleGroup.querySelector('input').required = false;
            vehicleGroup.querySelector('input').value = '';
        }
        
        this.validateForm();
    }
    
    toggleVisitorCategoryFields() {
        const selectedCategory = document.querySelector('input[name="visitor_category"]:checked').value;
        const categoryDetails = document.getElementById('category-details');
        
        categoryDetails.style.display = 'block';
        categoryDetails.innerHTML = this.generateCategoryFields(selectedCategory);
        
        // 新しく生成されたフィールドにイベントリスナーを追加
        const newInputs = categoryDetails.querySelectorAll('input, select');
        newInputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => {
                this.validateField(input);
                this.updateVisitorCount();
                this.validateForm();
            });
        });
        
        this.updateVisitorCount();
        this.validateForm();
    }
    
    generateCategoryFields(category) {
        let html = '<h2>見学者様詳細情報</h2>';
        
        switch (category) {
            case 'school':
                html += `
                    <div class="form-group required">
                        <label for="school_name">学校・団体名</label>
                        <input type="text" id="school_name" name="school_name" placeholder="ABC小学校" required>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group required">
                        <label for="school_name_kana">学校・団体名（ふりがな）</label>
                        <input type="text" id="school_name_kana" name="school_name_kana" placeholder="えーびーしーしょうがっこう" required>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group">
                        <label for="representative_name">代表者様氏名</label>
                        <input type="text" id="representative_name" name="representative_name" placeholder="山田 太郎">
                    </div>
                    <div class="form-group">
                        <label for="representative_name_kana">代表者様氏名（ふりがな）</label>
                        <input type="text" id="representative_name_kana" name="representative_name_kana" placeholder="やまだ たろう">
                    </div>
                    <div class="form-group required">
                        <label for="grade">学年</label>
                        <div class="input-with-unit">
                            <input type="number" id="grade" name="grade" min="1" max="12" required>
                            <span class="unit">年生</span>
                        </div>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group required">
                        <label for="class_count">クラス数</label>
                        <div class="input-with-unit">
                            <input type="number" id="class_count" name="class_count" min="1" max="20" required>
                            <span class="unit">クラス</span>
                        </div>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group required">
                        <label for="student_count">見学者様人数（児童・生徒）</label>
                        <div class="input-with-unit">
                            <input type="number" id="student_count" name="student_count" min="1" max="50" required>
                            <span class="unit">名</span>
                        </div>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group required">
                        <label for="teacher_count">見学者様人数（引率）</label>
                        <div class="input-with-unit">
                            <input type="number" id="teacher_count" name="teacher_count" min="1" max="10" required>
                            <span class="unit">名</span>
                        </div>
                        <span class="error-message"></span>
                    </div>
                `;
                break;
                
            case 'recruit':
                html += `
                    <div class="form-group required">
                        <label for="recruit_school_name">学校名</label>
                        <input type="text" id="recruit_school_name" name="recruit_school_name" placeholder="ABC大学" required>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group required">
                        <label for="recruit_grade">学年</label>
                        <div class="input-with-unit">
                            <input type="number" id="recruit_grade" name="recruit_grade" min="1" max="6" required>
                            <span class="unit">年生</span>
                        </div>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group required">
                        <label for="recruit_department">学部</label>
                        <input type="text" id="recruit_department" name="recruit_department" placeholder="工学部" required>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group required">
                        <label for="recruit_visitor_count">見学者様人数</label>
                        <div class="input-with-unit">
                            <input type="number" id="recruit_visitor_count" name="recruit_visitor_count" min="1" max="10" required>
                            <span class="unit">名</span>
                        </div>
                        <span class="error-message"></span>
                    </div>
                    <div id="companion-fields">
                        <!-- 同行者情報は人数入力後に動的生成 -->
                    </div>
                `;
                break;
                
            case 'family':
                html += `
                    <div class="form-group required">
                        <label for="adult_count">見学者様人数（大人）</label>
                        <div class="input-with-unit">
                            <input type="number" id="adult_count" name="adult_count" min="1" max="20" required>
                            <span class="unit">名</span>
                        </div>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group required">
                        <label for="child_count">見学者様人数（子ども）</label>
                        <div class="input-with-unit">
                            <input type="number" id="child_count" name="child_count" min="0" max="20" required>
                            <span class="unit">名</span>
                        </div>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group conditional" id="child-grade-group" style="display: none;">
                        <label for="child_grades">学年（子どもが1名以上の場合）</label>
                        <input type="text" id="child_grades" name="child_grades" placeholder="例：小学3年生、小学5年生">
                        <span class="error-message"></span>
                    </div>
                `;
                break;
                
            default: // company, government, other
                html += `
                    <div class="form-group required">
                        <label for="organization_name">会社・団体名</label>
                        <input type="text" id="organization_name" name="organization_name" placeholder="株式会社エフピコ" required>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group required">
                        <label for="organization_name_kana">会社・団体名（ふりがな）</label>
                        <input type="text" id="organization_name_kana" name="organization_name_kana" placeholder="かぶしきかいしゃえふぴこ" required>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group required">
                        <label for="org_adult_count">見学者様人数（大人）</label>
                        <div class="input-with-unit">
                            <input type="number" id="org_adult_count" name="org_adult_count" min="1" max="50" required>
                            <span class="unit">名</span>
                        </div>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group required">
                        <label for="org_child_count">見学者様人数（子ども）</label>
                        <div class="input-with-unit">
                            <input type="number" id="org_child_count" name="org_child_count" min="0" max="20" required>
                            <span class="unit">名</span>
                        </div>
                        <span class="error-message"></span>
                    </div>
                    <div class="form-group conditional" id="org-child-grade-group" style="display: none;">
                        <label for="org_child_grades">学年（子どもが1名以上の場合）</label>
                        <input type="text" id="org_child_grades" name="org_child_grades" placeholder="例：小学3年生、小学5年生">
                        <span class="error-message"></span>
                    </div>
                `;
                break;
        }
        
        return html;
    }
    
    updateVisitorCount() {
        let total = 0;
        const category = document.querySelector('input[name="visitor_category"]:checked')?.value;
        
        switch (category) {
            case 'school':
                const studentCount = parseInt(document.getElementById('student_count')?.value) || 0;
                const teacherCount = parseInt(document.getElementById('teacher_count')?.value) || 0;
                total = studentCount + teacherCount;
                break;
                
            case 'recruit':
                total = parseInt(document.getElementById('recruit_visitor_count')?.value) || 0;
                break;
                
            case 'family':
                const adultCount = parseInt(document.getElementById('adult_count')?.value) || 0;
                const childCount = parseInt(document.getElementById('child_count')?.value) || 0;
                total = adultCount + childCount;
                
                // 子どもの学年入力フィールドの表示/非表示
                const childGradeGroup = document.getElementById('child-grade-group');
                if (childGradeGroup) {
                    if (childCount > 0) {
                        childGradeGroup.style.display = 'block';
                        childGradeGroup.querySelector('input').required = true;
                    } else {
                        childGradeGroup.style.display = 'none';
                        childGradeGroup.querySelector('input').required = false;
                    }
                }
                break;
                
            default:
                const orgAdultCount = parseInt(document.getElementById('org_adult_count')?.value) || 0;
                const orgChildCount = parseInt(document.getElementById('org_child_count')?.value) || 0;
                total = orgAdultCount + orgChildCount;
                
                // 子どもの学年入力フィールドの表示/非表示
                const orgChildGradeGroup = document.getElementById('org-child-grade-group');
                if (orgChildGradeGroup) {
                    if (orgChildCount > 0) {
                        orgChildGradeGroup.style.display = 'block';
                        orgChildGradeGroup.querySelector('input').required = true;
                    } else {
                        orgChildGradeGroup.style.display = 'none';
                        orgChildGradeGroup.querySelector('input').required = false;
                    }
                }
                break;
        }
        
        this.totalVisitors = total;
        
        // 人数表示を更新
        const countValue = document.querySelector('.count-value');
        if (countValue) {
            countValue.textContent = total;
        }
        
        // 警告表示
        const warning = document.querySelector('.count-warning');
        if (warning) {
            if (total > this.maxVisitors) {
                warning.style.display = 'block';
            } else {
                warning.style.display = 'none';
            }
        }
    }
    
    searchByPostalCode(e) {
        const button = e.target;
        const group = button.closest('.postal-code-group');
        const postalInput = group.querySelector('input');
        const postalCode = postalInput.value.replace(/[^0-9]/g, '');
        
        if (postalCode.length !== 7) {
            alert('郵便番号は7桁で入力してください');
            return;
        }
        
        // 実際のAPIを使用する場合はここでAPIコール
        // デモ用の仮実装
        this.mockAddressSearch(postalCode, button);
    }
    
    mockAddressSearch(postalCode, button) {
        // 実際の実装では郵便番号APIを使用
        const mockData = {
            '7218607': {
                prefecture: '広島県',
                city: '福山市',
                address: '曙町'
            }
        };
        
        const data = mockData[postalCode];
        if (data) {
            // 申込者か旅行会社かを判定
            const isAgency = button.id === 'agency_postal_code' || 
                           button.closest('#travel-agency-section');
            
            const prefix = isAgency ? 'agency_' : '';
            
            const prefectureSelect = document.getElementById(prefix + 'prefecture');
            const cityInput = document.getElementById(prefix + 'city');
            const addressInput = document.getElementById(prefix + 'address');
            
            if (prefectureSelect) prefectureSelect.value = data.prefecture;
            if (cityInput) cityInput.value = data.city;
            if (addressInput) addressInput.value = data.address;
            
            alert('住所を自動入力しました');
        } else {
            alert('該当する住所が見つかりませんでした');
        }
    }
    
    updateCharCounter() {
        const purposeField = document.getElementById('purpose');
        const counter = document.querySelector('.char-counter');
        
        if (purposeField && counter) {
            const length = purposeField.value.length;
            counter.textContent = `${length} / 500`;
            
            if (length > 500) {
                counter.style.color = '#dc3545';
            } else {
                counter.style.color = '#666';
            }
        }
    }
    
    validateField(field) {
        const formGroup = field.closest('.form-group');
        const errorMessage = formGroup.querySelector('.error-message');
        let isValid = true;
        let message = '';
        
        // 必須項目チェック
        if (field.required && !field.value.trim()) {
            isValid = false;
            message = `※ ${field.previousElementSibling.textContent.replace(' *', '')}は必須項目です`;
        }
        
        // 各種形式チェック
        if (field.value.trim()) {
            switch (field.type) {
                case 'email':
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(field.value)) {
                        isValid = false;
                        message = '正しいメールアドレスを入力してください';
                    }
                    break;
                    
                case 'tel':
                    const phoneRegex = /^[0-9]{10,11}$/;
                    if (!phoneRegex.test(field.value.replace(/[^0-9]/g, ''))) {
                        isValid = false;
                        message = '正しい電話番号を入力してください';
                    }
                    break;
            }
        }
        
        // 特別なバリデーション
        if (field.id === 'postal_code' || field.id === 'agency_postal_code') {
            const postalRegex = /^[0-9]{7}$/;
            if (field.value && !postalRegex.test(field.value)) {
                isValid = false;
                message = '郵便番号は7桁の数字で入力してください';
            }
        }
        
        // エラー表示の更新
        if (isValid) {
            formGroup.classList.remove('error');
        } else {
            formGroup.classList.add('error');
            if (errorMessage) {
                errorMessage.textContent = message;
            }
        }
        
        return isValid;
    }
    
    validateForm() {
        const submitBtn = document.querySelector('.btn-submit');
        let isValid = true;
        
        // 必須項目のチェック
        const requiredFields = this.form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        // 人数制限チェック
        if (this.totalVisitors > this.maxVisitors) {
            isValid = false;
        }
        
        // 送信ボタンの状態更新
        if (submitBtn) {
            submitBtn.disabled = !isValid;
        }
        
        return isValid;
    }
    
    setRequiredFields(container, required) {
        const fields = container.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            if (required && field.classList.contains('required')) {
                field.required = true;
            } else {
                field.required = false;
            }
        });
    }
    
    clearFields(container) {
        const fields = container.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            field.value = '';
        });
    }
    
    handleSubmit(e) {
        e.preventDefault();
        
        if (!this.validateForm()) {
            alert('入力内容を確認してください');
            return;
        }
        
        // フォームデータを確認画面に送信
        this.form.submit();
    }
}

// DOM読み込み完了後に初期化
document.addEventListener('DOMContentLoaded', function() {
    new ReservationForm();
});