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

use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use ExportHttp\TwigFilter\Shopware6Uuid;

class Shopware6UpsertPropertyOption extends AbstractTwigFunction
{
    public function __construct()
    {
        parent::__construct();

        $this->addDependency('serviceFactory');
        $this->addDependency(Shopware6Uuid::class);
    }

    public function run(string $pavId, string $channelId, string $language = 'main'): ?string
    {
        if (empty($pavId)) {
            return null;
        }

        $pav = $this->getInjection('serviceFactory')->create('ProductAttributeValue')->getEntity($pavId);
        if (empty($pav)) {
            return null;
        }

        $attribute = $pav->get('attribute');
        if (empty($attribute)) {
            return null;
        }

        // skip wrong language
        if (!empty($attribute->get('isMultilang')) && $language !== $pav->get('language')) {
            return null;
        }

        if (!empty($channelId)) {
            if ($pav->get('scope') === 'Channel' && $pav->get('channelId') !== $channelId) {
                return null;
            }
            if ($pav->get('scope') === 'Global') {
                $channelPav = $this
                    ->getInjection('entityManager')
                    ->getRepository('ProductAttributeValue')
                    ->where([
                        'attributeId' => $pav->get('attributeId'),
                        'productId'   => $pav->get('productId'),
                        'language'    => $pav->get('language'),
                        'scope'       => 'Channel',
                        'channelId'   => $channelId
                    ])
                    ->findOne();
                if (!empty($channelPav)) {
                    return null;
                }
            }
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

        $propertyId = $this->upsertProperty($attribute, $apiHost, $headers, $language);

        if ($pav->get('value') === null || $pav->get('value') === '') {
            return null;
        }

        $optionId = $this->upsertPropertyValue($pav, $apiHost, $headers, $propertyId);

        return $optionId;
    }

    protected function upsertProperty(Entity $attribute, string $apiHost, array $headers, string $language): string
    {
        $uuid = $this->getInjection(Shopware6Uuid::class)->filter($attribute->get('id'));

        $nameField = 'name';
        if ($language !== 'main') {
            $nameField .= ucfirst(Util::toCamelCase(strtolower($language)));
        }

        $body = [
            'id'   => $uuid,
            'name' => $attribute->get($nameField),
            'filterable' => $attribute->has('filterable') && $attribute->get('filterable') == true,
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
            $body = [
                'id'                         => $uuid,
                'name'                       => $attribute->get($nameField),
                'displayType'                => 'text',
                'sortingType'                => 'alphanumeric',
                'filterable'                 => $attribute->has('filterable') && $attribute->get('filterable') == true,
                'visibleOnProductDetailPage' => true,
                'position'                   => 1
            ];
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
                    "Shopware6 upsertProperty failed. URL: '$apiHost/api/property-group'. Headers: '" . json_encode($headers) . "'. Body: '" . json_encode($body) . "'"
                );
            }
        }

        return $uuid;
    }

    protected function upsertPropertyValue(Entity $pav, string $apiHost, array $headers, string $propertyId): string
    {
        /**
         * Prepare value
         */
        $value = $pav->get('value');
        if (is_array($value)) {
            $value = implode(', ', $value);
        } else {
            $value = mb_substr((string)$value, 0, 250);
        }
        switch ($pav->get('attributeType')) {
            case 'bool':
                $value = !empty($value) ? '+' : '-';
                break;
            case 'unit':
                $value .= ' ' . $pav->get('valueUnit');
                break;
            case 'currency':
                $value .= ' ' . $pav->get('valueCurrency');
                break;
        }

        if ($value === '') {
            $value = 'None';
        }

        if ($pav->get('language') !== 'main') {
            $uuid = $this->getInjection(Shopware6Uuid::class)->filter($pav->get('mainLanguageId'));
        } else {
            $uuid = $this->getInjection(Shopware6Uuid::class)->filter($pav->get('id'));
        }

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
                    "Shopware6 upsertPropertyValue. URL: '$apiHost/api/property-group/$propertyId/options" . "'.Headers: '" . json_encode($headers) . "'. Body: '"
                    . json_encode(['id' => $uuid, 'name' => $value]) . "'"
                );
            }
        }

        return $uuid;
    }
}
