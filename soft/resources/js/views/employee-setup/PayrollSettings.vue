<template>
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200">
            <div
                class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3"
            >
                <div>
                    <div class="text-lg font-semibold text-slate-900">
                        Thiết lập tính lương
                    </div>
                    <div class="text-sm text-slate-500 mt-1">
                        Cấu hình kỳ lương mặc định và tùy chọn tạo bảng lương
                    </div>
                </div>
                <a
                    class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50"
                    href="/employees/payroll"
                    >Mở bảng lương</a
                >
            </div>

            <div class="p-6">
                <div
                    v-if="loading"
                    class="flex items-center gap-2 text-slate-600"
                >
                    <div
                        class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"
                    ></div>
                    <span>Đang tải...</span>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Chu kỳ lương</label
                        >
                        <select
                            v-model="form.pay_cycle"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        >
                            <option value="monthly">Theo tháng</option>
                            <option value="biweekly">2 tuần</option>
                            <option value="weekly">Theo tuần</option>
                        </select>
                        <div class="text-xs text-slate-500 mt-1">
                            Hiện UI kỳ lương tháng là trọng tâm; tuần/2 tuần để
                            chuẩn bị mở rộng.
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Ngày trả lương (gợi ý)</label
                        >
                        <input
                            v-model.number="form.pay_day"
                            type="number"
                            min="1"
                            max="31"
                            step="1"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="font-medium text-slate-900">
                                    Thiết lập kỳ lương theo tháng
                                </div>
                                <div class="text-xs text-slate-500 mt-1">
                                    Dùng để gợi ý khoảng thời gian khi tạo bảng
                                    lương
                                </div>
                            </div>
                            <div class="text-xs text-slate-600">
                                {{ monthlyHint }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Từ ngày</label
                        >
                        <input
                            v-model.number="form.start_day"
                            type="number"
                            min="1"
                            max="31"
                            step="1"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                            :disabled="form.pay_cycle !== 'monthly'"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Đến ngày</label
                        >
                        <input
                            v-model.number="form.end_day"
                            type="number"
                            min="1"
                            max="31"
                            step="1"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                            :disabled="form.pay_cycle !== 'monthly'"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >"Từ ngày" thuộc tháng trước</label
                        >
                        <select
                            v-model="form.start_in_prev_month"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                            :disabled="form.pay_cycle !== 'monthly'"
                        >
                            <option :value="true">Có</option>
                            <option :value="false">Không</option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Mặc định tính lại công khi tạo bảng lương</label
                        >
                        <select
                            v-model="form.default_recalculate_timekeeping"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        >
                            <option :value="true">Có</option>
                            <option :value="false">Không</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Tự động tạo bảng lương</label
                        >
                        <select
                            v-model="form.auto_generate_enabled"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        >
                            <option :value="false">Tắt</option>
                            <option :value="true">
                                Bật (cần cron/job để chạy tự động)
                            </option>
                        </select>
                        <div class="text-xs text-slate-500 mt-1">
                            Hiện tại chỉ lưu cấu hình; bước sau sẽ gắn cron để
                            tự tạo bảng lương theo kỳ.
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
                        type="button"
                        :disabled="saving"
                        @click="save"
                    >
                        Lưu
                    </button>
                </div>
            </div>
        </div>

        <!-- Holidays Section -->
        <div class="bg-white rounded-xl border border-slate-200">
            <div
                class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3"
            >
                <div>
                    <div class="text-lg font-semibold text-slate-900">
                        Ngày làm & Nghỉ
                    </div>
                    <div class="text-sm text-slate-500 mt-1">
                        Quản lý ngày lễ, tết - tự động nhận diện và ảnh hưởng
                        đến chấm công
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        class="px-4 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50"
                        type="button"
                        @click="openAutoGenerateModal"
                    >
                        🎉 Tự động tạo ngày lễ
                    </button>
                    <button
                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
                        type="button"
                        @click="openHolidayModal(null)"
                    >
                        + Thêm ngày lễ
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div
                    v-if="holidaysLoading"
                    class="flex items-center gap-2 text-slate-600"
                >
                    <div
                        class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"
                    ></div>
                    <span>Đang tải...</span>
                </div>

                <div v-else>
                    <div
                        v-if="holidays.length === 0"
                        class="text-center py-8 text-slate-500"
                    >
                        Chưa có ngày lễ nào. Nhấn "Tự động tạo ngày lễ" để thêm
                        các ngày lễ Việt Nam.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th
                                        class="text-left p-3 text-sm font-medium text-slate-600"
                                    >
                                        Ngày
                                    </th>
                                    <th
                                        class="text-left p-3 text-sm font-medium text-slate-600"
                                    >
                                        Tên ngày lễ
                                    </th>
                                    <th
                                        class="text-center p-3 text-sm font-medium text-slate-600"
                                    >
                                        Hệ số lương
                                    </th>
                                    <th
                                        class="text-center p-3 text-sm font-medium text-slate-600"
                                    >
                                        Nghỉ có lương
                                    </th>
                                    <th
                                        class="text-left p-3 text-sm font-medium text-slate-600"
                                    >
                                        Ghi chú
                                    </th>
                                    <th
                                        class="text-right p-3 text-sm font-medium text-slate-600 w-32"
                                    >
                                        Thao tác
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr
                                    v-for="h in holidays"
                                    :key="h.id"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="p-3 text-sm">
                                        {{ formatDate(h.holiday_date) }}
                                    </td>
                                    <td class="p-3">
                                        <div class="font-medium text-slate-900">
                                            {{ h.name }}
                                        </div>
                                    </td>
                                    <td class="p-3 text-center">
                                        <span
                                            class="px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs font-medium"
                                        >
                                            x{{ h.multiplier }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-center">
                                        <span
                                            v-if="h.paid_leave"
                                            class="text-green-600"
                                            >✓</span
                                        >
                                        <span v-else class="text-slate-400"
                                            >−</span
                                        >
                                    </td>
                                    <td class="p-3 text-sm text-slate-600">
                                        {{ h.notes || "—" }}
                                    </td>
                                    <td class="p-3 text-right">
                                        <button
                                            class="px-2 py-1 text-blue-600 hover:bg-blue-50 rounded text-sm"
                                            type="button"
                                            @click="openHolidayModal(h)"
                                        >
                                            Sửa
                                        </button>
                                        <button
                                            class="px-2 py-1 text-red-600 hover:bg-red-50 rounded text-sm"
                                            type="button"
                                            @click="deleteHoliday(h)"
                                        >
                                            Xóa
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200">
            <div
                class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3"
            >
                <div>
                    <div class="text-lg font-semibold text-slate-900">
                        Mẫu lương
                    </div>
                    <div class="text-sm text-slate-500 mt-1">
                        Tạo mẫu lương và các khoản cộng/trừ
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50"
                        href="/employees"
                        >Gán mẫu cho nhân viên</a
                    >
                    <button
                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
                        type="button"
                        @click="openTemplateModal(null)"
                    >
                        + Tạo mẫu
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div
                    v-if="templatesLoading"
                    class="flex items-center gap-2 text-slate-600"
                >
                    <div
                        class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"
                    ></div>
                    <span>Đang tải mẫu lương...</span>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50">
                            <tr>
                                <th
                                    class="text-left p-3 text-sm font-medium text-slate-600"
                                >
                                    Tên
                                </th>
                                <th
                                    class="text-right p-3 text-sm font-medium text-slate-600"
                                >
                                    Lương cơ bản
                                </th>
                                <th
                                    class="text-right p-3 text-sm font-medium text-slate-600"
                                >
                                    Công chuẩn
                                </th>
                                <th
                                    class="text-right p-3 text-sm font-medium text-slate-600"
                                >
                                    Nửa ngày (giờ)
                                </th>
                                <th
                                    class="text-right p-3 text-sm font-medium text-slate-600"
                                >
                                    OT/giờ
                                </th>
                                <th
                                    class="text-left p-3 text-sm font-medium text-slate-600"
                                >
                                    Trạng thái
                                </th>
                                <th
                                    class="text-right p-3 text-sm font-medium text-slate-600 w-40"
                                >
                                    Thao tác
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr
                                v-for="t in templates"
                                :key="t.id"
                                class="hover:bg-slate-50"
                            >
                                <td class="p-3 font-medium text-slate-900">
                                    <div>{{ t.name }}</div>
                                    <div
                                        v-if="t.items && t.items.length"
                                        class="text-xs text-slate-500 mt-1"
                                    >
                                        {{ t.items.length }} khoản
                                    </div>
                                </td>
                                <td class="p-3 text-right">
                                    {{ formatMoney(t.base_salary) }}
                                </td>
                                <td class="p-3 text-right">
                                    {{ t.standard_work_units }}
                                </td>
                                <td class="p-3 text-right">
                                    {{ t.half_day_threshold_hours }}
                                </td>
                                <td class="p-3 text-right">
                                    {{ formatMoney(t.overtime_hourly_rate) }}
                                </td>
                                <td class="p-3">
                                    <span
                                        class="px-2 py-1 rounded text-xs"
                                        :class="
                                            t.status === 'active'
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-slate-100 text-slate-700'
                                        "
                                    >
                                        {{
                                            t.status === "active"
                                                ? "Đang dùng"
                                                : "Tắt"
                                        }}
                                    </span>
                                </td>
                                <td class="p-3 text-right">
                                    <button
                                        class="px-3 py-2 rounded-lg border border-slate-300 text-sm hover:bg-white"
                                        type="button"
                                        @click="openTemplateModal(t)"
                                    >
                                        Sửa
                                    </button>
                                    <button
                                        class="ml-2 px-3 py-2 rounded-lg border border-red-300 text-red-700 text-sm hover:bg-red-50"
                                        type="button"
                                        @click="removeTemplate(t)"
                                    >
                                        Xóa
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!templates.length">
                                <td
                                    colspan="7"
                                    class="p-6 text-center text-slate-500"
                                >
                                    Chưa có mẫu lương
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="toast.show" class="fixed top-4 right-4 z-50">
            <div
                class="p-4 rounded-lg shadow-lg max-w-sm"
                :class="
                    toast.type === 'success'
                        ? 'bg-green-100 border border-green-400 text-green-700'
                        : 'bg-red-100 border border-red-400 text-red-700'
                "
            >
                <div class="flex items-center gap-2">
                    <span>{{ toast.message }}</span>
                </div>
            </div>
        </div>

        <!-- Salary template modal -->
        <div
            v-if="templateModal"
            class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded-xl w-full max-w-3xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold">
                        {{
                            templateForm.id ? "Sửa mẫu lương" : "Tạo mẫu lương"
                        }}
                    </h2>
                    <button
                        class="text-slate-500 hover:text-slate-700"
                        @click="closeTemplateModal"
                    >
                        ✕
                    </button>
                </div>

                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12 md:col-span-6">
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Tên *</label
                        >
                        <input
                            v-model="templateForm.name"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Trạng thái</label
                        >
                        <select
                            v-model="templateForm.status"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        >
                            <option value="active">Đang dùng</option>
                            <option value="inactive">Tắt</option>
                        </select>
                    </div>

                    <div class="col-span-12 md:col-span-3">
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Lương cơ bản</label
                        >
                        <input
                            v-model.number="templateForm.base_salary"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Công chuẩn</label
                        >
                        <input
                            v-model.number="templateForm.standard_work_units"
                            type="number"
                            min="0"
                            step="0.5"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Nửa ngày (giờ)</label
                        >
                        <input
                            v-model.number="
                                templateForm.half_day_threshold_hours
                            "
                            type="number"
                            min="0"
                            step="0.25"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >OT/giờ</label
                        >
                        <input
                            v-model.number="templateForm.overtime_hourly_rate"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
                    </div>

                    <div class="col-span-12">
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Ghi chú</label
                        >
                        <textarea
                            v-model="templateForm.notes"
                            rows="2"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        ></textarea>
                    </div>

                    <div class="col-span-12">
                        <div
                            class="flex items-center justify-between gap-3 mt-2"
                        >
                            <div>
                                <div class="font-medium text-slate-900">
                                    Khoản cộng/trừ
                                </div>
                                <div class="text-xs text-slate-500 mt-1">
                                    Ví dụ: phụ cấp, thưởng, khấu trừ (mẫu)
                                </div>
                            </div>
                            <button
                                class="px-3 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50"
                                type="button"
                                @click="addTemplateItem"
                            >
                                + Thêm khoản
                            </button>
                        </div>

                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th
                                            class="text-left p-2 text-xs font-medium text-slate-600"
                                        >
                                            Loại
                                        </th>
                                        <th
                                            class="text-left p-2 text-xs font-medium text-slate-600"
                                        >
                                            Tên
                                        </th>
                                        <th
                                            class="text-right p-2 text-xs font-medium text-slate-600"
                                        >
                                            Số tiền
                                        </th>
                                        <th
                                            class="text-left p-2 text-xs font-medium text-slate-600"
                                        >
                                            Trạng thái
                                        </th>
                                        <th
                                            class="text-right p-2 text-xs font-medium text-slate-600 w-16"
                                        ></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <tr
                                        v-for="(it, idx) in templateForm.items"
                                        :key="idx"
                                    >
                                        <td class="p-2">
                                            <select
                                                v-model="it.type"
                                                class="w-full px-2 py-1 border border-slate-300 rounded-md text-sm"
                                            >
                                                <option value="allowance">
                                                    Phụ cấp
                                                </option>
                                                <option value="bonus">
                                                    Thưởng
                                                </option>
                                                <option value="deduction">
                                                    Khấu trừ
                                                </option>
                                                <option value="other">
                                                    Khác
                                                </option>
                                            </select>
                                        </td>
                                        <td class="p-2">
                                            <input
                                                v-model="it.name"
                                                type="text"
                                                class="w-full px-2 py-1 border border-slate-300 rounded-md text-sm"
                                            />
                                        </td>
                                        <td class="p-2 text-right">
                                            <input
                                                v-model.number="it.amount"
                                                type="number"
                                                step="0.01"
                                                class="w-full px-2 py-1 border border-slate-300 rounded-md text-sm text-right"
                                            />
                                        </td>
                                        <td class="p-2">
                                            <select
                                                v-model="it.status"
                                                class="w-full px-2 py-1 border border-slate-300 rounded-md text-sm"
                                            >
                                                <option value="active">
                                                    Đang dùng
                                                </option>
                                                <option value="inactive">
                                                    Tắt
                                                </option>
                                            </select>
                                        </td>
                                        <td class="p-2 text-right">
                                            <button
                                                class="px-2 py-1 rounded border border-red-300 text-red-700 text-sm hover:bg-red-50"
                                                type="button"
                                                @click="removeTemplateItem(idx)"
                                            >
                                                Xóa
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!templateForm.items.length">
                                        <td
                                            colspan="5"
                                            class="p-3 text-center text-sm text-slate-500"
                                        >
                                            Chưa có khoản nào
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6">
                    <button
                        class="px-4 py-2 rounded-lg border"
                        type="button"
                        @click="closeTemplateModal"
                    >
                        Bỏ qua
                    </button>
                    <button
                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
                        type="button"
                        :disabled="templateSaving"
                        @click="saveTemplate"
                    >
                        {{ templateSaving ? "Đang lưu..." : "Lưu" }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Holiday Modal -->
        <div
            v-if="holidayModal"
            class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded-xl w-full max-w-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">
                        {{ holidayForm.id ? "Sửa ngày lễ" : "Thêm ngày lễ" }}
                    </h3>
                    <button
                        class="text-slate-400 hover:text-slate-600"
                        @click="closeHolidayModal"
                    >
                        ✕
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Tên ngày lễ</label
                        >
                        <input
                            v-model="holidayForm.name"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg mb-2"
                            placeholder="Tết Nguyên Đán"
                        />
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="preset in holidayPresets"
                                :key="preset.name"
                                type="button"
                                class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 hover:bg-slate-50 hover:border-blue-400 transition-colors"
                                @click="applyHolidayPreset(preset)"
                            >
                                {{ preset.name }}
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-slate-700 mb-1"
                                >Từ ngày</label
                            >
                            <input
                                v-model="holidayForm.start_date"
                                type="date"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-slate-700 mb-1"
                                >Đến hết ngày</label
                            >
                            <input
                                v-model="holidayForm.end_date"
                                type="date"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                            />
                            <div class="text-xs text-slate-500 mt-1">
                                {{ dayRangeText }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Hệ số lương</label
                        >
                        <input
                            v-model.number="holidayForm.multiplier"
                            type="number"
                            step="0.1"
                            min="0"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input
                            v-model="holidayForm.paid_leave"
                            type="checkbox"
                            class="rounded"
                        />
                        <span class="text-sm text-slate-700"
                            >Nghỉ có lương (không trừ lương nếu nghỉ)</span
                        >
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"
                        >Ghi chú</label
                    >
                    <textarea
                        v-model="holidayForm.notes"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        rows="2"
                    ></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mt-6">
                <button
                    class="px-4 py-2 rounded-lg border"
                    @click="closeHolidayModal"
                >
                    Bỏ qua
                </button>
                <button
                    class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
                    :disabled="holidaySaving"
                    @click="saveHoliday"
                >
                    {{ holidaySaving ? "Đang lưu..." : "Lưu" }}
                </button>
            </div>
        </div>
    </div>

    <!-- Auto Generate Modal -->
    <div
        v-if="autoGenerateModal"
        class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
    >
        <div class="bg-white rounded-xl w-full max-w-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">
                    Tự động tạo ngày lễ Việt Nam
                </h3>
                <button
                    class="text-slate-400 hover:text-slate-600"
                    @click="closeAutoGenerateModal"
                >
                    ✕
                </button>
            </div>

            <div class="space-y-4">
                <div
                    class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-700"
                >
                    <div class="font-medium mb-1">
                        🎉 Tự động nhận diện ngày lễ
                    </div>
                    <ul class="space-y-1 text-xs">
                        <li>✓ Tết Dương lịch (01/01)</li>
                        <li>✓ Tết Nguyên Đán (tính theo âm lịch)</li>
                        <li>✓ Giỗ Tổ Hùng Vương (10/3 âm lịch)</li>
                        <li>✓ Ngày Giải phóng miền Nam (30/04)</li>
                        <li>✓ Ngày Quốc tế Lao động (01/05)</li>
                        <li>✓ Ngày Quốc khánh (02/09)</li>
                    </ul>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"
                        >Chọn năm</label
                    >
                    <input
                        v-model.number="autoGenerateYear"
                        type="number"
                        min="2024"
                        max="2030"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                    />
                    <div class="text-xs text-slate-500 mt-1">
                        Hệ thống sẽ tự động tính ngày Tết và các ngày âm lịch
                        cho năm này
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mt-6">
                <button
                    class="px-4 py-2 rounded-lg border"
                    @click="closeAutoGenerateModal"
                >
                    Bỏ qua
                </button>
                <button
                    class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
                    :disabled="autoGenerating"
                    @click="autoGenerateHolidays"
                >
                    {{ autoGenerating ? "Đang tạo..." : "Tạo ngày lễ" }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { computed, onMounted, ref } from "vue";
import employeeApi from "@/api/employeeApi";

export default {
    name: "PayrollSettings",
    setup() {
        const toast = ref({ show: false, type: "success", message: "" });
        const showToast = (message, type = "success") => {
            toast.value = { show: true, type, message };
            setTimeout(() => (toast.value.show = false), 2500);
        };

        const loading = ref(false);
        const saving = ref(false);

        const templatesLoading = ref(false);
        const templates = ref([]);

        const templateModal = ref(false);
        const templateSaving = ref(false);
        const templateForm = ref({
            id: null,
            name: "",
            base_salary: 0,
            standard_work_units: 26,
            half_day_threshold_hours: 4.5,
            overtime_hourly_rate: 0,
            status: "active",
            notes: null,
            items: [],
        });

        const form = ref({
            pay_cycle: "monthly",
            start_day: 26,
            end_day: 25,
            start_in_prev_month: true,
            pay_day: 5,
            default_recalculate_timekeeping: true,
            auto_generate_enabled: false,
        });

        const monthlyHint = computed(() => {
            if (form.value.pay_cycle !== "monthly") return "—";
            const from = String(form.value.start_day || 1).padStart(2, "0");
            const to = String(form.value.end_day || 1).padStart(2, "0");

            // Tự động xác định: nếu start_day > end_day thì chắc chắn là qua tháng
            // Ví dụ: 26 → 25 = tháng trước → tháng này
            // Ví dụ: 1 → 31 = cùng tháng
            const isAcrossMonths = form.value.start_day > form.value.end_day;

            return isAcrossMonths
                ? `Kỳ: ${from} (tháng trước) → ${to} (tháng này)`
                : `Kỳ: ${from} → ${to} (cùng tháng)`;
        });

        const load = async () => {
            loading.value = true;
            try {
                const res = await employeeApi.getPayrollSettings();
                const data = res?.data?.data;
                if (data) {
                    form.value = {
                        ...form.value,
                        pay_cycle: data.pay_cycle || "monthly",
                        start_day: Number(data.start_day ?? 26),
                        end_day: Number(data.end_day ?? 25),
                        start_in_prev_month: Boolean(
                            data.start_in_prev_month ?? true,
                        ),
                        pay_day: Number(data.pay_day ?? 5),
                        default_recalculate_timekeeping: Boolean(
                            data.default_recalculate_timekeeping ?? true,
                        ),
                        auto_generate_enabled: Boolean(
                            data.auto_generate_enabled ?? false,
                        ),
                    };
                }
            } catch {
                // ignore
            } finally {
                loading.value = false;
            }
        };

        const save = async () => {
            saving.value = true;
            try {
                await employeeApi.savePayrollSettings({
                    pay_cycle: form.value.pay_cycle,
                    start_day: form.value.start_day,
                    end_day: form.value.end_day,
                    start_in_prev_month: form.value.start_in_prev_month,
                    pay_day: form.value.pay_day,
                    default_recalculate_timekeeping:
                        form.value.default_recalculate_timekeeping,
                    auto_generate_enabled: form.value.auto_generate_enabled,
                });
                showToast("Đã lưu thiết lập tính lương");
            } catch (e) {
                showToast(
                    e?.response?.data?.message || "Lưu thất bại",
                    "error",
                );
            } finally {
                saving.value = false;
            }
        };

        const formatMoney = (v) => {
            const n = Number(v || 0);
            return n.toLocaleString("vi-VN");
        };

        const loadTemplates = async () => {
            templatesLoading.value = true;
            try {
                const res = await employeeApi.getSalaryTemplates({});
                templates.value = res?.data?.data || [];
            } catch {
                templates.value = [];
            } finally {
                templatesLoading.value = false;
            }
        };

        const openTemplateModal = (t) => {
            if (!t) {
                templateForm.value = {
                    id: null,
                    name: "",
                    base_salary: 0,
                    standard_work_units: 26,
                    half_day_threshold_hours: 4.5,
                    overtime_hourly_rate: 0,
                    status: "active",
                    notes: null,
                    items: [],
                };
            } else {
                templateForm.value = {
                    id: t.id,
                    name: t.name || "",
                    base_salary: Number(t.base_salary || 0),
                    standard_work_units: Number(t.standard_work_units || 0),
                    half_day_threshold_hours: Number(
                        t.half_day_threshold_hours || 0,
                    ),
                    overtime_hourly_rate: Number(t.overtime_hourly_rate || 0),
                    status: t.status || "active",
                    notes: t.notes || null,
                    items: (t.items || []).map((x) => ({
                        type: x.type || "allowance",
                        name: x.name || "",
                        amount: Number(x.amount || 0),
                        status: x.status || "active",
                        notes: x.notes || null,
                    })),
                };
            }
            templateModal.value = true;
        };

        const closeTemplateModal = () => {
            templateModal.value = false;
        };

        const addTemplateItem = () => {
            templateForm.value.items.push({
                type: "allowance",
                name: "",
                amount: 0,
                status: "active",
                notes: null,
            });
        };

        const removeTemplateItem = (idx) => {
            templateForm.value.items.splice(idx, 1);
        };

        const saveTemplate = async () => {
            if (
                !templateForm.value.name ||
                !String(templateForm.value.name).trim()
            ) {
                showToast("Vui lòng nhập tên mẫu", "error");
                return;
            }

            templateSaving.value = true;
            try {
                const payload = {
                    name: String(templateForm.value.name).trim(),
                    base_salary: templateForm.value.base_salary,
                    standard_work_units: templateForm.value.standard_work_units,
                    half_day_threshold_hours:
                        templateForm.value.half_day_threshold_hours,
                    overtime_hourly_rate:
                        templateForm.value.overtime_hourly_rate,
                    status: templateForm.value.status || "active",
                    notes: templateForm.value.notes || null,
                    items: (templateForm.value.items || [])
                        .filter((it) => it && String(it.name || "").trim())
                        .map((it) => ({
                            type: it.type || "other",
                            name: String(it.name).trim(),
                            amount: it.amount ?? 0,
                            status: it.status || "active",
                            notes: it.notes || null,
                        })),
                };

                if (templateForm.value.id) {
                    await employeeApi.updateSalaryTemplate(
                        templateForm.value.id,
                        payload,
                    );
                    showToast("Đã cập nhật mẫu lương");
                } else {
                    await employeeApi.createSalaryTemplate(payload);
                    showToast("Đã tạo mẫu lương");
                }

                templateModal.value = false;
                await loadTemplates();
            } catch (e) {
                showToast(
                    e?.response?.data?.message || "Lưu mẫu lương thất bại",
                    "error",
                );
            } finally {
                templateSaving.value = false;
            }
        };

        const removeTemplate = async (t) => {
            if (!t?.id) return;
            if (!confirm(`Xóa mẫu lương "${t.name}"?`)) return;
            try {
                await employeeApi.deleteSalaryTemplate(t.id);
                showToast("Đã xóa mẫu lương");
                await loadTemplates();
            } catch (e) {
                showToast(
                    e?.response?.data?.message || "Xóa thất bại",
                    "error",
                );
            }
        };

        // ===== HOLIDAYS MANAGEMENT =====
        const holidaysLoading = ref(false);
        const holidays = ref([]);
        const holidayModal = ref(false);
        const holidaySaving = ref(false);
        const holidayForm = ref({
            id: null,
            start_date: "",
            end_date: "",
            name: "",
            multiplier: 2,
            paid_leave: true,
            notes: "",
        });

        const autoGenerateModal = ref(false);
        const autoGenerateYear = ref(new Date().getFullYear());
        const autoGenerating = ref(false);

        // Holiday presets with auto-calculated dates
        const currentYear = new Date().getFullYear();
        const holidayPresets = [
            {
                name: "Tết Dương lịch",
                start_date: `${currentYear}-01-01`,
                end_date: `${currentYear}-01-01`,
                multiplier: 2,
            },
            {
                name: "Tết Nguyên Đán",
                start_date: "2026-02-16", // Giao thừa
                end_date: "2026-02-20", // Mùng 4
                multiplier: 2,
                notes: "Từ giao thừa đến mùng 4 Tết",
            },
            {
                name: "Giỗ Tổ Hùng Vương",
                start_date: "2026-04-27",
                end_date: "2026-04-27",
                multiplier: 2,
            },
            {
                name: "Ngày Giải phóng miền Nam",
                start_date: "2026-04-30",
                end_date: "2026-04-30",
                multiplier: 2,
            },
            {
                name: "Ngày Quốc tế Lao động",
                start_date: "2026-05-01",
                end_date: "2026-05-01",
                multiplier: 2,
            },
            {
                name: "Ngày Quốc khánh",
                start_date: "2026-09-02",
                end_date: "2026-09-02",
                multiplier: 2,
            },
        ];

        const dayRangeText = computed(() => {
            if (!holidayForm.value.start_date || !holidayForm.value.end_date) {
                return "";
            }
            const start = new Date(holidayForm.value.start_date);
            const end = new Date(holidayForm.value.end_date);
            const days = Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1;
            return `(${days} ngày)`;
        });

        const loadHolidays = async () => {
            holidaysLoading.value = true;
            try {
                const res = await employeeApi.getHolidays({});
                holidays.value = res?.data?.data || [];
            } catch {
                holidays.value = [];
            } finally {
                holidaysLoading.value = false;
            }
        };

        const applyHolidayPreset = (preset) => {
            holidayForm.value.name = preset.name;
            holidayForm.value.start_date = preset.start_date;
            holidayForm.value.end_date = preset.end_date;
            holidayForm.value.multiplier = preset.multiplier || 2;
            holidayForm.value.notes = preset.notes || "";
        };

        const openHolidayModal = (h) => {
            if (!h) {
                const today = new Date().toISOString().split("T")[0];
                holidayForm.value = {
                    id: null,
                    start_date: today,
                    end_date: today,
                    name: "",
                    multiplier: 2,
                    paid_leave: true,
                    notes: "",
                };
            } else {
                holidayForm.value = {
                    id: h.id,
                    start_date: h.start_date || h.holiday_date || "",
                    end_date: h.end_date || h.holiday_date || "",
                    name: h.name || "",
                    multiplier: Number(h.multiplier || 2),
                    paid_leave: Boolean(h.paid_leave ?? true),
                    notes: h.notes || "",
                };
            }
            holidayModal.value = true;
        };

        const closeHolidayModal = () => {
            holidayModal.value = false;
        };

        const saveHoliday = async () => {
            if (
                !holidayForm.value.start_date ||
                !holidayForm.value.end_date ||
                !holidayForm.value.name
            ) {
                showToast("Vui lòng nhập đầy đủ thông tin", "error");
                return;
            }

            holidaySaving.value = true;
            try {
                const start = new Date(holidayForm.value.start_date);
                const end = new Date(holidayForm.value.end_date);

                // Nếu chỉ 1 ngày hoặc đang sửa
                if (start.getTime() === end.getTime() || holidayForm.value.id) {
                    const payload = {
                        holiday_date: holidayForm.value.start_date,
                        name: holidayForm.value.name,
                        multiplier: holidayForm.value.multiplier || 1,
                        paid_leave: Boolean(holidayForm.value.paid_leave),
                        notes: holidayForm.value.notes || null,
                    };

                    if (holidayForm.value.id) {
                        await employeeApi.updateHoliday(
                            holidayForm.value.id,
                            payload,
                        );
                        showToast("Đã cập nhật ngày lễ");
                    } else {
                        await employeeApi.createHoliday(payload);
                        showToast("Đã thêm ngày lễ");
                    }
                } else {
                    // Tạo nhiều ngày cho khoảng thời gian
                    const current = new Date(start);
                    let created = 0;

                    while (current <= end) {
                        const dateStr = current.toISOString().split("T")[0];
                        const dayNumber =
                            Math.round(
                                (current - start) / (1000 * 60 * 60 * 24),
                            ) + 1;
                        const totalDays =
                            Math.round((end - start) / (1000 * 60 * 60 * 24)) +
                            1;

                        let dayName = holidayForm.value.name;
                        if (totalDays > 1) {
                            dayName = `${holidayForm.value.name} (Ngày ${dayNumber}/${totalDays})`;
                        }

                        try {
                            await employeeApi.createHoliday({
                                holiday_date: dateStr,
                                name: dayName,
                                multiplier: holidayForm.value.multiplier || 1,
                                paid_leave: Boolean(
                                    holidayForm.value.paid_leave,
                                ),
                                notes: holidayForm.value.notes || null,
                            });
                            created++;
                        } catch {
                            // Bỏ qua nếu ngày đã tồn tại
                        }

                        current.setDate(current.getDate() + 1);
                    }

                    showToast(`Đã tạo ${created} ngày lễ`);
                }

                holidayModal.value = false;
                await loadHolidays();
            } catch (e) {
                showToast(
                    e?.response?.data?.message || "Lưu thất bại",
                    "error",
                );
            } finally {
                holidaySaving.value = false;
            }
        };

        const deleteHoliday = async (h) => {
            if (!h?.id) return;
            if (!confirm(`Xóa ngày lễ "${h.name}"?`)) return;
            try {
                await employeeApi.deleteHoliday(h.id);
                showToast("Đã xóa ngày lễ");
                await loadHolidays();
            } catch (e) {
                showToast(
                    e?.response?.data?.message || "Xóa thất bại",
                    "error",
                );
            }
        };

        const openAutoGenerateModal = () => {
            autoGenerateYear.value = new Date().getFullYear();
            autoGenerateModal.value = true;
        };

        const closeAutoGenerateModal = () => {
            autoGenerateModal.value = false;
        };

        const autoGenerateHolidays = async () => {
            autoGenerating.value = true;
            try {
                const res = await employeeApi.autoGenerateHolidays(
                    autoGenerateYear.value,
                );
                const data = res?.data?.data;
                showToast(
                    `Đã tạo ${data?.created || 0} ngày lễ, bỏ qua ${data?.skipped || 0} ngày đã tồn tại`,
                );
                autoGenerateModal.value = false;
                await loadHolidays();
            } catch (e) {
                showToast(
                    e?.response?.data?.message || "Tạo thất bại",
                    "error",
                );
            } finally {
                autoGenerating.value = false;
            }
        };

        const formatDate = (dateStr) => {
            if (!dateStr) return "—";
            const d = new Date(dateStr);
            const day = String(d.getDate()).padStart(2, "0");
            const month = String(d.getMonth() + 1).padStart(2, "0");
            const year = d.getFullYear();
            return `${day}/${month}/${year}`;
        };

        onMounted(async () => {
            await load();
            await loadTemplates();
            await loadHolidays();
        });

        return {
            toast,
            loading,
            saving,
            form,
            monthlyHint,
            save,
            formatMoney,
            templatesLoading,
            templates,
            templateModal,
            templateSaving,
            templateForm,
            openTemplateModal,
            closeTemplateModal,
            addTemplateItem,
            removeTemplateItem,
            saveTemplate,
            removeTemplate,
            // Holidays
            holidaysLoading,
            holidays,
            holidayModal,
            holidaySaving,
            holidayForm,
            holidayPresets,
            dayRangeText,
            openHolidayModal,
            closeHolidayModal,
            applyHolidayPreset,
            saveHoliday,
            deleteHoliday,
            autoGenerateModal,
            autoGenerateYear,
            autoGenerating,
            openAutoGenerateModal,
            closeAutoGenerateModal,
            autoGenerateHolidays,
            formatDate,
        };
    },
};
</script>
