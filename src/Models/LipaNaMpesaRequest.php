<?php

namespace TecksolKE\Payment;


use App\User;
use Illuminate\Database\Eloquent\Model;

class LipaNaMpesaRequest extends Model {
	/**
	 * Indicates if the IDs are auto-incrementing.
	 *
	 * @var bool
	 */
	public $incrementing = false;

	//set attributes
	protected $casts = [
		'callback' => 'array',
		'is_successful' => 'boolean',
	];
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'id', 'amount', 'reference_code', 'transID', 'phone_number', 'user_id', 'is_successful', 'callback',
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
