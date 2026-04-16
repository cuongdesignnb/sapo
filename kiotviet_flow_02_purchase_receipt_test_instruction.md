# KIOTVIET FLOW 02 — KIỂM THỬ NHẬP HÀNG TỪ NHÀ CUNG CẤP

## Mục tiêu
Bạn là AI agent kiểm thử hệ thống quản lý bán hàng nội bộ.
Nhiệm vụ của bạn là kiểm tra **Flow 02: Nhập hàng từ nhà cung cấp** của hệ thống hiện tại, theo hành vi tham chiếu từ tài liệu KiotViet.

Flow này chỉ tập trung vào **nghiệp vụ nhập hàng**:
1. Tạo phiếu nhập hàng
2. Thêm hàng hóa vào phiếu nhập
3. Chọn / thêm nhanh nhà cung cấp
4. Ghi nhận thanh toán đủ / thiếu / chưa thanh toán
5. Ghi nhận tồn kho tăng sau khi hoàn tất
6. Ghi nhận công nợ nhà cung cấp
7. Lưu tạm và cập nhật phiếu tạm
8. Hủy phiếu nhập hàng và hoàn tác tác động liên quan
9. Xem lịch sử thanh toán của phiếu nhập có công nợ

Không test các flow khác trong lần này.
Không mở rộng sang bán hàng, thu nợ khách, trả hàng nhập, kiểm kho, báo cáo tổng hợp hay các flow ngoài phạm vi trên.
Chỉ được kiểm thử đúng Flow 02.

---

## Nguyên tắc làm việc bắt buộc
1. Chỉ kiểm thử **một flow duy nhất**: Flow 02.
2. Không suy đoán hệ thống “có lẽ đúng”. Phải kiểm bằng thao tác thực tế hoặc bằng source + database + UI/API hiện có.
3. Nếu hệ thống có source, hãy ưu tiên kết hợp:
   - đọc route / controller / service / validation / policy
   - đọc schema database và constraint
   - chạy UI hoặc API test thực tế
4. Nếu phát hiện hệ thống chưa đúng hành vi mong đợi:
   - ghi rõ sai ở đâu
   - giải thích ảnh hưởng
   - đề xuất fix tối thiểu
   - chỉ bổ sung/sửa code khi đã xác định được lỗi thật
5. Không refactor lan rộng.
6. Không sửa các phần ngoài Flow 02.
7. Sau mỗi thay đổi, phải re-test đúng case vừa sửa.
8. Không kết luận “pass” nếu chưa có bằng chứng.
9. Nếu thiếu dữ liệu nền, được phép tạo **dữ liệu tối thiểu cần thiết** đúng theo bộ dữ liệu test ở file này; không được tạo dữ liệu thừa.
10. Nếu hệ thống có khác biệt có chủ đích so với KiotViet, phải ghi rõ đó là **deviation có chủ ý** chứ không tự coi là lỗi.

---

## Chuẩn tham chiếu của Flow 02
Flow 02 bám theo logic nhập hàng của KiotViet:
- Nhập hàng là nghiệp vụ ghi nhận hàng nhập từ nhà cung cấp, sau khi hoàn tất thì tồn kho tăng và công nợ phải trả được quản lý tương ứng.
- Tạo phiếu nhập đi từ menu Hàng hóa → Nhập hàng → + Nhập hàng.
- Có thể thêm hàng hóa vào phiếu bằng tìm kiếm / chọn nhanh; nếu sản phẩm chưa có thì có thể thêm mới trực tiếp trên phiếu nhập.
- Có thể thêm nhanh nhà cung cấp ngay trên phiếu nhập bằng dấu cộng cạnh ô tìm nhà cung cấp.
- Nếu **Tiền trả nhà cung cấp = 0** hoặc chỉ trả một phần, phần còn lại được tính vào công nợ với nhà cung cấp.
- Phiếu có thể được lưu ở trạng thái **Phiếu tạm** và được cập nhật lại sau đó.
- Khi **hủy phiếu nhập**, hệ thống chuyển phiếu sang trạng thái **Đã hủy**, đồng thời cập nhật lại tồn kho và công nợ; nếu có phiếu thanh toán liên quan thì hệ thống hỏi có hủy các phiếu thanh toán liên quan hay không.
- Với phiếu nhập có công nợ, có thể xem tab **Lịch sử thanh toán** để theo dõi các lần đã thanh toán.

