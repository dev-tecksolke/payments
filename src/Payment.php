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
}