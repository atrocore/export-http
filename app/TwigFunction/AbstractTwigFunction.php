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

namespace ExportHttp\TwigFunction;

use Atro\ConnectionType\ConnectionOauth2;

abstract class AbstractTwigFunction extends \Export\TwigFunction\AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('entityManager');
        $this->addDependency(ConnectionOauth2::class);
    }

    public function getConnectionData(): array
    {
        $connectionData = [];
        if (!empty($this->getFeedData()['data']['feedFields']['httpConnectionId'])) {
            $connectionEntity = $this->getInjection('entityManager')->getEntity('Connection', $this->getFeedData()['data']['feedFields']['httpConnectionId']);
            if (!empty($connectionEntity)) {
                $connectionData = $this->getInjection(ConnectionOauth2::class)->connect($connectionEntity);
            }
        }

        return $connectionData;
    }
}
