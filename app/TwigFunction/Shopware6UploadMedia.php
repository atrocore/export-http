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

class Shopware6UploadMedia extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('entityManager');
        $this->addDependency('config');
        $this->addDependency(Shopware6Uuid::class);
    }

    public function run(...$args)
    {
        if (empty($args[0])) {
            return null;
        }

        $assetId = $args[0];

        $asset = $this->getInjection('entityManager')->getRepository('Asset')->get($assetId);
        if (empty($asset)) {
            return null;
        }

        $attachment = $asset->get('file');
        $attachment->set('private', false);

        $pathData = $this->getInjection('entityManager')->getRepository('Attachment')->getAttachmentPathsData($attachment);
        if (empty($pathData['download'])) {
            return null;
        }

        $dirs = explode('/', $pathData['download']);
        $fileNameWithExtension = array_pop($dirs);

        $url = rtrim($this->getInjection('config')->get('siteUrl', ''), '/') . '/' . implode('/', $dirs) . '/' . rawurlencode($fileNameWithExtension);

        $siteUrlData = parse_url($this->getFeedData()['httpUrl']);
        $siteUrl = $siteUrlData['scheme'] . '://' . $siteUrlData['host'];

        $uuid = $this->getInjection(Shopware6Uuid::class)->filter($assetId);

        $connectionData = $this->getConnectionData();

        $headers = [
            'Content-Type: application/json',
            "Authorization: {$connectionData['token_type']} {$connectionData['access_token']}"
        ];

        /**
         * Create shopware media ID
         */
        $ch = curl_init("$siteUrl/api/media");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":"' . $uuid . '"}');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_exec($ch);
        curl_close($ch);

        $nameParts = explode('.', $fileNameWithExtension);
        $extension = array_pop($nameParts);

        $fileName = urlencode(implode('.', $nameParts));

        /**
         * Upload asset to shopware media
         */
        $ch = curl_init("$siteUrl/api/_action/media/$uuid/upload?extension=$extension&fileName=$fileName");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{"url":"' . $url . '"}');
        $response = curl_exec($ch);
        $responseInfo = curl_getinfo($ch);
        curl_close($ch);

        return $uuid;
    }
}
