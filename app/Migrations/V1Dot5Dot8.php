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

class V1Dot5Dot8 extends Base
{
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        try {
            $fromSchema->getTable('import_feed');
        } catch (\Exception $e) {
            return;
        }

        $toSchema = clone $fromSchema;

        $this->addColumn($toSchema, 'export_feed', 'process_response_formatter', ['type' => 'text', 'default' => null]);

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }
    }

    public function down(): void
    {
        $fromSchema = $this->getCurrentSchema();
        try {
            $fromSchema->getTable('import_feed');
        } catch (\Exception $e) {
            return;
        }

        $toSchema = clone $fromSchema;

        $this->dropColumn($toSchema, 'export_feed', 'process_response_formatter');

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }
    }

}
