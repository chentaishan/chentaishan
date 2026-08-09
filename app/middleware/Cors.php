<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;

class Cors
{
    /**
     * 允许跨域的来源（请按实际前端域名调整）
     * @var string[]
     */
    private $allowOrigins = [
        'http://localhost:8081',
        'http://localhost:8082',
        'http://localhost:8083',
        'http://localhost:8084',
        'http://121.196.145.119:20012/',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string)$request->header('origin');
        $allowOrigin = in_array($origin, $this->allowOrigins, true) ? $origin : '';

        if (strtoupper($request->method()) === 'OPTIONS') {
            $response = response('', 204);
        } else {
            /** @var Response $response */
            $response = $next($request);
        }

        if ($allowOrigin !== '') {
            $response->header([
                'Access-Control-Allow-Origin'      => $allowOrigin,
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Allow-Methods'     => 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
                'Access-Control-Allow-Headers'     => 'Content-Type,Authorization,X-Requested-With,token,Token',
                'Access-Control-Max-Age'           => '86400',
                'Vary'                             => 'Origin',
            ]);
        }

        return $response;
    }
}
