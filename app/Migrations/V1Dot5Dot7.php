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
use Espo\Core\Exceptions\Error;

class V1Dot5Dot7 extends Base
{
    public function up(): void
    {
        $this->rebuild();
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }

}
