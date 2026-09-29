<?php
declare(strict_types=1);
require_once __DIR__ . '/calendar_appointment.php';

final class AppointmentLifecycle
{
    public static function markConfirmed(PDO $pdo, int $requestId, array $proof): void
    {
        if (!CalendarAppointment::canConfirm($proof)) {
            throw new DomainException('calendar_proof_required');
        }
        $stmt=$pdo->prepare(
          "UPDATE vp_appointment_requests
           SET status='confirmed', calendar_reference=:ref
           WHERE request_id=:id AND status IN ('ready_to_check','proposed')"
        );
        $stmt->execute([':ref'=>(string)$proof['event_id'], ':id'=>$requestId]);
        if ($stmt->rowCount() !== 1) throw new DomainException('appointment_state_conflict');
    }

    public static function markUnavailable(PDO $pdo, int $requestId): void
    {
        $stmt=$pdo->prepare(
          "UPDATE vp_appointment_requests
           SET status='unavailable'
           WHERE request_id=:id AND status IN ('ready_to_check','proposed')"
        );
        $stmt->execute([':id'=>$requestId]);
        if ($stmt->rowCount() !== 1) throw new DomainException('appointment_state_conflict');
    }
}
