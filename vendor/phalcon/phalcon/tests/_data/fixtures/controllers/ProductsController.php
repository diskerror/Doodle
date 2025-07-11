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

namespace Phalcon\Tests\Controllers;

use Phalcon\Annotations\Router\Get;
use Phalcon\Annotations\Router\Route;

/**
 * Class ProductsController
 */
class ProductsController
{
    #[Get("/products")]
    public function indexAction()
    {
    }

    #[Get("/products/edit/{id:[0-9]+}", name: "edit-product")]
    public function editAction($id)
    {
    }

    #[Route("/products/save", methods: ["POST", "PUT"], name: "save-product")]
    public function saveAction()
    {
    }
}
