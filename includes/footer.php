            </div>
        </div>
    </div>
    
    <footer class="app-footer">
        <div class="footer-content">
            <p>تطوير: <strong>المهندس هارون الأهدل</strong></p>
            <p>للتواصل: <a href="tel:0531847156">0531847156</a></p>
        </div>
    </footer>
    
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js" defer></script>
    
    <!-- نظام المراقبة: تسجيل الأخطاء ومشاهدات الصفحات -->
    <script>
    (function() {
        var SITE_URL = '<?php echo SITE_URL; ?>';
        
        // تسجيل مشاهدة الصفحة
        function logPageView() {
            fetch(SITE_URL + '/api/log_event.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    type: 'page_view',
                    action: document.title || 'صفحة',
                    page_url: window.location.pathname
                })
            }).catch(function(){});
        }
        
        // تسجيل أخطاء JavaScript
        window.onerror = function(message, source, lineno, colno, error) {
            fetch(SITE_URL + '/api/log_event.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    type: 'error',
                    action: 'JS Error: ' + message,
                    details: JSON.stringify({source: source, line: lineno, col: colno, stack: error ? error.stack : ''}),
                    page_url: window.location.pathname
                })
            }).catch(function(){});
        };
        
        // تسجيل أخطاء Promise غير المعالجة
        window.addEventListener('unhandledrejection', function(event) {
            fetch(SITE_URL + '/api/log_event.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    type: 'error',
                    action: 'Promise Error: ' + (event.reason ? event.reason.message || String(event.reason) : 'Unknown'),
                    page_url: window.location.pathname
                })
            }).catch(function(){});
        });
        
        // تأخير تسجيل المشاهدة لضمان تحميل الصفحة
        setTimeout(logPageView, 1000);
    })();
    </script>
    <!-- تحديث الجداول فقط كل 10 ثواني بدون إعادة تحميل الصفحة -->
    <script>
    (function() {
        const REFRESH_INTERVAL = 10000; // 10 ثواني
        
        function isModalOpen() {
            const modals = document.querySelectorAll('.modal.show, .modal[style*="display: block"], .swal2-popup, .swal2-container, [role="dialog"]:not([aria-hidden="true"])');
            if (modals.length > 0) return true;
            
            const activeElement = document.activeElement;
            if (activeElement && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA' || activeElement.tagName === 'SELECT')) {
                return true;
            }
            
            const notificationDropdown = document.querySelector('.notification-dropdown.show, .notification-dropdown[style*="display: block"]');
            if (notificationDropdown) return true;
            
            return false;
        }
        
        function refreshTables() {
            if (isModalOpen()) return;
            
            // البحث عن جميع الجداول في الصفحة
            const tables = document.querySelectorAll('table tbody');
            if (tables.length === 0) return;
            
            // جلب محتوى الصفحة الحالية
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // تحديث كل جدول
                    tables.forEach((tbody, index) => {
                        const newTbody = doc.querySelectorAll('table tbody')[index];
                        if (newTbody && tbody.innerHTML !== newTbody.innerHTML) {
                            // حفظ الصف المحدد إن وجد
                            const selectedRow = tbody.querySelector('tr.selected, tr.active');
                            const selectedId = selectedRow ? selectedRow.dataset.id : null;
                            
                            // تحديث المحتوى
                            tbody.innerHTML = newTbody.innerHTML;
                            
                            // إعادة تحديد الصف
                            if (selectedId) {
                                const newSelectedRow = tbody.querySelector(`tr[data-id="${selectedId}"]`);
                                if (newSelectedRow) newSelectedRow.classList.add('selected', 'active');
                            }
                            
                            // تأثير بصري خفيف للإشارة للتحديث
                            tbody.style.opacity = '0.7';
                            setTimeout(() => tbody.style.opacity = '1', 300);
                        }
                    });
                    
                    // تحديث الإحصائيات أيضاً (البطاقات)
                    const statCards = document.querySelectorAll('[data-stat]');
                    statCards.forEach(card => {
                        const statType = card.dataset.stat;
                        const newCard = doc.querySelector(`[data-stat="${statType}"]`);
                        if (newCard && card.textContent !== newCard.textContent) {
                            card.textContent = newCard.textContent;
                            card.style.color = '#28a745';
                            setTimeout(() => card.style.color = '', 1000);
                        }
                    });
                })
                .catch(() => {});
        }
        
        // بدء التحديث التلقائي
        setInterval(refreshTables, REFRESH_INTERVAL);
    })();
    </script>
</body>
</html>
