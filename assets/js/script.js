/**
 * RUNEHA INC. ERP SYSTEM - MAIN JAVASCRIPT
 */

// ============================================
// DOM Ready
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    autoHideAlerts();
    
    // Initialize tooltips
    initTooltips();
    
    // Initialize form validation
    initFormValidation();
    
    // Initialize confirm dialogs
    initConfirmDialogs();
});

// ============================================
// ALERT AUTO-HIDE
// ============================================
function autoHideAlerts() {
    const alerts = document.querySelectorAll('.alert:not(.persistent)');
    if (alerts.length === 0) return;
    
    setTimeout(() => {
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => {
                if (alert.parentNode) alert.remove();
            }, 500);
        });
    }, 5000);
}

// ============================================
// TOOLTIPS
// ============================================
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(el => {
        el.addEventListener('mouseenter', function(e) {
            const tip = document.createElement('div');
            tip.className = 'tooltip';
            tip.textContent = this.getAttribute('data-tooltip');
            tip.style.cssText = `
                position: fixed;
                background: #1a1a2e;
                color: white;
                padding: 4px 10px;
                border-radius: 4px;
                font-size: 0.75rem;
                z-index: 9999;
                pointer-events: none;
                max-width: 200px;
                text-align: center;
            `;
            document.body.appendChild(tip);
            
            const rect = this.getBoundingClientRect();
            tip.style.left = (rect.left + rect.width / 2 - tip.offsetWidth / 2) + 'px';
            tip.style.top = (rect.top - tip.offsetHeight - 8) + 'px';
            
            this._tooltip = tip;
        });
        
        el.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
        });
    });
}

// ============================================
// FORM VALIDATION
// ============================================
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const required = this.querySelectorAll('[required]');
            
            required.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    field.focus();
                } else {
                    field.classList.remove('error');
                }
            });
            
            // Email validation
            const emailFields = this.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                if (field.value && !isValidEmail(field.value)) {
                    isValid = false;
                    field.classList.add('error');
                }
            });
            
            // Number validation
            const numberFields = this.querySelectorAll('input[type="number"]');
            numberFields.forEach(field => {
                const min = parseFloat(field.getAttribute('min'));
                const max = parseFloat(field.getAttribute('max'));
                const val = parseFloat(field.value);
                
                if (field.value && !isNaN(min) && val < min) {
                    isValid = false;
                    field.classList.add('error');
                }
                if (field.value && !isNaN(max) && val > max) {
                    isValid = false;
                    field.classList.add('error');
                }
            });
            
            // Password confirmation
            const password = this.querySelector('input[name="password"]');
            const confirm = this.querySelector('input[name="confirm_password"]');
            if (password && confirm && password.value !== confirm.value) {
                isValid = false;
                confirm.classList.add('error');
                alert('Passwords do not match.');
            }
            
            if (!isValid) {
                e.preventDefault();
                // Scroll to first error
                const firstError = this.querySelector('.error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    });
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// ============================================
// CONFIRM DIALOGS
// ============================================
function initConfirmDialogs() {
    const links = document.querySelectorAll('[data-confirm]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

// ============================================
// SIDEBAR TOGGLE (Mobile)
// ============================================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
}

// Close sidebar on outside click
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.menu-toggle');
    if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
        if (!sidebar.contains(e.target) && !toggle?.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    }
});

// ============================================
// MODAL HELPERS
// ============================================
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// Close modal on backdrop click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
});

