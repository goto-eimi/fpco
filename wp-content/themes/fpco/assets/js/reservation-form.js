/**
 * 予約フォーム JavaScript
 */

class ReservationForm {
    constructor() {
        this.form = document.getElementById('reservation-form');
        this.maxVisitors = window.factoryCapacity || 50; // 工場のcapacityを使用、なければデフォルト50
        this.totalVisitors = 0;
        this.isSubmitting = false; // 送信中フラグ
        
        this.init();
    }
    
    init() {
        // カレンダーからの遷移の場合はlocalStorageをクリア
        this.checkAndClearFormData();
        
        this.bindEvents();
        this.restoreFormData();
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
            radio.addEventListener('change', () => {
                this.toggleVisitorCategoryFields();
                // カテゴリ変更時も人数チェックを実行
                setTimeout(() => {
                    this.calculateTotalVisitors();
                    this.validateVisitorCount();
                }, 100);
            });
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
        
        // 人数フィールドの変更を監視して合計を計算
        document.addEventListener('input', (e) => {
            if (e.target.type === 'number' && (
                e.target.name.includes('_count') || 
                e.target.name.includes('_visitor_count') ||
                e.target.id === 'total_visitor_count' ||
                e.target.id === 'elementary_visitor_count' ||
                e.target.id === 'total_child_count'
            )) {
                this.calculateTotalVisitors();
                this.validateVisitorCount();
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
        
        // リアルタイムバリデーション（エラーサマリー表示は無効化）
        const inputs = this.form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => {
                this.validateField(input);
                this.saveFormData(); // 入力時にデータを保存
            });
            input.addEventListener('change', () => {
                this.saveFormData(); // 選択変更時にデータを保存
            });
        });
        
        // フォーム送信
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // ページアンロード時の確認とデータ保存
        window.addEventListener('beforeunload', (e) => {
            this.saveFormData();
            
            // フォーム送信中でない場合のみ確認ダイアログを表示
            if (!this.isSubmitting && this.hasFormData()) {
                const message = '入力内容が破棄されますがよろしいでしょうか？';
                e.preventDefault();
                e.returnValue = message;
                return message;
            }
        });
    }
    
