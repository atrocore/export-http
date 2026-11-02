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
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Ramsey\Uuid\Uuid;

class V1Dot11Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-02 12:00:00');
    }

    public function up(): void
    {
        $this->migrateExportFeed();
    }

    /**
     * The HTTP export feeds are a subtype of ExportFeed now: the connection, the request and the processing of the
     * response are moved to the table of the subtype.
     */
    protected function migrateExportFeed(): void
    {
        $columns = [
            'connectionId' => ['connection_id', 'type' => 'string', 'length' => 36, 'index' => true, 'source' => 'column'],
            'httpMethod' => ['http_method', 'type' => 'string', 'length' => 255, 'default' => 'POST', 'source' => 'data'],
            'httpUrl' => ['http_url', 'type' => 'text', 'source' => 'data'],
            'exportHttpValidatorId' => ['export_http_validator_id', 'type' => 'string', 'length' => 36, 'index' => true, 'source' => 'column'],
            'processResponseId' => ['process_response_id', 'type' => 'string', 'length' => 36, 'index' => true, 'source' => 'column'],
            'processResponseFormatter' => ['process_response_formatter', 'type' => 'text', 'source' => 'column'],
        ];

        $fromSchema = $this->getCurrentSchema();
        if (!$fromSchema->hasTable('export_feed')) {
            return;
        }

        $toSchema = clone $fromSchema;
        $this->createSubtypeTable($toSchema, 'export_feed', 'httpPro', $columns);

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        $records = $this->getDbal()->createQueryBuilder()
            ->select('*')
            ->from('export_feed')
            ->where('type = :type')
            ->setParameter('type', 'httpPro')
            ->fetchAllAssociative();

        foreach ($records as $record) {
            $this->insertSubtypeRecord('export_feed', 'httpPro', $record['id'], $columns, $this->getFeedSettings($record, $columns));
        }
    }

    /**
     * The settings of an export feed type: a setting was stored in a column of the export feed table or in the
     * "feedFields" of the "data" field.
     */
    protected function getFeedSettings(array $record, array $columns): array
    {
        $data = @json_decode((string)($record['data'] ?? ''), true);
        $feedFields = is_array($data['feedFields'] ?? null) ? $data['feedFields'] : [];

        $settings = [];
        foreach ($columns as $field => $options) {
            $settings[$field] = $options['source'] === 'data' ? ($feedFields[$field] ?? null) : ($record[$options[0]] ?? null);
        }

        return $settings;
    }

    /**
     * The table of a subtype: its own id, the unique link to the supertype record that is removed together with
     * it, the columns of the subtype fields.
     */
    protected function createSubtypeTable(Schema $schema, string $supertypeTable, string $type, array $columns): void
    {
        $tableName = $this->getSubtypeTableName($supertypeTable, $type);
        if ($schema->hasTable($tableName)) {
            return;
        }

        $table = $schema->createTable($tableName);
        $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('supertype_id', 'string', ['length' => 36, 'notnull' => false]);

        $indexes = [];
        foreach ($columns as $options) {
            $column = array_shift($options);
            $type = $options['type'];
            if (!empty($options['index'])) {
                $indexes[] = $column;
            }
            unset($options['type'], $options['index'], $options['source']);
            $table->addColumn($column, $type, array_merge(['notnull' => false], $options));
        }

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['supertype_id']);

        $foreignKeyName = $this->generateForeignKeyName($tableName . '_supertype_id');
        $table->addForeignKeyConstraint($supertypeTable, ['supertype_id'], ['id'], ['onDelete' => 'CASCADE'], $foreignKeyName);
        $table->addIndex(['supertype_id'], $foreignKeyName);

        foreach ($indexes as $column) {
            $table->addIndex([$column], $this->generateIndexName($tableName . '_' . $column));
        }
    }

    protected function insertSubtypeRecord(string $supertypeTable, string $type, string $supertypeId, array $columns, array $data): void
    {
        $tableName = $this->getSubtypeTableName($supertypeTable, $type);
        $conn = $this->getDbal();

        $exists = $conn->createQueryBuilder()
            ->select('id')
            ->from($tableName)
            ->where('supertype_id = :supertypeId')
            ->setParameter('supertypeId', $supertypeId)
            ->fetchOne();

        if ($exists !== false) {
            return;
        }

        $qb = $conn->createQueryBuilder()
            ->insert($tableName)
            ->setValue('id', ':id')
            ->setValue('supertype_id', ':supertypeId')
            ->setParameter('id', Uuid::uuid7()->toString())
            ->setParameter('supertypeId', $supertypeId);

        foreach ($columns as $field => $options) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            $value = $data[$field];
            $parameterType = ParameterType::STRING;

            switch ($options['type']) {
                case 'boolean':
                    $value = !empty($value);
                    $parameterType = ParameterType::BOOLEAN;
                    break;
                case 'integer':
                    if ($value === '') {
                        continue 2;
                    }
                    $value = (int)$value;
                    $parameterType = ParameterType::INTEGER;
                    break;
                case 'float':
                    if ($value === '') {
                        continue 2;
                    }
                    $value = (string)(float)$value;
                    break;
                case 'jsonArray':
                    $value = json_encode($value);
                    break;
                default:
                    $value = is_scalar($value) ? (string)$value : json_encode($value);
            }

            $qb->setValue($conn->quoteIdentifier($options[0]), ":$field");
            $qb->setParameter($field, $value, $parameterType);
        }

        $qb->executeStatement();
    }

    protected function getSubtypeTableName(string $supertypeTable, string $type): string
    {
        return $supertypeTable . '_type_' . strtolower(preg_replace('/([A-Z])/', '_$1', $type));
    }

    protected function generateIndexName(string $name): string
    {
        if (strlen($name) > 55) {
            $name = md5($name);
        }

        return 'IDX_' . strtoupper($name);
    }

    protected function generateForeignKeyName(string $name): string
    {
        if (strlen($name) > 55) {
            $name = md5($name);
        }

        return 'FK_' . strtoupper($name);
    }
}
