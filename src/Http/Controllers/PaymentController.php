<?php
/**
 * Created by PhpStorm.
 * User: jwambugu
 * Date: 6/18/19
 * Time: 10:10 AM
 */

namespace TecksolKE\Payment;


use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class PaymentController extends Controller {
	private $cacheKey;

	public function __construct() {
		$this->cacheKey = "tecksol_mpesa_access_token";
	}

	/**
	 * Generate the access token for authentication the app.
	 *
	 * @return mixed
	 * @throws \Exception
	 * @throws GuzzleException
	 */
	public function generateAccessToken() {
		// Create the request options
		$options = [
			'json' => [
				'consumer_key' => config('payment.consumer_key'),
				'consumer_secret' => config('payment.consumer_secret'),
			],
		];

		// Check if the key exists
		if (!Cache::has($this->cacheKey)) {

			try {
				$response = $this->makeRequest('oauth/v1/generate', $options);

				if (!$response->success) {
					throw new \Exception('App authentication failed. Check your credentials.');
				}

				return $this->storeToken($response);
			} catch (\Exception $e) {
				throw new \Exception($e->getMessage());
			}
		}
		return Cache::get($this->cacheKey);
	}

	/**
	 * Initiate an stk push transaction
	 * @param int $phoneNumber
	 * @param int $amount
	 * @param string $accountReference
	 * @return mixed|string
	 * @throws \Exception
	 */
	public function initiateSTKPush(int $phoneNumber, int $amount, string $accountReference) {
		// Validate the phone number
		$phoneNumber = $this->validatePhoneNumber($phoneNumber);

		// Create request options
		$options = [
			'phoneNumber' => $phoneNumber,
			'amount' => $amount,
			'accountReference' => $accountReference,
			'account' => config('payment.c2b.account'),
			'callback' => URL::signedRoute('stk.callback'),
		];

		// Create a transaction
		$this->createSTKPushTransaction($phoneNumber, $amount, $accountReference);

		// Make request to the mpesa system
		try {
			return $this->makeRequest('business/v1/c2b/stk/push', $this->setRequestOptions($options));
		} catch (\Exception $exception) {
			throw new \Exception($exception->getMessage());
		} catch (GuzzleException $exception) {
			throw new \Exception($exception->getMessage());
		}
	}

	/**
	 * Create an stk transaction
	 * @param int $phoneNumber
	 * @param int $amount
	 * @param string $accountReference
	 * @throws \Exception
	 */
	private function createSTKPushTransaction(int $phoneNumber, int $amount, string $accountReference) {
		// Create a new query
		$lipaNaMpesa = LipaNaMpesaRequest::query();

		// Check if the transaction exists
		$existingTransaction = $lipaNaMpesa->where('reference_code', $accountReference)
			->where('is_successful', false)
			->first();

		if (!$existingTransaction) {
			$lipaNaMpesa->create([
				'id' => Uuid::uuid4()->toString(),
				'phone_number' => $phoneNumber,
				'amount' => $amount,
				'reference_code' => $accountReference,
				'user_id' => auth()->id(),
			]);
		}
		return;
	}


	/**
	 * Validate the msisdn. Check if it starts with 2547 and the length must be 12 digits.
	 * @param int $phoneNumber
	 * @return int
	 * @throws \Exception
	 */
	private function validatePhoneNumber(int $phoneNumber) {
		// Verify the msisdn starts with 2547
		if (!Str::startsWith($phoneNumber, 2547)) {
			throw new \Exception("The phone number must start with 2547. ${phoneNumber} given.");
		}
		if (strlen($phoneNumber) != 12) {
			throw new \Exception("The phone number must 12 digits.");
		}

		return $phoneNumber;
	}

	/**
	 * Perform the b2c request. Allow users to withdraw from the application.
	 *
	 * @param int $msisdn
	 * @param int $amount
	 *
	 * @param string $id
	 * @param float $fee
	 * @return string
	 * @throws \Exception
	 */
//	public static function b2c(int $msisdn, int $amount, string $id, float $fee) {
//		//generate reference_code
//		$reference_code = (new SystemController())->generateCode();
//
//		// Validate the msisdn
//		$msisdn = (new Mpesa())->validateMSISDN($msisdn);
//
//		// Set the request options
//		$options = [
//			'msisdn' => $msisdn,
//			'amount' => $amount,
//			'callback' => (string)route('withdraw.callback'),
//			'account' => (string)config('mpesa.account'),
//			'referenceCode' => $reference_code,
//		];
//
//		// Make the request
//		try {
//			$response = (new Mpesa())->makeRequest('business/v1/b2c/payment-request',
//				(new Mpesa())->setRequestOptions($options));
//			if ($response->success) {
//				$check = MpesaWithDraw::query()->where('reference_code', $reference_code)->first();
//				if (!$check) {
//					//create a mpesa withdraw statements here
//					MpesaWithDraw::query()->create([
//						'user_id' => $id,
//						'phone_number' => $msisdn,
//						'reference_code' => $reference_code,
//						'amount' => $amount,
//						'fee' => $fee,
//					]);
//				}
//
//				return (object)[
//					'success' => true,
//					'message' => 'Withdraw was successfully',
//				];
//			}
//			return (object)[
//				'success' => false,
//				'message' => 'Withdraw Failed',
//			];
//		} catch (\Exception $e) {
//			Log::error($e->getMessage());
//			throw new \Exception($e->getMessage());
//		} catch (GuzzleException $e) {
//			Log::error($e->getMessage());
//			throw new \Exception($e->getMessage());
//		}
//	}

	/**
	 * Store the token in the cache for 58 minutes.
	 *
	 * @param object $response
	 * @return mixed
	 */
	private function storeToken(object $response) {
		// Extract the response data
		$accessToken = ($response->data->attributes->accessToken);
		$expiresIn = ($response->data->attributes->expiresIn);


		if (!Cache::has($this->cacheKey)) {
			Cache::remember($this->cacheKey, ($expiresIn - 20), function () use ($accessToken) {
				return $accessToken;
			});
		}

		return Cache::get($this->cacheKey);
	}

	/**
	 * Here we set the request options. This includes the authentication headers and sets
	 * the content type to application/json.
	 *
	 * @param array $options
	 * @return array
	 * @throws \Exception
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	private function setRequestOptions(array $options) {
		try {
			return [
				'headers' => [
					'Authorization' => 'Bearer ' . $this->generateAccessToken(),
					'Accept' => 'application/json',
				],
				'json' => $options,
			];
		} catch (\Exception $e) {
			throw new \Exception($e->getMessage(), 500);
		}
	}

	/**
	 * Make all the requests for the API
	 * @param string $uri
	 * @param array $options
	 * @param string $method
	 * @return mixed|string
	 * @throws GuzzleException
	 */
	private function makeRequest(string $uri, array $options, string $method = 'POST') {
		try {
			// Create a new instance of the guzzle client
			$client = new Client([
				'base_uri' => config('payment.uri'),
				'verify' => false,
			]);

			// Make the request
			$response = $client->request($method, $uri, $options);

			return json_decode($response->getBody()->getContents());
		} catch (ServerException $exception) {
			return $exception->getResponse()->getBody()->getContents();
		} catch (ClientException $exception) {
			return $exception->getResponse()->getBody()->getContents();
		}

	}
}