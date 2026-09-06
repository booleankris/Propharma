<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();
$paginator = new \Illuminate\Pagination\LengthAwarePaginator([], 21, 10, 1);
$compiler = app('blade.compiler');
$compiled = $compiler->compileString('
@forelse($denied as $transfer)
    Loop ran
@empty
    Empty ran
@endforelse
');
echo $compiled;
