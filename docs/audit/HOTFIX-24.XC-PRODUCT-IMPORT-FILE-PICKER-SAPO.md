# HOTFIX 24.XC - Product import file picker on sapo repo

## Pham vi
- Module: Hang hoa.
- Man hinh: `/products`.
- Nghiep vu: chon file Excel/CSV trong modal Product Import va preview file.
- Rui ro: hotfix 24.XB da push vao repo `cuongdesignnb/kiot`, nhung production dang dung repo `cuongdesignnb/sapo`.

## Repo production xac minh
- Local path dang thao tac: `D:\Kiot\kiotviet-sapo`.
- Production path theo brief: `/www/wwwroot/sapo.cuongdesign.net`.
- Remote local:
  - `origin`: `https://github.com/cuongdesignnb/sapo.git`
  - `clone`: `d:\Kiot\kiotviet-clone`
- Branch: `main`.
- HEAD truoc khi sua: `5e9bbfc fix(products): add POST products/bulk-destroy route and controller handler`.
- `origin/main` sau fetch truoc khi sua: `5e9bbfc`.
- Worktree truoc khi sua co untracked `soft/`; khong dung, khong stage.

## Root cause
- Production dung repo `cuongdesignnb/sapo`, khong phai repo `cuongdesignnb/kiot`.
- Commit `56da85c fix(products): make import file picker reliable` da nam tren repo `kiot`, nhung chua co tren repo `sapo`.
- `resources/js/Components/ExcelButtons.vue` trong repo `sapo` van dung logic cu:
  - `@change="isProductExcel ? handleProductFile : handleLegacyImport"`
  - button product modal goi truc tiep `importFile.click()`
  - chua co `productImportFile`
  - chua co `openProductFilePicker`
  - `handleProductFile(e)` chua validate extension/reset input

## Source da sua
- `resources/js/Components/ExcelButtons.vue`
- `docs/audit/HOTFIX-24.XC-PRODUCT-IMPORT-FILE-PICKER-SAPO.md`

## Thay doi da lam
- Tach product input: them hidden input rieng `productImportFile` trong modal Product Import.
- Giu legacy input `importFile` cho module khac va doi onchange ve `handleLegacyImport`.
- Reset input truoc khi mo picker: `openProductFilePicker()` set `productImportFile.value.value = ""` truoc khi click.
- Hien thi ten file: doi UI sang ten file mau xanh, kem dung luong KB.
- Enable nut kiem tra: nut `Kiem tra file` co `type="button"` va disabled theo `!selectedImportFile || importLoading`.
- Reset modal state: them `defaultImportOptions()`, `resetProductImportState()`, `openImportModal()`, va reset khi dong modal.
- Preview import giu nguyen endpoint `props.importPreviewUrl || props.importUrl`.

## Backend
- Khong sua backend.
- Route import da xac minh:

```text
POST      products/import
POST      products/import-commit
POST      products/import-preview
GET|HEAD  products/import-template
```

## Data safety
- Co migration khong: Khong.
- Co backfill khong: Khong.
- Co ghi DB khong: Khong trong hotfix frontend nay.
- Co dung ton kho khong: Khong.
- Co dung gia von khong: Khong.
- Co dung serial khong: Khong.
- Co tao stock movement khong: Khong.
- Co xoa du lieu/source khong: Khong.

## Test da chay
| Lenh | Ket qua |
|---|---|
| `git fetch origin main` | Pass, `origin/main` = `5e9bbfc` truoc khi sua |
| `php artisan route:list --path=products \| Select-String -Pattern 'products/import'` | Pass, thay du route import product |
| `git diff --check` | Pass |
| `php artisan test --filter=ProductExcelImportTest` | Pass 18 tests, 51 assertions |
| `php artisan test --filter=ProductExcelExportTest` | Pass 8 tests, 24 assertions |
| `npm run build` | Pass, Vite built successfully |
| `rg -n "productImportFile\|Khong mo duoc bo chon file" public/build/assets` | Pass, asset `ExcelButtons-DolMd-ln.js` co marker `productImportFile` |

Ghi chu: cac lenh PHP co warning startup do thieu extension local `oci8_12c`, `oci8_19`, `pdo_firebird`, `pdo_oci`; test van pass.

## Manual QA
- [ ] Chon file `.xlsx` hien ten file.
- [ ] Nut `Kiem tra file` enabled.
- [ ] Preview goi `/products/import-preview`.
- [ ] Dong/mo modal reset state.
- [ ] Chon lai cung file van nhan.
- [ ] File sai dinh dang bao loi.
- [ ] Console khong loi.
- [x] `npm run build` pass.

Manual QA tren browser that va OS file picker chua chay trong phien local nay.

## Deploy note
- Commit: tao trong repo `sapo` sau report nay.
- Server HEAD sau pull: chua xac minh trong phien local nay.
- Build asset local co chua `productImportFile`: Co.
- Da clear/cache lai Laravel tren server: Chua thuc hien trong phien local nay.
- Da hard reload browser: Chua thuc hien trong phien local nay.
- Sau khi push, production can:
  - `cd /www/wwwroot/sapo.cuongdesign.net`
  - `git pull origin main`
  - `npm run build`
  - `php artisan optimize:clear`
  - cache lai config/route/view neu production dang dung cache
  - hard reload browser hoac test tab an danh

## Ket luan
- Dat/chua dat: Dat repo target `sapo`, code fix, route check, automated tests va local build.
- Co the deploy chua: Co the deploy commit len production `sapo`; chua goi la full QA pass cho den khi manual QA tren browser that xong.
- Can lam tiep: pull commit moi tren server production, build frontend, clear/cache Laravel, hard reload va test modal import tren `/products`.
