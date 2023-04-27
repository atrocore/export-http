<?php
/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore UG (haftungsbeschränkt).
 *
 * This Software is the property of AtroCore UG (haftungsbeschränkt) and is
 * protected by copyright law - it is NOT Freeware and can be used only in one
 * project under a proprietary license, which is delivered along with this program.
 * If not, see <https://atropim.com/eula> or <https://atrodam.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
 */

declare(strict_types=1);

namespace ExportHttp\TwigFunction;

use Espo\Core\Utils\Language;
use Espo\ORM\Entity;
use Export\TwigFunction\ExtensibleEnumOption;
use ExportHttp\TwigFilter\Shopware6Uuid;

class Shopware6UpsertPropertyOptionFromField extends AbstractTwigFunction
{
    public function __construct()
    {
        parent::__construct();

        $this->addDependency(Shopware6Uuid::class);
        $this->addDependency('container');
    }

    public function run(string $field, Entity $entity, string $language = 'main', string $label = ''): ?string
    {
        if (empty($field) || empty($entity)) {
            return null;
        }

        $apiUrlData = parse_url($this->getFeedData()['httpUrl']);
        $apiHost = $apiUrlData['scheme'] . '://' . $apiUrlData['host'];

        $connectionData = $this->getConnectionData();
        $feedData = $this->getFeedData();

        $headers = [
            'Content-Type: application/json',
            "Authorization: {$connectionData['token_type']} {$connectionData['access_token']}"
        ];
        if ($language !== 'main') {
            foreach ($feedData['httpHeaders'] as $row) {
                if ($row['key'] === 'sw-language-id') {
                    $headers[] = "sw-language-id: {$row['value']}";
                }
            }
        }

        $propertyId = $this->upsertProperty($field, $entity->getEntityType(), $apiHost, $headers, $language, $label);

        $optionId = $this->upsertPropertyValue($field, $entity, $apiHost, $headers, $propertyId);

        return $optionId;
    }

    protected function upsertProperty(string $field, string $scope, string $apiHost, array $headers, string $language, string $label): string
    {
        $uuid = $this->getInjection(Shopware6Uuid::class)->filter("{$field}_{$scope}");

        if ($language === 'main') {
            $language = $this->getInjection('container')->get('config')->get('mainLanguage', 'en_US');
        }

        if (empty($label)) {
            $label = (new Language($this->getInjection('container'), $language))->translate($field, 'fields', $scope);
        }

        $body = [
            'id'   => $uuid,
            'name' => $label
        ];

        $ch = curl_init("$apiHost/api/property-group/$uuid");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $responseInfo = curl_getinfo($ch);
        curl_close($ch);

        if (!empty($responseInfo['http_code']) && $responseInfo['http_code'] !== 204) {
            $body = array_merge($body, [
                'displayType'                => 'text',
                'sortingType'                => 'alphanumeric',
                'filterable'                 => false,
                'visibleOnProductDetailPage' => true,
                'position'                   => 1
            ]);
            $ch = curl_init("$apiHost/api/property-group");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            $responseInfo = curl_getinfo($ch);
            curl_close($ch);

            if (!empty($responseInfo['http_code']) && $responseInfo['http_code'] !== 204) {
                $GLOBALS['log']->error(
                    "Shopware6 upsertPropertyFromField failed. URL: '$apiHost/api/property-group'. Headers: '" . json_encode($headers) . "'. Body: '" . json_encode($body) . "'"
                );
            }
        }

        return $uuid;
    }

    protected function upsertPropertyValue(string $field, Entity $entity, string $apiHost, array $headers, string $propertyId): string
    {
        $type = $this->getInjection('container')->get('metadata')->get(['entityDefs', $entity->getEntityType(), 'fields', $field, 'type'], 'varchar');

        /**
         * Prepare value
         */
        $value = $this->getValue($entity, $field, $type);
        if (is_array($value)) {
            $value = implode(', ', $value);
        } else {
            $value = mb_substr((string)$value, 0, 250);
        }
        switch ($type) {
            case 'bool':
                $value = !empty($value) ? '+' : '-';
                break;
            case 'unit':
                $value .= ' ' . $entity->get($field . 'Unit');
                break;
            case 'currency':
                $value .= ' ' . $entity->get($field . 'Currency');
                break;
        }

        if ($value === '') {
            $value = 'None';
        }

        $uuid = $this->getInjection(Shopware6Uuid::class)->filter("{$entity->get('id')}_{$field}");

        $ch = curl_init("$apiHost/api/property-group/$propertyId/options/$uuid");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt(
            $ch, CURLOPT_POSTFIELDS, json_encode([
                'id'   => $uuid,
                'name' => $value
            ])
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $responseInfo = curl_getinfo($ch);
        curl_close($ch);

        if (!empty($responseInfo['http_code']) && $responseInfo['http_code'] !== 204) {
            $ch = curl_init("$apiHost/api/property-group/$propertyId/options");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt(
                $ch, CURLOPT_POSTFIELDS, json_encode([
                    'id'   => $uuid,
                    'name' => $value
                ])
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            $responseInfo = curl_getinfo($ch);
            curl_close($ch);

            if (!empty($responseInfo['http_code']) && $responseInfo['http_code'] === 204) {
                return $uuid;
            }

            /**
             * Create as main language
             */
            $connectionData = $this->getConnectionData();
            $ch = curl_init("$apiHost/api/property-group/$propertyId/options");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt(
                $ch, CURLOPT_POSTFIELDS, json_encode([
                    'id'   => $uuid,
                    'name' => $value
                ])
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', "Authorization: {$connectionData['token_type']} {$connectionData['access_token']}"]);
            $response = curl_exec($ch);
            $responseInfo = curl_getinfo($ch);
            curl_close($ch);

            if (!empty($responseInfo['http_code']) && $responseInfo['http_code'] !== 204) {
                $GLOBALS['log']->error(
                    "Shopware6 upsertPropertyValueFromField. URL: '$apiHost/api/property-group/$propertyId/options" . "'.Headers: '" . json_encode($headers) . "'. Body: '"
                    . json_encode(['id' => $uuid, 'name' => $value]) . "'"
                );
            }
        }

        return $uuid;
    }

    protected function getValue(Entity $entity, string $field, string $fieldType)
    {
        $result = null;

        $value = $entity->get($field);
        if ($fieldType == 'extensibleEnum') {
            $option = $this->getInjection(ExtensibleEnumOption::class)->run($value);

            if (!empty($option)) {
                $result = $option->get('name');
            }
        } elseif ($fieldType == 'extensibleMultiEnum') {
            $result = [];

            $options = $this
                ->getInjection('entityManager')
                ->getRepository('ExtensibleEnumOption')
                ->where(['id' => $value])
                ->find();

            foreach ($value as $id) {
                foreach ($options as $option) {
                    if ($option->id == $id) {
                        $result[] = $option->get('name');
                    }
                }
            }
        }

        return $result;
    }
}
