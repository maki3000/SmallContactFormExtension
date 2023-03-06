<?php namespace CreationHandicap\ContactFormExtension\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateCreationhandicapContactformextensionExt extends Migration
{
    public function up()
    {
        Schema::create('creationhandicap_contactformextension_ext', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->text('whitelisted')->nullable();
            $table->text('blacklisted')->nullable();
            $table->string('api_link')->nullable();
            $table->string('api_link_params')->nullable();
            $table->text('allowed_countries')->nullable();
            $table->string('redirect_url')->nullable();
            $table->string('failed_message')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('creationhandicap_contactformextension_ext');
    }
}
