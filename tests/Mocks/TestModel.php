<?php

namespace Tests\Mocks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use BonsaiCms\Settings\Concerns\SerializableModel;
use BonsaiCms\Settings\Contracts\SerializationWrappable;

class TestModel extends Model implements SerializationWrappable
{
    use SerializableModel;

    protected $table = 'test_models';

    protected $fillable = ['name'];

    public $timestamps = false;

    public static function createTable(): void
    {
        Schema::create('test_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
    }
}
