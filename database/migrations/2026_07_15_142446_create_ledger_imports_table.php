    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        /**
         * Run the migrations.
         */
        public function up(): void
        {
            Schema::create('ledger_imports', function (Blueprint $table) {
                $table->id();
                $table->string('file_name');
                $table->string('file_path');

                $table->enum('status', [
                    'pending',
                    'processing',
                    'completed',
                    'failed',
                ])->default('pending');

                $table->unsignedInteger('total_customers')->default(0);
                $table->unsignedInteger('total_transactions')->default(0);
                $table->unsignedInteger('processed_transactions')->default(0);

                $table->longText('error_log')->nullable();

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamps();
            });
        }

        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::dropIfExists('ledger_imports');
        }
    };
