<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Di;

/**
 * This interface must be implemented in those classes that uses internally the
 * Phalcon\Di that creates them
 */
interface InjectionAwareInterface
{
    /**
     * Returns the internal dependency injector
     */
    public function getDI(): DiInterface | null;

    /**
     * Sets the dependency injector
     *
     * @param DiInterface $container
     */
    public function setDI(DiInterface $container): void;
}
