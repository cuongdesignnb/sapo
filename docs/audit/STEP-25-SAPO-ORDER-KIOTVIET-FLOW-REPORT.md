# BÁO CÁO KẾT QUẢ TRIỂN KHAI STEP 25 — HOÀN THIỆN NGHIỆP VỤ ĐẶT HÀNG SAPO - KIOTVIET FLOW

## 1. Tổng quan & Mục tiêu

Mục tiêu của **STEP 25** là hoàn thiện và chuẩn hóa nghiệp vụ **Đặt hàng** (POS Order & Order Processing) trên repository `cuongdesignnb/sapo` để bám sát mô hình nghiệp vụ bán lẻ thực tế của **KiotViet Retail**.

Các quy tắc nghiệp vụ cốt lõi đã được áp dụng đầy đủ:
- **Đơn đặt hàng (Phiếu tạm) chưa phải hóa đơn**: Khi tạo đơn chỉ ghi nhận đặt cọc (nếu có) vào Sổ quỹ (`CashFlow`), không trừ tồn kho, không đánh dấu Serial/IMEI đã bán (`sold`).
- **Đặt hàng khi hết tồn kho**: Cho phép đặt hàng ngay cả khi sản phẩm tạm thời hết hàng (nếu có thiết lập tương ứng), nhưng chỉ kiểm tra và trừ tồn thực tế khi tiến hành chuyển đơn thành hóa đơn.
- **Xử lý đơn hàng một phần hoặc nhiều lần**: Hỗ trợ xuất hóa đơn cho một phần số lượng hàng hóa đã đặt. Khi thanh toán một phần đơn hàng:
  - Khấu trừ lũy tiến số tiền cọc của đơn hàng (thông qua cột `order_deposit_applied_amount` của hóa đơn) để tránh khấu trừ trùng lặp.
  - Người dùng tại POS có quyền chọn `"Giữ lại để xử lý sau"` (đơn tiếp tục giữ trạng thái `confirmed`) hoặc `"Kết thúc phần còn lại"` (đơn chuyển sang trạng thái `ended`).
- **Gộp đơn hàng**: Hỗ trợ gộp nhiều đơn hàng compatible (cùng chi nhánh, cùng khách hàng, ở trạng thái `draft`/`confirmed` và chưa được xử lý phần nào) thành một đơn đặt hàng duy nhất, tự động gộp số lượng sản phẩm cùng loại và gộp số tiền đặt cọc.
- **Hủy & Kết thúc đơn hàng**: Hủy hoặc kết thúc đơn hàng từ Admin yêu cầu nhập lý do. Hủy đơn hàng không ảnh hưởng/rollback các hóa đơn một phần đã xuất trước đó.

---

## 2. Các thay đổi kỹ thuật chi tiết

### 2.1. Cấu trúc Database (Migrations & Models)
Chúng ta đã tạo và chạy thành công file migration bổ sung các trường dữ liệu sau:
1. **`order_items.fulfilled_quantity`** (default `0`): Theo dõi số lượng thực xuất của từng dòng hàng.
2. **`invoice_items.order_item_id`** (nullable, index): Liên kết dòng hóa đơn với dòng đặt hàng tương ứng.
3. **`invoices.order_deposit_applied_amount`** (default `0`): Lưu số tiền cọc của đơn hàng đã được khấu trừ trong hóa đơn này.

Cập nhật các Eloquent Models:
- **`Order.php`**: Khai báo quan hệ `invoices()` (`hasMany`) để liên kết một đơn hàng với nhiều hóa đơn.
- **`OrderItem.php`**: Bổ sung cast `fulfilled_quantity` -> `integer` và thêm accessor `remaining_quantity` (`qty - fulfilled_quantity`).
- **`InvoiceItem.php`**: Thêm quan hệ `orderItem()` (`belongsTo`).
- **`Invoice.php`**: Cast `order_deposit_applied_amount` -> `float`.
- **`OrderStatus.php`**: Thêm trạng thái `ended` (Đã kết thúc) vào enum và danh sách options hiển thị trên UI.

### 2.2. Backend Logic (Controllers & Routes)
Đăng ký đầy đủ các route cho `orders.merge`, `orders.cancel`, `orders.end` và cập nhật logic trong `OrderController`:
1. **Chặn cập nhật trạng thái trực tiếp (`update`)**:
   - Trả về lỗi 422 (JSON) nếu khách hàng gửi yêu cầu PUT cập nhật trực tiếp trạng thái sang `completed`, `cancelled`, hoặc `ended` từ ngoài.
   - Chặn thay đổi danh sách items hoặc số tiền đặt cọc nếu đơn hàng đã được xử lý một phần hoặc đã xuất hóa đơn.
2. **Ghi nhận đặt cọc khi Tạo đơn (`store` & POS `quickOrder`)**:
   - Tạo phiếu thu Sổ quỹ (`CashFlow`, loại `receipt`) ghi nhận đặt cọc khi `amount_paid > 0`.
