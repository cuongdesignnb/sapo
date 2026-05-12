<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: { type: Boolean, default: false },
    initialName: { type: String, default: '' },
});

const emit = defineEmits(['close', 'created']);

const creating = ref(false);
const errors = ref({});

const form = ref({
    name: '',
    phone: '',
    email: '',
    address: '',
    tax_code: '',
    note: '',
});

const reset = () => {
    form.value = {
        name: props.initialName || '',
        phone: '',
        email: '',
        address: '',
        tax_code: '',
        note: '',
    };
    errors.value = {};
};

watch(() => props.show, (val) => { if (val) reset(); });

const submit = async () => {
    if (!form.value.name.trim()) {
        errors.value = { name: 'Tên nhà cung cấp là bắt buộc' };
        return;
    }
    creating.value = true;
    errors.value = {};
    try {
        const res = await axios.post('/api/suppliers/quick-store', {
            name: form.value.name.trim(),
            phone: form.value.phone?.trim() || null,
            email: form.value.email?.trim() || null,
            address: form.value.address?.trim() || null,
            tax_code: form.value.tax_code?.trim() || null,
            note: form.value.note?.trim() || null,
        });
        if (res.data?.success && res.data.supplier) {
            emit('created', res.data.supplier);
            emit('close');
        }
    } catch (e) {
        if (e.response?.status === 422 && e.response.data?.errors) {
            const out = {};
            for (const [key, msgs] of Object.entries(e.response.data.errors)) {
                out[key] = Array.isArray(msgs) ? msgs[0] : msgs;
            }
            errors.value = out;
        } else {
            alert(e.response?.data?.message || 'Lỗi khi tạo nhà cung cấp.');
        }
    } finally {
        creating.value = false;
    }
};

const close = () => emit('close');
</script>

<template>
    <div v-if="show" class="fixed inset-0 bg-black/40 z-[100] flex items-center justify-center p-4 font-sans" @click.self="close">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-bold text-gray-800">Thêm nhà cung cấp</h2>
                <button @click="close" type="button" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <form @submit.prevent="submit" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tên nhà cung cấp <span class="text-red-500">*</span></label>
                    <input v-model="form.name" type="text" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-green-500 focus:border-green-500 outline-none" placeholder="Nhập tên nhà cung cấp" autofocus />
                    <span v-if="errors.name" class="text-red-500 text-xs mt-1 block">{{ errors.name }}</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Điện thoại</label>
                        <input v-model="form.phone" type="text" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-green-500 focus:border-green-500 outline-none" />
                        <span v-if="errors.phone" class="text-red-500 text-xs mt-1 block">{{ errors.phone }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                        <input v-model="form.email" type="email" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-green-500 focus:border-green-500 outline-none" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Mã số thuế</label>
                    <input v-model="form.tax_code" type="text" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-green-500 focus:border-green-500 outline-none" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Địa chỉ</label>
                    <input v-model="form.address" type="text" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-green-500 focus:border-green-500 outline-none" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Ghi chú</label>
                    <textarea v-model="form.note" rows="2" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-green-500 focus:border-green-500 outline-none resize-none"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="close" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50 font-medium text-sm">Hủy</button>
                    <button type="submit" :disabled="creating || !form.name.trim()" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 font-medium text-sm disabled:opacity-50">{{ creating ? 'Đang lưu...' : 'Lưu' }}</button>
                </div>
            </form>
        </div>
    </div>
</template>
