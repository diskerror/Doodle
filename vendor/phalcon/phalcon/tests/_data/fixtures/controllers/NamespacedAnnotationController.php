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

namespace MyNamespace\Controllers;

use Phalcon\Annotations\Router\Get;

/**
 * Class NamespacedAnnotationController
 */
class NamespacedAnnotationController
{
    #[Get("/")]
    public function indexAction()
    {
    }
}
