<?php

declare(strict_types=1);

return [
    'default'     => 'mysql',

    'connections' => [
        'mysql'   => [
            'type'        => 'mysql',
            'hostname'    => '127.0.0.1',
            'database'    => 'database',
            'username'    => 'root',
            'password'    => 'REMOVED',
            'hostport'    => 3306,
            'charset'     => 'utf8mb4',
            'prefix'      => '',
            'autoconnect' => true,
        ],
        'sqlite'  => [
            'type'     => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ],
        'mongodb' => [
            'type'           => 'mongodb',
            'hostname'       => '127.0.0.1',
            'database'       => 'test',
            'username'       => '',
            'password'       => '',
            'hostport'       => '27017',
            'dsn'            => '',
            'params'         => [],
            'charset'        => 'utf8',
            'pk'             => '_id',
            'pk_type'        => 'ObjectID',
            'prefix'         => '',
            'is_replica_set' => false,
        ],
        'oracle'  => [
            'type'     => 'oracle',
            'hostname' => '127.0.0.1',
            'database' => 'ORCL',
            'username' => 'system',
            'password' => '',
            'hostport' => '1521',
            'charset'  => 'AL32UTF8',
            'prefix'   => '',
        ],
    ],
];
