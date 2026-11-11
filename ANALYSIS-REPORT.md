# Analisis Statistik — Benchmark CRUD App ORM vs App SP (Fase 2)

**Tanggal**: 2026-09-06
**Sumber data**: `result/*.csv` (48 file, 30 sampel/file, total 1.440 baris)
**Tool**: Python 3.12 — pandas 3.0.5, scipy 1.18.1, matplotlib 3.11.1, numpy 2.5.3
**Script**: `scripts/analyze_benchmark.py`

> Data mentah (30 sampel per kombinasi) tidak diulang di sini — sudah ada utuh di `result/*.csv`. Dokumen ini fokus ke hasil olahan statistiknya.

---

## 1. Statistik Deskriptif Lengkap

Dihitung untuk seluruh 48 kombinasi (app × entity × operation × volume): mean, median, std dev, min, max, Q1, Q3, IQR. Ditampilkan ringkas (mean/median/SD) per tabel volume di bawah — nilai min/max/Q1/Q3/IQR lengkap ada di `scripts/analysis_output/descriptive.json`.

### Volume 5.000

| Entity | Operation | ORM Mean | ORM Median | ORM SD | SP Mean | SP Median | SP SD |
|---|---|---|---|---|---|---|---|
| product | create | 11.5101 | 3.1319 | 32.6246 | 1.3209 | 1.2578 | 0.1835 |
| product | read | 1.6231 | 1.3258 | 1.0615 | 0.2648 | 0.2515 | 0.0463 |
| product | update | 3.7092 | 3.0400 | 3.4118 | 5.4352 | 1.6431 | 8.7661 |
| product | delete | 3.2099 | 3.8885 | 1.9074 | 2.4202 | 1.5010 | 2.6027 |
| employee | create | 4.9304 | 4.2590 | 1.8667 | 1.3933 | 1.2704 | 0.2573 |
| employee | read | 1.7118 | 1.3214 | 1.9496 | 0.2910 | 0.2885 | 0.0437 |
| employee | update | 4.6021 | 4.3211 | 0.9316 | 1.4547 | 1.4909 | 0.2154 |
| employee | delete | 5.9939 | 1.6309 | 12.8576 | 1.2074 | 1.1970 | 0.0818 |

### Volume 15.000

| Entity | Operation | ORM Mean | ORM Median | ORM SD | SP Mean | SP Median | SP SD |
|---|---|---|---|---|---|---|---|
| product | create | 1.8042 | 1.7570 | 0.3247 | 2.0968 | 1.8216 | 0.5828 |
| product | read | 3.3137 | 2.6995 | 3.1494 | 0.2578 | 0.2499 | 0.0308 |
| product | update | 1.7634 | 1.7068 | 0.2336 | 1.6454 | 1.5609 | 0.1854 |
| product | delete | 1.4842 | 1.2438 | 0.5164 | 1.4256 | 1.3160 | 0.3205 |
| employee | create | 1.5842 | 1.3690 | 0.7383 | 1.2543 | 1.2269 | 0.1261 |
| employee | read | 3.1165 | 2.6660 | 0.9781 | 0.2812 | 0.2591 | 0.0460 |
| employee | update | 1.4875 | 1.3870 | 0.2056 | 1.3683 | 1.2235 | 0.4560 |
| employee | delete | 1.2930 | 1.2405 | 0.1843 | 1.2656 | 1.2040 | 0.1702 |

### Volume 25.000

| Entity | Operation | ORM Mean | ORM Median | ORM SD | SP Mean | SP Median | SP SD |
|---|---|---|---|---|---|---|---|
| product | create | 14.9287 | 5.0980 | 50.4271 | 1.5972 | 1.5191 | 0.2292 |
| product | read | 5.3693 | 4.1525 | 6.3628 | 0.2735 | 0.2495 | 0.0540 |
| product | update | 3.0040 | 1.8789 | 2.2718 | 1.5450 | 1.5207 | 0.2525 |
| product | delete | 2.5221 | 1.8236 | 1.2684 | 1.3338 | 1.2506 | 0.1802 |
| employee | create | 3.1978 | 3.1700 | 1.6447 | 1.2936 | 1.2614 | 0.1641 |
| employee | read | 5.3642 | 4.0660 | 6.4875 | 0.2689 | 0.2546 | 0.0487 |
| employee | update | 6.9564 | 2.6045 | 12.0606 | 6.5315 | 1.2380 | 13.7162 |
| employee | delete | 1.7016 | 1.7106 | 0.2417 | 2.2616 | 1.6741 | 1.5711 |

