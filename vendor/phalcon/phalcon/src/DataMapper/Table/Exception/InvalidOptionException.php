<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 *
 * Implementation of this file has been influenced by AtlasPHP
 *
 * @link    https://github.com/atlasphp/Atlas.Table
 * @license https://github.com/atlasphp/Atlas.Table/blob/1.x/LICENSE.md
 */

declare(strict_types=1);

namespace Phalcon\DataMapper\Table\Exception;

/**
 * Exception when an invalid option is supplied to a method
 */
class InvalidOptionException extends Exception
{
    /**
     * @param string $option
     */
    public function __construct(string $option)
    {
        parent::__construct("Invalid option supplied [$option]");
    }
}
