USE kost_management;

-- Trigger untuk log aktivitas user
DELIMITER //
CREATE TRIGGER user_activity_log 
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, description)
    VALUES (NEW.id, 'USER_REGISTERED', CONCAT('New user registered: ', NEW.username));
END //
DELIMITER ;

-- Trigger untuk update room status ketika booking approved
DELIMITER //
CREATE TRIGGER booking_status_update 
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF NEW.status = 'approved' AND OLD.status = 'pending' THEN
        UPDATE rooms SET status = 'occupied' WHERE id = NEW.room_id;
        
        INSERT INTO activity_logs (user_id, action, description)
        VALUES (NEW.user_id, 'BOOKING_APPROVED', CONCAT('Booking approved for room: ', NEW.room_id));
    END IF;
    
    IF NEW.status = 'completed' AND OLD.status = 'active' THEN
        UPDATE rooms SET status = 'available' WHERE id = NEW.room_id;
        
        INSERT INTO activity_logs (user_id, action, description)
        VALUES (NEW.user_id, 'BOOKING_COMPLETED', CONCAT('Booking completed for room: ', NEW.room_id));
    END IF;
END //
DELIMITER ;

-- Trigger untuk auto-generate payment record
DELIMITER //
CREATE TRIGGER auto_payment_record 
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF NEW.status = 'approved' AND OLD.status = 'pending' THEN
        INSERT INTO payments (booking_id, amount, payment_date, payment_method, status)
        VALUES (NEW.id, NEW.total_amount, CURDATE(), 'transfer', 'pending');
    END IF;
END //
DELIMITER ;