**Catatan pola umum**: hampir di semua kombinasi, **mean jauh lebih besar dari median** dan **SD sering lebih besar dari mean itu sendiri** (mis. `product create` ORM @25.000: mean 14,93 vs median 5,10, SD 50,43). Ini indikasi kuat distribusi **right-skewed dengan outlier ekstrem** (bukan distribusi normal) — dikonfirmasi formal di Bagian 2.

---

## 2. Uji Normalitas (Shapiro-Wilk)

Shapiro-Wilk dijalankan untuk seluruh 48 kombinasi (n=30 tiap kombinasi), α=0,05.

| Hasil | Jumlah |
|---|---|
| **Normal** (p > 0,05) | **3 / 48** |
| **Tidak normal** (p ≤ 0,05) | **45 / 48** |

Hanya 3 kombinasi yang lolos normalitas, **semuanya di sisi App ORM**, tidak satu pun di sisi App SP:

| App | Entity | Operation | Volume | Shapiro p-value |
|---|---|---|---|---|
| orm | employee | delete | 25.000 | 0,0816 |
| orm | product | create | 15.000 | 0,1432 |
| orm | product | update | 15.000 | 0,1367 |

Karena syarat *paired t-test* di Bagian 3 adalah **kedua sisi (ORM dan SP) harus lolos normalitas**, dan tidak ada satu pun dari 24 pasangan yang memenuhi syarat itu (SP tidak pernah lolos normalitas di kombinasi manapun), **seluruh 24 pasangan otomatis diuji pakai Wilcoxon signed-rank test**, bukan t-test.

---

## 3. Uji Signifikansi per Kombinasi (24 Pasangan)

Semua 24 pasangan diuji dengan **Wilcoxon signed-rank test** (`scipy.stats.wilcoxon`), sesuai hasil uji normalitas di Bagian 2. Effect size dilaporkan sebagai **rank-biserial correlation** (r, rentang -1 s.d. 1; tanda positif = SP lebih cepat, tanda negatif = ORM lebih cepat; |r| mendekati 1 = beda sangat konsisten arahnya di semua 30 pasangan sampel).

| Entity | Operation | Volume | Test | p-value | Signifikan? | Effect Size | Lebih Cepat |
|---|---|---|---|---|---|---|---|
| employee | create | 5.000 | Wilcoxon signed-rank | 5.60e-09 | Ya | r=0.991 | SP |
| employee | create | 15.000 | Wilcoxon signed-rank | 2.37e-05 | Ya | r=0.884 | SP |
| employee | create | 25.000 | Wilcoxon signed-rank | 2.55e-07 | Ya | r=0.935 | SP |
| employee | delete | 5.000 | Wilcoxon signed-rank | 1.73e-06 | Ya | r=1.000 | SP |
| employee | delete | 15.000 | Wilcoxon signed-rank | 0.3085 | Tidak | r=0.217 | SP |
| employee | delete | 25.000 | Wilcoxon signed-rank | 0.1579 | Tidak | r=-0.299 | SP |
| employee | read | 5.000 | Wilcoxon signed-rank | 1.73e-06 | Ya | r=1.000 | SP |
| employee | read | 15.000 | Wilcoxon signed-rank | 1.73e-06 | Ya | r=1.000 | SP |
| employee | read | 25.000 | Wilcoxon signed-rank | 1.90e-09 | Ya | r=1.000 | SP |
| employee | update | 5.000 | Wilcoxon signed-rank | 1.90e-09 | Ya | r=1.000 | SP |
| employee | update | 15.000 | Wilcoxon signed-rank | 0.0208 | Ya | r=0.480 | SP |
| employee | update | 25.000 | Wilcoxon signed-rank | 0.7303 | Tidak | r=0.075 | SP |
| product | create | 5.000 | Wilcoxon signed-rank | 1.90e-09 | Ya | r=1.000 | SP |
| product | create | 15.000 | Wilcoxon signed-rank | 0.0293 | Ya | r=-0.454 | ORM |
| product | create | 25.000 | Wilcoxon signed-rank | 1.90e-09 | Ya | r=1.000 | SP |
| product | delete | 5.000 | Wilcoxon signed-rank | 0.0054 | Ya | r=0.570 | SP |
| product | delete | 15.000 | Wilcoxon signed-rank | 0.7922 | Tidak | r=-0.058 | ORM |
| product | delete | 25.000 | Wilcoxon signed-rank | 2.35e-06 | Ya | r=0.884 | SP |
| product | read | 5.000 | Wilcoxon signed-rank | 1.73e-06 | Ya | r=1.000 | SP |
| product | read | 15.000 | Wilcoxon signed-rank | 1.90e-09 | Ya | r=1.000 | SP |
| product | read | 25.000 | Wilcoxon signed-rank | 1.90e-09 | Ya | r=1.000 | SP |
| product | update | 5.000 | Wilcoxon signed-rank | 0.1981 | Tidak | r=0.273 | SP |
| product | update | 15.000 | Wilcoxon signed-rank | 0.0577 | Tidak | r=0.398 | SP |
| product | update | 25.000 | Wilcoxon signed-rank | 9.22e-06 | Ya | r=0.845 | SP |

