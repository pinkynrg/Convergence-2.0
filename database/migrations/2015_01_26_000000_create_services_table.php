<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateServicesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('services',function(Blueprint $table) {
			$table->increments('id');
			$table->integer('company_id')->unsigned();
			$table->integer('internal_contact_id')->unsigned()->nullable();
			$table->integer('external_contact_id')->unsigned()->nullable();
			$table->string('job_number_internal')->nullable();
			$table->string('job_number_onsite')->nullable();
			$table->string('job_number_remote')->nullable();
			$table->integer('hotel_id')->nullable();
			$table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
			$table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->softDeletes();
		});

		Schema::table('services',function(Blueprint $table) {
			$table->foreign('company_id')->references('id')->on('companies');
			$table->foreign('internal_contact_id')->references('id')->on('company_person');
			$table->foreign('external_contact_id')->references('id')->on('company_person');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('services');
	}

}
