# HOTFIX 24.XD - Sync Product import file picker

## Pham vi
- Repo: `cuongdesignnb/sapo`.
- Local path: `D:\Kiot\kiotviet-sapo`.
- Production path: `/www/wwwroot/sapo.cuongdesign.net`.
- Module: Hang hoa.
- Man hinh: `/products`.
- Nghiep vu: dong bo Product Import file picker voi repo audit `kiot` va repo production `sapo`.
- Rui ro: server production pull tu `sapo`, nen chi push vao `kiot` se khong sua duoc loi tren production.

## Source truoc khi sua
- HEAD: `4fec446 fix(products): make import file picker reliable on sapo`.
- Remote: `origin https://github.com/cuongdesignnb/sapo.git`.
- Branch: `main`.
- File picker co fix chua: Co.
- Marker da co:
  - `productImportFile`
  - `openProductFilePicker`
  - `handleProductFile(event)`
- Logic cu `isProductExcel ? handleProductFile : handleLegacyImport`: Khong con.

## Thay doi da lam
- `resources/js/Components/ExcelButtons.vue`: khong sua trong 24.XD vi repo da co fix tu commit `4fec446`.
- Report audit: them file nay de ghi nhan sync hai repo va lenh deploy production.

## Data safety
- Co migration khong: Khong.
- Co backfill khong: Khong.
- Co ghi DB khong: Khong.
- Co dung ton kho khong: Khong.
- Co dung gia von khong: Khong.
- Co dung serial khong: Khong.
- Co tao stock movement khong: Khong.

## Tests da chay
| Lenh | Ket qua |
|---|---|
| `php artisan route:list --path=products \| Select-String -Pattern 'products/import'` | Pass, co du 4 route import product |
| `php artisan test --filter=ProductExcelImportTest` | Pass 18 tests, 51 assertions |
| `php artisan test --filter=ProductExcelExportTest` | Pass 8 tests, 24 assertions |
| `npm run build` | Pass, Vite built successfully |
| `rg -n "productImportFile\|Khong mo duoc bo chon file" public/build/assets` | Pass, build asset co marker `productImportFile` |

Ghi chu: cac lenh PHP co warning startup do thieu extension local `oci8_12c`, `oci8_19`, `pdo_firebird`, `pdo_oci`; test van pass.

## Manual QA
- [ ] Chon file hien ten file.
- [ ] Nut kiem tra enabled.
- [ ] Preview goi import-preview.
- [ ] Dong/mo modal reset state.
- [ ] Chon lai cung file van nhan.
- [ ] File sai dinh dang bao loi.
- [ ] Console khong loi.

Manual QA browser that chua chay trong phien local nay.

## Deploy production sapo
Chay tren server production:

```bash
cd /www/wwwroot/sapo.cuongdesign.net

git status
git pull origin main
git rev-parse --short HEAD

grep -n "productImportFile" resources/js/Components/ExcelButtons.vue
grep -n "openProductFilePicker" resources/js/Components/ExcelButtons.vue
grep -n "handleProductFile(event)" resources/js/Components/ExcelButtons.vue

rm -rf public/build
npm run build

grep -R "productImportFile" -n public/build/assets | head
grep -R "Không mở được bộ chọn file" -n public/build/assets | head

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Sau do hard reload browser bang `Ctrl + Shift + R` hoac test bang tab an danh.

## Commit
- SHA fix frontend hien co: `4fec446`.
- SHA report 24.XD: se cap nhat sau commit.

## Ket luan
- Dat/chua dat: Dat sync logic trong repo `sapo`, automated tests va build local.
- Co the deploy chua: Co the deploy production sau khi pull commit moi, build lai frontend va clear/cache.
- Can lam tiep: chay manual QA tren production/staging sau deploy.