    toggleTravelAgencySection(preserveData = false) {
        const isTravelAgency = document.querySelector('input[name="is_travel_agency"]:checked').value === 'yes';
        const section = document.getElementById('travel-agency-section');
        
        if (isTravelAgency) {
            section.style.display = 'block';
            this.setRequiredFields(section, true);
            
            // 旅行会社の郵便番号入力フィールドにイベントリスナーを再登録
            const agencyPostalInput = document.getElementById('agency_postal_code');
            if (agencyPostalInput && !agencyPostalInput.hasAttribute('data-listener-attached')) {
                agencyPostalInput.setAttribute('data-listener-attached', 'true');
                agencyPostalInput.addEventListener('input', (e) => {
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
            }
        } else {
            section.style.display = 'none';
            this.setRequiredFields(section, false);
            
            // データ復元中でない場合のみフィールドをクリア
            if (!preserveData) {
                this.clearFields(section);
            }
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
            const vehicleInput = vehicleInline.querySelector('.vehicle-required');
            if (vehicleInput) {
                vehicleInput.required = true;
            }
        } else {
            vehicleInline.style.display = 'none';
            const vehicleInput = vehicleInline.querySelector('.vehicle-required');
            if (vehicleInput) {
                vehicleInput.required = false;
                vehicleInput.removeAttribute('required');
                vehicleInput.value = '';
            }
        }
        
        this.validateForm();
    }
    
    toggleVisitorCategoryFields(preserveData = false) {
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
                
                // データ復元中でない場合のみフィールドをクリア
                if (!preserveData) {
                    this.clearFields(section);
                }
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
                <div class="info-row companion-name-row">
                    <span class="info-label">同行者様${this.numberToCircle(i)}</span>
                    <span class="info-input">
                        <span class="companion-field-label">氏名</span>
                        <span class="required-label">必須</span>
                        <input type="text" id="companion_${i}_name" name="companion_${i}_name" placeholder="山田 太郎" class="required">
                    </span>
                </div>
                <div class="info-row companion-dept-row">
                    <span class="info-label"></span>
                    <span class="info-input">
                        <span class="companion-field-label">学部</span>
                        <span class="required-label">必須</span>
                        <input type="text" id="companion_${i}_department" name="companion_${i}_department" placeholder="工学部" class="required">
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
                gradeRow.style.display = 'flex';
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
                        city: result.ja.address1 + (result.ja.address2 || ''),
                        address: ''  // 番地は手動入力のため空にする
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
    
    calculateTotalVisitors() {
        // 見学者様人数のみを計算（申込者は含まない）
        const selectedCategory = document.querySelector('input[name="visitor_category"]:checked')?.value;
        let categoryTotal = 0;
        
        if (selectedCategory) {
            switch (selectedCategory) {
                case 'school':
                    // 児童・生徒 + 引率者
                    const studentField = document.getElementById('school_student_count');
                    const supervisorField = document.getElementById('school_supervisor_count');
                    const studentCount = parseInt(studentField?.value) || 0;
                    const supervisorCount = parseInt(supervisorField?.value) || 0;
                    categoryTotal = studentCount + supervisorCount;
                    break;
                case 'recruit':
                    // リクルートの場合は見学者人数（申込者含む）
                    categoryTotal = parseInt(document.getElementById('recruit_visitor_count')?.value) || 0;
                    break;
                case 'family':
                    // 見学者（大人）+ 見学者（子ども）
                    const familyAdultCount = parseInt(document.getElementById('family_adult_count')?.value) || 0;
                    const familyChildCount = parseInt(document.getElementById('family_child_count')?.value) || 0;
                    categoryTotal = familyAdultCount + familyChildCount;
                    break;
                case 'company':
                    // 見学者（大人）+ 見学者（子ども）
                    const companyAdultCount = parseInt(document.getElementById('company_adult_count')?.value) || 0;
                    const companyChildCount = parseInt(document.getElementById('company_child_count')?.value) || 0;
                    categoryTotal = companyAdultCount + companyChildCount;
                    break;
                case 'government':
                    // 見学者（大人）+ 見学者（子ども）
                    const governmentAdultCount = parseInt(document.getElementById('government_adult_count')?.value) || 0;
                    const governmentChildCount = parseInt(document.getElementById('government_child_count')?.value) || 0;
                    categoryTotal = governmentAdultCount + governmentChildCount;
                    break;
                case 'other':
                    // 見学者（大人）+ 見学者（子ども）
                    const otherAdultCount = parseInt(document.getElementById('other_adult_count')?.value) || 0;
                    const otherChildCount = parseInt(document.getElementById('other_child_count')?.value) || 0;
                    categoryTotal = otherAdultCount + otherChildCount;
                    break;
            }
        }
        
        // 総合人数を返す（カテゴリ別計算結果）
        this.totalVisitors = categoryTotal;
        return categoryTotal;
    }
    
    validateVisitorCount() {
        const categoryTotal = this.calculateTotalVisitors();
        let errorMessage = '';
        
        // 既存のエラーメッセージ要素を削除
        const existingErrors = document.querySelectorAll('.visitor-count-error, .visitor-consistency-error');
        existingErrors.forEach(error => error.remove());
        
        const selectedCategory = document.querySelector('input[name="visitor_category"]:checked')?.value;
        const totalVisitorField = document.getElementById('total_visitor_count');
        const childCountField = document.getElementById('total_child_count');
        
        // 分類別人数と最終人数フィールドの整合性チェック
        if (selectedCategory && totalVisitorField && totalVisitorField.value) {
            const finalTotal = parseInt(totalVisitorField.value) || 0;
            
            if (categoryTotal !== finalTotal && (categoryTotal > 0 || finalTotal > 0)) {
                errorMessage = `見学者の分類での人数（${categoryTotal}名）と見学者様人数（${finalTotal}名）が一致しません。どちらも同じ値を入力してください。`;
                
                // エラーメッセージを表示（最終人数フィールドの近く）
                const parentElement = totalVisitorField.closest('.info-row') || totalVisitorField.parentElement;
                if (parentElement) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'visitor-consistency-error';
                    errorDiv.style.cssText = 'color: #d32f2f; font-size: 14px; margin-top: 10px; padding: 10px; background-color: #ffebee; border-radius: 4px; border: 1px solid #f8bbd9;';
                    errorDiv.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 18px;">⚠️</span>
                            <span>${errorMessage}</span>
                        </div>
                    `;
                    parentElement.appendChild(errorDiv);
                }
                return false;
            }
        }
        
        // 統一フォーム部分の小学生以下人数チェック
        if (totalVisitorField && childCountField && 
            totalVisitorField.value && childCountField.value) {
            const totalCount = parseInt(totalVisitorField.value) || 0;
            const elementaryCount = parseInt(childCountField.value) || 0;
            
            if (elementaryCount > totalCount) {
                errorMessage = `内小学生以下の人数が見学者様人数を超えています。見学者様人数：${totalCount}名、内小学生以下：${elementaryCount}名`;
                
                // エラーメッセージを表示（統一フォーム部分）
                const parentElement = childCountField.closest('.info-row') || childCountField.parentElement;
                if (parentElement) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'visitor-count-error';
                    errorDiv.style.cssText = 'color: #d32f2f; font-size: 14px; margin-top: 10px; padding: 10px; background-color: #ffebee; border-radius: 4px; border: 1px solid #f8bbd9;';
                    errorDiv.textContent = errorMessage;
                    parentElement.appendChild(errorDiv);
                }
                return false;
            }
        }
        
        // 実際の人数として最終フィールドの値を使用（存在する場合）
        const actualTotal = totalVisitorField && totalVisitorField.value ? 
                           parseInt(totalVisitorField.value) || 0 : categoryTotal;
        
        if (actualTotal > this.maxVisitors) {
            errorMessage = `見学者様の合計人数が上限（${this.maxVisitors}名）を超えています。現在の合計：${actualTotal}名`;
            
            // エラーメッセージを表示
            const selectedCategory = document.querySelector('input[name="visitor_category"]:checked');
            let targetSection = null;
            
            if (selectedCategory) {
                targetSection = document.getElementById(selectedCategory.value + '-details');
            }
            
            // 統一フォームの場合
            const totalVisitorCount = document.getElementById('total_visitor_count');
            if (totalVisitorCount && totalVisitorCount.closest('.info-row').style.display !== 'none') {
                targetSection = totalVisitorCount.closest('.info-row');
            }
            
            if (targetSection) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'visitor-count-error';
                errorDiv.style.cssText = 'color: #dc3545; font-size: 16px; font-weight: bold; margin: 15px 0; padding: 15px; background: #f8d7da; border: 2px solid #dc3545; border-radius: 6px; box-shadow: 0 2px 4px rgba(220,53,69,0.2);';
                errorDiv.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">⚠️</span>
                        <span>${errorMessage}</span>
                    </div>
                `;
                
                // 人数入力フィールドの後に追加
                if (totalVisitorCount && targetSection === totalVisitorCount.closest('.info-row')) {
                    // 統一フォームの場合
                    targetSection.insertAdjacentElement('afterend', errorDiv);
                } else if (targetSection.classList.contains('info-row')) {
                    // info-row要素の場合は直後に追加
                    targetSection.insertAdjacentElement('afterend', errorDiv);
                } else {
                    // カテゴリー別フォームの場合
                    // 人数入力フィールドを含むinfo-rowを探す
                    const countFields = targetSection.querySelectorAll('input[type="number"][name*="_count"]');
                    if (countFields.length > 0) {
                        const lastCountFieldRow = countFields[countFields.length - 1].closest('.info-row');
                        if (lastCountFieldRow) {
                            lastCountFieldRow.insertAdjacentElement('afterend', errorDiv);
                        } else {
                            targetSection.appendChild(errorDiv);
                        }
                    } else {
                        targetSection.appendChild(errorDiv);
                    }
                }
                
                // スクロールしてエラーメッセージを表示
                setTimeout(() => {
                    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
            
            return false;
        }
        
        return true;
    }
    
    validateField(field) {
        // 非表示の項目はバリデーションしない
        const hiddenParent = field.closest('.conditional[style*="display: none"]');
        if (hiddenParent) {
            return true;
        }
        
        const formGroup = field.closest('.form-group') || field.closest('.info-row');
        const errorMessage = formGroup.querySelector('.error-message');
        let isValid = true;
        let message = '';
        
        // フィールド固有のバリデーション
        switch (field.id) {
            case 'applicant_name':
                if (field.required && !field.value.trim()) {
                    isValid = false;
                    message = '申込者様氏名を入力してください';
                }
                break;
                
            case 'applicant_name_kana':
                if (field.required && !field.value.trim()) {
                    isValid = false;
                    message = '申込者様氏名（ふりがな）を入力してください';
                } else if (field.value.trim() && !/^[ぁ-んー\s]+$/.test(field.value)) {
                    isValid = false;
                    message = '申込者様氏名（ふりがな）はひらがなで入力してください';
                }
                break;
                
            case 'postal_code':
            case 'agency_postal_code':
                if (field.required && !field.value.trim()) {
                    isValid = false;
                    message = '郵便番号を入力してください';
                } else if (field.value.trim()) {
                    const cleanedValue = field.value.replace(/[^0-9]/g, '');
                    if (cleanedValue.length !== 7) {
                        isValid = false;
                        message = '郵便番号は7桁の半角数字でご入力ください';
                    }
                }
                break;
                
            case 'city':
            case 'agency_city':
                if (field.required && !field.value.trim()) {
                    isValid = false;
                    message = '市区町村名を入力してください';
                }
                break;
                
            case 'address':
            case 'agency_address':
                if (field.required && !field.value.trim()) {
                    isValid = false;
                    message = '番地・ビル名を入力してください';
                }
                break;
                
            default:
                // ラジオボタンのバリデーション
                if (field.type === 'radio') {
                    const radioGroup = document.getElementsByName(field.name);
                    const isChecked = Array.from(radioGroup).some(radio => radio.checked);
                    if (field.required && !isChecked) {
                        isValid = false;
                        if (field.name === 'is_travel_agency') {
                            message = '申込者様は旅行会社の方ですか？を選択してください';
                        } else if (field.name === 'visitor_category') {
                            message = '見学者様の分類を選択してください';
                        } else {
                            message = `${this.getFieldLabel(field)}を選択してください`;
                        }
                    }
                } else {
                    // その他の必須項目チェック
                    if (field.required && !field.value.trim()) {
                        isValid = false;
                        message = `${this.getFieldLabel(field)}を入力してください`;
                    }
                    
                    // その他の形式チェック
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
                }
                break;
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
    
    validateForm(showErrors = false) {
        const submitBtn = document.querySelector('.btn-submit');
        let isValid = true;
        const errors = [];
        const errorMessages = {};
        
        // 必須項目のチェック（表示されているフィールドのみ）
        const requiredFields = this.form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            // フィールドが非表示の親要素内にないかチェック
            const hiddenParent = field.closest('.conditional[style*="display: none"]');
            if (!hiddenParent) {
                if (!this.validateField(field)) {
                    isValid = false;
                    // エラーメッセージを収集（重複を避ける）
                    const message = this.getFieldErrorMessage(field);
                    if (message && !errorMessages[message]) {
                        errorMessages[message] = true;
                        errors.push(message);
                    }
                }
            }
        });
        
        // ラジオボタングループのバリデーション
        const radioGroups = ['is_travel_agency', 'visitor_category'];
        radioGroups.forEach(groupName => {
            const radios = this.form.querySelectorAll(`input[name="${groupName}"]`);
            if (radios.length > 0) {
                const isChecked = Array.from(radios).some(radio => radio.checked);
                if (!isChecked) {
                    isValid = false;
                    let message = '';
                    if (groupName === 'is_travel_agency') {
                        message = '申込者様は旅行会社の方ですか？を選択してください';
                    } else if (groupName === 'visitor_category') {
                        message = '見学者様の分類を選択してください';
                    }
                    if (message && !errorMessages[message]) {
                        errorMessages[message] = true;
                        errors.push(message);
                    }
                }
            }
        });
        
        // 人数制限チェック
        if (!this.validateVisitorCount()) {
            isValid = false;
            const categoryTotal = this.calculateTotalVisitors();
            const selectedCategory = document.querySelector('input[name="visitor_category"]:checked')?.value;
            const totalVisitorField = document.getElementById('total_visitor_count');
            const childCountField = document.getElementById('total_child_count');
            
            // 分類別人数と最終人数の整合性チェック
            if (selectedCategory && totalVisitorField && totalVisitorField.value) {
                const finalTotal = parseInt(totalVisitorField.value) || 0;
                
                if (categoryTotal !== finalTotal && (categoryTotal > 0 || finalTotal > 0)) {
                    const message = `見学者の分類での人数（${categoryTotal}名）と見学者様人数（${finalTotal}名）が一致しません`;
                    if (!errorMessages[message]) {
                        errorMessages[message] = true;
                        errors.push(message);
                    }
                }
            }
            
            // 統一フォーム部分の小学生以下人数チェック
            if (totalVisitorField && childCountField && 
                totalVisitorField.value && childCountField.value) {
                const totalCount = parseInt(totalVisitorField.value) || 0;
                const elementaryCount = parseInt(childCountField.value) || 0;
                
                if (elementaryCount > totalCount) {
                    const message = `内小学生以下の人数が見学者様人数を超えています。見学者様人数：${totalCount}名、内小学生以下：${elementaryCount}名`;
                    if (!errorMessages[message]) {
                        errorMessages[message] = true;
                        errors.push(message);
                    }
                }
            }
            
            // 上限人数チェック
            const actualTotal = totalVisitorField && totalVisitorField.value ? 
                               parseInt(totalVisitorField.value) || 0 : categoryTotal;
            if (actualTotal > this.maxVisitors) {
                const message = `見学者様の合計人数が上限（${this.maxVisitors}名）を超えています（現在：${actualTotal}名）`;
                if (!errorMessages[message]) {
                    errorMessages[message] = true;
                    errors.push(message);
                }
            }
        }
        
        // エラーメッセージの表示（フォーム送信時のみ）
        if (showErrors) {
            this.displayFormErrors(errors);
        }
        
        // バリデーション結果に応じて送信ボタンの状態を切り替え
        if (submitBtn) {
            submitBtn.disabled = !isValid;
        }
        
        return isValid;
    }
    
    setRequiredFields(container, required) {
        const fields = container.querySelectorAll('.required');
        fields.forEach(field => {
            if (required) {
                field.required = true;
            } else {
                field.required = false;
                field.removeAttribute('required');
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
        
        // フォームバリデーションを実行してエラーを収集
        const isValid = this.validateForm(true);
        
        if (!isValid) {
            // エラーがある場合はエラー表示エリアにスクロール
            const errorMessagesDiv = document.getElementById('error-messages');
            if (errorMessagesDiv && errorMessagesDiv.style.display === 'block') {
                errorMessagesDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
        
        // 送信中フラグを設定
        this.isSubmitting = true;
        
        // 送信前に非表示セクション内のフィールドを無効化（送信対象から除外）
        // ただし、ラジオボタンは除外（選択状態を保持）
        const hiddenSections = this.form.querySelectorAll('.conditional[style*="display: none"]');
        hiddenSections.forEach(section => {
            // 交通機関の「その他」インライン入力は除外
            if (section.id === 'transportation-other-inline') {
                return;
            }
            
            const fields = section.querySelectorAll('input:not([type="radio"]), select, textarea');
            fields.forEach(field => {
                if (field.name) {
                    field.disabled = true;
                    field.dataset.wasEnabled = 'true';
                }
            });
        });
        
        // 送信時にlocalStorageをクリア
        this.clearFormData();
        
        // フォームデータを確認画面に送信
        this.form.submit();
    }
    
    saveFormData() {
        const formData = new FormData(this.form);
        const data = {};
        
        // 通常のフォームフィールド
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        // ラジオボタンの状態を保存
        const radioGroups = ['is_travel_agency', 'visitor_category', 'transportation'];
        radioGroups.forEach(groupName => {
            const checkedRadio = this.form.querySelector(`input[name="${groupName}"]:checked`);
            if (checkedRadio) {
                data[groupName] = checkedRadio.value;
            }
        });
        
        localStorage.setItem('reservation_form_data', JSON.stringify(data));
    }
    
    restoreFormData() {
        const savedData = localStorage.getItem('reservation_form_data');
        if (!savedData) return;
        
        try {
            const data = JSON.parse(savedData);
            
            // まずラジオボタンの状態を復元
            const radioGroups = ['is_travel_agency', 'visitor_category', 'transportation'];
            radioGroups.forEach(groupName => {
                if (data[groupName]) {
                    const radioButton = this.form.querySelector(`input[name="${groupName}"][value="${data[groupName]}"]`);
                    if (radioButton) {
                        radioButton.checked = true;
                    }
                }
            });
            
            // 条件付き表示セクションを復元（ラジオボタンの状態に基づいて）
            this.toggleTravelAgencySection(true); // データ保持フラグをtrueに
            this.toggleVisitorCategoryFields(true); // データ保持フラグをtrueに
            this.toggleTransportationFields();
            
            // 基本フィールドを復元
            Object.keys(data).forEach(key => {
                const field = this.form.querySelector(`[name="${key}"]`);
                if (field && field.type !== 'radio') {
                    field.value = data[key];
                }
            });
            
            // 見学者分類の詳細フィールドを再度復元（clearFields後のため）
            setTimeout(() => {
                const selectedCategory = data['visitor_category'];
                if (selectedCategory) {
                    const categoryFieldPrefix = selectedCategory + '_';
                    Object.keys(data).forEach(key => {
                        if (key.startsWith(categoryFieldPrefix)) {
                            const field = this.form.querySelector(`[name="${key}"]`);
                            if (field && data[key]) {
                                field.value = data[key];
                            }
                        }
                    });
                }
                
                // 旅行会社関連フィールドを再度復元
                const isTravelAgency = data['is_travel_agency'];
                if (isTravelAgency === 'yes') {
                    const agencyFieldPrefixes = ['agency_'];
                    agencyFieldPrefixes.forEach(prefix => {
                        Object.keys(data).forEach(key => {
                            if (key.startsWith(prefix)) {
                                const field = this.form.querySelector(`[name="${key}"]`);
                                if (field && data[key]) {
                                    field.value = data[key];
                                }
                            }
                        });
                    });
                }
            }, 50);
            
            // 条件表示された項目内のフィールド値を確実に復元
            setTimeout(() => {
                Object.keys(data).forEach(key => {
                    const field = this.form.querySelector(`[name="${key}"]`);
                    if (field && field.type !== 'radio' && data[key]) {
                        field.value = data[key];
                        
                        // 入力イベントを発火して関連処理をトリガー
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                
                // 子ども人数に応じた学年フィールドの表示/非表示を復元
                ['family', 'company', 'government', 'other'].forEach(category => {
                    const childCountField = document.querySelector(`[name="${category}_child_count"]`);
                    if (childCountField && data[`${category}_child_count`]) {
                        childCountField.value = data[`${category}_child_count`];
                        this.toggleChildGradeField(childCountField);
                    }
                });
                
                // リクルート見学者の同行者フィールドを復元
                if (data['recruit_visitor_count']) {
                    const recruitVisitorCount = document.getElementById('recruit_visitor_count');
                    if (recruitVisitorCount) {
                        recruitVisitorCount.value = data['recruit_visitor_count'];
                        this.updateCompanionFields(data['recruit_visitor_count']);
                        
                        // 同行者フィールドの値を復元
                        setTimeout(() => {
                            Object.keys(data).forEach(key => {
                                if (key.startsWith('companion_')) {
                                    const field = document.querySelector(`[name="${key}"]`);
                                    if (field && data[key]) {
                                        field.value = data[key];
                                    }
                                }
                            });
                        }, 100);
                    }
                }
                
                // 最終的にフォームバリデーションを実行
                this.validateForm();
            }, 150);
            
        } catch (error) {
            console.error('フォームデータの復元に失敗しました:', error);
        }
    }
    
    clearFormData() {
        localStorage.removeItem('reservation_form_data');
    }
    
    checkAndClearFormData() {
        // URLパラメータやreferrerをチェックしてカレンダーからの遷移かを判定
        const urlParams = new URLSearchParams(window.location.search);
        const referrer = document.referrer;
        
        // カレンダーページからの遷移の場合
        const isFromCalendar = referrer.includes('/reservation-calendar/') || 
                              urlParams.has('from_calendar') ||
                              urlParams.has('date') ||
                              urlParams.has('factory_id');
        
        if (isFromCalendar) {
            // localStorageをクリア
            this.clearFormData();
        }
    }
    
    hasFormData() {
        // フォームに何らかの入力があるかチェック
        const inputs = this.form.querySelectorAll('input, select, textarea');
        
        for (let input of inputs) {
            // 隠しフィールドやボタンは除外
            if (input.type === 'hidden' || input.type === 'submit' || input.type === 'button') {
                continue;
            }
            
            // チェックボックスやラジオボタンの場合
            if (input.type === 'radio' || input.type === 'checkbox') {
                if (input.checked) {
                    return true;
                }
            }
            // テキスト系フィールドの場合
            else if (input.value && input.value.trim() !== '') {
                return true;
            }
        }
        
        return false;
    }
    
    getFieldLabel(field) {
        // フィールドのラベルを取得
        const infoRow = field.closest('.info-row');
        if (infoRow) {
            const label = infoRow.querySelector('.info-label');
            if (label) {
                return label.textContent.replace(/\n/g, '').trim();
            }
        }
        
        // ラベルが見つからない場合はフィールド名から推測
        const fieldName = field.name || field.id;
        return fieldName || '不明なフィールド';
    }
    
    getFieldErrorMessage(field) {
        // フィールド固有のエラーメッセージを取得
        switch (field.id) {
            case 'applicant_name':
                return '申込者様氏名を入力してください';
                
            case 'applicant_name_kana':
                if (!field.value.trim()) {
                    return '申込者様氏名（ふりがな）を入力してください';
                } else if (!/^[ぁ-んー\s]+$/.test(field.value)) {
                    return '申込者様氏名（ふりがな）はひらがなで入力してください';
                }
                break;
                
            case 'postal_code':
            case 'agency_postal_code':
                if (!field.value.trim()) {
                    return '郵便番号を入力してください';
                } else {
                    const cleanedValue = field.value.replace(/[^0-9]/g, '');
                    if (cleanedValue.length !== 7) {
                        return '郵便番号は7桁の半角数字でご入力ください';
                    }
                }
                break;
                
            case 'prefecture':
            case 'agency_prefecture':
                return '都道府県を選択してください';
                
            case 'city':
            case 'agency_city':
                return '市区町村名を入力してください';
                
            case 'address':
            case 'agency_address':
                return '番地・ビル名を入力してください';
                
            default:
                // ラジオボタンの場合
                if (field.type === 'radio') {
                    if (field.name === 'is_travel_agency') {
                        return '申込者様は旅行会社の方ですか？を選択してください';
                    } else if (field.name === 'visitor_category') {
                        return '見学者様の分類を選択してください';
                    }
                }
                
                // その他のフィールド
                const label = this.getFieldLabel(field);
                if (field.type === 'email' && field.value.trim()) {
                    return '正しいメールアドレスを入力してください';
                } else if (field.type === 'tel' && field.value.trim()) {
                    return '正しい電話番号を入力してください';
                } else {
                    return `${label}を入力してください`;
                }
        }
    }
    
    displayFormErrors(errors) {
        const errorMessagesDiv = document.getElementById('error-messages');
        const errorList = document.getElementById('error-list');
        
        if (!errorMessagesDiv || !errorList) return;
        
        if (errors.length > 0) {
            // エラーリストをクリア
            errorList.innerHTML = '';
            
            // エラーメッセージを追加
            errors.forEach(error => {
                const li = document.createElement('li');
                li.textContent = error;
                errorList.appendChild(li);
            });
            
            // エラーメッセージを表示
            errorMessagesDiv.style.display = 'block';
        } else {
            // エラーがない場合は非表示
            errorMessagesDiv.style.display = 'none';
        }
    }
    
    displayFormErrorsOnSubmit(errors) {
        // フォーム送信時のみエラーサマリーを表示
        this.displayFormErrors(errors);
    }
}

// DOM読み込み完了後に初期化
document.addEventListener('DOMContentLoaded', function() {
    new ReservationForm();
});