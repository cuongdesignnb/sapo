<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    categories: Array,
    show: Boolean,
});

const emit = defineEmits(['close']);

const newForm = useForm({ name: '', description: '' });
const editingId = ref(null);
const editForm = useForm({ name: '', description: '' });

const startEdit = (cat) => {
    editingId.value = cat.id;
    editForm.name = cat.name;
    editForm.description = cat.description || '';
};

const cancelEdit = () => { editingId.value = null; };

const submitNew = () => {
    newForm.post('/settings/categories', {
        preserveScroll: true,
        onSuccess: () => newForm.reset(),
    });
};

const submitEdit = (id) => {
    editForm.put(`/settings/categories/${id}`, {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
};

const deleteCategory = (cat) => {
    if (confirm(`Xóa nhóm hàng "${cat.name}"?`)) {
        useForm({}).delete(`/settings/categories/${cat.id}`, { preserveScroll: true });
    }
};
</script>

<template>
<div v-if="show" class="fixed inset-0 z-[999] flex items-center justify-center bg-black/40" @click.self="emit('close')">
    <div class="bg-white rounded-lg shadow-2xl w-[680px] max-h-[85vh] flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <h2 class="text-lg font-bold text-gray-800">Quản lý Nhóm hàng</h2>
            <button @click="emit('close')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- Add new -->
        <div class="px-6 py-3 bg-blue-50 border-b flex gap-3 items-end">
            <div class="flex-1">
                <label class="block text-xs font-bold text-gray-500 mb-1">Tên nhóm hàng mới</label>
                <input v-model="newForm.name" @keyup.enter="submitNew" placeholder="Ví dụ: Điện thoại, Phụ kiện..." class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <button @click="submitNew" :disabled="!newForm.name || newForm.processing" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded text-sm font-medium disabled:opacity-50 whitespace-nowrap">
                + Thêm mới
            </button>
        </div>

        <!-- List -->
        <div class="flex-1 overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="text-left px-6 py-2.5 text-xs font-bold text-gray-500 uppercase">Tên nhóm hàng</th>
                        <th class="text-center px-4 py-2.5 text-xs font-bold text-gray-500 uppercase w-28">Số SP</th>
                        <th class="text-right px-6 py-2.5 text-xs font-bold text-gray-500 uppercase w-32">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="cat in categories" :key="cat.id" class="hover:bg-gray-50/80 group">
                        <td class="px-6 py-3">
                            <template v-if="editingId === cat.id">
                                <input v-model="editForm.name" @keyup.enter="submitEdit(cat.id)" @keyup.escape="cancelEdit" class="w-full border border-blue-400 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 outline-none" autofocus>
                            </template>
                            <template v-else>
                                <span class="text-gray-800 font-medium">{{ cat.name }}</span>
                            </template>
                        </td>
                        <td class="text-center px-4 py-3">
                            <span class="bg-gray-100 text-gray-600 text-xs font-bold px-2.5 py-1 rounded-full">{{ cat.products_count }}</span>
                        </td>
                        <td class="text-right px-6 py-3">
                            <template v-if="editingId === cat.id">
                                <button @click="submitEdit(cat.id)" class="text-blue-600 hover:text-blue-800 text-xs font-bold mr-2">Lưu</button>
                                <button @click="cancelEdit" class="text-gray-500 hover:text-gray-700 text-xs font-bold">Hủy</button>
                            </template>
                            <template v-else>
                                <button @click="startEdit(cat)" class="text-blue-600 hover:text-blue-800 text-xs font-bold mr-3 opacity-0 group-hover:opacity-100 transition-opacity">Sửa</button>
                                <button @click="deleteCategory(cat)" class="text-red-500 hover:text-red-700 text-xs font-bold opacity-0 group-hover:opacity-100 transition-opacity">Xóa</button>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="!categories?.length">
                        <td colspan="3" class="px-6 py-8 text-center text-gray-400">Chưa có nhóm hàng nào</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="px-6 py-3 border-t bg-gray-50 flex justify-between items-center">
            <span class="text-xs text-gray-500">Tổng: <strong>{{ categories?.length || 0 }}</strong> nhóm hàng</span>
            <button @click="emit('close')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-1.5 rounded text-sm font-medium">Đóng</button>
        </div>
    </div>
</div>
</template>
