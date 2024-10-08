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

class V1Dot5Dot5 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE export_feed ADD process_response_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_PROCESS_RESPONSE_ID ON export_feed (process_response_id)");
        $this->exec("CREATE INDEX IDX_PROCESS_RESPONSE_ID_DELETE ON export_feed (process_response_id, deleted)");
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
