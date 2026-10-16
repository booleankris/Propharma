-- =====================================================================
-- SCRIPT PENGARSIPAN & PENGHAPUSAN TRANSAKSI SEBELUM 11 OKTOBER 2026
-- KHUSUS CABANG: APOTEK SAHABAT PMI (pharmacy_id = 1)
-- =====================================================================
-- Catatan:
-- 1. Cabang lain (Mulawarman, MIM, Sutomo, Antasari, dll) TIDAK akan terpengaruh.
-- 2. Data sebelum tanggal 11 Oktober 2026 disalin terlebih dahulu ke tabel arsip:
--    - medicine_transactions_archive
--    - medicine_cart_archive
--    - finance_payments_archive
-- 3. Data finance yang berelasi dengan transaksi tersebut ikut dibersihkan.
-- =====================================================================

START TRANSACTION;

-- 1. BUAT TABEL ARSIP (JIKA BELUM ADA)
CREATE TABLE IF NOT EXISTS medicine_transactions_archive LIKE medicine_transactions;
CREATE TABLE IF NOT EXISTS medicine_cart_archive LIKE medicine_cart;
CREATE TABLE IF NOT EXISTS finance_payments_archive LIKE finance_payments;

-- 2. SALIN DATA KE TABEL ARSIP SEBELUM DIHAPUS
INSERT IGNORE INTO medicine_transactions_archive
SELECT * FROM medicine_transactions
WHERE pharmacy_id = 1 AND created_at < '2026-10-11 00:00:00';

INSERT IGNORE INTO medicine_cart_archive
SELECT mc.* FROM medicine_cart mc
INNER JOIN medicine_transactions mt ON mc.transaction_id = mt.id
WHERE mt.pharmacy_id = 1 AND mt.created_at < '2026-10-11 00:00:00';

INSERT IGNORE INTO finance_payments_archive
SELECT fp.* FROM finance_payments fp
INNER JOIN medicine_transactions mt ON fp.medicine_transaction_id = mt.id
WHERE mt.pharmacy_id = 1 AND mt.created_at < '2026-10-11 00:00:00';

-- 3. HAPUS DATA OPERASIONAL TERKAIT
-- (a) Hapus data pembayaran finance terkait
DELETE fp FROM finance_payments fp
INNER JOIN medicine_transactions mt ON fp.medicine_transaction_id = mt.id
WHERE mt.pharmacy_id = 1 AND mt.created_at < '2026-10-11 00:00:00';

-- (b) Hapus item transaksi di medicine_cart
DELETE mc FROM medicine_cart mc
INNER JOIN medicine_transactions mt ON mc.transaction_id = mt.id
WHERE mt.pharmacy_id = 1 AND mt.created_at < '2026-10-11 00:00:00';

-- (c) Hapus transaksi utama di medicine_transactions
DELETE FROM medicine_transactions
WHERE pharmacy_id = 1 AND created_at < '2026-10-11 00:00:00';

COMMIT;
