/**
 * Time Slots Management Modal Component
 * Can be integrated into any page that needs time slots management
 * Uses prefixed IDs to avoid conflicts with main page elements
 */

class TimeSlotsModal {
    constructor() {
        this.timeSlots = [];
        this.editingSlotId = null;
        this.modal = null;
        this.onSlotsUpdated = null;
        
        this.createModal();
        this.bindEvents();
    }

    createModal() {
        const modalHTML = `
            <div id="tsmModal" class="tsm-backdrop">
                <div class="tsm-dialog">
                    <div class="tsm-header">
                        <h3 class="tsm-title">مدیریت زمان‌های کلاس</h3>
                        <button type="button" class="tsm-close" id="tsmCloseBtn">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    
                    <div class="tsm-body">
                        <!-- Add/Edit Form -->
                        <div class="tsm-form-section">
                            <h5 class="tsm-form-title">
                                <i class="bi bi-plus-circle"></i>
                                <span id="tsmFormTitle">افزودن زمان جدید</span>
                            </h5>
                            <form id="tsmForm" novalidate>
                                <div class="mb-3">
                                    <label class="form-label-app required">نام زمان</label>
                                    <input type="text" class="form-control-app" id="tsmName" placeholder="مثال: صبح، ظهر، شام" required>
                                    <div class="tsm-error" id="tsmNameError">نام زمان الزامی است</div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label-app required">زمان شروع</label>
                                        <div class="tsm-time-input">
                                            <input type="number" class="form-control-app" id="tsmStartHour" placeholder="ساعت" min="0" max="23" required>
                                            <span>:</span>
                                            <input type="number" class="form-control-app" id="tsmStartMinute" placeholder="دقیقه" min="0" max="59" value="0">
                                        </div>
                                        <div class="tsm-error" id="tsmStartError">زمان شروع الزامی است</div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label-app required">زمان پایان</label>
                                        <div class="tsm-time-input">
                                            <input type="number" class="form-control-app" id="tsmEndHour" placeholder="ساعت" min="0" max="23" required>
                                            <span>:</span>
                                            <input type="number" class="form-control-app" id="tsmEndMinute" placeholder="دقیقه" min="0" max="59" value="0">
                                        </div>
                                        <div class="tsm-error" id="tsmEndError">زمان پایان الزامی است</div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label-app">توضیحات</label>
                                    <textarea class="form-control-app" id="tsmDescription" rows="2" placeholder="توضیحات اختیاری..."></textarea>
                                </div>
                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn-app btn-app--secondary btn-app--sm" id="tsmCancelBtn">انصراف</button>
                                    <button type="submit" class="btn-app btn-app--primary btn-app--sm" id="tsmSubmitBtn">
                                        <span class="spinner-border spinner-border-sm d-none" id="tsmSpinner"></span>
                                        <i class="bi bi-check-lg" id="tsmBtnIcon"></i>
                                        <span id="tsmBtnText">ذخیره</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Time Slots List -->
                        <div class="tsm-list-section">
                            <h5 class="tsm-list-title">
                                <i class="bi bi-clock"></i>
                                زمان‌های موجود
                            </h5>
                            <div id="tsmLoading" class="tsm-loading">
                                <div class="spinner-border spinner-border-sm"></div>
                                <span>در حال بارگذاری...</span>
                            </div>
                            <div id="tsmList"></div>
                            <div id="tsmEmpty" class="tsm-empty" style="display: none;">
                                <i class="bi bi-clock"></i>
                                <div>هیچ زمان کلاسی تعریف نشده</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tsm-footer">
                        <button type="button" class="btn-app btn-app--secondary" id="tsmDoneBtn">بستن</button>
                    </div>
                </div>
            </div>
        `;

        // Add styles
        if (!document.getElementById('tsmStyles')) {
            const styles = document.createElement('style');
            styles.id = 'tsmStyles';
            styles.textContent = `
                .tsm-backdrop {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    z-index: 1050;
                    display: none;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }
                .tsm-backdrop.show {
                    opacity: 1;
                }
                .tsm-dialog {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) scale(0.9);
                    background: var(--bg-primary);
                    border-radius: 12px;
                    width: 90%;
                    max-width: 550px;
                    max-height: 90vh;
                    overflow-y: auto;
                    z-index: 1051;
                    transition: transform 0.3s ease;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                }
                .tsm-backdrop.show .tsm-dialog {
                    transform: translate(-50%, -50%) scale(1);
                }
                .tsm-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 1rem 1.25rem;
                    border-bottom: 1px solid var(--border-color);
                }
                .tsm-title {
                    font-size: 1.1rem;
                    font-weight: 600;
                    margin: 0;
                }
                .tsm-close {
                    background: none;
                    border: none;
                    font-size: 1.25rem;
                    cursor: pointer;
                    color: var(--text-secondary);
                    padding: 0.25rem;
                    border-radius: 6px;
                    transition: all 0.2s;
                }
                .tsm-close:hover {
                    background: var(--bg-secondary);
                    color: var(--text-primary);
                }
                .tsm-body {
                    padding: 1.25rem;
                }
                .tsm-footer {
                    padding: 1rem 1.25rem;
                    border-top: 1px solid var(--border-color);
                    display: flex;
                    justify-content: flex-end;
                }
                .tsm-form-section {
                    background: var(--bg-secondary);
                    border-radius: 8px;
                    padding: 1rem;
                    margin-bottom: 1.25rem;
                }
                .tsm-form-title {
                    font-size: 0.95rem;
                    font-weight: 600;
                    margin-bottom: 1rem;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .tsm-list-title {
                    font-size: 0.95rem;
                    font-weight: 600;
                    margin-bottom: 0.75rem;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .tsm-time-input {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .tsm-time-input input {
                    width: 70px;
                    text-align: center;
                }
                .tsm-time-input span {
                    font-weight: bold;
                    color: var(--text-secondary);
                }
                .tsm-error {
                    display: none;
                    color: var(--color-danger-600);
                    font-size: 0.8rem;
                    margin-top: 0.25rem;
                    align-items: center;
                    gap: 0.25rem;
                }
                .tsm-error.show {
                    display: flex;
                }
                .tsm-loading {
                    text-align: center;
                    padding: 1.5rem;
                    color: var(--text-secondary);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 0.5rem;
                }
                .tsm-empty {
                    text-align: center;
                    padding: 1.5rem;
                    color: var(--text-secondary);
                }
                .tsm-empty i {
                    font-size: 2rem;
                    margin-bottom: 0.5rem;
                    display: block;
                }
                .tsm-item {
                    border: 1px solid var(--border-color);
                    border-radius: 8px;
                    padding: 0.75rem 1rem;
                    margin-bottom: 0.5rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: var(--bg-primary);
                }
                .tsm-item:last-child {
                    margin-bottom: 0;
                }
                .tsm-item-name {
                    font-weight: 600;
                    margin-bottom: 0.125rem;
                }
                .tsm-item-time {
                    color: var(--text-secondary);
                    font-size: 0.85rem;
                }
                .tsm-item-actions {
                    display: flex;
                    gap: 0.25rem;
                }
            `;
            document.head.appendChild(styles);
        }

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('tsmModal');
    }

