<?php
/**
 * Created by PhpStorm.
 * User: jwambugu
 * Date: 6/18/19
 * Time: 11:31 AM
 */
Route::group([
	'prefix' => 'payments-callbacks',
	'namespace' => 'TecksolKE\Payment',
	'middleware' => 'signed',
], function () {
	// STK Callback
	Route::post('stk', 'CallbackController@stkCallback')->name('payment.stk.callback');
	// B2C Callback
	Route::post('b2c', 'CallbackController@b2cCallback')->name('payment.b2c.callback');

});