Lưu ý:
- Flow này chỉ bám hành vi của nghiệp vụ nhập hàng.
- Không test trả hàng nhập trong flow này.
- Không test hóa đơn đầu vào điện tử trong flow này.
- Nếu hệ thống có import Excel cho dòng hàng trên phiếu nhập, coi đây là test phụ trợ, không phải tiêu chí pass lõi.

---

## Phạm vi kiểm tra
### A. Tạo phiếu nhập hàng hoàn tất
Cần kiểm:
- tạo phiếu nhập mới
- thêm 1 hoặc nhiều hàng hóa vào phiếu
- nhập số lượng, giá nhập
- chọn nhà cung cấp
- hoàn thành phiếu
- kiểm tra tồn kho tăng
- kiểm tra công nợ và thanh toán

### B. Ghi nhận thanh toán
Cần kiểm:
- thanh toán đủ
- thanh toán một phần
- chưa thanh toán
- phương thức thanh toán nếu hệ thống có
- số tiền còn lại có được ghi vào công nợ hay không

### C. Phiếu tạm
Cần kiểm:
- lưu tạm phiếu nhập
- mở lại và chỉnh sửa phiếu tạm
- hoàn thành từ phiếu tạm
- sửa khi phiếu đã hoàn thành có bị chặn hay không theo rule hiện tại

### D. Thêm nhanh trong phiếu nhập
Cần kiểm:
- thêm nhanh nhà cung cấp
- nếu có hỗ trợ: thêm nhanh hàng hóa mới ngay trên phiếu nhập
- dữ liệu tạo nhanh có đồng bộ về danh mục hay không

### E. Hủy phiếu nhập
Cần kiểm:
- hủy phiếu nhập đã hoàn thành
- hành vi với phiếu thanh toán liên quan
- tồn kho và công nợ có rollback đúng không
- trạng thái phiếu có đổi sang Đã hủy không

### F. Lịch sử thanh toán
Cần kiểm:
- phiếu có công nợ có xem được lịch sử thanh toán hay không
- dữ liệu lịch sử có khớp với các lần thanh toán đã ghi nhận hay không

---

## Dữ liệu nền tối thiểu bắt buộc
Ưu tiên tái sử dụng dữ liệu của Flow 01. Nếu chưa có, tạo đúng bộ tối thiểu sau:

### Kho
- code: `KHO_TONG`
- name: `Kho tổng`

### Hàng hóa 1
- code: `SP001`
- name: `Nước suối 500ml`
- cost_price mặc định: `5000`
- sale_price mặc định: `7000`
- opening_stock ban đầu: `20`

### Hàng hóa 2
- code: `SP002`
- name: `Bánh quy hộp`
- cost_price mặc định: `20000`
- sale_price mặc định: `30000`
- opening_stock ban đầu: `10`

### Nhà cung cấp
- code: `NCC001`
- name: `Công ty Minh Phát`
- phone: `0900000002`

Không tự đổi bộ dữ liệu trừ khi có lý do kỹ thuật bất khả kháng.
Nếu phải đổi, ghi rõ lý do.

---

## Bộ kịch bản test cố định
Sử dụng đúng các case sau để tránh test ngẫu nhiên.

### CASE 02A — Nhập hàng thanh toán đủ
Dữ liệu:
- NCC: `NCC001`
- Kho: `KHO_TONG`
- SP001: số lượng `5`, giá nhập `5000`
- SP002: số lượng `2`, giá nhập `20000`
- Tổng giá trị phiếu: `65000`
- Tiền trả nhà cung cấp: `65000`

Kỳ vọng:
- phiếu lưu thành công
- trạng thái phiếu là hoàn tất / đã nhập hàng
- tồn SP001 tăng từ `20` lên `25`
- tồn SP002 tăng từ `10` lên `12`
- công nợ nhà cung cấp tăng `0`
- nếu có phiếu chi / thanh toán thì số tiền là `65000`

