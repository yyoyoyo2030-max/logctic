<?php
/**
 * نظام إدارة اللوجستيك
 * إعدادات الواتساب (Evolution API)
 */

header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('views/dashboard/dashboard.php');
}

$success = '';
$error = '';

// Create custom routes table if it doesn't exist
$conn->exec("CREATE TABLE IF NOT EXISTS whatsapp_custom_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    description VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");


// تحديث الإعدادات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    try {
        $api_url = clean_input($_POST['api_url']);
        $api_key = clean_input($_POST['api_key']);
        $instance_name = clean_input($_POST['instance_name']);
        $drivers_manager_group_id = '';
        $warehouse_manager_group_id = clean_input($_POST['warehouse_manager_group_id'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // التحقق من وجود إعدادات مسبقة
        $stmt = $conn->query("SELECT id FROM whatsapp_settings LIMIT 1");
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = $conn->prepare("UPDATE whatsapp_settings SET api_url=?, api_key=?, instance_name=?, drivers_manager_group_id=?, warehouse_manager_group_id=?, is_active=?");
            $stmt->execute([$api_url, $api_key, $instance_name, $drivers_manager_group_id, $warehouse_manager_group_id, $is_active]);
        } else {
            $stmt = $conn->prepare("INSERT INTO whatsapp_settings (api_url, api_key, instance_name, drivers_manager_group_id, warehouse_manager_group_id, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$api_url, $api_key, $instance_name, $drivers_manager_group_id, $warehouse_manager_group_id, $is_active]);
        }
        
        // حفظ إعدادات جروبات الفروع
        if (isset($_POST['branch_groups']) && is_array($_POST['branch_groups'])) {
            $stmt_branch = $conn->prepare("UPDATE branches SET whatsapp_group_id = ? WHERE id = ?");
            foreach ($_POST['branch_groups'] as $branch_id => $group_id) {
                $stmt_branch->execute([clean_input($group_id), $branch_id]);
            }
        }
        
        $success = 'تم حفظ الإعدادات بنجاح';
    } catch(Exception $e) {
        $error = 'خطأ أثناء الحفظ: ' . $e->getMessage();
    }
}

// جلب الإعدادات الحالية
$stmt = $conn->query("SELECT * FROM whatsapp_settings LIMIT 1");
$settings = $stmt->fetch() ?: [
    'api_url' => '', 'api_key' => '', 'instance_name' => '',
    'drivers_manager_group_id' => '', 'warehouse_manager_group_id' => '', 'is_active' => 0
];

