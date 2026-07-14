# Kanzza Frozen Food Backend

Backend REST API untuk aplikasi mobile Kanzza Frozen Food. Project menggunakan
Laravel, Sanctum, Eloquent, MySQL/MariaDB, dan Midtrans Snap.

## Struktur MVC

Backend memakai alur Laravel MVC yang sederhana:

```text
Route -> Controller -> Model -> Database
                   -> API Resource -> JSON
```

Folder utama:

```text
src/app/
├── Enums/                 Nilai tetap seperti role dan status
├── Exceptions/            Exception khusus payment gateway
├── Http/
│   ├── Controllers/       Menerima request dan mengatur response
│   ├── Middleware/        Pemeriksaan role dan status user
│   ├── Requests/          Validasi input API
│   └── Resources/         Bentuk output JSON (view untuk REST API)
├── Models/                Relasi, query scope, dan logika bisnis database
├── Providers/             Konfigurasi Laravel
└── Services/
    └── MidtransClient.php Integrasi HTTP ke Midtrans
```

Tidak ada lagi lapisan `Application`, `Domain Repository`, interface
repository, atau `Infrastructure Repository`. Form Request dan API Resource
tetap dipakai karena keduanya merupakan fitur Laravel yang memisahkan validasi
input dan format output dari controller. Karena backend ini REST API, view HTML
digantikan oleh API Resource.

## Pembagian tanggung jawab

- Controller menangani request, authorization sederhana, pemanggilan model, dan
  response.
- Model menangani relasi Eloquent, filter query, transaksi order, stok, dan
  perubahan status.
- Form Request menangani seluruh aturan validasi input.
- API Resource menjaga kontrak JSON agar tetap konsisten untuk Flutter.
- `MidtransClient` menjadi satu-satunya service khusus karena berkomunikasi
  dengan layanan eksternal.

## Menjalankan project

```bash
docker compose up -d --build
docker exec php_atar composer install
docker exec php_atar php artisan migrate --seed
```

API lokal tersedia melalui `http://localhost:24/api/v1`.

## Ongkir delivery

Ongkir dihitung backend dengan rumus:

```text
ongkir = jarak rute (km) × BUSINESS_SHIPPING_RATE_PER_KM
```

Tarif default adalah Rp5.000/km. Flutter mengirim `distance_km` hasil
perhitungan rute, lalu backend menghitung ulang nominal ongkir dan mengabaikan
nilai `shipping_cost` dari client. Backend juga memastikan jarak tersebut
tidak lebih pendek dari jarak garis lurus koordinat toko ke alamat customer.

Konfigurasi lokasi dan tarif:

```env
BUSINESS_STORE_LATITUDE=-6.282288
BUSINESS_STORE_LONGITUDE=106.554848
BUSINESS_SHIPPING_RATE_PER_KM=5000
BUSINESS_SHIPPING_MAX_DISTANCE_KM=100
BUSINESS_SHIPPING_DISTANCE_TOLERANCE_KM=0.1
```

Alamat delivery wajib memiliki `latitude` dan `longitude`. Pickup selalu
memiliki ongkir nol.

## Pembayaran COD

Customer memilih COD dengan mengirim `payment_method: "cash"` pada
`POST /api/v1/orders`. COD hanya tersedia untuk `delivery`; pesanan langsung
berstatus `confirmed`, sedangkan pembayaran tetap `unpaid`. Stok tetap
direservasi sejak order dibuat dan order COD tidak boleh membuat pembayaran
Midtrans.

Order COD dapat diproses dan ditugaskan kepada driver walaupun belum dibayar.
Saat barang sudah diterima customer, driver menyelesaikan pengiriman dengan:

```json
{
  "status": "delivered",
  "payment_received": true,
  "notes": "Pesanan tiba dan pembayaran COD diterima"
}
```

Backend menolak status `delivered` untuk COD jika `payment_received` bukan
`true`. Jika konfirmasi valid, perubahan delivery menjadi `delivered`, payment
menjadi `paid`, pencatatan waktu/driver penerima uang, dan perubahan reservasi
stok menjadi penjualan dilakukan dalam satu transaksi database. Order yang
sudah ditugaskan kepada driver tidak dapat dibatalkan melalui endpoint cancel.

## Notification Midtrans

Atur Payment Notification URL pada dashboard Midtrans ke:

```text
https://kanza.djncloud.my.id/api/v1/payments/midtrans/notification
```

Endpoint memverifikasi `signature_key` Midtrans. Status `cancel`, `expire`,
`deny`, atau `failure` akan membatalkan order dan mengembalikan stok yang
sebelumnya direservasi. Proses pengembalian stok bersifat idempotent sehingga
notification yang dikirim ulang tidak menggandakan stok.

## Pemeriksaan

```bash
docker exec php_atar php artisan test
docker exec php_atar php artisan route:list
docker exec php_atar vendor/bin/pint --test
docker exec php_atar composer audit
```
