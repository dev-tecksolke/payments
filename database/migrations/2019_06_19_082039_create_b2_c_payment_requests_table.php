<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2CPaymentRequestsTable extends Migration {
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		Schema::create('b2_c_payment_requests', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->string('reference_code')->unique();
			$table->string('transID')->unique()->nullable();
			$table->double('amount', 2);
			$table->unsignedBigInteger('phone_number');
			$table->boolean('is_successful')->default(false);
			$table->json('response');
			$table->json('callback')->nullable();
			$table->uuid('user_id');
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		Schema::dropIfExists('b2_c_payment_requests');
	}
}
