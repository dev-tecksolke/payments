<?php
/**
 * Created by PhpStorm.
 * User: jwambugu
 * Date: 6/18/19
 * Time: 11:28 AM
 */

namespace TecksolKE\Payment;


use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CallbackController extends Controller {
	/**
	 * Handle stk push callback data
	 * @param Request $request
	 * @param string $table
	 * @return array
	 */
	private function handleCallbackData(Request $request, string $table) {
		// Extract the request payload and parse it to json
		$payload = json_decode($request->getContent());

		// Get the reference code use
		$referenceCode = $payload->data->referenceCode;

		// Find the transaction using the reference code
		$transaction = DB::table($table)->where('reference_code', $referenceCode);

		// Check if the transaction exists using the reference code
		if (!$transaction->first()) {
			return [
				'success' => false,
				'message' => 'Unknown transaction reference code.',
			];
		}

		// Get the user ID
		$userID = $transaction->first('user_id')->user_id;

		// Check if the transaction was successful
		if (!$payload->success) {
			// Update the transaction, set it as unsuccessful
			$this->updateFailedTransaction($table, $referenceCode, $payload);

			return [
				'success' => false,
				'message' => 'Transaction was unsuccessful.',
			];
		}

		// Get the transaction ID
		$transactionID = $payload->data->transactionID;

		// Check if we have the transaction ID already
		$existingTransaction = $transaction->whereIn('transID', [$transactionID])->first();

		if (($existingTransaction)) {
			return [
				'success' => false,
				'message' => 'Duplicate transaction ID.',
			];
		}

		$transaction = DB::table($table)->where('reference_code', $referenceCode)
			->where('is_successful', false);

		if (!$transaction->first()) {
			return [
				'success' => false,
				'message' => 'Transaction already processed.',
			];
		}

		// Update transaction data
		$this->updateSuccessfulTransaction($referenceCode, $transactionID, $payload, $table);

		return [
			'success' => true,
			'data' => $payload,
			'userID' => $userID,
		];
	}

	/**
	 * Update the data for a failed transaction
	 * @param string $table
	 * @param string $referenceCode
	 * @param object $payload
	 */
	private function updateFailedTransaction(string $table, string $referenceCode, object $payload) {
		DB::table($table)->where('reference_code', $referenceCode)
			->where('is_successful', false)
			->update([
				'is_successful' => false,
				'callback' => json_encode($payload),
			]);
	}

	/**
	 * Update successful transaction transaction ID and callback data
	 * @param string $referenceCode
	 * @param string $transactionID
	 * @param object $payload
	 * @param string $table
	 */
	private function updateSuccessfulTransaction(
		string $referenceCode, string $transactionID, object $payload, string $table
	) {
		DB::table($table)->where('reference_code', $referenceCode)
			->where('is_successful', false)->update([
				'is_successful' => true,
				'transID' => $transactionID,
				'callback' => json_encode($payload),
			]);

		return;
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
			// Get the lipa na mpesa table name
			$lipaNaMpesa = new LipaNaMpesaRequest();

			dd($this->handleCallbackData($request, $lipaNaMpesa->getTable()));

		} catch (\Exception $exception) {
			throw new \Exception($exception->getMessage());
		}
	}

	/**
	 * Handle the b2c callback data
	 * @param Request $request
	 * @return array
	 * @throws \Exception
	 */
	public function b2cCallback(Request $request) {
		info("B2C Callback", $request->all());
		// Extract the callback data
		try {
			// Get the b2c table name
			$b2c = new B2CPaymentRequest();

			return $this->handleCallbackData($request, $b2c->getTable());

		} catch (\Exception $exception) {
			throw new \Exception($exception->getMessage());
		}
	}
}