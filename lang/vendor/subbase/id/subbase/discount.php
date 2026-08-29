<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filament Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during Filament for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    // Navigation
    'navigation_label' => 'Diskon',
    'navigation_group' => 'Langganan',

    'create_discount' => 'Buat Diskon',
    'edit_discount' => 'Edit Diskon',

    // Table Columns
    'name' => 'Nama',
    'code' => 'Kode',
    'type' => 'Tipe',
    'value' => 'Nilai',
    'currency' => 'Mata Uang',
    'min_amount' => 'Jumlah Minimum',
    'max_uses' => 'Maksimal Penggunaan',
    'used_count' => 'Jumlah Digunakan',
    'starts_at' => 'Tanggal Mulai',
    'expires_at' => 'Tanggal Kadaluarsa',
    'is_active' => 'Aktif',
    'priority' => 'Prioritas',
    'description' => 'Deskripsi',

    // Form Fields
    'basic_information' => 'Informasi Dasar',
    'discount_value' => 'Nilai Diskon',
    'usage_limits' => 'Penggunaan & Jadwal',
    'applicability' => 'Penerapan',
    'min_amount_hint' => 'Kosongkan jika tidak ada minimum',
    'priority_hint' => 'Angka lebih tinggi = diterapkan lebih dulu jika ada beberapa diskon yang cocok',
    'applies_to' => 'Berlaku Untuk',
    'applies_to_plans' => 'Paket',
    'applies_to_features' => 'Fitur',
    'applicable_plans' => 'Paket yang Berlaku',
    'applicable_features' => 'Fitur yang Berlaku',

    // Types
    'type_percentage' => 'Persentase',
    'type_fixed' => 'Jumlah Tetap',

    // Messages
    'discount_applied' => 'Diskon berhasil diterapkan',
    'discount_invalid' => 'Kode diskon tidak valid',
    'discount_expired' => 'Diskon ini sudah kadaluarsa',
    'discount_not_active' => 'Diskon ini tidak aktif',
    'discount_max_uses_reached' => 'Diskon ini telah mencapai batas penggunaan maksimal',
    'discount_min_amount_not_met' => 'Jumlah minimum tidak terpenuhi untuk diskon ini',
];
