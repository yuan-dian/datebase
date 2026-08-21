<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use yuandian\Database\Db\Builder\Mongo as MongoBuilder;
use yuandian\Database\Db\Connector\Mongo as MongoConnection;
use yuandian\Database\Db\MongoQuery;

echo "=== MongoQuery API Verification ===\n\n";

echo "1. Checking MongoQuery class...\n";
if (class_exists(MongoQuery::class)) {
    echo "   ✓ MongoQuery class exists\n";
} else {
    echo "   ✗ MongoQuery class NOT found\n";
}

echo "\n2. Checking new methods...\n";
$newMethods = [
    'command',
    'cmd',
    'getDistinct',
    'listCollections',
    'aggregate',
    'multiAggregate',
    'inc',
    'dec',
    'collection',
    'typeMap',
    'awaitData',
    'batchSize',
    'exhaust',
    'modifiers',
    'noCursorTimeout',
    'oplogReplay',
    'partial',
    'maxTimeMS',
    'collation',
    'slaveOk',
    'tailable',
    'writeConcern',
    'field',
    'withoutField',
    'skip',
    'limit',
    'order',
    'cursor',
    'getCursor',
    'parseOptions',
    'getFieldsType',
    'getFieldType',
    'getType',
    'getAutoInc',
    'autoInc',
    'getPk'
];
foreach ($newMethods as $method) {
    if (method_exists(MongoQuery::class, $method)) {
        echo "   ✓ MongoQuery::{$method}() exists\n";
    } else {
        echo "   ✗ MongoQuery::{$method}() NOT found\n";
    }
}

echo "\n3. Checking MongoConnection new methods...\n";
$connectorMethods = ['command', 'cmd'];
foreach ($connectorMethods as $method) {
    if (method_exists(MongoConnection::class, $method)) {
        echo "   ✓ MongoConnection::{$method}() exists\n";
    } else {
        echo "   ✗ MongoConnection::{$method}() NOT found\n";
    }
}

echo "\n4. Checking MongoBuilder...\n";
if (class_exists(MongoBuilder::class)) {
    echo "   ✓ MongoBuilder class exists\n";
} else {
    echo "   ✗ MongoBuilder class NOT found\n";
}

echo "\n=== Test Complete ===\n";
