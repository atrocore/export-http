<?php

namespace ExportHttp\TwigFunction;

use Espo\ORM\Entity;
use ExportHttp\TwigFilter\Shopware6Uuid;

class Shopware6GetConfiguratorSettings extends AbstractTwigFunction
{
    public function __construct()
    {
        parent::__construct();

        $this->addDependency(Shopware6Uuid::class);
    }

    public function run($product): array
    {
        $result = [];

        if (!$product instanceof Entity) {
            return $result;
        }

//        $uuid = !empty($shopwareId) ? $shopwareId : $this->getInjection(Shopware6Uuid::class)->filter($product->get('id'));
        $uuid = !empty($shopwareId) ? $shopwareId : $this->getInjection(Shopware6Uuid::class)->filter('27b99e11dcfa4742811faf54d3189744');

//        $apiUrlData = parse_url($this->getFeedData()['httpUrl']);
//        $apiHost = $apiUrlData['scheme'] . '://' . $apiUrlData['host'];

        $apiHost = 'https://shop.scanholz.com';

        $connectionData = $this->getConnectionData();

        $headers = [
            'Content-Type: application/json',
            "Authorization: {$connectionData['token_type']} {$connectionData['access_token']}"
        ];

        $ch = curl_init("$apiHost/api/search/product");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'ids' => $uuid,
            'associations' => [
                'configuratorSettings' => [
                    'associations' => [
                        'option' => ['total-count-mode' => 1]
                    ],
                    'total-count-mode' => 1
                ]
            ]
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $responseInfo = curl_getinfo($ch);
        curl_close($ch);

        if (!empty($responseInfo['http_code']) && $responseInfo['http_code'] === 200) {
            $data = @json_decode($response, true);
            if (!empty($data['included'] && is_array($data['included']))) {
                foreach ($data['included'] as $item) {
                    if (isset($item['type']) && $item['type'] == 'product_configurator_setting' && isset($item['attributes'])) {
                        $optionId = $item['attributes']['optionId'] ?? null;

                        if (!empty($optionId)) {
                            $result[$optionId] = $item['id'];
                        }
                    }
                }
            }
        }

        return $result;
    }
}
