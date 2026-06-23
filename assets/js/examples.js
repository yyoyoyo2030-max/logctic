/**
 * أمثلة استخدام الميزات الجديدة في Logistic Pro
 * 
 * هذا الملف يحتوي على أمثلة عملية لاستخدام جميع الميزات
 */

// ================================================
// 1. استخدام Toast Notifications
// ================================================

// مثال: بعد إضافة سجل جديد
function onAddSuccess() {
    showToast('تمت الإضافة بنجاح! ✓', 'success');
}

// مثال: عند حدوث خطأ
function onError() {
    showToast('عذراً، حدث خطأ في النظام', 'error');
}

// مثال: تحذير
function onWarning() {
    showToast('انتبه! لديك مهام متأخرة', 'warning');
}

// ================================================
// 2. استخدام Loading Spinner مع AJAX
// ================================================

// مثال: تحميل بيانات
async function loadData() {
    showLoading();
    
    try {
        const response = await fetch('/api/transfers');
        const data = await response.json();
        
        hideLoading();
        showToast('تم تحميل البيانات', 'success');
        
        return data;
    } catch (error) {
        hideLoading();
        showToast('فشل تحميل البيانات', 'error');
    }
}

// مثال: إرسال نموذج
function submitForm(formData) {
    showLoading();
    
    fetch('/api/submit', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast('تم الحفظ بنجاح', 'success');
            location.reload();
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showToast('فشلت العملية', 'error');
    });
}

// ================================================
// 3. استخدام SweetAlert2 للتأكيدات
// ================================================

// مثال: حذف شحنة
function deleteTransfer(transferId) {
    confirmDelete('هل تريد حذف هذه الشحنة؟').then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch(`/api/transfers/delete/${transferId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('تم الحذف بنجاح', 'success');
                    // إزالة الصف من الجدول
                    document.querySelector(`#transfer-${transferId}`).remove();
                } else {
                    showToast('فشل الحذف', 'error');
                }
            });
        }
    });
}

// مثال: تأكيد إرسال
function confirmSend() {
    Swal.fire({
        title: 'تأكيد الإرسال',
        text: 'هل تريد إرسال هذه الشحنة؟',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'نعم، أرسل',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            // قم بعملية الإرسال
            showToast('تم إرسال الشحنة', 'success');
        }
    });
}

// ================================================
// 4. استخدام البحث في الجداول
// ================================================

// HTML المطلوب:
/*
<input type="text" 
       class="table-search" 
       data-table="transfersTable" 
       placeholder="🔍 بحث في الشحنات...">

<table id="transfersTable">
    <!-- محتوى الجدول -->
</table>
*/

// الكود يعمل تلقائياً! ✓

// ================================================
// 5. استخدام Dark Mode برمجياً
// ================================================

// التحقق من الوضع الحالي
function getCurrentTheme() {
    return document.documentElement.getAttribute('data-theme');
}

// تفعيل الوضع الداكن
function enableDarkMode() {
    document.documentElement.setAttribute('data-theme', 'dark');
    localStorage.setItem('theme', 'dark');
}

// تفعيل الوضع الفاتح
function enableLightMode() {
    document.documentElement.setAttribute('data-theme', 'light');
    localStorage.setItem('theme', 'light');
}

// ================================================
// 6. إنشاء رسوم بيانية Chart.js
// ================================================

// مثال: رسم بياني دائري للحالات
function createStatusChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['مكتمل', 'قيد التنفيذ', 'معلق'],
            datasets: [{
                data: [data.completed, data.inProgress, data.pending],
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',  // أخضر
                    'rgba(14, 165, 233, 0.8)',  // أزرق
                    'rgba(245, 158, 11, 0.8)'   // برتقالي
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// مثال: رسم بياني خطي للشحنات الشهرية
function createMonthlyChart(canvasId, months, values) {
    const ctx = document.getElementById(canvasId);
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'عدد الشحنات',
                data: values,
                borderColor: 'rgba(14, 165, 233, 1)',
                backgroundColor: 'rgba(14, 165, 233, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// ================================================
// 7. استخدام fetchData Helper
// ================================================

// مثال: جلب الإحصائيات
async function loadStatistics() {
    const data = await fetchData('/api/statistics');
    
    if (data) {
        // عرض الإحصائيات
        document.getElementById('totalTransfers').textContent = data.total;
        document.getElementById('completedTransfers').textContent = data.completed;
        showToast('تم تحديث الإحصائيات', 'info');
    }
}

// ================================================
// 8. Form Validation المحسن
// ================================================

// HTML المطلوب:
/*
<form data-validate>
    <div class="form-group">
        <label>الاسم</label>
        <input type="text" name="name" required>
    </div>
    <button type="submit">حفظ</button>
</form>
*/

// Validation يعمل تلقائياً! ✓

// ================================================
// 9. مثال كامل: إضافة شحنة جديدة
// ================================================

function addNewTransfer() {
    // عرض نموذج modal
    showModal('addTransferModal');
}

function submitNewTransfer(form) {
    const formData = new FormData(form);
    
    showLoading();
    
    fetch('/api/transfers/add', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showToast('تم إضافة الشحنة بنجاح', 'success');
            hideModal('addTransferModal');
            location.reload();
        } else {
            showToast(data.message || 'فشلت الإضافة', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showToast('حدث خطأ في الاتصال', 'error');
        console.error(error);
    });
    
    return false; // منع الإرسال الافتراضي
}

// ================================================
// 10. مثال: تحديث حالة الشحنة
// ================================================

function updateTransferStatus(transferId, newStatus) {
    showLoading();
    
    fetch(`/api/transfers/${transferId}/status`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ status: newStatus })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showToast('تم تحديث الحالة', 'success');
            
            // تحديث الواجهة
            const badge = document.querySelector(`#transfer-${transferId} .status-badge`);
            badge.textContent = newStatus;
            badge.className = `status-badge status-${newStatus}`;
        } else {
            showToast('فشل التحديث', 'error');
        }
    });
}

// ================================================
// ملاحظات مهمة:
// ================================================

/*
1. تأكد من تحميل main.js قبل استخدام أي دالة
2. جميع الدوال متاحة عالمياً (window.showToast, window.showLoading...)
3. SweetAlert2 و Chart.js محملين من CDN
4. Font Awesome للأيقونات
5. جميع الأمثلة تدعم RTL
*/

console.log('✅ Examples.js loaded - All features ready!');
