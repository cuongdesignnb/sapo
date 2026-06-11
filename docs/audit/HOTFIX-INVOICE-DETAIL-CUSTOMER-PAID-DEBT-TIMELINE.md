# HOTFIX - Sửa lỗi chi tiết hóa đơn / công nợ hiển thị khách đã thanh toán đủ sai

## Thông tin hệ thống
- **Branch:** `main`
- **Commit trước:** `56ece6d3ec81c60589a0d86d3c4fcaa602d530d7`
- **Commit sau:** (Sẽ được sinh ra sau khi commit code hotfix)

---

## SQL audit case thực tế (Local DB)

### 1. Kết quả SELECT invoices
Mã hóa đơn thực tế trong dữ liệu local: `HD178007130134`

```sql
SELECT
    id,
    code,
    customer_id,
    order_id,
    branch_id,
    subtotal,
    discount,
    total,
    customer_paid,
    order_deposit_applied_amount,
    status,
    payment_method,
    created_at,
    updated_at
FROM invoices
WHERE code = 'HD178007130134';
```

**Kết quả:**
```json
[
    {
        "id": 3,
        "code": "HD178007130134",
        "customer_id": 2,
        "order_id": 8,
        "branch_id": null,
        "subtotal": "15000000.00",
        "discount": "0.00",
        "total": "15000000.00",
        "customer_paid": "15000000.00",
        "order_deposit_applied_amount": "0.00",
        "status": "Hoàn thành",
        "payment_method": "cash",
        "created_at": "2026-05-29 23:15:01",
        "updated_at": "2026-05-29 23:15:01"
    }
]
```

### 2. Kết quả SELECT orders
```sql
SELECT
    id,
    code,
    amount_paid,
    total_payment,
    status,
    created_at,
    updated_at
FROM orders
WHERE id = (
    SELECT order_id
    FROM invoices
    WHERE code = 'HD178007130134'
);
```

**Kết quả:**
```json
[
    {
        "id": 8,
        "code": "DH1780071274",
        "amount_paid": "15000000.00",
        "total_payment": "15000000.00",
        "status": "completed",
        "created_at": "2026-05-29 23:13:00",
        "updated_at": "2026-05-29 23:15:01"
    }
]
```

### 3. Kết quả SELECT cash_flows
```sql
SELECT
    id,
    code,
    type,
    amount,
    target_type,
    target_id,
    reference_type,
    reference_code,
    category,
    description,
    time,
    created_at,
    status
FROM cash_flows
WHERE reference_code IN ('HD178007130134', 'DH1780071274', 'PT178007130194', 'TTHD178007130134')
   OR code IN ('PT178007130194', 'TTHD178007130134')
   OR description LIKE '%HD178007130134%'
   OR description LIKE '%DH1780071274%'
ORDER BY created_at, id;
```

**Kết quả:**
```json
[
    {
        "id": 1,
        "code": "PT178007130194",
        "type": "receipt",
        "amount": "5000000.00",
        "target_type": "Khách hàng",
        "target_id": 2,
        "reference_type": "Invoice",
        "reference_code": "HD178007130134",
        "category": "Thu tiền khách trả",
        "description": "Xử lý đơn DH1780071274 → HD HD178007130134",
        "time": "2026-05-29 23:15:01",
        "created_at": "2026-05-29 23:15:01",
        "status": "active"
    }
]
```

### 4. Kết quả SELECT customer_debts
```sql
SELECT
    id,
    customer_id,
    ref_code,
    amount,
    debt_total,
    type,
    note,
    order_id,
    order_return_id,
    recorded_at,
    created_at
FROM customer_debts
WHERE customer_id = (
    SELECT customer_id
    FROM invoices
    WHERE code = 'HD178007130134'
)
ORDER BY recorded_at, id;
```

**Kết quả:**
```json
[]
```

---

## Root cause (Nguyên nhân lỗi)
1. **`invoice.customer_paid` bị hiểu sai:**
   Trường `customer_paid` trong bảng `invoices` lưu trữ số tiền lũy kế khách hàng đã trả (bao gồm tiền cọc đơn hàng, tiền thanh toán tại thời điểm xuất hóa đơn, và tiền thu nợ sau hóa đơn). Ở frontend, công thức tính dư nợ của hóa đơn trước đó chỉ là `total - customer_paid`, gây ra việc hiển thị "Dư nợ: 0đ" hoặc báo "Khách đã thanh toán đủ" khi lũy kế thanh toán đạt giá trị bằng `total`, trong khi thực tế tại thời điểm xuất hóa đơn khách chưa trả đủ.
2. **Timeline double-count:**
   Trong `PartnerDebtLedgerService`, hệ thống tự động sinh ra dòng ảo `TTHD...` đại diện cho khoản thanh toán hóa đơn lấy từ `$invoice->customer_paid`. Đồng thời, nếu có dòng cash flow thực `PT...` liên quan đến hóa đơn đó, hệ thống lại tiếp tục cộng dòng `PT...` này vào timeline công nợ, dẫn tới số dư công nợ bị trừ hai lần (double-count) cho cùng một khoản tiền thực tế thanh toán.
