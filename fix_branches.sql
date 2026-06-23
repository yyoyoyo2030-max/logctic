SET NAMES utf8mb4;

DELETE FROM branches WHERE name LIKE '%?%';
DELETE FROM branches WHERE name = 'فرع الدمام';
DELETE FROM branches WHERE name = 'فرع جدة';

INSERT IGNORE INTO branches (name) VALUES 
('الثلاجه'), 
('الحلويات'), 
('المنصورة'), 
('النسيم'), 
('الواحه'), 
('بريده'), 
('حائل'), 
('خميس مشيط'), 
('سكاي مول');
