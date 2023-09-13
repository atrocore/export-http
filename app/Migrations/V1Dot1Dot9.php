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

namespace ExportHttp\Migrations;

use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Metadata;
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
