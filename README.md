# Dokumentasi Aplikasi Inventory PT Sinar Nusantara

Dokumen ini menjelaskan aplikasi persediaan berdasarkan source code, route,
migration, model, dan view yang ada di proyek. Nama tabel yang disebut sebagai
tabel utama adalah tabel domain inventory; Laravel juga memiliki tabel
pendukung autentikasi, cache, queue, dan session.

## 1. Overview aplikasi

| Aspek | Keterangan |
|---|---|
| Nama aplikasi | Inventory Management PT Sinar Nusantara |
| Tujuan bisnis | Mengelola master produk, kategori, gudang, pelanggan, transaksi barang masuk, penjualan, transfer stok, pembatalan, dan laporan persediaan |
| Framework backend | Laravel Framework 13.35.0 pada lingkungan proyek yang diperiksa (requirement Composer: Laravel `^13.17`) |
| Bahasa backend | PHP `^8.3` |
| Database | Konfigurasi mendukung SQLite, MySQL/MariaDB, PostgreSQL, dan SQL Server. `.env.example` menetapkan SQLite sebagai default; database aktual ditentukan oleh konfigurasi environment |
| Tampilan | Blade, layout dan halaman aplikasi di `src/`, serta beberapa halaman autentikasi/profil Laravel di `resources/views/` |
| Frontend tooling | Vite 8, JavaScript, CSS; dependency frontend juga memuat Tailwind CSS dan Alpine.js |
| Pengujian | PHPUnit 12; konfigurasi test menggunakan SQLite in-memory |

Aplikasi menggunakan struktur Laravel yang dikustomisasi, bukan framework
terpisah. Beberapa konvensi project berbeda dari skeleton Laravel standar:

- Route web berada di `app/routes/web.php`, lalu dimuat dari `bootstrap/app.php`.
- Migration dan seeder aplikasi berada di `app/database/`.
- Halaman utama aplikasi berada di `src/pages/`, layout bersama berada di
  `src/layouts/`, dan aset berada di `src/assets/`.
- `PageRouter` dapat mendaftarkan route GET berdasarkan file Blade.
- `Frontend::render()` memilih view, meneruskan data/layout, membuat payload
  frontend dan menyimpannya di cache project.
- Terdapat command kustom `make:migration` (`MakeMigrationCommand`) yang
  menyesuaikan nama tabel dan lokasi migration.
- Terdapat `CrudGenerateCommand` dengan signature `crud:generate`, yang
  membaca metadata tabel aktif dan membuat model, controller, route resource,
  serta view index/create/edit dari stub.
- Terdapat `MakeAllCommand` (`make:all`) untuk scaffolding komponen Laravel
  seperti model, migration, factory, seeder, Form Request, controller, dan
  route.
- `FrontendGenerateCommand` (`frontend:generate`) membuat manifest halaman
  frontend; project juga memiliki command build/preview pendukung.

## 2. Arsitektur sistem

### 2.1 Alur request

1. Browser mengirim request HTTP ke URL aplikasi.
2. Laravel menjalankan middleware `web` (termasuk session dan proteksi CSRF).
   `MinifyHtmlMiddleware` ditambahkan ke grup web.
3. Route dipilih dari definisi eksplisit di `app/routes/web.php` atau route GET
   yang dibuat `PageRouter`.
4. Untuk fitur dinamis, route memanggil controller. Controller memvalidasi
   input, memanggil model/service, dan menyiapkan data.
5. Controller memanggil `Frontend::render(viewKey, pageKey, data)` untuk
   halaman Blade aplikasi.
6. Blade dirender dengan layout aplikasi atau guest. Response HTML diterima
   browser.

Representasi singkat:

```text
Browser
  -> Laravel web middleware
  -> route (manual atau PageRouter)
  -> autentikasi/role middleware (jika dipasang pada route)
  -> controller
  -> model/service/database
  -> Frontend::render()
  -> Blade view dan layout
  -> response HTML
```

### 2.2 Hak akses

| Role/kondisi | Hak akses yang diterapkan |
|---|---|
| Guest | Route login, registrasi, reset password, dan halaman publik yang tidak dikecualikan dari `PageRouter` |
| Admin | CRUD kategori, gudang, pelanggan, produk; transaksi; pembatalan; dan laporan |
| Operator | Transaksi, riwayat transaksi, pembatalan, dan laporan |
| User terautentikasi | Dashboard, profil, verifikasi/konfirmasi password, dan route autentikasi lain yang hanya memakai middleware `auth` |

