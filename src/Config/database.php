<?php

declare(strict_types=1);

return [
    // 默认数据源
    'default'     => 'mysql',

    // 多数据源配置
    'connections' => [
        'mysql'   => [
            // 数据库类型
            'type'        => 'mysql',
            // 服务器地址
            'hostname'    => '127.0.0.1',
            // 数据库名
            'database'    => 'database',
            // 数据库用户名
            'username'    => 'root',
            // 数据库密码
            'password'    => 'REMOVED',
            // 数据库连接端口
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
