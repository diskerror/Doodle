<?php

/**
 * This file is part of the Phalcon.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Support\Helper\Arr;

use Phalcon\Traits\Helper\Arr\FilterTrait;

/**
 * Filters an array using array_filter. If a callback is supplied, it will be
 * used.
 */
class Filter
{
    use FilterTrait;

    /**
     * @param array<array-key, mixed> $collection
     * @param callable|null           $method
     *
     * @return array
     */
    public function __invoke(array $collection, callable | null $method = null)
    {
        return $this->toFilter($collection, $method);
    }
}
