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

namespace ExportHttp\Migrations;

use Espo\Core\Exceptions\Error;
use Atro\Core\Migration\Base;

class V1Dot1Dot9 extends Base
{
    public function up(): void
    {
        try {
            $records = $this
                ->getPDO()
                ->query("SELECT id, `data` FROM `export_feed` WHERE deleted=0 AND `data` LIKE '%\"exportHttpMustacheBody\"%'")
                ->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $records = [];
        }

        foreach ($records as $record) {
            if (!empty($record['data'])) {
                $record['data'] = str_replace('"exportHttpMustacheBody"', '"exportHttpTwigBody"', $record['data']);
                $data = $this->getPDO()->quote($record['data']);
                $this->getPDO()->exec("UPDATE `export_feed` SET `data`=$data WHERE id='{$record['id']}'");
            }
        }
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }
}
