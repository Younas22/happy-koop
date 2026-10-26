<?php

/*
 * Root front controller.
 *
 * Lets Apache serve the app from the project root instead of /public, so it
 * works without `php artisan serve`:
 *   - local XAMPP:   http://localhost/happy-koop
 *   - live hosting:  https://shop.happykoop.be (subdomain docroot = project root)
 *
 * The root .htaccess sends every request here and blocks direct access to the
 * application files. If the hosting panel lets you point the subdomain's
 * document root at /public, prefer that; this file is then never reached.
 */

require __DIR__.'/public/index.php';
