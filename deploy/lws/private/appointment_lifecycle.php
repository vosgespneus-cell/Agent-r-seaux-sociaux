<?php
declare(strict_types=1);
require_once __DIR__.'/calendar_appointment.php';

final class AppointmentLifecycle
{
    public static function markConfirmed(PDO $pdo,int $requestId,array $proof):void
    {
        if(!CalendarAppointment::canConfirm($proof))throw new DomainException('calendar_proof_required');
        $eventId=trim((string)$proof['event_id']);

        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare("SELECT status,calendar_reference FROM vp_appointment_requests WHERE request_id=:id FOR UPDATE");
            $q->execute([':id'=>$requestId]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row)throw new DomainException('appointment_not_found');

            if($row['status']==='confirmed'){
                if(hash_equals((string)$row['calendar_reference'],$eventId)){$pdo->commit();return;}
                throw new DomainException('appointment_confirmation_conflict');
            }
            if(!in_array($row['status'],['ready_to_check','proposed'],true))throw new DomainException('appointment_state_conflict');

            $q=$pdo->prepare("SELECT request_id FROM vp_appointment_requests WHERE calendar_reference=:ref AND request_id<>:id LIMIT 1");
            $q->execute([':ref'=>$eventId,':id'=>$requestId]);
            if($q->fetchColumn())throw new DomainException('calendar_reference_already_used');

            $q=$pdo->prepare("UPDATE vp_appointment_requests SET status='confirmed',calendar_reference=:ref WHERE request_id=:id");
            $q->execute([':ref'=>$eventId,':id'=>$requestId]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function markUnavailable(PDO $pdo,int $requestId):void
    {
        $q=$pdo->prepare("UPDATE vp_appointment_requests SET status='unavailable'
          WHERE request_id=:id AND status IN ('ready_to_check','proposed')");
        $q->execute([':id'=>$requestId]);
        if($q->rowCount()!==1){
            $q=$pdo->prepare("SELECT status FROM vp_appointment_requests WHERE request_id=:id");
            $q->execute([':id'=>$requestId]);
            if($q->fetchColumn()!=='unavailable')throw new DomainException('appointment_state_conflict');
        }
    }
}
