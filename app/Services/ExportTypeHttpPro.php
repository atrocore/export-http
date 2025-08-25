<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace ExportHttp\Services;

use Atro\ConnectionType\ConnectionHttp;
use Atro\ConnectionType\HttpConnectionInterface;
use Atro\Entities\File;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\EntityCollection;
use Export\Entities\ExportFeed;
use Export\Entities\ExportJob;
use Import\Services\ImportFeed;
use stdClass;

class ExportTypeHttpPro extends \Export\Services\ExportTypeSimple
{
    public function export(array $data, ExportJob $exportJob): File
    {
        /** @var ExportFeed $exportFeed */
        $exportFeed = $exportJob->get('exportFeed');

        if (!empty($exportJob->get('shouldResend')) && !empty($exportJob->get('file')) && !empty($exportJob->get('requestUrl'))) {
            $this->setData($data);
            $this->convertor = $this->getDataConvertor();
            $attachment = $exportJob->get('file');
            $url = $exportJob->get('requestUrl');
            $exportJob->set('shouldResend', false);
            if ($this->shouldLoadEntities($exportFeed)) {
                $entities = $this->getEntities();
            }
        } else {
            $attachment = parent::export($data, $exportJob);
            // save file to export job
            $exportJob->set('fileId', $attachment->get('id'));
            $this->getEntityManager()->saveEntity($exportJob);

            $httpUrl = (string)$this->data['feed']['httpUrl'];
            $templateData = [];

            if ($this->shouldLoadEntities($exportFeed)) {
                $entities = $this->getEntities();
            }

            if (str_contains($httpUrl, 'entities') && isset($entities)) {
                $templateData['entities'] = $entities;
            }

            // prepare URL
            $url = $this->renderTemplateContents($httpUrl, $templateData);

            $exportJob->set('requestUrl', $url);
        }

        // get file contents
        $contents = $this->getEntityManager()->getRepository('File')->getContents($attachment);

        /**
         * Prepare headers
         */
        $headers = [];
        if (!empty($this->data['feed']['httpHeaders'])) {
            foreach ($this->data['feed']['httpHeaders'] as $v) {
                if (strtolower($v['key']) === 'content-type') {
                    $hasContentType = true;
                }
                $headers[] = "{$v['key']}: {$v['value']}";
            }
        }

        if (empty($hasContentType)) {
            $headers[] = 'Content-Type: ' . $attachment->get('mimeType');
        }

        $response = $this
            ->createConnection($this->data['feed']['data']['feedFields']['httpConnectionId'] ?? null)
            ->request($url, $this->data['feed']['httpMethod'], $headers, $contents, false);

        $httpCode = $response->getCode();
        $output = $response->getOutput();

        $exportHttpValidator = $exportFeed->get('exportHttpValidator');
        if (!empty($exportHttpValidator)) {
            $res = $this->renderTemplateContents($exportHttpValidator->get('validator'), ['httpCode' => $httpCode, 'responseText' => $output, 'entities' => $entities ?? []]);
            $res = trim($res);
            $success = strtolower($res) === 'true' || $res === '1';
            if (empty($success)) {
                throw new BadRequest("Validation failed for validator {$exportHttpValidator->get('name')}. \n Result: $res \n Response Code: $httpCode \n Body: $output");
            }
        } else {
            // Standard validation
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new BadRequest("Response Code: $httpCode Body: $output");
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
                $attachmentContents = $this->renderTemplateContents($formatter, [
                    'httpCode'     => $httpCode,
                    'responseText' => $output,
                    'entities'     => $entities ?? []
                ]);
            }

            $attachmentData->name = implode('.', $nameParts);
            $attachmentData->folderId = $this->createExportFileFolder($exportJob->get('exportFeed'))->get('id');
            $attachmentData->hidden = true;

            switch ($importFeed->getFeedField('format')) {
                case 'CSV':
                    $attachmentData->name .= '.csv';
                    $attachmentData->mimeType = 'text/csv';
                    break;
                case 'Excel':
                    $attachmentData->name .= '.xlsx';
                    $attachmentData->mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                    break;
                case 'JSON':
                    $attachmentData->name .= '.json';
                    $attachmentData->mimeType = 'application/json';
                    break;
                case 'XML':
                    $attachmentData->name .= '.xml';
                    $attachmentData->mimeType = 'application/xml';
                    break;
            }

            try {
                $fileData = $this->getService('File')->createFileViaContents($attachmentData, $attachmentContents);
                if (!empty($fileData['id'])) {
                    /** @var ImportFeed $importFeedService */
                    $importFeedService = $this->getService('ImportFeed');

                    $payload = new \stdClass();
                    $payload->executeNow = true;
                    $importFeedService->pushJobs($importFeed, $fileData['id'], $payload);
                }
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('Response processing failed: ' . $e->getMessage());
            }
        }

        $exportJob->set('stateMessage', $output);

        return $attachment;
    }


    protected function shouldLoadEntities(ExportFeed $exportFeed)
    {
        $httpUrl = (string)$this->data['feed']['httpUrl'];

        if (str_contains($httpUrl, 'entities')) {
            return true;
        }

        $exportHttpValidator = $exportFeed->get('exportHttpValidator');
        if (!empty($exportHttpValidator) && str_contains($exportHttpValidator->get('validator'), 'entities')) {
            return true;
        }

        $formatter = (string)$exportFeed->get('processResponseFormatter');
        if (str_contains($formatter, 'entities') && !empty($exportFeed->get('processResponse'))) {
            return true;
        }

        return false;
    }

    protected function getEntities(): ?EntityCollection
    {
        if (!empty($this->data['feed']['separateJob'])) {
            return $this->getCollection();
        } else {
            return $this->getFullCollection();
        }
    }

    protected function createConnection(?string $httpConnectionId = null): HttpConnectionInterface
    {
        if (empty($httpConnectionId)) {
            return $this->getContainer()->get(ConnectionHttp::class);
        }

        return $this->getContainer()->get('connectionFactory')->createById($httpConnectionId);
    }

}
