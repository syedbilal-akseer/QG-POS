<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesTeam extends Model
{
    use HasFactory;

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function teamAssignments()
    {
        return $this->hasMany(TeamAssignment::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'team_assignments');
    }
}
