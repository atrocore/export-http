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

namespace ExportHttp\TwigFilter;

use Dam\Core\Download\Custom;
use Espo\ORM\Entity;
use Export\TwigFilter\AbstractTwigFilter;

class ImageUrl extends AbstractTwigFilter
{
    public function __construct()
    {
        $this->addDependency('config');
        $this->addDependency('entityManager');
        $this->addDependency(Shopware6Uuid::class);
        $this->addDependency(Custom::class);
    }

    public function filter($value)
    {
        if (empty($value) || !is_object($value) || !($value instanceof Entity) || $value->getEntityType() !== 'Asset') {
            return false;
        }

        $attachmentId = $value->get('fileId');
        $attachment = $this->getInjection('entityManager')->getRepository('Attachment')->get($attachmentId);
        if (empty($attachment)) {
            return false;
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
            return false;
        }

        return rtrim($this->getInjection('config')->get('siteUrl', ''), '/') . '/' . $filePath;
    }
}
