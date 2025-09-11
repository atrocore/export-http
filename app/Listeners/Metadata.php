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

use Atro\Listeners\AbstractListener;
use Atro\Core\EventManager\Event;

class Metadata extends AbstractListener
{
    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        if (!empty($data['entityDefs']['ImportFeed'])) {
            $data['entityDefs']['ExportFeed']['fields']['processResponse'] = [
                'type'                  => 'link',
                'conditionalProperties' => [
                    'visible' => [
                        'conditionGroup' => [
                            [
                                'type'      => 'in',
                                'attribute' => 'type',
                                'value'     => [
                                    'httpPro',
                                ],
                            ],
                        ],
                    ],

                ],
            ];
            $data['entityDefs']['ExportFeed']['links']['processResponse'] = [
                'type'   => 'belongsTo',
                'entity' => 'ImportFeed',
            ];

            $data['entityDefs']['ExportFeed']['fields']['processResponseFormatter'] = [
                'type'                  => 'text',
                "view"                  => "views/fields/script",
                'conditionalProperties' => [
                    'visible' => [
                        'conditionGroup' => [
                            [
                                'type'      => 'isNotEmpty',
                                'attribute' => 'processResponseId',
                            ],
                        ],
                    ],
                ],
            ];
        }

        $event->setArgument('data', $data);
    }
}
