<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

if (!isLoggedIn()) {
    redirect('views/auth/login.php');
}

// التحقق من أن المستخدم مشيك
if ($_SESSION['role'] != 'driver') {
    redirect('views/dashboard/dashboard.php');
}

// تحديث حالة التحويل إلى تم الاستلام
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $transfer_id = clean_input($_POST['transfer_id']);
    $new_status = clean_input($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE transfers SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $transfer_id])) {
        $success = 'تم تأكيد الاستلام بنجاح';
        
        // إعادة السائق ليكون متاحاً
        if ($new_status == 'delivered') {
            try {
                // جلب معرف السائق المعيّن لهذا التحويل
                $stmt_da = $conn->prepare("SELECT driver_id FROM driver_assignments WHERE transfer_id = ?");
                $stmt_da->execute([$transfer_id]);
                $assignment = $stmt_da->fetch();
                
                if ($assignment) {
                    $stmt_avail = $conn->prepare("UPDATE drivers SET is_available = 1 WHERE id = ?");
                    $stmt_avail->execute([$assignment['driver_id']]);
                }
                
                // إرسال إشعار واتساب
                $stmt_t = $conn->prepare("SELECT transfer_number, from_location, to_location FROM transfers WHERE id = ?");
                $stmt_t->execute([$transfer_id]);
                $transfer_info = $stmt_t->fetch();
                
                if ($transfer_info) {
                    require_once '../../api/whatsapp.php';
                    $msg = "🌟 *إشعار نظام اللوجستيات* 🌟\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
                    $msg .= "✅ *تم استلام التحويل بنجاح*\n\n";
                    $msg .= "🔖 *رقم التحويل:* `{$transfer_info['transfer_number']}`\n";
                    $msg .= "🏢 *الفرع المرسل:* {$transfer_info['from_location']}\n";
                    $msg .= "📍 *الفرع المستلم:* {$transfer_info['to_location']}\n";
                    $msg .= "👤 *تأكيد بواسطة:* {$_SESSION['full_name']}\n\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "شكراً لجهودكم! 🙏\n";
                    
                    $branch_group_id = null;
                    if (!empty($transfer_info['to_location'])) {
                        $stmt_b = $conn->prepare("SELECT whatsapp_group_id FROM branches WHERE name = ? LIMIT 1");
                        $stmt_b->execute([trim($transfer_info['to_location'])]);
                        $branch_group_id = $stmt_b->fetchColumn();
                    }
                    
                    $stmt_w = $conn->query("SELECT warehouse_manager_group_id FROM whatsapp_settings LIMIT 1");
                    $w_settings = $stmt_w->fetch();
                    $main_group_id = $w_settings ? $w_settings['warehouse_manager_group_id'] : null;

                    // إرسال للجروب المخصص للفرع إن وجد
                    if (!empty($branch_group_id)) {
                        sendWhatsAppMessage($branch_group_id, $msg);
                    }
                    
                    // إرسال نسخة للجروب الرئيسي (أو كبديل إن لم يوجد جروب مخصص)
                    if (!empty($main_group_id) && $main_group_id !== $branch_group_id) {
                        sendWhatsAppMessage($main_group_id, $msg);
                    }
                    notifyCustomRoutes('transfer_updated', $msg);
                }
            } catch(PDOException $e) {
                // تجاهل أخطاء الإرسال
            }
        }
    } else {
        $error = 'حدث خطأ أثناء التحديث';
    }
}

// الحصول على اسم فرع المشيك
$checker_branch_name = '';
if (!empty($_SESSION['branch_id'])) {
    $stmt_b = $conn->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt_b->execute([$_SESSION['branch_id']]);
    $checker_branch_name = $stmt_b->fetchColumn();
}

// الحصول على جميع التحويلات التي حالتها "جاري التوصيل" ولديها سائق معيّن وتخص فرع المشيك كوجهة
$query = "
    SELECT t.*, b.name as branch_name, u.full_name as uploader_name,
           da.pickup_date, da.delivery_date, da.notes, da.assigned_at,
           d.name as driver_name, d.phone as driver_phone
    FROM transfers t 
    LEFT JOIN branches b ON t.branch_id = b.id 
    LEFT JOIN users u ON t.uploaded_by = u.id 
    INNER JOIN driver_assignments da ON t.id = da.transfer_id
    LEFT JOIN drivers d ON da.driver_id = d.id
    WHERE t.status IN ('in_transit', 'delivered')
";

