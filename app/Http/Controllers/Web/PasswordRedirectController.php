<?php
/**
 *
 */

namespace App\Http\Controllers\Web;


class PasswordRedirectController extends \App\Http\Controllers\Controller
{
    /**
     * Redirect password reset requests back into the api application
     */
    public function __invoke()
    {
        $uri = str_replace("/password", "/auth/password", request()->getUri());
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: " . $uri);
        exit();
    }
}