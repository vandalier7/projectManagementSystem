<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('projects', function (Blueprint $table) {
			$table->boolean('is_system')->default(false)->after('task_default_submission_mode');
		});

		$admin = User::where('system_role', 'admin')->first();

		if (!$admin) {
			return;
		}

		$main = new Project([
			'name' => 'Main',
			'lead_id' => $admin->id,
			'created_by' => $admin->id,
		]);
		$main->is_system = true;
		$main->save();

		foreach (User::pluck('id') as $userId) {
			ProjectMember::firstOrCreate([
				'project_id' => $main->id,
				'user_id' => $userId,
			]);
		}
	}

	public function down(): void
	{
		Schema::table('projects', function (Blueprint $table) {
			$table->dropColumn('is_system');
		});
	}
};