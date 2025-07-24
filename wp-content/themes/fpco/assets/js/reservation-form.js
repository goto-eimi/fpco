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
        
        // 郵便番号入力の自動フォーマット
        document.querySelectorAll('.postal-code-input').forEach(input => {
            input.addEventListener('input', (e) => {
                // 数字以外を削除
                let value = e.target.value.replace(/[^0-9]/g, '');
                // 7桁に制限
                if (value.length > 7) {
                    value = value.slice(0, 7);
                }
                e.target.value = value;
                
                // 7桁入力されたら自動で住所検索
                if (value.length === 7) {
                    const target = e.target.id === 'agency_postal_code' ? 'agency' : 'applicant';
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
        const otherRow = document.getElementById('transportation-other-row');
        const vehicleRow = document.getElementById('vehicle-count-row');
        
        // その他の表示/非表示
        if (selectedTransportation === 'other') {
            otherRow.style.display = 'flex';
            otherRow.querySelector('input').required = true;
        } else {
            otherRow.style.display = 'none';
            otherRow.querySelector('input').required = false;
            otherRow.querySelector('input').value = '';
        }
        
        // 台数入力の表示/非表示
        if (selectedTransportation === 'car' || selectedTransportation === 'chartered_bus') {
            vehicleRow.style.display = 'flex';
            vehicleRow.querySelector('input').required = true;
        } else {
            vehicleRow.style.display = 'none';
            vehicleRow.querySelector('input').required = false;
            vehicleRow.querySelector('input').value = '';
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
    
    
    searchByPostalCode(e) {
        const button = e.target;
        const target = button.getAttribute('data-target'); // 'agency' or 'applicant'
        const postalInput = target === 'agency' ? 
            document.getElementById('agency_postal_code') : 
            document.getElementById('postal_code');
        
        const postalCode = postalInput.value.replace(/[^0-9]/g, '');
        
        if (postalCode.length !== 7) {
            alert('郵便番号は7桁の数字で入力してください（ハイフンなし）');
            return;
        }
        
        // 郵便番号APIを使用（実際の実装）
        this.fetchAddressFromAPI(postalCode, target);
    }
    
    fetchAddressFromAPI(postalCode, target) {
        // ボタンを無効化してローディング表示
        const button = document.querySelector(`.btn-postal-search[data-target="${target}"]`);
        button.disabled = true;
        button.textContent = '検索中...';
        
        // 郵便番号検索API（zipcloud）を使用
        fetch(`https://zipcloud.ibsnet.co.jp/api/search?zipcode=${postalCode}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 200 && data.results && data.results.length > 0) {
                    const result = data.results[0];
                    this.fillAddress(target, {
                        prefecture: result.address1,
                        city: result.address2,
                        address: result.address3
                    });
                } else {
                    alert('該当する住所が見つかりませんでした');
                }
            })
            .catch(error => {
                console.error('住所検索エラー:', error);
                alert('住所検索に失敗しました。手動で入力してください。');
            })
            .finally(() => {
                // ボタンを元に戻す
                button.disabled = false;
                button.textContent = '住所検索';
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