Route master data memakai `auth` dan `role:admin`. Route transaksi serta
laporan memakai `auth` dan `role:operator,admin`. Dashboard dan profil hanya
memasang `auth`, tidak memasang pemeriksaan role. `RoleMiddleware` memeriksa
nilai `role` dan menghasilkan HTTP 403 jika tidak cocok.

Route `/register` dan POST `/register` **aktif** di route guest. Registrasi
membuat user melalui `RegisteredUserController`; kolom role memiliki default
`operator` dari migration. Dengan demikian aplikasi tidak membatasi
pembuatan user hanya melalui admin. Model `User` memiliki `is_active`, tetapi
middleware role yang ada tidak memeriksa nilai tersebut.

Tidak ditemukan route/controller khusus untuk penyesuaian stok manual atau
retur barang. Mutasi stok yang tersedia saat ini berasal dari barang masuk,
penjualan, transfer, dan reversal pembatalan.

### 2.3 Komponen khusus

| Komponen | Tanggung jawab |
|---|---|
| `PageRouter` | Memindai `src/pages/` dan mengubah file Blade menjadi route GET, dengan pilihan prefix, middleware, data tambahan, dan pola pengecualian |
| `Frontend` | Merender view dengan data, page key, payload frontend, dan layout; menulis payload halaman ke cache project |
| `MakeMigrationCommand` | Command `make:migration` yang menyesuaikan nama tabel dengan konvensi project dan menggunakan migration path aplikasi |
| `CrudGenerateCommand` | Command `crud:generate {name}` yang membaca schema database aktif dan membuat file CRUD dari stub; melewati file yang sudah ada |
| `MakeAllCommand` | Command `make:all {name}` untuk scaffolding beberapa bagian model/resource Laravel sekaligus |
| `FrontendGenerateCommand` | Command `frontend:generate` untuk membuat manifest halaman dan cache frontend |
| `StockService` | Menerapkan/membalik perubahan stok, memvalidasi quantity pada service, mengunci baris stok, mengecek stok minimum dan kapasitas integer, serta membuat stock movement |
| `TransactionMoney` | Mengubah nilai uang ke unit minor integer (sen), menghitung subtotal/total secara presisi, memformat nilai, serta menolak format/overflow yang tidak valid |
| `InventoryReportService` | Berisi builder query untuk laporan stok, barang masuk, dan penjualan. Pada source saat ini class tersebut tidak dipanggil oleh route/controller; `ReportController` membentuk query laporannya sendiri |

### 2.4 Route fitur

Route transaksi dan laporan berikut memerlukan autentikasi serta role admin
atau operator; route master data memerlukan admin.

| URL/metode | Route/controller |
|---|---|
| `GET /dashboard` | `DashboardController::__invoke` |
| `GET /categories`, `/categories/create`, `/categories/{category}/edit` | `CategoryController` |
| `GET /warehouses`, `/warehouses/create`, `/warehouses/{warehouse}/edit` | `WarehouseController` |
| `GET /customers`, `/customers/create`, `/customers/{customer}/edit` | `CustomerController` |
| `GET /products`, `/products/create`, `/products/{product}/edit` | `ProductController` |
| `GET /inbounds/create`, `POST /inbounds`, `GET /inbounds/{transaction}` | `InboundController` |
| `GET /sales/create`, `POST /sales` | `SaleController` |
| `GET /transfers/create`, `POST /transfers` | `TransferController` |
| `GET /transactions`, `GET /transactions/{transaction}`, `POST /transactions/{transaction}/cancel` | `TransactionController` |
| `GET /reports` | `ReportController::index` |
| `GET /reports/stock` dan `/reports/stocks` | `ReportController::stock` / `stocks` |
| `GET /reports/inbound` dan `/reports/inbounds` | `ReportController::inbound` / `inbounds` |
| `GET /reports/sales` | `ReportController::sales` |
| `GET /reports/stocks/export`, `/reports/inbounds/export`, `/reports/sales/export` | Endpoint ekspor CSV laporan |

`PageRouter::register()` juga mendaftarkan route GET untuk halaman yang tidak
masuk daftar pengecualian. Route transaksi dan CRUD yang memerlukan data atau
akses khusus dikecualikan dan didefinisikan secara manual. Tidak ditemukan
route API JSON inventory terpisah; jalur mutasi inventory yang ada adalah
route web dengan middleware Laravel.

## 3. Skema database

Migration saat ini mendefinisikan sembilan tabel domain inventory berikut.
`id` dibuat sebagai primary key Laravel (`BIGINT UNSIGNED` pada MySQL), dan
tabel-tabel domain memakai `created_at` serta `updated_at`, kecuali tabel
pendukung framework yang dapat memiliki bentuk berbeda.

### 3.1 Tabel domain

