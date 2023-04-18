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

class Shopware6GetToDeleteVariantProperty extends AbstractTwigFunction
{
    public function __construct()
    {
        parent::__construct();

        $this->addDependency(Shopware6Uuid::class);
    }

    public function run($uuid, $optionsIds): array
    {
        $result = [];

        if (empty($uuid) || !is_string($uuid)) {
            return $result;
        }

        if (empty($optionsIds) || !is_array($optionsIds)) {
            $optionsIds = [];
        }

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
            if (!empty($data['data'][0]['attributes']['optionIds'])) {
                foreach ($data['data'][0]['attributes']['optionIds'] as $id) {
                    if (!in_array($id, $optionsIds)) {
                        $result[] = $id;
                    }
                }
            }
        }

        return $result;
    }
}