    bindEvents() {
        // Form submission
        document.getElementById('tsmForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSubmit();
        });

        // Close buttons
        document.getElementById('tsmCloseBtn').addEventListener('click', () => this.close());
        document.getElementById('tsmDoneBtn').addEventListener('click', () => this.close());
        document.getElementById('tsmCancelBtn').addEventListener('click', () => this.cancelEdit());

        // Click outside to close
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) this.close();
        });

        // Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('show')) {
                this.close();
            }
        });
    }

    async open(onSlotsUpdated = null) {
        this.onSlotsUpdated = onSlotsUpdated;
        this.modal.style.display = 'block';
        // Force reflow
        this.modal.offsetHeight;
        this.modal.classList.add('show');
        await this.loadTimeSlots();
    }

    close() {
        this.modal.classList.remove('show');
        setTimeout(() => {
            this.modal.style.display = 'none';
        }, 300);
        this.cancelEdit();
    }

    async loadTimeSlots() {
        const loading = document.getElementById('tsmLoading');
        const list = document.getElementById('tsmList');
        const empty = document.getElementById('tsmEmpty');
        
        loading.style.display = 'flex';
        list.innerHTML = '';
        empty.style.display = 'none';
        
        try {
            const data = await APIClient.get('coaches.php', { action: 'time-slots' });
            
            loading.style.display = 'none';
            
            if (data.success && Array.isArray(data.data) && data.data.length > 0) {
                this.timeSlots = data.data;
                this.renderTimeSlots();
            } else {
                this.timeSlots = [];
                empty.style.display = 'block';
            }
        } catch (error) {
            console.error('Error loading time slots:', error);
            loading.innerHTML = '<div class="text-danger">خطا در بارگذاری</div>';
        }
    }

    renderTimeSlots() {
        const list = document.getElementById('tsmList');
        
        list.innerHTML = this.timeSlots.map(slot => `
            <div class="tsm-item" data-id="${slot.id}">
                <div>
                    <div class="tsm-item-name">${this.escapeHtml(slot.name)}</div>
                    <div class="tsm-item-time">
                        <i class="bi bi-clock"></i>
                        ${slot.start_time} - ${slot.end_time}
                    </div>
                    ${slot.description ? `<div class="text-muted small">${this.escapeHtml(slot.description)}</div>` : ''}
                </div>
                <div class="tsm-item-actions">
                    <button class="btn-app btn-app--secondary btn-app--sm" onclick="timeSlotsModal.editSlot(${slot.id})" title="ویرایش">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn-app btn-app--danger btn-app--sm" onclick="timeSlotsModal.deleteSlot(${slot.id})" title="حذف">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }

    editSlot(slotId) {
        const slot = this.timeSlots.find(s => s.id === slotId);
        if (!slot) return;

        // Reset form first
        document.getElementById('tsmForm').reset();
        this.clearErrors();
        
        // Set editing ID
        this.editingSlotId = slotId;
        
        // Update UI
        document.getElementById('tsmFormTitle').textContent = 'ویرایش زمان';
        document.getElementById('tsmBtnText').textContent = 'بروزرسانی';
        
        // Fill form
        document.getElementById('tsmName').value = slot.name;
        document.getElementById('tsmDescription').value = slot.description || '';
        
        const [startH, startM] = slot.start_time.split(':');
        const [endH, endM] = slot.end_time.split(':');
        
        document.getElementById('tsmStartHour').value = parseInt(startH);
        document.getElementById('tsmStartMinute').value = parseInt(startM);
        document.getElementById('tsmEndHour').value = parseInt(endH);
        document.getElementById('tsmEndMinute').value = parseInt(endM);
        
        // Scroll to form
        document.getElementById('tsmForm').scrollIntoView({ behavior: 'smooth' });
    }

    cancelEdit() {
        this.editingSlotId = null;
        document.getElementById('tsmFormTitle').textContent = 'افزودن زمان جدید';
        document.getElementById('tsmBtnText').textContent = 'ذخیره';
        document.getElementById('tsmForm').reset();
        this.clearErrors();
    }

    async deleteSlot(slotId) {
        const slot = this.timeSlots.find(s => s.id === slotId);
        if (!slot) return;
        
        if (!confirm(`آیا از حذف زمان "${slot.name}" اطمینان دارید؟`)) return;
        
        try {
            const result = await APIClient.delete(`coaches.php?action=time-slots&id=${slotId}`);
            if (result.success) {
                notify.success('زمان با موفقیت حذف شد');
                await this.loadTimeSlots();
                if (this.onSlotsUpdated) this.onSlotsUpdated();
            } else {
                throw new Error(result.error || 'خطا در حذف');
            }
        } catch (error) {
            notify.error('خطا در حذف: ' + error.message);
        }
    }

    async handleSubmit() {
        this.clearErrors();
        
        const name = document.getElementById('tsmName').value.trim();
        const startHour = parseInt(document.getElementById('tsmStartHour').value);
        const startMinute = parseInt(document.getElementById('tsmStartMinute').value) || 0;
        const endHour = parseInt(document.getElementById('tsmEndHour').value);
        const endMinute = parseInt(document.getElementById('tsmEndMinute').value) || 0;
        const description = document.getElementById('tsmDescription').value.trim();
        
        let isValid = true;
        
        if (!name) {
            this.showError('tsmName', 'tsmNameError');
            isValid = false;
        }
        
        if (isNaN(startHour) || startHour < 0 || startHour > 23) {
            this.showError('tsmStartHour', 'tsmStartError');
            isValid = false;
        }
        
        if (isNaN(endHour) || endHour < 0 || endHour > 23) {
            this.showError('tsmEndHour', 'tsmEndError');
            isValid = false;
        }
        
        if (isValid) {
            const startTotal = startHour * 60 + startMinute;
            const endTotal = endHour * 60 + endMinute;
            
            if (endTotal <= startTotal) {
                this.showError('tsmEndHour', 'tsmEndError', 'زمان پایان باید بعد از شروع باشد');
                isValid = false;
            }
        }
        
        if (!isValid) {
            notify._showSnackbar('لطفاً اطلاعات را صحیح وارد کنید', 'warning');
            return;
        }
        
        const startTime = `${String(startHour).padStart(2, '0')}:${String(startMinute).padStart(2, '0')}`;
        const endTime = `${String(endHour).padStart(2, '0')}:${String(endMinute).padStart(2, '0')}`;
        
        const formData = {
            name,
            start_time: startTime,
            end_time: endTime,
            description: description || null
        };
        
        this.setLoading(true);
        
        try {
            let result;
            if (this.editingSlotId) {
                result = await APIClient.put(`coaches.php?action=time-slots&id=${this.editingSlotId}`, formData);
            } else {
                result = await APIClient.post('coaches.php?action=time-slots', formData);
            }
            
            if (result.success) {
                notify.success(this.editingSlotId ? 'زمان بروزرسانی شد' : 'زمان افزوده شد');
                this.cancelEdit();
                await this.loadTimeSlots();
                if (this.onSlotsUpdated) this.onSlotsUpdated();
            } else {
                throw new Error(result.error || 'خطا');
            }
        } catch (error) {
            notify.error('خطا: ' + error.message);
        } finally {
            this.setLoading(false);
        }
    }

    setLoading(loading) {
        const btn = document.getElementById('tsmSubmitBtn');
        const spinner = document.getElementById('tsmSpinner');
        const icon = document.getElementById('tsmBtnIcon');
        const text = document.getElementById('tsmBtnText');
        
        btn.disabled = loading;
        spinner.classList.toggle('d-none', !loading);
        icon.classList.toggle('d-none', loading);
        text.textContent = loading ? 'در حال ذخیره...' : (this.editingSlotId ? 'بروزرسانی' : 'ذخیره');
    }

    showError(fieldId, errorId, message = null) {
        document.getElementById(fieldId).classList.add('is-invalid');
        const error = document.getElementById(errorId);
        error.classList.add('show');
        if (message) error.textContent = message;
    }

    clearErrors() {
        document.querySelectorAll('#tsmForm .is-invalid').forEach(el => el.classList.remove('is-invalid'));
        document.querySelectorAll('#tsmForm .tsm-error').forEach(el => el.classList.remove('show'));
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize global instance
window.timeSlotsModal = new TimeSlotsModal();