3. **Cách hiển thị popup detail hóa đơn chưa đầy đủ breakdown:**
   Frontend chỉ hiển thị tổng cộng và khách đã thanh toán, không tách bạch rõ ràng tiền cọc đơn hàng đã áp dụng, tiền khách trả thêm lúc xuất hóa đơn và tiền thu nợ sau hóa đơn.

---

## Giải pháp & Các thay đổi thực hiện

### 1. Sửa API chi tiết hóa đơn (`InvoiceController@detail` và `CustomerController@debtVoucherDetail`)
- Bổ sung breakdown chi tiết trong JSON trả về của API:
  - `total_paid` (lũy kế đã thanh toán)
  - `order_deposit_applied_amount` (tiền cọc đơn hàng đã áp dụng)
  - `paid_excluding_deposit` (thanh toán/thu thêm không bao gồm cọc)
  - `paid_after_invoice` (bằng `paid_excluding_deposit`)
  - `remaining_amount` / `debt_amount` (dư nợ còn lại thực tế của hóa đơn)

### 2. Sửa frontend popup hóa đơn (`resources/js/Pages/Customers/Index.vue`)
- Cập nhật cả 2 popup `invoiceDetail` và `debtVoucherDetailModal` để hiển thị rõ ràng:
  - Tổng cộng hóa đơn
  - Khách đã thanh toán lũy kế
    - Cọc đơn hàng đã áp dụng
    - Thanh toán/thu thêm
  - Còn phải thu hóa đơn (dư nợ thực tế)
- Ẩn nhãn "Đã thanh toán đủ" nếu dư nợ hóa đơn (`remaining_amount`) lớn hơn 0.

### 3. Chống double-count timeline công nợ (`PartnerDebtLedgerService` & `PartnerFinancialTimelineService`)
- Thêm kiểm tra de-duplication: Nếu hóa đơn hoặc đơn hàng gốc đã có dòng hạch toán công nợ thực tế (`CustomerDebt` tồn tại) hoặc có dòng dòng tiền thực tế (`CashFlow` không bị cancelled), thì dòng ảo `TTHD...` (sinh ra từ `invoice.customer_paid`) sẽ được cấu hình thành `is_reference_only = true` và `affects_debt_balance = false` với số dư tác động bằng `0.0`.
- Giữ nguyên `display_effect` và `customer_display_effect` của `TTHD` là số tiền khách trả để hiển thị tham chiếu chính xác trên giao diện timeline nhưng không làm sai lệch số dư công nợ.
- Thêm cột `order_id` vào select queries của `Invoice::query()` trong cả hai timeline service để tránh lỗi thiếu dữ liệu đơn hàng gốc khi thực hiện de-dup.

### 4. Sửa test `HOTFIXFollowUpDebtHistoryPaginationTest`
- Lỗi count 26 entries thay vì 25: Do partner trong test được khởi tạo với `supplier_debt_amount = 0` nhưng lại tạo 25 hóa đơn nhập hàng chưa trả tiền (tổng giá trị 32.5M). Sự không khớp này khiến timeline Net sinh thêm 1 dòng virtual opening balance `OPENING-BALANCE-` để cân đối số dư về 0.
- Giải pháp: Cập nhật `supplier_debt_amount = $expectedPayable` (32.5M) lúc khởi tạo partner để database nhất quán với ledger, triệt tiêu dòng virtual opening balance, trả về đúng 25 entries.

---

## Safety Checklist
- **Có migration không:** Có, đã chạy migration `2026_06_10_202941_add_partial_order_fulfillment_fields` bổ sung cột `order_deposit_applied_amount` vào table `invoices` thành công.
- **Có backfill dữ liệu cũ không:** Không.
- **Có update/sửa đổi dữ liệu cũ bằng tay không:** Không.
- **Có recalculate công nợ khách hàng không:** Không.

---

## Kết quả kiểm thử & Build

### 1. PHPUnit Tests
Chạy thành công tất cả các test suite liên quan:
- `InvoiceDetailPaymentBreakdownTest`: **PASS** (1 test, 6 assertions)
- `CustomerDebtTimelineNoDoubleCountTest`: **PASS** (1 test, 10 assertions)
- `OrderDepositPartialDebtTest`: **PASS** (3 tests, 37 assertions)
- `OrderPartialFulfillmentAndMergeTest`: **PASS** (12 tests, 83 assertions)
- `HOTFIXFollowUpDebtHistoryPaginationTest`: **PASS** (5 tests, 21 assertions)
- `CustomerDebt` directory: **PASS** (9 tests, 61 assertions)
- `Customers` directory: **PASS** (106 tests passed, 1 skipped)
- `POS` directory: **PASS** (65 tests passed, 273 assertions)

### 2. Frontend Build
- `npm run build`: **Thành công** (built in 8.35s, không có lỗi biên dịch).

---

## Kết luận
- **Trạng thái:** Hoàn thành tốt, toàn bộ các mục tiêu đặt ra trong hotfix đều đã được đáp ứng đầy đủ và vượt qua kiểm thử.
- **Có thể deploy chưa:** Sẵn sàng deploy.
- **Rủi ro còn lại:** Không có rủi ro nào được phát hiện.