// ============================================
// TABLE SORTING
// ============================================
function sortTable(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const th = table.querySelectorAll('thead th')[columnIndex];
    const forcedType = (th && th.getAttribute('data-sort-type')) || '';
    const isAscending = table.dataset.sortAsc === 'true';

    const isPlainNumber = (text) => {
        if (!text || /[A-Za-z]/.test(text)) return false;
        const cleaned = text.replace(/[₱$,%\s]/g, '').replace(/,/g, '');
        return /^-?\d+(\.\d+)?$/.test(cleaned);
    };

    rows.sort((a, b) => {
        const aVal = a.cells[columnIndex]?.textContent.trim() || '';
        const bVal = b.cells[columnIndex]?.textContent.trim() || '';
        const useNumeric = forcedType === 'number' || (forcedType !== 'string' && isPlainNumber(aVal) && isPlainNumber(bVal));

        if (useNumeric) {
            const aNum = parseFloat(aVal.replace(/[₱$,%\s]/g, '').replace(/,/g, ''));
            const bNum = parseFloat(bVal.replace(/[₱$,%\s]/g, '').replace(/,/g, ''));
            return isAscending ? aNum - bNum : bNum - aNum;
        }
        return isAscending ? aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' }) : bVal.localeCompare(aVal, undefined, { numeric: true, sensitivity: 'base' });
    });

    rows.forEach(row => tbody.appendChild(row));
    table.dataset.sortAsc = isAscending ? 'false' : 'true';
}

// ============================================
// PRINT HELPER
// ============================================
function printPage() {
    window.print();
}

// ============================================
// EXPORT TO CSV
// ============================================
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const rowData = [];
        row.querySelectorAll('th, td').forEach(cell => {
            let text = cell.textContent.trim();
            // Remove multiple spaces and newlines
            text = text.replace(/\s+/g, ' ');
            // Escape quotes
            text = text.replace(/"/g, '""');
            rowData.push(`"${text}"`);
        });
        csv.push(rowData.join(','));
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${filename || 'export'}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

// ============================================
// NUMBER FORMATTING
// ============================================
function formatCurrency(amount) {
    const n = parseFloat(amount);
    if (!Number.isFinite(n)) return '₱0.00';
    return '₱' + n.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatNumber(num) {
    if (typeof num === 'string' && /[A-Za-z-]/.test(num) && !/^-?\d+(\.\d+)?$/.test(num.trim())) {
        return num;
    }
    const n = parseInt(num, 10);
    if (!Number.isFinite(n)) return String(num ?? '');
    return n.toLocaleString();
}

// ============================================
// DATE HELPERS
// ============================================
function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatDateTime(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// ============================================
// SEARCH FILTER
// ============================================
function filterTable(tableId, searchInput) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const searchTerm = searchInput.value.toLowerCase().trim();
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

// ============================================
// FORM RESET
// ============================================
function resetForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.reset();
        form.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
    }
}

// ============================================
// LOADING INDICATOR
// ============================================
function showLoading(containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        container.innerHTML = `
            <div class="loading-spinner" style="text-align:center;padding:2rem;">
                <i class="fas fa-spinner fa-spin fa-2x" style="color:#4e73df;"></i>
                <p style="color:#94a3b8;margin-top:0.5rem;">Loading...</p>
            </div>
        `;
    }
}

// ============================================
// TOAST NOTIFICATION
// ============================================
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    const colors = {
        success: '#1cc88a',
        error: '#e74a3b',
        warning: '#f6c23e',
        info: '#36b9cc'
    };
    
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${colors[type] || colors.info};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 500;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        animation: slideUp 0.3s ease;
        max-width: 400px;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        setTimeout(() => toast.remove(), 500);
    }, 4000);
}

// Add keyframe for toast animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
`;
document.head.appendChild(style);

// ============================================
// EXPOSE GLOBALLY
// ============================================
window.toggleSidebar = toggleSidebar;
window.openModal = openModal;
window.closeModal = closeModal;
window.sortTable = sortTable;
window.printPage = printPage;
window.exportToCSV = exportToCSV;
window.formatCurrency = formatCurrency;
window.formatNumber = formatNumber;
window.formatDate = formatDate;
window.formatDateTime = formatDateTime;
window.filterTable = filterTable;
window.resetForm = resetForm;
window.showLoading = showLoading;
window.showToast = showToast;