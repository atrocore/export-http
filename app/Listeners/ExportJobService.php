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

namespace ExportHttp\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;
use Espo\Core\Acl;

class ExportJobService extends AbstractListener
{
    public function beforePutAclMetaForLink(Event $event): void
    {
        $entity = $event->getArgument('entity');
        $entityFrom = $event->getArgument('entityFrom');
        $link = $event->getArgument('link');

        if ($entityFrom->getEntityName() !== 'ExportFeed' || $link !== 'exportJobs') {
            return;
        }

        $condition = $entityFrom->get('type') === 'httpPro'
            &&  in_array($entity->get('state'), ['Failed', 'Canceled'])
            && ($this->getUser()->isAdmin() ?? $this->getAcl()->check($entity, 'edit'))
            && $entity->get('fileId') && $entity->get('requestUrl');

        $entity->setMetaPermission('trySendRequestAgain', $condition);

    }

    protected function getAcl(): Acl
    {
        return $this->getContainer()->get('acl');
    }
}
