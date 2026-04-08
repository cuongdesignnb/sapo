<?php
/**
 * Run this script with: php create_purchase_returns_dir.php
 * It creates the PurchaseReturns directory and copies all 3 Vue files
 * from the source worktree to the kiotviet-sapo target.
 */

$srcBase = 'D:\\Kiot\\kiotviet-clone.worktrees\\copilot-worktree-2026-04-07T09-54-25\\resources\\js\\Pages\\PurchaseReturns\\';
$dstBase = 'D:\\Kiot\\kiotviet-sapo\\resources\\js\\Pages\\PurchaseReturns\\';

if (!is_dir($dstBase)) {
    mkdir($dstBase, 0777, true);
    echo "Created directory: $dstBase\n";
} else {
    echo "Directory already exists: $dstBase\n";
}

$files = ['Index.vue', 'Create.vue', 'Show.vue'];
foreach ($files as $file) {
    $src = $srcBase . $file;
    $dst = $dstBase . $file;
    if (!file_exists($src)) {
        echo "ERROR: Source not found: $src\n";
        continue;
    }
    if (copy($src, $dst)) {
        echo "Copied: $file\n";
    } else {
        echo "ERROR copying: $file\n";
    }
}
echo "Done.\n";
