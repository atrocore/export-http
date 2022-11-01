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

namespace ExportHttp\Services;

use Espo\ConnectionType\ConnectionOauth2;
use Espo\Core\Exceptions\BadRequest;
use Espo\Entities\Attachment;
use Export\Entities\ExportJob;

class ExportTypeHttpPro extends \Export\Services\ExportTypeSimple
{
    public function export(array $data, ExportJob $exportJob): Attachment
    {
        $attachment = parent::export($data, $exportJob);

        // prepare URL
        $url = $this->renderTemplateContents((string)$this->data['feed']['httpUrl'], ['entities' => $this->getFullCollection()]);

        /**
         * Prepare headers
         */
        $headers = ['Content-Type: ' . $attachment->get('type')];
        if (!empty($this->data['feed']['httpHeaders'])) {
            foreach ($this->data['feed']['httpHeaders'] as $v) {
                $headers[] = "{$v['key']}: {$v['value']}";
            }
        }
        if (!empty($this->data['feed']['data']['feedFields']['httpConnectionId'])) {
            $connectionEntity = $this->getEntityManager()->getEntity('Connection', $this->data['feed']['data']['feedFields']['httpConnectionId']);
            if (!empty($connectionEntity)) {
                $connectionData = $this->getInjection(ConnectionOauth2::class)->connect($connectionEntity);
                $headers[] = "Authorization: {$connectionData['token_type']} {$connectionData['access_token']}";
            }
        }

        /**
         * Send request
         */
        $ch = curl_init($this->data['feed']['httpUrl']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->data['feed']['httpMethod']);
        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $output = curl_exec($ch);
        if ($output === false) {
            throw new BadRequest('Curl error: ' . curl_error($ch));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!in_array($httpCode, [200, 201, 204])) {
            throw new BadRequest("Response Code: $httpCode Body: $output");
        }

        $exportJob->set('stateMessage', $output);

        return $attachment;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency(ConnectionOauth2::class);
    }
}
