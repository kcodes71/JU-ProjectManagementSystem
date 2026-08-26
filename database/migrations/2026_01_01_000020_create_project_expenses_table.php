<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_expenses', function (Blueprint $table) {
            $table->id('expense_id');

            $table->foreignId('project_id')
                ->constrained('projects', 'project_id')
                ->cascadeOnDelete();

            $table->foreignId('phase_id')
                ->constrained('phases', 'phase_id')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);

            $table->string('description', 255);

            $table->date('expense_date');

            $table->foreignId('created_by')
                ->constrained('users', 'user_id');

            $table->timestamps();

            $table->index([
                'project_id',
                'phase_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_expenses');
    }
};