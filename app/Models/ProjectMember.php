<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectMember extends Model
{
	public $timestamps = false;

	protected $fillable = [
		'project_id',
		'user_id',
		'joined_at',
	];

	protected $casts = [
		'joined_at' => 'datetime',
	];

	## The project this membership belongs to.
	public function project()
	{
		return $this->belongsTo(Project::class);
	}

	## The user this membership belongs to.
	public function user()
	{
		return $this->belongsTo(User::class);
	}
}