| Tabel | Kolom dan tipe penting | Key, batasan, dan keterangan |
|---|---|---|
| `users` | `id`, `name VARCHAR`, `email VARCHAR`, `email_verified_at TIMESTAMP NULL`, `password VARCHAR`, `remember_token`, `role ENUM(admin, operator)`, `is_active BOOLEAN` | `email` unique. `role` default `operator`; `is_active` default true. Selain itu migration Laravel membuat tabel `password_reset_tokens` dan `sessions`. |
| `categories` | `id`, `name VARCHAR`, `slug VARCHAR` | `slug` unique. |
| `warehouses` | `id`, `code VARCHAR(20)`, `name VARCHAR`, `address VARCHAR NULL`, `is_active BOOLEAN` | `code` unique; `is_active` default true. |
| `customers` | `id`, `name VARCHAR`, `phone VARCHAR(20) NULL`, `address TEXT NULL` | Nama/telepon/alamat pelanggan penjualan. |
| `products` | `id`, `sku VARCHAR`, `name VARCHAR`, `category_id BIGINT UNSIGNED`, `cost_price DECIMAL(12,2)`, `selling_price DECIMAL(12,2)`, `min_stock INT UNSIGNED`, `is_active BOOLEAN` | `sku` unique; `category_id` FK; harga default 0; `min_stock` default 1. Batas validasi form `min_stock` saat ini 1–100.000. |
| `stocks` | `id`, `warehouse_id BIGINT UNSIGNED`, `product_id BIGINT UNSIGNED`, `quantity INT` | FK gudang dan produk; unique gabungan `(warehouse_id, product_id)`; quantity default 0. |
| `transactions` | `id`, `trx_no VARCHAR`, `type ENUM(IN, OUT, TRANSFER)`, `warehouse_id BIGINT UNSIGNED`, `destination_warehouse_id BIGINT UNSIGNED NULL`, `customer_id BIGINT UNSIGNED NULL`, `transaction_date DATE`, `status ENUM(active, cancelled)`, `notes TEXT NULL`, `cancelled_at TIMESTAMP NULL`, `cancelled_by BIGINT UNSIGNED NULL`, `cancel_reason VARCHAR NULL`, `user_id BIGINT UNSIGNED` | `trx_no` unique; index gabungan `type/status/transaction_date`; FK gudang asal, gudang tujuan, pelanggan, pembatal, pembuat. |
| `transaction_items` | `id`, `transaction_id BIGINT UNSIGNED`, `product_id BIGINT UNSIGNED`, `quantity INT`, `unit_price DECIMAL(12,2)`, `subtotal DECIMAL(14,2)` | FK transaksi dan produk; index `product_id`; nilai harga/subtotal disimpan per item. Migration terakhir menghapus kolom snapshot `product_name`/`product_sku` jika kolom tersebut ada. |
| `stock_movements` | `id`, `transaction_id BIGINT UNSIGNED`, `transaction_item_id BIGINT UNSIGNED NULL`, `warehouse_id BIGINT UNSIGNED`, `product_id BIGINT UNSIGNED`, `quantity INT`, `balance_after INT` | FK transaksi, detail item opsional, gudang, dan produk. Quantity movement positif berarti stok bertambah; negatif berarti stok berkurang. |

Tipe `INT` quantity dan saldo stok berpadanan dengan bilangan signed 32-bit
pada MySQL; batas maksimum validasi quantity adalah `2.147.483.647`. Service
juga mencegah hasil saldo stok melebihi batas tersebut. Nilai uang dibatasi
oleh aturan validasi dan pemeriksaan overflow service/helper.

### 3.2 Relasi dan perilaku penghapusan

```text
categories 1 --- n products
products   1 --- n stocks
warehouses 1 --- n stocks
warehouses 1 --- n transactions (warehouse_id)
warehouses 1 --- n transactions (destination_warehouse_id, nullable)
customers  1 --- n transactions (nullable)
users      1 --- n transactions (user_id)
users      1 --- n transactions (cancelled_by, nullable)
transactions 1 --- n transaction_items
products     1 --- n transaction_items
transactions 1 --- n stock_movements
transaction_items 1 --- n stock_movements (nullable)
warehouses   1 --- n stock_movements
products     1 --- n stock_movements
```

Migration menggunakan cascade untuk beberapa relasi stok/detil transaksi,
`nullOnDelete` untuk pelanggan, gudang tujuan, dan pengguna pembatal, serta
`restrictOnDelete` untuk produk pada `stocks`, `transaction_items`, dan
`stock_movements`. Aplikasi juga mencegah penghapusan gudang/kategori yang
masih digunakan. Produk yang memiliki stok atau histori dinonaktifkan alih-alih
dihapus. Foreign key pada transaksi yang diwajibkan tidak menyatakan cascade,
sehingga database mencegah penghapusan parent yang masih dirujuk.

