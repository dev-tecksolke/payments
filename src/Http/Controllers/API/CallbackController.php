<?php
/**
 * Created by PhpStorm.
 * User: jwambugu
 * Date: 6/18/19
 * Time: 11:28 AM
 */

namespace TecksolKE\Payment;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CallbackController extends Controller {
	/**
	 * Handle stk push callback data
	 * @param Request $request
	 * @return
	 * @throws \Exception
	 */
	private function handleSTKCallbackData(Request $request) {
		// Extract the request payload and parse it to json
		$payload = json_decode($request->getContent());

		// Get the reference code use
		$referenceCode = $payload->data->referenceCode;

		$lipaNaMpesa = LipaNaMpesaRequest::query()->where('reference_code', $referenceCode);

		// Check if the transaction exists using the reference code
		if (!$lipaNaMpesa->first()) {
			return [
				'success' => false,
				'message' => 'Unknown transaction.',
			];
		}


		// Check if the transaction was successful
		if (!$payload->success) {
			// Update the transaction, set it as unsuccessful
			$lipaNaMpesa->first()->update([
				'is_successful' => false,
				'callback' => ($payload),
			]);

			return [
				'success' => false,
				'message' => 'Transaction was unsuccessful.',
			];
		}

		// Get the transaction ID
		$transactionID = $payload->data->transactionID;

		// Check if we have the transaction ID already
		$existingTransaction = $lipaNaMpesa->whereIn('transID', [$transactionID])->first();

		if (($existingTransaction)) {
			return [
				'success' => false,
				'message' => 'Duplicate transaction ID.',
			];
		}

		$lipaNaMpesa = LipaNaMpesaRequest::query()->where('reference_code', $referenceCode)
			->where('is_successful', false)->first();

		if (!$lipaNaMpesa) {
			return [
				'success' => false,
				'message' => 'Transaction already processed.',
			];
		}

		// Update the transaction, set it as successful
		$lipaNaMpesa->update([
			'is_successful' => true,
			'transID' => $transactionID,
			'callback' => ($payload),
		]);

		return [
			'success' => true,
			'data' => $payload,
			'user_id' => $lipaNaMpesa->first('user_id')->user_id,
		];
	}

	/**
	 * Return the callback data to the user after processing
	 * @param Request $request
	 * @return array
	 * @throws \Exception
	 */
	public function stkCallback(Request $request) {
		// Extract the callback data
		try {
			return $this->handleSTKCallbackData($request);
		} catch (\Exception $exception) {
			throw new \Exception($exception->getMessage());
		}
	}

	public function b2cCallback(Request $request) {
		info("B2C Callback", $request->all());
	}
}