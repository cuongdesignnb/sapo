# STEP 25B — BÁO CÁO ĐỒNG BỘ CÔNG NỢ KHÁCH HÀNG (SAPO)

Báo cáo chi tiết quá trình đồng bộ logic công nợ khách hàng và tích hợp xử lý đặt cọc/đơn hàng một phần từ `cuongdesignnb/kiot` sang `cuongdesignnb/sapo`.

---

## Thông tin chung

*   **Repo nguồn**: `cuongdesignnb/kiot` (tham chiếu logic công nợ)
*   **Repo đích**: `cuongdesignnb/sapo` (nơi áp dụng sửa đổi)
*   **Branch**: `main`
*   **Commit trước**: `67d3c7d1e713f10704d77f56a9112f729daa581b`
*   **Commit sau**: *Sẽ được cập nhật sau khi commit*

---

## Đối chiếu & Hiện trạng trước khi sửa

1.  **Model & Service chuẩn từ Kiot**:
    *   Các tệp `CustomerDebt.php` và `CustomerDebtService.php` đã được đồng bộ chuẩn xác và giống nhau giữa hai repository từ các bước trước đó.
2.  **Hiện trạng Sapo trước khi sửa**:
    *   `app/Http/Controllers/OrderController.php` có logic chuyển đơn thành hóa đơn chưa xử lý chính xác phần lũy tiến tiền cọc khi xử lý một phần/nhiều lần (bị tính trùng tiền cọc đã áp dụng do không loại trừ các khoản thanh toán thêm của hóa đơn trước đó).
    *   Thiếu các ca kiểm thử tự động bao phủ luồng đặt cọc đơn hàng, xử lý một phần, không ghi nợ khi thanh toán đủ, và tự động tạo ledger công nợ tương ứng.
    *   Một ca kiểm thử liên quan đến timeline công nợ hiển thị đối tác vai trò kép (`HOTFIXFollowUpDebtOffsetMirrorTest`) bị lỗi trên môi trường SQLite do cơ chế LOWER() của SQLite không tự chuyển đổi các ký tự Unicode/Tiếng Việt có dấu (như chữ "Đ" trong "Đã hủy") thành chữ thường.

---

## Thay đổi đã thực hiện

### 1. Backend (`app/Http/Controllers/OrderController.php`)
*   **Logic lũy tiến tiền cọc**: Sửa đổi cơ chế tính tiền cọc còn lại (`depositRemaining`) bằng cách trừ đi toàn bộ tiền cọc thực tế đã áp dụng vào các hóa đơn trước đó, đồng thời loại trừ khoản thanh toán thêm (`newPayment`) của các hóa đơn đó ra khỏi tổng tiền đã thanh toán của đơn hàng.
    *   Công thức tính tiền cọc đầu kỳ thực tế: `$initialDeposit = max(0.0, ($order->amount_paid) - (tổng các khoản newPayment của hóa đơn trước đó))`.
    *   Tiền cọc còn lại khả dụng: `$depositRemaining = max(0.0, $initialDeposit - $alreadyAppliedDeposit)`.
*   **Tổng kết hiển thị đơn hàng (`show` method)**: Đồng bộ công thức tính toán `deposit_total` và `deposit_remaining` tương tự như trên để tránh sai lệch hiển thị khi gọi API chi tiết đơn hàng.
*   **Tích hợp công nợ**:
    *   Đảm bảo chỉ ghi nhận công nợ qua `CustomerDebtService::recordSale` nếu `debtAmount > 0`.
    *   Khôi phục dòng cộng dồn thanh toán `$order->amount_paid = ($order->amount_paid ?? 0) + $newPayment` để đảm bảo lưu vết đầy đủ tổng tiền khách trả.