**Ringkasan**: **18 dari 24 pasangan (75%) signifikan** (p < 0,05); 6 tidak signifikan. Dari 18 yang signifikan, **17 mengarah ke SP lebih cepat**, dan **1 mengarah ke ORM lebih cepat** (`product create` @15.000, r=-0,454, tergolong efek menengah bukan ekstrem). Semua operasi `read` (6/6 kombinasi) signifikan dengan **r=1,000 atau mendekati** — artinya di semua 30 pasangan sampel, SP SELALU lebih cepat tanpa pengecualian, efek paling konsisten di seluruh dataset.

---

## 4. Ringkasan Tren terhadap Volume Data

Median ORM vs SP (ms) di tiap level volume, per kombinasi entity×operation, dan pola gap (selisih median ORM−SP) seiring volume naik:

| Entity | Operation | ORM@5k | SP@5k | ORM@15k | SP@15k | ORM@25k | SP@25k | Pola Gap |
|---|---|---|---|---|---|---|---|---|
| employee | create | 4.259 | 1.270 | 1.369 | 1.227 | 3.170 | 1.261 | menyempit |
| employee | delete | 1.631 | 1.197 | 1.240 | 1.204 | 1.711 | 1.674 | menyempit |
| employee | read | 1.321 | 0.288 | 2.666 | 0.259 | 4.066 | 0.255 | **melebar** |
| employee | update | 4.321 | 1.491 | 1.387 | 1.224 | 2.604 | 1.238 | menyempit |
| product | create | 3.132 | 1.258 | 1.757 | 1.822 | 5.098 | 1.519 | melebar |
| product | delete | 3.889 | 1.501 | 1.244 | 1.316 | 1.824 | 1.251 | menyempit |
| product | read | 1.326 | 0.252 | 2.700 | 0.250 | 4.153 | 0.249 | **melebar** |
| product | update | 3.040 | 1.643 | 1.707 | 1.561 | 1.879 | 1.521 | menyempit |

**Pola yang jelas dan konsisten**: kedua operasi **`read`** (product & employee) menunjukkan gap **melebar secara rapi dan monoton** seiring volume naik (median SP nyaris konstan ~0,25-0,29ms di semua level, sementara median ORM naik hampir linear: ~1,3ms → ~2,7ms → ~4,1-4,2ms). Ini satu-satunya pasangan operasi dengan tren scaling yang benar-benar mulus dan dapat diprediksi.

Operasi lain (`create`, `update`, `delete`) menunjukkan pola **non-monoton** (naik-turun antar level volume, bukan tren scaling yang rapi) — mis. `product create` ORM: 3,13ms (5k) → 1,76ms (15k) → 5,10ms (25k). Ini lebih mencerminkan **noise antar sesi eksekusi command** (`artisan` di-invoke sebagai proses baru tiap kali, kena cold-start PHP/OPcache yang bervariasi) daripada pola scaling asli terhadap volume data — konsisten dengan temuan outlier ekstrem di Bagian 1 (SD jauh lebih besar dari median untuk metrik-metrik ini).

