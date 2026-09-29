<?php
declare(strict_types=1);
require_once __DIR__ . '/../deploy/lws/private/calendar_appointment.php';

$ok = CalendarAppointment::validate([
  'service' => 'Montage pneus',
  'start' => '2026-10-01T10:00:00+02:00',
  'end' => '2026-10-01T10:30:00+02:00',
  'source_reference' => 'TEST-PLANNING-1'
]);
if (!$ok['valid']) throw new RuntimeException('valid appointment rejected');
if ($ok['appointment']['calendar_id'] !== 'vosgespneus@gmail.com') throw new RuntimeException('wrong calendar');

$bad = CalendarAppointment::validate([
  'service' => 'Montage pneus',
  'start' => '2026-10-01T11:00:00+02:00',
  'end' => '2026-10-01T10:00:00+02:00',
  'source_reference' => 'TEST-PLANNING-2'
]);
if ($bad['valid']) throw new RuntimeException('invalid range accepted');

if (!CalendarAppointment::canConfirm([
  'event_id' => 'google-event-test',
  'start' => '2026-10-01T10:00:00+02:00',
  'end' => '2026-10-01T10:30:00+02:00',
  'status' => 'confirmed'
])) throw new RuntimeException('valid proof rejected');

if (CalendarAppointment::canConfirm([
  'event_id' => '',
  'start' => '2026-10-01T10:00:00+02:00',
  'end' => '2026-10-01T10:30:00+02:00',
  'status' => 'confirmed'
])) throw new RuntimeException('missing proof accepted');

echo "VP_CALENDAR_APPOINTMENT_OK\n";
