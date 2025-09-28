<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $table = 'documents';
    protected $primaryKey = 'id';
    protected $fillable = [
        'title',
        'description',
        'filename',
        'cid',
        'local_hash'
    ];
}
