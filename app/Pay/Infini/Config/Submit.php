<?php
declare(strict_types=1);

/**
 * Infini 支付插件 - 后台配置表单定义
 * 每个元素的 name 对应 Config.php 中的键名
 */
return [
    [
        'name' => 'key_id',
        'title' => 'Key ID（公钥）',
        'type' => 'text',
        'placeholder' => '在 Infini 商户后台 → 开发者页面生成的 Public Key',
    ],
    [
        'name' => 'secret_key',
        'title' => 'Secret Key（私钥）',
        'type' => 'text',
        'placeholder' => '创建密钥时仅展示一次，请妥善保管',
    ],
    [
        'name' => 'env',
        'title' => '运行环境',
        'type' => 'select',
        'default' => 'sandbox',
        'options' => [
            ['value' => 'sandbox', 'title' => '沙盒（测试）'],
            ['value' => 'production', 'title' => '生产环境'],
        ],
    ],
    [
        'name' => 'pay_methods',
        'title' => '支付方式',
        'type' => 'select',
        'default' => '1',
        'options' => [
            ['value' => '1', 'title' => '仅加密货币'],
            ['value' => '2', 'title' => '仅卡支付'],
            ['value' => '1,2', 'title' => '加密货币 + 卡支付'],
        ],
    ],
    [
        'name' => 'order_expire',
        'title' => '订单有效期（秒）',
        'type' => 'text',
        'placeholder' => '默认 1800（30分钟），留空使用 Infini 后台默认值',
        'default' => '1800',
    ],
];
