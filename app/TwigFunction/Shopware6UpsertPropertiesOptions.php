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
use Espo\ORM\EntityCollection;
use ExportHttp\TwigFilter\Shopware6Uuid;

class Shopware6UpsertPropertiesOptions extends AbstractTwigFunction
{
    public function __construct()
    {
        parent::__construct();

        $this->addDependency('serviceFactory');
        $this->addDependency('entityManager');
        $this->addDependency(Shopware6Uuid::class);
    }

    public function run(string $productId, string $channelId = '', string $language = 'main'): array
    {
        if (empty($productId)) {
            return [];
        }

        $properties = $optionIds = [];

        $linked = $this->getInjection('serviceFactory')->create('Product')->findLinkedEntities($productId, 'productAttributeValues', []);

        if (isset($linked['collection']) && $linked['collection'] instanceof EntityCollection) {
            $collection = $linked['collection'];

            foreach ($collection as $item) {
                if ($this->validateProductAttributeValue($item, $collection, $channelId, $language)) {
                    $attribute = $item->get('attribute');

                    $propertyId = $this->getInjection(Shopware6Uuid::class)->filter($attribute->id);

                    $nameField = 'name';
                    if ($language !== 'main') {
                        $nameField .= ucfirst(Util::toCamelCase(strtolower($language)));
                    }

                    $data = [
                        'id' => $propertyId,
                        'name' => $attribute->get($nameField),
                        'displayType' => 'text',
                        'sortingType' => 'alphanumeric',
                        'filterable' => $attribute->has('filterable') && $attribute->get('filterable') == true,
                        'visibleOnProductDetailPage' => true,
                        'position' => 1
                    ];

                    $mainPav = $item;
                    if ($item->get('language') != 'main') {
                        $mainPav = $this
                            ->getInjection('entityManager')
                            ->getRepository('ProductAttributeValue')
                            ->where([
                                'language' => 'main',
                                'attributeId' => $item->get('attributeId'),
                                'productId' => $item->get('productId'),
                                'channelId' => $item->get('channelId')
                            ])
                            ->findOne();
                    }

                    $optionId = $this->getInjection(Shopware6Uuid::class)->filter($propertyId . '_' . $this->preparePropertyOptionValue($mainPav));
                    $optionIds[] = $optionId;

                    $data['options'] = [
                        [
                            'id' => $optionId,
                            'name' => $this->preparePropertyOptionValue($item),
                            'position' => 1
                        ]
                    ];

                    $properties[] = $data;
                }
            }
        }

        if (!empty($properties)) {
            $this->syncProperties($properties, $language);
        }

        return $optionIds;
    }

    protected function syncProperties(array $properties, string $language): void
    {
        $body[] = [
            'entity'  => 'property_group',
            'action'  => 'upsert',
            'payload' => []
        ];

        foreach ($properties as $property) {
            $body[0]['payload'][] = $property;
        }

        $apiHost = $this->getApiHost();
        $headers = $this->getHeaders($language);

        $ch = curl_init("$apiHost/api/_action/sync");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $responseInfo = curl_getinfo($ch);
        curl_close($ch);

        if (empty($responseInfo['http_code']) || !in_array($responseInfo['http_code'], [200, 201, 204])) {
            $GLOBALS['log']->error(
                "Shopware6 upsertProperty failed. URL: '$apiHost/api/property-group'. Headers: '" . json_encode($headers) . "'. Body: '" . json_encode($body) . "'"
            );
        }
    }

    protected function validateProductAttributeValue(Entity $pav, EntityCollection $pavs, string $channelId, string $language): bool
    {
        if ($pav->get('value') === null || $pav->get('value') === '') {
            return false;
        }

        if (!empty($pav->get('attributeIsMultilang')) && $language !== $pav->get('language')) {
            return false;
        }

        if (empty($channelId) && !empty($pav->get('channelId'))) {
            return false;
        }

        if (!empty($channelId)) {
            if ($pav->get('scope') == 'Channel' && $pav->get('channelId') != $channelId) {
                return false;
            }

            if ($pav->get('scope') == 'Global') {
                $mainPav = $this
                    ->getInjection('entityManager')
                    ->getRepository('ProductAttributeValue')
                    ->select(['id'])
                    ->where([
                        'language' => $pav->get('language'),
                        'attributeId' => $pav->get('attributeId'),
                        'productId' => $pav->get('productId'),
                        'channelId' => $channelId
                    ])
                    ->findOne();

                if (!empty($mainPav)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function getApiHost(): string
    {
        $apiUrlData = parse_url($this->getFeedData()['httpUrl']);

        return $apiUrlData['scheme'] . '://' . $apiUrlData['host'];
    }

    protected function getHeaders(string $language): array
    {
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

        return $headers;
    }

    protected function preparePropertyOptionValue(Entity $pav)
    {
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

        return $value;
    }
}
