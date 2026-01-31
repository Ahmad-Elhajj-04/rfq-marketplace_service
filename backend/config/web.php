<?php

use yii\filters\Cors;

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'rfq-marketplace-service',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],

    'modules' => [
        'v1' => [
            'class' => \app\modules\v1\Module::class,
        ],
    ],

    
    'as cors' => [
        'class' => Cors::class,
        'cors' => [
            'Origin' => ['*'],
            'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'Access-Control-Request-Headers' => ['*'],
            'Access-Control-Max-Age' => 86400,
            'Access-Control-Allow-Credentials' => false,
        ],
    ],

    'components' => [

        'request' => [
            'cookieValidationKey' => 'oXfPHProvgQiUo-mUmovZF0lZ_TL3sV_',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],

        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
        ],

        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],

        'user' => [
            'identityClass' => app\models\User::class,
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'jwt' => [
            'class' => \sizeg\jwt\Jwt::class,
            'key' => $params['jwtSecret'],
        ],

        'errorHandler' => [
            'errorAction' => 'site/error',
        ],

        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            'useFileTransport' => true,
        ],

        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],

        'db' => $db,

'urlManager' => [
    'enablePrettyUrl' => true,
    'showScriptName' => false,
    'rules' => [
        // Optional but helpful for Postman/browser preflight
        'OPTIONS <route:.+>' => 'site/options',

        // Default REST controllers (no custom actions)
[
  'class' => 'yii\rest\UrlRule',
  'controller' => [
    'v1/auth',
    'v1/requests',
    'v1/categories',
    'v1/subscriptions',
    'v1/notifications',
  ],
],

        // Requests: add custom route for cancel
        [
            'class' => 'yii\rest\UrlRule',
            'controller' => ['v1/requests'],
            'pluralize' => false,
            'extraPatterns' => [
                'GET mine' => 'mine',
                'POST {id}/cancel' => 'cancel',
            ],
        ],

        // Quotations: custom routes
        [
            'class' => 'yii\rest\UrlRule',
            'controller' => ['v1/quotations'],
            'pluralize' => false,
            'extraPatterns' => [
                'GET mine' => 'mine',
                'GET by-request' => 'by-request',
                'POST {id}/withdraw' => 'withdraw',
                'POST {id}/accept' => 'accept',
                'POST {id}/reject' => 'reject',
            ],
        ],
    ],
],
    ],

    'params' => $params,
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
       
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
       
    ];
}

return $config;
