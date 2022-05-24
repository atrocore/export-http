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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\Entities\Attachment;
use Export\Entities\ExportJob;

class ExportTypeHttpPro extends \Export\Services\AbstractExportType
{
    public function export(array $data, ExportJob $exportJob): Attachment
    {
        $m = new \Mustache_Engine(['entity_flags' => ENT_QUOTES]);

        $template = '{{#entities}}{{#.}}{{#brand}}{{code}}{{/brand}}{{/.}}{{/entities}}';

        echo $m->render($template, ['entities' => $this->getEntityManager()->getRepository('Product')->find()]);
        die();


        $body = $data['feed']['data']['feedFields']['exportHttpBody'];

        echo '<pre>';
        print_r($str);
        die();
    }

    public function runExport(ExportJob $exportJob): Attachment
    {
        $repository = $this->getEntityManager()->getRepository('Attachment');

        // create attachment
        $attachment = $repository->get();
        $attachment->set('name', $this->getExportFileName('json'));
        $attachment->set('role', 'Export');
        $attachment->set('relatedType', 'ExportJob');
        $attachment->set('relatedId', $this->data['id']);
        $attachment->set('storage', 'UploadDir');
        $attachment->set('storageFilePath', $this->createPath());

        $fileName = $repository->getFilePath($attachment);

        $this->storeJsonFile($exportJob->getData(), $fileName);

        $attachment->set('type', 'application/json');
        $attachment->set('size', \filesize($repository->getFilePath($attachment)));

        $this->getEntityManager()->saveEntity($attachment);

        $ch = curl_init($this->data['feed']['httpUrl']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->data['feed']['httpMethod']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($fileName));

        $headers = ['Content-Type: application/json'];
        if (!empty($this->data['feed']['httpHeaders'])) {
            foreach ($this->data['feed']['httpHeaders'] as $v) {
                $headers[] = "{$v['key']}: {$v['value']}";
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $output = curl_exec($ch);

        if ($output === false) {
            throw new BadRequest('Curl error: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!in_array($httpCode, [200, 201])) {
            throw new BadRequest("Response Code: $httpCode Body: $output");
        }

        $exportJob->set('stateMessage', $output);

        curl_close($ch);

        return $attachment;
    }

    protected function storeJsonFile(array $data, string $fileName): void
    {
        $this->createDir($fileName);

        $result = [];

        $cacheFile = fopen($data['fullFileName'], "r");
        while (($json = fgets($cacheFile)) !== false) {
            if (empty($json)) {
                continue;
            }
            $record = Json::decode($json, true);

            $row = [];
            foreach ($data['configuration'] as $item) {
                $row = array_merge($row, $this->convertor->convert($record, $item));
            }

            $result[] = $row;
        }

        fclose($cacheFile);

        if ($this->data['feed']['limit'] === 1 && !empty($this->data['feed']['separateJob'])) {
            $result = array_shift($result);
        }

        file_put_contents($fileName, str_replace("{{configurator}}", Json::encode($result), $this->data['feed']['exportHttpBody']));
    }

    protected function createDir(string $fileName): void
    {
        $parts = explode('/', $fileName);
        array_pop($parts);

        Util::createDir(implode('/', $parts));
    }
}