// جلب الفروع
$branches = $conn->query("SELECT id, name, whatsapp_group_id FROM branches ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// جلب التوجيهات المخصصة
$custom_routes = $conn->query("SELECT * FROM whatsapp_custom_routes ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fab fa-whatsapp" style="color: #25D366;"></i> إعدادات إشعارات الواتساب</h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="content-section" style="max-width: 800px; margin: 0 auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <form method="POST" action="">
        <div class="form-section">
            <div class="form-section-title">
                <i class="fas fa-plug"></i>
                إعدادات Evolution API
            </div>
            
            <div class="form-group">
                <label>رابط API (URL) *</label>
                <input type="url" name="api_url" class="form-control" value="<?php echo htmlspecialchars($settings['api_url']); ?>" placeholder="مثال: https://api.yoursite.com" required>
                <small class="text-muted">الرابط الأساسي لخادم Evolution API</small>
            </div>
            
            <div class="form-group">
                <label>مفتاح API (Global API Key) *</label>
                <div style="position: relative;">
                    <input type="password" name="api_key" id="api_key_input" class="form-control" value="<?php echo htmlspecialchars($settings['api_key']); ?>" required style="padding-left: 40px;">
                    <button type="button" id="toggleApiKey" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666; font-size: 16px;" title="إظهار/إخفاء">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <input type="hidden" name="instance_name" value="logistic_system">
        </div>

        <div class="form-section" style="margin-top: 30px;">
            <div class="form-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <i class="fas fa-users"></i>
                    إعدادات المجموعات (الجروبات)
                </div>
                <button type="button" id="fetchGroupsBtn" class="btn btn-sm btn-info">
                    <i class="fas fa-sync"></i> جلب المجموعات من الواتساب
                </button>
            </div>
            

            <div class="form-group">
                <label><i class="fab fa-whatsapp" style="color: #25D366;"></i> الجروب الرئيسي (العام) لجميع الفروع</label>
                <select name="warehouse_manager_group_id" id="warehouse_group" class="form-control">
                    <option value="">-- اضغط على زر الجلب لاختيار مجموعة --</option>
                    <?php if(!empty($settings['warehouse_manager_group_id'])): ?>
                        <option value="<?php echo htmlspecialchars($settings['warehouse_manager_group_id']); ?>" selected>المجموعة الحالية: <?php echo htmlspecialchars($settings['warehouse_manager_group_id']); ?></option>
                    <?php endif; ?>
                </select>
                <small class="text-muted">هذا الجروب يستقبل نسخة من جميع إشعارات تأكيد الاستلام لكافة الفروع. إذا لم يكن لفرع معين جروب مخصص، سيتم الإرسال هنا فقط.</small>
            </div>
            
            <hr style="border: 0; border-top: 1px solid #ddd; margin: 25px 0;">
            
            <h4 style="margin-bottom: 15px; color: #2c3e50;"><i class="fas fa-code-branch"></i> الجروبات المخصصة للفروع</h4>
            <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                <?php foreach ($branches as $branch): ?>
                <div style="flex: 1 1 calc(33.333% - 15px); min-width: 220px; background: #fdfdfd; border: 1px solid #e1e4e8; border-radius: 10px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.2s ease;">
                    <div style="display: flex; align-items: center; margin-bottom: 12px;">
                        <div style="width: 32px; height: 32px; background: #e3f2fd; color: #007bff; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-left: 10px;">
                            <i class="fas fa-store"></i>
                        </div>
                        <strong style="font-size: 1.05em; color: #34495e;"><?php echo htmlspecialchars($branch['name']); ?></strong>
                    </div>
                    <select name="branch_groups[<?php echo $branch['id']; ?>]" class="form-control branch-group-select" data-current="<?php echo htmlspecialchars($branch['whatsapp_group_id'] ?? ''); ?>" style="border-radius: 6px; border: 1px solid #ced4da; font-size: 0.9em; padding: 6px 12px; height: auto;">
                        <option value="">-- الجروب العام --</option>
                        <?php if(!empty($branch['whatsapp_group_id'])): ?>
                            <option value="<?php echo htmlspecialchars($branch['whatsapp_group_id']); ?>" selected>المجموعة الحالية: <?php echo htmlspecialchars($branch['whatsapp_group_id']); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-section" style="margin-top: 30px;">
            <div class="form-section-title">
                <i class="fas fa-qrcode"></i>
                ربط الواتساب (QR Code)
            </div>
            
            <div class="connection-status-box" style="text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #f9f9f9;">
                <h3 id="wa_status_text">جاري فحص حالة الاتصال...</h3>
                <div id="wa_qr_container" style="margin: 20px 0; display: none;">
                    <img id="wa_qr_image" src="" alt="WhatsApp QR Code" style="max-width: 250px; border: 5px solid #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px;">
                    <p style="margin-top: 10px; color: #666;">افتح تطبيق الواتساب في هاتفك وامسح الكود أعلاه</p>
                </div>
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <button type="button" id="btnReconnect" class="btn btn-success" style="display: none;"><i class="fas fa-sync-alt"></i> إعادة الاتصال</button>
                    <button type="button" id="btnGenerateQR" class="btn btn-primary" style="display: none;"><i class="fas fa-qrcode"></i> ربط برقم جديد (QR)</button>
                </div>
            </div>
        </div>
        
        <div class="form-group full-width" style="margin-top: 20px;">
            <label class="toggle-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="is_active" <?php echo $settings['is_active'] ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                <strong>تفعيل إرسال الإشعارات عبر الواتساب</strong>
            </label>
        </div>
        
        <div class="form-actions" style="margin-top: 30px;">
            <button type="submit" name="save_settings" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> حفظ الإعدادات</button>
        </div>
    </form>
</div>

<div class="content-section" style="max-width: 800px; margin: 30px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <div class="form-section-title" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <i class="fas fa-route"></i>
            توجيه الإشعارات المخصصة (لغير المستخدمين)
        </div>
    </div>
    <p class="text-muted" style="margin-bottom: 20px;">يمكنك هنا تخصيص إرسال عمليات معينة داخل النظام إلى أرقام هواتف محددة مباشرة، حتى وإن لم يكن لديهم حساب في النظام.</p>

    <form id="addRouteForm" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef; margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 200px;">
            <label style="font-size: 0.9em; margin-bottom: 5px; display: block;">العملية المستهدفة *</label>
            <select name="event_type" id="route_event_type" class="form-control" required>
                <option value="">-- اختر العملية --</option>
                <option value="all">جميع العمليات</option>
                <option value="transfer_created">رفع تحويل</option>
                <option value="transfer_updated">استلام وتسليم (تحديث الحالة)</option>
                <option value="driver_assigned_transfer">تعيين السائق (لتحويل)</option>
                <option value="task_created">رفع مهمة</option>
                <option value="driver_assigned_task">تعيين السائق (لمهمة)</option>
            </select>
        </div>
        <div style="flex: 1; min-width: 150px;">
            <label style="font-size: 0.9em; margin-bottom: 5px; display: block;">رقم الهاتف (بدون أصفار) *</label>
            <input type="text" id="route_phone" class="form-control" placeholder="مثال: 5xxxxxxx" required>
        </div>
        <div style="flex: 1; min-width: 150px;">
            <label style="font-size: 0.9em; margin-bottom: 5px; display: block;">اسم الشخص / الوصف</label>
            <input type="text" id="route_desc" class="form-control" placeholder="مثال: المدير العام">
        </div>
        <div>
            <button type="button" id="btnAddRoute" class="btn btn-primary" style="height: 42px;"><i class="fas fa-plus"></i> إضافة التوجيه</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>العملية</th>
                    <th>الرقم</th>
                    <th>الوصف</th>
                    <th>الحالة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($custom_routes)): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted">لا توجد توجيهات مخصصة مضافة حالياً.</td>
                </tr>
                <?php else: ?>
                    <?php 
                    $eventNames = [
                        'all' => 'جميع العمليات',
                        'transfer_created' => 'رفع تحويل',
                        'transfer_updated' => 'استلام وتسليم (تحديث الحالة)',
                        'driver_assigned_transfer' => 'تعيين السائق (لتحويل)',
                        'task_created' => 'رفع مهمة',
                        'driver_assigned_task' => 'تعيين السائق (لمهمة)'
                    ];
                    foreach ($custom_routes as $route): 
                    ?>
                    <tr id="route-row-<?php echo $route['id']; ?>">
                        <td><span class="status-badge" style="background: #e3f2fd; color: #0d47a1;"><?php echo $eventNames[$route['event_type']] ?? $route['event_type']; ?></span></td>
                        <td dir="ltr" style="text-align: right; font-weight: bold;"><?php echo htmlspecialchars($route['phone_number']); ?></td>
                        <td><?php echo htmlspecialchars($route['description']); ?></td>
                        <td>
                            <label class="switch" style="position: relative; display: inline-block; width: 40px; height: 20px;">
                                <input type="checkbox" class="toggle-route" data-id="<?php echo $route['id']; ?>" <?php echo $route['is_active'] ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                                <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: <?php echo $route['is_active'] ? '#2196F3' : '#ccc'; ?>; transition: .4s; border-radius: 20px;"></span>
                                <span class="knob" style="position: absolute; content: ''; height: 16px; width: 16px; left: <?php echo $route['is_active'] ? '22px' : '2px'; ?>; bottom: 2px; background-color: white; transition: .4s; border-radius: 50%;"></span>
                            </label>
                        </td>
                        <td class="actions">
                            <button type="button" class="btn btn-sm btn-danger delete-route" data-id="<?php echo $route['id']; ?>"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('fetchGroupsBtn').addEventListener('click', function() {
    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الجلب...';
    btn.disabled = true;

    fetch('../../api/whatsapp_groups.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.groups && data.groups.length > 0) {
            let warehouseSelect = document.getElementById('warehouse_group');
            
            let currentWarehouse = warehouseSelect.value;
            
            warehouseSelect.innerHTML = '<option value="">-- اختر مجموعة مسؤولي المستودعات --</option>';
            
            let groupsArray = Array.isArray(data.groups) ? data.groups : (data.groups.data || []);
            let branchSelects = document.querySelectorAll('.branch-group-select');
            
            groupsArray.forEach(group => {

                if(group.id && group.subject) {
                    let option2 = document.createElement('option');
                    option2.value = group.id;
                    option2.textContent = group.subject;
                    if(group.id === currentWarehouse) option2.selected = true;
                    warehouseSelect.appendChild(option2);
                }
            });
            
            // تعبئة قوائم الفروع
            branchSelects.forEach(select => {
                let currentBranchVal = select.getAttribute('data-current');
                select.innerHTML = '<option value="">-- الجروب العام --</option>';
                groupsArray.forEach(group => {
                    if(group.id && group.subject) {
                        let opt = document.createElement('option');
                        opt.value = group.id;
                        opt.textContent = group.subject;
                        if(group.id === currentBranchVal) opt.selected = true;
                        select.appendChild(opt);
                    }
                });
            });
            
            btn.innerHTML = '<i class="fas fa-check"></i> تم الجلب بنجاح';
            
            setTimeout(() => {
                alert('تم جلب المجموعات بنجاح. يمكنك الآن النقر على الحقل لاختيار المجموعة من القائمة المنسدلة.');
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }, 500);
        } else {
            alert(data.message || 'فشل جلب المجموعات');
        }
    })
    .catch(error => {
        alert('حدث خطأ في الاتصال');
        console.error(error);
    })
    .finally(() => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    });
});

