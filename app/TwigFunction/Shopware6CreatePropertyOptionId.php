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

use ExportHttp\TwigFilter\Shopware6Uuid;

class Shopware6CreatePropertyOptionId extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('serviceFactory');
        $this->addDependency('entityManager');
        $this->addDependency(Shopware6Uuid::class);
    }

    public function run(...$args)
    {
        if (empty($args)) {
            return null;
        }

        $pavId = array_shift($args);
        $channelId = array_pop($args);

        $pav = $this->getInjection('serviceFactory')->create('ProductAttributeValue')->getEntity($pavId);
        if (empty($pav)) {
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

        $headers = [
            'Content-Type: application/json',
            "Authorization: {$connectionData['token_type']} {$connectionData['access_token']}"
        ];

        $propertyId = $this->getInjection(Shopware6Uuid::class)->filter($pav->get('attributeId'));

        $ch = curl_init("$apiHost/api/property-group");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt(
            $ch, CURLOPT_POSTFIELDS, json_encode([
                'id'                         => $propertyId,
                'name'                       => $pav->get('attributeName'),
                'displayType'                => 'text',
                'sortingType'                => 'alphanumeric',
                'filterable'                 => false,
                'visibleOnProductDetailPage' => true,
                'position'                   => 1
            ])
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_exec($ch);
        curl_close($ch);

        if (empty($pav->get('value')) && $pav->get('value') !== '0' && $pav->get('value') !== 0) {
            return null;
        }

        $value = $pav->get('value');
        if (is_bool($value)) {
            $value = $value ? 'Yes' : 'No';
        } elseif (is_array($value)) {
            $value = implode(', ', $value);
        } else {
            $value = (string)$value;
        }

        $optionId = $this->getInjection(Shopware6Uuid::class)->filter($pav->get('attributeId') . $pav->get('language') . md5($value));

        $ch = curl_init("$apiHost/api/property-group/$propertyId/options");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt(
            $ch, CURLOPT_POSTFIELDS, json_encode([
                'id'   => $optionId,
                'name' => $value
            ])
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_exec($ch);
        curl_close($ch);

        return $optionId;
    }
}
