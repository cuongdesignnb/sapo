# Báo cáo AUDIT HOTFIX STEP 25C — Đơn hàng: Cọc / Công nợ / Chi nhánh

## Thông tin commit
- **Commit trước hotfix**: `67d3c7d1e713f10704d77f56a9112f729daa581b`
- **Commit sau hotfix**: (Sẽ cập nhật sau khi commit code)
- **Files changed**:
  - [OrderController.php](file:///d:/Kiot/kiotviet-sapo/app/Http/Controllers/OrderController.php)
  - [PartnerDebtLedgerService.php](file:///d:/Kiot/kiotviet-sapo/app/Services/PartnerDebtLedgerService.php)
  - [OrderPartialFulfillmentAndMergeTest.php](file:///d:/Kiot/kiotviet-sapo/tests/Feature/Orders/OrderPartialFulfillmentAndMergeTest.php)
  - [ProcessOrderViaPosTest.php](file:///d:/Kiot/kiotviet-sapo/tests/Feature/Orders/ProcessOrderViaPosTest.php)

## Chi tiết an toàn dữ liệu
- **Có migration không**: Không
- **Có backfill không**: Không
- **Có update dữ liệu cũ không**: Không
- **Có recalculate công nợ/cashflow/tồn/serial không**: Không

## Chi tiết Code Fixes
- **Đã xóa cộng `$newPayment` vào `orders.amount_paid` chưa**: Rồi, bỏ hoàn toàn logic cộng dồn trong `processOrder()`.
- **Đã sửa `debtAmount = max(0, ...)` chưa**: Rồi, đảm bảo không âm.
- **Đã chặn `recordSale()` khi debt <= 0 chưa**: Rồi, bọc điều kiện `if ($debtAmount > 0)` trước khi gọi `CustomerDebtService::recordSale`.
- **Đã thêm `branch_id` vào invoice chưa**: Rồi, thêm `'branch_id' => $order->branch_id` khi tạo Invoice.

## Kết quả Tests & Build
- **automated tests đã chạy**:
  - `php artisan test --filter=OrderPartialFulfillmentAndMergeTest` -> **PASS** (12 tests)
  - `php artisan test --filter=OrderDeposit` -> **PASS** (3 tests)
  - `php artisan test --filter=Order` -> **PASS** (95 tests)
  - `php artisan test --filter=POS` -> **PASS** (103 tests)
  - `php artisan test --filter=CustomerDebt` -> **PASS** (47 tests)
  - `php artisan test --filter=CashFlow` -> **PASS** (39 tests)
  - `php artisan test --filter=RR13OrderConvertStockTest` -> **PASS** (5 tests)
- **Kết quả `npm run build`**: Thành công (`built in 7.16s`)

## Manual QA / Kịch bản kiểm thử
1. **Kịch bản 1 — Cọc + trả thêm lần 1, xử lý tiếp lần 2**:
   - Khởi tạo order cọc 150k. Xử lý hóa đơn 200k, trả thêm 50k.
   - Lần 1: cọc áp dụng 150k. `orders.amount_paid` giữ nguyên 150k.
   - Lần 2: xử lý tiếp hóa đơn 300k, trả thêm 300k. Cọc áp dụng là 0k (không bị áp cọc ảo).
   - Kiểm chứng thành công qua test case `test_order_deposit_multiple_invoice`.
2. **Kịch bản 2 — Khách trả dư**:
   - Order cọc 150k. Xử lý hóa đơn 200k, khách trả thêm 100k (tổng 250k).
   - `debtAmount = 0`, không phát sinh nợ âm và không tạo `customer_debts` dương. Cashflow ghi nhận 100k trả thêm.
   - Kiểm chứng thành công qua test case `test_order_process_overpaid_does_not_create_positive_debt`.
3. **Kịch bản 3 — Chi nhánh**:
   - Order thuộc chi nhánh A -> Invoice tạo ra thuộc chi nhánh A.
   - Stock movement ghi nhận đúng `branch_id` của chi nhánh A.
   - Kiểm chứng thành công qua test case `test_order_process_invoice_keeps_branch_id`.

## Báo cáo dữ liệu cũ (Nếu production đã deploy commit cũ `67d3c7d1`)
Nếu production đã deploy commit cũ và phát sinh giao dịch, cần thực hiện audit dry-run:
- **Order bị cộng dồn `amount_paid`**: Tìm các order có `amount_paid` lớn hơn số tiền cọc ban đầu được ghi nhận ở phiếu đặt hàng hoặc lớn hơn `total_payment`.
- **Invoice bị áp cọc ảo**: Tìm các invoice có `order_deposit_applied_amount > 0` được tạo từ lần xử lý thứ 2 trở đi của cùng 1 order.
- **Công nợ dương từ việc trả dư**: Tìm các bản ghi `customer_debts` dạng `sale` được liên kết với hóa đơn mà số tiền khách trả lớn hơn tổng tiền hóa đơn trừ đi cọc áp dụng.
- **Chi nhánh bị null / sai**: Tìm các invoice được tạo từ order mà có `branch_id` khác với `order->branch_id` (hoặc null).

*Lưu ý: Chỉ thống kê dry-run và báo cáo, tuyệt đối không tự động cập nhật dữ liệu cũ.*

## Kết luận
- **Kết luận đạt/chưa đạt**: **ĐẠT**
- **Có thể deploy chưa**: **CÓ THỂ DEPLOY**
- **Rủi ro còn lại**: Thấp, các nghiệp vụ đặt cọc và xử lý đơn hàng đã được bao phủ chặt chẽ bởi bộ test case.
