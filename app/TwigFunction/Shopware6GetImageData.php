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

use Dam\Core\Download\Custom;
use Dam\Entities\Asset;
use Espo\Core\Utils\Util;

class Shopware6GetImageData extends AbstractTwigFunction
{
    public function __construct()
    {
        parent::__construct();

        $this->addDependency('entityManager');
        $this->addDependency(Custom::class);
    }

    public function run(Asset $asset): array
    {
        $attachmentId = $asset->get('fileId');
        $attachment = $this->getInjection('entityManager')->getRepository('Attachment')->get($attachmentId);
        if (empty($attachment)) {
            return [];
        }

        try {
            $converter = $this->getInjection(Custom::class)->setAttachment($attachment);
            $parameters = ['quality' => 90, 'format' => 'jpeg'];
            if ($converter->getImageWidth() > 1600) {
                $parameters['width'] = 1600;
                $parameters['scale'] = 'byWidth';
            }

            $filePath = $converter->setParams($parameters)->convert()->getFilePath();
        } catch (\Throwable $e) {
            return [];
        }

        $dirs = explode('/', $filePath);
        $fileNameWithExtension = array_pop($dirs);

        $nameParts = explode('.', $fileNameWithExtension);
        $extension = array_pop($nameParts);

        // prepare filename
        $fileName = implode('.', $nameParts);
        $fileName = Util::replaceDiacriticalCharacters($fileName);
        $fileName = preg_replace("/[^a-zA-Z0-9\.\_]+/", "", $fileName);
        $fileName = $fileName . '_' . $asset->get('id');

        return [
            'filename' => $fileName,
            'extension' => $extension
        ];
    }
}

