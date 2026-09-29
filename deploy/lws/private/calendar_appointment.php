<?php
declare(strict_types=1);

/**
 * Contract for the VOSGES PNEUS Google Calendar adapter.
 * Network/OAuth implementation stays outside the public repository.
 */
final class CalendarAppointment
{
    public static function validate(array $request): array
    {
        $errors = [];
        $service = trim((string)($request['service'] ?? ''));
        $start = trim((string)($request['start'] ?? ''));
        $end = trim((string)($request['end'] ?? ''));
        $source = trim((string)($request['source_reference'] ?? ''));

        if ($service === '') $errors[] = 'service_required';
        if ($source === '') $errors[] = 'source_reference_required';

        try {
            $startAt = new DateTimeImmutable($start);
            $endAt = new DateTimeImmutable($end);
            if ($endAt <= $startAt) $errors[] = 'invalid_time_range';
        } catch (Throwable $e) {
            $errors[] = 'invalid_datetime';
        }

        return [
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'appointment' => [
                'service' => $service,
                'start' => $start,
                'end' => $end,
                'source_reference' => $source,
                'calendar_id' => 'vosgespneus@gmail.com',
                'timezone' => 'Europe/Paris',
            ],
        ];
    }

    public static function canConfirm(array $proof): bool
    {
        return trim((string)($proof['event_id'] ?? '')) !== ''
            && trim((string)($proof['start'] ?? '')) !== ''
            && trim((string)($proof['end'] ?? '')) !== ''
            && (($proof['status'] ?? '') === 'confirmed');
    }
}
