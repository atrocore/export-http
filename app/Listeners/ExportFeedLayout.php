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
    public function detail(Event $event): void
    {
        $result = $event->getArgument('result');

        $result[1]['rows'][count($result[1]['rows']) - 1][] = ['name' => 'httpMethod'];
        $result[1]['rows'][] = [['name' => 'httpUrl', 'fullWidth' => true]];

        if ($this->getMetadata()->get(['entityDefs', 'ExportFeed', 'fields', 'processResponse'])) {
            $result[0]['rows'][] = [['name' => 'processResponse'], ['name' => 'processResponseFormatter']];
        }

        $result[0]['rows'][] = [['name' => 'exportHttpValidator'], false];


        $event->setArgument('result', $result);
    }

    public function relationships(Event $event): void
    {
        $result = $event->getArgument('result');

        $result = array_merge([['name' => 'exportHttpHeaders', 'canClose' => false]], $result);

        $event->setArgument('result', $result);
    }
}
