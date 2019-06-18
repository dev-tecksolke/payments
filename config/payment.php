<?php
/**
 * Created by PhpStorm.
 * User: jwambugu
 * Date: 6/18/19
 * Time: 10:26 AM
 */

/**
 * Configures all the mpesa configuration data required in the app
 */
return [
	'uri' => env('MPESA_API_BASE_URI'),
	'consumer_key' => env('MPESA_CONSUMER_KEY'),
	'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
	'c2b' => [
		'account' => env('MPESA_ACCOUNT'),
		'type' => 'c2b',
	],
];