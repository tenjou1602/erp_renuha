<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['warehouse']);

$page_title = 'Receive Purchases';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_po'])) {
    if (!canReceivePurchasedMaterials()) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: receive_purchases.php');
        exit();
    }
    $po_id = (int)($_POST['po_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? AND status IN ('confirmed', 'ready_for_warehouse')");
        $stmt->execute([$po_id]);
        $po = $stmt->fetch();
        if (!$po) {
            $_SESSION['error'] = 'Purchase order is not ready for receiving.';
        } else {
            $upd = $pdo->prepare("UPDATE purchase_orders SET status = 'delivered' WHERE id = ?");
            $upd->execute([$po_id]);
            if (!empty($po['purchase_request_id'])) {
                $pdo->prepare("UPDATE purchase_requests SET status = 'received' WHERE id = ?")->execute([$po['purchase_request_id']]);
            }
            logActivity($_SESSION['user_id'], 'Received purchased materials', 'Warehouse', $po['po_number']);
            $_SESSION['success'] = 'Marked ' . $po['po_number'] . ' as received. Record Stock-In for the delivered items.';
            header('Location: stock.php?action=in');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = userDatabaseError($e, 'Warehouse');
    }
    header('Location: receive_purchases.php');
    exit();
}

$orders = [];
try {
    $orders = $pdo->query("
        SELECT po.*, pr.pr_number, p.project_code, p.name AS project_name
        FROM purchase_orders po
        LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
        LEFT JOIN projects p ON pr.project_id = p.id
        WHERE po.status IN ('confirmed', 'ready_for_warehouse', 'delivered')
        ORDER BY FIELD(po.status, 'ready_for_warehouse', 'confirmed', 'delivered'), po.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Warehouse');
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-truck-loading"></i> Receive Purchases</h1>
    <a href="stock.php?action=in" class="btn btn-primary">Stock-In</a>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<p class="muted-note">Warehouse verifies deliveries from Procurement, marks the PO received, then records Stock-In.</p>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>PO #</th>
                    <th>PR #</th>
                    <th>Project</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="6" class="table-empty">No purchase orders ready for warehouse.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($order['po_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($order['pr_number'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars(($order['project_code'] ?? '') . ' ' . ($order['project_name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars($order['supplier']); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($order['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></td>
                        <td>
                            <?php if (in_array($order['status'], ['confirmed', 'ready_for_warehouse'], true) && canReceivePurchasedMaterials()): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="po_id" value="<?php echo (int)$order['id']; ?>">
                                <button type="submit" name="receive_po" class="btn btn-sm btn-primary">Verify &amp; receive</button>
                            </form>
                            <?php else: ?>
                            <a href="stock.php?action=in" class="btn btn-sm btn-outline">Stock-In</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
