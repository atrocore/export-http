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

use Espo\Core\Utils\Json;
use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class LayoutController extends AbstractListener
{
    public function afterActionRead(Event $event): void
    {
        $scope = $event->getArgument('params')['scope'];

        $name = $event->getArgument('params')['name'];

        $method = 'modify' . $scope . ucfirst($name);

        if (method_exists($this, $method)) {
            $this->{$method}($event);
        }
    }

    protected function modifyExportFeedDetail(Event $event): void
    {
        $result = Json::decode($event->getArgument('result'), true);

        $result[1]['rows'][] = [['name' => 'httpMethod'], ['name' => 'httpConnectionId']];
        $result[1]['rows'][] = [['name' => 'httpUrl', 'fullWidth' => true]];

        if ($this->getMetadata()->get(['entityDefs', 'ExportFeed', 'fields', 'processResponse'])) {
            $result[0]['rows'][] = [['name' => 'processResponse'], ['name' => 'processResponseFormatter']];
        }

        $result[0]['rows'][] = [['name' => 'exportHttpValidator'], false];


        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyExportFeedRelationships(Event $event): void
    {
        $result = Json::decode($event->getArgument('result'), true);

        $result = array_merge([['name' => 'exportHttpHeaders']], $result);

        $event->setArgument('result', Json::encode($result));
    }
}
