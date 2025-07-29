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
        
        // 見学者人数の変更（recruit用の同行者フィールド生成）
        document.addEventListener('change', (e) => {
            if (e.target.id === 'recruit_visitor_count') {
                this.updateCompanionFields(e.target.value);
            }
        });
        
        // 子ども人数の変更（学年フィールドの表示/非表示）
        document.addEventListener('change', (e) => {
            if (e.target.name && e.target.name.includes('_child_count')) {
                this.toggleChildGradeField(e.target);
            }
        });
        
        // 削除: 自動計算機能は不要になったため
        
        // 郵便番号入力処理（フォーマット+自動検索）
        document.querySelectorAll('.postal-code-input').forEach(input => {
            input.addEventListener('input', (e) => {
                // 数字以外を削除
                let value = e.target.value.replace(/[^0-9]/g, '');
                // 7桁に制限
                if (value.length > 7) {
                    value = value.slice(0, 7);
                }
                
                // 値を設定
                e.target.value = value;
                
                // 7桁入力されたら自動で住所検索
                if (value.length === 7) {
                    const target = e.target.getAttribute('data-target');
                    this.fetchAddressFromAPI(value, target);
                }
            });
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
        const otherInline = document.getElementById('transportation-other-inline');
        const vehicleInline = document.getElementById('vehicle-count-inline');
        
        // その他の表示/非表示
        if (selectedTransportation === 'other') {
            otherInline.style.display = 'inline-flex';
            otherInline.querySelector('input').required = true;
        } else {
            otherInline.style.display = 'none';
            otherInline.querySelector('input').required = false;
            otherInline.querySelector('input').value = '';
        }
        
        // 台数入力の表示/非表示
        if (selectedTransportation === 'car' || selectedTransportation === 'chartered_bus' || selectedTransportation === 'taxi') {
            vehicleInline.style.display = 'flex';
            vehicleInline.querySelector('input').required = true;
        } else {
            vehicleInline.style.display = 'none';
            vehicleInline.querySelector('input').required = false;
            vehicleInline.querySelector('input').value = '';
        }
        
        this.validateForm();
    }
    
    toggleVisitorCategoryFields() {
        const selectedCategory = document.querySelector('input[name="visitor_category"]:checked')?.value;
        
        // 全ての詳細セクションを非表示にする
        const allDetailSections = [
            'school-details',
            'recruit-details', 
            'family-details',
            'company-details',
            'government-details',
            'other-details'
        ];
        
        allDetailSections.forEach(sectionId => {
            const section = document.getElementById(sectionId);
            if (section) {
                section.style.display = 'none';
                this.setRequiredFields(section, false);
                this.clearFields(section);
            }
        });
        
        // 選択された分類に応じて対応するセクションを表示
        if (selectedCategory) {
            const targetSectionId = selectedCategory + '-details';
            const targetSection = document.getElementById(targetSectionId);
            
            if (targetSection) {
                targetSection.style.display = 'block';
                this.setRequiredFields(targetSection, true);
            }
        }
        
        this.validateForm();
    }
    
    updateCompanionFields(visitorCount) {
        const companionContainer = document.getElementById('companion-fields');
        if (!companionContainer) return;
        
        // 既存の同行者フィールドをクリア
        companionContainer.innerHTML = '';
        
        const count = parseInt(visitorCount) || 0;
        const companionCount = Math.max(0, count - 1); // 申込者を除く
        
        // 同行者フィールドを動的に生成（最大8名まで）
        for (let i = 1; i <= Math.min(companionCount, 8); i++) {
            const companionHTML = `
                <div class="info-row">
                    <span class="info-label">同行者様${this.numberToCircle(i)}</span>
                    <span class="info-input">
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <input type="text" id="companion_${i}_name" name="companion_${i}_name" placeholder="山田 太郎" class="required" style="width: 100%;">
                                <small style="color: #666; font-size: 11px;">氏名</small>
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <input type="text" id="companion_${i}_department" name="companion_${i}_department" placeholder="工学部" class="required" style="width: 100%;">
                                <small style="color: #666; font-size: 11px;">学部</small>
                            </div>
                        </div>
                    </span>
                </div>
            `;
            companionContainer.insertAdjacentHTML('beforeend', companionHTML);
        }
        
        this.validateForm();
    }
    
    numberToCircle(num) {
        const circles = ['①', '②', '③', '④', '⑤', '⑥', '⑦', '⑧'];
        return circles[num - 1] || num.toString();
    }
    
    toggleChildGradeField(childCountField) {
        const fieldName = childCountField.name;
        const category = fieldName.split('_')[0]; // family, company, government, other
        const gradeField = document.querySelector(`.conditional-child-grade input[id="${category}_child_grade"]`);
        const gradeRow = gradeField?.closest('.conditional-child-grade');
        
        if (gradeRow) {
            const childCount = parseInt(childCountField.value) || 0;
            if (childCount > 0) {
                gradeRow.style.display = 'block';
                gradeField.classList.add('required');
                gradeField.required = true;
            } else {
                gradeRow.style.display = 'none';
                gradeField.classList.remove('required');
                gradeField.required = false;
                gradeField.value = '';
            }
        }
        
        this.validateForm();
    }
    
    fetchAddressFromAPI(postalCode, target) {
        // madefor.github.io の郵便番号検索APIを使用（CORS対応）
        fetch(`https://madefor.github.io/postal-code-api/api/v1/${postalCode.slice(0,3)}/${postalCode.slice(3)}.json`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('郵便番号が見つかりません');
                }
                return response.json();
            })
            .then(data => {
                if (data && data.data && data.data.length > 0) {
                    const result = data.data[0];
                    this.fillAddress(target, {
                        prefecture: result.ja.prefecture,
                        city: result.ja.address1,
                        address: result.ja.address2
                    });
                } else {
                    // 住所が見つからない場合は何も表示しない（自動検索なので）
                }
            })
            .catch(error => {
                console.error('住所検索エラー:', error);
                // エラーの場合は何も表示しない（自動検索なので）
            });
    }
    
    fillAddress(target, addressData) {
        const prefix = target === 'agency' ? 'agency_' : '';
        
        const prefectureSelect = document.getElementById(prefix + 'prefecture');
        const cityInput = document.getElementById(prefix + 'city');
        const addressInput = document.getElementById(prefix + 'address');
        
        if (prefectureSelect && addressData.prefecture) {
            prefectureSelect.value = addressData.prefecture;
        }
        if (cityInput && addressData.city) {
            cityInput.value = addressData.city;
        }
        if (addressInput && addressData.address) {
            // 既存の番地・建物名がある場合は上書きしない
            if (!addressInput.value) {
                addressInput.value = addressData.address;
            }
        }
        
        // バリデーションを実行
        this.validateForm();
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
        const formGroup = field.closest('.form-group') || field.closest('.info-row');
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
            const cleanedValue = field.value.replace(/[^0-9]/g, '');
            if (field.value && cleanedValue.length !== 7) {
                isValid = false;
                message = '郵便番号は7桁の数字で入力してください（ハイフンなし）';
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