// إظهار/إخفاء مفتاح API
document.getElementById('toggleApiKey').addEventListener('click', function() {
    const input = document.getElementById('api_key_input');
    const icon = this.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
});

// اتصال الواتساب و الـ QR
const waStatusText = document.getElementById('wa_status_text');
const btnGenerateQR = document.getElementById('btnGenerateQR');
const btnReconnect = document.getElementById('btnReconnect');
const waQrContainer = document.getElementById('wa_qr_container');
const waQrImage = document.getElementById('wa_qr_image');
let checkInterval = null;

function checkConnectionStatus() {
    fetch('../../api/whatsapp_instance.php?action=status')
    .then(res => res.json())
    .then(data => {
        if (data.success && data.state === 'open') {
            waStatusText.innerHTML = '<span style="color: #25D366;"><i class="fas fa-check-circle"></i> الرقم متصل بنجاح! الواتساب جاهز للإرسال.</span>';
            btnGenerateQR.style.display = 'none';
            btnReconnect.style.display = 'none';
            waQrContainer.style.display = 'none';
            if (checkInterval) clearInterval(checkInterval);
        } else {
            waStatusText.innerHTML = '<span style="color: #e74c3c;"><i class="fas fa-times-circle"></i> غير متصل.</span>';
            btnReconnect.style.display = 'inline-block';
            btnGenerateQR.style.display = 'inline-block';
        }
    }).catch(err => {
        waStatusText.innerHTML = '<span style="color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> لا يمكن فحص الاتصال، تأكد من حفظ الرابط والمفتاح أولاً.</span>';
    });
}

