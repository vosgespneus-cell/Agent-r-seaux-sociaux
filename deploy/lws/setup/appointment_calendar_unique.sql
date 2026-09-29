-- Migration rejouable pour les installations existantes.
-- Refuse volontairement l'ajout si des références Calendar dupliquées existent :
-- elles doivent être examinées avant de créer l'index UNIQUE.

SET @vp_calendar_duplicates := (
  SELECT COUNT(*) FROM (
    SELECT calendar_reference
    FROM vp_appointment_requests
    WHERE calendar_reference IS NOT NULL
    GROUP BY calendar_reference
    HAVING COUNT(*) > 1
  ) AS duplicates
);

SET @vp_calendar_index_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'vp_appointment_requests'
    AND index_name = 'vp_appointment_calendar_reference'
);

SET @vp_calendar_sql := IF(
  @vp_calendar_index_exists > 0,
  'SELECT ''vp_appointment_calendar_reference already present''',
  IF(
    @vp_calendar_duplicates > 0,
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''duplicate calendar_reference values must be resolved before migration''',
    'ALTER TABLE vp_appointment_requests ADD UNIQUE KEY vp_appointment_calendar_reference (calendar_reference)'
  )
);

PREPARE vp_calendar_stmt FROM @vp_calendar_sql;
EXECUTE vp_calendar_stmt;
DEALLOCATE PREPARE vp_calendar_stmt;
