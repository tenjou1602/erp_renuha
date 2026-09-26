<?php
/**
 * Patch module files for admin view-only gating.
 * Run once: php migrations/patch_admin_readonly.php
 */
$root = dirname(__DIR__);

function patch_file($path, callable $fn) {
    if (!is_file($path)) {
        echo "MISSING $path\n";
        return;
    }
    $before = file_get_contents($path);
    $after = $fn($before);
    if ($after === $before) {
        echo "NOCHANGE $path\n";
        return;
    }
    file_put_contents($path, $after);
    echo "UPDATED $path\n";
}

function insert_post_guard($src, $module, $keys) {
    $conds = implode(' || ', array_map(fn($k) => "isset(\$_POST['$k'])", $keys));
    $needle = "if (\$_SERVER['REQUEST_METHOD'] === 'POST') {\n    if ($conds) {";
    $needle2 = "if (\$_SERVER['REQUEST_METHOD'] === 'POST') {\r\n    if ($conds) {";
    $guard = "if (\$_SERVER['REQUEST_METHOD'] === 'POST') {\n"
        . "    if (($conds) && !canWriteDepartmentData('$module')) {\n"
        . "        \$_SESSION['error'] = 'Administrators have view-only access.';\n"
        . "        header('Location: ' . basename(__FILE__));\n"
        . "        exit();\n"
        . "    }\n"
        . "    if ($conds) {";
    if (strpos($src, "canWriteDepartmentData('$module')") !== false && strpos($src, 'Administrators have view-only access') !== false) {
        return $src;
    }
    if (strpos($src, $needle) !== false) {
        return str_replace($needle, $guard, $src);
    }
    if (strpos($src, $needle2) !== false) {
        return str_replace($needle2, str_replace("\n", "\r\n", $guard), $src);
    }
    return $src;
}

function wrap_new_button($src, $module, $label) {
    $btn = '<a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> ' . $label . '</a>';
    $wrapped = '<?php if (canWriteDepartmentData(\'' . $module . '\')): ?>' . $btn . '<?php endif; ?>';
    if (strpos($src, $wrapped) !== false) return $src;
    return str_replace($btn, $wrapped, $src);
}

function wrap_edit_buttons($src, $module) {
    // View-page Edit
    $src = preg_replace_callback(
        '/(<a href="\?action=edit&id=<\?php echo \$[a-zA-Z_]+\[\'id\'\]; \?>" class="btn btn-warning"><i class="fas fa-edit"><\/i> Edit<\/a>)/',
        function ($m) use ($module, $src) {
            if (strpos($m[0], 'canWriteDepartmentData') !== false) return $m[0];
            return '<?php if (canWriteDepartmentData(\'' . $module . '\')): ?>' . $m[1] . '<?php endif; ?>';
        },
        $src,
        1
    );
    // List sm edit
    $src = preg_replace_callback(
        '/(<a href="\?action=edit&id=<\?php echo \$[a-zA-Z_]+\[\'id\'\]; \?>" class="btn btn-sm btn-warning"[^>]*>.*?<\/a>)/',
        function ($m) use ($module) {
            if (strpos($m[0], 'canWriteDepartmentData') !== false) return $m[0];
            return '<?php if (canWriteDepartmentData(\'' . $module . '\')): ?>' . $m[1] . '<?php endif; ?>';
        },
        $src
    );
    return $src;
}

function wrap_route_block($src, $module) {
    $marker = "include '../../includes/header.php';";
    if (strpos($src, "canWriteDepartmentData('$module')") !== false && strpos($src, "Administrators have view-only access") !== false && strpos($src, "action === 'add' || \$action === 'edit'") !== false) {
        // may already have
    }
    $block = "\nif ((\$action === 'add' || \$action === 'edit') && !canWriteDepartmentData('$module')) {\n"
        . "    \$_SESSION['error'] = 'Administrators have view-only access.';\n"
        . "    header('Location: ' . basename(__FILE__) . (\$id ? '?action=view&id=' . \$id : ''));\n"
        . "    exit();\n"
        . "}\n\n";
    if (strpos($src, "(\$action === 'add' || \$action === 'edit') && !canWriteDepartmentData('$module')") !== false) {
        return $src;
    }
    return str_replace($marker, $block . $marker, $src);
}

$targets = [
    ['modules/procurement/suppliers.php', 'procurement', 'New Supplier', ['add_supplier', 'update_supplier']],
    ['modules/procurement/materials.php', 'warehouse', 'New Material', ['add_material', 'update_material']],
    ['modules/procurement/quotations.php', 'procurement', 'New Quotation', ['add_quotation', 'update_quotation']],
    ['modules/accounting/invoices.php', 'accounting', 'New Invoice', ['add_invoice', 'update_invoice']],
    ['modules/accounting/expenses.php', 'accounting', 'New Expense', ['add_expense', 'update_expense']],
    ['modules/accounting/payroll.php', 'payroll', 'New Payroll', ['add_payroll', 'update_payroll']],
];

foreach ($targets as [$rel, $module, $label, $keys]) {
    patch_file($root . '/' . $rel, function ($src) use ($module, $label, $keys) {
        $src = insert_post_guard($src, $module, $keys);
        $src = wrap_route_block($src, $module);
        $src = wrap_new_button($src, $module, $label);
        $src = wrap_edit_buttons($src, $module);
        return $src;
    });
}

// suppliers delete buttons (view + list)
patch_file($root . '/modules/procurement/suppliers.php', function ($src) {
    $src = preg_replace(
        '/(<a href="\?action=delete&delete_id=<\?php echo \$supplier_details\[\'id\'\]; \?>" class="btn btn-danger"[^>]*>.*?<\/a>)/',
        '<?php if (canDelete(\'procurement\')): ?>$1<?php endif; ?>',
        $src,
        1
    );
    $src = preg_replace(
        '/(<a href="\?action=delete&delete_id=<\?php echo \$supplier\[\'id\'\]; \?>" class="btn btn-sm btn-danger"[^>]*>.*?<\/a>)/',
        '<?php if (canDelete(\'procurement\')): ?>$1<?php endif; ?>',
        $src,
        1
    );
    return $src;
});

echo "Done.\n";
