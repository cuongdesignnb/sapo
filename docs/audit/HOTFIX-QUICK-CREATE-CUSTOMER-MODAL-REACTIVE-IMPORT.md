# HOTFIX — QuickCreateCustomerModal reactive import

## Lỗi production
- Màn hình: /orders/create
- Error: ReferenceError: reactive is not defined
- Asset lỗi: QuickCreateCustomerModal-*.js

## Root cause
- File: resources/js/Components/QuickCreateCustomerModal.vue
- Import thiếu: reactive
- Component dùng reactive ở đâu: dòng 146, khởi tạo dualRoleConfirm

## Patch
```diff
- import { ref, watch } from 'vue';
+ import { ref, watch, reactive } from 'vue';
```

## Data safety
- Migration: No
- Backfill: No
- Update dữ liệu cũ: No
- Recalculate: No

## Tests
- Static check: verified import `{ ref, watch, reactive }` exists and `dualRoleConfirm = reactive({...})` is used correctly.
- npm run build: Success, compiled to QuickCreateCustomerModal-DFvd_q1F.js
- Manual QA: OK

## Deploy note
- Commit: 3592cecd6e87e889d43ca3b44d6896255b755a89
- Production đã pull chưa: No
- Production đã build lại chưa: No
