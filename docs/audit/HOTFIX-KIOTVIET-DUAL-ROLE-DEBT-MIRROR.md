# HOTFIX — KiotViet dual-role customer/supplier debt mirror

## Phạm vi
- **Module**: Financial, Partner, Debt (Công nợ & Lịch sử đối tác)
- **Màn hình**: 
  - Khách hàng (tab Công nợ và list Nợ hiện tại)
  - Nhà cung cấp (tab Công nợ và list Nợ cần trả hiện tại)
- **Nghiệp vụ**: Công nợ khách hàng kiêm nhà cung cấp (dual-role partner).
- **Rủi ro**: 
  - Thấp. Tất cả tính toán và đối chiếu công nợ được thực hiện trên lớp đọc (Read-only API), không ghi xuống database hoặc tự động cấn trừ ảo trong GET request.

## Source đã kiểm tra
- **Route**:
  - `GET /customers/{customer}/debt-history`
  - `GET /api/suppliers/{id}/debt-transactions`
- **Controller**:
  - [CustomerController](file:///d:/Kiot/kiotviet-clone/app/Http/Controllers/CustomerController.php)
  - [SupplierController](file:///d:/Kiot/kiotviet-clone/app/Http/Controllers/SupplierController.php)
- **Service**:
  - [PartnerDebtLedgerService](file:///d:/Kiot/kiotviet-clone/app/Services/PartnerDebtLedgerService.php) (Tách biệt logic tính toán ledger)
- **Model**:
  - [Customer](file:///d:/Kiot/kiotviet-clone/app/Models/Customer.php)
  - [CustomerDebt](file:///d:/Kiot/kiotviet-clone/app/Models/CustomerDebt.php)
  - [SupplierDebtTransaction](file:///d:/Kiot/kiotviet-clone/app/Models/SupplierDebtTransaction.php)
  - [CashFlow](file:///d:/Kiot/kiotviet-clone/app/Models/CashFlow.php)
  - [DebtOffset](file:///d:/Kiot/kiotviet-clone/app/Models/DebtOffset.php)
- **Frontend**:
  - [Customers/Index.vue](file:///d:/Kiot/kiotviet-clone/resources/js/Pages/Customers/Index.vue)
  - [Suppliers/Index.vue](file:///d:/Kiot/kiotviet-clone/resources/js/Pages/Suppliers/Index.vue)
- **Test**:
  - [DualRolePartnerDebtTimelineTest](file:///d:/Kiot/kiotviet-clone/tests/Feature/Customers/DualRolePartnerDebtTimelineTest.php)
  - [SupplierPayableLedgerTest](file:///d:/Kiot/kiotviet-clone/tests/Feature/Suppliers/SupplierPayableLedgerTest.php)
- **Commit**:
  - `7fb035c fix(debt): align customer and supplier debt tabs with Kiot standard`

## KiotViet expected behavior từ ảnh
- Đối tác vừa là khách hàng vừa là nhà cung cấp:
  - Cột chính ở màn NCC: `Nợ cần trả hiện tại` = `supplier_debt_amount`. Tab `Công nợ` chỉ hiển thị các giao dịch NCC thuần (Nhập hàng, Thanh toán, Điều chỉnh).
  - Cột chính ở màn Khách hàng: `Nợ hiện tại` (dạng net) = `debt_amount - supplier_debt_amount`. Tab `Công nợ` hiển thị gộp cả giao dịch khách hàng và các giao dịch NCC mirror với dấu đảo ngược (`customer_effect = -1 * supplier_effect`), tính running balance ròng.
  - Không tự động cấn trừ chỉ vì cả hai bên có nợ. Chỉ khi có chứng từ cấn bằng (`CB...` / `HCB...`) thì mới hiện dòng cấn bằng thật.

## Hiện trạng source trước sửa
- Source trước đó trộn cả hóa đơn bán hàng/customer debt vào màn NCC hoặc không đảo dấu đúng cách, tính running balance không chính xác theo góc nhìn khách hàng kiêm NCC, gây lệch số hoặc làm mất dấu âm/dương thực tế.

## Root cause
- Thiếu lớp Ledger Builder tập trung xử lý cho hai góc nhìn (Khách hàng Net Ledger và Nhà cung cấp Payable Ledger).

## Thay đổi đã làm
1. Phát triển [PartnerDebtLedgerService](file:///d:/Kiot/kiotviet-clone/app/Services/PartnerDebtLedgerService.php) làm Single Source of Truth cho việc tính toán ledger.
2. Chuẩn hóa [SupplierController](file:///d:/Kiot/kiotviet-clone/app/Http/Controllers/SupplierController.php) và [CustomerController](file:///d:/Kiot/kiotviet-clone/app/Http/Controllers/CustomerController.php) để gọi service mới.
3. Cập nhật frontend để hiển thị đúng cột nợ, giá trị, mã phiếu và running balance.
4. Bổ sung bộ tests tự động bao quát đầy đủ các Case nghiệp vụ và Legacy Fallback.

## Có ảnh hưởng dữ liệu đang có không?
- **Không**. Thay đổi chỉ sửa cách build/read timeline trên API và hiển thị ở frontend. Không thực hiện di chuyển hay cập nhật dữ liệu cũ trên Database.

## Data safety
- **Migration**: Không có (sử dụng schema hiện có).
- **Backfill**: Không.
- **Update dữ liệu cũ**: Không.
- **Delete**: Không.
- **Rollback**: Revert các file code đã chỉnh sửa và build lại frontend.

## Tests đã chạy
1. `tests/Feature/Customers/DualRolePartnerDebtTimelineTest.php` -> **PASS**
2. `tests/Feature/Suppliers/SupplierPayableLedgerTest.php` -> **PASS**
3. `tests/Feature/CustomerDebt/*` -> **PASS**
4. `tests/Feature/Supplier/*` -> **PASS**
5. `tests/Feature/OrderReturn/*` -> **PASS**
6. `tests/Feature/Purchase/*` -> **PASS**

## Manual QA
- **Màn Nhà cung cấp**:
  1. Chọn đối tác kiêm NCC.
  2. Cột `Nợ cần trả hiện tại` hiển thị `supplier_debt_amount`.
  3. Mở tab `Công nợ` chỉ thấy các giao dịch Nhập hàng (+), Thanh toán (-).
- **Màn Khách hàng**:
  1. Chọn đối tác kiêm NCC đó.
  2. Cột `Nợ hiện tại` hiển thị `debt_amount - supplier_debt_amount` (có thể âm).
  3. Mở tab `Công nợ` thấy gộp cả các giao dịch NCC mirror với dấu đảo ngược, running balance phản ánh đúng số công nợ ròng (net balance).

## Rủi ro còn lại
- Không có.

## Kết luận
- **Đạt**: Đã khớp 100% các tiêu chí từ ảnh chụp thật KiotViet.
- **Có thể deploy chưa**: Có.
- **Cần làm tiếp**: Tiến hành commit, push code và triển khai deploy lên môi trường Production.
