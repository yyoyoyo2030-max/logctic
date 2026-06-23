/**
 * Logistic Pro - Main JavaScript
 * نظام إدارة اللوجستيات المتقدم
 */

// ================================================
// 1. Dark Mode System
// ================================================
const themeToggle = document.getElementById('theme-toggle');
const html = document.documentElement;

// تحميل الوضع المحفوظ
const savedTheme = localStorage.getItem('theme') || 'light';
html.setAttribute('data-theme', savedTheme);
updateThemeIcon(savedTheme);

themeToggle?.addEventListener('click', () => {
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeIcon(newTheme);
    
    showToast(newTheme === 'dark' ? 'تم تفعيل الوضع الداكن' : 'تم تفعيل الوضع الفاتح', 'success');
});

function updateThemeIcon(theme) {
    const icon = themeToggle?.querySelector('i');
    if (icon) {
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// ================================================
// 2. Toast Notification System
// ================================================
function showToast(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icons = {
        success: 'fa-circle-check',
        error: 'fa-circle-xmark',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };
    
    toast.innerHTML = `
        <i class="fas ${icons[type]}"></i>
        <span>${message}</span>
    `;
    
    container.appendChild(toast);
    
    // Animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Remove
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// جعل showToast متاحة عالمياً
window.showToast = showToast;

// ================================================
// 3. Loading Overlay
// ================================================
function showLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) overlay.style.display = 'flex';
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) overlay.style.display = 'none';
}

window.showLoading = showLoading;
window.hideLoading = hideLoading;

// ================================================
// 4. Table Search & Filter
// ================================================
function initTableSearch() {
    const searchInputs = document.querySelectorAll('.table-search');
    
    searchInputs.forEach(input => {
        const tableId = input.dataset.table;
        const table = document.getElementById(tableId);
        if (!table) return;
        
        input.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    });
}

// ================================================
// 5. Confirm Delete with SweetAlert2
// ================================================
function confirmDelete(message = 'هل أنت متأكد من الحذف؟') {
    return Swal.fire({
        title: 'تأكيد الحذف',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء',
        reverseButtons: true
    });
}

window.confirmDelete = confirmDelete;

// ================================================
// 6. Mobile Sidebar Toggle
// ================================================
function initMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    // إنشاء زر القائمة للموبايل
    if (window.innerWidth <= 768) {
        const menuBtn = document.createElement('button');
        menuBtn.className = 'mobile-menu-btn';
        menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
        document.body.appendChild(menuBtn);
        
        menuBtn.addEventListener('click', () => {
            sidebar?.classList.toggle('mobile-open');
            document.body.classList.toggle('sidebar-open');
        });
        
        // إغلاق عند النقر خارج القائمة
        mainContent?.addEventListener('click', () => {
            sidebar?.classList.remove('mobile-open');
            document.body.classList.remove('sidebar-open');
        });
    }
}

// ================================================
// 7. Form Validation Enhancement
// ================================================
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    showToast(`الحقل "${field.previousElementSibling?.textContent}" مطلوب`, 'error');
                } else {
                    field.classList.remove('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
}

// ================================================
// 8. Auto-hide Alerts
// ================================================
function initAutoHideAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('alert-success')) {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 3000);
        }
    });
}

// ================================================
// 9. Smooth Scroll
// ================================================
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

// ================================================
// 10. Initialize on DOM Load
// ================================================
document.addEventListener('DOMContentLoaded', () => {
    initTableSearch();
    initMobileSidebar();
    initFormValidation();
    initAutoHideAlerts();
    
    // إخفاء شاشة التحميل إذا كانت موجودة
    hideLoading();
    
    console.log('✅ Logistic Pro - System Ready');
});

// ================================================
// 11. AJAX Helper Functions
// ================================================
async function fetchData(url, options = {}) {
    showLoading();
    try {
        const response = await fetch(url, options);
        const data = await response.json();
        hideLoading();
        return data;
    } catch (error) {
        hideLoading();
        showToast('حدث خطأ في الاتصال', 'error');
        console.error('Fetch error:', error);
        return null;
    }
}

window.fetchData = fetchData;

// ================================================
// 12. Number Formatting
// ================================================
function formatNumber(num) {
    return new Intl.NumberFormat('ar-SA').format(num);
}

window.formatNumber = formatNumber;

// ================================================
// 13. Date Formatting
// ================================================
function formatDate(dateString) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('ar-SA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(date);
}

window.formatDate = formatDate;

// ================================================
// 14. Mobile Menu Toggle
// ================================================
const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');

// فتح/إغلاق القائمة
function toggleMobileMenu() {
    sidebar?.classList.toggle('mobile-open');
    mobileMenuToggle?.classList.toggle('active');
    sidebarOverlay?.classList.toggle('active');
    document.body.style.overflow = sidebar?.classList.contains('mobile-open') ? 'hidden' : '';
}

// زر القائمة
mobileMenuToggle?.addEventListener('click', toggleMobileMenu);

// الغطاء الخلفي
sidebarOverlay?.addEventListener('click', toggleMobileMenu);

// إغلاق القائمة عند النقر على أي رابط
const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
sidebarLinks.forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            toggleMobileMenu();
        }
    });
});

// إغلاق القائمة عند تغيير حجم الشاشة للكبير
window.addEventListener('resize', () => {
    if (window.innerWidth > 768 && sidebar?.classList.contains('mobile-open')) {
        toggleMobileMenu();
    }
});
