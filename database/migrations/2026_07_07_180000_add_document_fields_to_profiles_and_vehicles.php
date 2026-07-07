<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->string('cnh_file_path')->nullable()->after('cnh_expiry');
            $table->timestamp('cnh_uploaded_at')->nullable()->after('cnh_file_path');
        });

        Schema::table('trucks', function (Blueprint $table) {
            $table->string('crlv_file_path')->nullable()->after('renavam');
            $table->date('crlv_expiry')->nullable()->after('crlv_file_path');
            $table->timestamp('crlv_uploaded_at')->nullable()->after('crlv_expiry');
        });

        Schema::table('trailers', function (Blueprint $table) {
            $table->string('crlv_file_path')->nullable()->after('renavam');
            $table->date('crlv_expiry')->nullable()->after('crlv_file_path');
            $table->timestamp('crlv_uploaded_at')->nullable()->after('crlv_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn(['cnh_file_path', 'cnh_uploaded_at']);
        });

        Schema::table('trucks', function (Blueprint $table) {
            $table->dropColumn(['crlv_file_path', 'crlv_expiry', 'crlv_uploaded_at']);
        });

        Schema::table('trailers', function (Blueprint $table) {
            $table->dropColumn(['crlv_file_path', 'crlv_expiry', 'crlv_uploaded_at']);
        });
    }
};
