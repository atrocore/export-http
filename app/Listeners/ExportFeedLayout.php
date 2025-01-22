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

use Atro\Listeners\AbstractLayoutListener;
use Espo\Core\Utils\Json;
use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class ExportFeedLayout extends AbstractLayoutListener
{
    protected function detail(Event $event): void
    {
        $result = $event->getArgument('result');

        $result[1]['rows'][] = [['name' => 'httpMethod'], ['name' => 'httpConnectionId']];
        $result[1]['rows'][] = [['name' => 'httpUrl', 'fullWidth' => true]];

        if ($this->getMetadata()->get(['entityDefs', 'ExportFeed', 'fields', 'processResponse'])) {
            $result[0]['rows'][] = [['name' => 'processResponse'], ['name' => 'processResponseFormatter']];
        }

        $result[0]['rows'][] = [['name' => 'exportHttpValidator'], false];


        $event->setArgument('result',  $result);
    }

    protected function relationships(Event $event): void
    {
        $result = $event->getArgument('result');

        $result = array_merge([['name' => 'exportHttpHeaders', 'canClose' => false]], $result);

        $event->setArgument('result',  $result);
    }
}