// زر إعادة الاتصال (بدون QR - يستخدم الجلسة المحفوظة)
btnReconnect.addEventListener('click', function() {
    waStatusText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري إعادة الاتصال...';
    btnReconnect.disabled = true;
    btnGenerateQR.disabled = true;
    
    fetch('../../api/whatsapp_instance.php?action=reconnect')
    .then(res => res.json())
    .then(data => {
        if (data.success && data.state === 'open') {
            checkConnectionStatus();
        } else if (data.success && data.qr) {
            // الجلسة انتهت، يحتاج QR جديد
            waQrImage.src = data.qr;
            waQrContainer.style.display = 'block';
            waStatusText.innerHTML = '<span style="color: #f39c12;"><i class="fas fa-exclamation-circle"></i> الجلسة السابقة انتهت. امسح الكود الجديد 👆</span>';
            btnReconnect.style.display = 'none';
            btnGenerateQR.style.display = 'none';
            if (checkInterval) clearInterval(checkInterval);
            checkInterval = setInterval(checkConnectionStatus, 3000);
        } else {
            waStatusText.innerHTML = '<span style="color: #f39c12;"><i class="fas fa-info-circle"></i> ' + (data.message || 'جاري المحاولة... انتظر قليلاً ثم اضغط مرة أخرى.') + '</span>';
            // حاول الفحص بعد 5 ثواني
            setTimeout(checkConnectionStatus, 5000);
        }
    }).catch(err => {
        waStatusText.innerHTML = '<span style="color: #e74c3c;">حدث خطأ في الاتصال بالسيرفر.</span>';
    }).finally(() => {
        btnReconnect.disabled = false;
        btnGenerateQR.disabled = false;
    });
});

