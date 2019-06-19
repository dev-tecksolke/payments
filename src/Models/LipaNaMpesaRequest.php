<?php

namespace TecksolKE\Payment;


use App\User;
use Illuminate\Database\Eloquent\Model;

class LipaNaMpesaRequest extends Model {
	// Attributes to be casted to their native types
	protected $casts = [
		'callback' => 'array',
		'response' => 'array',
		'is_successful' => 'boolean',
	];
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'amount', 'reference_code', 'transID', 'phone_number', 'user_id', 'is_successful', 'callback', 'response',
	];

	/**
	 * get user for this
	 * upload files
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function user() {
		return $this->belongsTo(User::class);
	}
}
