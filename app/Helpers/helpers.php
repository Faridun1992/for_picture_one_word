<?php


use Illuminate\Support\Facades\Route;

if (!function_exists('getRoutesListByName')) {
    /**
     * @param bool $toJson
     * @return string
     */
    function getRoutesListByName(bool $toJson = true): string
    {
        $routes = [];

        foreach (Route::getRoutes()->getRoutesByName() as $route_name => $route)
            $routes[$route_name] = $route->uri;

        if ($toJson)
            $routes = json_encode($routes);

        return $routes;
    }
}

if (!function_exists('isProduction')) {
    /**
     * @return string
     */
    function isProduction(): string
    {
        return app()->isProduction();
    }
}

if (!function_exists('error')) {
    /**
     * @param string $msg
     * @param int $code
     */
    function error(string $msg, int $code = 422): void
    {
        abort($code, $msg);
    }
}

if (!function_exists('pluralForm')) {

    /**
     * @param int $n
     * @param array $forms
     * @return mixed
     */
    function pluralForm(int $n, array $forms): mixed
    {
        return $n % 10 == 1 && $n % 100 != 11 ? $forms[0] : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? $forms[1] : $forms[2]);
    }
}
