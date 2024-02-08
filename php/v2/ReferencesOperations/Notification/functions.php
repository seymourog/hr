<?php

namespace NW\WebService\References\Operations\Notification;

function getResellerEmailFrom()
{
    return 'contractor@example.com';
}

function getEmailsByPermit($resellerId, $event)
{
    // TODO: Replace this with real logic to get emails by permit
    return ['someemeil@example.com', 'someemeil2@example.com'];
}
