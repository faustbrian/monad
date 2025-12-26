<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\CodingStandard\Rector\Factory;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\DeadCode\Rector\Stmt\RemoveUnreachableStatementRector;
use Rector\EarlyReturn\Rector\If_\RemoveAlwaysElseRector;
use Rector\Php83\Rector\Class_\ReadOnlyAnonymousClassRector;

return Factory::create(
    paths: [__DIR__.'/src', __DIR__.'/tests'],
    skip: [
        RemoveUnreachableStatementRector::class => [__DIR__.'/tests'],
        ReadOnlyAnonymousClassRector::class => [__DIR__.'/tests/Option/LazyOptionTest.php'],
        RemoveNonExistingVarAnnotationRector::class => [__DIR__.'/src/Either/Right.php'],
        FlipTypeControlToUseExclusiveTypeRector::class => [__DIR__.'/src/Option/None.php'],
        RemoveAlwaysElseRector::class => [__DIR__.'/src/Option/Option.php'],
    ],
);
