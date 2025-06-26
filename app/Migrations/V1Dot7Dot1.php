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

use Atro\Core\Migration\Base;
use Atro\Core\Exceptions\Error;

class V1Dot7Dot1 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-06-27 07:00:00');
    }
    public function up(): void
    {
        if($this->isPgSQL()) {
            $this->exec("ALTER TABLE export_http_header ALTER value TYPE TEXT");
        }else{
            $this->exec("ALTER TABLE export_http_header CHANGE value value LONGTEXT DEFAULT NULL");
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
