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

class V1Dot4Dot0 extends Base
{
    public function up(): void
    {
        $records = $this
            ->getSchema()
            ->getConnection()
            ->createQueryBuilder()
            ->select('*')
            ->from('export_feed')
            ->where('deleted=0')
            ->fetchAllAssociative();

        foreach ($records as $record) {
            $data = [];
            if (!empty($record['data'])) {
                $array = @json_decode((string)$record['data'], true);
                if (!empty($array)) {
                    $data = $array;
                }
            }

            if (!empty($data['feedFields']['httpContentType'])) {
                $this
                    ->getSchema()
                    ->getConnection()
                    ->createQueryBuilder()
                    ->update('export_feed')
                    ->set('file_type', ':file_type')->setParameter('file_type', 'json')
                    ->where('id=:id')->setParameter('id', $record['id'])
                    ->executeQuery();
            }

            if (!empty($data['feedFields']['exportHttpTwigBody'])) {
                $this
                    ->getSchema()
                    ->getConnection()
                    ->createQueryBuilder()
                    ->update('export_feed')
                    ->set('template', ':template')->setParameter('template', $data['feedFields']['exportHttpTwigBody'])
                    ->where('id=:id')->setParameter('id', $record['id'])
                    ->executeQuery();
            }
        }
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }
}
