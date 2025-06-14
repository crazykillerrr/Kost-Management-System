USE kost_management;

-- Stored Procedure untuk booking kamar
DELIMITER //
CREATE PROCEDURE BookRoom(
    IN p_user_id INT,
    IN p_room_id INT,
    IN p_start_date DATE,
    IN p_duration_months INT,
    OUT p_booking_id INT,
    OUT p_status VARCHAR(50)
)
BEGIN
    DECLARE room_available INT DEFAULT 0;
    DECLARE room_price DECIMAL(10,2);
    DECLARE total_amount DECIMAL(10,2);
    DECLARE end_date DATE;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_status = 'ERROR: Transaction failed';
        SET p_booking_id = 0;
    END;
    
    START TRANSACTION;
    
    -- Check if room is available
    SELECT COUNT(*), price INTO room_available, room_price
    FROM rooms 
    WHERE id = p_room_id AND status = 'available';
    
    IF room_available = 0 THEN
        SET p_status = 'ERROR: Room not available';
        SET p_booking_id = 0;
        ROLLBACK;
    ELSE
        -- Calculate total amount and end date
        SET total_amount = room_price * p_duration_months;
        SET end_date = DATE_ADD(p_start_date, INTERVAL p_duration_months MONTH);
        
        -- Insert booking
        INSERT INTO bookings (user_id, room_id, booking_date, start_date, end_date, duration_months, total_amount)
        VALUES (p_user_id, p_room_id, CURDATE(), p_start_date, end_date, p_duration_months, total_amount);
        
        SET p_booking_id = LAST_INSERT_ID();
        SET p_status = 'SUCCESS: Booking created successfully';
        
        COMMIT;
    END IF;
END //
DELIMITER ;

-- Stored Procedure untuk approve booking
DELIMITER //
CREATE PROCEDURE ApproveBooking(
    IN p_booking_id INT,
    IN p_admin_id INT,
    OUT p_status VARCHAR(50)
)
BEGIN
    DECLARE room_id_val INT;
    DECLARE booking_exists INT DEFAULT 0;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_status = 'ERROR: Transaction failed';
    END;
    
    START TRANSACTION;
    
    -- Check if booking exists and is pending
    SELECT COUNT(*), room_id INTO booking_exists, room_id_val
    FROM bookings 
    WHERE id = p_booking_id AND status = 'pending';
    
    IF booking_exists = 0 THEN
        SET p_status = 'ERROR: Booking not found or already processed';
        ROLLBACK;
    ELSE
        -- Update booking status
        UPDATE bookings SET status = 'approved' WHERE id = p_booking_id;
        
        -- Update room status
        UPDATE rooms SET status = 'occupied' WHERE id = room_id_val;
        
        -- Log activity
        INSERT INTO activity_logs (user_id, action, description)
        VALUES (p_admin_id, 'APPROVE_BOOKING', CONCAT('Approved booking ID: ', p_booking_id));
        
        SET p_status = 'SUCCESS: Booking approved successfully';
        COMMIT;
    END IF;
END //
DELIMITER ;