**Batasan histori produk:** harga satuan dan subtotal tersimpan pada
`transaction_items`, tetapi nama dan SKU produk tidak disimpan sebagai
snapshot di skema terakhir. Halaman transaksi dan laporan mengakses relasi
produk yang ada saat ini. Migration terakhir secara eksplisit menghapus kolom
snapshot nama/SKU bila sebelumnya ada.

### 3.3 Tabel pendukung Laravel

Selain sembilan tabel bisnis, migration framework juga membuat
`password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
`job_batches`, dan `failed_jobs`. Tabel pendukung ini bukan tabel transaksi
inventory.

## 4. Alur aplikasi end-to-end

### 4.1 Login, registrasi, dan autentikasi

| Tahap | URL | Controller/method | View/data |
|---|---|---|---|
| Buka login | `GET /login` | `AuthenticatedSessionController::create` | `resources/views/auth/login.blade.php`, dirender melalui `Frontend::render(..., 'layouts.guest')` |
| Kirim login | `POST /login` | `AuthenticatedSessionController::store` memakai `LoginRequest::authenticate` | Validasi email/password, rate limit; session diregenerasi; redirect ke URL intended atau `/dashboard` |
| Daftar user | `GET /register`, `POST /register` | `RegisteredUserController::create/store` | Form guest; validasi nama/email/password; user baru login setelah terdaftar |
| Keluar | `POST /logout` | `AuthenticatedSessionController::destroy` | Logout, invalidasi session dan regenerasi CSRF token; redirect ke `/login` |

POST login dibatasi middleware `throttle:6,1`; `LoginRequest` juga membatasi
percobaan login berdasarkan email dan IP. Registrasi aktif dan submit-nya juga
memiliki middleware throttle. Jangan gunakan kata sandi seeder pada
lingkungan publik/produksi; ganti dengan kredensial yang dikelola secara aman.

### 4.2 Dashboard

- **URL:** `GET /dashboard`
- **Controller:** `DashboardController::__invoke`
- **Model yang ditanya:** `Product`, `Warehouse`, `Customer`, `Category`,
  `Transaction`, `Stock`.
- **View:** `src/pages/dashboard.blade.php`, dengan layout aplikasi.
- **Data:** jumlah semua produk/gudang/pelanggan/kategori; jumlah transaksi
  penjualan aktif (`type=OUT`) untuk tanggal hari ini; sampai lima stok
  terendah dengan quantity kurang dari atau sama dengan `product.min_stock`;
  lima transaksi paling baru.

Nilai “Penjualan Hari Ini” adalah **jumlah transaksi**, bukan jumlah uang atau
kuantitas item. `lowStock` dihitung dengan mengambil stok beserta relasinya,
memfilter dan mengurutkannya di aplikasi.

### 4.3 Master data (admin)

Semua resource memakai pola REST Laravel dengan index, create, store, edit,
update, destroy di controller; method `show` mengarahkan ke halaman edit.
Route resource dilindungi `auth` dan `role:admin`.

Model `InboundTransaction` yang ada merupakan turunan `Transaction` dan
menggunakan tabel `transactions`; route inbound yang diperiksa menggunakan
model `Transaction`.

| Entitas | URL contoh | Controller / view | Aturan dan perilaku penting |
|---|---|---|---|
| Kategori | `GET /categories`, `GET /categories/create`, `POST /categories`, `GET /categories/{id}/edit`, `PUT /categories/{id}`, `DELETE /categories/{id}` | `CategoryController`; `src/pages/categories/{index,create,edit}.blade.php` | Nama wajib; slug dibuat dari nama dengan `Str::slug`; suffix `-2`, `-3`, dst. dipakai jika slug sudah digunakan. Kategori yang masih dipakai produk tidak dapat dihapus. |
| Gudang | `/warehouses` dan operasi resource | `WarehouseController`; `src/pages/warehouses/` | Kode unik maksimal 20 karakter, nama wajib, alamat opsional, status aktif. Penghapusan ditolak jika ada stok, movement, atau transaksi yang menggunakan gudang. |
| Pelanggan | `/customers` dan operasi resource | `CustomerController`; `src/pages/customers/` | Nama wajib; telepon maksimal 20 karakter dan alamat opsional. Transaksi mengacu pada pelanggan secara nullable sehingga penghapusan pelanggan mengosongkan relasi tersebut di database. |
| Produk | `/products` dan operasi resource | `ProductController`; `src/pages/products/` | SKU unik; nama dan kategori wajib; harga dapat bernilai 0 dengan maksimal dua desimal; `min_stock` wajib antara 1 dan 100.000; status aktif/nonaktif. Kategori dipilih dari daftar kategori. Produk dengan stok atau histori dinonaktifkan, bukan dihapus. |

### 4.4 Barang masuk

1. **Form:** `GET /inbounds/create` → `InboundController::create` mengambil
   gudang aktif dan produk aktif → `src/pages/inbounds/create.blade.php`.
2. **Input:** satu atau lebih detail produk; JavaScript dapat menambah/menghapus
   baris dan menghitung perkiraan subtotal/total.
3. **Submit:** `POST /inbounds` → `InboundController::store`.
4. **Validasi:** gudang aktif, tanggal, catatan opsional, minimal satu item,
   product ID aktif dan unik per transaksi, quantity `required|integer|min:1`
   serta maksimum `2.147.483.647`, dan harga satuan angka 0 atau lebih dengan
   maksimum dua desimal.
5. **Transaksi database:** controller mengunci gudang dan produk, membuat
   transaksi `IN-{ULID}` bertipe `IN`, membuat item, menghitung subtotal
   melalui `TransactionMoney`, lalu memanggil
   `StockService::applyTransaction`.
6. **Perubahan stok:** untuk setiap item, stok gudang bertambah sebesar
   quantity; service membuat `stock_movements` dengan quantity positif dan
   `balance_after`.
7. **Selesai:** commit atomik dan redirect ke detail transaksi. Jika terjadi
   exception, transaksi di-rollback, input dipertahankan, dan error ditampilkan.
8. `GET /inbounds/{transaction}` memanggil `InboundController::show`, memeriksa
   bahwa tipe transaksi adalah `IN`, lalu merender
   `src/pages/inbounds/show.blade.php`.

Contoh: item 3 unit dengan harga `10.000,00` menyimpan unit price dan subtotal
`30.000,00`; saldo stok warehouse bertambah 3.

### 4.5 Penjualan

1. **Form:** `GET /sales/create` → `SaleController::create` menyediakan gudang
   aktif, pelanggan, produk aktif → `src/pages/sales/create.blade.php`.
2. **Submit:** `POST /sales` → `SaleController::store`.
3. **Validasi:** gudang aktif, pelanggan ada, tanggal, minimal satu item,
   produk aktif dan tidak berulang, serta quantity wajib integer minimum 1
   maksimum `2.147.483.647`.
4. Controller mengunci gudang dan produk, membuat transaksi `OUT-{ULID}` dan
   detail. Harga detail **diambil kembali dari `products.selling_price` di
   server**, bukan dipercaya dari harga browser. `unit_price` serta `subtotal`
   tersimpan di `transaction_items`.
5. `StockService::applyTransaction` mengurangi stok sesuai quantity dan
   membuat stock movement negatif. Penurunan yang membuat saldo kurang dari
   `product.min_stock` ditolak.
6. Seluruh perubahan transaksi/item/stok/movement berada dalam database
   transaction. Berhasil akan diarahkan ke detail transaksi; exception
   menyebabkan rollback dan form diisi kembali.

Contoh: `trx_no=OUT-{ULID}`, `type=OUT`, item 2 unit dengan harga master
`20.000,00` menyimpan harga tersebut dan subtotal `40.000,00`. Detail transaksi
memformat angka melalui `TransactionMoney`.

### 4.6 Transfer antar-gudang

1. **Form:** `GET /transfers/create` → `TransferController::create` memuat
   gudang dan produk aktif → `src/pages/transfers/create.blade.php`.
2. **Submit:** `POST /transfers` → `TransferController::store`.
3. **Validasi:** gudang asal dan tujuan aktif, keduanya berbeda, terdapat
   minimal satu item, produk aktif dan tidak duplikat, serta quantity integer
   minimum 1 dengan batas maksimum database.
4. Controller memulai transaksi DB, mengunci gudang dan produk menurut urutan
   ID, membuat transaksi `TRF-{ULID}` bertipe `TRANSFER`, dan item dengan harga
   serta subtotal 0.
5. Service mengurangi stok dari gudang asal (memastikan saldo tidak turun di
   bawah minimum), menambah stok gudang tujuan, dan membuat movement untuk
   masing-masing perubahan.
6. Commit berhasil mengarahkan ke detail transaksi. Exception menyebabkan
   rollback seluruh proses dan input dikembalikan ke form.

### 4.7 Riwayat dan detail transaksi

- **Daftar:** `GET /transactions` → `TransactionController::index`, mengambil
  transaksi terbaru beserta gudang/pelanggan/item dengan pagination 15 →
  `src/pages/transactions/index.blade.php`.
- **Detail:** `GET /transactions/{transaction}` →
  `TransactionController::show`, memuat item dan produk, gudang asal/tujuan,
  pelanggan, serta pembuat → `src/pages/transactions/show.blade.php`.
- Detail menampilkan nomor, tipe, tanggal, status, catatan, item, harga,
  subtotal, dan total. Tombol pembatalan tersedia hanya ketika status `active`.

### 4.8 Pembatalan transaksi

1. Form pada halaman detail mengirim `POST /transactions/{transaction}/cancel`
   dengan alasan pembatalan opsional.
2. `TransactionController::cancel` memvalidasi input dan mengunci baris
   transaksi (`lockForUpdate`); transaksi yang sudah `cancelled` ditolak.
3. `StockService::reverseTransaction` memvalidasi ulang setiap quantity,
   mengunci stok yang terlibat dalam urutan gudang/produk yang konsisten, lalu
   menerapkan kebalikan movement:

   | Tipe awal | Dampak reversal |
   |---|---|
   | `IN` | Mengurangi stok gudang penerima; harus tetap memenuhi minimum stock |
   | `OUT` | Menambah kembali stok gudang penjualan |
   | `TRANSFER` | Mengurangi stok gudang tujuan (harus memenuhi batas minimum), lalu mengembalikan quantity ke gudang asal |

4. Jika reversal berhasil, transaksi diperbarui menjadi `cancelled` dan
   menyimpan `cancelled_at`, `cancelled_by`, serta `cancel_reason`. Item dan
   movement terdahulu tidak dihapus; movement reversal menambah audit trail.
5. Jika stok yang diterima sudah digunakan atau reversal akan melanggar
   minimum, proses ditolak dan rollback menjaga transaksi tetap aktif tanpa
   perubahan parsial. Pembatalan kedua kali tidak membalik stok lagi.

### 4.9 Laporan dan ekspor CSV

| Halaman | URL | Controller/view | Filter dan isi |
|---|---|---|---|
| Ringkasan | `GET /reports` | `ReportController::index`; `src/pages/reports/index.blade.php` | Menampilkan akses ke laporan stok, barang masuk, dan penjualan; tanggal awal/akhir default bulan berjalan |
| Stok | `GET /reports/stock` | `ReportController::stock`; `src/pages/reports/stock.blade.php` | Filter gudang dan produk; menampilkan stok, gudang, produk, kategori. Query ekspor `?export=csv` atau route `/reports/stocks/export`. |
| Barang masuk | `GET /reports/inbound` | `ReportController::inbound`; `src/pages/reports/inbound.blade.php` | Periode `from`/`to`, gudang, produk; transaksi `IN` berstatus active. Ekspor CSV melalui `?export=csv` atau `/reports/inbounds/export`. |
| Penjualan | `GET /reports/sales` | `ReportController::sales`; `src/pages/reports/sales.blade.php` | Periode, gudang, produk, pelanggan; transaksi `OUT` berstatus active. Ekspor CSV melalui `?export=csv` atau `/reports/sales/export`. |

Controller memvalidasi filter, memformat detail transaksi dari item yang
tersimpan, dan menghasilkan CSV UTF-8 dengan BOM. `safeCsvValue()` memberi
awalan apostrof untuk teks yang berpotensi menjadi formula spreadsheet
(diawali `=`, `+`, `-`, atau `@`). Harga/subtotal laporan berasal dari detail
transaksi, sedangkan nama/SKU produk berasal dari relasi produk yang saat ini
tersedia.

## 5. Aturan bisnis utama

1. **Quantity transaksi:** setiap baris barang masuk, penjualan, dan transfer
   wajib berupa integer minimal 1. Nilai maksimum request adalah
   `2.147.483.647`, sesuai batas signed integer quantity.
2. **Batas stok:** service mencegah saldo negatif, saldo di bawah
   `product.min_stock` ketika stok dikurangi, serta overflow di atas
   `2.147.483.647`. Dengan nilai `min_stock` default 1, pengurangan normal
   tidak boleh menghabiskan stok hingga nol.
3. **Harga transaksi:** harga penjualan dihitung dari harga jual produk di
   server saat transaksi dibuat. Detail transaksi menyimpan `unit_price` dan
   `subtotal`, sehingga perubahan harga master tidak mengubah angka uang pada
   detail lama.
4. **Transfer:** transfer adalah perpindahan quantity, bukan penjualan;
   `unit_price` dan `subtotal` detail disimpan sebagai 0.
5. **Atomicity:** transaksi, item, stok, dan movement dibuat/dibalik dalam
   database transaction. Jika satu item gagal, keseluruhan operasi dibatalkan.
6. **Audit trail:** pembatalan mengubah status dan menambah movement reversal;
   record transaksi/item lama tidak dihapus.
7. **Produk berhistori:** produk dengan stok atau histori dinonaktifkan agar
   tetap dapat dirujuk; FK pada tiga tabel inventory juga membatasi
   penghapusan produk.
8. **Pembuatan nomor transaksi:** nomor menggunakan prefix `IN-`, `OUT-`, atau
   `TRF-` diikuti ULID dan memiliki unique constraint.

## 6. Keamanan dan integritas

- Halaman transaksi dan laporan memerlukan autentikasi serta role operator
  atau admin; route CRUD master data hanya untuk admin.
- Form POST menggunakan `@csrf`; Laravel memvalidasi request di server,
  sehingga batas input HTML bukan satu-satunya perlindungan.
- Login, registrasi, reset password, dan beberapa aksi autentikasi memakai
  middleware throttle. Form login juga memakai rate limiter berdasarkan email
  dan IP.
- Produk/gudang dipilih ulang serta dikunci pada sebagian jalur transaksi;
  service menggunakan `lockForUpdate` untuk stok. Untuk transfer dan
  pembatalan, penguncian stok dibangun dari pasangan gudang/produk terurut
  untuk mengurangi risiko deadlock.
- Database transaction menjaga konsistensi multi-item dan rollback saat
  exception. Beberapa transaction closure meminta hingga tiga percobaan dari
  Laravel; perilaku retry/deadlock aktual juga bergantung driver/database.
- CSV ekspor melakukan mitigasi formula injection.
- Seeder menyediakan akun demo dengan kata sandi default. Gunakan akun itu
  hanya pada database development terisolasi dan ganti sebelum penggunaan
  publik. `.env` serta kredensial database jangan dimasukkan ke repository.
- Aplikasi tidak memiliki customer-facing registration flow yang berbeda:
  route registrasi Breeze di atas aktif dan membentuk user aplikasi, dengan
  role default dari database.

## 7. Pengujian

Konfigurasi `phpunit.xml` menetapkan `APP_ENV=testing`, `DB_CONNECTION=sqlite`,
`DB_DATABASE=:memory:`, session/cache/mail/queue test. Dengan demikian suite
tidak ditujukan untuk database aplikasi aktif.

| File feature test | Fokus |
|---|---|
| `tests/Feature/Auth/AuthenticationTest.php` | Autentikasi dan logout |
| `tests/Feature/Auth/RegistrationTest.php` | Registrasi |
| `tests/Feature/Auth/EmailVerificationTest.php` | Proses verifikasi email |
| `tests/Feature/Auth/PasswordConfirmationTest.php` | Konfirmasi password |
| `tests/Feature/Auth/PasswordResetTest.php` | Reset password |
| `tests/Feature/Auth/PasswordUpdateTest.php` | Update password |
| `tests/Feature/ProfileTest.php` | Profil user |
| `tests/Feature/RoleAccessTest.php` | Pembatasan akses admin/operator dan seeder |
| `tests/Feature/CategoryCrudTest.php` | CRUD kategori dan slug |
| `tests/Feature/WarehouseCrudTest.php` | CRUD gudang |
| `tests/Feature/CustomerCrudTest.php` | CRUD pelanggan |
| `tests/Feature/ProductCrudTest.php` | CRUD produk dan batas minimum stok |
| `tests/Feature/InboundTest.php` | Barang masuk dan service stok |
| `tests/Feature/SaleTest.php` | Penjualan, stok, dan aturan minimum |
| `tests/Feature/TransferTest.php` | Transfer dua gudang dan stok minimum |
| `tests/Feature/CancelTransactionTest.php` | Pembatalan/reversal, multi-item, dan rollback |
| `tests/Feature/QuantityMinimumTest.php` | Quantity invalid/valid, atomicity, overflow, dan aturan form |
| `tests/Feature/DashboardTest.php` | Statistik dashboard |
| `tests/Feature/ReportTest.php` | Laporan dan filter |
| `tests/Feature/InventoryReportsTest.php` | Laporan stok/transaksi dan histori |
| `tests/Feature/SidebarNavigationTest.php` | Navigasi sesuai role |
| `tests/Feature/PageRouterTest.php` | Route berbasis file |
| `tests/Feature/CrudGenerateCommandTest.php` | Generator CRUD dari metadata tabel, relasi, validasi dasar, dan perilaku saat dijalankan ulang |
| `tests/Feature/ExampleTest.php` | Contoh test bawaan |

Hasil verifikasi terakhir yang tercatat sebelum dokumen ini dibuat:

```text
php artisan test --compact
105 tests passed
880 assertions
```

Tes browser sebelumnya dilakukan pada form barang masuk, penjualan, dan
transfer, termasuk quantity kosong, 0, negatif, pecahan, baris dinamis, dan
quantity 1. Tes otomatis dan tes browser memiliki cakupan berbeda; keduanya
tidak menggantikan pengujian MySQL paralel/deadlock.

## 8. Cara menjalankan aplikasi

Prasyarat: PHP 8.3+, Composer, Node.js/npm, serta salah satu database yang
didukung. Berikut langkah development lokal yang aman:

1. Install dependency PHP:

   ```bash
   composer install
   ```

2. Install dependency frontend:

   ```bash
   npm ci
   ```

3. Siapkan file environment jika belum ada. Pada Windows PowerShell:

   ```powershell
   Copy-Item .env.example .env
   ```

   Jangan menimpa `.env` yang sudah berisi konfigurasi lokal pengguna.

4. Buat application key:

   ```bash
   php artisan key:generate
   ```

5. Konfigurasikan koneksi database lokal di `.env`. Contoh MySQL development
   memakai nilai placeholder berikut; sesuaikan dengan database development
   yang sudah dibuat:

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nama_database_development
   DB_USERNAME=user_development
   DB_PASSWORD=password_development
   ```

   `.env.example` menggunakan SQLite sebagai default. MySQL bukan database
   default yang dapat disimpulkan dari source code; gunakan hanya bila server
   dan database development sudah tersedia.