### CASE 02B — Nhập hàng chưa thanh toán
Dữ liệu:
- NCC: `NCC001`
- Kho: `KHO_TONG`
- SP001: số lượng `10`, giá nhập `5000`
- Tổng giá trị phiếu: `50000`
- Tiền trả nhà cung cấp: `0`

Kỳ vọng:
- phiếu lưu thành công
- trạng thái phiếu là hoàn tất / đã nhập hàng
- tồn SP001 tăng thêm `10`
- công nợ nhà cung cấp tăng `50000`
- lịch sử thanh toán của phiếu chưa có giao dịch thanh toán thực thu/chi hoặc ghi nhận đúng là chưa thanh toán

### CASE 02C — Nhập hàng thanh toán một phần
Dữ liệu:
- NCC: `NCC001`
- Kho: `KHO_TONG`
- SP002: số lượng `3`, giá nhập `20000`
- Tổng giá trị phiếu: `60000`
- Tiền trả nhà cung cấp: `20000`

Kỳ vọng:
- phiếu lưu thành công
- tồn SP002 tăng thêm `3`
- công nợ nhà cung cấp tăng `40000`
- lịch sử thanh toán của phiếu ghi nhận `20000`

### CASE 02D — Lưu tạm và cập nhật phiếu tạm
Dữ liệu:
- NCC: `NCC001`
- Kho: `KHO_TONG`
- SP001: số lượng `4`, giá nhập `5000`
- Chỉ lưu tạm, chưa hoàn thành

Kỳ vọng:
- phiếu ở trạng thái `Phiếu tạm`
- tồn kho chưa tăng nếu hệ thống bám đúng logic hoàn tất mới nhập kho
- công nợ chưa ghi nhận cuối cùng nếu phiếu chưa hoàn tất
- mở lại phiếu tạm để sửa được số lượng thành `6`
- sau khi hoàn thành, tồn tăng đúng theo dữ liệu cuối cùng đã sửa

### CASE 02E — Thêm nhanh NCC trong phiếu nhập
Dữ liệu:
- NCC mới: `NCC_FLOW02`
- name: `Nhà cung cấp Flow 02`
- phone: `0900000202`

Kỳ vọng:
- tạo được ngay trên phiếu nhập
- NCC được gán vào phiếu hiện tại
- ra danh mục nhà cung cấp tìm thấy bản ghi tương ứng
- không sinh ra bản ghi “ảo” chỉ tồn tại trên phiếu

### CASE 02F — Thêm nhanh hàng hóa trong phiếu nhập (nếu hệ thống hỗ trợ)
Dữ liệu:
- code: `SP_NEW_02`
- name: `Hàng mới Flow 02`
- giá nhập: `15000`
- số lượng: `2`

Kỳ vọng:
- thêm được ngay trên phiếu nhập
- hàng hóa xuất hiện trong danh mục hàng hóa
- hoàn thành phiếu thì tồn của hàng mới tăng đúng `2`

Nếu hệ thống không hỗ trợ thêm nhanh hàng hóa ngay trên phiếu nhập, ghi rõ là `Not applicable` và không coi là fail nếu đây là khác biệt có chủ đích.

### CASE 02G — Hủy phiếu nhập hàng đã hoàn thành
Dữ liệu:
- lấy một phiếu đã hoàn thành ở CASE 02A hoặc CASE 02C

Kỳ vọng:
- hủy được theo rule hiện tại
- trạng thái phiếu chuyển sang `Đã hủy`
- tồn kho bị trừ lại đúng phần đã cộng từ phiếu đó
- công nợ nhà cung cấp rollback đúng
- nếu phiếu có thanh toán liên quan, hệ thống có hành vi rõ ràng: hỏi hủy phiếu thanh toán liên quan hoặc xử lý tương đương

### CASE 02H — Xem lịch sử thanh toán phiếu nhập
Dữ liệu:
- lấy phiếu ở CASE 02C

Kỳ vọng:
- mở được tab / màn hình lịch sử thanh toán
- thấy đúng số tiền `20000`
- tổng đã thanh toán và còn nợ khớp với phiếu

---

## Trình tự kiểm thử bắt buộc