$params = [];
if (!canManageAllBranches() && $checker_branch_name) {
    $query .= " AND t.to_location = ?";
    $params[] = $checker_branch_name;
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$transfers = $stmt->fetchAll();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1>التحويلات الجارية</h1>
    <div>
        <span class="status-badge status-in_transit">المشيك: <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- إحصائيات سريعة -->
<div class="dashboard-stats">
    <div class="stat-card info">
        <div class="stat-icon">🚚</div>
        <div class="stat-info">
            <h3><?php echo count($transfers); ?></h3>
            <p>تحويلات جاري توصيلها</p>
        </div>
    </div>
</div>

<div class="content-section">
    <div class="section-header">
        <h2>التحويلات قيد التوصيل</h2>
    </div>
    
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>رقم التحويل</th>
                    <th>من</th>
                    <th>إلى</th>
                    <th>السائق</th>
                    <th>الحالة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($transfers) > 0): ?>
                    <?php foreach ($transfers as $transfer): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($transfer['transfer_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($transfer['from_location']); ?></td>
                        <td><?php echo htmlspecialchars($transfer['to_location']); ?></td>
                        <td><?php echo htmlspecialchars($transfer['driver_name'] ?? '-'); ?></td>
                        <td>
                            <?php if ($transfer['status'] == 'delivered'): ?>
                                <span class="status-badge status-delivered">تم الاستلام مسبقاً</span>
                            <?php else: ?>
                                <span class="status-badge status-in_transit">جاري التوصيل</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <button onclick="showTransferDetails(<?php echo $transfer['id']; ?>)" class="btn btn-sm btn-info">التفاصيل</button>
                            <?php if ($transfer['status'] == 'in_transit'): ?>
                                <form method="POST" action="" style="display:inline;">
                                    <input type="hidden" name="transfer_id" value="<?php echo $transfer['id']; ?>">
                                    <input type="hidden" name="status" value="delivered">
                                    <button type="submit" name="update_status" class="btn btn-sm btn-success">تأكيد الاستلام</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            <p class="text-muted">لا توجد تحويلات جاري توصيلها حالياً</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal تفاصيل التحويل -->
<div id="detailsModal" class="modal">
    <div class="modal-content modal-edit" style="max-width: 650px;">
        <div class="modal-header modal-header-edit">
            <div class="modal-header-content">
                <div class="modal-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <h3>تفاصيل التحويل</h3>
            </div>
            <button class="modal-close" onclick="closeModal('detailsModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="transferDetails">
                <!-- سيتم ملؤها بواسطة JavaScript -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('detailsModal')">
                <i class="fas fa-times"></i> إغلاق
            </button>
        </div>
    </div>
</div>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// إغلاق عند النقر خارج المودال
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}

function showTransferDetails(transferId) {
    // جلب التفاصيل عبر PHP
    const transfers = <?php echo json_encode($transfers); ?>;
    const transfer = transfers.find(t => t.id == transferId);
    
    if (transfer) {
        const statuses = {
            'pending': 'قيد الانتظار',
            'assigned': 'تم التعيين',
            'in_transit': 'قيد التوصيل',
            'delivered': 'تم الاستلام',
            'cancelled': 'ملغي'
        };
        
        const html = `
            <div class="info-row">
                <div class="label">رقم التحويل:</div>
                <div class="value"><strong>${transfer.transfer_number}</strong></div>
            </div>
            <div class="info-row">
                <div class="label">من الموقع:</div>
                <div class="value">${transfer.from_location}</div>
            </div>
            <div class="info-row">
                <div class="label">إلى الموقع:</div>
                <div class="value">${transfer.to_location}</div>
            </div>
            <div class="info-row">
                <div class="label">الحالة:</div>
                <div class="value"><span class="status-badge status-${transfer.status}">${statuses[transfer.status]}</span></div>
            </div>
            <div class="info-row">
                <div class="label">تاريخ الاستلام:</div>
                <div class="value">${transfer.pickup_date || 'لم يحدد'}</div>
            </div>
            <div class="info-row">
                <div class="label">تاريخ التوصيل:</div>
                <div class="value">${transfer.delivery_date || 'لم يحدد'}</div>
            </div>
            <div class="info-row">
                <div class="label">الوصف:</div>
                <div class="value">${transfer.description || 'لا يوجد'}</div>
            </div>
            <div class="info-row">
                <div class="label">ملاحظات التعيين:</div>
                <div class="value">${transfer.notes || 'لا يوجد'}</div>
            </div>
            ${transfer.file_path ? `
            <div class="info-row">
                <div class="label">المرفق:</div>
                <div class="value"><a href="<?php echo SITE_URL; ?>/uploads/${transfer.file_path}" target="_blank" class="btn btn-sm btn-info">عرض الملف</a></div>
            </div>
            ` : ''}
        `;
        
        document.getElementById('transferDetails').innerHTML = html;
        openModal('detailsModal');
    }
}

// إغلاق المودال عند الضغط خارجه
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
