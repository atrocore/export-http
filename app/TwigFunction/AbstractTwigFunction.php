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

use Atro\ConnectionType\ConnectionHttp;
use Atro\ConnectionType\ConnectionOauth2;
use Atro\ConnectionType\HttpConnectionInterface;
use Atro\Core\KeyValueStorages\StorageInterface;

abstract class AbstractTwigFunction extends \Export\TwigFunction\AbstractTwigFunction
{
    protected  function getHeaders(): array
    {
        $feed = $this->getFeedData();

        $headers = [];
        if (!empty($feed['httpHeaders'])) {
            foreach ($feed['httpHeaders'] as $v) {
                $headers[] = "{$v['key']}: {$v['value']}";
            }
        }
        return $headers;
    }

    protected function createConnection(?string $connectionId = null): HttpConnectionInterface
    {
        if (empty($connectionId)) {
            return $this->container->get(ConnectionHttp::class);
        }

        return $this->container->get('connectionFactory')->createById($connectionId);
    }
}
