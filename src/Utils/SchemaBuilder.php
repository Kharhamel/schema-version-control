<?php
namespace BrainDiminished\SchemaVersionControl\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;

/**
 * Database schema builder.
 *
 * Given a deep associative array supposed to describe a database schema, SchemaBuilder::build will construct a
 * corresponding Schema object.
 */
class SchemaBuilder
{
    /** @var array */
    protected $schemaDesc;

    /**
     * Build an array descriptor into a Schema object
     * @param array $schemaDesc
     * @return Schema
     */
    public function build(array $schemaDesc): Schema
    {
        $this->schemaDesc = $schemaDesc;
        $schema = new Schema();
        foreach ($schemaDesc['tables'] as $name => $tableDesc) {
            $table = $schema->createTable($name);
            $this->buildTable($tableDesc, $table);
        }
        return $schema;
    }

    protected function buildTable(array $tableDesc, Table $table)
    {
        $pk_columns = [];
        foreach ($tableDesc['columns'] as $columnName => $columnDesc) {
            if (!is_array($columnDesc)) {
                $columnDesc = ['type' => $columnDesc];
            }
            if (isset($columnDesc['primary_key'])
                && $columnDesc['primary_key']) {
                $pk_columns[] = $columnName;
            }
            $column = $table->addColumn($columnName, $columnDesc['type']);
            $this->buildColumn($columnDesc, $column);
        }
        if (isset($tableDesc['indexes'])) {
            foreach ($tableDesc['indexes'] as $indexName => $indexDesc) {
                $this->buildIndex($indexDesc, $table, $indexName);
            }
        }
        if (!empty($pk_columns)) {
            $table->setPrimaryKey($pk_columns);
        }
        if (isset($tableDesc['foreign_keys'])) {
            foreach ($tableDesc['foreign_keys'] as $constraintName => $constraintDesc) {
                $this->buildForeignKeyConstraint($constraintDesc, $table, $constraintName);
            }
        }
    }

    protected function buildColumn(array $columnDesc, Column $column)
    {
        if (isset($columnDesc['fixed'])) {
            $column->setFixed($columnDesc['fixed']);
        }
        if (isset($columnDesc['length'])) {
            $column->setLength($columnDesc['length']);
        }
        if (isset($columnDesc['precision'])) {
            $column->setPrecision($columnDesc['precision']);
        }
        if (isset($columnDesc['scale'])) {
            $column->setScale($columnDesc['scale']);
        }
        $column->setNotnull(isset($columnDesc['not_null']) && $columnDesc['not_null']);
        if (isset($columnDesc['default'])) {
            $column->setDefault($columnDesc['default']);
        }
        if (isset($columnDesc['auto_increment'])) {
            $column->setAutoincrement($columnDesc['auto_increment']);
        }
        if (isset($columnDesc['comment'])) {
            $column->setComment($columnDesc['comment']);
        }
        if (isset($columnDesc['custom'])) {
            $column->setCustomSchemaOptions($columnDesc['custom']);
        }
    }

    protected function buildForeignKeyConstraint(array $constraintDesc, Table $table, string $name)
    {
        if (isset($constraintDesc['column'])) {
            $localColumns = [$constraintDesc['column']];
        } else {
            $localColumns = $constraintDesc['columns'];
        }
        $references = $constraintDesc['references'];
        if (is_array($references)) {
            $foreignTable = $references['table'];
            if (isset($references['column'])) {
                $foreignColumns = [$references['column']];
            } else {
                $foreignColumns = $references['columns'];
            }
        } else {
            $foreignTable = $references;
            $foreignColumns = $this->getPrimaryKeyColumns($foreignTable);
            if (!is_array($foreignColumns)) {
                $foreignColumns = [$foreignColumns];
            }
        }
        $options = array_diff_key($constraintDesc, ['column'=>0,'columns'=>0,'references'=>0]);
        $table->addForeignKeyConstraint($foreignTable, $localColumns, $foreignColumns, $options, $name);
    }

    protected function getPrimaryKeyColumns(string $tableName)
    {
        $pkColumns = [];
        $tableDesc = $this->schemaDesc['tables'][$tableName];
        foreach ($tableDesc['columns'] as $columnName => $columnDesc) {
            if (isset($columnDesc['primary_key'])
                && $columnDesc['primary_key']) {
                $pkColumns[] = $columnName;
            }
        }
        return $pkColumns;
    }

    protected function buildIndex(array $indexDesc, Table $table, string $name)
    {
        if (isset($indexDesc['column'])) {
            $columns = [$indexDesc['column']];
        } else {
            $columns = $indexDesc['columns'];
        }
        $options = array_diff_key($indexDesc, ['column'=>0,'columns'=>0]);
        $table->addIndex($columns, $name, [], $options);
    }
}
