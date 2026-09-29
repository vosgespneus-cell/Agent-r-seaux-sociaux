-- Rend la preuve Calendar unique quand elle existe.
-- MariaDB autorise plusieurs NULL dans un index UNIQUE.
ALTER TABLE vp_appointment_requests
  ADD UNIQUE KEY vp_appointment_calendar_reference (calendar_reference);
