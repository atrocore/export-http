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

namespace ExportHttp\Listeners;

use Treo\Core\EventManager\Event;

class Metadata extends \Treo\Listeners\AbstractListener
{
    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        $data['entityDefs']['ExportFeed']['fields']['type']['options'][] = 'http';

        $data['clientDefs']['ExportFeed']['dynamicLogic']['fields']['fieldDelimiterForRelation']['visible']['conditionGroup'][0]['value'][] = [
            'type'  => 'and',
            'value' => [
                [
                    'type'      => 'equals',
                    'attribute' => 'type',
                    'value'     => 'http',
                ],
                [
                    'type'      => 'isTrue',
                    'attribute' => 'convertRelationsToString'
                ]
            ],
        ];

        $data['clientDefs']['ExportFeed']['dynamicLogic']['fields']['fieldDelimiterForRelation']['required']['conditionGroup'][0]['value'][] = [
            'type'  => 'and',
            'value' => [
                [
                    'type'      => 'equals',
                    'attribute' => 'type',
                    'value'     => 'http',
                ],
                [
                    'type'      => 'isTrue',
                    'attribute' => 'convertRelationsToString'
                ]
            ],
        ];

        $data['clientDefs']['ExportFeed']['dynamicLogic']['fields']['delimiter']['visible']['conditionGroup'][0]['value'][] = [
            'type'  => 'and',
            'value' => [
                [
                    'type'      => 'equals',
                    'attribute' => 'type',
                    'value'     => 'http',
                ],
                [
                    'type'      => 'isTrue',
                    'attribute' => 'convertCollectionToString'
                ]
            ],
        ];

        $data['clientDefs']['ExportFeed']['dynamicLogic']['fields']['delimiter']['required']['conditionGroup'][0]['value'][] = [
            'type'  => 'and',
            'value' => [
                [
                    'type'      => 'equals',
                    'attribute' => 'type',
                    'value'     => 'http',
                ],
                [
                    'type'      => 'isTrue',
                    'attribute' => 'convertCollectionToString'
                ]
            ],
        ];

        $event->setArgument('data', $data);
    }
}
