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

use Atro\Listeners\AbstractListener;
use Atro\Core\EventManager\Event;

class Metadata extends AbstractListener
{
    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        if (!empty($data['entityDefs']['ImportFeed'])) {
            $data['entityDefs']['ExportFeed']['fields']['processResponse'] = [
                'type' => 'link'
            ];
            $data['entityDefs']['ExportFeed']['links']['processResponse'] = [
                'type'   => 'belongsTo',
                'entity' => 'ImportFeed'
            ];
            $data['clientDefs']['ExportFeed']['dynamicLogic']['fields']['processResponse']['visible']['conditionGroup'] = [
                [
                    'type'      => 'in',
                    'attribute' => 'type',
                    'value'     => 'httpPro'
                ]
            ];

            $data['entityDefs']['ExportFeed']['fields']['processResponseFormatter'] = [
                'type' => 'text',
                "view" => "views/fields/script",
            ];
            $data['clientDefs']['ExportFeed']['dynamicLogic']['fields']['processResponseFormatter']['visible']['conditionGroup'] = [
                [
                    'type'      => 'isNotEmpty',
                    'attribute' => 'processResponse'
                ]
            ];
        }

        $event->setArgument('data', $data);
    }
}
