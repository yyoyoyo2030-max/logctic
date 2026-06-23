/**
 * نظام التحديث الصامت - كل 5 ثواني
 * تحديث كل البيانات بدون إعادة تحميل الصفحة
 */

(function() {
    'use strict';
    
    const REFRESH_INTERVAL = 5000; // 5 ثواني
    let lastTransfersUpdate = Date.now();
    
    // تحديث الإحصائيات في Dashboard
    function updateStats() {
        const dashboard = document.querySelector('[data-page="dashboard"]');
        if (!dashboard) return;
        
        fetch('../../api/stats.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // تحديث الأرقام
                    updateElement('[data-stat="drivers"]', data.drivers_count);
                    updateElement('[data-stat="branches"]', data.branches_count);
                    updateElement('[data-stat="tasks"]', data.tasks_count);
                    updateElement('[data-stat="transfers"]', data.transfers_count);
                }
            })
            .catch(() => {});
    }
    
    // تحديث الإشعارات
    function updateNotifications() {
        const badge = document.querySelector('.notification-badge');
        if (!badge) return;
        
        fetch('../../api/notifications.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.unread_count > 0) {
                    badge.textContent = data.unread_count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(() => {});
    }
    
    // تحديث التحويلات الحديثة في Dashboard
    function updateRecentTransfers() {
        const transfersList = document.querySelector('.recent-transfers tbody');
        if (!transfersList) return;
        
        fetch('../../api/recent-transfers.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.transfers && data.transfers.length > 0) {
                    // مسح الجدول الحالي
                    transfersList.innerHTML = '';
                    
                    // إضافة التحويلات الجديدة
                    data.transfers.forEach(transfer => {
                        const row = createTransferRow(transfer);
                        transfersList.appendChild(row);
                    });
                }
            })
            .catch(() => {});
    }
    
    // إنشاء صف تحويل جديد
    function createTransferRow(transfer) {
        const tr = document.createElement('tr');
        tr.dataset.transferId = transfer.id;
        
        const statusClass = getStatusClass(transfer.status);
        const statusText = getStatusText(transfer.status);
        
        tr.innerHTML = `
            <td>${transfer.tracking_number || transfer.id}</td>
            <td>${transfer.from_branch_name || '-'}</td>
            <td>${transfer.to_branch_name || '-'}</td>
            <td>${transfer.driver_name || 'غير محدد'}</td>
            <td><span class="badge ${statusClass}">${statusText}</span></td>
            <td>${formatDate(transfer.created_at)}</td>
            <td>
                <a href="view_transfer.php?id=${transfer.id}" class="btn btn-sm btn-info">
                    <i class="fas fa-eye"></i>
                </a>
            </td>
        `;
        
        return tr;
    }
    
    // تحديث حالة المهام
    function updateTasksStatus() {
        const tasksTable = document.querySelector('[data-page="tasks"] tbody');
        if (!tasksTable) return;
        
        fetch('../../api/tasks-status.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.tasks) {
                    data.tasks.forEach(task => {
                        const row = tasksTable.querySelector(`tr[data-task-id="${task.id}"]`);
                        if (row) {
                            const statusCell = row.querySelector('.task-status');
                            if (statusCell && statusCell.textContent.trim() !== task.status) {
                                statusCell.textContent = task.status;
                                row.style.backgroundColor = '#fffbcc';
                                setTimeout(() => row.style.backgroundColor = '', 2000);
                            }
                        }
                    });
                }
            })
            .catch(() => {});
    }
    
    // تحديث حالة التحويلات
    function updateTransfersStatus() {
        const transfersTable = document.querySelector('[data-page="transfers"] tbody');
        if (!transfersTable) return;
        
        fetch('../../api/transfers-status.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.transfers) {
                    data.transfers.forEach(transfer => {
                        const row = transfersTable.querySelector(`tr[data-transfer-id="${transfer.id}"]`);
                        if (row) {
                            const statusCell = row.querySelector('.transfer-status');
                            if (statusCell && statusCell.textContent.trim() !== transfer.status) {
                                statusCell.textContent = getStatusText(transfer.status);
                                statusCell.className = 'badge ' + getStatusClass(transfer.status);
                                row.style.backgroundColor = '#fffbcc';
                                setTimeout(() => row.style.backgroundColor = '', 2000);
                            }
                        }
                    });
                }
            })
            .catch(() => {});
    }
    
    // جلب جميع التحويلات وتحديث الجدول بالكامل
    function refreshAllTransfers() {
        const transfersTable = document.querySelector('[data-page="all-transfers"] tbody');
        if (!transfersTable) return;
        
        fetch('../../api/all-transfers.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.transfers) {
                    const currentIds = Array.from(transfersTable.querySelectorAll('tr'))
                        .map(tr => tr.dataset.transferId);
                    
                    // إضافة التحويلات الجديدة
                    data.transfers.forEach(transfer => {
                        if (!currentIds.includes(String(transfer.id))) {
                            const row = createTransferRow(transfer);
                            row.style.backgroundColor = '#d4edda';
                            transfersTable.insertBefore(row, transfersTable.firstChild);
                            setTimeout(() => row.style.backgroundColor = '', 3000);
                        }
                    });
                }
            })
            .catch(() => {});
    }
    
    // دوال مساعدة
    function updateElement(selector, value) {
        const element = document.querySelector(selector);
        if (element && element.textContent !== String(value)) {
            element.textContent = value;
            element.style.color = '#28a745';
            setTimeout(() => element.style.color = '', 1000);
        }
    }
    
    function getStatusClass(status) {
        const statusMap = {
            'pending': 'badge-warning',
            'assigned': 'badge-info',
            'in_transit': 'badge-primary',
            'delivered': 'badge-success',
            'cancelled': 'badge-danger'
        };
        return statusMap[status] || 'badge-secondary';
    }
    
    function getStatusText(status) {
        const textMap = {
            'pending': 'قيد الانتظار',
            'assigned': 'تم التعيين',
            'in_transit': 'جاري التوصيل',
            'delivered': 'تم التوصيل',
            'cancelled': 'ملغي'
        };
        return textMap[status] || status;
    }
    
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('ar-SA', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // بدء التحديث التلقائي لكل شيء
    function startAutoRefresh() {
        // تحديث فوري أول مرة
        updateStats();
        updateNotifications();
        updateRecentTransfers();
        updateTasksStatus();
        updateTransfersStatus();
        refreshAllTransfers();
        
        // ثم كل 3 ثواني
        setInterval(() => {
            updateStats();
            updateNotifications();
            updateRecentTransfers();
            updateTasksStatus();
            updateTransfersStatus();
            refreshAllTransfers();
        }, REFRESH_INTERVAL);
    }
    
    // التشغيل عند تحميل الصفحة
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startAutoRefresh);
    } else {
        startAutoRefresh();
    }
    
})();
