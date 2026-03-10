<?php

// Load Yii before Composer autoloader to avoid redeclaration issues
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';

// Load Craft class (extends Yii) so Craft:: static calls work in tests
require dirname(__DIR__) . '/vendor/craftcms/cms/src/Craft.php';

require dirname(__DIR__) . '/vendor/autoload.php';

// Bootstrap a minimal Yii application so model validation and
// Craft static method calls work without the full Craft environment
new \yii\console\Application([
    'id' => 'craft-dispatch-test',
    'basePath' => dirname(__DIR__),
]);