3. **Xử lý đơn một phần & Nhiều lần (`processOrder`)**:
   - Validate số lượng thực xuất phải `<= remaining_quantity`.
   - Cho phép **Serial/IMEI Override** tại thời điểm POS checkout: nhân viên có thể quét mã serial khác với serial đã đặt trước trong đơn đặt hàng.
   - Hỗ trợ **Fallback tự động**: Nếu payload request không gửi lên danh sách `items`, backend tự động map toàn bộ số lượng hàng hóa còn lại của đơn hàng để xử lý (tương thích ngược hoàn hảo với các test suite cũ).
   - Tính toán khấu trừ cọc lũy tiến:
     $$\text{deposit\_remaining} = \max(0, \text{order.amount\_paid} - \sum \text{invoices.order\_deposit\_applied\_amount})$$
     $$\text{applied\_amount} = \min(\text{deposit\_remaining}, \text{invoice\_total})$$
     $$\text{debt\_amount} = \text{invoice\_total} - (\text{applied\_amount} + \text{additional\_payment})$$
   - Ghi nhận `CashFlow` cho phần thanh toán thêm nếu có (`amount_paid` của request > 0). Không tạo cashflow cọc trùng lặp.
   - Cập nhật trạng thái đơn hàng sang `completed` nếu đã giao hết, hoặc sang `ended` nếu còn dư nhưng người dùng chọn kết thúc, hoặc giữ `confirmed` để xử lý tiếp.
4. **Gộp đơn hàng (`merge`)**:
   - Chặn gộp nếu có bất kỳ đơn hàng nào đã xử lý một phần (`fulfilled_quantity > 0`).
   - Group các sản phẩm có cùng `product_id` lại thành một dòng, cộng dồn số lượng đặt, gộp các mảng `serial_ids`.
   - Gộp cọc `amount_paid` sang đơn mới và chuyển trạng thái các đơn nguồn sang `cancelled` kèm note ghi rõ mã đơn gộp mới.
5. **Hủy & Kết thúc đơn hàng (`cancel` & `endOrder`)**:
   - Lưu lý do hủy/kết thúc vào cột `note` của đơn hàng.
   - Ghi nhận hành động kèm lý do hủy/kết thúc cụ thể vào nhật ký hoạt động (`ActivityLog`).
   - Trả về JSON (kèm mã 200/422) nếu client yêu cầu muốn nhận JSON.

### 2.3. Frontend (POS & Admin UI)
- **POS Index (`resources/js/Pages/POS/Index.vue`)**:
  - Bổ sung UI chọn Ngày giao hàng dự kiến, Phương thức đặt cọc, Tài khoản cọc và Panel điền thông tin Giao nhận hàng trong tab Đặt hàng.
  - Khi load đơn đặt hàng để xử lý, tự động hydrate số lượng sản phẩm còn lại khả dụng (`remaining_quantity`).
  - Thêm modal xác nhận lựa chọn khi thanh toán một phần: `"Giữ lại để xử lý sau"` hoặc `"Kết thúc phần còn lại"`.
- **Orders Admin Index (`resources/js/Pages/Orders/Index.vue`)**:
  - Tích hợp checkbox chọn nhiều đơn hàng và nút **Gộp đơn** trên toolbar danh sách (chỉ sáng khi chọn >= 2 đơn hợp lệ).
  - Tích hợp nút **Hủy đơn** và **Kết thúc đơn** trên giao diện chi tiết, hiển thị modal nhập lý do và gửi POST request tương ứng.
- **Orders Create (`resources/js/Pages/Orders/Create.vue`)**:
  - Bổ sung các input: Phương thức cọc, Thông tin tài khoản cọc và Ngày giao dự kiến gửi đầy đủ lên backend.

---

## 3. Kết quả kiểm thử tự động (Automated Verification)

