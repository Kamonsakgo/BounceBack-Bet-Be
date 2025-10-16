@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-5 py-8">
    <h1 class="text-2xl font-medium mb-4">แดชบอร์ดโปรโมชัน</h1>

    <!-- เมนูการจัดการ -->
    <div class="mt-8">
        <h2 class="text-lg font-medium mb-4">เมนูการจัดการ</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <a href="/promotions/test" class="block p-4 bg-white dark:bg-[#161615] border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-lg hover:bg-gray-50 dark:hover:bg-[#1a1a1a]">
                <div class="text-center">
                    <div class="w-8 h-8 bg-red-100 dark:bg-red-900 rounded-full mx-auto mb-2 flex items-center justify-center">
                        <span class="text-red-600 dark:text-red-400 text-sm">🧪</span>
                    </div>
                    <span class="text-sm font-medium">ทดสอบโปรโมชัน</span>
                </div>
            </a>
            <a href="/promotions/create" class="block p-4 bg-white dark:bg-[#161615] border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-lg hover:bg-gray-50 dark:hover:bg-[#1a1a1a]">
                <div class="text-center">
                    <div class="w-8 h-8 bg-orange-100 dark:bg-orange-900 rounded-full mx-auto mb-2 flex items-center justify-center">
                        <span class="text-orange-600 dark:text-orange-400 text-sm">➕</span>
                    </div>
                    <span class="text-sm font-medium">สร้างโปรโมชัน</span>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection


