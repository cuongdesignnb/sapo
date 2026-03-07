@extends('layouts.master')

@section('title', 'User chấm công chưa map')

@section('content')
<div class="container-fluid">
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="p-4 border-b flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">User chấm công chưa map</h1>
                <p class="text-sm text-gray-600 mt-1">
                    Danh sách mã chấm công (device_user_id) đang có log nhưng chưa gán được nhân viên.
                </p>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-sm text-gray-600">
                    Tổng: <span class="font-semibold text-gray-900">{{ $rows->count() }}</span>
                </div>

                @if(Auth::check() && Auth::user()->hasPermission('staff.manage'))
                    <form method="POST" action="{{ route('employees.attendance.unmapped-users.refresh-mapping') }}" class="flex items-center gap-2">
                        @csrf
                        <input type="number" name="device_id" placeholder="device_id (optional)" class="w-[160px] px-3 py-2 border rounded-md text-sm" />
                        <input type="text" name="device_user_ids" placeholder="device_user_id (vd: 12,15,16)" class="w-[240px] px-3 py-2 border rounded-md text-sm" />
                        <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 text-sm">
                            Refresh mapping
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="p-4">
            <div class="overflow-auto">
                <table class="min-w-[800px] w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 text-sm font-medium text-gray-600">device_user_id</th>
                            <th class="text-left p-3 text-sm font-medium text-gray-600">Số log</th>
                            <th class="text-left p-3 text-sm font-medium text-gray-600">Lần cuối</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($rows as $r)
                            <tr>
                                <td class="p-3 text-sm font-semibold text-gray-900">{{ $r->device_user_id }}</td>
                                <td class="p-3 text-sm text-gray-800">{{ $r->log_count }}</td>
                                <td class="p-3 text-sm text-gray-800">{{ $r->last_punch }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-6 text-center text-gray-500">Không có dữ liệu</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 text-sm text-gray-700">
                <div class="font-semibold mb-1">Cách xử lý để map được</div>
                <ol class="list-decimal ml-5 space-y-1">
                    <li>Vào danh sách nhân viên và cập nhật trường <span class="font-mono">attendance_code</span> đúng bằng <span class="font-mono">device_user_id</span>.</li>
                    <li>Chạy chức năng “refresh mapping” (admin) hoặc đợi tool C# gọi endpoint refresh-mapping.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection
