<?php

return [
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'email' => 'Format :attribute tidak valid.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'boolean' => 'Pilihan :attribute tidak valid.',
    'exists' => ':attribute yang dipilih tidak tersedia.',
    'min' => ['string' => ':attribute minimal :min karakter.', 'numeric' => ':attribute minimal :min.'],
    'date_format' => 'Format :attribute tidak valid.',
    'enum' => 'Pilihan :attribute tidak valid.',
    'max' => ['string' => ':attribute maksimal :max karakter.'],
    'size' => ['string' => ':attribute harus terdiri dari :size karakter.'],
    'attributes' => ['email' => 'Email', 'password' => 'Kata sandi', 'name' => 'Nama lengkap', 'token' => 'Undangan'],
];
