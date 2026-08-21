<?php
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | @copyright (c) 原点 All rights reserved.
// +----------------------------------------------------------------------
// | Author: 原点 <467490186@qq.com>
// +----------------------------------------------------------------------
// | Date: 2026/4/17
// +----------------------------------------------------------------------
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\model\ShareBase;

require __DIR__ . '/../vendor/autoload.php';


$config = [
    'default'     => 'mysql',
    'connections' => [
        'mysql' => [
            // 数据库类型
            'type'            => 'mysql',
            // 服务器地址
            'hostname'        => '127.0.0.1',
            // 数据库名
            'database'        => 'database',
            // 数据库用户名
            'username'        => 'root',
            // 数据库密码
            'password'        => 'REMOVED',
            // 数据库连接端口
            'hostport'        => 3306,
            // 数据库连接参数
            'params'          => [
                // 连接超时3秒
                \PDO::ATTR_TIMEOUT => 3,
            ],
            // 数据库编码默认采用utf8
            'charset'         => 'utf8',
            // 数据库表前缀
            'prefix'          => '',
            // 断线重连
            'break_reconnect' => true,
            // 自定义分页类
            'bootstrap'       => '',
        ],
    ],
];
Db::setConfig($config);

$aa = ShareBase::where('share_id', '=', 2047600338951996096)->find();
$aa->load('shareFiles');
var_dump($aa);

