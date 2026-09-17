<?php
/**
 * Low-stock helpers for warehouse dashboards and alerts.
 */
if (!function_exists('getLowStockMaterials')) {
    function getLowStockMaterials($limit = null) {
        global $pdo;
        try {
            $sql = "SELECT id, material_code, name, unit, current_stock, min_stock
                    FROM materials
                    WHERE status = 'active'
                      AND min_stock > 0
                      AND current_stock <= min_stock
                    ORDER BY (current_stock / NULLIF(min_stock, 0)) ASC, current_stock ASC, name ASC";
            if ($limit !== null) {
                $sql .= ' LIMIT ' . (int) $limit;
            }
            return $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    function countLowStockMaterials() {
        global $pdo;
        try {
            return (int) $pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'active' AND min_stock > 0 AND current_stock <= min_stock")->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    function renderLowStockBanner($link_base = null) {
        if (!function_exists('canViewModule') || !canViewModule('warehouse')) {
            return;
        }

        $total = countLowStockMaterials();
        if ($total <= 0) {
            return;
        }

        $items = getLowStockMaterials(5);
        $parts = [];
        foreach ($items as $item) {
            $label = htmlspecialchars($item['name'])
                . ' (' . (int) $item['current_stock']
                . ' / min ' . (int) $item['min_stock']
                . ')';
            $href = ($link_base ?: (defined('APP_URL') ? APP_URL . 'modules/warehouse/inventory.php' : 'inventory.php'))
                . '?highlight=' . (int) $item['id'];
            $parts[] = '<a href="' . htmlspecialchars($href) . '">' . $label . '</a>';
        }

        $more = $total > 5 ? ' and ' . ($total - 5) . ' more' : '';
        echo '<div class="alert alert-warning low-stock-banner">'
            . '<i class="fas fa-exclamation-triangle"></i> '
            . '<strong>' . (int) $total . ' material' . ($total === 1 ? '' : 's') . '</strong> '
            . 'at or below minimum stock: '
            . implode(', ', $parts)
            . htmlspecialchars($more)
            . '</div>';
    }
}