6. Terapkan migration pada database development yang benar:

   ```bash
   php artisan migrate
   ```

   Untuk data contoh, jalankan seeder **hanya jika database development
   tersebut memang disiapkan untuk menerima data demo**:

   ```bash
   php artisan db:seed
   ```

   Jangan gunakan `migrate:fresh`, `db:wipe`, atau seeder pada database yang
   berisi data penting tanpa rencana dan persetujuan yang sesuai.

7. Jalankan server Laravel dan Vite di dua terminal development:

   ```bash
   php artisan serve
   ```

   ```bash
   npm run dev
   ```

   Alternatif untuk aset statis:

   ```bash
   npm run build
   ```

8. Buka `http://127.0.0.1:8000/login`.

### Akun demo dari seeder

| Role | Email | Kata sandi development |
|---|---|---|
| Admin | `admin@sinar.com` | `admin123` |
| Operator | `operator@sinar.com` | `operator123` |

Akun ini berasal dari `UserSeeder`; kata sandi di-hash oleh model. Jangan
menggunakan kredensial demo pada lingkungan yang dapat diakses publik.

### Menjalankan test

```bash
php artisan test
```

Suite memakai SQLite in-memory sesuai `phpunit.xml`. Untuk memverifikasi
kompatibilitas database MySQL, siapkan database MySQL testing yang terisolasi
dan jalankan migration/test yang relevan terhadap database itu; hasil SQLite
saja bukan bukti runtime penuh MySQL.

