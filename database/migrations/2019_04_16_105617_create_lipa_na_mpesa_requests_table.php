<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLipaNaMpesaRequestsTable extends Migration {
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		Schema::create('lipa_na_mpesa_requests', function (Blueprint $table) {
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
		Schema::dropIfExists('lipa_na_mpesa_requests');
	}
}
