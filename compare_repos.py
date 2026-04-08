import os
import hashlib

src = r"D:\Kiot\kiotviet-clone.worktrees\copilot-worktree-2026-04-07T09-54-25"
tgt = r"D:\Kiot\kiotviet-sapo"

def file_hash(path):
    h = hashlib.md5()
    with open(path, 'rb') as f:
        h.update(f.read())
    return h.hexdigest()

missing = []
different = []
same = 0

dirs = ["app","config","database\\migrations","database\\seeders","database\\factories","lang","public","resources\\views","resources\\js","resources\\css","routes","tests"]

for d in dirs:
    src_dir = os.path.join(src, d)
    if not os.path.isdir(src_dir):
        print(f"[INFO] Directory not found in source: {d}")
        continue
    for root, _, files in os.walk(src_dir):
        for fname in files:
            full_src = os.path.join(root, fname)
            rel = os.path.relpath(full_src, src)
            full_tgt = os.path.join(tgt, rel)
            if not os.path.exists(full_tgt):
                missing.append(rel)
            else:
                if file_hash(full_src) != file_hash(full_tgt):
                    different.append(rel)
                else:
                    same += 1

root_files = ["composer.json","package.json","vite.config.js","artisan","phpunit.xml","add_col.php","check_defaults.php","check_settings.php","check_tables.php","khlanhacungcap.md","remove_links.php","repair_settings.php","run_migration.php","seed_extra_settings.php","seed_order_settings.php","test_migrate.php","test_schema.php","update_cost.php"]

for rf in root_files:
    full_src = os.path.join(src, rf)
    full_tgt = os.path.join(tgt, rf)
    if not os.path.exists(full_src):
        continue
    if not os.path.exists(full_tgt):
        missing.append(rf)
    else:
        if file_hash(full_src) != file_hash(full_tgt):
            different.append(rf)
        else:
            same += 1

print(f"=== MISSING IN TARGET ({len(missing)} files) ===")
for f in sorted(missing):
    print(f"  {f}")

print(f"\n=== DIFFERENT CONTENT ({len(different)} files) ===")
for f in sorted(different):
    print(f"  {f}")

print(f"\n=== IDENTICAL FILES: {same} ===")
