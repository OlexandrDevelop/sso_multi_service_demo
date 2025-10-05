<?php

return [
	'auth_base_url' => env('SSO_AUTH_BASE_URL', 'http://localhost:8001'),
	'cookie_name' => env('SSO_COOKIE_NAME', 'sso_token'),
	'cookie_domain' => env('SSO_COOKIE_DOMAIN', null),
	'cookie_samesite' => env('SSO_COOKIE_SAMESITE', 'None'),
	'cookie_secure' => (bool) env('SSO_COOKIE_SECURE', false),
]; 