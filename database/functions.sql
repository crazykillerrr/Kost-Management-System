USE kost_management;

-- Function untuk menghitung total revenue
DELIMITER //
CREATE FUNCTION GetTotalRevenue(start_date DATE, end_date DATE) 
RETURNS DECIMAL(15,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE total DECIMAL(15,2) DEFAULT 0;
    
    SELECT COALESCE(SUM(amount), 0) INTO total
    FROM payments 
    WHERE payment_date BETWEEN start_date AND end_date 
    AND status = 'completed';
    
    RETURN total;
END //
DELIMITER ;

-- Function untuk menghitung occupancy rate
DELIMITER //
CREATE FUNCTION GetOccupancyRate() 
RETURNS DECIMAL(5,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE total_rooms INT DEFAULT 0;
    DECLARE occupied_rooms INT DEFAULT 0;
    DECLARE rate DECIMAL(5,2) DEFAULT 0;
    
    SELECT COUNT(*) INTO total_rooms FROM rooms;
    SELECT COUNT(*) INTO occupied_rooms FROM rooms WHERE status = 'occupied';
    
    IF total_rooms > 0 THEN
        SET rate = (occupied_rooms / total_rooms) * 100;
    END IF;
    
    RETURN rate;
END //
DELIMITER ;

-- Function untuk validasi email
DELIMITER //
CREATE FUNCTION IsValidEmail(email VARCHAR(100)) 
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE is_valid BOOLEAN DEFAULT FALSE;
    
    IF email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$' THEN
        SET is_valid = TRUE;
    END IF;
    
    RETURN is_valid;
END //
DELIMITER ;
