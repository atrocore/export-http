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

use Atro\ConnectionType\AbstractConnection;
use Atro\ConnectionType\ConnectionAtroCore;
use Atro\ConnectionType\ConnectionHttp;
use Atro\ConnectionType\HttpConnectionInterface;
use Atro\DTO\HttpResponseDTO;
use Espo\Core\Exceptions\BadRequest;
use Espo\Entities\Attachment;
use Export\Entities\ExportFeed;
use Export\Entities\ExportJob;
use Import\Services\ImportFeed;

class ExportTypeHttpPro extends \Export\Services\ExportTypeSimple
{
    public function export(array $data, ExportJob $exportJob): Attachment
    {
        $attachment = parent::export($data, $exportJob);

        // save file to export job
        $exportJob->set('fileId', $attachment->get('id'));
        $this->getEntityManager()->saveEntity($exportJob);

        if (!empty($this->data['feed']['separateJob'])) {
            $entities = $this->getCollection();
        } else {
            $entities = $this->getFullCollection();
        }

        // prepare URL
        $url = $this->renderTemplateContents((string)$this->data['feed']['httpUrl'], ['entities' => $entities]);

        // get file contents
        $contents = file_get_contents($this->getEntityManager()->getRepository('Attachment')->getFilePath($attachment));

        /**
         * Prepare headers
         */
        $headers = ['Content-Type: ' . $attachment->get('type')];
        if (!empty($this->data['feed']['httpHeaders'])) {
            foreach ($this->data['feed']['httpHeaders'] as $v) {
                $headers[] = "{$v['key']}: {$v['value']}";
            }
        }

        $response = $this
            ->createConnection($this->data['feed']['data']['feedFields']['httpConnectionId'] ?? null)
            ->request($url, $this->data['feed']['httpMethod'], $headers, $contents);

        $httpCode = $response->getCode();
        $output = $response->getOutput();

        /** @var ExportFeed $exportFeed */
        $exportFeed = $exportJob->get('exportFeed');

        $exportHttpValidator = $exportFeed->get('exportHttpValidator');
        if (!empty($exportHttpValidator)) {
            $res = $this->renderTemplateContents($exportHttpValidator->get('validator'), ['httpCode' => $httpCode, 'responseText' => $output, 'entities' => $entities]);
            $res = trim($res);
            $success = strtolower($res) === 'true' || $res === '1';
            if (empty($success)) {
                throw new BadRequest("Validation failed for validator {$exportHttpValidator->get('name')}. \n Result: $res \n Response Code: $httpCode \n Body: $output");
            }
        }

        if (!empty($output) && !empty($importFeed = $exportFeed->get('processResponse'))) {
            $attachmentData = new \stdClass();

            $nameParts = $attachment->get('name');
            $nameParts = explode('.', $nameParts);
            array_pop($nameParts);

            $attachmentContents = $output;

            $formatter = $exportFeed->get('processResponseFormatter');
            if (!empty($formatter)) {
                $attachmentContents = $this->renderTemplateContents($formatter, ['responseText' => $output, 'entities' => $entities]);
            }

            $attachmentData->name = implode('.', $nameParts);
            $attachmentData->contents = $attachmentContents;
            $attachmentData->relatedType = 'ImportJob';
            $attachmentData->field = 'uploadedFile';
            $attachmentData->role = 'Attachment';

            switch ($importFeed->getFeedField('format')) {
                case 'CSV':
                    $attachmentData->name .= '.csv';
                    $attachmentData->type = 'text/csv';
                    break;
                case 'Excel':
                    $attachmentData->name .= '.xlsx';
                    $attachmentData->type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                    break;
                case 'JSON':
                    $attachmentData->name .= '.json';
                    $attachmentData->type = 'application/json';
                    break;
                case 'XML':
                    $attachmentData->name .= '.xml';
                    $attachmentData->type = 'application/xml';
                    break;
            }

            try {
                /** @var \Espo\Services\Attachment $attachmentService */
                $attachmentService = $this->getService('Attachment');

                if (!empty($attachmentImport = $attachmentService->createEntity($attachmentData))) {
                    /** @var ImportFeed $importFeedService */
                    $importFeedService = $this->getService('ImportFeed');

                    $importFeedService->pushJobs($importFeed, $attachmentImport->id);
                }
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('Response processing failed: ' . $e->getMessage());
            }
        }

        $exportJob->set('stateMessage', $output);

        return $attachment;
    }

    protected function createConnection(?string $httpConnectionId = null): HttpConnectionInterface
    {
        if (empty($httpConnectionId)) {
            return $this->getContainer()->get(ConnectionHttp::class);
        }

        return $this->getContainer()->get('connectionFactory')->createById($httpConnectionId);
    }
}
