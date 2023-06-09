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

use Espo\ORM\Entity;
use ExportHttp\TwigFilter\Shopware6Uuid;

class Shopware6UpsertVariantOptions extends AbstractTwigFunction
{
    public function __construct()
    {
        parent::__construct();

        $this->addDependency('serviceFactory');
        $this->addDependency('entityManager');
        $this->addDependency(Shopware6Uuid::class);
        $this->addDependency(Shopware6UpsertPropertyOption::class);
    }

    public function run(string $pavId, string $channelId = '', string $shopwareIdField = '', string $shopwareProductIdField = ''): ?string
    {
        $pav = $this->getInjection('serviceFactory')->create('ProductAttributeValue')->getEntity($pavId);

        if (!empty($channelId)) {
            if ($pav->get('scope') === 'Channel' && $pav->get('channelId') !== $channelId && !$pav->get('isVariantSpecificAttribute')) {
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
                        'channelId'   => $channelId,
                        'isVariantSpecificAttribute' => true
                    ])
                    ->findOne();
                if (!empty($channelPav)) {
                    return null;
                }
            }
        }

        if (!$pav->get('isVariantSpecificAttribute')) {
            return null;
        }

        if ($pav->get('language') !== 'main') {
            $mainPav = $this
                ->getInjection('entityManager')
                ->getRepository('ProductAttributeValue')
                ->where([
                    'language' => 'main',
                    'attributeId' => $pav->get('attributeId'),
                    'productId' => $pav->get('productId'),
                    'channelId' => $pav->get('channelId'),
                    'isVariantSpecificAttribute' => true
                ])
                ->findOne();

            if (empty($mainPav)) {
                return null;
            }

            $pav = $mainPav;
        }

        $attribute = $pav->get('attribute');
        if (!empty($shopwareIdField) && $attribute->has($shopwareIdField) && !empty($attribute->get($shopwareIdField))) {
            $propertyId = $attribute->get($shopwareIdField);
        } else {
            $propertyId = $this->getInjection(Shopware6Uuid::class)->filter($attribute->id);
        }

        $value = $this->getInjection(Shopware6UpsertPropertyOption::class)->preparePropertyOptionValue($pav);

        if (empty($uuid = $this->searchPropertyId($propertyId, $value))) {
            $uuidString = $propertyId . '_' . $value;

            $uuid = $this->getInjection(Shopware6Uuid::class)->filter($uuidString);
        }

        if (!empty($shopwareProductIdField) && $attribute->has($shopwareProductIdField) && !empty($pav->get('product')->get($shopwareProductIdField))) {
            $productId = $pav->get('product')->get($shopwareProductIdField);
        } else {
            $productId = $this->getInjection(Shopware6Uuid::class)->filter($pav->get('productId'));
        }

        $productProperties = $this->getProductProperties($productId);

        if (in_array($uuid, $productProperties)) {
            return $uuid;
        }

        return null;
    }

    protected function getProductProperties(string $uuid): array
    {
        $apiUrlData = parse_url($this->getFeedData()['httpUrl']);
        $apiHost = $apiUrlData['scheme'] . '://' . $apiUrlData['host'];

        $connectionData = $this->getConnectionData();

        $headers = [
            'Content-Type: application/json',
            "Authorization: {$connectionData['token_type']} {$connectionData['access_token']}"
        ];

        $ch = curl_init("$apiHost/api/search/product");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['ids' => $uuid]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $responseInfo = curl_getinfo($ch);
        curl_close($ch);

        if (!empty($responseInfo['http_code']) && $responseInfo['http_code'] === 200) {
            $data = @json_decode($response, true);
            if (!empty($data['data'][0]['attributes']['propertyIds'])) {
                return $data['data'][0]['attributes']['propertyIds'];
            }
        }

        return [];
    }

    protected function searchPropertyId(string $propertyId, $value): ?string
    {
        $apiUrlData = parse_url($this->getFeedData()['httpUrl']);
        $apiHost = $apiUrlData['scheme'] . '://' . $apiUrlData['host'];

        $connectionData = $this->getConnectionData();

        $headers = [
            'Content-Type: application/json',
            "Authorization: {$connectionData['token_type']} {$connectionData['access_token']}"
        ];

        $ch = curl_init("$apiHost/api/search/property-group/$propertyId/options");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt(
            $ch, CURLOPT_POSTFIELDS, json_encode([
                'total-count-mode' => 1
            ])
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $responseInfo = curl_getinfo($ch);
        curl_close($ch);

        if (!empty($responseInfo['http_code']) && !empty($response)) {
            $response = json_decode($response, true);

            if (is_array($response) && !empty($response['data'])) {
                foreach ($response['data'] as $option) {
                    if (array_key_exists('attributes', $option) && array_key_exists('name', $option['attributes']) && $option['attributes']['name'] == $value) {
                        return $option['id'];
                    }
                }
            }
        }

        return null;
    }
}
