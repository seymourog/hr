<?php

namespace NW\WebService\References\Operations\Notification;

class Status
{
    const COMPLETED = 0;
    const PENDING = 1;
    const REJECTED = 2;

    public static function getName(int $id): string
    {
        $a = [self::COMPLETED => 'Completed', self::PENDING => 'Pending', self::REJECTED => 'Rejected',];

        return $a[$id];
    }
}
