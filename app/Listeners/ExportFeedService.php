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

class ExportFeedService extends \Atro\Listeners\AbstractListener
{
    public function prepareFeedData(Event $event): void
    {
        $result = $event->getArgument('result');
        $result['httpHeaders'] = [];

        if (!empty($headers = $event->getArgument('feed')->get('exportHttpHeaders')) && count($headers) > 0) {
            foreach ($headers as $header) {
                $result['httpHeaders'][] = [
                    'key'   => $header->get('name'),
                    'value' => $header->get('value'),
                ];
            }
        };

        $event->setArgument('result', $result);
    }
}