Chúng tôi đã viết một test suite hoàn chỉnh bao gồm **10 test cases tự động** trong [OrderPartialFulfillmentAndMergeTest.php](file:///d:/Kiot/kiotviet-sapo/tests/Feature/Orders/OrderPartialFulfillmentAndMergeTest.php) bao quát toàn bộ các kịch bản nghiệp vụ:

1. `order status guard` (Chặn PUT đổi status đặc biệt) -> **PASS**
2. `order create deposit cashflow` (Tạo đơn có cọc, tăng quỹ, không trừ kho) -> **PASS**
3. `pos quick order` (Đặt hàng nhanh POS lưu đủ cọc, expected date, delivery info) -> **PASS**
4. `order process full` (Xử lý toàn bộ đơn hàng, trừ kho, mark serial sold) -> **PASS**
5. `order process partial keep` (Xử lý một phần và giữ lại đơn để xử lý tiếp) -> **PASS**
6. `order process partial end` (Xử lý một phần và kết thúc phần còn lại) -> **PASS**
7. `order deposit multiple invoice` (Xử lý nhiều lần, khấu trừ cọc lũy tiến chính xác) -> **PASS**
8. `order process serial partial` (Xử lý một phần hàng serial, chỉ serial thực xuất chuyển sang sold) -> **PASS**
9. `order merge` (Gộp đơn hàng cùng khách, cùng chi nhánh, cộng dồn sản phẩm & cọc) -> **PASS**
10. `order cancel and end` (Hủy/kết thúc đơn đặt hàng ghi lý do vào log, giữ nguyên hóa đơn partial đã tạo) -> **PASS**

### Regression Tests (Kiểm thử hồi quy)
- Chạy test suite POS cũ `ProcessOrderViaPosTest` -> **PASS 100% (8/8 cases)**
- Chạy test suite tồn kho `RR13OrderConvertStockTest` -> **PASS 100% (5/5 cases)**

Các tài nguyên frontend Vue và JS được build biên dịch (`npm run build`) thành công **100% không gặp lỗi syntax hay type check**.

---

## 4. Hướng dẫn xác minh thủ công (Manual Verification)

Bạn có thể tiến hành test trực tiếp luồng nghiệp vụ trên môi trường local bằng cách thực hiện các bước sau:

### Kịch bản 1: Tạo đơn đặt hàng có cọc và kiểm tra kho/quỹ
1. Truy cập màn hình bán hàng POS hoặc tạo đơn đặt hàng tại trang Admin.
2. Chọn 1 sản phẩm hàng thường và 1 sản phẩm serial. Chọn khách hàng bất kỳ.
3. Nhập số tiền đặt cọc (ví dụ: `200,000 VND`), chọn phương thức thanh toán là `Chuyển khoản`.
4. Bấm **Đặt hàng**.
5. **Kiểm tra**:
   - Truy cập **Sổ quỹ**: Phải xuất hiện 1 phiếu thu đặt cọc giá trị `200,000 VND` gắn liền với mã đơn đặt hàng đó.
   - Truy cập **Hàng hóa**: Số lượng tồn kho của 2 sản phẩm và trạng thái của Serial/IMEI phải giữ nguyên (không bị giảm hay chuyển trạng thái).

### Kịch bản 2: Xử lý đơn hàng một phần nhiều lần tại POS
1. Mở POS, chọn chức năng **Xử lý đặt hàng** (nút Load Đơn đặt hàng). Chọn đơn hàng vừa tạo ở kịch bản 1.
2. Sản phẩm hiển thị với số lượng đặt. Giảm số lượng xuống còn 1.
3. Bấm **Thanh toán**. Hệ thống sẽ hiển thị một Modal hỏi: *"Bạn muốn Giữ lại phần còn lại để xử lý sau hay Kết thúc phần còn lại?"*
4. Chọn **Giữ lại để xử lý sau** và tiến hành thanh toán.
5. **Kiểm tra**:
   - Hóa đơn được tạo ra chỉ tính tiền cho 1 sản phẩm đã chọn, và tự động trừ số tiền cọc `200,000 VND` vào hóa đơn (thể hiện ở cột Tiền cọc đặt hàng áp dụng).
   - Tồn kho của sản phẩm giảm đi 1, serial được chọn chuyển sang trạng thái `Đã bán`.
   - Trạng thái của đơn đặt hàng vẫn là `Đã xác nhận` (Confirmed).
6. Load lại đơn đặt hàng đó trên POS: Số lượng sản phẩm hiển thị lúc này sẽ là số lượng còn lại chưa giao. Tiến hành xử lý nốt phần còn lại, thanh toán và chọn **Kết thúc**.
7. Đơn đặt hàng sẽ chuyển sang trạng thái `Hoàn thành` (Completed).

### Kịch bản 3: Gộp đơn hàng trên trang quản lý Admin
1. Tạo 2 đơn đặt hàng nháp hoặc đã xác nhận cho cùng 1 Khách hàng tại cùng 1 Chi nhánh. Đơn 1 cọc `50k`, đơn 2 cọc `100k`.
2. Mở danh sách đơn đặt hàng tại Admin (`/orders`).
3. Tích chọn cả 2 đơn hàng đó. Nút **Gộp đơn** trên thanh toolbar sẽ sáng lên.
4. Bấm **Gộp đơn** và xác nhận.
5. **Kiểm tra**:
   - Một đơn hàng gộp mới được tạo ra ở trạng thái nháp/xác nhận, chứa toàn bộ sản phẩm của 2 đơn hàng cũ (các sản phẩm trùng được gộp dòng cộng dồn số lượng).
   - Tiền đặt cọc của đơn gộp mới là `150,000 VND` (gộp từ 50k và 100k).
   - Hai đơn đặt hàng cũ chuyển sang trạng thái `Đã hủy` với ghi chú rõ ràng: *"Đã gộp vào đơn hàng [Mã đơn mới]"*.