## 9. Struktur folder proyek

```text
app/
├── Console/Commands/         Command kustom
├── Http/
│   ├── Controllers/          Controller auth, CRUD, transaksi, dashboard, laporan
│   ├── Middleware/           RoleMiddleware, MinifyHtmlMiddleware
│   └── Requests/             Form Request autentikasi dan inbound
├── Models/                   Model Eloquent dan relasi
├── Services/                 StockService dan InventoryReportService
├── Support/                  Frontend, PageRouter, TransactionMoney, helpers
├── database/
│   ├── migrations/           Migration Laravel dan domain inventory
│   ├── seeders/              UserSeeder, MasterDataSeeder, DatabaseSeeder
│   └── factories/
└── routes/                   web.php dan console.php
bootstrap/app.php             Konfigurasi route, command, middleware
config/                       Konfigurasi Laravel; termasuk koneksi database
resources/views/              View auth/profil dan layout framework
src/
├── assets/                   CSS dan JavaScript
├── layouts/                  Layout utama, sidebar, header
└── pages/                    Blade halaman inventory, transaksi, dashboard, laporan
tests/Feature/                Pengujian HTTP dan fitur
```

## 10. Ringkasan alur bisnis

```text
User login
  → buka dashboard
  → tambah/master data produk (admin)
  → catat barang masuk
  → catat penjualan
  → transfer stok antar-gudang
  → batalkan transaksi bila dibutuhkan
  → tinjau detail/riwayat dan laporan
  → export CSV
  → logout
```

Operator menjalankan transaksi, pembatalan, dan laporan sesuai role yang
diizinkan. Pengelolaan master data dilakukan oleh admin.