### Bước 1 — Đọc source để xác định nơi cần kiểm
Tìm và liệt kê:
- model / entity của phiếu nhập, dòng phiếu nhập, nhà cung cấp, kho, thanh toán, công nợ, tồn kho
- migration / schema / constraint liên quan
- route / controller / service / repository
- validation rule
- màn hình UI hoặc endpoint API tạo/sửa/lưu tạm/hủy/xem lịch sử thanh toán

Kết quả cần ghi ra:
- file nào điều khiển từng phần của flow nhập hàng
- rule nào đang áp dụng
- điểm nào nghi ngờ lệch với hành vi tham chiếu

### Bước 2 — Kiểm tra database/schema
Kiểm tra tối thiểu:
- có bảng hoặc thực thể cho phiếu nhập và dòng phiếu nhập hay không
- có trạng thái phiếu hay không (`draft/temp/completed/cancelled` hoặc tương đương)
- có liên kết tới nhà cung cấp, kho, người tạo hay không
- có bảng / cột ghi nhận thanh toán và công nợ hay không
- có bảng / cơ chế stock movement hoặc tồn kho tổng hợp hay không
- có cơ chế soft delete / hủy / đảo bút toán hay không

Nếu không có constraint nhưng code đang validate bằng app layer, phải ghi rõ.

### Bước 3 — Kiểm tra dữ liệu nền tối thiểu
Xác minh các dữ liệu sau đã sẵn sàng dùng cho flow:
- `KHO_TONG`
- `SP001`
- `SP002`
- `NCC001`

Nếu chưa có, tạo tối thiểu đúng bộ dữ liệu trên.

### Bước 4 — Chạy CASE 02A: nhập hàng thanh toán đủ
Thao tác:
1. Tạo phiếu nhập mới
2. Chọn NCC `NCC001`
3. Chọn kho `KHO_TONG`
4. Thêm `SP001`, số lượng `5`, giá nhập `5000`
5. Thêm `SP002`, số lượng `2`, giá nhập `20000`
6. Nhập tiền trả nhà cung cấp `65000`
7. Hoàn thành phiếu

Cần kiểm:
- phiếu tạo thành công
- trạng thái đúng
- tồn kho tăng đúng
- không phát sinh công nợ còn lại
- thanh toán / phiếu chi ghi đúng nếu hệ thống có

### Bước 5 — Chạy CASE 02B: nhập hàng chưa thanh toán
Thao tác:
1. Tạo phiếu nhập mới
2. Chọn NCC `NCC001`
3. Chọn kho `KHO_TONG`
4. Thêm `SP001`, số lượng `10`, giá nhập `5000`
5. Để tiền trả nhà cung cấp = `0`
6. Hoàn thành phiếu

Cần kiểm:
- tồn tăng đúng
- công nợ tăng `50000`
- phiếu hoàn tất thành công
- lịch sử thanh toán phản ánh đúng là chưa thanh toán hoặc không có giao dịch thanh toán

### Bước 6 — Chạy CASE 02C: nhập hàng thanh toán một phần
Thao tác:
1. Tạo phiếu nhập mới
2. Chọn NCC `NCC001`
3. Chọn `SP002`, số lượng `3`, giá nhập `20000`
4. Nhập tiền trả nhà cung cấp `20000`
5. Hoàn thành phiếu

Cần kiểm:
- tồn tăng đúng
- công nợ tăng `40000`
- có ghi nhận thanh toán `20000`

### Bước 7 — Chạy CASE 02D: lưu tạm và cập nhật phiếu tạm
Thao tác:
1. Tạo phiếu nhập mới
2. Chọn NCC `NCC001`
3. Chọn `SP001`, số lượng `4`, giá nhập `5000`
4. Lưu tạm
5. Mở lại phiếu tạm
6. Sửa số lượng thành `6`
7. Hoàn thành phiếu

Cần kiểm:
- trạng thái phiếu tạm có tồn tại
- sửa được phiếu tạm
- dữ liệu cuối cùng sau hoàn thành là số lượng `6`
- tồn không bị cộng hai lần

### Bước 8 — Chạy CASE 02E: thêm nhanh nhà cung cấp trên phiếu nhập
Thao tác:
1. Tạo phiếu nhập mới
2. Dùng nút cộng cạnh ô tìm nhà cung cấp
3. Tạo `NCC_FLOW02`
4. Lưu và gán vào phiếu
5. Kiểm tra danh mục nhà cung cấp

