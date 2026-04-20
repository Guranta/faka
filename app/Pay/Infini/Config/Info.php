<?php
declare(strict_types=1);

/**
 * Infini 支付插件元信息
 * 文档: https://developer.infini.money
 */
return [
    'name' => 'Infini支付',
    'version' => '1.0.0',
    'author' => 'Infini',
    'describe' => 'Infini 数字货币支付网关，支持 USDT/USDC 等加密货币收款',
    'callback' => [
        // 是否启用签名验证（由 Impl/Signature.php 处理）
        \App\Consts\Pay::IS_SIGN => true,
        // 是否启用状态验证
        \App\Consts\Pay::IS_STATUS => true,
        // 回调数据中状态字段名
        \App\Consts\Pay::FIELD_STATUS_KEY => 'status',
        // 状态成功值
        \App\Consts\Pay::FIELD_STATUS_VALUE => 'paid',
        // 回调数据中商户订单号字段名（对应 client_reference）
        \App\Consts\Pay::FIELD_ORDER_KEY => 'client_reference',
        // 回调数据中金额字段名
        \App\Consts\Pay::FIELD_AMOUNT_KEY => 'amount',
        // 回调成功后返回给 Infini 的响应内容
        \App\Consts\Pay::FIELD_RESPONSE => 'success',
    ],
];
