<?php

namespace NW\WebService\References\Operations\Notification;

class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW    = 1;
    public const TYPE_CHANGE = 2;

    /**
     * @throws \Exception
     */
    public function doOperation(): array
    {
        $data = (array)$this->getRequest('data');
        $resellerId = $data['resellerId'];
        $notificationType = (int)$data['notificationType'];
        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail'   => false,
            'notificationClientBySms'     => [
                'isSent'  => false,
                'message' => '',
            ],
        ];

        if (empty((int)$resellerId)) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result;
        }

        if (empty((int)$notificationType)) {
            throw new \Exception('Empty notificationType', 400);
        }

        $reseller = $this->getNotNullObject(Seller::getById((int)$resellerId), 'Seller not found!');
        $client = $this->getNotNullObject(Contractor::getById((int)$data['clientId']), 'сlient not found!');
        $this->validateClient($client, $resellerId);

        $cFullName = $client->getFullName();
        if (empty($client->getFullName())) {
            $cFullName = $client->name;
        }

        $cr = $this->getNotNullObject(Employee::getById((int)$data['creatorId']), 'Creator not found!');
        $et = $this->getNotNullObject(Employee::getById((int)$data['expertId']), 'Expert not found!');

        $differences = $this->getDifferences($notificationType, $data, $resellerId);

        $templateData = $this->getTemplateData($data, $cr, $et, $cFullName, $differences, $resellerId);

        $emailFrom = getResellerEmailFrom($resellerId);
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            $this->sendEmails($emails, $emailFrom, $templateData, $resellerId);
            $result['notificationEmployeeByEmail'] = true;
        }

        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            $this->sendClientNotifications($client, $emailFrom, $templateData, $resellerId, $data, $result);
        }

        return $result;
    }

    private function getNotNullObject($object, $errorMessage)
    {
        if ($object === null) {
            throw new \Exception($errorMessage, 400);
        }

        return $object;
    }

    private function validateClient($client, $resellerId)
    {
        if ($client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $resellerId) {
            throw new \Exception('сlient not found!', 400);
        }
    }

    private function getDifferences($notificationType, $data, $resellerId)
    {
        if ($notificationType === self::TYPE_NEW) {
            return __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            return __('PositionStatusHasChanged', [
                'FROM' => Status::getName((int)$data['differences']['from']),
                'TO'   => Status::getName((int)$data['differences']['to']),
            ], $resellerId);
        }

        return '';
    }

    private function getTemplateData($data, $cr, $et, $cFullName, $differences, $resellerId)
    {
        $templateData = [
            'COMPLAINT_ID'       => (int)$data['complaintId'],
            'COMPLAINT_NUMBER'   => (string)$data['complaintNumber'],
            'CREATOR_ID'         => (int)$data['creatorId'],
            'CREATOR_NAME'       => $cr->getFullName(),
            'EXPERT_ID'          => (int)$data['expertId'],
            'EXPERT_NAME'        => $et->getFullName(),
            'CLIENT_ID'          => (int)$data['clientId'],
            'CLIENT_NAME'        => $cFullName,
            'CONSUMPTION_ID'     => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER'   => (string)$data['agreementNumber'],
            'DATE'               => (string)$data['date'],
            'DIFFERENCES'        => $differences,
        ];

        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        return $templateData;
    }

    private function sendEmails($emails, $emailFrom, $templateData, $resellerId)
    {
        foreach ($emails as $email) {
            MessagesClient::sendMessage([
                0 => [ // MessageTypes::EMAIL
                    'emailFrom' => $emailFrom,
                    'emailTo'   => $email,
                    'subject'   => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                    'message'   => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                ],
            ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
        }
    }

    private function sendClientNotifications($client, $emailFrom, $templateData, $resellerId, $data, &$result)
    {
        if (!empty($emailFrom) && !empty($client->email)) {
            MessagesClient::sendMessage([
                0 => [ // MessageTypes::EMAIL
                    'emailFrom' => $emailFrom,
                    'emailTo'   => $client->email,
                    'subject'   => __('complaintClientEmailSubject', $templateData, $resellerId),
                    'message'   => __('complaintClientEmailBody', $templateData, $resellerId),
                ],
            ], $resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to']);
            $result['notificationClientByEmail'] = true;
        }

        if (!empty($client->mobile)) {
            $error = null;
            $res = NotificationManager::send($resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to'], $templateData, $error);
            if ($res) {
                $result['notificationClientBySms']['isSent'] = true;
            }
            if (!empty($error)) {
                $result['notificationClientBySms']['message'] = $error;
            }
        }
    }
}