Cần kiểm:
- NCC mới xuất hiện ngay trên phiếu
- NCC tồn tại thật trong danh mục
- không sinh trùng dữ liệu bất thường

### Bước 9 — Chạy CASE 02F: thêm nhanh hàng hóa trên phiếu nhập (nếu có)
Thao tác:
1. Tạo phiếu nhập mới
2. Dùng tính năng thêm hàng hóa mới ngay trong phiếu
3. Tạo `SP_NEW_02`
4. Hoàn thành phiếu với số lượng `2`, giá nhập `15000`
5. Kiểm tra lại danh mục hàng hóa và tồn kho

Nếu tính năng không tồn tại, ghi rõ bằng chứng và đánh dấu `Not applicable`.

### Bước 10 — Chạy CASE 02G: hủy phiếu nhập đã hoàn thành
Thao tác:
1. Mở phiếu đã hoàn thành
2. Chọn Hủy
3. Nếu hệ thống hỏi về phiếu thanh toán liên quan, ghi nhận rõ các lựa chọn và chọn phương án phù hợp để giữ dữ liệu nhất quán
4. Xác nhận hủy

Cần kiểm:
- trạng thái chuyển `Đã hủy`
- tồn kho rollback đúng
- công nợ rollback đúng
- dữ liệu thanh toán liên quan được xử lý nhất quán

### Bước 11 — Chạy CASE 02H: xem lịch sử thanh toán
Thao tác:
1. Mở phiếu có thanh toán một phần ở CASE 02C
2. Mở tab / màn hình lịch sử thanh toán

Cần kiểm:
- thấy đúng giao dịch `20000`
- tổng đã thanh toán + còn nợ = tổng phiếu

### Bước 12 — Đối chiếu số liệu sau từng case
Sau mỗi case, bắt buộc kiểm ít nhất 4 điểm:
1. Trạng thái phiếu
2. Tồn kho của từng sản phẩm bị ảnh hưởng
3. Công nợ nhà cung cấp
4. Giao dịch thanh toán liên quan

Công thức đối chiếu:
- **Tồn sau phiếu nhập = tồn trước + tổng số lượng nhập hoàn tất - tổng số lượng bị rollback do hủy phiếu**
- **Công nợ phát sinh = tổng giá trị phiếu - số tiền đã thanh toán - các khoản rollback liên quan nếu hủy**

---

## Gợi ý nơi cần soi kỹ trong source
Agent cần ưu tiên đọc và soi kỹ các điểm sau:
- service xử lý `complete purchase receipt`
- service xử lý `save draft`
- service xử lý `cancel purchase receipt`
- logic cập nhật tồn kho
- logic cập nhật công nợ nhà cung cấp
- logic tạo phiếu thanh toán / phiếu chi liên quan
- validation không cho số lượng âm / giá nhập âm / NCC rỗng
- rule chặn sửa phiếu đã hoàn thành (nếu có)

Nếu phát hiện logic dùng update trực tiếp tồn kho mà không có log movement, phải ghi rõ rủi ro.

---

## Checklist sai lệch nghiêm trọng
Nếu có một trong các lỗi sau, kết luận Flow 02 **chưa đạt**:
- hoàn thành phiếu nhưng tồn kho không tăng đúng
- hoàn thành phiếu nhưng công nợ không tăng đúng khi trả thiếu / chưa trả
- lưu tạm mà đã cộng tồn hoặc ghi nợ như phiếu hoàn tất
- sửa phiếu tạm làm tồn cộng chồng nhiều lần
- hủy phiếu nhưng không rollback tồn và công nợ
- có thanh toán liên quan nhưng hủy phiếu làm dữ liệu thanh toán mồ côi / lệch tổng
- thêm nhanh NCC hoặc hàng hóa trên phiếu nhưng không đồng bộ về danh mục
- tổng đã thanh toán + còn nợ không khớp tổng phiếu

---

## Ghi nhận sai lệch
Mỗi lỗi phải ghi theo mẫu:
- ID lỗi
- Hạng mục
- Case liên quan
- Bước tái hiện
- Kết quả hiện tại
- Kết quả mong đợi
- Mức độ ảnh hưởng: Low / Medium / High / Critical
- Nguyên nhân nghi ngờ
- File liên quan
- Đề xuất fix tối thiểu

