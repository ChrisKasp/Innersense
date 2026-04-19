<?php

declare(strict_types=1);

function isValidDateOrEmpty(string $value): bool
{
    if ($value === '') {
        return true;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
}

/**
 * @return list<string>
 */
function buildHalfHourSlots(string $start, string $end): array
{
    $slots = [];
    $cursor = DateTimeImmutable::createFromFormat('H:i', $start);
    $endTime = DateTimeImmutable::createFromFormat('H:i', $end);

    if (!$cursor instanceof DateTimeImmutable || !$endTime instanceof DateTimeImmutable) {
        return $slots;
    }

    while ($cursor < $endTime) {
        $slots[] = $cursor->format('H:i');
        $cursor = $cursor->modify('+30 minutes');
    }

    return $slots;
}

function isValidHalfHourSlot(string $time): bool
{
    return in_array($time, buildHalfHourSlots('09:00', '20:00'), true);
}

/**
 * @return list<string>
 */
function getAvailableHalfHourSlots(PDO $pdo, string $date): array
{
    $allSlots = buildHalfHourSlots('09:00', '20:00');

    if (!isValidDateOrEmpty($date) || $date === '') {
        return $allSlots;
    }

    $stmt = $pdo->prepare(
        'SELECT DATE_FORMAT(slot_time, "%H:%i") AS slot_key
         FROM schedule_blocked_slot
         WHERE slot_date = :slot_date'
    );
    $stmt->execute([':slot_date' => $date]);

    $blockedMap = [];
    foreach ($stmt->fetchAll() as $row) {
        $slot = (string) ($row['slot_key'] ?? '');
        if ($slot !== '') {
            $blockedMap[$slot] = true;
        }
    }

    return array_values(array_filter(
        $allSlots,
        static fn (string $slot): bool => !isset($blockedMap[$slot])
    ));
}