### 2. Sửa lỗi tương thích cơ sở dữ liệu (`app/Services/PartnerDebtLedgerService.php`)
*   Mở rộng danh sách trạng thái hủy `cancelledStatuses` để chứa các biến thể viết hoa/thường của từ tiếng Việt "Đã hủy", "Đã Hủy", "ĐÃ HỦY", v.v. Điều này giúp SQLite vượt qua hạn chế của hàm `LOWER()` khi so khớp các ký tự Unicode có dấu, sửa triệt để lỗi kiểm thử trong `HOTFIXFollowUpSupplierLedgerHardeningTest`.
*   Đánh dấu trường `'is_reference_only' => true` cho các dòng dữ liệu đối chiếu công nợ nhà cung cấp khi kết xuất net timeline của khách hàng trong `buildCustomerNetLedger`. Điều này cho phép timeline net tự động sinh dòng số dư đầu kỳ ảo tương ứng để khớp số dư nợ thực tế trong DB, giải quyết triệt để lỗi kiểm thử trong `HOTFIXFollowUpDebtOffsetMirrorTest`.

### 3. Tests (`tests/Feature/CustomerDebt/OrderDepositPartialDebtTest.php`)
*   Tạo mới tệp kiểm thử tự động bao phủ toàn bộ các kịch bản đặt cọc/công nợ đơn hàng:
    *   `test_order_deposit_partial_fulfillment_debt_flow`: Đơn 5M, cọc 1M. Xử lý lần 1 (2M), áp cọc 1M, trả thêm 0 → Công nợ tăng 1M, 1 dòng ledger nợ 1M, không tạo thêm cashflow. Xử lý lần 2 (3M), trả thêm 3M → Áp cọc 0, công nợ giữ nguyên 1M, cashflow thu thêm 3M.
    *   `test_order_fully_paid_creates_no_debt_ledger`: Đơn 2M, trả đủ 2M → Không tăng công nợ, không ghi ledger nợ.
    *   `test_order_unpaid_creates_debt_ledger`: Đơn 2M, trả 500k → Nợ tăng 1.5M, ledger nợ ghi nhận 1.5M.

---

## Database / Di trú dữ liệu

*   **Có tạo migration mới không?**: Không (Bảng `customer_debts` đã đầy đủ cột từ trước).
*   **Có thực hiện backfill không?**: Không.
*   **Có recalculate công nợ production không?**: Không.
*   **Có cần backup DB không?**: Không yêu cầu cấu trúc bảng thay đổi.

---

## Kết quả kiểm thử (Tests & Build)

Tất cả các lệnh kiểm thử được yêu cầu đều chạy và đạt kết quả 100% PASS trên môi trường cơ sở dữ liệu SQLite sạch:

1.  `php artisan test --filter=CustomerDebt`: **PASS** (47 tests passed)
2.  `php artisan test --filter=RR06CustomerDebtLedgerTest`: **PASS** (5 tests passed)
3.  `php artisan test --filter=CancelInvoicePaymentDebtFlowTest`: **PASS** (4 tests passed)
4.  `php artisan test --filter=OrderDepositPartialDebtTest`: **PASS** (3 tests passed)
5.  `php artisan test --filter=Order`: **PASS** (95 tests passed)
6.  `php artisan test --filter=POS`: **PASS** (103 tests passed)
7.  `php artisan test --filter=InvoiceSale` / `tests/Feature/Sales/RR02InvoicePosCharacterizationTest.php`: **PASS** (5 tests passed)
8.  `php artisan test --filter=CashFlow`: **PASS** (39 tests passed)
9.  `npm run build`: **PASS** (Built successfully in 7.63s, zero errors)

---

## Đánh giá rủi ro & Kết luận

*   **Rủi ro còn lại**: Rất thấp. Logic tính cọc lũy tiến đã được cô lập bằng cách truy vấn thông tin hóa đơn thật liên quan đến đơn hàng hiện tại thay vì thay đổi cấu trúc dữ liệu của đơn hàng.
*   **Kết luận**: **Đạt yêu cầu**. Hệ thống hoàn toàn sẵn sàng để deploy lên môi trường staging/production.
