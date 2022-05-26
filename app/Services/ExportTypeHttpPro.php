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
use Espo\Core\Utils\Util;
use Espo\Entities\Attachment;
use Espo\ORM\EntityCollection;
use Export\Entities\ExportJob;

class ExportTypeHttpPro extends \Export\Services\AbstractExportType
{
    private int $iteration = 0;

    public function export(array $data, ExportJob $exportJob): Attachment
    {
        $this->setData($data);

        return $this->runExport($exportJob);
    }

    public function runExport(ExportJob $exportJob): Attachment
    {
        $entities = new EntityCollection();
        while (!empty($collection = $this->getCollection())) {
            foreach ($collection as $entity) {
                $entities->append($entity);
            }
        }

        $exportJob->set('count', count($entities));

        $template = $this->data['feed']['data']['feedFields']['exportHttpMustacheBody'];
        $templateData = [
            'entities' => $entities,
            'config'   => $this->getConfig()->getData(),
        ];

        $mustache = new \Mustache_Engine([
            'entity_flags' => ENT_QUOTES,
            'helpers'      => [],
        ]);
        $body = $mustache->render($template, $templateData);
        $body = preg_replace("/}[\n\s]*,[\n\s]*]/", "}]", $body);
        $bodyArray = @json_decode($body, true);
        if (!empty($bodyArray)) {
            $body = json_encode($bodyArray);
        }

        /**
         * Create attachment
         */
        $repository = $this->getEntityManager()->getRepository('Attachment');
        $attachment = $repository->get();
        $attachment->set('name', $this->getExportFileName('json'));
        $attachment->set('role', 'Export');
        $attachment->set('relatedType', 'ExportJob');
        $attachment->set('relatedId', $this->data['id']);
        $attachment->set('storage', 'UploadDir');
        $attachment->set('storageFilePath', $this->createPath());
        $fileName = $repository->getFilePath($attachment);
        $this->createDir($fileName);
        file_put_contents($fileName, $body);
        $attachment->set('type', 'application/json');
        $attachment->set('size', \filesize($repository->getFilePath($attachment)));
        $this->getEntityManager()->saveEntity($attachment);
        $exportJob->set('fileId', $attachment->get('id'));

        /**
         * Prepare headers
         */
        $headers = ['Content-Type: application/json'];
        if (!empty($this->data['feed']['httpHeaders'])) {
            foreach ($this->data['feed']['httpHeaders'] as $v) {
                $headers[] = "{$v['key']}: {$v['value']}";
            }
        }

        if (!empty($this->data['feed']['data']['feedFields']['httpConnectionId'])) {
            $connectionEntity = $this->getEntityManager()->getEntity('Connection', $this->data['feed']['data']['feedFields']['httpConnectionId']);
            if (!empty($connectionEntity)) {
                $response = $this->getInjection(ConnectionOauth2::class)->connect($connectionEntity);
                $headers[] = "Authorization: {$response['token_type']} {$response['access_token']}";
            }
        }

        /**
         * Send request
         */
        $ch = curl_init($this->data['feed']['httpUrl']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->data['feed']['httpMethod']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
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

    protected function createDir(string $fileName): void
    {
        $parts = explode('/', $fileName);
        array_pop($parts);
        Util::createDir(implode('/', $parts));
    }

    protected function getCollection(): ?EntityCollection
    {
        if (!empty($this->data['feed']['separateJob']) && !empty($this->iteration)) {
            return null;
        }

        if (!$this->getContainer()->get('acl')->check($this->data['feed']['entity'], 'read')) {
            return null;
        }

        $params = $this->getSelectParams();
        $params['offset'] = $this->data['offset'];
        $params['maxSize'] = $this->data['limit'];

        $this->data['offset'] = $this->data['offset'] + $this->data['limit'];
        $this->iteration++;

        $result = $this->getEntityService()->findEntities($params);
        if (isset($result['collection']) && count($result['collection']) > 0) {
            return $result['collection'];
        }

        return null;
    }
}
