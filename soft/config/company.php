<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Company Information
    |--------------------------------------------------------------------------
    |
    | Thông tin công ty sẽ được hiển thị trên hóa đơn và các báo cáo
    |
    */

    'name' => env('COMPANY_NAME', 'Công ty TNHH ABC'),
    
    'address' => env('COMPANY_ADDRESS', '123 Đường ABC, Quận XYZ, TP.HCM'),
    
    'phone' => env('COMPANY_PHONE', '0901 234 567'),
    
    'email' => env('COMPANY_EMAIL', 'info@company.com'),
    
    'website' => env('COMPANY_WEBSITE', 'www.company.com'),
    
    'tax_code' => env('COMPANY_TAX_CODE', '0123456789'),
    
    /*
    |--------------------------------------------------------------------------
    | Logo Settings
    |--------------------------------------------------------------------------
    |
    | Đường dẫn tới logo công ty (relative to public folder)
    | Ví dụ: 'images/logo.png' sẽ tìm file tại public/images/logo.png
    |
    */
    
    'logo' => env('COMPANY_LOGO', 'images/logo.png'),
    
    /*
    |--------------------------------------------------------------------------
    | Contact & Support
    |--------------------------------------------------------------------------
    |
    | Thông tin liên hệ và hỗ trợ khách hàng
    |
    */
    
    'hotline' => env('COMPANY_HOTLINE', '1900 1234'),
    
    'support_email' => env('COMPANY_SUPPORT_EMAIL', 'support@company.com'),
    
    'return_policy' => env('COMPANY_RETURN_POLICY', 'Trong vòng 7 ngày kể từ ngày mua hàng'),
    
    /*
    |--------------------------------------------------------------------------
    | Bank Information (Optional)
    |--------------------------------------------------------------------------
    |
    | Thông tin ngân hàng để hiển thị trên hóa đơn (nếu cần)
    |
    */
    
    'bank_name' => env('COMPANY_BANK_NAME', null),
    
    'bank_account' => env('COMPANY_BANK_ACCOUNT', null),
    
    'bank_account_name' => env('COMPANY_BANK_ACCOUNT_NAME', null),
];