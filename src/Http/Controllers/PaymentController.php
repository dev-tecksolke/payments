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
			'callback' => URL::signedRoute('payment.stk.callback'),
		];

		// Make request to the mpesa system
		try {
			$response = $this->makeRequest('business/v1/c2b/stk/push', $this->setRequestOptions($options));

			// Create a transaction
			$this->createSTKPushTransaction($phoneNumber, $amount, $accountReference, $response);

			return $response;
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
	 * @param string $referenceCode
	 * @param object $response
	 */
	private function createSTKPushTransaction(
		int $phoneNumber, int $amount, string $referenceCode, object $response
	) {
		// Create a new query
		$lipaNaMpesa = LipaNaMpesaRequest::query();

		// Check if the transaction exists
		$existingTransaction = $lipaNaMpesa->where('reference_code', $referenceCode)
			->where('is_successful', false)
			->first();

		if (!$existingTransaction) {
			$lipaNaMpesa->create([
				'phone_number' => $phoneNumber,
				'amount' => $amount,
				'reference_code' => $referenceCode,
				'response' => json_encode($response),
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
	 * Imitate a new B2C Transaction. Allows us to fund the users via mobile wallet.
	 * @param int $phoneNumber
	 * @param int $amount
	 * @param string $referenceCode
	 * @return mixed|string
	 * @throws \Exception
	 */
	public function initiateB2CTransaction(int $phoneNumber, int $amount, string $referenceCode) {
		// Validate the phone number
		try {
			$msisdn = $this->validatePhoneNumber($phoneNumber);
		} catch (\Exception $exception) {
			throw new \Exception($exception->getMessage());
		}

		// Set the request options
		$options = [
			'msisdn' => $msisdn,
			'amount' => $amount,
			'callback' => URL::signedRoute('payment.b2c.callback'),
			'account' => config('payment.b2c.account'),
			'referenceCode' => $referenceCode,
		];
		try {
			$response = $this->makeRequest('business/v1/b2c/payment-request', $this->setRequestOptions($options));

			// Create a transaction
			$this->createB2CTransaction($phoneNumber, $amount, $referenceCode, $response);

			return $response;
		} catch (GuzzleException $exception) {
			throw new \Exception($exception->getMessage());
		}
	}

	/**
	 * Store the data for the b2c transaction in the DB
	 * @param int $phoneNumber
	 * @param int $amount
	 * @param string $referenceCode
	 * @param object $response
	 */
	private function createB2CTransaction(
		int $phoneNumber, int $amount, string $referenceCode, object $response
	) {
		// Create a new query
		$b2cTransaction = B2CPaymentRequest::query();

		// Check if the transaction exists
		$existingTransaction = $b2cTransaction->where('reference_code', $referenceCode)
			->where('is_successful', false)
			->first();

		if (!$existingTransaction) {
			$b2cTransaction->create([
				'phone_number' => $phoneNumber,
				'amount' => $amount,
				'reference_code' => $referenceCode,
				'response' => json_encode($response),
				'user_id' => auth()->id(),
			]);
		}
		return;
	}

	/**
	 * Store the token in the cache. This helps us to make consecutive requests faster
	 * without the need to generate a fresh access token.
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