---

## 5. Visualisasi

8 line chart (median execution time vs volume data, ORM vs SP) disimpan di `result/charts/`:

| Entity | Operation | File |
|---|---|---|
| Product | Create | `result/charts/product-create.png` |
| Product | Read | `result/charts/product-read.png` |
| Product | Update | `result/charts/product-update.png` |
| Product | Delete | `result/charts/product-delete.png` |
| Employee | Create | `result/charts/employee-create.png` |
| Employee | Read | `result/charts/employee-read.png` |
| Employee | Update | `result/charts/employee-update.png` |
| Employee | Delete | `result/charts/employee-delete.png` |

`product-read.png` dan `employee-read.png` adalah yang paling menarik secara visual — garis SP nyaris rata (flat) di dekat 0, sementara garis ORM naik curam dan hampir linear terhadap volume, secara visual langsung menunjukkan pola "melebar" di Bagian 4 tanpa perlu tabel angka.

---

## 6. Ringkasan Temuan Utama

1. **Operasi `read` (list+pagination) adalah temuan paling kuat dan paling bisa diandalkan di seluruh analisis ini**: signifikan secara statistik di 6/6 kombinasi (p < 2×10⁻⁶ semua), effect size maksimal (r=1,000, SP menang di SELURUH 30 pasangan sampel tanpa pengecualian), dan gap-nya melebar konsisten & mulus seiring volume data naik — dari beda ~1ms di 5.000 baris jadi ~3,8-3,9ms di 25.000 baris. Ini paling kuat menunjukkan `Eloquent::paginate()` menanggung biaya `COUNT(*)` tambahan yang scaling dengan ukuran tabel, sedangkan `sp_product_list`/`sp_employee_list` tidak.

2. **75% (18/24) kombinasi menunjukkan perbedaan yang signifikan secara statistik**, dan dari yang signifikan, **17 dari 18 mengarah ke SP lebih cepat** — jadi arah keunggulan SP bukan kebetulan, ini pola yang konsisten dan didukung uji statistik formal, bukan cuma dari mean mentah yang bisa menyesatkan.

3. **45 dari 48 kombinasi (94%) TIDAK terdistribusi normal** (Shapiro-Wilk p≤0,05) — data benchmark eksekusi waktu di lingkungan ini secara umum right-skewed dengan outlier ekstrem (kemungkinan cold-start proses PHP CLI per invocation, bukan karakteristik query itu sendiri). Ini alasan kuat kenapa uji nonparametrik (Wilcoxon) dipakai untuk semua 24 pasangan, dan kenapa **median jauh lebih representatif daripada mean** untuk melaporkan hasil benchmark ini.

4. **Operasi `create`/`update`/`delete` jauh lebih ambigu** dibanding `read` — 6 dari 24 kombinasi (25%) tidak signifikan secara statistik, dan 1 kombinasi (`product create` @15.000) malah signifikan ke arah **ORM lebih cepat** (r=-0,454, efek menengah). Pola gap-nya juga non-monoton terhadap volume (naik-turun, bukan tren rapi) — ini indikasi variabilitas antar-sesi eksekusi lebih dominan dibanding efek arsitektur akses data untuk operasi-operasi ini, jadi klaim "SP selalu lebih cepat" **tidak berlaku universal** — hanya kuat untuk `read`.

5. **Kesimpulan gabungan**: keunggulan performa App SP atas App ORM **paling kuat dan paling bisa dipertanggungjawabkan secara statistik untuk operasi baca (read/list)**, jauh lebih lemah dan kadang terbalik untuk operasi tulis (create/update/delete) pada level akses-data murni ini — berbeda dari benchmark HTTP di Fase 1 (`bernchamrk-result.md`) yang menunjukkan SP menang konsisten di semua 10 operasi. Perbedaan ini masuk akal: overhead HTTP/middleware yang seragam di Fase 1 mungkin "meratakan" perbedaan yang sebenarnya lebih bervariasi di level data-access murni, dan noise antar-invocation command CLI (Fase 2) lebih terasa untuk operasi yang secara alami cepat (create/update/delete pada baris tunggal) dibanding operasi yang secara struktural mahal dan scaling dengan volume (read/paginate).
