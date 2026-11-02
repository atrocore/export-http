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
use Atro\Listeners\AbstractLayoutListener;

/**
 * The response of the request is processed by an import feed, it is available when the Import module is installed.
 */
class ExportFeedTypeHttpProLayout extends AbstractLayoutListener
{
    public function detail(Event $event): void
    {
        if (empty($this->getMetadata()->get(['entityDefs', 'ExportFeedTypeHttpPro', 'fields', 'processResponse']))) {
            return;
        }

        $result = $event->getArgument('result');
        $result[0]['rows'][] = [['name' => 'processResponse'], ['name' => 'processResponseFormatter']];
        $event->setArgument('result', $result);
    }
}
