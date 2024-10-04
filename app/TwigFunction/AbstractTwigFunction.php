<?php
/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore GmbH.
 *
 * This Software is the property of AtroCore GmbH and is
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
use Atro\Core\KeyValueStorages\StorageInterface;

abstract class AbstractTwigFunction extends \Export\TwigFunction\AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('entityManager');
        $this->addDependency(ConnectionOauth2::class);
        $this->addDependency('memcachedStorage');
    }

    public function getConnectionData(): array
    {
        $connectionData = [];
        $feedData = $this->getFeedData();

        if (array_key_exists('data' , $feedData) && is_array($feedData['data']) && array_key_exists('feedFields', $feedData['data']) && is_array($feedData['data']['feedFields'])) {
            $connectionId = $this->getFeedData()['data']['feedFields']['httpConnectionId'] ?? null;

            if (!empty($connectionId)) {
                if ($this->getMemoryStorage()->has('access_token_' . $connectionId)) {
                    return $this->getMemoryStorage()->get('access_token_' . $connectionId);
                }

                $connectionEntity = $this->getInjection('entityManager')->getEntity('Connection', $connectionId);
                if (!empty($connectionEntity)) {
                    $connectionData = $this->getInjection(ConnectionOauth2::class)->connect($connectionEntity);
                    $this->getMemoryStorage()->set('access_token_' . $connectionId, $connectionData, (int)$connectionData['expires_in'] ?? 600);
                }
            }
        }

        return $connectionData;
    }

    /**
     * @return StorageInterface
     */
    protected function getMemoryStorage(): StorageInterface
    {
        return $this->getInjection('memcachedStorage');
    }
}
