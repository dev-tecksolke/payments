<?php

namespace TecksolKE\Payment;

use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider {
	/**
	 * Register services.
	 *
	 * @return void
	 */
	public function register() {
		// Register the simulate singleton
		$this->app->singleton(Payment::class, function (){
			return new Payment();
		});

		// Merge the package config
		$this->mergeConfigFrom(__DIR__ . '/../config/payment.php', 'payment');
	}

	/**
	 * Bootstrap services.
	 *
	 * @return void
	 */
	public function boot() {
		$this->publishes([
			__DIR__ . '/../config/payment.php' => 'config/payment.php',

		]);

		$this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
		$this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
	}
}