// زر ربط برقم جديد (QR)
btnGenerateQR.addEventListener('click', function() {
    waStatusText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري توليد كود الـ QR...';
    btnGenerateQR.disabled = true;
    
    fetch('../../api/whatsapp_instance.php?action=qr')
    .then(res => res.json())
    .then(data => {
        if (data.success && data.qr) {
            waQrImage.src = data.qr;
            waQrContainer.style.display = 'block';
            waStatusText.innerHTML = 'الرجاء مسح الكود أعلاه 👆';
            btnGenerateQR.style.display = 'none';
            btnReconnect.style.display = 'none';
            
            if (checkInterval) clearInterval(checkInterval);
            checkInterval = setInterval(checkConnectionStatus, 3000);
            
        } else if (data.success && data.state === 'open') {
            checkConnectionStatus();
        } else {
            waStatusText.innerHTML = '<span style="color: #e74c3c;">فشل توليد الكود: ' + (data.message || '') + '</span>';
            btnGenerateQR.disabled = false;
        }
    }).catch(err => {
        waStatusText.innerHTML = '<span style="color: #e74c3c;">حدث خطأ في الاتصال بالسيرفر.</span>';
        btnGenerateQR.disabled = false;
    });
});

// فحص الحالة عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    checkConnectionStatus();
});

// Custom Routes Logic
document.getElementById('btnAddRoute').addEventListener('click', function() {
    const eventType = document.getElementById('route_event_type').value;
    const phone = document.getElementById('route_phone').value;
    const desc = document.getElementById('route_desc').value;
    
    if (!eventType || !phone) {
        alert('يرجى تحديد نوع العملية ورقم الهاتف');
        return;
    }
    
    const formData = new FormData();
    formData.append('event_type', eventType);
    formData.append('phone_number', phone);
    formData.append('description', desc);
    
    fetch('../../api/whatsapp_custom_routes.php?action=add', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message);
        }
    });
});

document.querySelectorAll('.delete-route').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!confirm('هل أنت متأكد من حذف هذا التوجيه؟')) return;
        
        const id = this.getAttribute('data-id');
        const formData = new FormData();
        formData.append('id', id);
        
        fetch('../../api/whatsapp_custom_routes.php?action=delete', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('route-row-' + id).remove();
            } else {
                alert(data.message);
            }
        });
    });
});

document.querySelectorAll('.toggle-route').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const id = this.getAttribute('data-id');
        const isActive = this.checked ? 1 : 0;
        const slider = this.nextElementSibling;
        const knob = slider.nextElementSibling;
        
        const formData = new FormData();
        formData.append('id', id);
        formData.append('is_active', isActive);
        
        fetch('../../api/whatsapp_custom_routes.php?action=toggle', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                slider.style.backgroundColor = isActive ? '#2196F3' : '#ccc';
                knob.style.left = isActive ? '22px' : '2px';
            } else {
                alert(data.message);
                this.checked = !isActive; // revert
            }
        });
    });
});
</script>
<?php include '../../includes/footer.php'; ?>
