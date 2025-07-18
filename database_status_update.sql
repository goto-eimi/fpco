-- 予約ステータス更新スクリプト
-- 実行前に必ずバックアップを取得してください

-- 1. 予約ステータスのenum型を5つの状態に拡張
ALTER TABLE wp_reservations 
MODIFY COLUMN status ENUM('new', 'pending', 'approved', 'rejected', 'cancelled') 
DEFAULT 'new' 
COMMENT '予約ステータス: new=新規受付, pending=確認中, approved=承認, rejected=否認, cancelled=キャンセル';

-- 2. 既存データのマイグレーション（必要に応じて実行）
-- 既存の 'confirmed' データを 'approved' に変更
UPDATE wp_reservations SET status = 'approved' WHERE status = 'confirmed';

-- 3. 実行後の確認
-- SELECT status, COUNT(*) as count FROM wp_reservations GROUP BY status;
-- DESCRIBE wp_reservations;