---

## Chỉ sửa lỗi đã xác định
Nếu được phép sửa code:
- sửa nhỏ nhất có thể
- không đổi kiến trúc toàn hệ thống
- không đụng flow khác
- thêm test hoặc script tái hiện lỗi
- sau khi sửa phải re-test đúng case lỗi và ít nhất 1 case lân cận bị ảnh hưởng

---

## Điều kiện PASS cho Flow 02
Chỉ được kết luận PASS khi đồng thời đúng cả các điều kiện sau:
1. Tạo được phiếu nhập hoàn tất.
2. Tồn kho tăng đúng khi hoàn tất.
3. Thanh toán đủ thì không còn công nợ.
4. Thanh toán thiếu / chưa thanh toán thì công nợ tăng đúng.
5. Phiếu tạm lưu được và chỉnh sửa lại được.
6. Hoàn thành từ phiếu tạm không làm cộng tồn 2 lần.
7. Hủy phiếu rollback đúng tồn, công nợ và thanh toán liên quan.
8. Lịch sử thanh toán hiển thị khớp với dữ liệu thực tế.
9. Thêm nhanh NCC trên phiếu nhập đồng bộ đúng về danh mục.
10. Không có lỗi nghiêm trọng làm lệch tồn, lệch nợ hoặc lệch trạng thái.

---

## Điều kiện PASS WITH DEVIATION
Được phép kết luận `Pass with deviation` nếu:
- hệ thống không có thêm nhanh hàng hóa trên phiếu nhập nhưng đây là khác biệt có chủ đích và không phá flow lõi;
- tên menu / tên trạng thái khác KiotViet nhưng hành vi tương đương;
- không có giao diện “Lịch sử thanh toán” riêng nhưng dữ liệu thanh toán vẫn xem được rõ ràng ở chi tiết phiếu hoặc tab công nợ.

Phải ghi rõ deviation và ảnh hưởng.

---

## Đầu ra bắt buộc
Agent phải trả về đủ 5 phần sau:

### 1. Tóm tắt kiểm thử
- Flow đang test
- phạm vi đã kiểm
- môi trường kiểm
- dữ liệu test đã dùng

### 2. Bảng kết quả
Dùng đúng cấu trúc:

| Hạng mục | Case | Kết quả | Ghi chú |
|---|---|---|---|
| Nhập hàng | CASE 02A thanh toán đủ | Pass/Fail | ... |
| Nhập hàng | CASE 02B chưa thanh toán | Pass/Fail | ... |
| Nhập hàng | CASE 02C thanh toán một phần | Pass/Fail | ... |
| Nhập hàng | CASE 02D phiếu tạm | Pass/Fail | ... |
| Nhập hàng | CASE 02E thêm nhanh NCC | Pass/Fail | ... |
| Nhập hàng | CASE 02F thêm nhanh hàng | Pass/Fail/NA | ... |
| Nhập hàng | CASE 02G hủy phiếu | Pass/Fail | ... |
| Nhập hàng | CASE 02H lịch sử thanh toán | Pass/Fail | ... |

### 3. Danh sách lỗi
Mỗi lỗi ghi ngắn gọn nhưng đủ tái hiện.

### 4. Danh sách fix đã áp dụng (nếu có)
- file sửa
- lý do sửa
- cách kiểm lại

### 5. Kết luận cuối
- Pass / Fail / Pass with deviation
- nêu rõ có nên sang Flow 03 hay chưa

---

## Quy tắc an toàn khi sửa
- Không xóa dữ liệu production.
- Không chạy destructive migration trên môi trường thật.
- Không đổi dữ liệu thật nếu chưa có backup.
- Nếu không chắc, dừng ở mức báo lỗi + đề xuất fix.

---

## Chế độ làm việc mong muốn
Hãy làm việc như một QA + BA + Developer hỗn hợp:
- đọc hành vi hiện tại
- đối chiếu với hành vi mong đợi
- chứng minh bằng test
- sửa tối thiểu nếu sai
- re-test
- báo cáo rõ ràng

Khi xong Flow 02, dừng lại.
Không tự động sang Flow 03 cho đến khi được yêu cầu.
