<?php
/**
 * Created by PhpStorm.
 * User: jwambugu
 * Date: 6/18/19
 * Time: 9:32 AM
 */

namespace TecksolKE\Payment;


class Payment {
	/**
	 * Generate a new STK push request
	 * @param int $phoneNumber
	 * @param int $amount
	 * @param string $referenceCode
	 * @return mixed|string
	 * @throws \Exception
	 */
	public static function stkPush(int $phoneNumber, int $amount, string $referenceCode) {
		try {
			return (array)(new PaymentController())->initiateSTKPush($phoneNumber, $amount, $referenceCode);
		} catch (\Exception $exception) {
			throw new \Exception($exception->getMessage());
		}
	}

	/**
	 * Generate a new b2c request
	 * @param int $phoneNumber
	 * @param int $amount
	 * @param string $referenceCode
	 * @return array
	 * @throws \Exception
	 */
	public function b2c(int $phoneNumber, int $amount, string $referenceCode) {
		try {
			return (array)(new PaymentController())->initiateB2CTransaction($phoneNumber, $amount, $referenceCode);
		} catch (\Exception $exception) {
			throw new \Exception($exception->getMessage());
		}
	}
}