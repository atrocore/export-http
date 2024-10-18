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
        $this->addDependency('connectionFactory');
    }

    public function getConnectionData(): array
    {
        $connectionData = [];
        
        $connectionId = $this->getFeedData()['data']['feedFields']['httpConnectionId'] ?? null;
        if (!empty($connectionId)) {
            if ($this->getMemoryStorage()->has('access_token_' . $connectionId)) {
                return $this->getMemoryStorage()->get('access_token_' . $connectionId);
            }

            $connectionEntity = $this->getInjection('entityManager')->getEntity('Connection', $connectionId);
            if (!empty($connectionEntity)) {
                $connection = $this->getInjection('connectionFactory')->create($connectionEntity);
                $connectionData = $connection->connect($connectionEntity);
                $this->getMemoryStorage()->set('access_token_' . $connectionId, $connectionData, (int)$connectionData['expires_in'] ?? 600);
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
