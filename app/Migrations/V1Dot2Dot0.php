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

class V1Dot2Dot0 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("DELETE FROM `export_feed` WHERE `type`='http'");
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }
}
