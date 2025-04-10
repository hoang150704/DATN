<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name',255);
            $table->string('username', 50)->unique()->index();
            $table->string('email')->unique()->index();
            $table->string('avatar',255)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password',255)->nullable();
            $table->enum('role', ['admin', 'member', 'staff'])->default(User::ROLE_MEMBER); // Thêm role nhân viên
            $table->boolean('is_active')->default(true);
            $table->string('reason',255)->nullable();
            $table->softDeletes();
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();
            $table->string('provider_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
